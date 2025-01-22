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
use Molongui\Authorship\Settings;
use Molongui\Authorship\User;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Admin_Post extends \Molongui\Authorship\Common\Utils\Post
{
    private $javascript           = '/assets/js/edit-post.2c2e.min.js';
    private $javascript_gutenberg = MOLONGUI_AUTHORSHIP_URL . 'assets/js/edit-post-gutenberg.72e5.min.js';
    private $stylesheet           = '';
    private $stylesheet_ltr       = '';
    private $stylesheet_rtl       = '';
    public function __construct()
    {
        if ( Post::byline_takeover() )
        {
            $this->set_assets();
            add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts'  ) );
            add_filter( 'authorship/edit_post_script_params', array( $this, 'post_script_params' ) );
            add_action( 'admin_print_footer_scripts-edit.php', array( $this, 'fix_mine_count' ) );
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
            add_action( 'trashed_post', array( $this, 'on_trash' ) );
            add_action( 'untrashed_post', array( $this, 'on_untrash' ) );
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
            'confirm_delete'              => esc_html__( "Are you sure you want to remove this author?", 'molongui-authorship' ),
            'one_author_required'         => esc_html__( "Every post must have at least one author. You can remove the current author, but if you don't add a new one before saving, you will be assigned as the post author. Are you sure you want to proceed?", 'molongui-authorship' ),
            'ajax_suggest_link'           => $ajax_suggest_link,
            'author_search_min_length'    => apply_filters( 'molongui_authorship/author_search_min_length', 2 ),
            'author_search_results_limit' => apply_filters( 'molongui_authorship/author_search_results_limit', 400 ),
            'no_suggestions_found'        => esc_html( apply_filters( 'molongui_authorship/no_matching_authors_message', __( "No matching authors found.", 'molongui-authorship' ) ) ),

            'new_author_required'         => esc_html__( "Please fill in all required fields to proceed.", 'molongui-authorship' ),
            'new_author_wrong_email'      => esc_html__( "Invalid email. Please enter a valid email address.", 'molongui-authorship' ),
            'new_author_confirm'          => esc_html__( "Are you sure you want to add this new author? To add an existing author, use the search box instead.", 'molongui-authorship' ),
            'new_author_added'            => esc_html__( "New author created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ),

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
    public function edit_list_columns( $columns )
    {
        $new_columns = array();
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
        if ( array_key_exists( 'author', $columns ) ) $position = array_search( 'author', array_keys( $columns ) );      // Default 'Author' column position.
        elseif ( array_key_exists( 'title', $columns ) ) $position = array_search( 'title', array_keys( $columns ) )+1;  // After 'Title' column.
        else $position = count( $columns );                                                                                          // Last column.
        unset( $columns['author'] );
        $i = 0;
        foreach ( $columns as $key => $column )
        {
            if ( $i == $position )
            {
                $new_columns['molongui-author'] = Settings::is_enabled( 'co-authors' ) ? __( "Authors", 'molongui-authorship' ) : __( "Author" );
                if ( Settings::is_enabled( 'author-box' ) )
                {
                    $new_columns['molongui-box'] = __( "Author Box", 'molongui-authorship' );
                }
            }
            ++$i;
            $new_columns[$key] = $column;
        }
        return $new_columns;
    }
    public function fill_list_columns( $column, $ID )
    {
        if ( 'molongui-author' === $column )
        {
            $post_authors = Post::get_authors( $ID );
            if ( !$post_authors )
            {
                return;
            }

            $author_name_action = Settings::get( 'dashboard_author_name_action' );
            foreach ( $post_authors as $post_author )
            {
                $post_type = get_post_type( $ID );

                $author        = new Author( $post_author->id, $post_author->type );
                $display_name  = $author->get_name();
                $author_avatar = $author->get_avatar( array( 20, 20 ), 'url' );

                if ( 'guest' === $post_author->type )
                {
                    $name_link  = 'edit' == $author_name_action ? esc_url( admin_url( "post.php?post=$post_author->id&action=edit" ) ) : esc_url( admin_url( "edit.php?post_type=$post_type&guest=$post_author->id" ) );
                    $author_tag = __( 'guest', 'molongui-authorship' );
                }
                else
                {
                    $name_link  = 'edit' == $author_name_action ? esc_url( admin_url( "user-edit.php?user_id=$post_author->id" ) ) : esc_url( admin_url( "edit.php?post_type=$post_type&author=$post_author->id" ) );
                    $author_tag = __( 'user', 'molongui-authorship' );
                }

                ?>
                <p data-author-id="<?php echo esc_attr( $post_author->id ); ?>" data-author-type="<?php echo esc_attr( $post_author->type ); ?>" data-author-display-name="<?php echo esc_attr( $display_name ); ?>" data-author-avatar="<?php echo esc_attr( esc_url( $author_avatar ) ); ?>" style="margin:0 0 2px;">
                    <a href="<?php echo $name_link; ?>">
                        <?php echo esc_html( $display_name ); ?>
                    </a>
                    <?php if ( Settings::is_enabled( 'guest-author' ) ) : ?>
                        <span style="font-family: 'Courier New', Courier, monospace; font-size: 81%; color: #a2a2a2;" >
                        [<?php echo esc_html( $author_tag ); ?>]
                    </span>
                    <?php endif; ?>
                </p>
                <?php
            }

            return;
        }
        elseif ( 'molongui-box' === $column )
        {
            switch ( get_post_meta( $ID, '_molongui_author_box_display', true ) )
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

                    global $post, $post_type;
                    if ( !empty( $post->post_type ) )
                    {
                        $current_post_type = $post->post_type;
                    }
                    else
                    {
                        $current_post_type = ( 'page' === $post_type ? 'page' : '' );
                    }

                    if ( !empty( $current_post_type ) )
                    {
                        if ( in_array( $current_post_type, Settings::get_post_types_with_author_box( 'auto' ) ) )
                        {
                            $icon = 'visibility';
                            $tip  = __( "Visible", 'molongui-authorship' );
                        }
                        elseif ( in_array( $current_post_type, Settings::get_post_types_with_author_box( 'manual' ) ) )
                        {
                            $icon = 'hidden';
                            $tip  = __( "Hidden because no post configuration provided", 'molongui-authorship' );
                        }
                        else
                        {
                            $icon = 'hidden';
                            $tip  = __( "Hidden", 'molongui-authorship' );
                        }
                    }
                    else
                    {
                        $icon = 'minus';
                        $tip  = __( "Cannot determine visibility for this post type", 'molongui-authorship' );
                    }

                    break;
            }

            $html  = '<div class="m-tooltip">';
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
                    'name'            => 'author',                                   // this is the "name" attribute for filter <select>
                    'show_option_all' => __( "All authors", 'molongui-authorship' ), // label for all authors (display posts without filter)
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
                $guests = Authors::get_guests();

                if ( !empty( $guests ) )
                {
                    $selected = isset( $_GET['guest'] ) ? $_GET['guest'] : 0;

                    $output  = '<select id="filter-by-guest" name="guest">';
                    $output .= '<option value="0">' . esc_html__( "All guest authors", 'molongui-authorship' ) . '</option>';
                    foreach ( $guests as $guest )
                    {
                        $output .= '<option value="' . $guest->ID . '" ' . ( $guest->ID == $selected ? 'selected' : '' ) . '>' . $guest->post_title . '</option>';
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

        $post_types = Settings::enabled_post_types();
        if ( in_array( $post_type, $post_types ) and apply_filters( 'authorship/add_authors_widget', Post::byline_takeover(), $post_type ) )
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

        /*!
         * FILTER HOOK
         *
         * Allows filtering contributors metabox display criteria.
         *
         * @param bool   True by default.
         * @param string Current post type.
         * @since 4.8.6
         */
        if ( in_array( $post_type, $post_types ) and
             !is_plugin_active( 'molongui-post-contributors/molongui-post-contributors.php' ) and
             apply_filters( 'authorship/add_contributors_widget', true, $post_type ) )
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
        $class = Helpers::is_edit_mode() ? 'components-button is-secondary' : 'button button-primary';
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
                if ( empty( $author->avatar ) )
                {
                    $author->avatar = 'data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22150%22%20height%3D%22150%22%20viewBox%3D%220%200%20150%20150%22%20preserveAspectRatio%3D%22xMidYMid%20meet%22%3E%3Cg%20transform%3D%22translate(0.000000%2C150.000000)%20scale(0.025000%2C-0.025000)%22%20fill%3D%22%23D3D3D3%22%20stroke%3D%22none%22%3E%3Cpath%20d%3D%22M2745%205789%20c-225%20-20%20-496%20-81%20-722%20-164%20-127%20-47%20-393%20-177%20-508%20-250%20-254%20-160%20-574%20-453%20-748%20-687%20-645%20-864%20-748%20-1994%20-267%20-2947%20203%20-403%20530%20-781%20895%20-1034%20972%20-675%202238%20-675%203210%200%20302%20209%20593%20520%20786%20839%20205%20337%20324%20675%20385%201090%2025%20168%2025%20562%201%20728%20-50%20333%20-121%20571%20-256%20851%20-144%20299%20-297%20513%20-545%20760%20-252%20251%20-441%20388%20-741%20535%20-88%2044%20-204%2095%20-258%20115%20-391%20143%20-817%20200%20-1232%20164z%20m509%20-1123%20c231%20-60%20447%20-213%20577%20-411%20264%20-399%20212%20-923%20-124%20-1259%20-305%20-305%20-772%20-382%20-1152%20-189%20-269%20136%20-453%20367%20-528%20663%20-32%20128%20-30%20349%205%20481%20103%20382%20403%20660%20795%20734%20116%2022%20301%2014%20427%20-19z%20m51%20-2291%20c319%20-55%20581%20-167%20837%20-357%20233%20-173%20463%20-458%20582%20-720%20l35%20-77%20-72%20-65%20c-347%20-311%20-749%20-516%20-1187%20-605%20-179%20-36%20-283%20-46%20-500%20-46%20-217%200%20-321%2010%20-500%2046%20-438%2089%20-840%20294%20-1187%20605%20l-72%2065%2035%2077%20c81%20179%20207%20362%20358%20519%20315%20330%20729%20529%201201%20577%2083%208%20383%20-3%20470%20-19z%22/%3E%3C/g%3E%3C/svg%3E';
                    $data_uri = true;
                }

                $suggestions[] = array
                (
                    'id'            => $author->ID,
                    'type'          => esc_html( ucwords( $author->type ) ),
                    'display_name'  => esc_html( $author->display_name ),
                    'user_email'    => esc_html( $author->user_email ),
                    'user_login'    => esc_html( $author->user_login ),
                    'user_nicename' => esc_html( rawurldecode( $author->user_nicename ) ),
                    'avatar'        => isset( $data_uri ) ? $author->avatar : esc_url( $author->avatar ),
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
                'meta_compare'   => 'NOT EXISTS',//'!=',
            );
            $found_users = Authors::get_users( $args ); //get_users( $args );

            if ( !empty( $found_users ) )
            {
                foreach ( $found_users as $user )
                {
                    $found_authors[$user->user_login]         = $user;
                    $found_authors[$user->user_login]->type   = 'WP User';
                    $found_authors[$user->user_login]->avatar = get_avatar_url( $user->ID, array( 'size' => array( 20, 20 ) ) );
                }
            }
        }
        if ( !isset( $type ) or 'guest' === $type )
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
                    WHERE 
                        {$wpdb->posts}.post_status = 'publish' AND 
                        {$wpdb->posts}.post_type = %s AND 
                        {$wpdb->posts}.ID NOT IN ( $ignored_guests_placeholder ) AND 
                        (
                            {$wpdb->posts}.post_title LIKE %s OR
                            pm1.meta_value LIKE %s OR 
                            pm2.meta_value LIKE %s
                        )
                ", 'first_name', 'last_name', MOLONGUI_AUTHORSHIP_CPT, $like_keyword, $like_keyword, $like_keyword );
                $found_guests = $wpdb->get_col( $sql );

                if ( !empty( $found_guests ) )
                {
                    foreach ( $found_guests as $found_guest )
                    {
                        $guest = new Author( $found_guest, 'guest' );
                        $_author                = new \stdClass();
                        $_author->ID            = $found_guest;
                        $_author->user_login    = $guest->get_slug();
                        $_author->display_name  = $guest->get_display_name();
                        $_author->first_name    = $guest->get_first_name();
                        $_author->last_name     = $guest->get_last_name();
                        $_author->type          = 'Guest author';
                        $_author->user_email    = $guest->get_email();
                        $_author->website       = $guest->get_website();
                        $_author->description   = $guest->get_description();
                        $_author->user_nicename = sanitize_title( $_author->user_login );
                        $_author->avatar        = $guest->get_avatar( array( 20, 20 ), 'url' );

                        $found_authors[$_author->user_login] = $_author;
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
                Admin_User::clear_object_cache();

                $message = sprintf( wp_kses_post( __( "New user (%s) created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ) ), esc_html( $author_name ) );
                echo wp_json_encode( array( 'result' => 'success', 'message' => $message, 'author_id' => $user_id, 'author_type' => 'user', 'author_ref' => 'user-'.$user_id, 'author_name' => $author_name ) );
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
                self::clear_object_cache();

                $message = sprintf( wp_kses_post( __( "New guest author (%s) created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ) ), esc_html( $author_name ) );
                echo wp_json_encode( array( 'result' => 'success', 'message' => $message, 'author_id' => $guest_id, 'author_type' => 'guest', 'author_ref' => 'guest-'.$guest_id, 'author_name' => $author_name ) );
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
        ob_start();
        ?>
        <style>
            .block-editor-page .block-editor .edit-post-sidebar label[for^="post-author-selector-"],
            .block-editor-page .block-editor .edit-post-sidebar select[id^="post-author-selector-"],
            .block-editor-page .block-editor .edit-post-sidebar .edit-post-post-status .components-base-control.components-combobox-control.css-wdf2ti-Wrapper.e1puf3u0 .components-combobox-control__suggestions-container,
            .block-editor-page .block-editor .edit-post-sidebar .post-author-selector .components-input-control__container,
            .block-editor-page .block-editor .edit-post-sidebar .editor-post-author__panel .components-combobox-control__suggestions-container,
            .editor-post-panel__row:has(.editor-post-author__panel-toggle)
            {
                display: none;
            }
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
        wp_enqueue_script( 'molongui-authorship-block-editor-script'
            , $this->javascript_gutenberg
            , array( 'wp-data', 'wp-plugins', 'wp-edit-post', 'wp-element' )
            , MOLONGUI_AUTHORSHIP_VERSION
        );
        global $current_user;
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
                'avatar'             => esc_url( get_avatar_url( $current_user->ID ) ),
                'can_post_as_others' => Admin_User::can_post_as_others( $current_user->ID ),
            ),

            'selector_notice' =>  esc_html__( "The author selector has been replaced. Find the new control further down in this sidebar.", 'molongui-authorship' ),
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
            jQuery(function($)
            {
                const $inline_editor = inlineEditPost.edit;
                inlineEditPost.edit = function(id)
                {
                    $inline_editor.apply(this, arguments);
                    let post_id = 0;
                    if ( typeof(id) === 'object' )
                    {
                        post_id = parseInt(this.getId(id));
                    }
                    if ( post_id !== 0 )
                    {
                        const $q_editor = $('#edit-' + post_id);
                        const $post_row = $('#post-' + post_id);
                        const authorList = $q_editor.find('.molongui-post-authors__list');
                        if ( typeof(authorList) !== 'undefined' && authorList !== null )
                        {
                            $post_row.find('.molongui-author p').each(function(index, item)
                            {
                                let $img = '';
                                if ( $(item).data('author-avatar') )
                                {
                                    $img = '<img src="' + $(item).data('author-avatar') + '">';
                                }
                                const $ref = $(item).data('author-type') + '-' + $(item).data('author-id');

                                const $div = '<div id="' + $ref + '" class="molongui-post-authors__item molongui-post-authors__item--' + $(item).data('author-type') + '" data-author-id="' + $(item).data('author-id') + '" data-author-type="' + $(item).data('author-type') + '" data-author-ref="' + $ref + '">' +
                                    '<div class="molongui-post-authors__row">' +
                                        '<div class="molongui-post-authors__avatar">' + $img  + '</div>' +
                                        '<div class="molongui-post-authors__name">' + $(item).data('author-display-name') + '</div>' +
                                        '<div class="molongui-post-authors__actions">' +
                                            '<span class="dashicons dashicons-arrow-up-alt2 molongui-post-authors__up" data-direction="up" title="' + molongui_authorship_edit_post_params.up_label + '"></span>' +
                                            '<span class="dashicons dashicons-arrow-down-alt2 molongui-post-authors__down" data-direction="down" title="' + molongui_authorship_edit_post_params.down_label + '"></span>' +
                                            '<span class="dashicons dashicons-no-alt molongui-post-authors__delete" title="' + molongui_authorship_edit_post_params.delete_label + '"></span>' +
                                        '</div>' +
                                        '<input type="hidden" name="molongui_post_authors[]" value="' + $ref + '">' +
                                        '</div>' +
                                    '</div>';
                                authorList.append($div);
                            });
                            if ( 'function' === typeof window.molonguiAuthorshipInitAuthorSelector )
                            {
                                window.molonguiAuthorshipInitAuthorSelector( $q_editor.find('#molongui-post-authors') );
                            }
                            else
                            {
                                console.error( 'Global function molonguiAuthorshipInitAuthorSelector is not defined' );
                            }
                        }
                        let $box_display = $('#box_display_' + post_id).data('display-box');
                        if ( $box_display === '' )
                        {
                            $box_display = 'default';
                        }
                        $q_editor.find('[name="_molongui_author_box_display"]').val($box_display);
                        $q_editor.find('[name="_molongui_author_box_display"]').children('[value="' + $box_display + '"]').attr('selected', true);
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
    if ( !empty( $_POST['molongui_post_authors'] ) )
    {
        self::update_authors( $_POST['molongui_post_authors'], $post_id, $post->post_type, $post->post_author );
    }
    else
    {
        self::update_authors( array( 'user-'.get_current_user_id() ), $post_id, $post->post_type, $post->post_author );
    }
    if ( isset( $_POST['_molongui_author_box_display'] ) )
    {
        update_post_meta( $post_id, '_molongui_author_box_display', sanitize_text_field( $_POST['_molongui_author_box_display'] ) );
    }
}
    public function update_post_author( $data, $postarr, $unsanitized_postarr = array() )
    {
        $post_id = $postarr['ID'];
        if ( !self::can_save_post( $post_id, $postarr ) )
        {
            return $data;
        }
        if ( !Settings::get( 'post_as_others', false ) )
        {
            if ( !Admin_User::can_post_as_others() )
            {
                return $data;
            }
        }
        if ( !isset( $data['post_type'] ) or !Post::is_post_type_enabled( $data['post_type'] ) )
        {
            return $data;
        }

        $current_author  = !empty( $postarr['post_author'] ) ? $postarr['post_author'] : false;
        $new_post_author = false;
        if ( !empty( $postarr['molongui_post_authors'] ) )
        {
            foreach ( $postarr['molongui_post_authors'] as $author )
            {
                $split = explode( '-', $author );
                if ( $split[0] == 'user' )
                {
                    $new_post_author = $split[1];
                    break;
                }
            }
        }
        if ( !$new_post_author )
        {
            if ( $current_author )
            {
                $new_post_author = $current_author;
            }
            else
            {
                $new_post_author = get_current_user_id();
            }
        }
        $data['post_author'] = $new_post_author;
        return $data;
    }
    public function on_save( $post_id, $post )
    {
        if ( !self::can_save_post( $post_id ) )
        {
            return;
        }

        $post_status_changed = apply_filters( 'molongui_authorship/post_status_changed', false, $post_id );
        if ( WP::verify_nonce( 'molongui_post_authors' ) )
        {
            if ( isset( $_POST['molongui_post_authors'] ) )
            {
                self::update_authors( $_POST['molongui_post_authors'], $post_id, sanitize_text_field( $_POST['post_type'] ), $_POST['post_author'] );
            }
            else
            {
                self::update_authors( array( 'user-'.get_current_user_id() ), $post_id, sanitize_text_field( $_POST['post_type'] ), $_POST['post_author'] );
            }
        }
        elseif ( $post_status_changed )
        {
            $old_post_authors = get_post_meta( $post_id, '_molongui_author', false );
            self::update_counters( $post_id, sanitize_text_field( $_POST['post_type'] ), $old_post_authors, $old_post_authors );
        }
        if ( WP::verify_nonce( 'molongui_author_box_display' ) )
        {
            if ( isset( $_POST['_molongui_author_box_display'] ) )
            {
                update_post_meta( $post_id, '_molongui_author_box_display', sanitize_text_field( $_POST['_molongui_author_box_display'] ) );
            }
        }
        if ( WP::verify_nonce( 'molongui_author_box_position' ) )
        {
            if ( isset( $_POST['_molongui_author_box_position'] ) )
            {
                update_post_meta( $post_id, '_molongui_author_box_position', sanitize_text_field( $_POST['_molongui_author_box_position'] ) );
            }
        }
        self::clear_object_cache();
    }
    public function post_status_before_update( $post_id, $data )
    {
        $status = $data['post_status'];

        add_filter( '_authorship/post_status_before_update', function() use ( $status )
        {

            return $status;
        });
    }
    public function on_trash( $post_id )
    {
        if ( is_customize_preview() )
        {
            return;
        }
        $post_type = self::get_post_type( $post_id );
        if ( !Post::is_post_type_enabled( $post_type, $post_id ) )
        {
            return;
        }
        self::clear_object_cache();
        $post_status = Post::get_public_post_status( $post_type );
        if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
        {
            $count_updater = Post_Count_Updater::instance();
            $count_updater::decrement_counter( get_post_type( $post_id ), Post::get_authors( $post_id ) );
        }
    }
    public function on_untrash( $post_id )
    {
        $post_type = self::get_post_type( $post_id );
        if ( !Post::is_post_type_enabled( $post_type, $post_id ) )
        {
            return;
        }
        self::clear_object_cache();
        $post_status = Post::get_public_post_status( $post_type );
        if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
        {
            $count_updater = Post_Count_Updater::instance();
            $count_updater::increment_counter( get_post_type( $post_id ), Post::get_authors( $post_id ) );
        }
    }
    public function on_transition_post_status( $new_status, $old_status, $post )
    {
        add_filter( 'molongui_authorship/old_post_status', function( $post_id ) use ( $old_status )
        {
            return $old_status;
        });
        add_filter( 'molongui_authorship/new_post_status', function( $post_id ) use ( $new_status )
        {
            return $new_status;
        });
        add_filter( 'molongui_authorship/post_status_changed', function( $post_id ) use ( $new_status, $old_status )
        {
            return $new_status !== $old_status;
        });
    }
    public static function clear_object_cache()
    {
        Cache::clear( 'posts' );
    }
    public static function get_countable_post_statuses()
    {
        /*!
         * FILTER HOOK
         *
         * Allows filtering the post statuses that should be counted.
         *
         * @param array Post statuses to be counted.
         * @since 4.9.0
         */
        return apply_filters( 'molongui_authorship/countable_post_statuses', array
        (
            'publish',
            'private',
        ));
    }
    public static function update_authors( $post_authors, $post_id, $post_type, $post_author )
    {
        $old_post_authors = get_post_meta( $post_id, '_molongui_author', false );
        $new_post_authors = $post_authors;
        $post_authors_changed = isset( $new_post_authors ) ? !Helpers::arrays_equal( $old_post_authors, $new_post_authors ) : true;
        $post_status_changed  = apply_filters( 'molongui_authorship/post_status_changed', false, $post_id );
        if ( !$post_authors_changed and !$post_status_changed )
        {
            return;
        }
        if ( $post_authors_changed )
        {
            if ( empty( $new_post_authors ) and in_array( $post_type, Settings::enabled_post_types() ) )
            {
                $current_user        = wp_get_current_user();
                $new_post_authors[0] = 'user-'.$current_user->ID;
            }
            elseif ( empty( $new_post_authors ) )
            {
                $new_post_authors[0] = 'user-'.$post_author;
            }
            if ( !Settings::get( 'post_as_others', false ) )
            {
                if ( !Admin_User::can_post_as_others() )
                {
                    $current_user = wp_get_current_user();
                    $first_author = explode( '-', $new_post_authors[0] );
                    if ( $current_user->ID !== (int)$first_author[1] )
                    {
                        $new_post_authors = array_merge( array( 'user-'.$current_user->ID ), $new_post_authors );
                        $new_post_authors = array_unique( $new_post_authors );
                        add_filter( 'redirect_post_location', function( $location )
                        {
                            return add_query_arg( 'posting-as-others', 'error', $location );
                        });
                        setcookie( 'ma_cannot_post_as_others', _x( "You are not permitted to post on behalf of others. If you wish to remove your name as the post author, please contact the site administrator to enable that option for you.", 'Error message displayed on the WP Block Editor', 'molongui-authorship' ), 0, '/' );
                    }
                }
            }
            delete_post_meta( $post_id, '_molongui_author' );
            foreach ( $new_post_authors as $author )
            {
                add_post_meta( $post_id, '_molongui_author', $author, false );
            }
            update_post_meta( $post_id, '_molongui_main_author', $new_post_authors[0] );
        }
        self::update_counters( $post_id, $post_type, $new_post_authors, $old_post_authors );
    }
    public static function update_counters( $post_id, $post_type, $new_authors, $old_authors )
    {
        $old_status = apply_filters( 'molongui_authorship/old_post_status', null, $post_id );
        $new_status = apply_filters( 'molongui_authorship/new_post_status', null, $post_id );

        if ( !isset( $old_status ) )
        {
            $old_status = apply_filters( '_authorship/post_status_before_update', 'publish' );
        }
        if ( !isset( $new_status ) )
        {
            $new_status = get_post_status( $post_id );
        }
        $post_statuses_to_count = self::get_countable_post_statuses();
        $old_status_is_countable = in_array( $old_status, $post_statuses_to_count );
        $new_status_is_countable = in_array( $new_status, $post_statuses_to_count );
        $post_status_changed = ( ( $old_status_is_countable and !$new_status_is_countable ) or ( !$old_status_is_countable and $new_status_is_countable ) );
        if ( $post_status_changed )
        {
            if ( in_array( $new_status, $post_statuses_to_count ) )
            {
                foreach ( $new_authors as $new_author )
                {
                    $count_updater = Post_Count_Updater::instance();
                    $count_updater::increment_counter( $post_type, $new_author );
                }
            }
            elseif ( in_array( $old_status, $post_statuses_to_count ) )
            {
                $count_updater = Post_Count_Updater::instance();
                $count_updater::decrement_counter( $post_type, $old_authors );
            }
        }
        elseif ( in_array( $new_status, $post_statuses_to_count ) )
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

            $default_authors[$author_ref] = array
            (
                'id'           => $author->get_id(),
                'type'         => $author->get_type(),
                'avatar'       => $author->get_avatar( array( 20, 20 ), 'url' ),
                'display_name' => $author->get_display_name(),
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
                    const authorList = authorSelector.querySelector('.molongui-post-authors__list');
                    if (!authorList)
                    {
                        return;
                    }
                    authorList.innerHTML = '';
                    const authors = <?php echo wp_json_encode( $default_authors ); ?>;
                    for (const ref in authors)
                    {
                        if (authors.hasOwnProperty(ref))
                        {
                            const authorData = authors[ref];
                            const itemDiv = document.createElement('div');
                            itemDiv.id = authorData.type + '-' + authorData.id;
                            itemDiv.className = 'molongui-post-authors__item molongui-post-authors__item--' + authorData.type;
                            const rowDiv = document.createElement('div');
                            rowDiv.className = 'molongui-post-authors__row ui-sortable-handle';
                            itemDiv.appendChild(rowDiv);
                            const avatarDiv = document.createElement('div');
                            avatarDiv.className = 'molongui-post-authors__avatar';
                            rowDiv.appendChild(avatarDiv);
                            const avatarImg = document.createElement('img');
                            avatarImg.className = 'avatar avatar-20 photo';
                            avatarImg.src = authorData.avatar;
                            avatarImg.alt = '';
                            avatarImg.width = 20;
                            avatarImg.height = 20;
                            avatarDiv.appendChild(avatarImg);
                            const nameDiv = document.createElement('div');
                            nameDiv.className = 'molongui-post-authors__name';
                            nameDiv.title = 'Drag this author to reorder';
                            nameDiv.textContent = authorData.display_name;
                            rowDiv.appendChild(nameDiv);
                            const actionsDiv = document.createElement('div');
                            actionsDiv.className = 'molongui-post-authors__actions';
                            rowDiv.appendChild(actionsDiv);
                            const upSpan = document.createElement('span');
                            upSpan.className = 'dashicons dashicons-arrow-up-alt2 molongui-post-authors__up';
                            upSpan.title = 'Move up';
                            actionsDiv.appendChild(upSpan);
                            const downSpan = document.createElement('span');
                            downSpan.className = 'dashicons dashicons-arrow-down-alt2 molongui-post-authors__down';
                            downSpan.title = 'Move down';
                            actionsDiv.appendChild(downSpan);
                            const deleteSpan = document.createElement('span');
                            deleteSpan.className = 'dashicons dashicons-no-alt molongui-post-authors__delete';
                            deleteSpan.title = 'Remove';
                            actionsDiv.appendChild(deleteSpan);
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'molongui_post_authors[]';
                            hiddenInput.value = authorData.type + '-' + authorData.id;
                            rowDiv.appendChild(hiddenInput);
                            authorList.appendChild(itemDiv);
                        }
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
                    const authorSelectClassic = document.getElementById('post_author_override');
                    if (authorSelectClassic)
                    {
                        authorSelectClassic.value = <?php echo $default_author_id; ?>;
                    }
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

} // class
new Admin_Post();
