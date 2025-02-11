<?php

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Dashboard
{
    private $javascript     = '';
    private $stylesheet     = '';
    private $stylesheet_ltr = '/assets/css/admin.eaaa.min.css';
    private $stylesheet_rtl = '/assets/css/admin-rtl.4bc5.min.css';
    public function __construct()
    {
        $this->set_assets();
        add_action( 'admin_enqueue_scripts', array( $this, 'register_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_filter( "authorship/admin_extra_styles", array( $this, 'extra_styles' ) );
        add_action( 'admin_menu', array( $this, 'add_posts_menu_items' ), PHP_INT_MAX - 1 );
        add_action( 'admin_menu', array( $this, 'add_settings_menu_items' ), PHP_INT_MAX - 1 );
        add_filter( 'plugin_action_links_'.MOLONGUI_AUTHORSHIP_BASENAME, array( Plugin::class, 'add_quick_links' ) );
    }
    public function set_assets()
    {
        $this->stylesheet = MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? $this->stylesheet_rtl : $this->stylesheet_ltr );
        $this->stylesheet = apply_filters( 'authorship/admin/styles', $this->stylesheet );
        $this->javascript = MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript;
        $this->javascript = apply_filters( 'authorship/admin/script', $this->javascript );
    }
    public function register_styles()
    {
        $deps = array( 'wp-color-picker' );

        Assets::register_style( $this->stylesheet, 'admin', $deps );
    }
    public function enqueue_styles()
    {
        $screen  = get_current_screen();
        $screens = array_merge
        (
            Settings::get_post_types_with_author_box(),
            Settings::enabled_screens(),
            array
            (
                'profile', 'users', 'user', 'user-edit',
                MOLONGUI_AUTHORSHIP_CPT, 'edit-'.MOLONGUI_AUTHORSHIP_CPT,
                Settings::instance()->get_screen_id(),
                Admin_Author::instance()->get_screen_id(),
            )
        );
        if ( !in_array( $screen->id, $screens ) )
        {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );

        Assets::enqueue_style( $this->stylesheet, 'admin', true );
    }
    public function extra_styles()
    {
        $css = '';
        $css .= '';
        return apply_filters( 'authorship/admin/extra_styles', $css );
    }
    public function add_posts_menu_items()
    {
        if ( Settings::get( 'dashboard_posts_submenu', false ) )
        {
            add_posts_page
            (
                '',
                _x( "Authors", "Posts submenu title", 'molongui-authorship' ),
                'manage_options',
                'admin.php?page=authors',
                '',
                2
            );
        }
    }
    public function add_settings_menu_items()
    {
        if ( !Settings::get( 'dashboard_settings_submenu', true ) )
        {
            return;
        }
        $position = 2;
        if ( Settings::is_enabled( 'guest-author' ) )
        {
            add_options_page
            (
                '',
                _x( "Authors", "Settings submenu title", 'molongui-authorship' ),
                'manage_options',
                Settings::url(),
                '',
                $position
            );

            $position++;
        }
    }

} // class
new Dashboard();
