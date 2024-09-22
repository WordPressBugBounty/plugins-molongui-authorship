<?php

/*!
 * Molongui Authorship
 *
 * @package           Molongui Authorship
 * @author            Molongui
 * @copyright         2015 Molongui
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Molongui Authorship
 * Plugin URI:        https://www.molongui.com/wordpress-plugin-post-authors
 * Description:       Best Author Box for WordPress! Easy, Beautiful and Responsive. Add authors, co-authors, multiple authors and guest authors to your WordPress posts.
 * Version:           4.9.7
 * Requires at least: 5.2
 * Tested up to:      6.6
 * Requires PHP:      5.6.20
 * Author:            Molongui
 * Author URI:        https://www.molongui.com
 * Text Domain:       molongui-authorship
 * Domain Path:       /i18n
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 *
 * This plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or any later version.
 *
 * This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this plugin. If not, see
 * http://www.gnu.org/licenses.
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Modules\DB_Update;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
final class MolonguiAuthorship
{
    const VERSION = '4.9.7';
    private static $_instance = null;
    public function __clone()
    {
        _doing_it_wrong( __FUNCTION__, esc_html__( "Cloning instances of this class is forbidden.", 'molongui-authorship' ), '4.4.0' );
    }
    public function __wakeup()
    {
        _doing_it_wrong( __FUNCTION__, esc_html__( "Unserializing instances of this class is forbidden.", 'molongui-authorship' ), '4.4.0' );
    }
    public static function instance()
    {
        if ( is_null( self::$_instance ) )
        {
            self::$_instance = new self();
            do_action( 'authorship/loaded' );
        }

        return self::$_instance;
    }
    function __construct()
    {
        add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
        if ( !$this->checks_passed() )
        {
            return false;
        }
        self::define_constants();
        require_once MOLONGUI_AUTHORSHIP_DIR . 'common/autoloader.php';
        register_activation_hook( MOLONGUI_AUTHORSHIP_FILE  , array( $this, 'activate'   ) );
        register_deactivation_hook( MOLONGUI_AUTHORSHIP_FILE, array( $this, 'deactivate' ) );
        add_action( 'wpmu_new_blog', array( $this, 'activate_on_new_blog' ), 10, 6 );
        add_action( 'plugin_loaded' , array( $this, 'on_plugin_loaded'  ) );
        add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
        return true;
    }
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain( 'molongui-authorship', false, plugin_dir_path( __FILE__ ) . 'i18n/' );
    }
    public function checks_passed()
    {
        if ( version_compare( PHP_VERSION, '5.6.20', '<' ) )
        {
            add_action( 'admin_notices', array( $this, 'fail_php_error' ) );
            return false;
        }
        if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) )
        {
            add_action( 'admin_notices', array( $this, 'fail_wp_error' ) );
            return false;
        }

        return true;
    }
    function fail_php_error()
    {
        $min_php_version = '5.6.20';

        if ( isset( $_GET['activate'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            unset( $_GET['activate'] );
        }

        /*! // translators: %1$s: <strong>. %2$s: </strong>. %3$s: Min required PHP version. %4$s: User PHP version */
        $message  = sprintf( esc_html__( '%1$sMolongui Authorship%2$s requires PHP version %3$s or greater to operate. Unfortunately, your current PHP version (%4$s) is too old, so the plugin has been disabled.', 'molongui-authorship' ), '<strong>', '</strong>', $min_php_version, PHP_VERSION );
        $message .= sprintf( '<p><a href="%s" class="button-primary" target="_blank">%s</a></p>', 'https://www.molongui.com/help/how-to-update-my-php-version/', __( "How to update PHP?", 'molongui-authorship' ) );
        $html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
        echo wp_kses_post( $html_message );
    }
    function fail_wp_error()
    {
        $min_wp_version = '5.2';

        if ( isset( $_GET['activate'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            unset( $_GET['activate'] );
        }

        /*! // translators: %1$s: <strong>.%2$s: </strong>. %3$s: Min required WordPress version */
        $message = sprintf( esc_html__( '%1$sMolongui Authorship%2$s requires WordPress version %3$s or higher. Please update your WordPress to run this plugin.', 'molongui-authorship' ), '<strong>', '</strong>', $min_wp_version );
        $html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
        echo wp_kses_post( $html_message );
    }
    public function define_constants()
    {
        foreach( self::get_constants() as $name => $value )
        {
            if ( !defined( $name ) )
            {
                define( $name, $value );
            }
        }
    }
    public static function get_constants()
    {
        return array
        (
            'MOLONGUI_AUTHORSHIP_VERSION'         => self::VERSION,
            'MOLONGUI_AUTHORSHIP_FILE'            => __FILE__,                             // /var/www/domain/wp-content/plugins/molongui-boilerplate/molongui-boilerplate.php
            'MOLONGUI_AUTHORSHIP_DIR'             => plugin_dir_path( __FILE__ ),          // /var/www/domain/wp-content/plugins/molongui-boilerplate/
            'MOLONGUI_AUTHORSHIP_FOLDER'          => basename( dirname( __FILE__ ) ),      // molongui-boilerplate
            'MOLONGUI_AUTHORSHIP_URL'             => plugin_dir_url( __FILE__ ),           // https://domain.tld/wp-content/plugins/molongui-boilerplate/
            'MOLONGUI_AUTHORSHIP_BASENAME'        => plugin_basename( __FILE__ ),          // molongui-boilerplate/molongui-boilerplate.php
            'MOLONGUI_AUTHORSHIP_NAMESPACE'       => '\Molongui\Authorship',
            'MOLONGUI_AUTHORSHIP_PREFIX'          => 'molongui_authorship',
            'MOLONGUI_AUTHORSHIP_NAME'            => 'molongui-authorship',                // slug
            'MOLONGUI_AUTHORSHIP_DB_SCHEMA'       => 23,
            'MOLONGUI_AUTHORSHIP_DB_VERSION'      => 'molongui_authorship_db_version',     // Options key
            'MOLONGUI_AUTHORSHIP_INSTALL'         => 'molongui_authorship_install',        // Options key
            'MOLONGUI_AUTHORSHIP_NOTICES'         => 'molongui_authorship_notices',        // Options key
            'MOLONGUI_AUTHORSHIP_ID'              => 'authorship',
            'MOLONGUI_AUTHORSHIP_TITLE'           => 'Molongui Authorship',
            'MOLONGUI_AUTHORSHIP_DEBUG'           => false,
            'MOLONGUI_AUTHORSHIP_HAS_PRO'         => true,
            'MOLONGUI_AUTHORSHIP_MIN_PRO'         => '1.6.8',
            'MOLONGUI_AUTHORSHIP_RECOMMENDED_PRO' => '1.7.0',
            'MOLONGUI_AUTHORSHIP_WEB'             => 'https://www.molongui.com/wordpress-plugin-post-authors',
            'MOLONGUI_AUTHORSHIP_DEMO'            => 'https://demos.molongui.com/test-drive-molongui-authorship-pro/',
            'MOLONGUI_AUTHORSHIP_TAG'             => 'Authorship',
            'MOLONGUI_AUTHORSHIP_CPT'             => 'guest_author',
            'MOLONGUI_AUTHORSHIP_MAIN_SETTINGS'     => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_BOX_SETTINGS'      => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_BYLINE_SETTINGS'   => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_ARCHIVES_SETTINGS' => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_SEO_SETTINGS'      => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_COMPAT_SETTINGS'   => 'molongui_authorship_options',
            'MOLONGUI_AUTHORSHIP_INSTALLATION'      => 'molongui_authorship_install',
        );
    }
    public function activate( $network_wide )
    {
        Activator::activate( $network_wide );
    }
    public function deactivate( $network_wide )
    {
        Deactivator::deactivate( $network_wide );
    }
    public function activate_on_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta )
    {
        Activator::activate_on_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta );
    }
    public function on_plugin_loaded( $plugin )
    {
        if ( MOLONGUI_AUTHORSHIP_FILE !== $plugin )
        {
            return;
        }
        require_once MOLONGUI_AUTHORSHIP_DIR . 'includes/overwrites.php';
    }
    public function on_plugins_loaded()
    {
        self::maybe_enable_debug_mode();
        if ( self::is_disabled() )
        {
            if ( class_exists( '\Molongui\Authorship\Common\Utils\Debug' ) )
            {
                Debug::console_log( null, "The ".MOLONGUI_AUTHORSHIP_TITLE." plugin is disabled. Remove the 'noAuthorship' query string from the URL in order to enable it." );
            }
            return false;
        }
        $this->update_db();

        if ( $this->is_compatible() )
        {
            $this->init();
        }
    }
    private function maybe_enable_debug_mode()
    {
        if ( !is_admin() and isset( $_GET['debugAuthorship'] ) and ( 0 !== $_GET['debugAuthorship'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            add_filter( 'authorship/debug', '__return_true' );
        }
    }
    private function is_disabled()
    {
        if ( !is_admin() and isset( $_GET['noAuthorship'] ) and 0 !== $_GET['noAuthorship'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            return true;
        }

        return false;
    }
    private function is_compatible()
    {

        return true;
    }
    private function update_db()
    {
        $update_db = new DB_Update( MOLONGUI_AUTHORSHIP_DB_SCHEMA, MOLONGUI_AUTHORSHIP_DB_VERSION, MOLONGUI_AUTHORSHIP_NAMESPACE );
        if ( $update_db->db_update_needed() )
        {
            $update_db->run_update();
        }
    }
    public function init()
    {
        $paths = array
        (
            MOLONGUI_AUTHORSHIP_DIR . 'dropins/',

            MOLONGUI_AUTHORSHIP_DIR . 'includes/helpers/',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/hooks/',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/deprecated/',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/compat.php',

            MOLONGUI_AUTHORSHIP_DIR . 'includes/guest-author.php',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/post.php',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/settings.php',
            MOLONGUI_AUTHORSHIP_DIR . 'includes/user.php',

            MOLONGUI_AUTHORSHIP_DIR . 'common/hooks.php',
        );
        foreach ( $paths as $path )
        {
            self::require_file( $path );
        }
        Debug::console_log( null, sprintf( "%s %s", MOLONGUI_AUTHORSHIP_TITLE, MOLONGUI_AUTHORSHIP_VERSION ) );
        do_action( 'authorship/init' );
    }
    public static function require_file( $path )
    {
        if ( is_file( $path ) and file_exists( $path ) )
        {
            require_once $path;
        }
        elseif ( is_dir( $path ) )
        {
            foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path ) ) as $file )
            {

                if ( $file->isFile() and 'php' === $file->getExtension() and 'index.php' !== $file->getFilename() )
                {
                    require_once $file->getPathname();
                }
            }
        }
    }

} // class
MolonguiAuthorship::instance();