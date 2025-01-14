<?php
/*!
 * Handles all administrative tasks related to authors within WordPress.
 *
 * This file is responsible for managing the admin interface functionality, adding the "Authors" menu,
 * and handling the related options and actions required to manage authors from the WordPress dashboard.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Admin_User;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Admin_Author
{
    private $screen_id;
    use Singleton;
    public function __construct()
    {
        $this->screen_id = ( !Settings::get( 'dashboard_settings_enabled', true ) or Settings::get( 'dashboard_authors_menu', true ) )
            ? 'toplevel_page_authors'
            : 'admin_page_authors';
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_menu', array( $this, 'add_submenu' ), PHP_INT_MAX - 1 );
        add_filter( 'set-screen-option', array( $this, 'set_screen_options' ), 10, 3 );
        add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_new_author' ) );
        add_action( 'admin_post_add_author', array( $this, 'handle_add_new_author' ) );
    }
    public function get_screen_id()
    {
        return $this->screen_id;
    }
    public function add_menu()
    {
        if ( 'toplevel_page_authors' === $this->screen_id )
        {
            $icon     = apply_filters( 'molongui_authorship/authors_menu_icon', 'dashicons-' . Settings::get( 'dashboard_authors_menu_icon', 'ellipsis' ) );
            $position = intval( apply_filters( 'molongui_authorship/authors_menu_position', Settings::get( 'dashboard_authors_menu_position', 5 ) ) );

            add_menu_page
            (
                _x( "Authors", "Page title", 'molongui-authorship' ),
                _x( "Authors", "Menu title", 'molongui-authorship' ),
                'edit_others_posts',
                'authors',
                array( $this, 'render_view_all_screen' ),
                $icon, //molongui_get_base64_svg( $this->menu_icon() ),
                $position
            );
        }
    }
    public function add_submenu()
    {
        $options = Settings::get();

        $page_hook = add_submenu_page
        (
            'authors',
            _x( "Authors", "Page title", 'molongui-authorship' ),
            _x( "View All", "Authors submenu title", 'molongui-authorship' ),
            'edit_others_posts',
            'authors',
            array( $this, 'render_view_all_screen' )
        );
        add_action( "load-$page_hook", array( $this, 'add_screen_options' ) );

        if ( current_user_can( 'create_users' ) or Settings::get( 'guest_author_enabled', true ) )
        {
            add_submenu_page
            (
                'authors',
                _x( "Add New Author", "Page title", 'molongui-authorship' ),
                _x( "Add New", "Authors submenu title", 'molongui-authorship' ),
                'edit_others_posts',
                'author-new',
                array( $this, 'render_add_new_screen' ),
                5
            );
        }

        add_submenu_page
        (
            'authors',
            _x( "Settings", "Page title", 'molongui-authorship' ),
            _x( "Settings", "Authors submenu title", 'molongui-authorship' ),
            'manage_options',
            MOLONGUI_AUTHORSHIP_NAME,
            array( $this, 'render_settings_screen' )
        );

        add_submenu_page
        (
            'authors',
            _x( "Tools", "Page title", 'molongui-authorship' ),
            _x( "Tools", "Authors submenu title", 'molongui-authorship' ),
            'manage_options',
            'molongui-authorship-tools',
            null
        );

        add_submenu_page
        (
            'authors',
            _x( "Help", "Page title", 'molongui-authorship' ),
            _x( "Help!", "Authors submenu title", 'molongui-authorship' ),
            'manage_options',
            'molongui-authorship-help',
            null
        );
        global $submenu;
        if ( isset( $submenu['authors'] ) )
        {
            foreach ( $submenu['authors'] as &$item )
            {
                if ( $item[2] === 'molongui-authorship-tools' )
                {
                    $item[2] = admin_url( 'admin.php?page=molongui-authorship&tab=tools' );
                }
                elseif ( $item[2] === 'molongui-authorship-help' )
                {
                    $item[2] = admin_url( 'admin.php?page=molongui-authorship&tab=help' );
                }
            }
        }
    }
    public function add_screen_options()
    {
        $arguments = array
        (
            'label'   => __( "Authors Per Page", 'molongui-authorship' ),
            'default' => 20,
            'option'  => 'authors_per_page'
        );
        add_screen_option( 'per_page', $arguments );
    }
    public function set_screen_options( $screen_option, $option, $value )
    {
        if ( 'authors_per_page' == $option )
        {
            return $value;
        }

        return $screen_option;
    }
    public function menu_icon()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"
                 style="width: 1em; height: 1em; vertical-align: middle; fill: black; overflow: hidden;">
                    <path d="M273.664 682.666667L706.389333 249.941333l-60.330666-60.330666L213.333333 622.336V682.666667h60.330667z m35.370667 85.333333H128v-181.034667L615.893333 99.072a42.666667 42.666667 0 0 1 60.330667 0l120.704 120.704a42.666667 42.666667 0 0 1 0 60.330667L309.034667 768zM128 853.333333h768v85.333334H128v-85.333334z"  />
                </svg>';
    }
    public function render_view_all_screen()
    {
        $authors_table = new Author_List_Table( 'molongui-authorship' );
        $authors_table->prepare_items();

        include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-page-authors.php';
    }
    public static function render_add_new_screen()
    {
        include MOLONGUI_AUTHORSHIP_DIR . 'views/admin/html-page-author-new.php';
    }
    public static function render_settings_screen()
    {
        Settings::render();
    }
    public function handle_add_new_author()
    {
        check_admin_referer( 'create-author', 'authorship-create-author-nonce' );
        if ( empty( $_REQUEST['user-account'] ) )
        {
            $display_name = $_REQUEST['first-name'] . ' ' . $_REQUEST['last-name'];
            $postarr = array
            (
                'post_type'      => 'guest_author',
                'post_title'     => $display_name,
                'meta_input'     => array
                (
                    '_molongui_guest_author_display_name' => $display_name,
                    '_molongui_guest_author_first_name'   => $_REQUEST['first-name'],
                    '_molongui_guest_author_last_name'    => $_REQUEST['last-name'],
                ),
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            );
            $guest_id = wp_insert_post( $postarr, true );
            if ( is_wp_error( $guest_id ) )
            {
                update_option( 'molongui_authorship_add_author_error_'.get_current_user_id(), $guest_id, true );
                update_option( 'molongui_authorship_add_author_input_'.get_current_user_id(), $_REQUEST, true );
                wp_safe_redirect( wp_get_referer() );
                die();
            }
            else
            {
                Admin_Guest_Author::clear_object_cache();
                wp_safe_redirect( add_query_arg( array( 'post' => $guest_id, 'action' => 'edit' ), self_admin_url( 'post.php' ) ) );
                die();
            }
        }
        else
        {
            if ( !current_user_can( 'create_users' ) )
            {
                wp_die(
                    '<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
                    '<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
                    403
                );
            }
            $userdata = array
            (
                'first_name' => $_REQUEST['first-name'],
                'last_name'  => $_REQUEST['last-name'],
                'role'       => $_REQUEST['role'],
                'user_login' => $_REQUEST['username'],
                'user_pass'  => $_REQUEST['password'],
                'user_email' => wp_unslash( $_REQUEST['email'] ),
                'locale'     => '',
            );
            $user_id = wp_insert_user( $userdata );
            if ( is_wp_error( $user_id ) )
            {
                update_option( 'molongui_authorship_add_author_error_'.get_current_user_id(), $user_id, true );
                update_option( 'molongui_authorship_add_author_input_'.get_current_user_id(), $_REQUEST, true );
                wp_safe_redirect( wp_get_referer() );
                die();
            }
            else
            {
                $notify = !empty( $_REQUEST['user-notify'] ) ? 'both' : 'admin';
                wp_new_user_notification( $user_id, null, $notify );
                Admin_User::clear_object_cache();
                wp_safe_redirect( add_query_arg( 'user_id', $user_id, self_admin_url( 'user-edit.php#molongui-user-fields' ) ) );
                die();
            }
        }
    }
    public function admin_bar_new_author()
    {
        if ( 'toplevel_page_authors' !== $this->screen_id )
        {
            return;
        }

        global $wp_admin_bar;

        $wp_admin_bar->add_menu( array
        (
            'parent' => 'new-content',
            'id'     => 'new-author',
            'title'  => __( 'Author' ),
            'href'   => admin_url( 'admin.php?page=author-new' ),
        ));
    }
    public static function is_guest( $author = null )
    {
        if ( empty( $author ) ) return false;
        if ( $author instanceof \WP_User ) return false;
        if ( $author instanceof \WP_Post ) return true;
        if ( is_object( $author ) ) return ( ( !empty( $author->type ) and $author->type == 'guest' ) ? true : false );
        if ( is_string( $author ) ) if ( strncmp( $author, 'guest', strlen( 'guest' ) ) === 0 ) return true;
        return false;
    }
    public static function has_local_avatar( $author_id = null, $author_type = 'user' )
    {
        if ( empty( $author_id ) )
        {
            return false;
        }

        switch( $author_type )
        {
            case 'user':
                $img = get_user_meta( $author_id, 'molongui_author_image_url', true );
                return ( !empty( $img ) ? true : false );

            case 'guest':
                return ( has_post_thumbnail( $author_id ) ? true : false );
        }

        return false;
    }

} // class
Admin_Author::instance();