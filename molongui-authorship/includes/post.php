<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Post extends \Molongui\Authorship\Common\Utils\Post
{
    private $edit_post_scripts = MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-post.b3bc.min.js';
    public function __construct()
    {
        if ( authorship_byline_takeover() )
        {
            $this->edit_post_scripts = apply_filters( 'authorship/edit_post/script', $this->edit_post_scripts );
            add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts'  ) );
            add_filter( 'authorship/edit_post_script_params', array( $this, 'post_script_params' ) );
            add_action( 'admin_print_footer_scripts-edit.php', array( $this, 'fix_mine_count' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_author_metabox' ), -1 );
            add_action( 'add_meta_boxes', array( $this, 'add_box_metabox' ), 1 );
            add_action( 'wp_ajax_authors_ajax_suggest', array( $this, 'suggest_authors' ) );
            add_action( 'wp_ajax_molongui_authorship_quick_add_author', array( $this, 'quick_add_author' ) );
            add_action( 'admin_menu', array( $this, 'remove_default_author_metabox' ) );
            add_action( 'admin_head', array( $this, 'print_block_editor_styles' ) );
            add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_scripts' ) );
            add_action( 'admin_head', array( $this, 'quick_edit_remove_default_author_selector' ) );
            add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_add_fields' ), 10, 2 );
            add_action( 'admin_footer', array( $this, 'quick_edit_init_fields' ) );
            add_filter( 'wp_insert_post_data', array( $this, 'update_post_author' ), 10, 3 );
            add_action( 'pre_post_update', array( $this, 'post_status_before_update' ), 10, 2 );
            add_action( 'trashed_post', array( $this, 'on_trash' ) );
            add_action( 'untrashed_post', array( $this, 'on_untrash' ) );
            add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
            add_action( 'pre_get_posts', array( $this, 'filter_user_posts' ), PHP_INT_MAX );
            add_filter( 'posts_where', array( $this, 'remove_author_from_where_clause' ), 10, 2 );
        }
        add_filter( 'manage_posts_columns', array( $this, 'edit_list_columns' ) );
        add_filter( 'manage_pages_columns', array( $this, 'edit_list_columns' ) );
        add_action( 'manage_posts_custom_column', array( $this, 'fill_list_columns' ), 10, 2 );
        add_action( 'manage_pages_custom_column', array( $this, 'fill_list_columns' ), 10, 2 );
        add_action( 'restrict_manage_posts', array( $this, 'add_user_filter' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_guest_filter' ) );
        add_filter( 'molongui_authorship/add_user_filter_to_post_list_screen', array( $this, 'hide_user_filter' ) );
        add_filter( 'molongui_authorship/add_guest_filter_to_post_list_screen', array( $this, 'hide_guest_filter' ) );
        add_action( 'save_post', array( $this, 'on_save' ), 10, 2 );
        add_action( 'attachment_updated', array( $this, 'on_save' ), 10, 2 );
    }
    public function register_scripts()
    {
        $deps = array( 'jquery', 'suggest' );

        if ( authorship_is_feature_enabled( 'multi' ) )
        {
            $deps[] = 'jquery-ui-sortable';
        }
        if ( Helpers::is_block_editor() )
        {
            $deps = array_merge( $deps, array( 'wp-blocks', 'wp-i18n', 'wp-edit-post' ) );
        }

        Assets::register_script( $this->edit_post_scripts, 'edit_post', $deps );
    }
    public function enqueue_scripts()
    {
        $screen = get_current_screen();
        if ( !in_array( $screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) )
             or
             ( !current_user_can( 'edit_others_posts' ) and !current_user_can( 'edit_others_pages' ) )
        )
        {
            return;
        }

        Assets::enqueue_script( $this->edit_post_scripts, 'edit_post', true );
    }
    public function post_script_params()
    {
        $ajax_suggest_link = add_query_arg( array
        (
            'action'    => 'authors_ajax_suggest',
            'post_type' => rawurlencode( get_post_type() ),
        ), wp_nonce_url( 'admin-ajax.php', 'molongui-author-search', 'molongui-author-search-nonce' ) );

        $params = array
        (
            'guest_enabled'          => authorship_is_feature_enabled( 'guest' ),
            'coauthors_enabled'      => authorship_is_feature_enabled( 'multi' ),
            'remove_author_tip'      => esc_html__( "Remove author from selection", 'molongui-authorship' ),

            'tag_title'              => esc_html__( "Drag this author to reorder", 'molongui-authorship' ),
            'delete_label'           => esc_html__( "Remove", 'molongui-authorship' ),
            'up_label'               => esc_html__( "Move up", 'molongui-authorship' ),
            'down_label'             => esc_html__( "Move down", 'molongui-authorship' ),
            'confirm_delete'         => esc_html__( "Are you sure you want to remove this author?", 'molongui-authorship' ),
            'one_author_required'    => esc_html__( "Every post must have at least one author. You can remove the current author, but if you don't add a new one before saving, you will be assigned as the post author. Are you sure you want to proceed?", 'molongui-authorship' ),
            'ajax_suggest_link'      => $ajax_suggest_link,

            'new_author_required'    => esc_html__( "Please fill in all required fields to proceed.", 'molongui-authorship' ),
            'new_author_wrong_email' => esc_html__( "Invalid email. Please enter a valid email address.", 'molongui-authorship' ),
            'new_author_confirm'     => esc_html__( "Are you sure you want to add this new author? To add an existing author, use the search box instead.", 'molongui-authorship' ),
            'new_author_added'       => esc_html__( "New author created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ),
            'new_author_ajax_error'  => esc_html__( "ERROR: Connection to the backend failed. The author has not be added.", 'molongui-authorship' ),

            'debug_mode'             => Debug::is_enabled(),
        );
        return apply_filters( 'authorship/edit_post/script_params', $params );
    }
    public function fix_mine_count()
    {
        $current_screen = get_current_screen();
        if ( !in_array( $current_screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
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
    public function edit_list_columns( $columns )
    {
        $new_columns = array();
        global $post, $post_type;
        $pt = ( isset( $post->post_type ) ? $post->post_type : '' );
        if ( empty( $post->post_type ) and $post_type == 'page' )
        {
            $pt = 'page';
        }
        if ( empty( $pt ) or $pt == 'guest_author' or !in_array( $pt, molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
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
                $new_columns['molongui-author'] = authorship_is_feature_enabled( 'multi' ) ? __( "Authors", 'molongui-authorship' ) : __( "Author" );
                if ( authorship_is_feature_enabled( 'box' ) )
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
        if ( $column == 'molongui-author' )
        {
            $authors = authorship_get_post_authors( $ID );
            if ( !$authors )
            {
                return;
            }

            $author_name_action = Settings::get( 'author_name_action' );
            foreach ( $authors as $author )
            {
                $post_type = get_post_type( $ID );

                $author_class  = new Author( $author->id, $author->type );
                $display_name  = $author_class->get_name();
                $author_avatar = $author_class->get_avatar( array( 20, 20 ), 'url' );

                if ( $author->type == 'guest' )
                {
                    $name_link  = 'edit' == $author_name_action ? esc_url( admin_url( "post.php?post=$author->id&action=edit" ) ) : esc_url( admin_url( "edit.php?post_type=$post_type&guest=$author->id" ) );
                    $author_tag = __( 'guest', 'molongui-authorship' );
                }
                else
                {
                    $name_link  = 'edit' == $author_name_action ? esc_url( admin_url( "user-edit.php?user_id=$author->id" ) ) : esc_url( admin_url( "edit.php?post_type=$post_type&author=$author->id" ) );
                    $author_tag = __( 'user', 'molongui-authorship' );
                }

                ?>
                <p data-author-id="<?php echo esc_attr( $author->id ); ?>" data-author-type="<?php echo esc_attr( $author->type ); ?>" data-author-display-name="<?php echo esc_attr( $display_name ); ?>" data-author-avatar="<?php echo esc_attr( esc_url( $author_avatar ) ); ?>" style="margin:0 0 2px;">
                    <a href="<?php echo $name_link; ?>">
                        <?php echo esc_html( $display_name ); ?>
                    </a>
                    <?php if ( authorship_is_feature_enabled( 'guest' ) ) : ?>
                        <span style="font-family: 'Courier New', Courier, monospace; font-size: 81%; color: #a2a2a2;" >
                        [<?php echo esc_html( $author_tag ); ?>]
                    </span>
                    <?php endif; ?>
                </p>
                <?php
            }

            return;
        }
        elseif ( $column == 'molongui-box' )
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
                        if ( in_array( $current_post_type, authorship_box_post_types( 'auto' ) ) )
                        {
                            $icon = 'visibility';
                            $tip  = __( "Visible", 'molongui-authorship' );
                        }
                        elseif ( in_array( $current_post_type, authorship_box_post_types( 'manual' ) ) )
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
            $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all', false );
            if ( in_array( $post_type, $post_types ) )
            {
                $args = array
                (
                    'name'            => 'author',                                   // this is the "name" attribute for filter <select>
                    'show_option_all' => __( "All authors", 'molongui-authorship' ), // label for all authors (display posts without filter)
                    'role__in'        => apply_filters( 'authorship/user/roles', array( 'administrator', 'editor', 'author', 'contributor' ) ),
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

        if ( User::get_user_count() > $threshold )
        {
            return false;
        }

        return $default;
    }
    public function add_guest_filter()
    {
        if ( !authorship_is_feature_enabled( 'guest' ) )
        {
            return;
        }

        /*!
         * FILTER HOOK
         *
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
            $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all', false );
            if ( in_array( $post_type, $post_types ) )
            {
                $guests = molongui_get_guests();

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
    public function add_meta_boxes( $post_type )
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

        $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
        if ( in_array( $post_type, $post_types ) and apply_filters( 'authorship/add_authors_widget', authorship_byline_takeover(), $post_type ) )
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
        if ( authorship_is_feature_enabled( 'box' ) and in_array( $post_type, authorship_box_post_types() ) and apply_filters( 'authorship/add_author_box_widget', true, $post_type ) )
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

        $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
        if ( in_array( $post_type, $post_types ) and apply_filters( 'authorship/add_authors_widget', authorship_byline_takeover(), $post_type ) )
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
        if ( authorship_is_feature_enabled( 'box' ) and in_array( $post_type, authorship_box_post_types() ) and apply_filters( 'authorship/add_author_box_widget', true, $post_type ) )
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

        if ( empty( $_REQUEST['q'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            wp_die();
        }

        $search = sanitize_text_field( strtolower( $_REQUEST['q'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $ignore = array_map( 'sanitize_text_field', explode( ',', sanitize_text_field( $_REQUEST['existing_authors'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $authors = self::search_authors( $search, $ignore );
        if ( empty( $authors ) )
        {
            echo esc_html( apply_filters( 'molongui_authorship/no_matching_authors_message', __( "No matching authors found.", 'molongui-authorship' ) ) );
        }

        foreach ( $authors as $author )
        {
            $user_type = 'guest-author';
            if ( $author instanceof \WP_User )
            {
                $user_type = 'wp-user';
            }
            printf(
                "%s %s<code><small>%s %s(#%s)</small></code><span style='display:none'> ∣ %s ∣ %s ∣ %s ∣ %s </span>\n",
                esc_html( str_replace( '∣', '|', $author->display_name ) ),
               '<span style="display:none">∣ </span>',
                esc_html( $author->type ),
                '<span style="display:none">∣ </span>',
                esc_html( $author->ID ),
                esc_html( $author->user_email ),
                esc_html( $author->user_login ),
                esc_html( rawurldecode( $author->user_nicename ) ),
                esc_url( $author->avatar )
            );
        }

        wp_die();
    }
    public static function search_authors( $search = '', $ignored_authors = array() )
    {
        $found_authors = array();
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
            'meta_key'       => 'molongui_author_archived',
            'meta_compare'   => 'NOT EXISTS',//'!=',
        );
        $found_users = authorship_get_users( $args ); //get_users( $args );

        if ( !empty( $found_users ) )
        {
            foreach ( $found_users as $user )
            {
                $found_authors[$user->user_login]         = $user;
                $found_authors[$user->user_login]->type   = 'WP User';
                $found_authors[$user->user_login]->avatar = get_avatar_url( $user->ID, array( 'size' => array( 20, 20 ) ) );
            }
        }
        if ( authorship_is_feature_enabled( 'guest' ) )
        {
            global $wpdb;
            $like_keyword = '%' . $wpdb->esc_like( $search ) . '%';
            $sql = $wpdb->prepare( "
                SELECT DISTINCT ID 
                FROM {$wpdb->posts} 
                LEFT JOIN {$wpdb->postmeta} pm1 ON {$wpdb->posts}.ID = pm1.post_id AND pm1.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} pm2 ON {$wpdb->posts}.ID = pm2.post_id AND pm2.meta_key = %s
                WHERE 
                    {$wpdb->posts}.post_status = 'publish' AND 
                    {$wpdb->posts}.post_type = %s AND 
                    (
                        {$wpdb->posts}.post_title LIKE %s OR
                        pm1.meta_value LIKE %s OR 
                        pm2.meta_value LIKE %s
                    )
            ", 'first_name', 'last_name', MOLONGUI_AUTHORSHIP_CPT, $like_keyword, $like_keyword, $like_keyword, $like_keyword );
            $found_guests = $wpdb->get_col( $sql );

            if ( !empty( $found_guests ) )
            {
                foreach ( $found_guests as $found_guest )
                {
                    $guest = new Author( $found_guest, 'guest' );
                    $_author                = new \stdClass();
                    $_author->ID            = $found_guest;
                    $_author->user_login    = $guest->get_slug();
                    $_author->display_name  = $guest->get_name();
                    $_author->first_name    = $guest->get_meta( 'first_name' );
                    $_author->last_name     = $guest->get_meta( 'last_name' );
                    $_author->type          = 'Guest author';
                    $_author->user_email    = $guest->get_mail();
                    $_author->website       = $guest->get_meta( 'web' );
                    $_author->description   = $guest->get_bio();
                    $_author->user_nicename = sanitize_title( $_author->user_login );
                    $_author->avatar        = $guest->get_avatar( array( 20, 20 ), 'url' );

                    $found_authors[$_author->user_login] = $_author;
                }
            }
        }

        return (array) $found_authors;
    }
    public static function author_selector( $post = null, $screen = 'edit' )
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/post/html-author-selector.php';
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
                authorship_user_clear_object_cache();

                $message = sprintf( esc_html__( "New user (%s) created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ), $author_name );
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
                authorship_guest_clear_object_cache();

                $message = sprintf( esc_html__( "New guest author (%s) created and added to this post. You can complete their profile in the Authors > View All screen.", 'molongui-authorship' ), $author_name );
                echo wp_json_encode( array( 'result' => 'success', 'message' => $message, 'author_id' => $guest_id, 'author_type' => 'guest', 'author_ref' => 'guest-'.$guest_id, 'author_name' => $author_name ) );
                wp_die();
            }
        }

        wp_die();
    }
    public function remove_default_author_metabox()
    {
        if ( authorship_byline_takeover() )
        {
            $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
            foreach ( $post_types as $post_type )
            {
                remove_meta_box( 'authordiv', $post_type, 'normal' );
            }
        }
    }
    public function print_block_editor_styles()
    {
        ob_start();
        ?>
        <style>
            .block-editor-page .block-editor .edit-post-sidebar label[for^="post-author-selector-"],
            .block-editor-page .block-editor .edit-post-sidebar select[id^="post-author-selector-"],
            .block-editor-page .block-editor .edit-post-sidebar .edit-post-post-status .components-base-control.components-combobox-control.css-wdf2ti-Wrapper.e1puf3u0 .components-combobox-control__suggestions-container,
            .block-editor-page .block-editor .edit-post-sidebar .post-author-selector .components-input-control__container,
            .block-editor-page .block-editor .edit-post-sidebar .editor-post-author__panel .components-combobox-control__suggestions-container
            .block-editor-page .block-editor .edit-post-sidebar .editor-post-author__panel-toggle,
            .block-editor-page .block-editor .editor-sidebar .editor-post-author__panel-toggle
            {
                display: none;
            }
           .molongui-post-authors-warning
           {
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
        wp_enqueue_script( 'molongui-authorship-block-editor-script', MOLONGUI_AUTHORSHIP_URL . 'assets/js/edit-post-gutenberg.87f4.min.js', array( 'wp-plugins', 'wp-edit-post', 'wp-i18n', 'wp-hooks', 'wp-data', 'utils', 'wp-element', 'wp-components', 'wp-dom-ready' ), MOLONGUI_AUTHORSHIP_VERSION );
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
                'can_post_as_others' => User::can_post_as_others( $current_user->ID ),
            ),

            'selector_notice' =>  esc_html__( "The author selector has been replaced. Find the new control further down in this sidebar.", 'molongui-authorship' ),
        ));
    }
    public function quick_edit_remove_default_author_selector()
    {
        global $pagenow, $post_type;

        $post_types = molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );

        if ( 'edit.php' == $pagenow and authorship_byline_takeover() and in_array( $post_type, $post_types ) )
        {
            remove_post_type_support( $post_type, 'author' );
        }
    }
    public function quick_edit_add_fields( $column_name, $post_type )
    {
        $post_types = molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' );
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
                            <span class="title"><?php authorship_is_feature_enabled( 'multi' ) ? _e( "Authors", 'molongui-authorship' ) : _e( "Author" ); ?></span>
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
        if ( !authorship_byline_takeover() )
        {
            return;
        }
        $current_screen = get_current_screen();
        if ( substr( $current_screen->id, 0, strlen( 'edit-' ) ) != 'edit-' or !in_array( $current_screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
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
    if ( !authorship_byline_takeover() )
    {
        return;
    }
    if ( !in_array( $post->post_type, molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) )
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

        if ( !self::can_save_post( $post_id ) )
        {
            return $data;
        }
        if ( !Settings::get( 'post_as_others', false ) )
        {
            if ( !User::can_post_as_others() )
            {
                return $data;
            }
        }
        if ( !isset( $data['post_type'] ) or !authorship_is_post_type_enabled( $data['post_type'] ) )
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
        authorship_post_clear_object_cache();
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
        if ( !authorship_is_post_type_enabled( $post_type, $post_id ) )
        {
            return;
        }
        authorship_post_clear_object_cache();
        $post_status = authorship_post_status( $post_type );
        if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
        {
            authorship_decrement_post_counter( get_post_type( $post_id ), authorship_get_post_authors( $post_id, 'ref' ) );
        }
    }
    public function on_untrash( $post_id )
    {
        $post_type = self::get_post_type( $post_id );
        if ( !authorship_is_post_type_enabled( $post_type, $post_id ) )
        {
            return;
        }
        authorship_post_clear_object_cache();
        $post_status = authorship_post_status( $post_type );
        if ( in_array( get_post_meta( $post_id, '_wp_trash_meta_status', true ), $post_status ) )
        {
            authorship_increment_post_counter( get_post_type( $post_id ), authorship_get_post_authors( $post_id, 'ref' ) );
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
    public function filter_user_posts( $wp_query )
    {
        if ( isset( $wp_query->is_guest_author ) )
        {
            return;
        }
        if ( molongui_is_request( 'admin' ) )
        {
            $current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( !isset( $current_screen->id ) )
            {
                return;
            }

            if ( !( $wp_query->is_author and in_array( $current_screen->id, molongui_enabled_post_screens( MOLONGUI_AUTHORSHIP_PREFIX, 'all' ) ) ) )
            {
                return;
            }
        }
        if ( !$wp_query->is_main_query() and apply_filters_ref_array( 'molongui_edit_main_query_only', array( true, &$wp_query ) ) )
        {
            return;
        }
        if ( $wp_query->is_author )
        {
            if ( !empty( $wp_query->query_vars['author'] ) )
            {
                $author_id = $wp_query->query_vars['author'];
            }
            else
            {
                $author = get_users( array( 'nicename' => $wp_query->query_vars['author_name'] ) );
                if ( !$author )
                {
                    return;
                }

                $author_id = $author[0]->ID;
            }
            authorship_add_author_meta_query( $wp_query, 'user', $author_id );
            add_filter( '_authorship/posts_where', '__return_true' );
        }
    }
    public function remove_author_from_where_clause( $where, $wp_query )
    {
        if ( apply_filters( '_authorship/posts_where', false ) )
        {
            remove_filter( '_authorship/posts_where', '__return_true' );

            $_where = $where;

            if ( !empty( $wp_query->query_vars['author'] ) )
            {
                global $wpdb;
                $where = str_replace( ' AND '.$wpdb->posts.'.post_author IN ('.$wp_query->query_vars['author'].')', '', $where );
                $where = str_replace( ' AND ('.$wpdb->posts.'.post_author = '.$wp_query->query_vars['author'].')' , '', $where );
                $where = apply_filters( 'authorship/posts_where', $where, $_where, $wp_query );
            }
        }
        return $where;
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
        $post_authors_changed = isset( $new_post_authors ) ? !molongui_are_arrays_equal( $old_post_authors, $new_post_authors ) : true;
        $post_status_changed  = apply_filters( 'molongui_authorship/post_status_changed', false, $post_id );
        if ( !$post_authors_changed and !$post_status_changed )
        {
            return;
        }
        if ( $post_authors_changed )
        {
            if ( empty( $new_post_authors ) and in_array( $post_type, molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX ) ) )
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
                if ( !User::can_post_as_others() )
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
                        setcookie( 'ma_cannot_post_as_others', _x( "You are not allowed to post on behalf of others. Ask the site administrator to enable that option for you if you wish to remove your name as the post author.", 'Error message displayed on the WP Block Editor', 'molongui-authorship' ), 0, '/' );
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
        $post_statuses_to_count = Post::get_countable_post_statuses();
        $old_status_is_countable = in_array( $old_status, $post_statuses_to_count );
        $new_status_is_countable = in_array( $new_status, $post_statuses_to_count );
        $post_status_changed = ( ( $old_status_is_countable and !$new_status_is_countable ) or ( !$old_status_is_countable and $new_status_is_countable ) );
        if ( $post_status_changed )
        {
            if ( in_array( $new_status, $post_statuses_to_count ) )
            {
                authorship_increment_post_counter( $post_type, $new_authors );
            }
            elseif ( in_array( $old_status, $post_statuses_to_count ) )
            {
                authorship_decrement_post_counter( $post_type, $old_authors );
            }
        }
        elseif ( in_array( $new_status, $post_statuses_to_count ) )
        {
            $removed = array_diff( $old_authors, $new_authors );
            if ( !empty( $removed ) )
            {
                authorship_decrement_post_counter( $post_type, $removed );
            }
            $added = array_diff( $new_authors, $old_authors );
            if ( !empty( $added ) )
            {
                authorship_increment_post_counter( $post_type, $added );
            }
        }
    }

} // class
new Post();