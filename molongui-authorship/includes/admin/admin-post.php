<?php
/*!
 * Admin utilities for managing post authorship within the Molongui Authorship plugin.
 *
 * This file contains the functionality needed to manage post-related operations within the WordPress admin interface,
 * particularly focusing on authorship features. It integrates various utilities like caching, asset handling, and
 * debugging tools to ensure the smooth management of authorship data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Admin_User;
use Molongui\Authorship\Author;
use Molongui\Authorship\Avatar;
use Molongui\Authorship\Authors;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Request;
use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Guest_Author;
use Molongui\Authorship\Post;
use Molongui\Authorship\Post_Authorship;
use Molongui\Authorship\Settings;
use Molongui\Authorship\User;

defined( 'ABSPATH' ) || exit;  

class Admin_Post extends \Molongui\Authorship\Common\Utils\Post
{
	private $javascript           = '/assets/js/edit-post.38ea.min.js';
	private $javascript_gutenberg = MOLONGUI_AUTHORSHIP_URL . 'assets/js/edit-post-gutenberg.4727.min.js';
	private $javascript_classic   = MOLONGUI_AUTHORSHIP_URL . 'assets/js/edit-post-classic.min.js';
	private $stylesheet           = '';
	private $stylesheet_ltr       = '';
	private $stylesheet_rtl       = '';

	private $list_authorship_cache = array();

	private static $post_status_transitions = array();

	private static $post_status_before_update = array();

	public function __construct()
	{
		if ( Post::byline_takeover() )
		{
			$this->set_assets();

			add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts'  ) );
			add_filter( 'authorship/edit_post_script_params', array( $this, 'post_script_params' ) );

			add_action( 'admin_print_footer_scripts-edit.php', array( $this, 'fix_mine_count' ) );
			add_action( 'admin_head-edit.php', array( $this, 'add_list_table_styles' ) );
			add_filter( 'query_vars', array( $this, 'add_no_main_author_query_var' ) );
			add_action( 'pre_get_posts', array( $this, 'filter_no_main_author_posts' ), PHP_INT_MAX );

			foreach ( Settings::enabled_post_types() as $post_type )
			{
				add_filter( 'views_edit-' . $post_type, array( $this, 'add_no_main_author_view' ) );
			}

			if ( Settings::is_enabled( 'guest-author' ) )
			{
				add_action( 'query_vars', array( $this, 'add_guest_query_var' ) );
				add_action( 'pre_get_posts', array( $this, 'filter_guest_posts' ), PHP_INT_MAX );
			}

			add_action( 'add_meta_boxes', array( $this, 'add_author_metabox' ), -1 );
			add_action( 'wp_ajax_molongui_authorship_quick_add_author', array( $this, 'quick_add_author' ) );

			add_action( 'admin_menu', array( $this, 'remove_classic_editor_author_metabox' ) );

			add_action( 'admin_head', array( $this, 'hide_block_editor_author_panel' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_scripts' ) );

			add_action( 'admin_head', array( $this, 'quick_edit_remove_default_author_selector' ) );
			add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_add_fields' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'quick_edit_init_fields' ) );

			add_filter( 'wp_insert_post_data', array( $this, 'update_post_author' ), 10, 3 );
			add_action( 'pre_post_update', array( $this, 'post_status_before_update' ), 10, 2 );
			add_action( 'check_admin_referer', array( $this, 'preflight_classic_editor_publication' ), 10, 2 );
			add_action( 'molongui_authorship/post_publication_blocked', array( $this, 'flag_main_author_required_error' ), 10, 4 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_classic_publication_guard' ) );
			add_action( 'admin_notices', array( $this, 'main_author_required_admin_notice' ) );

			add_action( 'trashed_post', array( $this, 'on_trash' ) );
			add_action( 'untrashed_post', array( $this, 'on_untrash' ) );
			add_action( 'before_delete_post', array( $this, 'on_before_delete' ) );
			add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		}

		add_filter( 'manage_posts_columns', array( $this, 'edit_list_columns' ) );
		add_filter( 'manage_pages_columns', array( $this, 'edit_list_columns' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'fill_list_columns' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'fill_list_columns' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'add_user_filter' ) );
		add_action( 'restrict_manage_posts', array( $this, 'add_guest_filter' ) );
		add_filter( 'molongui_authorship/add_user_filter_to_post_list_screen', array( $this, 'hide_user_filter' ) );
		add_filter( 'molongui_authorship/add_guest_filter_to_post_list_screen', array( $this, 'hide_guest_filter' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_box_metabox' ), 1 );
		add_action( 'admin_print_footer_scripts', array( $this, 'set_default_author' ) );

		add_action( 'save_post', array( $this, 'on_save' ), 10, 2 );
		add_action( 'attachment_updated', array( $this, 'on_save' ), 10, 2 );

		add_action( 'wp_ajax_authors_ajax_suggest', array( $this, 'suggest_authors' ) );
	}

	private static function get_author_avatar_data( Author $author, $size = array( 20, 20 ) )
	{
		$resolution = ( new Avatar( $author ) )->get_resolution( $size, 'url' );

		return array
		(
			'url'      => ! empty( $resolution['value'] ) ? $resolution['value'] : '',
			'fallback' => ! empty( $resolution['browser_fallback'] ) ? $resolution['browser_fallback'] : '',
		);
	}

	public function set_assets()
	{
		$this->stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? $this->stylesheet_rtl : $this->stylesheet_ltr );

		$this->stylesheet = apply_filters( 'authorship/edit_post/styles', $this->stylesheet );

		$this->javascript = MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript;

		$this->javascript = apply_filters( 'authorship/edit_post/script', $this->javascript );
	}


	public function register_admin_scripts()
	{
		Assets::register_typeahead();

		$deps = array( 'jquery', 'molongui-typeahead', 'jquery-ui-sortable' );

		if ( Helpers::is_block_editor() )
		{
			$deps = array_merge( $deps, array( 'wp-blocks', 'wp-i18n', 'wp-edit-post' ) );
		}

		Assets::register_script( $this->javascript, 'edit_post', $deps );
	}

	public function enqueue_admin_scripts()
	{
		$screen = get_current_screen();

		if ( !in_array( $screen->id, Settings::enabled_screens() )
			or
			( !current_user_can( 'edit_others_posts' ) and !current_user_can( 'edit_others_pages' ) )
		)
		{
			return;
		}

		Assets::enqueue_typeahead();
		Assets::enqueue_script( $this->javascript, 'edit_post', true );
	}

	public static function post_script_params()
	{
		$ajax_suggest_link = add_query_arg( array
		(
			'action'    => 'authors_ajax_suggest',
			'post_type' => rawurlencode( get_post_type() ),
		), wp_nonce_url( 'admin-ajax.php', 'molongui-author-search', 'molongui-author-search-nonce' ) );

		$params = array
		(
			'guest_enabled'               => Settings::is_guest_author_enabled(),
			'coauthors_enabled'           => Settings::is_co_authors_enabled(),
			'remove_author_tip'           => esc_html__( "Remove author from selection", 'molongui-authorship' ),

			'tag_title'                   => esc_html__( "Drag this author to reorder", 'molongui-authorship' ),
			'delete_label'                => esc_html__( "Remove", 'molongui-authorship' ),
			'up_label'                    => esc_html__( "Move up", 'molongui-authorship' ),
			'down_label'                  => esc_html__( "Move down", 'molongui-authorship' ),
			'main_author_label'           => esc_html__( "MAIN", 'molongui-authorship' ),
			'main_author_tip'             => esc_html__( "Main post author", 'molongui-authorship' ),
			'invalid_main_drop'            => esc_html__( "To make this author the main author, use the star icon instead of dragging them above the current main author.", 'molongui-authorship' ),
			'make_main_tip'               => esc_html__( "Set this author as the main post author", 'molongui-authorship' ),
			'confirm_delete'              => esc_html__( "Are you sure you want to remove this author?", 'molongui-authorship' ),
			'one_author_required'         => esc_html__( "Removing this author can leave the post without a main author. The post cannot be published until a new main author is selected. Are you sure you want to proceed?", 'molongui-authorship' ),
			'ajax_suggest_link'           => $ajax_suggest_link,
			'author_search_min_length'    => apply_filters( 'molongui_authorship/author_search_min_length', 2 ),
			'author_search_results_limit' => apply_filters( 'molongui_authorship/author_search_results_limit', 400 ),
			'no_suggestions_found'        => esc_html( apply_filters( 'molongui_authorship/no_matching_authors_message', __( "No matching authors found.", 'molongui-authorship' ) ) ),

			'new_author_required'         => esc_html__( "Please fill in all required fields to proceed.", 'molongui-authorship' ),
			'new_author_wrong_email'      => esc_html__( "Invalid email. Please enter a valid email address.", 'molongui-authorship' ),
			'new_author_confirm'          => esc_html__( "Are you sure you want to add this new author? To add an existing author, use the search box instead.", 'molongui-authorship' ),
			'new_author_added'            => esc_html__( "New author created and added to this post. You can complete their profile in the Authors > View Authors screen.", 'molongui-authorship' ),

			'new_author_ajax_error'       => esc_html__( "ERROR: Connection to the backend failed. The author has not be added.", 'molongui-authorship' ),

			'debug_mode'                  => Debug::is_enabled(),
		);

		return apply_filters( 'authorship/edit_post/script_params', $params );
	}


	public function fix_mine_count()
	{
		$current_screen = get_current_screen();

		if ( !in_array( $current_screen->id, Settings::enabled_screens() ) )
		{
			return;
		}

		$mine_count = get_user_meta( get_current_user_id(), 'molongui_author_'.$current_screen->post_type.'_count', true );

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) { $('.subsubsub .mine .count').html("(<?php echo $mine_count; ?>)"); });
		</script>
		<?php
	}

	public function add_no_main_author_view( $views )
	{
		$current_screen = get_current_screen();

		if ( ! $current_screen || empty( $current_screen->post_type ) )
		{
			return $views;
		}

		if ( ! in_array( $current_screen->post_type, Settings::enabled_post_types(), true ) )
		{
			return $views;
		}

		$is_active = '1' === (string) get_query_var( 'molongui_no_main_author', '' );

		if ( $is_active )
		{
			foreach ( $views as $key => $view )
			{
				$views[ $key ] = str_replace(
					array( ' class="current"', ' aria-current="page"' ),
					'',
					$view
				);
			}
		}

		$args = array(
			'molongui_no_main_author' => '1',
		);

		if ( 'post' !== $current_screen->post_type )
		{
			$args['post_type'] = $current_screen->post_type;
		}

		$url  = add_query_arg( $args, admin_url( 'edit.php' ) );
		$link = sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$is_active ? ' class="current" aria-current="page"' : '',
			esc_html_x( 'No Main Author', 'Post list view', 'molongui-authorship' )
		);

		$new_views = array();
		$inserted  = false;

		foreach ( $views as $key => $view )
		{
			if ( 'trash' === $key )
			{
				$new_views['molongui_no_main_author'] = $link;
				$inserted                              = true;
			}

			$new_views[ $key ] = $view;
		}

		if ( ! $inserted )
		{
			$new_views['molongui_no_main_author'] = $link;
		}

		return $new_views;
	}

	public function add_no_main_author_query_var( $query_vars )
	{
		if ( ! in_array( 'molongui_no_main_author', $query_vars, true ) )
		{
			$query_vars[] = 'molongui_no_main_author';
		}

		return $query_vars;
	}

	public function filter_no_main_author_posts( $wp_query )
	{
		if ( ! is_admin() || ! $wp_query->is_main_query() )
		{
			return;
		}

		global $pagenow;

		if ( 'edit.php' !== $pagenow || '1' !== (string) $wp_query->get( 'molongui_no_main_author' ) )
		{
			return;
		}

		$post_type = $wp_query->get( 'post_type' );
		$post_type = empty( $post_type ) ? 'post' : $post_type;

		if ( ! is_string( $post_type ) || ! in_array( $post_type, Settings::enabled_post_types(), true ) )
		{
			return;
		}

		$meta_query = $wp_query->get( 'meta_query' );

		if ( ! is_array( $meta_query ) )
		{
			$meta_query = array();
		}

		$meta_query[] = array(
			'key'     => Post_Authorship::MAIN_AUTHOR_META_KEY,
			'value'   => '',
			'compare' => '=',
		);

		$wp_query->set( 'meta_query', $meta_query );
	}

	public function add_guest_query_var( $query_vars )
	{
		$query_vars[] = 'guest';


		return $query_vars;
	}

	public function filter_guest_posts( $wp_query )
	{
		if ( !Request::is_from( 'admin' ) )
		{
			return false;
		}

		$qv = $wp_query->query_vars;

		if ( empty( $qv['guest'] ) )
		{
			return false;
		}

		$meta_query = $wp_query->get( 'meta_query' );

		if ( !is_array( $meta_query ) and empty( $meta_query ) )
		{
			$meta_query = array();
		}

		$meta_query[] = array
		(
			array
			(
				'key'     => '_molongui_author',
				'value'   => 'guest-'.$qv['guest'],
				'compare' => '==',
			),
		);
		$wp_query->set( 'meta_query', $meta_query );
	}

	public function add_list_table_styles()
	{
		$current_screen = get_current_screen();

		if ( !$current_screen || empty( $current_screen->post_type ) || !in_array( $current_screen->post_type, Settings::enabled_post_types(), true ) )
		{
			return;
		}
		?>
		<style type="text/css">
			.molongui-author-type-badge {
				display: inline-block;
				margin-left: 4px;
				padding: 0 4px;
				border: 1px solid #e2e4e7;
				border-radius: 3px;
				background: #f6f7f7;
				color: #8c8f94;
				font-size: 10px;
				font-weight: 400;
				line-height: 1.5;
				vertical-align: 1px;
			}
			.rtl .molongui-author-type-badge {
				margin-right: 4px;
				margin-left: 0;
			}
		</style>
		<?php
	}

	private function get_list_authorship_data( $post_id )
	{
		$post_id = absint( $post_id );

		if ( isset( $this->list_authorship_cache[$post_id] ) )
		{
			return $this->list_authorship_cache[$post_id];
		}

		$data = array
		(
			'main'      => Post::get_main_author( $post_id ),
			'coauthors' => array(),
		);

		if ( Settings::is_enabled( 'co-authors' ) )
		{
			$post_authors = Post::get_authors( $post_id );
			$main_ref     = $data['main'] && !empty( $data['main']->ref ) ? $data['main']->ref : '';

			if ( $post_authors )
			{
				foreach ( $post_authors as $post_author )
				{
					if ( !is_object( $post_author ) || empty( $post_author->ref ) || $main_ref === $post_author->ref )
					{
						continue;
					}

					$data['coauthors'][] = $post_author;
				}
			}
		}

		$this->list_authorship_cache[$post_id] = $data;

		return $data;
	}

	private function render_list_author( $post_author, $post_id, $is_main = false )
	{
		if ( !is_object( $post_author ) || empty( $post_author->id ) || empty( $post_author->type ) || empty( $post_author->ref ) )
		{
			return;
		}

		$post_type         = get_post_type( $post_id );
		$author            = new Author( $post_author->id, $post_author->type );
		$display_name      = $author->get_base_display_name();
		$avatar_data       = self::get_author_avatar_data( $author, array( 20, 20 ) );
		$author_name_action = Settings::get( 'dashboard_author_name_action' );

		if ( 'guest' === $post_author->type )
		{
			$name_link = 'edit' == $author_name_action
				? admin_url( "post.php?post=$post_author->id&action=edit" )
				: admin_url( "edit.php?post_type=$post_type&guest=$post_author->id" );
		}
		else
		{
			$name_link = 'edit' == $author_name_action
				? admin_url( "user-edit.php?user_id=$post_author->id" )
				: admin_url( "edit.php?post_type=$post_type&author=$post_author->id" );
		}

		?>
		<p data-author-id="<?php echo esc_attr( $post_author->id ); ?>" data-author-type="<?php echo esc_attr( $post_author->type ); ?>" data-author-ref="<?php echo esc_attr( $post_author->ref ); ?>" data-author-main="<?php echo esc_attr( $is_main ? '1' : '0' ); ?>" data-author-display-name="<?php echo esc_attr( $display_name ); ?>" data-author-avatar="<?php echo esc_attr( esc_url( $avatar_data['url'] ) ); ?>" data-author-avatar-fallback="<?php echo esc_attr( esc_url( $avatar_data['fallback'] ) ); ?>" style="margin:0 0 4px;">
			<a href="<?php echo esc_url( $name_link ); ?>">
				<?php echo esc_html( $display_name ); ?>
			</a>
			<?php if ( 'guest' === $post_author->type && Settings::is_enabled( 'guest-author' ) ) : ?>
				<span class="molongui-author-type-badge"><?php echo esc_html_x( 'Guest', 'Post list author type badge', 'molongui-authorship' ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	public function edit_list_columns( $columns )
	{
		$new_columns              = array();
		$authorship_columns_added = false;
		$coauthors_enabled       = Settings::is_enabled( 'co-authors' );
		$author_box_enabled       = false;


		global $post, $post_type;

		$pt = ( isset( $post->post_type ) ? $post->post_type : '' );
		if ( empty( $post->post_type ) and $post_type == 'page' )
		{
			$pt = 'page';
		}

		if ( empty( $pt ) or $pt == 'guest_author' or !in_array( $pt, Settings::enabled_post_types() ) )
		{
			return $columns;
		}

		if ( Settings::is_enabled( 'author-box' ) )
		{
			$author_box_enabled = in_array( $pt, Settings::get_post_types_with_author_box(), true );
		}

		if ( array_key_exists( 'author', $columns ) ) $position = array_search( 'author', array_keys( $columns ) );       
		elseif ( array_key_exists( 'title', $columns ) ) $position = array_search( 'title', array_keys( $columns ) )+1;   
		else $position = count( $columns );                                                                                           

		unset( $columns['author'] );

		$i = 0;
		foreach ( $columns as $key => $column )
		{
			if ( $i == $position )
			{
				$new_columns['molongui-author'] = __( "Author", 'molongui-authorship' );

				if ( $coauthors_enabled )
				{
					$new_columns['molongui-coauthors'] = __( "Co-authors", 'molongui-authorship' );
				}

				if ( $author_box_enabled )
				{
					$new_columns['molongui-box'] = __( "Author Box", 'molongui-authorship' );
				}

				$authorship_columns_added = true;
			}

			++$i;
			$new_columns[$key] = $column;
		}

		if ( !$authorship_columns_added )
		{
			$new_columns['molongui-author'] = __( "Author", 'molongui-authorship' );

			if ( $coauthors_enabled )
			{
				$new_columns['molongui-coauthors'] = __( "Co-authors", 'molongui-authorship' );
			}

			if ( $author_box_enabled )
			{
				$new_columns['molongui-box'] = __( "Author Box", 'molongui-authorship' );
			}
		}

		return $new_columns;
	}

	public function fill_list_columns( $column, $ID )
	{
		if ( 'molongui-author' === $column )
		{
			$authorship = $this->get_list_authorship_data( $ID );

			if ( empty( $authorship['main'] ) )
			{
				echo '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'No main author', 'molongui-authorship' ) . '</span>';
				return;
			}

			$this->render_list_author( $authorship['main'], $ID, true );
			return;
		}

		elseif ( 'molongui-coauthors' === $column )
		{
			$authorship = $this->get_list_authorship_data( $ID );

			if ( empty( $authorship['coauthors'] ) )
			{
				echo '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'No co-authors', 'molongui-authorship' ) . '</span>';
				return;
			}

			foreach ( $authorship['coauthors'] as $coauthor )
			{
				$this->render_list_author( $coauthor, $ID, false );
			}

			return;
		}

		elseif ( 'molongui-box' === $column )
		{
			$current_post_type = get_post_type( $ID );

			if ( !Settings::is_enabled( 'author-box' )
				or
				empty( $current_post_type )
				or
				!in_array( $current_post_type, Settings::get_post_types_with_author_box(), true )
			)
			{
				return;
			}

			$box_display = get_post_meta( $ID, '_molongui_author_box_display', true );

			if ( !in_array( $box_display, array( 'default', 'show', 'hide' ), true ) )
			{
				$box_display = 'default';
			}

			switch ( $box_display )
			{
				case 'show':
					$icon = 'visibility';
					$tip  = __( "Visible", 'molongui-authorship' );
					break;

				case 'hide':
					$icon = 'hidden';
					$tip  = __( "Hidden", 'molongui-authorship' );
					break;

				default:
					if ( Settings::get( 'author_box_auto_display_override', true )
						or
						in_array( $current_post_type, Settings::get_post_types_with_author_box( 'auto' ), true )
					)
					{
						$icon = 'visibility';
						$tip  = __( "Visible — inherited from global settings", 'molongui-authorship' );
					}
					else
					{
						$icon = 'hidden';
						$tip  = __( "Hidden because no post configuration provided", 'molongui-authorship' );
					}

					break;
			}

			$html  = '<div id="box_display_' . absint( $ID ) . '" class="m-tooltip" data-display-box="' . esc_attr( $box_display ) . '">';
			$html .= '<span class="dashicons dashicons-'.esc_attr( $icon ).'"></span>';
			$html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">' . esc_html( $tip ) . '</span>';
			$html .= '</div>';

			echo $html;
			return;
		}
	}

	public function add_user_filter()
	{
		if ( apply_filters( 'molongui_authorship/add_user_filter_to_post_list_screen', true ) )
		{
			global $post_type;
			$post_types = Settings::enabled_post_types();

			if ( in_array( $post_type, $post_types ) )
			{
				$args = array
				(
					'name'            => 'author',                                    
					'show_option_all' => __( "All authors", 'molongui-authorship' ),  
					'role__in'        => Settings::enabled_user_roles(),
				);

				if ( isset( $_GET['author'] ) )
				{
					$args['selected'] = $_GET['author'];
				}

				wp_dropdown_users( $args );
			}
		}
	}

	public function hide_user_filter( $default )
	{
		$threshold = apply_filters( 'molongui_authorship/hide_user_filter_threshold', 1000 );

		if ( Admin_User::get_user_count() > $threshold )
		{
			return false;
		}

		return $default;
	}

	public function add_guest_filter()
	{
		if ( !Settings::is_enabled( 'guest-author' ) )
		{
			return;
		}

		/*!
		 * FILTER HOOK
		 * Allows preventing the display of the guest author filter at the top of the posts listing table.
		 *
		 * The guest author filter allows users to filter listed posts by guest author, similar to the default 'date' or
		 * 'category' filters.
		 *
		 * @since 4.9.0
		 * @since 4.9.5 Renamed from 'molongui_authorship/add_guest_filter'
		 */
		if ( apply_filters( 'molongui_authorship/add_guest_filter_to_post_list_screen', true ) )
		{
			global $post_type;
			$post_types = Settings::enabled_post_types( 'guest-author' );

			if ( in_array( $post_type, $post_types ) )
			{
				$args = array
				(
					'type'       => 'guests',
					'post_types' => array( $post_type ),
					'dont_sort'  => true,
					'prefetch'   => array
					(
						'core' => array( 'post_title' ),
						'meta' => array(),
					),
				);
				$guests = Authors::get_authors( $args );

				if ( !empty( $guests ) )
				{
					$selected = isset( $_GET['guest'] ) ? $_GET['guest'] : 0;

					$output  = '<select id="filter-by-guest" name="guest">';
					$output .= '<option value="0">' . esc_html__( "All guest authors", 'molongui-authorship' ) . '</option>';
					foreach ( $guests as $guest )
					{
						$guest_id   = $guest->get_id();
						$guest_name = $guest->get_base_display_name();

						$output .= '<option value="' . $guest_id . '" ' . ( $guest_id == $selected ? 'selected' : '' ) . '>' . $guest_name . '</option>';
					}
					$output .= '</select>';

					echo $output;
				}
			}
		}
	}

	public function hide_guest_filter( $default )
	{
		$threshold = apply_filters( 'molongui_authorship/hide_guest_filter_threshold', 1000 );

		if ( Guest_Author::get_guest_count() > $threshold )
		{
			return false;
		}

		return $default;
	}


	public function should_add_author_metabox( $post_type )
	{
		if ( !Post::byline_takeover() )
		{
			return apply_filters( 'molongui_authorship/add_authors_widget', false, $post_type );
		}

		if ( !in_array( $post_type, Settings::enabled_post_types(), true ) )
		{
			return apply_filters( 'molongui_authorship/add_authors_widget', false, $post_type );
		}

		return apply_filters( 'molongui_authorship/add_authors_widget', true, $post_type );
	}

	public function should_add_contributor_metabox( $post_type )
	{
		if ( is_plugin_active( 'molongui-post-contributors/molongui-post-contributors.php' ) )
		{
			return false;
		}

		if ( 'guest_author' === $post_type )
		{
			return false;
		}
		return apply_filters( 'molongui_authorship/add_contributors_widget', true, $post_type );
	}

	public function add_author_metabox( $post_type )
	{
		/*!
		 * FILTER HOOK
		 *
		 * Allows changing the capabilities criteria followed to decide whether to add custom meta boxes.
		 *
		 * @param bool   Current user editor capabilities.
		 * @param string Current post type.
		 * @since 4.4.0
		 */
		$editor_caps = apply_filters( 'authorship/editor_caps', current_user_can( 'edit_others_pages' ) or current_user_can( 'edit_others_posts' ), $post_type );

		if ( !$editor_caps )
		{
			return;
		}

		if ( $this->should_add_author_metabox( $post_type ) )
		{
			add_meta_box
			(
				'molongui-post-authors-box'
				, __( "Authors", 'molongui-authorship' )
				, array( $this, 'render_author_metabox' )
				, $post_type
				, 'side'
				, 'high'
			);
		}

		if ( $this->should_add_contributor_metabox( $post_type ) )
		{
			add_meta_box
			(
				'molongui-post-contributors-box'
				, __( "Contributors", 'molongui-authorship' )
				, array( $this, 'render_contributor_metabox' )
				, $post_type
				, 'side'
				, 'high'
			);
		}
	}

	public function add_box_metabox( $post_type )
	{
		if ( Settings::is_enabled( 'author-box' )
			and in_array( $post_type, Settings::get_post_types_with_author_box( 'manual' ) )
			and apply_filters( 'authorship/add_author_box_widget', true, $post_type ) )
		{
			add_meta_box
			(
				'molongui-author-box-box'
				, __( "Author Box", 'molongui-authorship' )
				,  array( $this, 'render_box_metabox' )
				, $post_type
				, 'side'
				, 'high'
			);
		}
	}

	public function render_author_metabox( $post )
	{
		wp_nonce_field( 'molongui_authorship_post', 'molongui_authorship_post_nonce' );

		self::author_selector( $post->ID );
	}

	public function render_contributor_metabox( $post )
	{
		$class = Helpers::is_edit_mode() ? 'components-button is-secondary' : 'button button-secondary';
		?>
		<div class="molongui-metabox">
			<div class="m-title"><?php esc_html_e( "Reviewers? Fact-checkers?", 'molongui-authorship' ); ?></div>
			<p class="m-description"><?php echo wp_kses_post( sprintf( __( "The %sMolongui Post Contributors%s plugin allows you to add contributors to your posts and display them towards the post author.", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></p>
			<?php if ( current_user_can( 'install_plugins' ) ) : ?>
				<p class="m-description"><?php echo wp_kses_post( sprintf( __( "Install it now, it's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></p>
				<a class="<?php echo $class; ?>" href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=molongui-post-contributors' ), 'install-plugin_molongui-post-contributors' ); ?>"><?php esc_html_e( "Install Now", 'molongui-authorship' ); ?></a>
			<?php else : ?>
				<p class="m-description"><?php echo wp_kses_post( sprintf( __( "Ask the site administrator to install it, it's %sfree%s!", 'molongui-authorship' ), '<strong>', '</strong>' ) ); ?></p>
				<a class="<?php echo $class; ?>" href="<?php echo esc_url( 'https://wordpress.org/plugins/molongui-post-contributors/' ); ?>" target="_blank"><?php esc_html_e( "Know More", 'molongui-authorship' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_box_metabox( $post )
	{
		include MOLONGUI_AUTHORSHIP_DIR . 'views/post/html-admin-box-metabox.php';
	}

	public function suggest_authors()
	{
		if ( !WP::verify_nonce( 'molongui-author-search', 'molongui-author-search-nonce', 'get' ) )
		{
			echo __( '<span class="ac_error" style="color:white"><strong>ERROR</strong>: Invalid nonce. Reload the page.</span>', 'molongui-authorship' );
			wp_die();
		}

		$response = array();

		$search = isset( $_REQUEST['term'] ) ? sanitize_text_field( strtolower( $_REQUEST['term'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( !empty( $search ) )
		{
			$ignore = isset( $_REQUEST['existing_authors'] ) ? sanitize_text_field( $_REQUEST['existing_authors'] ) : array();

			if ( !empty( $ignore ) )
			{
				$ignore = array_map( 'sanitize_text_field', explode( ',', $ignore ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}

			if ( isset( $_REQUEST['guests'] ) and 'true' === $_REQUEST['guests'] )
			{
				add_filter( 'molongui_authorship/force_search_guest_authors', '__return_true' );
			}

			$authors = self::search_authors( $search, $ignore );

			$suggestions = array();
			foreach ( $authors as $author )
			{
				$suggestions[] = array
				(
					'id'              => $author->ID,
					'type'            => esc_html( ucwords( $author->type ) ),
					'display_name'    => esc_html( $author->display_name ),
					'user_email'      => esc_html( $author->user_email ),
					'user_login'      => esc_html( $author->user_login ),
					'user_nicename'   => esc_html( rawurldecode( $author->user_nicename ) ),
					'avatar'          => ! empty( $author->avatar ) ? esc_url_raw( $author->avatar ) : '',
					'avatar_fallback' => ! empty( $author->avatar_fallback ) ? esc_url_raw( $author->avatar_fallback ) : '',
				);
			}

			$response = $suggestions;
		}

		wp_send_json( $response );

		wp_die();
	}

	public static function search_authors( $search = '', $ignored_authors = array(), $type = null )
	{
		$found_authors  = array();
		$ignored_users  = array();
		$ignored_guests = array();

		if ( !empty( $ignored_authors ) )
		{
			foreach ( $ignored_authors as $ignored_author )
			{
				$split = explode( '-', $ignored_author );
				if ( $split[0] === 'user' )
				{
					$ignored_users[] = absint( $split[1] );
				}
				else
				{
					$ignored_guests[] = absint( $split[1] );
				}
			}
		}

		if ( !isset( $type ) or 'user' === $type )
		{
			/*!
			 * FILTER HOOK
			 * Allows registered users to be excluded from search results.
			 *
			 * @since 5.0.16
			 */
			if ( apply_filters( 'molongui_authorship/search_registered_users', true ) )
			{
				$args = array
				(
					'count_total'    => false,
					'fields'         => 'all',
					'search'         => sprintf( '*%s*', $search ),
					'search_columns' => array
					(
						'display_name',
						'user_email',
						'user_login',
					),
					'capability'     => apply_filters( 'molongui_authorship/users_cap', array() ),
					'exclude'        => $ignored_users,

					'meta_key'       => 'molongui_author_archived',
					'meta_compare'   => 'NOT EXISTS', 
				);
				$found_users = get_users( $args );


				if ( !empty( $found_users ) )
				{
					foreach ( $found_users as $user )
					{
						$author      = new Author( $user, 'user' );
						$avatar_data = self::get_author_avatar_data( $author, array( 20, 20 ) );

						$found_authors[$user->user_login]                  = $user;
						$found_authors[$user->user_login]->type            = 'WP User';
						$found_authors[$user->user_login]->avatar          = $avatar_data['url'];
						$found_authors[$user->user_login]->avatar_fallback = $avatar_data['fallback'];
					}
				}
			}
		}

		if ( !isset( $type ) or 'guest' === $type )
		{
			/*!
			 * FILTER HOOK
			 * Allows guest authors to be excluded from search results.
			 *
			 * @since 5.0.16
			 */
			if ( apply_filters( 'molongui_authorship/search_guest_authors', true ) )
			{
				if ( Settings::is_guest_author_enabled_on_post_type()
					or
					Settings::is_enabled( 'guest-author' ) and is_null( Post::get_post_type() )
					or
					apply_filters( 'molongui_authorship/force_search_guest_authors', false ) )
				{
					global $wpdb;

					$like_keyword = '%' . $wpdb->esc_like( $search ) . '%';

					if ( !empty( $ignored_guests ) and is_array( $ignored_guests ) )
					{
						$ignored_guests_placeholder = implode( ',', array_map( 'absint', $ignored_guests ) );
					}
					else
					{
						$ignored_guests_placeholder = '0';
					}

					$sql = $wpdb->prepare( "
                    SELECT DISTINCT ID 
                    FROM {$wpdb->posts} 
                    LEFT JOIN {$wpdb->postmeta} pm1 ON {$wpdb->posts}.ID = pm1.post_id AND pm1.meta_key = %s
                    LEFT JOIN {$wpdb->postmeta} pm2 ON {$wpdb->posts}.ID = pm2.post_id AND pm2.meta_key = %s
                    LEFT JOIN {$wpdb->postmeta} pm3 ON {$wpdb->posts}.ID = pm3.post_id AND pm3.meta_key = %s
                    WHERE 
                        {$wpdb->posts}.post_status = 'publish' AND 
                        {$wpdb->posts}.post_type = %s AND 
                        {$wpdb->posts}.ID NOT IN ( $ignored_guests_placeholder ) AND 
                        pm3.meta_id IS NULL AND 
                        (
                            {$wpdb->posts}.post_title LIKE %s OR
                            pm1.meta_value LIKE %s OR 
                            pm2.meta_value LIKE %s
                        )
                ", 'first_name', 'last_name', '_molongui_guest_author_archived', MOLONGUI_AUTHORSHIP_CPT, $like_keyword, $like_keyword, $like_keyword );

					$found_guests = $wpdb->get_col( $sql );


					if ( !empty( $found_guests ) )
					{
						foreach ( $found_guests as $found_guest )
						{
							$guest = new Author( $found_guest, 'guest' );

							$_author                = new \stdClass();
							$_author->ID            = $found_guest;
							$_author->user_login    = $guest->get_slug();
							$_author->display_name  = $guest->get_base_display_name();
							$_author->first_name    = $guest->get_first_name();
							$_author->last_name     = $guest->get_last_name();
							$_author->type          = 'Guest author';
							$_author->user_email    = $guest->get_email();
							$_author->website       = $guest->get_website();
							$_author->description   = $guest->get_description();
							$_author->user_nicename = sanitize_title( $_author->user_login );

							$avatar_data              = self::get_author_avatar_data( $guest, array( 20, 20 ) );
							$_author->avatar           = $avatar_data['url'];
							$_author->avatar_fallback  = $avatar_data['fallback'];

							$found_authors[$_author->user_login] = $_author;
						}
					}
				}
			}
		}


		return (array)$found_authors;
	}

	public static function author_selector( $post = null, $screen = 'edit' )
	{
		include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-post-author-selector.php';
	}

	public function quick_add_author()
	{
		if ( !WP::verify_nonce( 'molongui_authorship_quick_add_author', 'nonce' ) )
		{
			echo wp_json_encode( array( 'result' => 'error', 'message' => __( "Missing or invalid nonce.", 'molongui-authorship' ), 'function' => __FUNCTION__ ) );
			wp_die();
		}

		if ( empty( $_POST['author_name'] ) or empty( $_POST['author_type'] ) or ( empty( $_POST['author_email'] ) and 'user' === $_POST['author_type'] ) )
		{
			echo wp_json_encode( array( 'result' => 'error', 'message' => __( "Missing required author information.", 'molongui-authorship' ), 'function' => __FUNCTION__ ) );
			wp_die();
		}

		if ( !current_user_can( 'create_users' ) and 'guest' !== $_POST['author_type'] )
		{
			echo wp_json_encode( array( 'result' => 'error', 'message' => __( "Sorry, you are not allowed to add users to this site.", 'molongui-authorship' ), 'function' => __FUNCTION__ ) );
			wp_die();
		}

		$author_name  = sanitize_text_field( $_POST['author_name'] );
		$author_email = sanitize_text_field( $_POST['author_email'] );

		if ( 'user' === sanitize_text_field( $_POST['author_type'] ) )
		{
			$userdata = array
			(
				'user_pass'     => wp_generate_password(),
				'user_login'    => $author_email,
				'user_email'    => $author_email,
				'role'          => 'author',
				'user_nicename' => '',
				'display_name'  => $author_name,
				'nickname'      => '',
				'first_name'    => '',
				'last_name'     => '',
				'description'   => '',
				'user_url'      => '',
			);

			$user_id = wp_insert_user( $userdata );

			if ( is_wp_error( $user_id ) )
			{
				echo wp_json_encode( array( 'result' => 'error', 'message' => $user_id->get_error_message(), 'function' => __FUNCTION__ ) );
				wp_die();
			}
			else
			{
				$author      = new Author( $user_id, 'user' );
				$avatar_data = self::get_author_avatar_data( $author, array( 20, 20 ) );
				$message     = sprintf( wp_kses_post( __( "New user (%s) created and added to this post. You can complete their profile in the Authors > View Authors screen.", 'molongui-authorship' ) ), esc_html( $author_name ) );

				echo wp_json_encode( array
				(
					'result'          => 'success',
					'message'         => $message,
					'author_id'       => $user_id,
					'author_type'     => 'user',
					'author_ref'      => 'user-'.$user_id,
					'author_name'     => $author_name,
					'avatar'          => esc_url_raw( $avatar_data['url'] ),
					'avatar_fallback' => esc_url_raw( $avatar_data['fallback'] ),
				) );
				wp_die();
			}
		}
		else
		{
			$postarr = array
			(
				'post_type'      => 'guest_author',
				'post_name'      => $author_name,
				'post_title'     => $author_name,
				'post_excerpt'   => '',
				'post_content'   => '',
				'thumbnail'      => '',
				'meta_input'     => array
				(
					'_molongui_guest_author_display_name' => $author_name,
					'_molongui_guest_author_mail'         => $author_email,
				),
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_author'    => get_current_user_id(),
			);

			$guest_id = wp_insert_post( $postarr, true );

			if ( is_wp_error( $guest_id ) )
			{
				echo wp_json_encode( array( 'result' => 'error', 'message' => $guest_id->get_error_message(), 'function' => __FUNCTION__ ) );
				wp_die();
			}
			else
			{
				$author      = new Author( $guest_id, 'guest' );
				$avatar_data = self::get_author_avatar_data( $author, array( 20, 20 ) );
				$message     = sprintf( wp_kses_post( __( "New guest author (%s) created and added to this post. You can complete their profile in the Authors > View Authors screen.", 'molongui-authorship' ) ), esc_html( $author_name ) );

				echo wp_json_encode( array
				(
					'result'          => 'success',
					'message'         => $message,
					'author_id'       => $guest_id,
					'author_type'     => 'guest',
					'author_ref'      => 'guest-'.$guest_id,
					'author_name'     => $author_name,
					'avatar'          => esc_url_raw( $avatar_data['url'] ),
					'avatar_fallback' => esc_url_raw( $avatar_data['fallback'] ),
				) );
				wp_die();
			}
		}

		wp_die();
	}

	public function remove_classic_editor_author_metabox()
	{
		if ( Post::byline_takeover() )
		{
			$post_types = Settings::enabled_post_types();

			foreach ( $post_types as $post_type )
			{
				remove_meta_box( 'authordiv', $post_type, 'normal' );
			}
		}
	}

	public function hide_block_editor_author_panel()
	{
		if ( !$this->should_add_author_metabox( Post::get_post_type() ) )
		{
			return;
		}

		ob_start();
		?>
		<style>
            /* Hide default author selector displayed by the WP Block Editor (Gutenberg). */
            /* 5.6 > WP > 5.0 (The whole control is hidden) */
            .block-editor-page .block-editor .edit-post-sidebar label[for^="post-author-selector-"],
            .block-editor-page .block-editor .edit-post-sidebar select[id^="post-author-selector-"],
                /* 5.8 > WP >= 5.6 (Only the selector is hidden. Container kept to add our custom notice) */
            .block-editor-page .block-editor .edit-post-sidebar .edit-post-post-status .components-base-control.components-combobox-control.css-wdf2ti-Wrapper.e1puf3u0 .components-combobox-control__suggestions-container,
                /* 6.5.4 > WP >= 5.8 */
            .block-editor-page .block-editor .edit-post-sidebar .post-author-selector .components-input-control__container,
                /* 6.6 > WP >= 6.5.5 */
            .block-editor-page .block-editor .edit-post-sidebar .editor-post-author__panel .components-combobox-control__suggestions-container,
                /* WP >= 6.6 */
            .editor-post-panel__row:has(.editor-post-author__panel-toggle)
                /*
							.block-editor-page .block-editor .edit-post-sidebar .editor-post-author__panel-toggle,
							.block-editor-page .block-editor .editor-sidebar .editor-post-author__panel-toggle
				*/
            {
                display: none;
            }

            /* Hide the generic Author row identified by the companion JavaScript in current Gutenberg versions. */
            .editor-post-panel__row[data-molongui-native-author-hidden="true"]
            {
                display: none !important;
            }

            /* Style the notice displayed instead of the default author selector to let the user know where is the new control. */
            .molongui-post-authors-warning
            {
                width: 100%;
                padding: 10px 6px;
                background: #f6f7f7;
                border: 1px solid #ccd0d4;
                border-radius: 3px;
                font-size: 12px;
                color: #535353;
            }
		</style>
		<?php

		echo Helpers::minify_css( ob_get_clean() );
	}

	public function enqueue_block_editor_scripts()
	{
		$current_screen = get_current_screen();

		if ( !isset( $current_screen ) or $current_screen->base !== 'post' )
		{
			return;
		}

		if ( !$this->should_add_author_metabox( Post::get_post_type() ) )
		{
			return;
		}

		wp_enqueue_script( 'molongui-authorship-block-editor-script'
			, $this->javascript_gutenberg
			, array( 'wp-data', 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-api-fetch' )
			, MOLONGUI_AUTHORSHIP_VERSION
		);

		$post_type_object = get_post_type_object( $current_screen->post_type );
		$rest_namespace   = ! empty( $post_type_object->rest_namespace ) ? $post_type_object->rest_namespace : 'wp/v2';
		$rest_base        = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $current_screen->post_type;

		wp_localize_script(
			'molongui-authorship-block-editor-script',
			'molonguiAuthorshipSaveBridge',
			array( 'restPath' => '/' . trim( $rest_namespace, '/' ) . '/' . trim( $rest_base, '/' ) )
		);

		global $current_user;
		$user        = Admin_User::instance();
		$author      = new Author( $current_user, 'user' );
		$avatar_data = self::get_author_avatar_data( $author );

		wp_localize_script( 'molongui-authorship-block-editor-script', 'molongui_authorship_block_editor_data', array
		(
			'root'   => esc_url_raw( rest_url() ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'author' => array
			(
				'id'                 => $current_user->ID,
				'type'               => 'user',
				'ref'                => 'user-'.$current_user->ID,
				'label'              => $current_user->display_name,
				'avatar'             => esc_url_raw( $avatar_data['url'] ),
				'avatar_fallback'    => esc_url_raw( $avatar_data['fallback'] ),
				'can_post_as_others' => $user->can_post_as_others( $current_user->ID ),
			),

			'author_label'    => esc_html( translate( 'Author', 'default' ) ),
			'selector_notice' => esc_html__( "The author selector has been replaced. Find the new control further down in this sidebar.", 'molongui-authorship' ),
		));
	}


	public function quick_edit_remove_default_author_selector()
	{
		global $pagenow, $post_type;

		$screens = Settings::enabled_screens();

		if ( 'edit.php' == $pagenow and Post::byline_takeover() and in_array( $post_type, $screens ) )
		{
			remove_post_type_support( $post_type, 'author' );
		}
	}

	public function quick_edit_add_fields( $column_name, $post_type )
	{
		$post_types = Settings::enabled_post_types();
		if ( !in_array( $post_type, $post_types ) )
		{
			return;
		}

		if ( $column_name == 'molongui-author' ) : ?>

			<br class="clear" />
			<fieldset class="inline-edit-col-left">
				<div class="inline-edit-col">
					<h4><?php _e( "Authorship data", 'molongui-authorship' ); ?></h4>
					<div class="inline-edit-group wp-clearfix">
						<label class="inline-edit-authors alignleft" style="width: 100%;">
							<span class="title"><?php Settings::is_enabled( 'co-authors' ) ? _e( "Authors", 'molongui-authorship' ) : _e( "Author" ); ?></span>
							<?php self::author_selector( null, 'quick' ); ?>
							<?php wp_nonce_field( 'molongui_author_box_display', 'molongui_author_box_display_nonce' ); ?>
						</label>
					</div>
				</div>
			</fieldset>

		<?php

		elseif ( $column_name == 'molongui-box' ) : ?>

			<br class="clear" />
			<fieldset class="inline-edit-col-left">
				<div class="inline-edit-col">
					<div class="inline-edit-group wp-clearfix">
						<label class="inline-edit-box-display alignleft">
							<span class="title"><?php _e( "Author box", 'molongui-authorship' ); ?></span>
							<select name="_molongui_author_box_display">
								<option value="default" ><?php _e( "Default", 'molongui-authorship' ); ?></option>
								<option value="show"    ><?php _e( "Show"   , 'molongui-authorship' ); ?></option>
								<option value="hide"    ><?php _e( "Hide"   , 'molongui-authorship' ); ?></option>
							</select>
						</label>
					</div>
				</div>
				<?php wp_nonce_field( 'molongui_authorship_quick_edit', 'molongui_authorship_quick_edit_nonce' ); ?>
			</fieldset>

		<?php endif;
	}

	public function quick_edit_init_fields()
	{
		if ( !Post::byline_takeover() )
		{
			return;
		}

		$current_screen = get_current_screen();

		if ( substr( $current_screen->id, 0, strlen( 'edit-' ) ) != 'edit-' or !in_array( $current_screen->id, Settings::enabled_screens() ) )
		{
			return;
		}

		wp_enqueue_script( 'jquery' );

		ob_start();
		?>

		<script type="text/javascript">
			// Ensure the DOM is fully loaded before doing anything.
			jQuery(function($)
			{
				// Create a copy of the WP inline edit post function.
				const $inline_editor = inlineEditPost.edit;

				// Overwrite the function with our own code.
				inlineEditPost.edit = function(id)
				{
					// "Call" the original WP edit function. We don't want to leave WordPress hanging.
					$inline_editor.apply(this, arguments);

					// Get the post ID.
					let post_id = 0;
					if ( typeof(id) === 'object' )
					{
						post_id = parseInt(this.getId(id));
					}

					// If we have our post...
					if ( post_id !== 0 )
					{
						// Find quick editor row and the post being edited.
						const $q_editor = $('#edit-' + post_id);
						const $post_row = $('#post-' + post_id);

						/*
						 * Populate the author selector from structured data already rendered in the Author and Co-authors columns.
						 *
						 * Quick Edit reuses a cloned selector with no post context. The list-table row therefore carries the
						 * canonical main reference explicitly, while the shared author-selector JavaScript owns the row markup.
						 * This avoids maintaining a second HTML template that can drift away from the full editor controls.
						 */
						const $selector = $q_editor.find('#molongui-post-authors').first();

						if ( $selector.length && 'function' === typeof window.molonguiAuthorshipPopulateAuthorSelector )
						{
							const $authors         = [];
							const $main_author_row = $post_row.find('.molongui-author p[data-author-main="1"]').first();
							const $main_author     = $main_author_row.length ? String($main_author_row.data('author-ref') || '') : '';

							$post_row.find('.molongui-author p, .molongui-coauthors p').each(function(index, item)
							{
								$authors.push(
									{
										id              : $(item).data('author-id'),
										type            : $(item).data('author-type'),
										ref             : $(item).data('author-ref'),
										name            : $(item).data('author-display-name'),
										avatar          : $(item).data('author-avatar') || '',
										avatar_fallback : $(item).data('author-avatar-fallback') || '',
									});
							});

							window.molonguiAuthorshipPopulateAuthorSelector( $selector, $authors, $main_author );
						}

						/*
						 * Populate 'Display box' custom field.
						 *
						 * @since 3.2.4
						 */

						// Read the normalized post-level value embedded in the Author Box list-table cell.
						let $box_display = $post_row.find('#box_display_' + post_id).attr('data-display-box') || 'default';

						// Populate the select. Using val() updates the selected property without leaving stale selected attributes behind.
						$q_editor.find('[name="_molongui_author_box_display"]').val($box_display);
					}
				};
			});
		</script>
		<?php

		echo Helpers::minify_js( ob_get_clean() );
	}


	public function quick_edit_save_fields( $post_id, $post )
	{
		if ( !WP::verify_nonce( 'molongui_authorship_quick_edit' ) )
		{
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
		{
			return;
		}

		if ( !Post::byline_takeover() )
		{
			return;
		}

		if ( !in_array( $post->post_type, Settings::enabled_post_types() ) )
		{
			return;
		}

		if ( !current_user_can( 'edit_post', $post_id ) )
		{
			return;
		}

		$post_authors = isset( $_POST['molongui_post_authors'] ) ? $_POST['molongui_post_authors'] : array();
		$main_author  = isset( $_POST['molongui_main_author'] ) ? $_POST['molongui_main_author'] : null;

		self::update_authors( $post_authors, $post_id, $post->post_type, $post->post_author, $main_author );

		if ( isset( $_POST['_molongui_author_box_display'] ) )
		{
			update_post_meta( $post_id, '_molongui_author_box_display', sanitize_text_field( $_POST['_molongui_author_box_display'] ) );
		}

	}


	public static function prepare_authorship_for_save( $post_id, $post_authors, $submitted_main_author = null )
	{
		$post_id       = absint( $post_id );
		$stored        = Post_Authorship::get_authorship( $post_id, true );
		$normalized    = Post_Authorship::prepare_submitted_authorship( $post_id, $post_authors, $submitted_main_author );
		$forced_author = false;

		if ( is_wp_error( $normalized ) )
		{
			return $normalized;
		}

		$authors     = $normalized['authors'];
		$main_author = $normalized['main'];

		if ( !Settings::get( 'post_as_others', false ) )
		{
			$user = Admin_User::instance();

			if ( !$user->can_post_as_others() )
			{
				$current_user_id  = get_current_user_id();
				$current_user_ref = $current_user_id ? 'user-' . $current_user_id : '';
				$explicit_main    = ( null !== $submitted_main_author && '' !== $main_author );

				if ( $current_user_ref and
					( Post_Authorship::STATE_UNRESOLVED !== $stored['state'] or $explicit_main ) and
					$current_user_ref !== $main_author )
				{
					$authors       = array_values( array_diff( $authors, array( $current_user_ref ) ) );
					$main_author   = $current_user_ref;
					$forced_author = true;
					array_unshift( $authors, $current_user_ref );
				}
			}
		}

		$normalized = Post_Authorship::normalize_authorship( $main_author, $authors );

		if ( is_wp_error( $normalized ) )
		{
			return $normalized;
		}

		$normalized['forced_current_user'] = $forced_author;

		return $normalized;
	}

	private static function flag_posting_as_others_error()
	{
		add_filter( 'redirect_post_location', function( $location )
		{
			return add_query_arg( 'posting-as-others', 'error', $location );
		});

		setcookie( 'ma_cannot_post_as_others', _x( "You are not permitted to post on behalf of others. If you wish to remove your name as the post author, please contact the site administrator to enable that option for you.", 'Error message displayed on the WP Block Editor', 'molongui-authorship' ), 0, '/' );
	}

	public function enqueue_classic_publication_guard( $hook_suffix )
	{
		if ( !in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) )
		{
			return;
		}

		$screen = get_current_screen();

		if ( !$screen || !$screen->post_type || !Post::is_post_type_enabled( $screen->post_type ) || Helpers::is_block_editor() )
		{
			return;
		}

		wp_enqueue_script(
			'molongui-authorship-classic-publication-guard',
			$this->javascript_classic,
			array(),
			MOLONGUI_AUTHORSHIP_VERSION,
			true
		);

		wp_localize_script(
			'molongui-authorship-classic-publication-guard',
			'molonguiAuthorshipClassicGuard',
			array( 'message' => Post_Authorship::main_author_required_message() )
		);
	}

	public function preflight_classic_editor_publication( $action, $result )
	{
		global $pagenow;

		if ( 'post.php' !== $pagenow || ! $result || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) )
		{
			return;
		}

		$request_action = isset( $_POST['action'] ) && is_string( $_POST['action'] )
			? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( !in_array( $request_action, array( 'editpost', 'post' ), true ) )
		{
			return;
		}

		$post_id = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post    = $post_id ? get_post( $post_id ) : false;
		$type    = $post ? $post->post_type : ( isset( $_POST['post_type'] ) && is_string( $_POST['post_type'] )
			? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$expected_action = 'editpost' === $request_action ? 'update-post_' . $post_id : 'add-' . $type;

		if ( $action !== $expected_action || !Post::byline_takeover() || !Post::is_post_type_enabled( $type, $post_id ) )
		{
			return;
		}

		$type_object = get_post_type_object( $type );

		if ( !$type_object || ( $post_id && !current_user_can( 'edit_post', $post_id ) )
			|| ( !$post_id && !current_user_can( $type_object->cap->create_posts ) ) )
		{
			return;
		}

		$status = isset( $_POST['post_status'] ) && is_string( $_POST['post_status'] )
			? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : ( $post ? $post->post_status : 'draft' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( !empty( $_POST['publish'] ) && 'private' !== $status ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		{
			$status = 'publish';
		}
		if ( !empty( $_POST['saveasdraft'] ) || !empty( $_POST['advanced'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		{
			$status = 'draft';
		}
		if ( !empty( $_POST['pending'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		{
			$status = 'pending';
		}
		if ( isset( $_POST['visibility'] ) && 'private' === $_POST['visibility'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		{
			$status = 'private';
		}

		if ( !Post_Authorship::post_status_requires_main_author( $status ) )
		{
			return;
		}

		if ( WP::verify_nonce( 'molongui_post_authors' ) )
		{
			$submitted_authors = isset( $_POST['molongui_post_authors'] ) && is_array( $_POST['molongui_post_authors'] )
				? wp_unslash( $_POST['molongui_post_authors'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$submitted_main    = isset( $_POST['molongui_main_author'] ) && is_string( $_POST['molongui_main_author'] )
				? sanitize_text_field( wp_unslash( $_POST['molongui_main_author'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$authorship        = self::prepare_authorship_for_save( $post_id, $submitted_authors, $submitted_main );
		}
		else
		{
			$authorship = Post_Authorship::get_publication_authorship_candidate(
				$post_id,
				array(),
				array( 'post_author' => $post ? $post->post_author : get_current_user_id() )
			);
		}

		if ( is_wp_error( $authorship ) || !Post_Authorship::is_publishable_authorship( $authorship['main'], $authorship['authors'] ) )
		{
			wp_die(
				esc_html( Post_Authorship::main_author_required_message() ),
				esc_html__( 'Publication blocked', 'molongui-authorship' ),
				array( 'response' => 400, 'back_link' => true )
			);
		}
	}

	public function flag_main_author_required_error( $post_id, $requested_status, $fallback_status, $authorship )
	{
		global $pagenow;

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( !wp_doing_ajax() && 'edit.php' !== $pagenow ) )
		{
			return;
		}

		$selected_posts = isset( $_REQUEST['post'] ) ? (array) wp_unslash( $_REQUEST['post'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_posts = array_filter( array_map( 'absint', $selected_posts ) );
		$message        = count( $selected_posts ) > 1
			? __( 'One or more posts need a valid main author before they can be published.', 'molongui-authorship' )
			: Post_Authorship::main_author_required_message();

		if ( !headers_sent() )
		{
			setcookie( 'ma_main_author_required', $message, 0, '/' );
		}
	}

	public function main_author_required_admin_notice()
	{
		global $pagenow;

		if ( !isset( $_COOKIE['ma_main_author_required'] ) || !is_string( $_COOKIE['ma_main_author_required'] ) )
		{
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_COOKIE['ma_main_author_required'] ) );

		if ( !headers_sent() )
		{
			setcookie( 'ma_main_author_required', '', time() - HOUR_IN_SECONDS, '/' );
		}
		unset( $_COOKIE['ma_main_author_required'] );

		if ( 'edit.php' !== $pagenow || '' === $message )
		{
			return;
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	public function update_post_author( $data, $postarr, $unsanitized_postarr = array() )
	{
		$post_id = !empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;

		if ( !self::can_save_post( $post_id, $postarr ) )
		{
			return $data;
		}

		if ( !isset( $data['post_type'] ) or !Post::is_post_type_enabled( $data['post_type'] ) )
		{
			return $data;
		}

		$has_submitted_authors = array_key_exists( 'molongui_post_authors', $postarr );
		$has_submitted_main    = array_key_exists( 'molongui_main_author', $postarr );
		$authorship_submitted  = $has_submitted_authors || $has_submitted_main || WP::verify_nonce( 'molongui_post_authors' );

		if ( $authorship_submitted )
		{
			$submitted_authors = $has_submitted_authors ? $postarr['molongui_post_authors'] : array();
			$submitted_main    = $has_submitted_main ? $postarr['molongui_main_author'] : null;
			$authorship        = self::prepare_authorship_for_save( $post_id, $submitted_authors, $submitted_main );

			if ( is_wp_error( $authorship ) )
			{
				$authorship = Post_Authorship::get_authorship( $post_id, true );
			}
		}
		else
		{
			$authorship = Post_Authorship::get_publication_authorship_candidate( $post_id, $postarr, $data );

			if ( is_wp_error( $authorship ) )
			{
				$authorship = Post_Authorship::get_authorship( $post_id, true );
			}
		}

		$new_post_author = Post_Authorship::resolve_post_author( $post_id, $authorship['main'], 'wp_insert_post_data' );

		if ( $new_post_author )
		{
			$data['post_author'] = $new_post_author;
		}

		return $data;
	}

	public function on_save( $post_id, $post )
	{
		$post_id = absint( $post_id );

		if ( !$post_id || !$post instanceof \WP_Post )
		{
			return;
		}

		$post_type           = $post->post_type;
		$post_status_changed = self::has_post_status_changed( $post_id );
		$can_save_fields     = self::can_save_post( $post_id );

		if ( !$can_save_fields )
		{
			if ( $post_status_changed && Post::is_post_type_enabled( $post_type, $post_id ) )
			{
				$post_authors = Post_Authorship::get_author_refs( $post_id, true );
				self::update_counters( $post_id, $post_type, $post_authors, $post_authors );
			}

			self::clear_post_status_tracking( $post_id );
			return;
		}


		if ( WP::verify_nonce( 'molongui_post_authors' ) )
		{
			$post_authors = isset( $_POST['molongui_post_authors'] ) ? $_POST['molongui_post_authors'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$main_author  = isset( $_POST['molongui_main_author'] ) ? $_POST['molongui_main_author'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$post_author  = isset( $_POST['post_author'] ) ? absint( $_POST['post_author'] ) : absint( $post->post_author ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			self::update_authors( $post_authors, $post_id, $post_type, $post_author, $main_author );
		}
		elseif ( $post_status_changed )
		{
			$post_authors = Post_Authorship::get_author_refs( $post_id, true );
			self::update_counters( $post_id, $post_type, $post_authors, $post_authors );
		}

		if ( WP::verify_nonce( 'molongui_author_box_display' ) )
		{
			if ( isset( $_POST['_molongui_author_box_display'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			{
				update_post_meta( $post_id, '_molongui_author_box_display', sanitize_text_field( wp_unslash( $_POST['_molongui_author_box_display'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		if ( WP::verify_nonce( 'molongui_author_box_position' ) )
		{
			if ( isset( $_POST['_molongui_author_box_position'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			{
				update_post_meta( $post_id, '_molongui_author_box_position', sanitize_text_field( wp_unslash( $_POST['_molongui_author_box_position'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		self::clear_post_status_tracking( $post_id );
	}

	public function post_status_before_update( $post_id, $data )
	{
		$post_id = absint( $post_id );

		if ( !$post_id )
		{
			return;
		}

		$old_status = get_post_status( $post_id );

		if ( $old_status )
		{
			self::$post_status_before_update[$post_id] = sanitize_key( $old_status );
		}
	}


	public function on_trash( $post_id )
	{
		$this->maybe_update_counters_for_tracked_status_transition( $post_id );
	}

	public function on_untrash( $post_id )
	{
		$this->maybe_update_counters_for_tracked_status_transition( $post_id );
	}

	public function on_before_delete( $post_id )
	{
		$post_id = absint( $post_id );
		$post     = $post_id ? get_post( $post_id ) : null;

		if ( !$post instanceof \WP_Post || !Post::is_post_type_enabled( $post->post_type, $post_id ) )
		{
			return;
		}

		if ( !in_array( $post->post_status, self::get_countable_post_statuses(), true ) )
		{
			return;
		}

		$post_authors = Post_Authorship::get_author_refs( $post_id, true );

		if ( empty( $post_authors ) )
		{
			return;
		}

		Post_Count_Updater::decrement_counter( $post->post_type, $post_authors );
	}

	public function on_transition_post_status( $new_status, $old_status, $post )
	{
		if ( !$post instanceof \WP_Post )
		{
			return;
		}

		$post_id = absint( $post->ID );

		if ( !$post_id )
		{
			return;
		}

		if ( !isset( self::$post_status_transitions[$post_id] ) )
		{
			self::$post_status_transitions[$post_id] = array
			(
				'old' => sanitize_key( $old_status ),
				'new' => sanitize_key( $new_status ),
			);
		}
		else
		{
			self::$post_status_transitions[$post_id]['new'] = sanitize_key( $new_status );
		}
	}

	private static function get_post_status_transition( $post_id )
	{
		$post_id    = absint( $post_id );
		$old_status = '';
		$new_status = $post_id ? sanitize_key( (string) get_post_status( $post_id ) ) : '';

		if ( $post_id && isset( self::$post_status_transitions[$post_id] ) )
		{
			$old_status = self::$post_status_transitions[$post_id]['old'];
			$new_status = self::$post_status_transitions[$post_id]['new'];
		}
		elseif ( $post_id && isset( self::$post_status_before_update[$post_id] ) )
		{
			$old_status = self::$post_status_before_update[$post_id];
		}

		if ( '' === $old_status )
		{
			$old_status = sanitize_key( (string) apply_filters( '_authorship/post_status_before_update', '', $post_id ) );
		}

		$old_status = sanitize_key( (string) apply_filters( 'molongui_authorship/old_post_status', $old_status, $post_id ) );
		$new_status = sanitize_key( (string) apply_filters( 'molongui_authorship/new_post_status', $new_status, $post_id ) );

		if ( '' === $old_status )
		{
			$old_status = $new_status;
		}

		return array
		(
			'old' => $old_status,
			'new' => $new_status,
		);
	}

	private static function has_post_status_changed( $post_id )
	{
		$transition = self::get_post_status_transition( $post_id );
		$changed    = $transition['old'] !== $transition['new'];

		return (bool) apply_filters( 'molongui_authorship/post_status_changed', $changed, absint( $post_id ) );
	}

	private static function clear_post_status_tracking( $post_id )
	{
		$post_id = absint( $post_id );

		unset( self::$post_status_transitions[$post_id], self::$post_status_before_update[$post_id] );
	}

	private function maybe_update_counters_for_tracked_status_transition( $post_id )
	{
		$post_id = absint( $post_id );

		if ( !$post_id || !isset( self::$post_status_transitions[$post_id] ) || !self::has_post_status_changed( $post_id ) )
		{
			return;
		}

		$post = get_post( $post_id );

		if ( !$post instanceof \WP_Post || !Post::is_post_type_enabled( $post->post_type, $post_id ) )
		{
			self::clear_post_status_tracking( $post_id );
			return;
		}

		$post_authors = Post_Authorship::get_author_refs( $post_id, true );
		self::update_counters( $post_id, $post->post_type, $post_authors, $post_authors );
	}


	public static function get_countable_post_statuses()
	{
		/*!
		 * FILTER HOOK
		 * Allows filtering the post statuses that should be counted.
		 *
		 * @param array Post statuses to be counted.
		 * @since 4.9.0
		 */
		$countable_post_status = apply_filters( 'molongui_authorship/countable_post_statuses', array
		(
			'publish',
			'private',
		));

		return Post::sanitize_post_status_arg( $countable_post_status );
	}

	public static function update_authors( $post_authors, $post_id, $post_type, $post_author, $main_author = null )
	{
		$post_id = absint( $post_id );

		if ( !$post_id )
		{
			return false;
		}

		$old_authorship = Post_Authorship::get_authorship( $post_id, true );
		$new_authorship = self::prepare_authorship_for_save( $post_id, $post_authors, $main_author );

		if ( is_wp_error( $new_authorship ) )
		{
			return $new_authorship;
		}

		if ( Post_Authorship::post_status_requires_main_author( get_post_status( $post_id ) )
			&& ! Post_Authorship::is_publishable_authorship( $new_authorship['main'], $new_authorship['authors'] ) )
		{
			return new \WP_Error(
				'molongui_authorship_main_author_required',
				__( 'Select a valid main author before updating a published post.', 'molongui-authorship' )
			);
		}

		$old_post_authors = $old_authorship['authors'];
		$new_post_authors = $new_authorship['authors'];

		$requires_materialization = in_array(
			$old_authorship['state'],
			array( Post_Authorship::STATE_LEGACY, Post_Authorship::STATE_INCONSISTENT ),
			true
		);

		$post_authors_changed = $requires_materialization ||
			$old_authorship['main'] !== $new_authorship['main'] ||
			!Helpers::arrays_equal( $old_post_authors, $new_post_authors );
		$post_status_changed  = self::has_post_status_changed( $post_id );

		if ( !$post_authors_changed and !$post_status_changed )
		{
			return false;
		}

		if ( $post_authors_changed )
		{
			$result = Post_Authorship::set_authorship(
				$post_id,
				$new_authorship['main'],
				$new_post_authors,
				'admin_post_save'
			);

			if ( is_wp_error( $result ) )
			{
				return $result;
			}

			if ( !empty( $new_authorship['forced_current_user'] ) )
			{
				self::flag_posting_as_others_error();
			}

		}

		self::update_counters( $post_id, $post_type, $new_post_authors, $old_post_authors );

		if ( $post_authors_changed )
		{
			Post_Authorship::sync_post_author( $post_id, $new_authorship['main'], 'admin_post_save' );
		}

		return $new_authorship;
	}

	public static function update_counters( $post_id, $post_type, $new_authors, $old_authors )
	{
		$transition = self::get_post_status_transition( $post_id );
		$old_status = $transition['old'];
		$new_status = $transition['new'];

		$post_statuses_to_count = self::get_countable_post_statuses();

		$old_status_is_countable = in_array( $old_status, $post_statuses_to_count, true );
		$new_status_is_countable = in_array( $new_status, $post_statuses_to_count, true );

		$post_status_changed = ( ( $old_status_is_countable and !$new_status_is_countable ) or ( !$old_status_is_countable and $new_status_is_countable ) );


		if ( $post_status_changed )
		{
			if ( in_array( $new_status, $post_statuses_to_count, true ) )
			{
				foreach ( $new_authors as $new_author )
				{
					$count_updater = Post_Count_Updater::instance();
					$count_updater::increment_counter( $post_type, $new_author );
				}
			}

			elseif ( in_array( $old_status, $post_statuses_to_count, true ) )
			{
				$count_updater = Post_Count_Updater::instance();
				$count_updater::decrement_counter( $post_type, $old_authors );
			}
		}

		elseif ( in_array( $new_status, $post_statuses_to_count, true ) )
		{
			$removed = array_diff( $old_authors, $new_authors );

			if ( !empty( $removed ) )
			{
				foreach ( $removed as $old_author )
				{
					$count_updater = Post_Count_Updater::instance();
					$count_updater::decrement_counter( $post_type, $old_author );
				}
			}

			$added = array_diff( $new_authors, $old_authors );

			if ( !empty( $added ) )
			{
				foreach ( $added as $new_author )
				{
					$count_updater = Post_Count_Updater::instance();
					$count_updater::increment_counter( $post_type, $new_author );
				}
			}
		}

		self::clear_post_status_tracking( $post_id );
	}

	public function set_default_author()
	{
		if ( Post::byline_takeover() )
		{
			$this->set_default_author_in_custom_selector();
		}
		else
		{
			$this->set_default_author_in_default_selector();
		}
	}

	public function set_default_author_in_custom_selector()
	{
		if ( empty( Settings::get( 'default_post_author_enabled', false ) ) )
		{
			return;
		}

		$_default_authors = Settings::get( 'default_post_author', '' );

		if ( empty( $_default_authors ) )
		{
			return;
		}

		$default_authors  = array();
		$_default_authors = explode( ',', $_default_authors );
		foreach ( $_default_authors as $author_ref )
		{
			$ref_data = explode( '-', $author_ref );
			$author   = new Author( $ref_data[1], $ref_data[0] );

			$avatar_data = self::get_author_avatar_data( $author, array( 20, 20 ) );

			$default_authors[$author_ref] = array
			(
				'id'              => $author->get_id(),
				'type'            => $author->get_type(),
				'avatar'          => esc_url_raw( $avatar_data['url'] ),
				'avatar_fallback' => esc_url_raw( $avatar_data['fallback'] ),
				'display_name'    => $author->get_base_display_name(),
			);
		}

		/*!
		 * FILTER HOOK
		 * Allows filtering the list of post types that will have a configured default author assigned by default.
		 *
		 * By default, only the 'post' post type is included. Developers can add or remove post types by using this
		 * filter to alter the returned array.
		 *
		 * @param array $post_types An array of eligible post types.
		 * @since 5.0.0
		 */
		$post_types = apply_filters( 'molongui_authorship/default_post_author_post_types', array( 'post' ) );

		global $pagenow;

		if ( 'post-new.php' === $pagenow and in_array( Post::get_post_type(), $post_types ) )
		{
			ob_start();
			?>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function()
				{
					const authorSelector = document.getElementById('molongui-post-authors');
					if (!authorSelector)
					{
						return;
					}

					// Select the author list.
					const authorList = authorSelector.querySelector('.molongui-post-authors__list');
					if (!authorList)
					{
						return;
					}

					// Empty current author list.
					authorList.innerHTML = '';

					// Parse the JSON-encoded authors array
					const authors = <?php echo wp_json_encode( $default_authors ); ?>;

					// Loop over each author and construct the required HTML structure.
					for (const ref in authors)
					{
						if (authors.hasOwnProperty(ref))
						{
							const authorData = authors[ref];

							// Create the outer item div
							const itemDiv = document.createElement('div');
							itemDiv.id = authorData.type + '-' + authorData.id;
							itemDiv.className = 'molongui-post-authors__item molongui-post-authors__item--' + authorData.type;

							// Create the row div
							const rowDiv = document.createElement('div');
							rowDiv.className = 'molongui-post-authors__row ui-sortable-handle';
							itemDiv.appendChild(rowDiv);

							// Add the avatar wrapper only when the resolved avatar chain returned an image.
							if ( authorData.avatar )
							{
								const avatarDiv = document.createElement('div');
								avatarDiv.className = 'molongui-post-authors__avatar';

								const avatarImg = document.createElement('img');
								avatarImg.className = 'avatar avatar-20 photo';
								avatarImg.src = authorData.avatar;
								avatarImg.alt = '';
								avatarImg.width = 20;
								avatarImg.height = 20;

								if ( authorData.avatar_fallback )
								{
									avatarImg.setAttribute( 'data-molongui-avatar-fallback', authorData.avatar_fallback );
								}

								avatarDiv.appendChild(avatarImg);
								rowDiv.appendChild(avatarDiv);
							}

							// Name div
							const nameDiv = document.createElement('div');
							nameDiv.className = 'molongui-post-authors__name';
							nameDiv.title = 'Drag this author to reorder';
							nameDiv.textContent = authorData.display_name;
							rowDiv.appendChild(nameDiv);

							// Actions div
							const actionsDiv = document.createElement('div');
							actionsDiv.className = 'molongui-post-authors__actions';
							rowDiv.appendChild(actionsDiv);

							// Actions: Up
							const upSpan = document.createElement('span');
							upSpan.className = 'dashicons dashicons-arrow-up-alt2 molongui-post-authors__up';
							upSpan.title = 'Move up';
							actionsDiv.appendChild(upSpan);

							// Actions: Down
							const downSpan = document.createElement('span');
							downSpan.className = 'dashicons dashicons-arrow-down-alt2 molongui-post-authors__down';
							downSpan.title = 'Move down';
							actionsDiv.appendChild(downSpan);

							// Actions: Delete
							const deleteSpan = document.createElement('span');
							deleteSpan.className = 'dashicons dashicons-no-alt molongui-post-authors__delete';
							deleteSpan.title = 'Remove';
							actionsDiv.appendChild(deleteSpan);

							// Hidden input
							const hiddenInput = document.createElement('input');
							hiddenInput.type = 'hidden';
							hiddenInput.name = 'molongui_post_authors[]';
							hiddenInput.value = authorData.type + '-' + authorData.id;
							rowDiv.appendChild(hiddenInput);

							// Finally, append the item to the list
							authorList.appendChild(itemDiv);
						}
					}

					/*
					 * The default-author setting is an explicit editorial choice. Keep the new hidden main-author field
					 * synchronized with the first configured default instead of leaving the auto-draft's native author
					 * as a stale explicit main.
					 */
					const defaultAuthorRefs = Object.keys(authors);
					if ( defaultAuthorRefs.length && 'function' === typeof window.molonguiAuthorshipSetMainAuthor )
					{
						window.molonguiAuthorshipSetMainAuthor( defaultAuthorRefs[0] );
					}

					// Initialize browser fallbacks for any Gravatar `d=404` URLs added above.
					if ( 'function' === typeof window.molonguiAuthorshipInitAvatarFallbacks )
					{
						window.molonguiAuthorshipInitAvatarFallbacks( authorSelector );
					}
				});
			</script>
			<?php

			echo Helpers::minify_js( ob_get_clean() );
		}
	}

	public function set_default_author_in_default_selector()
	{
		if ( empty( Settings::get( 'default_post_author_enabled', false ) ) )
		{
			return;
		}

		$default_authors = Settings::get( 'default_post_author', '' );

		if ( empty( $default_authors ) )
		{
			return;
		}

		$default_authors = explode( ',', $default_authors );

		foreach ( $default_authors as $default_author )
		{
			$author = explode( '-', $default_author );
			if ( 'user' === $author[0] )
			{
				$default_author_id = $author[1];
				break;
			}
		}

		if ( !isset( $default_author_id ) or empty( $default_author_id ) )
		{
			return;
		}

		/*!
		 * FILTER HOOK
		 * Allows filtering the list of post types that will have a configured default author assigned by default.
		 *
		 * By default, only the 'post' post type is included. Developers can add or remove post types by using this
		 * filter to alter the returned array.
		 *
		 * @param array $post_types An array of eligible post types.
		 * @since 5.0.0
		 */
		$post_types = apply_filters( 'molongui_authorship/default_post_author_post_types', array( 'post' ) );

		global $pagenow;

		if ( 'post-new.php' === $pagenow and in_array( Post::get_post_type(), $post_types ) )
		{
			ob_start();
			?>
			<script type="text/javascript">
				document.addEventListener('DOMContentLoaded', function()
				{
					// For Classic Editor.
					const authorSelectClassic = document.getElementById('post_author_override');
					if (authorSelectClassic)
					{
						authorSelectClassic.value = <?php echo $default_author_id; ?>;
					}

					// For Gutenberg Editor.
					wp.data.subscribe( function()
					{
						const isPostNew = wp.data.select('core/editor').isCleanNewPost();
						const author = wp.data.select('core/editor').getEditedPostAttribute('author');
						if ( isPostNew && author !== <?php echo $default_author_id; ?> )
						{
							wp.data.dispatch('core/editor').editPost( { author: <?php echo $default_author_id; ?> } );
						}
					});
				});
			</script>
			<?php

			echo Helpers::minify_js( ob_get_clean() );
		}
	}


	public static function clear_object_cache()
	{
		WP::deprecated_function_once( __FUNCTION__, '5.2.0' );

		Cache::clear( 'posts' );
	}

}  

new Admin_Post();
