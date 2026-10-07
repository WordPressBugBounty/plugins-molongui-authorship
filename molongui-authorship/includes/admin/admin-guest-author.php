<?php
/*!
 * Handles guest author functionality within the Molongui Authorship plugin.
 *
 * This file defines the features related to guest authors, including creating, retrieving, and managing guest authors
 * in a WordPress environment. It provides utility functions for handling guest author data in posts and extends the
 * functionality of traditional WordPress authorship by allowing non-registered users to be associated with content.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Author;
use Molongui\Authorship\Authors;
use Molongui\Authorship\Avatar;
use Molongui\Authorship\Author_Meta_Fields;
use Molongui\Authorship\Common\Modules\Media_Picker;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Guest_Author;
use Molongui\Authorship\Post_Authorship;
use Molongui\Authorship\Settings;
use Molongui\Authorship\Social;

defined( 'ABSPATH' ) || exit;  

class Admin_Guest_Author {

	const DELETE_CONFIRM_QUERY_ARG = 'molongui_guest_delete_confirmation';

	const DELETE_EXECUTE_ACTION = 'molongui_delete_guest_authors';

	const DELETE_BULK_ACTION = 'molongui_delete_guest_authors';

	private static $guest_deletion_policies_applied = array();

	private static $deleting_guest_objects = array();

	private $post_type;

	private $javascript        = '/assets/js/edit-guest.d257.min.js';
	private $legacy_javascript = '';
	private $stylesheet        = '';
	private $stylesheet_ltr    = '/assets/js/edit-guest.xxxx.min.css';
	private $stylesheet_rtl    = '/assets/js/edit-guest-rtl.xxxx.min.css';

	public function __construct() {
		$this->post_type = Guest_Author::get_post_type();

		add_filter( 'pre_trash_post', array( $this, 'prevent_guest_author_trash' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'cleanup_authorship_before_guest_deletion' ) );
		add_action( 'deleted_post', array( $this, 'after_guest_deleted' ), 10, 2 );

		if ( Settings::is_enabled( 'guest-author' ) ) {
			add_filter( 'post_updated_messages', array( $this, 'custom_messages' ) );
			add_filter( 'bulk_post_updated_messages', array( $this, 'custom_bulk_messages' ), 10, 2 );
			add_action( 'admin_menu', array( $this, 'remove_menu_item' ) );

			add_filter( 'wp_insert_post_data', array( $this, 'filter_cpt_title' ), PHP_INT_MAX, 2 );
			add_action( 'save_post_' . $this->post_type, array( $this, 'on_save' ) );
			add_filter( 'removable_query_args', array( $this, 'add_removable_arg' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_filter( 'wp_untrash_post_status', array( $this, 'restore_previous_status_on_untrash' ), 10, 3 );

			add_action( 'transition_post_status', array( $this, 'maybe_update_guest_count' ), 10, 3 );

			add_action( 'admin_enqueue_scripts', array( $this, 'set_assets' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 20 );
			add_filter( 'authorship/edit_guest_script_params', array( $this, 'localize_admin_scripts' ) );

			add_filter( 'views_edit-' . $this->post_type, array( $this, 'remove_mine_filter' ) );
			add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_list_columns' ) );
			add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'fill_list_columns' ), 5, 2 );

			add_filter( 'post_row_actions', array( $this, 'remove_view_link' ) );
			add_filter( 'post_row_actions', array( $this, 'replace_trash_row_action' ), 20, 2 );

			add_action( 'admin_head', array( $this, 'quick_edit_add_title_field' ) );
			add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_add_custom_fields' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'hide_status_fields_from_inline_edit' ) );
			add_action( 'admin_footer', array( $this, 'quick_edit_populate_custom_fields' ) );
			add_action( 'save_post_' . $this->post_type, array( $this, 'quick_edit_save_custom_fields' ), 10, 2 );

			add_filter( 'bulk_actions-' . 'edit-' . $this->post_type, array( $this, 'remove_bulk_edit_action' ) );
			add_filter( 'handle_bulk_actions-edit-' . $this->post_type, array( $this, 'handle_delete_bulk_action' ), 5, 3 );

			add_action( 'admin_head', array( $this, 'remove_media_buttons' ) );
			add_action( 'admin_head', array( $this, 'remove_preview_button' ) );
			add_action( 'edit_form_after_title', array( $this, 'add_top_section_after_title' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_filter( 'admin_post_thumbnail_html', array( $this, 'filter_profile_picture_metabox_html' ), 10, 3 );
			add_action( 'post_submitbox_misc_actions', array( $this, 'render_save_box_summary' ) );
			add_filter( 'postbox_classes_' . $this->post_type . '_submitdiv', array( $this, 'add_save_metabox_class' ) );
			add_filter( 'authorship/admin/guest/convert/metabox', array( $this, 'hide_convert_metabox' ), 9 );
			add_action( 'admin_footer-post.php', array( $this, 'replace_edit_screen_trash_link' ) );

			add_action( 'load-edit.php', array( $this, 'maybe_render_delete_confirmation_screen' ), 1 );
			add_action( 'admin_post_' . self::DELETE_EXECUTE_ACTION, array( $this, 'handle_confirmed_deletion' ) );
		}
	}

	private function is_guest_author_screen() {

		$screen = get_current_screen();

		if ( empty( $screen->id ) ) {
			return false;
		}

		return in_array(
			$screen->id,
			array(
				'edit-' . $this->post_type,
				$this->post_type,
			),
			true
		);
	}

	public function set_assets() {

		if ( ! $this->is_guest_author_screen() ) {
			return;
		}

		$this->stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . (
			is_rtl()
				? $this->stylesheet_rtl
				: $this->stylesheet_ltr
			);

		$this->stylesheet = apply_filters(
			'authorship/edit_guest/styles',
			$this->stylesheet
		);

		$this->javascript = MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript;

		$legacy_javascript = apply_filters_deprecated(
			'authorship/edit_guest/script',
			array( $this->javascript ),
			'5.3.0'
		);

		$this->legacy_javascript = '';

		if (
			is_string( $legacy_javascript )
			&&
			'' !== $legacy_javascript
			&&
			$this->javascript !== $legacy_javascript
		) {
			$this->legacy_javascript = $legacy_javascript;
		}
	}

	public function custom_messages( $msg ) {
		$msg[ $this->post_type ] = array(
			0  => '',                                                    
			1  => __( 'Guest author updated.', 'molongui-authorship' ),
			2  => 'Custom field updated.',                               
			3  => 'Custom field deleted.',                               
			4  => __( 'Guest author updated.', 'molongui-authorship' ),
			5  => __( 'Guest author restored to revision', 'molongui-authorship' ),
			6  => __( 'Guest author published.', 'molongui-authorship' ),
			7  => __( 'Guest author saved.', 'molongui-authorship' ),
			8  => __( 'Guest author submitted.', 'molongui-authorship' ),
			9  => __( 'Guest author scheduled.', 'molongui-authorship' ),
			10 => __( 'Guest author draft updated.', 'molongui-authorship' ),
		);

		return $msg;
	}


	public function custom_bulk_messages( $bulk_messages, $bulk_counts ) {
		if ( ! isset( $bulk_messages[ $this->post_type ] ) ) {
			$bulk_messages[ $this->post_type ] = array();
		}

		$deleted_count = isset( $bulk_counts['deleted'] ) ? absint( $bulk_counts['deleted'] ) : 0;

		$bulk_messages[ $this->post_type ]['deleted'] = _n(
			'%s guest author permanently deleted.',
			'%s guest authors permanently deleted.',
			$deleted_count,
			'molongui-authorship'
		);

		return $bulk_messages;
	}

	public function remove_menu_item() {
		$menu_level = Settings::get( 'dashboard_guest_authors_menu_location' );

		$slug = 'edit.php?post_type=' . $this->post_type;

		if ( ! current_user_can( 'edit_others_pages' ) and ! current_user_can( 'edit_others_posts' ) ) {
			if ( 'top' !== $menu_level ) {
				if ( 'users.php' === $menu_level ) {
					$menu_level = 'profile.php';
				}

				remove_submenu_page( $menu_level, $slug );
			} else {
				remove_menu_page( $slug );
			}
		}
	}


	public function filter_cpt_title( $data, $postarr ) {
		if ( $data['post_type'] != $this->post_type ) {
			return $data;
		}

		if ( $postarr['ID'] == null or empty( $_POST ) ) {
			return $data;
		}

		if ( ! isset( $_POST['molongui_authorship_guest_nonce'] ) or ! wp_verify_nonce( $_POST['molongui_authorship_guest_nonce'], 'molongui_authorship_guest' ) ) {
			return $data;
		}

		$first_name = isset( $_POST['_molongui_guest_author_first_name'] )
			? sanitize_text_field( wp_unslash( $_POST['_molongui_guest_author_first_name'] ) )
			: '';

		$last_name = isset( $_POST['_molongui_guest_author_last_name'] )
			? sanitize_text_field( wp_unslash( $_POST['_molongui_guest_author_last_name'] ) )
			: '';

		$display_name = isset( $_POST['_molongui_guest_author_display_name'] )
			? sanitize_text_field( wp_unslash( $_POST['_molongui_guest_author_display_name'] ) )
			: '';

		if ( '' !== $display_name ) {
			$post_title = $display_name;
		} else {
			$post_title = trim( $first_name . ' ' . $last_name );
		}

		if ( '' !== $post_title ) {
			$data['post_title'] = $post_title;
		}

		$data['post_name'] = '';

		return $data;
	}

	public function on_save( $post_id ) {

		if ( ! isset( $_POST['molongui_authorship_guest_nonce'] ) or ! wp_verify_nonce( $_POST['molongui_authorship_guest_nonce'], 'molongui_authorship_guest' ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return $post_id;
		}

		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}
		}


		$is_pro = Plugin::has_pro();

		foreach ( (array) Social::get( 'enabled' ) as $id => $network ) {

			if ( ! $is_pro && ! empty( $network['premium'] ) ) {
				continue;
			}

			$key = Author::GUEST_META_PREFIX . $id;

			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		$inputs = array(
			'_molongui_guest_author_first_name',
			'_molongui_guest_author_last_name',
			'_molongui_guest_author_display_name',
			'_molongui_guest_author_name_prefix',
			'_molongui_guest_author_name_suffix',
			'_molongui_guest_author_credentials',
			'_molongui_guest_author_professional_headline',
			'_molongui_guest_author_job',
			'_molongui_guest_author_company',
			'_molongui_guest_author_department',
			'_molongui_guest_author_location',
			'_molongui_guest_author_mail',
			'_molongui_guest_author_phone',
		);

		foreach ( $inputs as $input ) {
			$value = isset( $_POST[ $input ] )
				? sanitize_text_field( wp_unslash( $_POST[ $input ] ) )
				: '';

			if ( '' !== $value ) {
				update_post_meta( $post_id, $input, $value );
			} else {
				delete_post_meta( $post_id, $input );
			}
		}

		$urls = array(
			'_molongui_guest_author_web',
			'_molongui_guest_author_company_link',
		);
		foreach ( $urls as $url ) {
			if ( ! empty( $_POST[ $url ] ) ) {
				update_post_meta( $post_id, $url, sanitize_url( $_POST[ $url ] ) );
			} else {
				delete_post_meta( $post_id, $url );
			}
		}

		$checkboxes = array(
			'_molongui_guest_author_archived',
		);
		foreach ( $checkboxes as $checkbox ) {
			if ( isset( $_POST[ $checkbox ] ) ) {
				update_post_meta( $post_id, $checkbox, sanitize_text_field( $_POST[ $checkbox ] ) );
			} else {
				delete_post_meta( $post_id, $checkbox );
			}
		}

		if ( Settings::is_enabled( 'author-box' ) ) {

			$box_display = 'default';

			if ( $is_pro && isset( $_POST['_molongui_guest_author_box_display'] ) ) {

				$submitted_display = sanitize_key(
					wp_unslash( $_POST['_molongui_guest_author_box_display'] )
				);

				if (
					in_array(
						$submitted_display,
						array( 'default', 'show', 'hide' ),
						true
					)
				) {
					$box_display = $submitted_display;

					update_post_meta(
						$post_id,
						'_molongui_guest_author_box_display',
						$box_display
					);
				}
			}

			if ( isset( $_POST['_molongui_guest_author_custom_link'] ) ) {
				update_post_meta(
					$post_id,
					'_molongui_guest_author_custom_link',
					esc_url_raw(
						wp_unslash( $_POST['_molongui_guest_author_custom_link'] )
					)
				);
			}

			if ( 'hide' !== $box_display ) {

				if ( isset( $_POST['_molongui_guest_author_box_meta_visibility'] ) && is_array( $_POST['_molongui_guest_author_box_meta_visibility'] ) ) {
					Author_Meta_Fields::update_author_visibility(
						new Author( $post_id, 'guest' ),
						wp_unslash( $_POST['_molongui_guest_author_box_meta_visibility'] )
					);
				}

				$box_checkboxes = array(
					'_molongui_guest_author_show_icon_mail',
					'_molongui_guest_author_show_icon_web',
					'_molongui_guest_author_show_icon_phone',
				);

				foreach ( $box_checkboxes as $meta_key ) {
					if ( isset( $_POST[ $meta_key ] ) ) {
						update_post_meta(
							$post_id,
							$meta_key,
							'1'
						);
					} else {
						delete_post_meta( $post_id, $meta_key );
					}
				}
			}
		}

		add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99, 2 );

		do_action( 'authorship/guest/save', $post_id, $_POST );
	}

	public function add_notice_query_var( $location, $post_id ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );

		$author      = new Author( $post_id, 'guest' );
		$name_exists = $author->is_display_name_available();

		if ( $name_exists ) {
			switch ( $name_exists ) {
				case 'user':
					return add_query_arg( array( 'authorship_guest_save' => 'user_alert' ), $location );
					break;
				case 'guest':
					return add_query_arg( array( 'authorship_guest_save' => 'guest_alert' ), $location );
					break;
				case 'both':
					return add_query_arg( array( 'authorship_guest_save' => 'both_alert' ), $location );
					break;
			}
		}

		return $location;
	}

	public function add_removable_arg( $args ) {
		array_push( $args, 'authorship_guest_save' );
		return $args;
	}

	public function admin_notices() {
		if ( ! isset( $_GET['authorship_guest_save'] ) ) {
			return;
		}

		switch ( $_GET['authorship_guest_save'] ) {
			case 'user_alert':
				$message = esc_html__( 'There is a registered WordPress user with the same display name. You might want to address that.', 'molongui-authorship' );
				break;

			case 'guest_alert':
				$message = esc_html__( 'There is another guest author with the same display name. You might want to address that.', 'molongui-authorship' );
				break;

			case 'both_alert':
				$message = esc_html__( 'There is a registered WordPress user and another guest author with the same display name. You might want to address that.', 'molongui-authorship' );
				break;

			default:
				$message = '';
				break;
		}

		if ( empty( $message ) ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo $message; ?></p>
		</div>
		<?php
	}

	public function restore_previous_status_on_untrash( $new_status, $post_id, $previous_status ) {

		$post = get_post( $post_id );

		if (
			! $post instanceof \WP_Post
			||
			$this->post_type !== $post->post_type
		) {
			return $new_status;
		}

		return ! empty( $previous_status )
			? $previous_status
			: $new_status;
	}

	public function prevent_guest_author_trash( $trash, $post ) {
		if ( $post instanceof \WP_Post && $this->post_type === $post->post_type ) {
			return false;
		}

		return $trash;
	}

	public function cleanup_authorship_before_guest_deletion( $guest_id ) {
		$guest_id = absint( $guest_id );
		$guest    = get_post( $guest_id );

		if ( ! $guest instanceof \WP_Post || $this->post_type !== $guest->post_type ) {
			return;
		}

		self::$deleting_guest_objects[ $guest_id ] = $guest;

		if ( ! empty( self::$guest_deletion_policies_applied[ $guest_id ] ) ) {
			return;
		}

		$result = Post_Authorship::apply_guest_deletion_action(
			$guest_id,
			'remove',
			'',
			'programmatic_guest_author_deletion'
		);

		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Unable to delete author', 'molongui-authorship' ),
				array( 'response' => 500 )
			);
		}

		self::$guest_deletion_policies_applied[ $guest_id ] = true;
	}

	public function after_guest_deleted( $guest_id, $post = null ) {
		$guest_id = absint( $guest_id );
		$guest    = $post instanceof \WP_Post ? $post : ( isset( self::$deleting_guest_objects[ $guest_id ] ) ? self::$deleting_guest_objects[ $guest_id ] : null );

		if ( ! $guest instanceof \WP_Post || $this->post_type !== $guest->post_type ) {
			return;
		}

		unset( self::$deleting_guest_objects[ $guest_id ], self::$guest_deletion_policies_applied[ $guest_id ] );

		self::update_guest_count();

		do_action( 'authorship/admin/guest/deleted', $guest_id, $guest );
	}

	public function maybe_update_guest_count( $new_status, $old_status, $post ) {
		if ( $this->post_type === $post->post_type and ( ( $new_status === 'publish' and $old_status !== 'publish' ) or ( $new_status !== 'publish' and $old_status === 'publish' ) ) ) {
			self::update_guest_count();
		}
	}


	public function register_admin_scripts() {

		if ( ! $this->is_guest_author_screen() ) {
			return;
		}

		if ( Assets::is_readable( $this->javascript ) ) {
			Assets::register_script(
				$this->javascript,
				'edit_guest_core'
			);
		}

		if ( ! empty( $this->legacy_javascript ) && Assets::is_readable( $this->legacy_javascript ) ) {
			Assets::register_script(
				$this->legacy_javascript,
				'edit_guest'
			);
		}
	}

	public function enqueue_admin_scripts() {

		if ( ! $this->is_guest_author_screen() ) {
			return;
		}

		$screen = get_current_screen();

		if (
			! empty( $screen->id )
			&& $this->post_type === $screen->id
			&& Settings::is_enabled( 'local-avatar' )
			&& current_user_can( 'upload_files' )
		) {
			Media_Picker::enqueue();
		}

		if ( Assets::is_readable( $this->javascript ) ) {
			Assets::enqueue_script(
				$this->javascript,
				'edit_guest_core',
				true
			);
		}

		if ( ! empty( $this->legacy_javascript ) && Assets::is_readable( $this->legacy_javascript ) ) {
			Assets::enqueue_script(
				$this->legacy_javascript,
				'edit_guest',
				true
			);
		}
	}

	public function localize_admin_scripts() {
		$params = array(
		);

		return apply_filters( 'authorship/edit_guest/script_params', $params );
	}


	public function remove_mine_filter( $views ) {
		unset( $views['mine'] );

		return $views;
	}

	public function add_list_columns( $columns ) {
		unset( $columns['title'] );
		unset( $columns['date'] );
		unset( $columns['thumbnail'] );

		$new_cols = array(
			'guestAuthorPic'      => __( 'Avatar', 'molongui-authorship' ),
			'title'               => __( 'Name', 'molongui-authorship' ),
			'guestDisplayBox'     => __( 'Box', 'molongui-authorship' ),
			'guestAuthorBio'      => __( 'Bio', 'molongui-authorship' ),
			'guestAuthorMail'     => __( 'Email', 'molongui-authorship' ),
			'guestAuthorPhone'    => __( 'Phone', 'molongui-authorship' ),
			'guestAuthorUrl'      => __( 'URL', 'molongui-authorship' ),
			'guestAuthorJob'      => __( 'Job', 'molongui-authorship' ),
			'guestAuthorCia'      => __( 'Co.', 'molongui-authorship' ),
			'guestAuthorCiaUrl'   => __( 'Co. URL', 'molongui-authorship' ),
			'guestAuthorSocial'   => __( 'Social Profiles', 'molongui-authorship' ),
			'guestAuthorEntries'  => __( 'Entries', 'molongui-authorship' ),
			'guestAuthorArchived' => '<span class="dashicons dashicons-archive"></span>',
			'guestAuthorId'       => __( 'ID', 'molongui-authorship' ),
		);

		if ( ! Settings::is_enabled( 'local-avatar' )
			&& ! Settings::is_enabled( 'gravatar' )
			&& ! Avatar::has_custom_default()
		) {
			unset( $new_cols['guestAuthorPic'] );
		}

		if ( ! Settings::is_enabled( 'author-box' ) ) {
			unset( $new_cols['guestDisplayBox'] );
		}

		if ( ! Settings::get( 'social_profiles_enabled' ) ) {
			unset( $new_cols['guestAuthorSocial'] );
		}

		if ( 'trash' == get_query_var( 'post_status' ) ) {
			unset( $new_cols['guestAuthorEntries'] );
		}

		return array_merge( $columns, $new_cols );
	}

	public function fill_list_columns( $column, $ID ) {
		$value  = '';
		$author = new Author( $ID, 'guest' );

		if ( 'guestAuthorPic' === $column ) {
			echo $author->get_avatar( array( 60, 60 ) );
			return;
		}

		elseif ( 'guestDisplayBox' == $column ) {
			$value = $author->get_box_display();

			switch ( $value ) {
				case 'show':
					$icon = 'visibility';
					$tip  = __( 'Visible', 'molongui-authorship' );
					break;

				case 'hide':
					$icon = 'hidden';
					$tip  = __( 'Hidden', 'molongui-authorship' );
					break;

				default:
					$icon = 'admin-generic';
					$tip  = __( 'Visibility depends on global plugin settings', 'molongui-authorship' );
					break;
			}

			$html  = '<div id="box_display_' . $ID . '" data-display-box="' . $value . '">';
			$html .= '<div class="m-tooltip">';
			$html .= '<span class="dashicons dashicons-' . $icon . '"></span>';
			$html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">' . $tip . '</span>';
			$html .= '</div>';
			$html .= '</div>';

			echo $html;
			return;
		}

		elseif ( 'guestAuthorEntries' == $column ) {
			$html   = '';
			$values = $author->get_post_counts();

			foreach ( Settings::enabled_post_types( 'guest-author', 'object' ) as $post_type ) {
				$link = admin_url( 'edit.php?post_type=' . $post_type['id'] . '&guest=' . $ID );

				if ( isset( $values[ $post_type['id'] ] ) and $values[ $post_type['id'] ] > 0 ) {
					$html .= '<div><a href="' . $link . '">' . $values[ $post_type['id'] ] . ' ' . $post_type['label'] . '</a></div>';
				}
			}

			if ( ! $html ) {
				$html = __( 'None' );
			}

			echo $html;
			return;
		}

		elseif ( 'guestAuthorSocial' === $column ) {

			$social_profiles = $author->get_social();

			foreach ( $social_profiles as $network => $url ) {
				?>
				<div class="m-tooltip">
					<a	href="<?php echo esc_url( $url ); ?>"
						  target="_blank"
						  rel="noopener noreferrer"
					>
						<i	class="m-a-icon-<?php echo esc_attr( sanitize_html_class( $network ) ); ?>"
							  aria-hidden="true"
						></i>
					</a>

					<span class="m-tooltip__text m-tooltip__top">
						<?php echo esc_html( $url ); ?>
					</span>
				</div>
				<?php
			}

			return;
		}

		elseif ( 'guestAuthorArchived' === $column ) {
			if ( $author->is_archived() ) {
				$tip = __( 'Archived author', 'molongui-authorship' );

				$html  = '<div class="m-tooltip">';
				$html .= '<span class="dashicons dashicons-yes"></span>';
				$html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w50">' . $tip . '</span>';
				$html .= '</div>';

				echo $html;
			}
			return;
		}

		elseif ( 'guestAuthorId' === $column ) {
			echo $ID;
			return;
		}

		elseif ( 'guestAuthorMail' === $column ) {
			$email = $author->get_email();

			if ( empty( $email ) ) {
				echo '-';
				return;
			}

			printf(
				'<a href="mailto:%1$s">%2$s</a>',
				esc_attr( $email ),
				esc_html( $email )
			);

			return;
		}

		elseif ( 'guestAuthorBio' === $column ) {
			$value = $author->get_description();
		} elseif ( 'guestAuthorPhone' === $column ) {
			$value = $author->get_meta( 'phone' );
		} elseif ( $column == 'guestAuthorJob' ) {
			$value = $author->get_meta( 'job' );
		} elseif ( $column == 'guestAuthorCia' ) {
			$value = $author->get_meta( 'company' );
		}

		if ( ! empty( $value ) ) {
			$html  = '<div class="m-tooltip">';
			$html .= '<i class="m-a-icon-ok"></i>';
			$html .= '<span class="m-tooltip__text m-tooltip__top' . ( 'guestAuthorBio' === $column ? ' m-tooltip__w400' : ' m-tooltip__w100' ) . '">' . esc_html( $value ) . '</span>';
			$html .= '</div>';

			echo $html;
			return;
		}
		elseif ( $column == 'guestAuthorUrl' ) {
			$value = $author->get_website();
		} elseif ( $column == 'guestAuthorCiaUrl' ) {
			$value = $author->get_meta( 'company_link' );
		}

		if ( ! empty( $value ) ) {
			$html  = '<div class="m-tooltip">';
			$html .= '<a href="' . esc_url( $value ) . '" target="_blank">';
			$html .= '<i class="m-a-icon-ok"></i>';
			$html .= '</a>';
			$html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">' . esc_url( $value ) . '</span>';
			$html .= '</div>';

			echo $html;
			return;
		} else {
			echo '-';
			return;
		}
	}


	public function remove_view_link( $actions ) {
		if ( ! apply_filters( 'authorship/guest/row_actions/remove_view_link', true ) ) {
			return $actions;
		}

		if ( $this->post_type == get_post_type() ) {
			unset( $actions['view'] );
		}

		return $actions;
	}


	public function replace_trash_row_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || $this->post_type !== $post->post_type ) {
			return $actions;
		}

		unset( $actions['trash'], $actions['delete'] );

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			$actions['delete'] = sprintf(
				'<a href="%1$s" class="submitdelete" aria-label="%2$s">%3$s</a>',
				esc_url( self::get_delete_confirmation_url( array( $post->ID ) ) ),
				esc_attr( sprintf( __( 'Delete &#8220;%s&#8221;', 'molongui-authorship' ), get_the_title( $post ) ) ),
				esc_html__( 'Delete', 'molongui-authorship' )
			);
		}

		return $actions;
	}

	public function replace_edit_screen_trash_link() {
		$screen = get_current_screen();

		if ( ! isset( $screen->post_type ) || $this->post_type !== $screen->post_type ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
			return;
		}

		$url   = self::get_delete_confirmation_url( array( $post_id ) );
		$label = __( 'Delete', 'molongui-authorship' );
		?>
		<script type="text/javascript">
			jQuery(function($) {
				var $deleteLink = $('#delete-action a.submitdelete');

				if (!$deleteLink.length) {
					return;
				}

				$deleteLink.attr('href', <?php echo wp_json_encode( $url ); ?>).text(<?php echo wp_json_encode( $label ); ?>);
			});
		</script>
		<?php
	}

	public static function get_delete_confirmation_url( $guest_ids ) {
		$guest_ids = self::normalize_guest_ids( $guest_ids );

		if ( empty( $guest_ids ) ) {
			return '';
		}

		$url = add_query_arg(
			array(
				'post_type'                       => Guest_Author::get_post_type(),
				self::DELETE_CONFIRM_QUERY_ARG    => '1',
				'guest_ids' => implode( ',', $guest_ids ),
			),
			admin_url( 'edit.php' )
		);

		return add_query_arg(
			'_wpnonce',
			wp_create_nonce( self::get_delete_confirmation_nonce_action( $guest_ids ) ),
			$url
		);
	}

	private static function get_delete_confirmation_nonce_action( $guest_ids ) {
		$guest_ids = self::normalize_guest_ids( $guest_ids );

		return 'molongui_confirm_guest_author_deletion_' . implode( '_', $guest_ids );
	}

	private static function get_delete_execution_nonce_action( $guest_ids ) {
		$guest_ids = self::normalize_guest_ids( $guest_ids );

		return 'molongui_execute_guest_author_deletion_' . implode( '_', $guest_ids );
	}

	private static function normalize_guest_ids( $guest_ids ) {
		if ( is_string( $guest_ids ) ) {
			$guest_ids = explode( ',', $guest_ids );
		}

		$guest_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $guest_ids ) ) ) );
		sort( $guest_ids, SORT_NUMERIC );

		return $guest_ids;
	}

	private function validate_guest_deletion_targets( $guest_ids ) {
		$guests = array();

		foreach ( self::normalize_guest_ids( $guest_ids ) as $guest_id ) {
			$guest = get_post( $guest_id );

			if ( ! $guest instanceof \WP_Post || $this->post_type !== $guest->post_type ) {
				return new \WP_Error(
					'molongui_authorship_invalid_guest_deletion_target',
					__( 'One or more selected Guest Authors no longer exist.', 'molongui-authorship' )
				);
			}

			if ( ! current_user_can( 'delete_post', $guest_id ) ) {
				return new \WP_Error(
					'molongui_authorship_guest_deletion_forbidden',
					__( 'You are not allowed to delete one or more selected Guest Authors.', 'molongui-authorship' )
				);
			}

			$guests[ $guest_id ] = $guest;
		}

		return $guests;
	}

	private function get_guest_deletion_replacement_authors( $deleted_guest_ids ) {
		$authors = Authors::get_authors(
			array(
				'type'             => 'authors',
				'exclude_guests'   => self::normalize_guest_ids( $deleted_guest_ids ),
				'exclude_archived' => true,
				'orderby'          => 'name',
				'order'            => 'ASC',
			)
		);
		$options = array(
			'user'  => array(),
			'guest' => array(),
		);

		foreach ( $authors as $author ) {
			if ( ! $author instanceof Author ) {
				continue;
			}

			$type = $author->get_type();

			if ( ! isset( $options[ $type ] ) ) {
				continue;
			}

			$options[ $type ][] = array(
				'id'   => absint( $author->get_id() ),
				'ref'  => $type . '-' . absint( $author->get_id() ),
				'name' => $author->get_base_display_name(),
			);
		}

		return $options;
	}

	private function render_guest_deletion_user_select( $guest_id, $users ) {
		?>
		<label class="screen-reader-text" for="molongui_guest_reassign_user_<?php echo esc_attr( $guest_id ); ?>">
			<?php esc_html_e( 'Select a user to attribute the content to.', 'molongui-authorship' ); ?>
		</label>
		<select id="molongui_guest_reassign_user_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_reassign_user[<?php echo esc_attr( $guest_id ); ?>]">
			<option value=""><?php esc_html_e( 'Select a user', 'molongui-authorship' ); ?></option>
			<?php foreach ( $users as $author ) : ?>
				<option value="<?php echo esc_attr( absint( $author['id'] ) ); ?>"><?php echo esc_html( $author['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private function render_guest_deletion_guest_select( $guest_id, $guests ) {
		?>
		<label class="screen-reader-text" for="molongui_guest_reassign_guest_<?php echo esc_attr( $guest_id ); ?>">
			<?php esc_html_e( 'Select a Guest Author to attribute the authored content to.', 'molongui-authorship' ); ?>
		</label>
		<select id="molongui_guest_reassign_guest_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_reassign_guest[<?php echo esc_attr( $guest_id ); ?>]">
			<option value=""><?php esc_html_e( 'Select a Guest Author', 'molongui-authorship' ); ?></option>
			<?php foreach ( $guests as $author ) : ?>
				<option value="<?php echo esc_attr( absint( $author['id'] ) ); ?>"><?php echo esc_html( $author['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	private function enqueue_guest_deletion_styles() {
		$css = '
			#deleteguests fieldset {
				max-width: 880px;
				margin-top: 18px;
			}

			#deleteguests .wrap > ul > li + li {
				margin-top: 32px;
				padding-top: 12px;
				border-top: 1px solid #dcdcde;
			}

			#deleteguests .wrap > ul > li + li > fieldset {
				margin-top: 0;
			}

			#deleteguests fieldset legend {
				margin-bottom: 2em;
			}

			#deleteguests fieldset ul {
				max-width: 880px;
				margin: 14px 0 0;
				margin-inline-start: 16px;
			}

			#deleteguests fieldset ul > li {
				margin: 0 0 24px;
				padding: 0;
				line-height: 1.5;
			}

			#deleteguests fieldset ul > li:last-child {
				margin-bottom: 0;
			}

			#deleteguests fieldset ul > li > input[type="radio"] {
				margin-top: 2px;
				margin-inline-end: 6px;
				vertical-align: top;
			}

			#deleteguests fieldset ul > li > label {
				line-height: 1.5;
			}

			#deleteguests fieldset ul > li > p.description {
				display: block;
				max-width: 760px;
				margin: 6px 0 0;
				margin-inline-start: 26px;
				line-height: 1.5;
			}

			#deleteguests fieldset ul > li > select {
				display: block;
				width: 320px;
				max-width: calc( 100% - 26px );
				margin: 8px 0 0;
				margin-inline-start: 26px;
			}

			#deleteguests li[data-molongui-delete-option] > label > .description {
				display: inline-block;
				margin-inline-start: 6px;
				padding: 0 6px;
				border: 1px solid #e2e4e7;
				border-radius: 8px;
				background: #f6f7f7;
				color: #8c8f94;
				font-size: 10px;
				font-weight: 400;
				line-height: 1.5;
				vertical-align: 1px;
			}

			#deleteguests li[data-molongui-delete-option] > input[type="radio"]:disabled + label {
				color: #8c8f94;
			}

			#deleteguests .submit {
				margin-top: 24px;
			}
		';

		wp_enqueue_style( 'common' );
		wp_add_inline_style( 'common', $css );
	}

	public function maybe_render_delete_confirmation_screen() {
		$requested = isset( $_GET[ self::DELETE_CONFIRM_QUERY_ARG ] )
			? sanitize_text_field( wp_unslash( $_GET[ self::DELETE_CONFIRM_QUERY_ARG ] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = isset( $_GET['post_type'] )
			? sanitize_key( wp_unslash( $_GET['post_type'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '1' !== $requested || $this->post_type !== $post_type ) {
			return;
		}

		$this->render_delete_confirmation_screen();
	}

	public function render_delete_confirmation_screen() {
		$guest_ids = isset( $_GET['guest_ids'] ) ? self::normalize_guest_ids( wp_unslash( $_GET['guest_ids'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $guest_ids ) ) {
			wp_die( esc_html__( 'No Guest Author was selected for deletion.', 'molongui-authorship' ) );
		}

		check_admin_referer( self::get_delete_confirmation_nonce_action( $guest_ids ) );

		$guests = $this->validate_guest_deletion_targets( $guest_ids );

		if ( is_wp_error( $guests ) ) {
			wp_die(
				esc_html( $guests->get_error_message() ),
				esc_html__( 'Unable to delete author', 'molongui-authorship' ),
				array( 'response' => 403 )
			);
		}

		$replacement_options    = $this->get_guest_deletion_replacement_authors( $guest_ids );
		$has_user_replacements  = ! empty( $replacement_options['user'] );
		$has_guest_replacements = ! empty( $replacement_options['guest'] );
		$pro_conversion         = (bool) apply_filters( 'molongui_authorship/guest_deletion/convert_to_user_available', false );

		global $title;
		$title = __( 'Delete Guest Authors', 'molongui-authorship' );

		$this->enqueue_guest_deletion_styles();

		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>
		<form method="post" name="deleteguests" id="deleteguests" class="delete-and-reassign-users-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::DELETE_EXECUTE_ACTION ); ?>" />
			<input type="hidden" name="guest_ids" value="<?php echo esc_attr( implode( ',', $guest_ids ) ); ?>" />
			<?php wp_nonce_field( self::get_delete_execution_nonce_action( $guest_ids ), 'molongui_guest_deletion_nonce' ); ?>

			<div class="wrap">
				<h1><?php esc_html_e( 'Delete Guest Authors', 'molongui-authorship' ); ?></h1>

				<?php if ( 1 === count( $guest_ids ) ) : ?>
					<p><?php esc_html_e( 'You have specified this guest author for deletion:', 'molongui-authorship' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'You have specified these guest authors for deletion:', 'molongui-authorship' ); ?></p>
				<?php endif; ?>

				<ul>
					<?php foreach ( $guests as $guest_id => $guest ) : ?>
						<?php $author_ref = Post_Authorship::build_reference( $guest_id, 'guest' ); ?>
						<li>
							<?php if ( ! Post_Authorship::author_has_explicit_relations( $author_ref ) ) : ?>
								<input type="hidden" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="remove" />
								<p>
									<?php
									printf(
										esc_html__( '%1$s (ID #%2$s): This guest author does not have any content.', 'molongui-authorship' ),
										'<strong>' . esc_html( get_the_title( $guest ) ) . '</strong>',
										esc_html( $guest_id )
									);
									?>
								</p>
							<?php else : ?>
								<fieldset>
									<legend>
										<?php
										printf(
											esc_html__( '%1$s (ID #%2$s): What should be done with the content owned by this guest author?', 'molongui-authorship' ),
											'<strong>' . esc_html( get_the_title( $guest ) ) . '</strong>',
											esc_html( $guest_id )
										);
										?>
									</legend>
									<ul>
										<li>
											<input type="radio" id="delete_option_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="delete" required />
											<label for="delete_option_<?php echo esc_attr( $guest_id ); ?>"><?php esc_html_e( 'Delete all content.', 'molongui-authorship' ); ?></label>
											<p class="description">
												<?php esc_html_e( 'For content managed by Molongui Authorship, only posts where this guest author is the main author will be deleted; secondary co-author credits are removed from surviving posts.', 'molongui-authorship' ); ?>
											</p>
										</li>

										<li>
											<input type="radio" id="reassign_user_option_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="reassign_user" <?php disabled( ! $has_user_replacements ); ?> required />
											<label for="reassign_user_option_<?php echo esc_attr( $guest_id ); ?>"><?php esc_html_e( 'Attribute all content to a user.', 'molongui-authorship' ); ?></label>
											<?php if ( $has_user_replacements ) : ?>
												<?php $this->render_guest_deletion_user_select( $guest_id, $replacement_options['user'] ); ?>
											<?php else : ?>
												<p class="description"><?php esc_html_e( 'No other active WordPress user is available for reassignment.', 'molongui-authorship' ); ?></p>
											<?php endif; ?>
										</li>

										<li data-molongui-delete-option="reassign_guest">
											<input type="radio" id="reassign_guest_option_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="reassign_guest" <?php disabled( ! $has_guest_replacements ); ?> required />
											<label for="reassign_guest_option_<?php echo esc_attr( $guest_id ); ?>">
												<?php esc_html_e( 'Attribute authored content to another Guest Author.', 'molongui-authorship' ); ?>
											</label>
											<?php if ( $has_guest_replacements ) : ?>
												<?php $this->render_guest_deletion_guest_select( $guest_id, $replacement_options['guest'] ); ?>
											<?php else : ?>
												<p class="description"><?php esc_html_e( 'Create another Guest Author before using this option.', 'molongui-authorship' ); ?></p>
											<?php endif; ?>
										</li>

										<li data-molongui-delete-option="remove">
											<input type="radio" id="remove_authorship_option_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="remove" required />
											<label for="remove_authorship_option_<?php echo esc_attr( $guest_id ); ?>">
												<?php esc_html_e( "Keep content and remove this guest author's authorship.", 'molongui-authorship' ); ?>
											</label>
											<p class="description">
												<?php esc_html_e( 'Posts losing their main author will be moved to Draft when required. Posts where this guest author is only a co-author will keep their current status.', 'molongui-authorship' ); ?>
											</p>
										</li>

										<li data-molongui-delete-option="convert_user">
											<input type="radio" id="convert_user_option_<?php echo esc_attr( $guest_id ); ?>" name="molongui_guest_delete_action[<?php echo esc_attr( $guest_id ); ?>]" value="convert_user" <?php disabled( ! $pro_conversion ); ?> required />
											<label for="convert_user_option_<?php echo esc_attr( $guest_id ); ?>">
												<?php esc_html_e( 'Convert to User and preserve authorship.', 'molongui-authorship' ); ?>
												<?php if ( ! Plugin::has_pro() ) : ?>
													<span class="description"><?php esc_html_e( 'only Pro', 'molongui-authorship' ); ?></span>
												<?php endif; ?>
											</label>
											<p class="description">
												<?php esc_html_e( "Removes the Guest Author record while preserving this author's Molongui Authorship attribution, role, and position in a WordPress user account.", 'molongui-authorship' ); ?>
											</p>
										</li>
									</ul>
								</fieldset>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php submit_button( __( 'Confirm Deletion', 'molongui-authorship' ), 'primary' ); ?>
			</div><!-- .wrap -->
		</form><!-- #deleteguests -->
		<?php
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	public function handle_confirmed_deletion() {
		$guest_ids = isset( $_POST['guest_ids'] ) ? self::normalize_guest_ids( wp_unslash( $_POST['guest_ids'] ) ) : array();

		if ( empty( $guest_ids ) ) {
			wp_die( esc_html__( 'No Guest Author was selected for deletion.', 'molongui-authorship' ) );
		}

		check_admin_referer( self::get_delete_execution_nonce_action( $guest_ids ), 'molongui_guest_deletion_nonce' );

		$guests = $this->validate_guest_deletion_targets( $guest_ids );

		if ( is_wp_error( $guests ) ) {
			wp_die(
				esc_html( $guests->get_error_message() ),
				esc_html__( 'Unable to delete author', 'molongui-authorship' ),
				array( 'response' => 403 )
			);
		}

		$raw_actions = isset( $_POST['molongui_guest_delete_action'] ) && is_array( $_POST['molongui_guest_delete_action'] )
			? wp_unslash( $_POST['molongui_guest_delete_action'] )
			: array();
		$raw_user_reassignments = isset( $_POST['molongui_guest_reassign_user'] ) && is_array( $_POST['molongui_guest_reassign_user'] )
			? wp_unslash( $_POST['molongui_guest_reassign_user'] )
			: array();
		$raw_guest_reassignments = isset( $_POST['molongui_guest_reassign_guest'] ) && is_array( $_POST['molongui_guest_reassign_guest'] )
			? wp_unslash( $_POST['molongui_guest_reassign_guest'] )
			: array();
		$deleted_refs   = array();
		$requests       = array();
		$pro_conversion = (bool) apply_filters( 'molongui_authorship/guest_deletion/convert_to_user_available', false );

		foreach ( $guest_ids as $guest_id ) {
			$deleted_refs[] = Post_Authorship::build_reference( $guest_id, 'guest' );
		}

		foreach ( $guest_ids as $guest_id ) {
			$removed_ref = Post_Authorship::build_reference( $guest_id, 'guest' );
			$action      = isset( $raw_actions[ $guest_id ] ) && is_string( $raw_actions[ $guest_id ] )
				? sanitize_key( $raw_actions[ $guest_id ] )
				: '';
			$allowed_actions = array( 'delete', 'reassign_user', 'reassign_guest', 'remove' );

			if ( $pro_conversion ) {
				$allowed_actions[] = 'convert_user';
			}

			if ( ! in_array( $action, $allowed_actions, true ) ) {
				wp_die(
					esc_html__( 'Select how content should be handled for every Guest Author being deleted.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 400 )
				);
			}

			$domain_action   = $action;
			$replacement_ref = '';

			if ( 'reassign_user' === $action ) {
				$replacement_id  = isset( $raw_user_reassignments[ $guest_id ] ) ? absint( $raw_user_reassignments[ $guest_id ] ) : 0;
				$replacement_ref = $replacement_id ? Post_Authorship::build_reference( $replacement_id, 'user' ) : '';
				$domain_action   = 'reassign';
			} elseif ( 'reassign_guest' === $action ) {
				$replacement_id  = isset( $raw_guest_reassignments[ $guest_id ] ) ? absint( $raw_guest_reassignments[ $guest_id ] ) : 0;
				$replacement_ref = $replacement_id ? Post_Authorship::build_reference( $replacement_id, 'guest' ) : '';
				$domain_action   = 'reassign';
			}

			if ( 'reassign' === $domain_action ) {
				if (
					! Post_Authorship::is_valid_reference( $replacement_ref )
					|| in_array( $replacement_ref, $deleted_refs, true )
					|| ! Post_Authorship::author_exists( $replacement_ref )
				) {
					wp_die(
						esc_html__( 'Select a valid replacement author who is not being deleted in this request.', 'molongui-authorship' ),
						esc_html__( 'Unable to delete author', 'molongui-authorship' ),
						array( 'response' => 400 )
					);
				}
			}

			$requests[ $guest_id ] = array(
				'action'          => $action,
				'domain_action'   => $domain_action,
				'replacement_ref' => $replacement_ref,
			);
		}

		$deleted_count = 0;

		foreach ( $requests as $guest_id => $request ) {
			if ( 'convert_user' === $request['action'] ) {
				$converted_user_id = apply_filters( 'molongui_authorship/guest_deletion/convert_to_user', 0, $guest_id );

				if ( is_wp_error( $converted_user_id ) || ! absint( $converted_user_id ) ) {
					$message = is_wp_error( $converted_user_id )
						? $converted_user_id->get_error_message()
						: __( 'The Guest Author could not be converted to a WordPress user.', 'molongui-authorship' );

					wp_die(
						esc_html( $message ),
						esc_html__( 'Unable to delete author', 'molongui-authorship' ),
						array( 'response' => 500 )
					);
				}
			} else {
				$result = Post_Authorship::apply_guest_deletion_action(
					$guest_id,
					$request['domain_action'],
					$request['replacement_ref'],
					'guest_author_deletion'
				);

				if ( is_wp_error( $result ) ) {
					wp_die(
						esc_html( $result->get_error_message() ),
						esc_html__( 'Unable to delete author', 'molongui-authorship' ),
						array( 'response' => 500 )
					);
				}

				if ( 'reassign' === $request['domain_action'] && ! empty( $result['post_types'] ) ) {
					$replacement_data = Post_Authorship::parse_reference( $request['replacement_ref'] );

					if ( $replacement_data ) {
						foreach ( $result['post_types'] as $post_type ) {
							Post_Count_Updater::update_author_post_counter(
								array(
									'id'   => $replacement_data['id'],
									'type' => $replacement_data['type'],
								),
								$post_type
							);
						}
					}
				}
			}

			self::$guest_deletion_policies_applied[ $guest_id ] = true;
			$deleted = wp_delete_post( $guest_id, true );

			if ( false === $deleted || null === $deleted ) {
				wp_die(
					esc_html__( 'The Guest Author record could not be deleted.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 500 )
				);
			}

			++$deleted_count;
		}

		$redirect_url = add_query_arg(
			array(
				'post_type' => $this->post_type,
				'deleted'   => $deleted_count,
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}


	public function quick_edit_add_title_field() {
		global $pagenow, $post_type;

		if ( 'edit.php' == $pagenow and $post_type == $this->post_type ) {
			add_post_type_support( $post_type, 'title' );
		}
	}

	public function quick_edit_add_custom_fields( $column_name, $post_type ) {
		if ( $column_name != 'guestDisplayBox' ) {
			return;
		}

		wp_nonce_field( 'quick_edit_guest', 'quick_edit_guest_nonce' );

		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
				<div class="inline-edit-group wp-clearfix">
					<label class="inline-edit-status alignleft">
						<span class="title"><?php esc_html_e( 'Author Box', 'molongui-authorship' ); ?></span>
						<select name="_molongui_guest_author_box_display">

							<option value="default">
								<?php esc_html_e( 'Default', 'molongui-authorship' ); ?>
							</option>

							<option
									value="show"
								<?php disabled( ! Plugin::has_pro() ); ?>
							>
								<?php esc_html_e( 'Show', 'molongui-authorship' ); ?>
							</option>

							<option
									value="hide"
								<?php disabled( ! Plugin::has_pro() ); ?>
							>
								<?php esc_html_e( 'Hide', 'molongui-authorship' ); ?>
							</option>

						</select>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}

	public function hide_status_fields_from_inline_edit() {

		$screen = get_current_screen();

		if (
			! isset( $screen->id, $screen->post_type )
			||
			'edit-' . $this->post_type !== $screen->id
			||
			$this->post_type !== $screen->post_type
		) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(
				function ( $ ) {

					/*
					 * Keep the native status fields in the DOM so WordPress can
					 * preserve their current values, but prevent users from
					 * changing Guest Author post status through inline editors.
					 */
					$( '#inline-edit select[name="_status"]' )
					.closest( 'label.inline-edit-status' )
					.hide();

					$( '#bulk-edit select[name="_status"]' )
					.closest( 'label.inline-edit-status' )
					.hide();
				}
			);
		</script>
		<?php
	}

	public function quick_edit_populate_custom_fields() {
		$current_screen = get_current_screen();

		if ( ! isset( $current_screen->id, $current_screen->post_type )
			|| $current_screen->id !== 'edit-' . $this->post_type
			|| $current_screen->post_type !== $this->post_type
		) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		?>
		<script type="text/javascript">
			jQuery(function($)
			{
				// Create a copy of the WP inline edit post function.
				var $inline_editor = inlineEditPost.edit;

				// Overwrite the function with our own code.
				inlineEditPost.edit = function(id)
				{
					// "Call" the original WP edit function. We don't want to leave WordPress hanging.
					$inline_editor.apply(this, arguments);

					// Get the post ID.
					var post_id = 0;
					if (typeof(id) == 'object')
					{
						post_id = parseInt(this.getId(id));
					}

					// If we have our post...
					if (post_id != 0)
					{
						// Find our row.
						$row = $('#edit-' + post_id);

						// Get the data.
						$box_display = $('#box_display_' + post_id).data('display-box');

						// Set default value if empty.
						if ($box_display === '')
						{
							$box_display = 'default';
						}

						// Populate the data.
						$row.find('[name="_molongui_guest_author_box_display"]').val($box_display);
						$row.find('[name="_molongui_guest_author_box_display"]').children('[value="' + $box_display + '"]').attr('selected', true);
					}
				}
			});
		</script>
		<?php
	}

	public function quick_edit_save_custom_fields( $post_id, $post ) {
		if ( ! isset( $_POST['quick_edit_guest_nonce'] ) or ! wp_verify_nonce( $_POST['quick_edit_guest_nonce'], 'quick_edit_guest' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['post_title'] ) ) {
			update_post_meta( $post_id, '_molongui_guest_author_display_name', sanitize_text_field( $_POST['post_title'] ) );
		}

		if ( Plugin::has_pro() && isset( $_POST['_molongui_guest_author_box_display'] ) ) {
			$box_display = sanitize_key( wp_unslash( $_POST['_molongui_guest_author_box_display'] ) );

			if ( in_array( $box_display, array( 'default', 'show', 'hide' ), true ) ) {
				update_post_meta(
					$post_id,
					'_molongui_guest_author_box_display',
					$box_display
				);
			}
		}

	}


	public function remove_bulk_edit_action( $actions ) {
		unset( $actions['trash'], $actions['delete'] );

		if ( current_user_can( 'delete_posts' ) ) {
			$actions[ self::DELETE_BULK_ACTION ] = __( 'Delete', 'molongui-authorship' );
		}

		if ( apply_filters( 'authorship/guest/bulk_actions/remove_edit', true ) ) {
			unset( $actions['edit'] );
		}

		return $actions;
	}

	public function handle_delete_bulk_action( $redirect_to, $doaction, $post_ids ) {
		if ( self::DELETE_BULK_ACTION !== $doaction ) {
			return $redirect_to;
		}

		$guest_ids = self::normalize_guest_ids( $post_ids );

		if ( empty( $guest_ids ) ) {
			return $redirect_to;
		}

		return self::get_delete_confirmation_url( $guest_ids );
	}


	public function remove_media_buttons() {
		$current_screen = get_current_screen();

		if ( ! isset( $current_screen->post_type ) || $this->post_type !== $current_screen->post_type ) {
			return;
		}

		remove_action( 'media_buttons', 'media_buttons' );
	}

	public function remove_preview_button() {
		$current_screen = get_current_screen();

		if ( ! isset( $current_screen->post_type ) || $this->post_type !== $current_screen->post_type ) {
			return;
		}

		if ( apply_filters( 'authorship/admin/guest/show_preview_button', false, $current_screen ) ) {
			return;
		}

		echo '<style>#post-preview{ display:none !important; }</style>';
	}

	public function add_top_section_after_title() {
		global $post;

		if ( empty( $post ) || $post->post_type !== $this->post_type ) {
			return;
		}

		$this->render_profile_metabox( $post );

		do_meta_boxes(
			get_current_screen(),
			'top',
			$post
		);
	}

	public function add_meta_boxes( $post_type ) {

		if ( ! current_user_can( 'edit_others_pages' ) && ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		if ( $this->post_type !== $post_type ) {
			return;
		}

		remove_meta_box(
			'submitdiv',
			$post_type,
			'side'
		);

		add_meta_box(
			'submitdiv',
			__( 'Save', 'molongui-authorship' ),
			'post_submit_meta_box',
			$post_type,
			'side',
			'high'
		);

		if (
			has_filter( 'authorship/admin/guest/shortbio_metabox_html' )
			&&
			apply_filters_deprecated(
				'authorship/admin/guest/shortbio/metabox',
				array( true ),
				'5.3.0',
				'molongui_authorship/admin/author/biography_fields'
			)
		) {
			add_meta_box(
				'authorshortbiodiv',
				__( 'Short Biography', 'molongui-authorship' ),
				array( $this, 'render_short_bio_metabox' ),
				$post_type,
				'top',
				'default'
			);
		}

		if ( ! Settings::is_enabled( 'local-avatar' ) ) {
			add_meta_box(
				'authoravatardiv',
				__( 'Profile Picture', 'molongui-authorship' ),
				array( $this, 'render_avatar_metabox' ),
				$post_type,
				'side',
				'low'
			);
		}

		if ( Settings::is_enabled( 'author-box' ) ) {
			add_meta_box(
				'authorboxdiv',
				__( 'Author Box', 'molongui-authorship' ),
				array( $this, 'render_box_metabox' ),
				$post_type,
				'side',
				'low'
			);
		}

		add_meta_box(
			'authorarchivediv',
			__( 'Author Status', 'molongui-authorship' ),
			array( $this, 'render_archive_metabox' ),
			$post_type,
			'side',
			'low'
		);

		if ( apply_filters( 'authorship/admin/guest/convert/metabox', true ) ) {
			add_meta_box(
				'authorconversiondiv',
				__( 'Convert to User', 'molongui-authorship' ),
				array( $this, 'render_conversion_metabox' ),
				$post_type,
				'side',
				'low'
			);
		}

		do_action( 'authorship/admin/guest/metaboxes', $post_type );
	}

	public function hide_convert_metabox() {
		if ( ! current_user_can( 'create_users' ) ) {
			return false;
		}
		return true;
	}

	public function render_profile_metabox( $post ) {
		wp_nonce_field(
			'molongui_authorship_guest',
			'molongui_authorship_guest_nonce'
		);

		$author = new Author( $post->ID, 'guest' );

		$display_name_field = Author::GUEST_META_PREFIX . 'display_name';

		$public_name = array(
			'fields' => array(
				array(
					'control'     => 'input',
					'id'          => '_molongui_guest_author_first_name',
					'name'        => '_molongui_guest_author_first_name',
					'label'       => __( 'First Name', 'molongui-authorship' ),
					'value'       => $author->get_first_name(),
					'description' => __( "The author's given name.", 'molongui-authorship' ),
				),
				array(
					'control'     => 'input',
					'id'          => '_molongui_guest_author_last_name',
					'name'        => '_molongui_guest_author_last_name',
					'label'       => __( 'Last Name', 'molongui-authorship' ),
					'value'       => $author->get_last_name(),
					'description' => __( "The author's family name.", 'molongui-authorship' ),
				),
				array(
					'control'     => 'input',
					'id'          => $display_name_field,
					'name'        => $display_name_field,
					'label'       => __( 'Display Name', 'molongui-authorship' ),
					'value'       => (string) get_post_meta(
						$post->ID,
						$display_name_field,
						true
					),
					'description' => __( 'Optional. When empty, First Name and Last Name are used.', 'molongui-authorship' ),
				),
				array(
					'control'     => 'input',
					'id'          => '_molongui_guest_author_name_prefix',
					'name'        => '_molongui_guest_author_name_prefix',
					'label'       => __( 'Name prefix', 'molongui-authorship' ),
					'value'       => $author->get_name_prefix(),
					'description' => __( 'Examples: Dr., Prof., or Rev.', 'molongui-authorship' ),
				),
				array(
					'control'     => 'input',
					'id'          => '_molongui_guest_author_name_suffix',
					'name'        => '_molongui_guest_author_name_suffix',
					'label'       => __( 'Name suffix', 'molongui-authorship' ),
					'value'       => $author->get_name_suffix(),
					'description' => __( 'Examples: Jr., Sr., II, or III.', 'molongui-authorship' ),
				),
				array(
					'control'     => 'input',
					'id'          => '_molongui_guest_author_credentials',
					'name'        => '_molongui_guest_author_credentials',
					'label'       => __( 'Credentials', 'molongui-authorship' ),
					'value'       => $author->get_credentials(),
					'description' => __( 'Examples: MD, PhD, RN, or CPA. Separate multiple credentials with commas.', 'molongui-authorship' ),
				),
			),
			'preview' => $author->get_display_name(),
		);

		$legacy_short_bio = has_filter(
			'authorship/admin/guest/shortbio_metabox_html'
		);

		$biography = array(
			'full' => array(
				'control'     => 'editor',
				'id'          => 'content',
				'name'        => 'content',
				'value'       => (string) get_post_field(
					'post_content',
					$post->ID
				),
				'rows'        => 10,
				'description' => __(
					"This author's main, detailed biography.",
					'molongui-authorship'
				),
				'settings'    => array(
					'media_buttons' => false,
					'textarea_rows' => 10,
					'editor_css'    => '<style>#wp-content-editor-tools{background:none;padding-top:0;}</style>',
				),
				'locked'      => false,
			),
			'short' => array(
				'show'        => (
					! $legacy_short_bio
					&&
					apply_filters(
						'authorship/admin/guest/shortbio/metabox',
						true
					)
				),
				'control'     => 'editor',
				'id'          => '_premium_short_bio_field',
				'name'        => '_premium_short_bio_field',
				'value'       => '',
				'rows'        => 5,
				'description' => __(
					'A concise alternative for places where a shorter bio is preferred.',
					'molongui-authorship'
				),
				'settings'    => array(
					'textarea_name' => '_premium_short_bio_field',
					'tinymce'       => array(
						'readonly' => true,
					),
					'quicktags'     => false,
					'media_buttons' => false,
					'teeny'         => true,
					'textarea_rows' => 5,
				),
				'locked'      => true,
			),
		);

		$biography = apply_filters(
			'molongui_authorship/admin/author/biography_fields',
			$biography,
			'guest',
			(int) $post->ID
		);

		$biography_template = MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-biography.php';

		$professional_info = array(
			'professional_headline' => array(
				'id'    => '_molongui_guest_author_professional_headline',
				'name'  => '_molongui_guest_author_professional_headline',
				'value' => $author->get_professional_headline(),
			),
			'job' => array(
				'id'    => '_molongui_guest_author_job',
				'name'  => '_molongui_guest_author_job',
				'value' => $author->get_meta( 'job' ),
			),
			'company' => array(
				'id'    => '_molongui_guest_author_company',
				'name'  => '_molongui_guest_author_company',
				'value' => $author->get_meta( 'company' ),
			),
			'department' => array(
				'id'    => '_molongui_guest_author_department',
				'name'  => '_molongui_guest_author_department',
				'value' => $author->get_department(),
			),
			'company_link' => array(
				'id'    => '_molongui_guest_author_company_link',
				'name'  => '_molongui_guest_author_company_link',
				'value' => $author->get_meta( 'company_link' ),
			),
		);

		$contact_info = array(
			'fields' => array(
				array(
					'control'     => 'input',
					'type'        => 'text',
					'id'          => '_molongui_guest_author_location',
					'name'        => '_molongui_guest_author_location',
					'label'       => __( 'Location', 'molongui-authorship' ),
					'value'       => $author->get_location(),
					'description' => '',
				),
				array(
					'control'     => 'input',
					'type'        => 'email',
					'id'          => '_molongui_guest_author_mail',
					'name'        => '_molongui_guest_author_mail',
					'label'       => __( 'Email', 'molongui-authorship' ),
					'value'       => $author->get_email(),
					'description' => '',
				),
				array(
					'control'     => 'input',
					'type'        => 'url',
					'id'          => '_molongui_guest_author_web',
					'name'        => '_molongui_guest_author_web',
					'label'       => __( 'Website', 'molongui-authorship' ),
					'value'       => $author->get_website(),
					'description' => '',
				),
				array(
					'control'     => 'input',
					'type'        => 'tel',
					'id'          => '_molongui_guest_author_phone',
					'name'        => '_molongui_guest_author_phone',
					'label'       => __( 'Phone', 'molongui-authorship' ),
					'value'       => $author->get_meta( 'phone' ),
					'description' => '',
				),
			),
		);

		$networks        = Social::get( 'enabled' );
		$is_pro          = Plugin::has_pro();
		$social_profiles = array(
			'settings_url'           => Settings::url( 'integrations' ),
			'has_inherited_profiles' => false,
			'fields'                 => array(),
		);

		if ( is_array( $networks ) ) {

			foreach ( $networks as $id => $network ) {

				$field_id = Author::GUEST_META_PREFIX . $id;
				$icon_id  = sanitize_html_class( $id );
				$locked   = ! $is_pro && ! empty( $network['premium'] );

				$value = '';

				if ( ! $locked ) {
					$value = (string) get_post_meta(
						$post->ID,
						$field_id,
						true
					);
				}

				$social_profiles['fields'][] = array(
					'id'             => $field_id,
					'name'           => $locked ? '' : $field_id,
					'label'          => isset( $network['name'] )
						? $network['name']
						: $id,
					'icon_class'     => 'm-a-icon-' . $icon_id,
					'value'          => $locked ? '' : $value,
					'example'        => isset( $network['url'] )
						? $network['url']
						: '',
					'example_id'     => 'molongui-social-example-' . $icon_id,
					'fallback_id'    => '',
					'fallback_value' => '',
					'locked'         => $locked,
				);
			}
		}

		require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/guest/html-author-profile.php';
	}

	public function render_save_box_summary( $post ) {

		if (
			! $post instanceof \WP_Post
			||
			$this->post_type !== $post->post_type
		) {
			return;
		}

		if (
			! current_user_can( 'edit_others_pages' )
			&&
			! current_user_can( 'edit_others_posts' )
		) {
			return;
		}

		$author = new Author(
			$post->ID,
			'guest'
		);

		$display_name = trim(
			(string) $author->get_base_display_name()
		);

		if (
			'auto-draft' === $post->post_status
			||
			'' === $display_name
		) {
			$display_name = __(
				'New Guest Author',
				'molongui-authorship'
			);
		}

		$is_archived = $author->is_archived();

		$avatar_resolution      = ( new Avatar( $author ) )->get_resolution( array( 96, 96 ), 'url', 'auto' );
		$profile_picture_status = $this->get_profile_picture_source_label( $avatar_resolution['source'] );

		if ( '' === $profile_picture_status ) {
			$profile_picture_status = __( 'None', 'molongui-authorship' );
		}

		$profile_picture_target = Settings::is_enabled( 'local-avatar' )
			? '#postimagediv'
			: '#authoravatardiv';

		$author_box_enabled = Settings::is_enabled( 'author-box' );

		if ( ! $author_box_enabled ) {

			$author_box_status = __(
				'Disabled',
				'molongui-authorship'
			);

		} else {

			switch ( $author->get_box_display() ) {
				case 'show':
					$author_box_status = __(
						'Shown',
						'molongui-authorship'
					);
					break;

				case 'hide':
					$author_box_status = __(
						'Hidden',
						'molongui-authorship'
					);
					break;

				case 'default':
				default:
					$author_box_status = __(
						'Global settings',
						'molongui-authorship'
					);
					break;
			}
		}

		$created_date = '';

		if ( 'auto-draft' !== $post->post_status ) {
			$created_date = get_the_date(
				get_option( 'date_format' ),
				$post
			);
		}

		?>
		<div class="molongui-profile-save-info">

			<!-- Guest Author -->
			<div class="molongui-profile-save-row">

			<span
					class="dashicons dashicons-admin-users"
					aria-hidden="true"
			></span>

				<div class="molongui-profile-save-row-content">

				<span class="molongui-profile-save-label">
					<?php esc_html_e( 'Editing:', 'molongui-authorship' ); ?>
				</span>

					<span>
					<?php echo esc_html( $display_name ); ?>
				</span>

				</div>

			</div>

			<!-- Author Status -->
			<div class="molongui-profile-save-row">

			<span
					class="dashicons <?php echo esc_attr( $is_archived ? 'dashicons-archive' : 'dashicons-yes-alt' ); ?>"
					aria-hidden="true"
			></span>

				<div class="molongui-profile-save-row-content">

				<span class="molongui-profile-save-label">
					<?php esc_html_e( 'Status:', 'molongui-authorship' ); ?>
				</span>

					<span>
					<?php
					echo esc_html(
						$is_archived
							? __( 'Archived', 'molongui-authorship' )
							: __( 'Active', 'molongui-authorship' )
					);
					?>
				</span>

					<a
							class="molongui-profile-save-edit"
							href="#authorarchivediv"
					>
						<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
					</a>

				</div>

			</div>

			<!-- Profile Picture -->
			<div class="molongui-profile-save-row">

			<span
					class="dashicons dashicons-format-image"
					aria-hidden="true"
			></span>

				<div class="molongui-profile-save-row-content">

				<span class="molongui-profile-save-label">
					<?php esc_html_e( 'Profile picture:', 'molongui-authorship' ); ?>
				</span>

					<span>
					<?php echo esc_html( $profile_picture_status ); ?>
				</span>

					<a
							class="molongui-profile-save-edit"
							href="<?php echo esc_attr( $profile_picture_target ); ?>"
					>
						<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
					</a>

				</div>

			</div>

			<!-- Author Box -->
			<div class="molongui-profile-save-row">

			<span
					class="dashicons dashicons-id-alt"
					aria-hidden="true"
			></span>

				<div class="molongui-profile-save-row-content">

				<span class="molongui-profile-save-label">
					<?php esc_html_e( 'Author box:', 'molongui-authorship' ); ?>
				</span>

					<span>
					<?php echo esc_html( $author_box_status ); ?>
				</span>

					<?php if ( $author_box_enabled ) : ?>

						<a
								class="molongui-profile-save-edit"
								href="#authorboxdiv"
						>
							<?php esc_html_e( 'Edit', 'molongui-authorship' ); ?>
						</a>

					<?php endif; ?>

				</div>

			</div>

			<?php if ( $created_date ) : ?>

				<!-- Created -->
				<div class="molongui-profile-save-row">

				<span
						class="dashicons dashicons-calendar"
						aria-hidden="true"
				></span>

					<div class="molongui-profile-save-row-content">

					<span class="molongui-profile-save-label">
						<?php esc_html_e( 'Created:', 'molongui-authorship' ); ?>
					</span>

						<span>
						<?php echo esc_html( $created_date ); ?>
					</span>

					</div>

				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	public function add_save_metabox_class( $classes ) {

		$classes[] = 'molongui-guest-save-box';

		global $post;

		if (
			$post instanceof \WP_Post
			&&
			in_array(
				$post->post_status,
				array( 'publish', 'auto-draft' ),
				true
			)
		) {
			$classes[] = 'molongui-guest-save-box--simple';
		}

		return $classes;
	}

	public function render_archive_metabox( $post ) {

		$author_status = array(
			'id'      => '_molongui_guest_author_archived',
			'name'    => '_molongui_guest_author_archived',
			'checked' => (bool) get_post_meta(
				$post->ID,
				'_molongui_guest_author_archived',
				true
			),
		);

		require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-status.php';
	}

	public function render_box_metabox( $post ) {

		$options = Settings::get();
		$is_pro  = Plugin::has_pro();

		$author  = new Author( $post->ID, 'guest' );
		$display = $author->get_box_display();

		$custom_link_active = ( 'custom' === $options['author_box_avatar_link'] || 'custom' === $options['author_box_name_link'] );

		$author_meta_visibility = Author_Meta_Fields::get_author_visibility( $author );
		$author_meta_fields     = array();

		foreach ( Author_Meta_Fields::get_fields( Author_Meta_Fields::CONTEXT_AUTHOR_BOX ) as $field_id => $definition ) {
			$author_meta_fields[ $field_id ] = array(
				'label' => $definition['label'],
				'value' => isset( $author_meta_visibility[ $field_id ] )
					? $author_meta_visibility[ $field_id ]
					: Author_Meta_Fields::VISIBILITY_DEFAULT,
			);
		}

		$author_box = array(
			'enabled'      => true,
			'settings_url' => Settings::url( 'reading' ),
			'display'      => array(
				'id'     => '_molongui_guest_author_box_display',
				'name'   => '_molongui_guest_author_box_display',
				'value'  => $display,
				'locked' => ! $is_pro,
				'filter' => '_authorship/guest/box_display',
			),
			'custom_link' => array(
				'id'     => '_molongui_guest_author_custom_link',
				'name'   => '_molongui_guest_author_custom_link',
				'value'  => (string) get_post_meta(
					$post->ID,
					'_molongui_guest_author_custom_link',
					true
				),
				'active' => $custom_link_active,
			),
			'meta_visibility' => array(
				'name'   => '_molongui_guest_author_box_meta_visibility',
				'fields' => $author_meta_fields,
			),
			'icons' => array(
				'website' => array(
					'id'      => '_molongui_guest_author_show_icon_web',
					'name'    => '_molongui_guest_author_show_icon_web',
					'checked' => (bool) get_post_meta(
						$post->ID,
						'_molongui_guest_author_show_icon_web',
						true
					),
				),
				'email' => array(
					'id'      => '_molongui_guest_author_show_icon_mail',
					'name'    => '_molongui_guest_author_show_icon_mail',
					'checked' => (bool) get_post_meta(
						$post->ID,
						'_molongui_guest_author_show_icon_mail',
						true
					),
				),
				'phone' => array(
					'id'      => '_molongui_guest_author_show_icon_phone',
					'name'    => '_molongui_guest_author_show_icon_phone',
					'checked' => (bool) get_post_meta(
						$post->ID,
						'_molongui_guest_author_show_icon_phone',
						true
					),
				),
			),
		);

		require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-author-box.php';
	}

	public function render_avatar_metabox( $post ) {

		$author     = new Author( $post->ID, 'guest', $post );
		$avatar     = new Avatar( $author );
		$resolution = $avatar->get_resolution( array( 256, 256 ), 'screen', 'auto' );

		$profile_picture = array(
			'avatar'                => $resolution['value'],
			'source'                => $this->get_profile_picture_source_label( $resolution['source'] ),
			'fallback_source'       => __( 'Default avatar', 'molongui-authorship' ),
			'settings_url'          => Settings::url(),
			'local_avatar_enabled'  => Settings::is_enabled( 'local-avatar' ),
		);

		require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/guest/sidebar/html-profile-picture.php';
	}

	public function filter_profile_picture_metabox_html( $content, $post_id, $thumbnail_id ) {

		if ( Guest_Author::get_post_type() !== get_post_type( $post_id ) ) {
			return $content;
		}

		$author = new Author( $post_id, 'guest' );
		$avatar = new Avatar( $author );

		$effective_resolution = $avatar->get_resolution( array( 256, 256 ), 'url', 'auto' );
		$fallback_resolution  = $avatar->get_resolution( array( 256, 256 ), 'url', 'gravatar' );

		$source_labels = array(
			'local'          => __( 'Local', 'molongui-authorship' ),
			'gravatar'       => __( 'Gravatar', 'molongui-authorship' ),
			'custom-default' => __( 'Default avatar', 'molongui-authorship' ),
			'none'           => '',
		);

		$thumbnail_id     = absint( $thumbnail_id );
		$local_preview_url = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'medium' ) : '';
		$local_edit_url    = $thumbnail_id ? get_edit_post_link( $thumbnail_id, 'raw' ) : '';
		$has_local_picture = ! empty( $thumbnail_id ) && ! empty( $local_preview_url );

		$preview_url = ! empty( $effective_resolution['value'] )
			? $effective_resolution['value']
			: '';

		if ( 'local' === $effective_resolution['source'] && $local_preview_url ) {
			$preview_url = $local_preview_url;
		}

		$profile_picture = array(
			'thumbnail_id'             => $thumbnail_id,
			'has_local_picture'        => $has_local_picture,
			'local_edit_url'           => $local_edit_url,
			'preview_url'              => $preview_url,
			'source'                   => isset( $source_labels[ $effective_resolution['source'] ] )
				? $source_labels[ $effective_resolution['source'] ]
				: '',
			'browser_fallback'         => ! empty( $effective_resolution['browser_fallback'] )
				? $effective_resolution['browser_fallback']
				: '',
			'fallback_url'             => ! empty( $fallback_resolution['value'] )
				? $fallback_resolution['value']
				: '',
			'fallback_source'          => isset( $source_labels[ $fallback_resolution['source'] ] )
				? $source_labels[ $fallback_resolution['source'] ]
				: '',
			'fallback_browser_url'     => ! empty( $fallback_resolution['browser_fallback'] )
				? $fallback_resolution['browser_fallback']
				: '',
			'local_source'             => $source_labels['local'],
			'custom_default_source'    => $source_labels['custom-default'],
			'can_manage_local_picture' => current_user_can( 'upload_files' ),
		);

		ob_start();
		require MOLONGUI_AUTHORSHIP_DIR . 'views/admin/guest/sidebar/html-profile-picture-picker.php';

		return ob_get_clean();
	}

	private function get_profile_picture_source_label( $source ) {

		switch ( $source ) {
			case 'local':
				return __( 'Local', 'molongui-authorship' );

			case 'gravatar':
				return __( 'Gravatar', 'molongui-authorship' );

			case 'custom-default':
				return __( 'Default avatar', 'molongui-authorship' );

			default:
				return '';
		}
	}

	public function render_conversion_metabox( $post ) {

		$author_conversion = array(
			'description'  => __(
				'Turn this Guest Author into a registered WordPress user while preserving their post authorship.',
				'molongui-authorship'
			),
			'button_label' => __(
				'Convert to User',
				'molongui-authorship'
			),
		);

		$default_conversion_template = MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/sidebar/html-conversion.php';

		if ( has_filter( 'authorship/admin/guest/convert_metabox_html' ) ) {

			$legacy_default_template = MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-convert-metabox.php';

			$conversion_template = apply_filters_deprecated(
				'authorship/admin/guest/convert_metabox_html',
				array( $legacy_default_template ),
				'5.3.0',
				'molongui_authorship/admin/author/conversion_template'
			);

		} else {

			$conversion_template = apply_filters(
				'molongui_authorship/admin/author/conversion_template',
				$default_conversion_template,
				'guest',
				(int) $post->ID,
				$author_conversion
			);
		}

		if ( ! is_string( $conversion_template ) || ! is_readable( $conversion_template ) ) {
			$conversion_template = $default_conversion_template;
		}

		require $conversion_template;
	}


	public static function update_guest_count() {
		$post_type = Guest_Author::get_post_type();

		/*!
		 * FILTER HOOK
		 *
		 * Allows the use of 'wp_count_posts' instead of a custom SQL query to count the number of guest authors.
		 *
		 * When dealing with a large number of guests, using 'wp_count_posts' can become slow. A more efficient way
		 * to get the guest count is to run a custom SQL query directly on the database. This approach bypasses
		 * the overhead of 'wp_count_posts' and can be significantly faster.
		 *
		 * @since 4.9.5
		 */
		if ( apply_filters( 'molongui_authorship/guest_count_custom_sql_query', true ) ) {
			global $wpdb;

			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s",
				$post_type,
				'publish'
			);

			$guest_count = $wpdb->get_var( $query );
		} else {
			$guest_count = wp_count_posts( $post_type );
			$guest_count = isset( $guest_count->publish ) ? $guest_count->publish : 0;
		}

		update_option( 'molongui_authorship_guest_count', $guest_count, false );
	}


	public static function clear_object_cache() {
		WP::deprecated_function_once( __FUNCTION__, '5.2.0' );

		Cache::clear( 'guests' );
		Cache::clear( 'posts' );
	}

	public function render_bio_metabox( $post ) {
		$guest_author_bio = get_post_field( 'post_content', $post->ID );

		wp_editor(
			$guest_author_bio,
			'content',
			array(
				'media_buttons' => false,
				  'textarea_rows' => 10,
				'editor_css'    => '<style>#wp-content-editor-tools{background:none;padding-top:0;}</style>',
			)
		);
	}

	public function render_short_bio_metabox( $post ) {
		$default_file = MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-short-bio-metabox.php';

		$file = apply_filters_deprecated(
			'authorship/admin/guest/shortbio_metabox_html',
			array( $default_file ),
			'5.3.0',
			'molongui_authorship/admin/author/biography_fields'
		);

		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			$file = $default_file;
		}

		include $file;
	}

	public function add_short_bio_metabox_class( $classes ) {
		if ( ! Plugin::has_pro() ) {
			$classes[] = 'free';
		}
		return $classes;
	}

	public function render_social_metabox( $post ) {
		$networks = Social::get( 'enabled' );

		include MOLONGUI_AUTHORSHIP_DIR . 'views/guest-author/html-admin-social-metabox.php';
	}

	public function add_conversion_metabox_class( $classes ) {
		if ( ! Plugin::has_pro() ) {
			$classes[] = 'free';
		}
		return $classes;
	}

}  

new Admin_Guest_Author();
