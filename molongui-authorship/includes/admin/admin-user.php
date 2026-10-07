<?php
/*!
 * Provides administrative functionality for managing user data in the context of Molongui Authorship.
 *
 * This file is responsible for handling various tasks related to user management in the admin area.
 *
 * Note: This class does not maintain state and should not be used to store user data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Common\Modules\Media_Picker;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) || exit;  

class Admin_User extends \Molongui\Authorship\Common\Utils\User {

	const EDIT_USER_SCRIPT = MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-user.9932.min.js';
	const AUTHOR_PROFILE_TAB_SEEN_META = 'molongui_authorship_author_profile_tab_seen';

	const AUTHOR_PROFILE_VIEW_QUERY_ARG = 'molongui_authorship_view';

	const AUTHOR_PROFILE_VIEW_QUERY_VALUE = 'author-profile';

	const DELETE_ACTION_REASSIGN_GUEST = 'molongui_reassign_guest';

	const DELETE_ACTION_REMOVE_AUTHORSHIP = 'molongui_remove_authorship';

	const DELETE_ACTION_CONVERT_GUEST = 'molongui_convert_guest';

	private static $user_deletion_requests = array();

	const EDIT_AVATAR_SCRIPT = MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-avatar.f5d1.min.js';

	use Singleton;

	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'register_user_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_user_scripts' ) );
		add_filter( 'authorship/edit_user_script_params', array( $this, 'edit_user_script_params' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_user_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_delete_user_form_styles' ) );

		add_filter( 'manage_users_columns', array( $this, 'edit_admin_columns' ) );
		add_action( 'manage_users_custom_column', array( $this, 'fill_admin_columns' ), 10, 3 );
		add_filter( 'users_have_additional_content', array( $this, 'users_have_additional_content' ), 10, 2 );
		add_filter( 'wp_dropdown_users_args', array( $this, 'filter_delete_user_reassignment_dropdown' ), 20, 2 );
		add_action( 'delete_user_form', array( $this, 'extend_delete_user_form' ), 10, 2 );
		add_action( 'load-users.php', array( $this, 'prepare_user_deletion_request' ), 1 );


		add_action( 'wp_ajax_authorship_mark_author_profile_tab_seen', array( $this, 'mark_author_profile_tab_seen' ) );
		add_filter( 'user_profile_picture_description', array( $this, 'picture_description' ), 10, 2 );
		add_action( 'edit_user_profile', array( $this, 'add_custom_profile_fields' ), 0 );  
		add_action( 'show_user_profile', array( $this, 'add_custom_profile_fields' ), 0 );  
		add_action( 'profile_update', array( $this, 'save_custom_fields' ) );
		add_action( 'delete_user', array( $this, 'handle_user_deletion' ), 10, 3 );

		add_action( 'admin_footer-profile.php', array( $this, 'add_contact_info_notice' ) );
		add_action( 'admin_footer-user-edit.php', array( $this, 'add_contact_info_notice' ) );

		add_action( 'user_register', array( __CLASS__, 'update_user_count' ) );  
		add_action( 'profile_update', array( __CLASS__, 'update_user_count' ) );  
		add_action( 'deleted_user', array( __CLASS__, 'update_user_count' ) );  
		add_action( 'set_user_role', array( __CLASS__, 'update_user_count' ) );  

		add_action( 'admin_notices', array( __CLASS__, 'post_as_others_admin_notice' ) );

		if ( Settings::is_enabled( 'co-authors' ) ) {
			add_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX, 4 );
			add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), PHP_INT_MAX, 4 );
		}
	}


	public function edit_admin_columns( $column_headers ) {
		unset( $column_headers['posts'] );

		$column_headers['molongui-entries'] = __( 'Entries', 'molongui-authorship' );

		if ( Settings::is_enabled( 'author-box' ) ) {
			$column_headers['molongui-box'] = __( 'Author Box', 'molongui-authorship' );
		}

		$column_headers['user-id'] = __( 'ID' );

		return $column_headers;
	}

	public function fill_admin_columns( $value, $column, $ID ) {

		if ( 'user-id' === $column ) {
			return $ID;
		} elseif ( 'molongui-entries' === $column ) {
			$html       = '';
			$post_types = Settings::enabled_post_types( 'all', 'object' );

			$post_types_id = array_column( $post_types, 'id' );
			foreach ( array( 'post', 'page' ) as $post_type ) {
				if ( ! in_array( $post_type, $post_types_id, true ) ) {
					$post_type_obj = get_post_type_object( $post_type );
					$post_types    = array_merge(
						$post_types,
						array(
							array(
								'id'       => $post_type,
								'label'    => $post_type_obj->label,
								'singular' => $post_type_obj->labels->singular_name,
							),
						)
					);
				}
			}

			foreach ( $post_types as $post_type ) {
				$count = get_user_meta( $ID, 'molongui_author_' . $post_type['id'] . '_count', true );
				$link  = admin_url( 'edit.php?post_type=' . $post_type['id'] . '&author=' . $ID );

				if ( $count > 0 ) {
					$html .= '<div><a href="' . $link . '">' . $count . ' ' . ( $count == 1 ? $post_type['singular'] : $post_type['label'] ) . '</a></div>';
				}
			}

			if ( ! $html ) {
				$html = __( 'None' );
			}

			return $html;
		} elseif ( 'molongui-box' === $column ) {
			$author = new Author( $ID, 'user' );

			switch ( $author->get_box_display() ) {
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

			$html  = '<div class="m-tooltip">';
			$html .= '<span class="dashicons dashicons-' . $icon . '"></span>';
			$html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">' . $tip . '</span>';
			$html .= '</div>';

			return $html;
		}

		return $value;
	}


	public function add_contact_info_notice() {
		if ( ! Settings::is_enabled( 'user-profile' ) ) {
			return;
		}

		if ( 'profile.php' === $GLOBALS['pagenow'] ) {
			$user_id = get_current_user_id();
		} elseif ( 'user-edit.php' === $GLOBALS['pagenow'] && ! empty( $_GET['user_id'] ) ) {
			$user_id = absint( $_GET['user_id'] );
		} else {
			return;
		}

		if ( empty( $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$compatible_social = User::get_registered_compatible_social_meta_keys( $user );

		if ( empty( $compatible_social ) ) {
			return;
		}

		?>
		<script>
			document.addEventListener( 'DOMContentLoaded', function() {
				var emailRow = document.querySelector( '#your-profile .user-email-wrap' );

				if ( ! emailRow ) {
					return;
				}

				var table = emailRow.closest( '.form-table' );

				if ( ! table ) {
					return;
				}

				var heading = table.previousElementSibling;

				if ( ! heading || 'H2' !== heading.tagName ) {
					return;
				}

				if ( document.querySelector( '.molongui-contact-info-description' ) ) {
					return;
				}

				var notice = document.createElement( 'p' );

				notice.className = 'description molongui-contact-info-description';
				notice.textContent =
				<?php
				echo wp_json_encode(
					__(
						'Molongui Authorship can use compatible social profiles entered in this section. Additional social networks and author profile fields are available in the Molongui Author Profile section below.',
						'molongui-authorship'
					)
				);
				?>
				;

				heading.insertAdjacentElement( 'afterend', notice );
			} );
		</script>
		<?php
	}

	public function add_custom_profile_fields( $user ) {
		if ( is_object( $user ) ) {
			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				if ( ! current_user_can( 'read', $user->ID ) or get_current_user_id() !== $user->ID ) {
					return;
				}
			}

			$match = array_intersect( $user->roles, Settings::enabled_user_roles() );
			if ( empty( $match ) ) {
				return;
			}
		} else {
			if ( 'add-new-user' !== $user ) {
				return;
			}

			$user     = new \stdClass();
			$user->ID = 0;
		}

		wp_nonce_field( 'molongui_authorship_update_user', 'molongui_authorship_update_user_nonce' );

		if ( Settings::is_enabled( 'user-profile' ) ) {

			$current_screen = get_current_screen();
			$is_own_profile = $current_screen && 'profile' === $current_screen->id;

			$profile_author = !empty( $user->ID )
				? new Author( (int) $user->ID, 'user' )
				: null;

			$public_name = array(
				'fields' => array(
					array(
						'control'     => 'output',
						'id'          => 'molongui-public-display-name',
						'label'       => __( 'Display Name', 'molongui-authorship' ),
						'value'       => $profile_author
							? $profile_author->get_base_display_name()
							: '',
						'description' => __( 'Managed in the User Account tab.', 'molongui-authorship' ),
						'action'      => array(
							'label'  => __( 'Edit', 'molongui-authorship' ),
							'href'   => '#display_name',
							'class'  => 'molongui-edit-core-user-field',
							'target' => 'display_name',
						),
					),
					array(
						'control'     => 'input',
						'id'          => 'molongui_author_name_prefix',
						'name'        => 'molongui_author_name_prefix',
						'label'       => __( 'Name prefix', 'molongui-authorship' ),
						'value'       => $profile_author
							? $profile_author->get_name_prefix()
							: '',
						'description' => __( 'Examples: Dr., Prof., or Rev.', 'molongui-authorship' ),
					),
					array(
						'control'     => 'input',
						'id'          => 'molongui_author_name_suffix',
						'name'        => 'molongui_author_name_suffix',
						'label'       => __( 'Name suffix', 'molongui-authorship' ),
						'value'       => $profile_author
							? $profile_author->get_name_suffix()
							: '',
						'description' => __( 'Examples: Jr., Sr., II, or III.', 'molongui-authorship' ),
					),
					array(
						'control'     => 'input',
						'id'          => 'molongui_author_credentials',
						'name'        => 'molongui_author_credentials',
						'label'       => __( 'Credentials', 'molongui-authorship' ),
						'value'       => $profile_author
							? $profile_author->get_credentials()
							: '',
						'description' => __( 'Examples: MD, PhD, RN, or CPA. Separate multiple credentials with commas.', 'molongui-authorship' ),
					),
				),
				'preview' => $profile_author
					? $profile_author->get_display_name()
					: '',
			);

			$biography = array(
				'full' => array(
					'control'     => 'textarea',
					'id'          => 'description',
					'name'        => 'description',
					'value'       => ! empty( $user->ID )
						? (string) get_user_meta( $user->ID, 'description', true )
						: '',
					'rows'        => 12,
					'description' => $is_own_profile
						? __( 'Your main, detailed biography.', 'molongui-authorship' )
						: __( "This author's main, detailed biography.", 'molongui-authorship' ),
					'settings'    => array(),
					'locked'      => false,
				),
				'short' => array(
					'show'        => Settings::is_enabled( 'author-box' ),
					'control'     => 'textarea',
					'id'          => '_premium_short_bio_field',
					'name'        => '',
					'value'       => '',
					'rows'        => 4,
					'description' => __( 'A concise alternative for places where a shorter bio is preferred.', 'molongui-authorship' ),
					'settings'    => array(),
					'locked'      => true,
				),
			);

			$biography = apply_filters(
				'molongui_authorship/admin/author/biography_fields',
				$biography,
				'user',
				(int) $user->ID
			);

			$default_biography_template = MOLONGUI_AUTHORSHIP_DIR . 'views/admin/author/html-biography.php';

			$biography_template = apply_filters(
				'authorship/edit/user/bio/tmpl',
				$default_biography_template
			);

			if ( ! is_string( $biography_template ) || ! is_readable( $biography_template ) ) {
				$biography_template = $default_biography_template;
			}

			$professional_info = array(
				'professional_headline' => array(
					'id'    => 'molongui_author_professional_headline',
					'name'  => 'molongui_author_professional_headline',
					'value' => $profile_author
						? $profile_author->get_professional_headline()
						: '',
				),
				'job' => array(
					'id'    => 'molongui_author_job',
					'name'  => 'molongui_author_job',
					'value' => $profile_author
						? $profile_author->get_meta( 'job' )
						: '',
				),
				'company' => array(
					'id'    => 'molongui_author_company',
					'name'  => 'molongui_author_company',
					'value' => $profile_author
						? $profile_author->get_meta( 'company' )
						: '',
				),
				'department' => array(
					'id'    => 'molongui_author_department',
					'name'  => 'molongui_author_department',
					'value' => $profile_author
						? $profile_author->get_department()
						: '',
				),
				'company_link' => array(
					'id'    => 'molongui_author_company_link',
					'name'  => 'molongui_author_company_link',
					'value' => $profile_author
						? $profile_author->get_meta( 'company_link' )
						: '',
				),
			);

			$contact_info = array(
				'fields' => array(
					array(
						'control'     => 'input',
						'type'        => 'text',
						'id'          => 'molongui_author_location',
						'name'        => 'molongui_author_location',
						'label'       => __( 'Location', 'molongui-authorship' ),
						'value'       => $profile_author->get_location(),
						'description' => '',
					),
					array(
						'control'     => 'output',
						'id'          => 'molongui-public-email',
						'label'       => __( 'Email', 'molongui-authorship' ),
						'value'       => ! empty( $user->user_email )
							? $user->user_email
							: '',
						'description' => __( 'Managed in the User Account tab.', 'molongui-authorship' ),
						'action'      => array(
							'label'  => __( 'Edit', 'molongui-authorship' ),
							'href'   => '#email',
							'class'  => 'molongui-edit-core-user-field',
							'target' => 'email',
						),
					),
					array(
						'control'     => 'output',
						'id'          => 'molongui-public-website',
						'label'       => __( 'Website', 'molongui-authorship' ),
						'value'       => ! empty( $user->user_url )
							? $user->user_url
							: '',
						'description' => __( 'Managed in the User Account tab.', 'molongui-authorship' ),
						'action'      => array(
							'label'  => __( 'Edit', 'molongui-authorship' ),
							'href'   => '#url',
							'class'  => 'molongui-edit-core-user-field',
							'target' => 'url',
						),
					),
					array(
						'control'     => 'input',
						'type'        => 'tel',
						'id'          => 'molongui_author_phone',
						'name'        => 'molongui_author_phone',
						'label'       => __( 'Phone', 'molongui-authorship' ),
						'value'       => $profile_author->get_meta( 'phone' ),
						'description' => '',
					),
				),
			);

			$networks              = Social::get( 'enabled' );
			$contact_social_values = array();
			$social_profiles       = array(
				'settings_url'           => Settings::url( 'integrations' ),
				'has_inherited_profiles' => false,
				'fields'                 => array(),
			);

			if ( ! empty( $user->ID ) && is_array( $networks ) ) {

				$compatible_social = User::get_registered_compatible_social_meta_keys( $user );
				$user_meta         = get_user_meta( $user->ID );

				foreach ( $compatible_social as $network_id => $meta_keys ) {

					if ( ! is_array( $meta_keys ) ) {
						continue;
					}

					foreach ( $meta_keys as $meta_key ) {

						if (
							! is_string( $meta_key )
							||
							'' === $meta_key
							||
							empty( $user_meta[ $meta_key ][0] )
						) {
							continue;
						}

						$value = trim( (string) $user_meta[ $meta_key ][0] );

						if ( '' === $value ) {
							continue;
						}

						$contact_social_values[ $network_id ] = $value;
						break;
					}
				}
			}

			$is_pro = Plugin::has_pro();

			if ( is_array( $networks ) ) {

				foreach ( $networks as $id => $network ) {

					$field_id = Author::USER_META_PREFIX . $id;
					$icon_id  = sanitize_html_class( $id );
					$locked   = ! $is_pro && ! empty( $network['premium'] );

					$molongui_value = '';

					if ( ! $locked && ! empty( $user->ID ) ) {
						$molongui_value = (string) get_the_author_meta(
							$field_id,
							$user->ID
						);
					}

					$fallback_value = '';

					if (
						! $locked
						&&
						'' === $molongui_value
						&&
						! empty( $contact_social_values[ $id ] )
					) {
						$fallback_value = $contact_social_values[ $id ];
					}

					$social_profiles['fields'][] = array(
						'id'             => $field_id,
						'name'           => $locked ? '' : $field_id,
						'label'          => isset( $network['name'] )
							? $network['name']
							: $id,
						'icon_class'     => 'm-a-icon-' . $icon_id,
						'value'          => $locked
							? ''
							: ( '' !== $molongui_value ? $molongui_value : $fallback_value ),
						'example'        => isset( $network['url'] )
							? $network['url']
							: '',
						'example_id'     => 'molongui-social-example-' . $icon_id,
						'fallback_id'    => 'molongui-social-fallback-' . $icon_id,
						'fallback_value' => $fallback_value,
						'has_own_value'  => '' !== $molongui_value,
						'locked'         => $locked,
					);
				}
			}

			$social_profiles['has_inherited_profiles'] = ! empty( $contact_social_values );

			$author_status = array(
				'id'      => 'molongui_author_archived',
				'name'    => 'molongui_author_archived',
				'checked' => ! empty( $user->ID )
					? (bool) get_user_meta(
						$user->ID,
						'molongui_author_archived',
						true
					)
					: false,
			);

			$author_box_options = Settings::get();

			$author_box_display = $profile_author
				? $profile_author->get_box_display()
				: 'default';

			$custom_link_active = (
				'custom' === $author_box_options['author_box_avatar_link']
				||
				'custom' === $author_box_options['author_box_name_link']
			);

			if ( ! Settings::is_enabled( 'author-box' ) ) {
				$author_box_status = __( 'Disabled', 'molongui-authorship' );
			} else {
				switch ( $author_box_display ) {
					case 'show':
						$author_box_status = __( 'Shown', 'molongui-authorship' );
						break;

					case 'hide':
						$author_box_status = __( 'Hidden', 'molongui-authorship' );
						break;

					case 'default':
					default:
						$author_box_status = __( 'Global settings', 'molongui-authorship' );
						break;
				}
			}

			$author_meta_visibility = Author_Meta_Fields::get_author_visibility( $profile_author );
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
				'enabled'      => Settings::is_enabled( 'author-box' ),
				'settings_url' => Settings::url( 'reading' ),
				'status'       => $author_box_status,
				'display'      => array(
					'id'     => 'molongui_author_box_display',
					'name'   => 'molongui_author_box_display',
					'value'  => $author_box_display,
					'locked' => ! $is_pro,
					'filter' => '',
				),
				'custom_link' => array(
					'id'     => 'molongui_author_custom_link',
					'name'   => 'molongui_author_custom_link',
					'value'  => $profile_author
						? (string) $profile_author->get_meta( 'custom_link' )
						: '',
					'active' => $custom_link_active,
				),
				'meta_visibility' => array(
					'name'   => 'molongui_author_box_meta_visibility',
					'fields' => $author_meta_fields,
				),
				'icons' => array(
					'website' => array(
						'id'      => 'molongui_author_show_icon_web',
						'name'    => 'molongui_author_show_icon_web',
						'checked' => $profile_author
							? (bool) $profile_author->get_meta( 'show_icon_web' )
							: false,
					),
					'email' => array(
						'id'      => 'molongui_author_show_icon_mail',
						'name'    => 'molongui_author_show_icon_mail',
						'checked' => $profile_author
							? (bool) $profile_author->get_meta( 'show_icon_mail' )
							: false,
					),
					'phone' => array(
						'id'      => 'molongui_author_show_icon_phone',
						'name'    => 'molongui_author_show_icon_phone',
						'checked' => $profile_author
							? (bool) $profile_author->get_meta( 'show_icon_phone' )
							: false,
					),
				),
			);

			$author_conversion = array(
				'description'  => __(
					'Turn this WordPress user into a Guest Author while preserving their post authorship.',
					'molongui-authorship'
				),
				'button_label' => __(
					'Convert to Guest',
					'molongui-authorship'
				),
			);

			include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/user/html-author-profile.php';
		} elseif ( Settings::is_enabled( 'local-avatar' ) ) {
			include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/user/html-profile-picture-standalone.php';
		}
	}

	public function mark_author_profile_tab_seen() {

		check_ajax_referer(
			'authorship_author_profile_tab_seen',
			'nonce'
		);

		update_user_meta(
			get_current_user_id(),
			self::AUTHOR_PROFILE_TAB_SEEN_META,
			1
		);

		wp_send_json_success();
	}

	public function picture_description( $description, $profileuser ) {

		$user_profile   = Settings::is_enabled( 'user-profile' );
		$local_avatar   = Settings::is_enabled( 'local-avatar' );
		$gravatar       = Settings::is_enabled( 'gravatar' );
		$custom_default = Avatar::has_custom_default();

		if ( ! $gravatar ) {
			if ( $local_avatar ) {
				$local_picture_message = $custom_default
					? __( 'Gravatar is disabled. %1$sUpload a local profile picture%2$s to override the configured default avatar.', 'molongui-authorship' )
					: __( 'Gravatar is disabled. %1$sUpload a local profile picture%2$s for this author.', 'molongui-authorship' );

				$additional_description = sprintf(
					wp_kses(
						// translators: 1: Opening link tag. 2: Closing link tag.
						$local_picture_message,
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					$user_profile
						? '<a href="#molongui-local-avatar">'
						: '<a href="#molongui-local-avatar-row">',
					'</a>'
				);

				return $additional_description;
			}

			if ( $custom_default ) {
				return __( 'Gravatar is disabled. The custom default avatar configured in Molongui Authorship is being used.', 'molongui-authorship' );
			}

			return __( 'No author profile picture is currently enabled in Molongui Authorship.', 'molongui-authorship' );
		}

		if ( ! $user_profile && $local_avatar ) {
			$additional_description = sprintf(
				wp_kses(
					// translators: 1: Opening link tag. 2: Closing link tag.
					__( 'Or %1$supload a local profile picture%2$s from your Media Library below.', 'molongui-authorship' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				'<a href="#molongui-local-avatar-row">',
				'</a>'
			);

			return $description
				? $description . '<br>' . $additional_description
				: $additional_description;
		}

		if ( $user_profile && $local_avatar ) {
			$additional_description = sprintf(
				wp_kses(
					// translators: 1: Opening link tag. 2: Closing link tag.
					__( 'Or you can upload a local profile picture using the %1$sMolongui Authorship field%2$s.', 'molongui-authorship' ),
					array(
						'a' => array(
							'href' => array(),
						),
					)
				),
				'<a href="#molongui-user-fields">',
				'</a>'
			);

			return $description . '<br>' . $additional_description;
		}

		$additional_description = sprintf(
			wp_kses(
				// translators: 1: Opening link tag. 2: Closing link tag.
				__( 'Or you can upload a local profile picture by enabling the Molongui Authorship "Local Avatar" option %1$shere%2$s.', 'molongui-authorship' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
			'<a href="' . esc_url( Settings::url() ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		);

		return $description . '<br>' . $additional_description;
	}

	public function save_custom_fields( $user_id ) {

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			if ( ! current_user_can( 'read', $user_id ) || get_current_user_id() !== $user_id ) {
				return $user_id;
			}
		}

		if ( ! WP::verify_nonce( 'molongui_authorship_update_user' ) ) {
			return $user_id;
		}

		if ( Settings::is_enabled( 'user-profile' ) ) {

			$profile_fields = array(
				'molongui_author_name_prefix'           => 'text',
				'molongui_author_name_suffix'           => 'text',
				'molongui_author_credentials'           => 'text',
				'molongui_author_professional_headline' => 'text',
				'molongui_author_job'                   => 'text',
				'molongui_author_company'               => 'text',
				'molongui_author_department'            => 'text',
				'molongui_author_company_link'          => 'url',
				'molongui_author_location'              => 'text',
				'molongui_author_phone'                 => 'text',
			);

			foreach ( $profile_fields as $meta_key => $field_type ) {
				$value = '';

				if ( isset( $_POST[ $meta_key ] ) && is_string( $_POST[ $meta_key ] ) ) {
					$value = wp_unslash( $_POST[ $meta_key ] );

					switch ( $field_type ) {
						case 'url':
							$value = esc_url_raw( $value );
							break;

						case 'text':
						default:
							$value = sanitize_text_field( $value );
							break;
					}
				}

				update_user_meta( $user_id, $meta_key, $value );
			}

			$is_pro = Plugin::has_pro();

			$compatible_social = User::get_registered_compatible_social_meta_keys( $user_id );

			foreach ( (array) Social::get( 'enabled' ) as $id => $network ) {

				if ( ! $is_pro && ! empty( $network['premium'] ) ) {
					continue;
				}

				$key = Author::USER_META_PREFIX . $id;

				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}

				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

				$own_value = (string) get_user_meta(
					$user_id,
					$key,
					true
				);

				$inherited_value = '';

				if (
					'' === $own_value
					&&
					! empty( $compatible_social[ $id ] )
					&&
					is_array( $compatible_social[ $id ] )
				) {
					foreach ( $compatible_social[ $id ] as $meta_key ) {

						if ( ! is_string( $meta_key ) || '' === $meta_key ) {
							continue;
						}

						$external_value = get_user_meta(
							$user_id,
							$meta_key,
							true
						);

						if ( ! is_scalar( $external_value ) ) {
							continue;
						}

						$external_value = trim( (string) $external_value );

						if ( '' !== $external_value ) {
							$inherited_value = $external_value;
							break;
						}
					}
				}

				if (
					'' === $own_value
					&&
					'' !== $inherited_value
					&&
					$value === $inherited_value
				) {
					continue;
				}

				if ( '' === $value ) {
					delete_user_meta( $user_id, $key );
				} else {
					update_user_meta( $user_id, $key, $value );
				}
			}

			$checkboxes = array(
				'molongui_author_archived',
			);

			foreach ( $checkboxes as $meta_key ) {
				if ( isset( $_POST[ $meta_key ] ) && is_string( $_POST[ $meta_key ] ) ) {
					update_user_meta(
						$user_id,
						$meta_key,
						sanitize_text_field(
							wp_unslash( $_POST[ $meta_key ] )
						)
					);
				} else {
					delete_user_meta( $user_id, $meta_key );
				}
			}

			if ( Settings::is_enabled( 'author-box' ) ) {

				$box_display = 'default';

				if ( $is_pro && isset( $_POST['molongui_author_box_display'] ) ) {

					$submitted_display = sanitize_key(
						wp_unslash( $_POST['molongui_author_box_display'] )
					);

					if (
						in_array(
							$submitted_display,
							array( 'default', 'show', 'hide' ),
							true
						)
					) {
						$box_display = $submitted_display;

						update_user_meta(
							$user_id,
							'molongui_author_box_display',
							$box_display
						);
					}
				}

				if ( isset( $_POST['molongui_author_custom_link'] ) ) {
					update_user_meta(
						$user_id,
						'molongui_author_custom_link',
						esc_url_raw(
							wp_unslash( $_POST['molongui_author_custom_link'] )
						)
					);
				}

				if ( 'hide' !== $box_display ) {

					if ( isset( $_POST['molongui_author_box_meta_visibility'] ) && is_array( $_POST['molongui_author_box_meta_visibility'] ) ) {
						Author_Meta_Fields::update_author_visibility(
							new Author( $user_id, 'user' ),
							wp_unslash( $_POST['molongui_author_box_meta_visibility'] )
						);
					}

					$box_checkboxes = array(
						'molongui_author_show_icon_mail',
						'molongui_author_show_icon_web',
						'molongui_author_show_icon_phone',
					);

					foreach ( $box_checkboxes as $meta_key ) {
						if ( isset( $_POST[ $meta_key ] ) ) {
							update_user_meta(
								$user_id,
								$meta_key,
								'1'
							);
						} else {
							delete_user_meta( $user_id, $meta_key );
						}
					}
				}
			}

			do_action( 'authorship/user/save', $user_id, $_POST );
		}

		if ( Settings::is_enabled( 'local-avatar' ) && current_user_can( 'upload_files' ) ) {
			if ( isset( $_POST['molongui_author_image_id'] ) ) {
				update_user_meta(
					$user_id,
					'molongui_author_image_id',
					absint( $_POST['molongui_author_image_id'] )
				);
			}

			if ( isset( $_POST['molongui_author_image_url'] ) && is_string( $_POST['molongui_author_image_url'] ) ) {
				update_user_meta(
					$user_id,
					'molongui_author_image_url',
					esc_url_raw(
						wp_unslash( $_POST['molongui_author_image_url'] )
					)
				);
			}

			if ( isset( $_POST['molongui_author_image_edit'] ) && is_string( $_POST['molongui_author_image_edit'] ) ) {
				update_user_meta(
					$user_id,
					'molongui_author_image_edit',
					esc_url_raw(
						wp_unslash( $_POST['molongui_author_image_edit'] )
					)
				);
			}
		}

		return $user_id;
	}

	public function users_have_additional_content( $has_content, $user_ids ) {
		if ( $has_content ) {
			return true;
		}

		foreach ( (array) $user_ids as $user_id ) {
			$user_id = absint( $user_id );

			if ( $user_id && Post_Authorship::author_has_explicit_relations( 'user-' . $user_id ) ) {
				return true;
			}
		}

		return false;
	}

	public function extend_delete_user_form( $current_user, $user_ids ) {
		if ( is_multisite() && is_network_admin() ) {
			return;
		}

		$user_ids       = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
		$guest_enabled  = Settings::is_guest_author_enabled();
		$guest_authors  = $guest_enabled ? $this->get_delete_form_guest_authors() : array();
		$pro_conversion = $guest_enabled && (bool) apply_filters( 'molongui_authorship/user_deletion/convert_to_guest_available', false );

		if ( empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			if ( (int) $current_user->ID === $user_id ) {
				continue;
			}

			$needs_native_owner = Post_Authorship::has_unmanaged_native_authored_content( $user_id );
			?>
			<div class="molongui-user-delete-options" data-user-id="<?php echo esc_attr( $user_id ); ?>" hidden>
				<p class="description" data-molongui-delete-note>
					<?php
					esc_html_e(
						'For content managed by Molongui Authorship, only posts where this user is the main author will be deleted; secondary co-author credits are removed from surviving posts.',
						'molongui-authorship'
					);
					?>
				</p>

				<li data-molongui-delete-option="<?php echo esc_attr( self::DELETE_ACTION_REASSIGN_GUEST ); ?>">
					<input
						type="radio"
						id="molongui_reassign_guest_<?php echo esc_attr( $user_id ); ?>"
						name="delete_option[<?php echo esc_attr( $user_id ); ?>]"
						value="<?php echo esc_attr( self::DELETE_ACTION_REASSIGN_GUEST ); ?>"
						<?php disabled( ! $guest_enabled || empty( $guest_authors ) ); ?>
						required
					/>
					<label for="molongui_reassign_guest_<?php echo esc_attr( $user_id ); ?>">
						<?php esc_html_e( 'Attribute authored content to a Guest Author.', 'molongui-authorship' ); ?>
						<span class="description"><?php esc_html_e( 'by Molongui Authorship', 'molongui-authorship' ); ?></span>
					</label>
					<?php if ( $guest_enabled && ! empty( $guest_authors ) ) : ?>
						<label class="screen-reader-text" for="molongui_guest_reassign_<?php echo esc_attr( $user_id ); ?>">
							<?php esc_html_e( 'Select a Guest Author to attribute the authored content to.', 'molongui-authorship' ); ?>
						</label>
						<select
							id="molongui_guest_reassign_<?php echo esc_attr( $user_id ); ?>"
							name="molongui_guest_reassign[<?php echo esc_attr( $user_id ); ?>]"
							data-molongui-required-for="<?php echo esc_attr( self::DELETE_ACTION_REASSIGN_GUEST ); ?>"
						>
							<option value=""><?php esc_html_e( 'Select a Guest Author', 'molongui-authorship' ); ?></option>
							<?php foreach ( $guest_authors as $guest_id => $guest_name ) : ?>
								<option value="<?php echo esc_attr( $guest_id ); ?>"><?php echo esc_html( $guest_name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<p class="description">
							<?php
							echo esc_html(
								$guest_enabled
									? __( 'Create a Guest Author before using this option.', 'molongui-authorship' )
									: __( 'Enable Guest Authors to use this option.', 'molongui-authorship' )
							);
							?>
						</p>
					<?php endif; ?>
					<?php $this->delete_form_native_owner_fields( $user_id, self::DELETE_ACTION_REASSIGN_GUEST, $user_ids, $needs_native_owner ); ?>
				</li>

				<li data-molongui-delete-option="<?php echo esc_attr( self::DELETE_ACTION_REMOVE_AUTHORSHIP ); ?>">
					<input
						type="radio"
						id="molongui_remove_authorship_<?php echo esc_attr( $user_id ); ?>"
						name="delete_option[<?php echo esc_attr( $user_id ); ?>]"
						value="<?php echo esc_attr( self::DELETE_ACTION_REMOVE_AUTHORSHIP ); ?>"
						required
					/>
					<label for="molongui_remove_authorship_<?php echo esc_attr( $user_id ); ?>">
						<?php esc_html_e( "Keep content and remove this user's authorship.", 'molongui-authorship' ); ?>
						<span class="description"><?php esc_html_e( 'by Molongui Authorship', 'molongui-authorship' ); ?></span>
					</label>
					<p class="description">
						<?php
						esc_html_e(
							'Posts losing their main author will be moved to Draft when required. Posts where this user is only a co-author will keep their current status.',
							'molongui-authorship'
						);
						?>
					</p>
					<?php $this->delete_form_native_owner_fields( $user_id, self::DELETE_ACTION_REMOVE_AUTHORSHIP, $user_ids, $needs_native_owner ); ?>
				</li>

				<li data-molongui-delete-option="<?php echo esc_attr( self::DELETE_ACTION_CONVERT_GUEST ); ?>">
					<input
						type="radio"
						id="molongui_convert_guest_<?php echo esc_attr( $user_id ); ?>"
						name="delete_option[<?php echo esc_attr( $user_id ); ?>]"
						value="<?php echo esc_attr( self::DELETE_ACTION_CONVERT_GUEST ); ?>"
						<?php disabled( ! $pro_conversion ); ?>
						required
					/>
					<label for="molongui_convert_guest_<?php echo esc_attr( $user_id ); ?>">
						<?php esc_html_e( 'Convert to Guest Author and preserve authorship.', 'molongui-authorship' ); ?>
						<span class="description"><?php esc_html_e( 'by Molongui Authorship Pro', 'molongui-authorship' ); ?></span>
					</label>
					<p class="description">
						<?php
						esc_html_e(
							"Removes the WordPress account while preserving this author's Molongui Authorship attribution, role, and position.",
							'molongui-authorship'
						);
						?>
					</p>
					<?php $this->delete_form_native_owner_fields( $user_id, self::DELETE_ACTION_CONVERT_GUEST, $user_ids, $needs_native_owner ); ?>
				</li>
			</div>
			<?php
		}

		?>
		<span data-molongui-delete-options-ready hidden></span>
		<?php
	}

	public function filter_delete_user_reassignment_dropdown( $query_args, $parsed_args ) {
		global $pagenow;

		if ( 'users.php' !== $pagenow || ( is_multisite() && is_network_admin() ) ) {
			return $query_args;
		}

		$name = isset( $parsed_args['name'] ) && is_string( $parsed_args['name'] )
			? $parsed_args['name']
			: '';

		if ( 'reassign_user' !== $name && 0 !== strpos( $name, 'reassign_user[' ) ) {
			return $query_args;
		}

		$enabled_roles = array_values(
			array_filter(
				array_unique(
					array_map( 'sanitize_key', (array) Settings::enabled_user_roles() )
				)
			)
		);

		if ( empty( $enabled_roles ) ) {
			$query_args['role__in'] = array( '__molongui_no_enabled_author_roles__' );
			return $query_args;
		}

		if ( ! empty( $query_args['role__in'] ) && is_array( $query_args['role__in'] ) ) {
			$existing_roles = array_values(
				array_filter(
					array_unique(
						array_map( 'sanitize_key', $query_args['role__in'] )
					)
				)
			);
			$enabled_roles  = array_values( array_intersect( $enabled_roles, $existing_roles ) );

			if ( empty( $enabled_roles ) ) {
				$query_args['role__in'] = array( '__molongui_no_enabled_author_roles__' );
				return $query_args;
			}
		}

		$query_args['role__in'] = $enabled_roles;

		return $query_args;
	}

	private function delete_form_native_owner_fields( $user_id, $action, $deleted_user_ids, $needs_native_owner ) {
		if ( ! $needs_native_owner ) {
			return;
		}

		$field_suffix = sanitize_key( str_replace( 'molongui_', '', $action ) );
		$select_name  = 'molongui_native_owner_' . $field_suffix . '[' . absint( $user_id ) . ']';
		$select_id    = 'molongui_native_owner_' . $field_suffix . '_' . absint( $user_id );
		$draft_name   = 'molongui_native_draft_' . $field_suffix . '[' . absint( $user_id ) . ']';
		?>
		<div class="molongui-native-owner-fields" data-molongui-native-fields-for="<?php echo esc_attr( $action ); ?>" hidden>
			<p class="description">
				<?php
				esc_html_e(
					'This user also owns content whose author is managed only by WordPress. Choose a WordPress user to own that content:',
					'molongui-authorship'
				);
				?>
			</p>
			<label class="screen-reader-text" for="<?php echo esc_attr( $select_id ); ?>">
				<?php esc_html_e( 'Select a WordPress user to own content not managed by Molongui Authorship.', 'molongui-authorship' ); ?>
			</label>
			<?php
			echo wp_dropdown_users(
				array(
					'show_option_none' => __( 'Select a user', 'molongui-authorship' ),
					'option_none_value' => '',
					'name'             => $select_name,
					'id'               => $select_id,
					'exclude'          => $deleted_user_ids,
					'echo'             => false,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core returns escaped dropdown markup.
			?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $draft_name ); ?>" value="1" />
				<?php esc_html_e( 'Move that WordPress-only content to Draft.', 'molongui-authorship' ); ?>
			</label>
		</div>
		<?php
	}

	private function get_delete_form_guest_authors() {
		$guest_ids = get_posts(
			array(
				'post_type'              => Guest_Author::get_post_type(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$guests = array();

		foreach ( $guest_ids as $guest_id ) {
			$author = new Author( absint( $guest_id ), 'guest' );
			$name   = $author->get_display_name();

			if ( '' !== (string) $name ) {
				$guests[ absint( $guest_id ) ] = (string) $name;
			}
		}

		return $guests;
	}

	public function prepare_user_deletion_request() {
		if ( is_multisite() ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] )
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
			: '';

		if ( 'dodelete' !== $action || empty( $_REQUEST['users'] ) || ! isset( $_REQUEST['delete_option'] ) ) {
			return;
		}

		if ( ! current_user_can( 'delete_users' ) ) {
			return;
		}

		check_admin_referer( 'delete-users' );

		$user_ids       = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['users'] ) ) ) ) );
		$delete_options = wp_unslash( $_REQUEST['delete_option'] );
		$per_user_core  = is_array( $delete_options );
		$custom_actions = array(
			self::DELETE_ACTION_REASSIGN_GUEST,
			self::DELETE_ACTION_REMOVE_AUTHORSHIP,
			self::DELETE_ACTION_CONVERT_GUEST,
		);

		foreach ( $user_ids as $user_id ) {
			if ( $per_user_core ) {
				$selected_action = isset( $delete_options[ $user_id ] ) && is_string( $delete_options[ $user_id ] )
					? sanitize_key( $delete_options[ $user_id ] )
					: '';
			} else {
				$selected_action = 1 === count( $user_ids ) && is_string( $delete_options )
					? sanitize_key( $delete_options )
					: '';
			}

			if ( ! in_array( $selected_action, $custom_actions, true ) ) {
				continue;
			}

			if ( ! $per_user_core && 1 !== count( $user_ids ) ) {
				wp_die(
					esc_html__( 'Molongui Authorship deletion options can only be used for one user at a time on this WordPress version.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 400 )
				);
			}

			if ( self::DELETE_ACTION_REASSIGN_GUEST === $selected_action && ! Settings::is_guest_author_enabled() ) {
				wp_die(
					esc_html__( 'Enable Guest Authors before attributing content to a Guest Author.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 400 )
				);
			}

			if (
				self::DELETE_ACTION_CONVERT_GUEST === $selected_action
				&& (
					! Settings::is_guest_author_enabled()
					|| ! apply_filters( 'molongui_authorship/user_deletion/convert_to_guest_available', false )
				)
			) {
				wp_die(
					esc_html__( 'Converting a WordPress user to a Guest Author requires Molongui Authorship Pro.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 400 )
				);
			}

			$request = array(
				'action'       => $selected_action,
				'guest_id'     => 0,
				'native_owner' => 0,
				'draft_native' => false,
			);

			if ( self::DELETE_ACTION_REASSIGN_GUEST === $selected_action ) {
				$guest_values = isset( $_REQUEST['molongui_guest_reassign'] ) && is_array( $_REQUEST['molongui_guest_reassign'] )
					? wp_unslash( $_REQUEST['molongui_guest_reassign'] )
					: array();
				$guest_id     = isset( $guest_values[ $user_id ] ) ? absint( $guest_values[ $user_id ] ) : 0;
				$guest_ref    = $guest_id ? Post_Authorship::build_reference( $guest_id, 'guest' ) : '';

				if (
					! $guest_id
					|| ! Post_Authorship::author_exists( $guest_ref )
					|| 'publish' !== get_post_status( $guest_id )
				) {
					wp_die(
						esc_html__( 'Select a valid Guest Author before deleting this user.', 'molongui-authorship' ),
						esc_html__( 'Unable to delete author', 'molongui-authorship' ),
						array( 'response' => 400 )
					);
				}

				$request['guest_id'] = $guest_id;
			}

			$needs_native_owner = Post_Authorship::has_unmanaged_native_authored_content( $user_id );
			$field_suffix       = sanitize_key( str_replace( 'molongui_', '', $selected_action ) );
			$owner_field        = 'molongui_native_owner_' . $field_suffix;
			$draft_field        = 'molongui_native_draft_' . $field_suffix;
			$native_owner       = 0;

			if ( $needs_native_owner ) {
				$owner_values = isset( $_REQUEST[ $owner_field ] ) && is_array( $_REQUEST[ $owner_field ] )
					? wp_unslash( $_REQUEST[ $owner_field ] )
					: array();
				$native_owner = isset( $owner_values[ $user_id ] ) ? absint( $owner_values[ $user_id ] ) : 0;

				if ( ! $this->is_valid_user_deletion_owner( $native_owner, $user_ids ) ) {
					wp_die(
						esc_html__( 'Select a valid WordPress user to own content that is not managed by Molongui Authorship.', 'molongui-authorship' ),
						esc_html__( 'Unable to delete author', 'molongui-authorship' ),
						array( 'response' => 400 )
					);
				}

				$draft_values            = isset( $_REQUEST[ $draft_field ] ) && is_array( $_REQUEST[ $draft_field ] )
					? wp_unslash( $_REQUEST[ $draft_field ] )
					: array();
				$request['draft_native'] = ! empty( $draft_values[ $user_id ] );
			} else {
				$native_owner = Post_Authorship::resolve_post_author( 0, '', 'user_deletion_native_fallback', array( $user_id ) );
			}

			if ( ! $this->is_valid_user_deletion_owner( $native_owner, $user_ids ) ) {
				wp_die(
					esc_html__( 'No valid WordPress user is available to preserve content while deleting this account.', 'molongui-authorship' ),
					esc_html__( 'Unable to delete author', 'molongui-authorship' ),
					array( 'response' => 500 )
				);
			}

			$request['native_owner'] = $native_owner;
			$this->store_user_deletion_request( $user_id, $request );

			if ( $per_user_core ) {
				$_REQUEST['delete_option'][ $user_id ] = 'reassign';
				$_REQUEST['reassign_user'][ $user_id ] = $native_owner;

				if ( isset( $_POST['delete_option'] ) && is_array( $_POST['delete_option'] ) ) {
					$_POST['delete_option'][ $user_id ] = 'reassign';
					$_POST['reassign_user'][ $user_id ] = $native_owner;
				}
			} else {
				$_REQUEST['delete_option'] = 'reassign';
				$_REQUEST['reassign_user'] = $native_owner;

				if ( isset( $_POST['delete_option'] ) ) {
					$_POST['delete_option'] = 'reassign';
					$_POST['reassign_user'] = $native_owner;
				}
			}
		}
	}

	private function store_user_deletion_request( $user_id, $request ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! is_array( $request ) ) {
			return;
		}

		self::$user_deletion_requests[ $user_id ] = $request;

		$request_payload = $request;
		$request_payload['_token'] = wp_create_nonce(
			'molongui_user_deletion_request_' . $user_id . '_' . sanitize_key( $request['action'] )
		);

		if ( ! isset( $_REQUEST['molongui_user_deletion_request'] ) || ! is_array( $_REQUEST['molongui_user_deletion_request'] ) ) {
			$_REQUEST['molongui_user_deletion_request'] = array();
		}

		$_REQUEST['molongui_user_deletion_request'][ $user_id ] = $request_payload;

		if ( ! isset( $_POST['molongui_user_deletion_request'] ) || ! is_array( $_POST['molongui_user_deletion_request'] ) ) {
			$_POST['molongui_user_deletion_request'] = array();
		}

		$_POST['molongui_user_deletion_request'][ $user_id ] = $request_payload;
	}

	private function get_user_deletion_request( $user_id ) {
		$user_id = absint( $user_id );

		if ( isset( self::$user_deletion_requests[ $user_id ] ) ) {
			return self::$user_deletion_requests[ $user_id ];
		}

		$requests = isset( $_REQUEST['molongui_user_deletion_request'] ) && is_array( $_REQUEST['molongui_user_deletion_request'] )
			? wp_unslash( $_REQUEST['molongui_user_deletion_request'] )
			: array();

		if ( empty( $requests[ $user_id ] ) || ! is_array( $requests[ $user_id ] ) ) {
			return array();
		}

		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] )
			? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
			: '';
		$nonce  = isset( $_REQUEST['_wpnonce'] ) && is_string( $_REQUEST['_wpnonce'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) )
			: '';

		if ( 'dodelete' !== $action || ! current_user_can( 'delete_users' ) || ! wp_verify_nonce( $nonce, 'delete-users' ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_user_deletion_request',
				__( 'The Molongui Authorship user deletion request is no longer valid.', 'molongui-authorship' )
			);
		}

		$request        = $requests[ $user_id ];
		$request_action = isset( $request['action'] ) && is_string( $request['action'] ) ? sanitize_key( $request['action'] ) : '';
		$request_token  = isset( $request['_token'] ) && is_string( $request['_token'] ) ? sanitize_text_field( $request['_token'] ) : '';
		$valid_actions  = array(
			self::DELETE_ACTION_REASSIGN_GUEST,
			self::DELETE_ACTION_REMOVE_AUTHORSHIP,
			self::DELETE_ACTION_CONVERT_GUEST,
		);

		if ( ! in_array( $request_action, $valid_actions, true ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_user_deletion_action',
				__( 'The selected Molongui Authorship deletion action is invalid.', 'molongui-authorship' )
			);
		}

		if ( ! wp_verify_nonce( $request_token, 'molongui_user_deletion_request_' . $user_id . '_' . $request_action ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_user_deletion_token',
				__( 'The Molongui Authorship user deletion state could not be verified.', 'molongui-authorship' )
			);
		}

		$deleted_user_ids = ! empty( $_REQUEST['users'] )
			? array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_REQUEST['users'] ) ) ) ) )
			: array( $user_id );
		$native_owner     = isset( $request['native_owner'] ) ? absint( $request['native_owner'] ) : 0;

		if ( ! $this->is_valid_user_deletion_owner( $native_owner, $deleted_user_ids ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_native_owner',
				__( 'The WordPress user selected to preserve native content is invalid.', 'molongui-authorship' )
			);
		}

		$normalized = array(
			'action'       => $request_action,
			'guest_id'     => isset( $request['guest_id'] ) ? absint( $request['guest_id'] ) : 0,
			'native_owner' => $native_owner,
			'draft_native' => ! empty( $request['draft_native'] ),
		);

		if ( self::DELETE_ACTION_REASSIGN_GUEST === $request_action ) {
			$guest_ref = $normalized['guest_id'] ? Post_Authorship::build_reference( $normalized['guest_id'], 'guest' ) : '';

			if (
				! $normalized['guest_id']
				|| ! Post_Authorship::author_exists( $guest_ref )
				|| 'publish' !== get_post_status( $normalized['guest_id'] )
			) {
				return new \WP_Error(
					'molongui_authorship_invalid_guest_reassignment',
					__( 'The Guest Author selected for reassignment is invalid.', 'molongui-authorship' )
				);
			}
		}

		if (
			self::DELETE_ACTION_CONVERT_GUEST === $request_action
			&& (
				! Settings::is_guest_author_enabled()
				|| ! apply_filters( 'molongui_authorship/user_deletion/convert_to_guest_available', false )
			)
		) {
			return new \WP_Error(
				'molongui_authorship_guest_conversion_unavailable',
				__( 'Converting this WordPress user to a Guest Author is not available.', 'molongui-authorship' )
			);
		}

		self::$user_deletion_requests[ $user_id ] = $normalized;

		return $normalized;
	}

	private function is_valid_user_deletion_owner( $user_id, $deleted_user_ids ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || in_array( $user_id, array_map( 'absint', (array) $deleted_user_ids ), true ) ) {
			return false;
		}

		return (bool) \WP_User::get_data_by( 'id', $user_id );
	}

	public function handle_user_deletion( $user_id, $reassign = null, $user = null ) {
		$user_id  = absint( $user_id );
		$reassign = null !== $reassign ? absint( $reassign ) : null;

		if ( ! $user_id ) {
			return;
		}

		$request = $this->get_user_deletion_request( $user_id );

		if ( is_wp_error( $request ) ) {
			wp_die(
				esc_html( $request->get_error_message() ),
				esc_html__( 'Unable to delete author', 'molongui-authorship' ),
				array( 'response' => 400 )
			);
		}

		$action             = 'delete';
		$replacement_author = '';
		$fallback_user_id   = 0;

		if ( ! empty( $request ) ) {
			switch ( $request['action'] ) {
				case self::DELETE_ACTION_REASSIGN_GUEST:
					$action             = 'reassign';
					$replacement_author = Post_Authorship::build_reference( absint( $request['guest_id'] ), 'guest' );
					break;

				case self::DELETE_ACTION_REMOVE_AUTHORSHIP:
					$action = 'remove';
					break;

				case self::DELETE_ACTION_CONVERT_GUEST:
					$guest_result = apply_filters(
						'molongui_authorship/user_deletion/create_guest_from_user',
						0,
						$user_id,
						$user
					);

					if ( is_wp_error( $guest_result ) ) {
						wp_die(
							esc_html( $guest_result->get_error_message() ),
							esc_html__( 'Unable to delete author', 'molongui-authorship' ),
							array( 'response' => 500 )
						);
					}

					$guest_id  = absint( $guest_result );
					$guest_ref = $guest_id ? Post_Authorship::build_reference( $guest_id, 'guest' ) : '';

					if ( ! $guest_id || ! Post_Authorship::author_exists( $guest_ref ) ) {
						wp_die(
							esc_html__( 'The user could not be converted to a Guest Author.', 'molongui-authorship' ),
							esc_html__( 'Unable to delete author', 'molongui-authorship' ),
							array( 'response' => 500 )
						);
					}

					$action             = 'reassign';
					$replacement_author = $guest_ref;
					break;
			}

			$fallback_user_id = absint( $request['native_owner'] );

			if ( ! empty( $request['draft_native'] ) ) {
				foreach ( Post_Authorship::get_unmanaged_native_post_ids( $user_id, true ) as $post_id ) {
					$status = get_post_status( $post_id );

					if ( 'draft' === $status || 'trash' === $status ) {
						continue;
					}

					$drafted = wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						),
						true
					);

					if ( is_wp_error( $drafted ) ) {
						wp_die(
							esc_html( $drafted->get_error_message() ),
							esc_html__( 'Unable to delete author', 'molongui-authorship' ),
							array( 'response' => 500 )
						);
					}
				}
			}
		} elseif ( $reassign ) {
			$action             = 'reassign';
			$replacement_author = Post_Authorship::build_reference( $reassign, 'user' );
			$fallback_user_id   = $reassign;
		}

		$reassignment_fallback = null;

		if ( $fallback_user_id ) {
			$reassignment_fallback = function ( $fallback_user, $post_id, $main_author, $context, $exclude_user_ids ) use ( $fallback_user_id ) {
				if ( 0 === $fallback_user && ! in_array( $fallback_user_id, (array) $exclude_user_ids, true ) ) {
					return $fallback_user_id;
				}

				return $fallback_user;
			};

			add_filter( 'molongui_authorship/post_author_fallback_user_id', $reassignment_fallback, 5, 5 );
		}

		try {
			$result = Post_Authorship::apply_user_deletion_action(
				$user_id,
				$action,
				$replacement_author,
				'wordpress_user_deletion'
			);
		} finally {
			if ( $reassignment_fallback ) {
				remove_filter( 'molongui_authorship/post_author_fallback_user_id', $reassignment_fallback, 5 );
			}
		}

		if ( is_wp_error( $result ) ) {
			if ( ! empty( $request ) && self::DELETE_ACTION_CONVERT_GUEST === $request['action'] && ! empty( $guest_id ) ) {
				wp_delete_post( $guest_id, true );
			}

			wp_die(
				esc_html( $result->get_error_message() ),
				esc_html__( 'Unable to delete author', 'molongui-authorship' ),
				array( 'response' => 500 )
			);
		}

		$replacement_data = '' !== $replacement_author ? Post_Authorship::parse_reference( $replacement_author ) : false;

		if ( $replacement_data && ! empty( $result['post_types'] ) ) {
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


	public static function post_as_others_admin_notice() {
		if ( array_key_exists( 'posting-as-others', $_GET ) ) :
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php printf( __( 'You are not allowed to post on behalf of others. Ask your administrator to enable that option for you on %1$sAuthors > Settings > Users > Permissions%2$s if you wish to remove your name as the post author.', 'molongui-authorship' ), '<code><strong>', '</strong></code>' ); ?></p>
			</div>
		<?php
		endif;
	}

	public function edit_others_posts( $allcaps, $caps, $args, $user ) {
		if ( ! is_user_logged_in() ) {
			return $allcaps;
		}

		$cap     = $args[0];                          
		$user_id = $args[1];                          
		$post_id = isset( $args[2] ) ? $args[2] : 0;  

		$postType = empty( $post_id ) ? Post::get_post_type() : Post::get_post_type( $post_id );
		$obj      = get_post_type_object( $postType );

		if ( ! $obj or 'revision' === $obj->name ) {
			return $allcaps;
		}

		if ( ! empty( $user->allcaps[ $obj->cap->edit_others_posts ] ) ) {
			return $allcaps;
		}

		global $in_comment_loop;
		if ( $in_comment_loop ) {
			return $allcaps;
		}

		$caps_to_modify = array(
			$obj->cap->edit_post,
			'edit_post',
			$obj->cap->edit_others_posts,
			'authorship_granted_edit_others_posts',
		);

		if ( ! in_array( $cap, $caps_to_modify ) ) {
			return $allcaps;
		}

		$author = new Author( $user_id );
		if ( $author->is_coauthor_for( $post_id ) ) {
			$post_status = get_post_status( $post_id );

			if ( 'publish' === $post_status and isset( $obj->cap->edit_published_posts ) and ! empty( $user->allcaps[ $obj->cap->edit_published_posts ] ) ) {
				$allcaps[ $obj->cap->edit_published_posts ] = true;
			} elseif ( 'private' === $post_status and isset( $obj->cap->edit_private_posts ) and ! empty( $user->allcaps[ $obj->cap->edit_private_posts ] ) ) {
				$allcaps[ $obj->cap->edit_private_posts ] = true;
			}

			$allcaps[ $obj->cap->edit_others_posts ] = true;

			$allcaps['authorship_granted_edit_others_posts'] = true;
		}

		return $allcaps;
	}

	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( in_array( $cap, array( 'edit_post', 'edit_others_posts' ) ) and in_array( 'edit_others_posts', $caps, true ) ) {
			if ( isset( $args[0] ) ) {
				$post_id = (int) $args[0];

				$post_authors = Post::get_authors( $post_id, 'id' );
				$allowEdit    = is_array( $post_authors ) ? in_array( $user_id, $post_authors ) : false;

				if ( $allowEdit ) {
					foreach ( $caps as &$item ) {
						if ( $item === 'edit_others_posts' ) {
							$item = 'edit_posts';
						}
					}
				}

				$caps = apply_filters( 'authorship/post/filter_map_meta_cap', $caps, $cap, $user_id, $post_id );
			}
		}

		return $caps;
	}

	public static function update_user_count() {
		$user_roles = Settings::enabled_user_roles();

		/*!
		 * FILTER HOOK
		 * Allows the use of WP_User_Query instead of a custom SQL query to count the number of users.
		 *
		 * When dealing with a large number of users, using the WP_User_Query can become slow. A more efficient way
		 * to get the user count based on roles is to run a custom SQL query directly on the database. This approach
		 * bypasses the overhead of WP_User_Query and can be significantly faster.
		 *
		 * Some empirical numbers:
		 *
		 *   Number of Users    Custom SQL    WP_User_Query
		 *   -----------------------------------------------
		 *   5,000              ~0.30 s       ~0.30 s
		 *   50,000             ~0.30 s       ~0.50 s
		 *   100,000            ~0.30 s       ~0.77 s
		 */
		if ( apply_filters( 'molongui_authorship/user_count_custom_sql_query', true ) ) {
			global $wpdb;

			$roles_placeholders = implode( ' OR ', array_fill( 0, count( $user_roles ), 'meta_value LIKE %s' ) );

			$role_like_clauses = array();
			foreach ( $user_roles as $role ) {
				$role_like_clauses[] = '%' . $role . '%';
			}

			$sql = $wpdb->prepare(
				"
                SELECT COUNT( DISTINCT user_id )
                FROM $wpdb->usermeta
                WHERE meta_key = '{$wpdb->prefix}capabilities'
                AND ( $roles_placeholders )
                ",
				$role_like_clauses
			);

			$user_count = $wpdb->get_var( $sql );
		} else {
			$user_query = new \WP_User_Query(
				array(
					'role__in' => $user_roles,
					'fields'   => 'ID',  
				)
			);
			$user_count = $user_query->get_total();
		}

		update_option( 'molongui_authorship_user_count', $user_count, false );
	}


	public function register_user_scripts() {

		$core_file = self::EDIT_USER_SCRIPT;

		wp_register_script(
			'molongui-authorship-edit-user',
			plugins_url( $core_file ),
			array( 'jquery' ),
			MOLONGUI_AUTHORSHIP_VERSION,
			false
		);

		$legacy_file = apply_filters_deprecated(
			'authorship/edit_user/script',
			array( $core_file ),
			'5.3.0'
		);

		if (
			! is_string( $legacy_file )
			||
			'' === $legacy_file
			||
			$core_file === $legacy_file
			||
			! Assets::is_readable( $legacy_file )
		) {
			return;
		}

		wp_register_script(
			'molongui-authorship-edit-user-legacy',
			plugins_url( $legacy_file ),
			array(
				'jquery',
				'molongui-authorship-edit-user',
			),
			MOLONGUI_AUTHORSHIP_VERSION,
			false
		);
	}

	public function enqueue_user_scripts() {

		$current_screen = get_current_screen();

		if (
			empty( $current_screen->id )
			||
			! in_array(
				$current_screen->id,
				array( 'profile', 'users', 'user', 'user-edit' ),
				true
			)
		) {
			return;
		}

		wp_enqueue_script( 'molongui-authorship-edit-user' );

		if (
			wp_script_is(
				'molongui-authorship-edit-user-legacy',
				'registered'
			)
		) {
			wp_enqueue_script(
				'molongui-authorship-edit-user-legacy'
			);
		}

		static $localized = false;

		if ( $localized ) {
			return;
		}

		wp_localize_script(
			'molongui-authorship-edit-user',
			'molongui_authorship_edit_user_params',
			self::edit_user_script_params()
		);

		$localized = true;
	}


	public function enqueue_delete_user_form_styles() {
		$screen = get_current_screen();

		if ( empty( $screen->id ) || 'users' !== $screen->id ) {
			return;
		}

		$css = '
			#updateusers fieldset {
				max-width: 880px;
				margin-top: 18px;
			}

			#updateusers .wrap > ul > li + li {
				margin-top: 32px;
				padding-top: 12px;
				border-top: 1px solid #dcdcde;
			}

			#updateusers .wrap > ul > li + li > fieldset {
				margin-top: 0;
			}

			#updateusers fieldset legend {
				margin-bottom: 2em;
			}

			#updateusers fieldset ul {
				max-width: 880px;
				margin: 14px 0 0;
				margin-inline-start: 16px;
			}

			#updateusers fieldset ul > li {
				margin: 0 0 24px;
				padding: 0;
				line-height: 1.5;
			}

			#updateusers fieldset ul > li:last-child {
				margin-bottom: 0;
			}

			#updateusers fieldset ul > li > input[type="radio"] {
				margin-top: 2px;
				margin-inline-end: 6px;
				vertical-align: top;
			}

			#updateusers fieldset ul > li > label {
				line-height: 1.5;
			}

			#updateusers fieldset ul > li > p.description,
			#updateusers fieldset ul > li > [data-molongui-delete-note] {
				display: block;
				max-width: 760px;
				margin: 6px 0 0;
				margin-inline-start: 26px;
				line-height: 1.5;
			}

			#updateusers fieldset ul > li > select {
				display: block;
				width: 320px;
				max-width: calc( 100% - 26px );
				margin: 8px 0 0;
				margin-inline-start: 26px;
			}

			#updateusers li[data-molongui-delete-option] > label > .description {
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

			#updateusers li[data-molongui-delete-option] > input[type="radio"]:disabled + label {
				color: #8c8f94;
			}

			#updateusers .molongui-native-owner-fields {
				box-sizing: border-box;
				max-width: 760px;
				margin: 10px 0 0;
				margin-inline-start: 26px;
				padding: 12px 14px;
				border: 1px solid #dcdcde;
				border-inline-start: 4px solid #72aee6;
				background: #f6f7f7;
			}

			#updateusers .molongui-native-owner-fields > .description:first-child {
				margin-top: 0;
			}

			#updateusers .molongui-native-owner-fields select {
				max-width: 100%;
			}

			#updateusers .submit {
				margin-top: 24px;
			}

			@media screen and ( max-width: 782px ) {
				#updateusers fieldset ul > li > select {
					width: calc( 100% - 26px );
				}
			}
		';

		wp_add_inline_style( 'common', $css );
	}

	private static function profile_tabs_enabled() {
		if ( ! Settings::is_enabled( 'user-profile' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if (
			empty( $screen->id ) ||
			! in_array( $screen->id, array( 'profile', 'user-edit' ), true )
		) {
			return false;
		}

		if ( 'profile' === $screen->id ) {
			$user_id = get_current_user_id();
		} else {
			$user_id = isset( $_GET['user_id'] )
				? absint( $_GET['user_id'] )
				: 0;
		}

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		return ! empty(
		array_intersect(
			$user->roles,
			Settings::enabled_user_roles()
		)
		);
	}

	public static function edit_user_script_params() {

		$profile_tabs_enabled = self::profile_tabs_enabled();
		$initial_view         = '';

		if ( $profile_tabs_enabled ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI state.
			$requested_view = isset( $_GET[ self::AUTHOR_PROFILE_VIEW_QUERY_ARG ] )
				? sanitize_key( wp_unslash( $_GET[ self::AUTHOR_PROFILE_VIEW_QUERY_ARG ] ) )
				: '';

			if ( self::AUTHOR_PROFILE_VIEW_QUERY_VALUE === $requested_view ) {
				$initial_view = 'author';
			}
		}

		$show_author_profile_new_badge = (
			$profile_tabs_enabled &&
			! get_user_meta(
				get_current_user_id(),
				self::AUTHOR_PROFILE_TAB_SEEN_META,
				true
			)
		);

		$params = array(
			'profile_tabs_enabled'           => $profile_tabs_enabled,
			'initial_view'                   => $initial_view,
			'author_profile_view_query_arg'  => self::AUTHOR_PROFILE_VIEW_QUERY_ARG,
			'profile_tab_label'              => esc_html__( 'User Account', 'molongui-authorship' ),
			'author_profile_tab_label'       => esc_html__( 'Author Profile', 'molongui-authorship' ),
			'profile_tabs_label'              => esc_html__( 'User profile sections', 'molongui-authorship' ),
			'update_button_label'             => esc_html__( 'Update', 'molongui-authorship' ),
			'show_author_profile_new_badge'  => $show_author_profile_new_badge,
			'new_feature_label'              => esc_html__( 'New', 'molongui-authorship' ),
			'new_feature_nonce'              => wp_create_nonce( 'authorship_author_profile_tab_seen' ),
			'ajax_url'                       => admin_url( 'admin-ajax.php' ),
		);

		return apply_filters( 'authorship/edit_user/script_params', $params );
	}

	public function enqueue_user_assets() {

		$screen = get_current_screen();

		if ( empty( $screen->id ) || ! in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
			return;
		}

		if ( ! Settings::is_enabled( 'local-avatar' ) || ! current_user_can( 'upload_files' ) ) {
			return;
		}

		Media_Picker::enqueue();
	}


	public function can_post_as_others( $user = 0 ) {
		$post_as_others = false;

		$user = self::get( $user );

		if ( $user instanceof \WP_User ) {
			remove_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX );
			if ( user_can( $user, 'edit_others_posts' ) ) {
				$post_as_others = true;
			}
			add_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX, 4 );
		}

		/*!
		 * FILTER HOOK
		 * Allows filtering whether the user can post as another author.
		 *
		 * @since 5.0.0
		 */
		return apply_filters( 'molongui_authorship/can_post_as_others', $post_as_others );
	}

	public static function get_user_count() {
		return get_option( 'molongui_authorship_user_count', 0 );
	}


	public static function clear_object_cache() {
		WP::deprecated_function_once( __FUNCTION__, '5.2.0' );

		Cache::clear( 'posts' );
		Cache::clear( 'users' );
	}

	public function register_avatar_scripts() {
		$file = apply_filters( 'authorship/edit_avatar/script', self::EDIT_AVATAR_SCRIPT );

		Assets::register_script( $file, 'edit_avatar' );
	}

	public static function enqueue_avatar_scripts() {
		$file = apply_filters( 'authorship/edit_avatar/script', self::EDIT_AVATAR_SCRIPT );

		Assets::enqueue_script( $file, 'edit_avatar', true );
	}

	public static function edit_avatar_script_params() {
		$params = array(
			'remove' => __( 'Remove', 'molongui-authorship' ),
			'edit'   => __( 'Edit', 'molongui-authorship' ),
			'upload' => __( 'Upload Avatar', 'molongui-authorship' ),
		);

		return apply_filters( 'authorship/edit_avatar/script_params', $params );
	}
}  

Admin_User::instance();

