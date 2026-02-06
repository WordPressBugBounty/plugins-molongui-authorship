<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class WP
{
    public static function the_query()
    {
        global $wp_the_query;

        return apply_filters( 'authorship/the_query', $wp_the_query );
    }
    public static function home_url()
    {
        if ( function_exists( 'pll_home_url' ) && ( !defined( 'PLL_FILTER_HOME_URL' ) || !PLL_FILTER_HOME_URL ) )
        {
            $lang = apply_filters( 'authorship/lang', '' );
            return pll_home_url( $lang );
        }
        return home_url();
    }
    public static function verify_nonce( $action, $nonce = null, $source = 'post' )
    {
        if ( in_array( $source, array( 'get', 'post', 'request', 'server' ) ) )
        {
            if ( !isset( $nonce ) )
            {
                $nonce = $action . '_nonce';
            }

            switch( $source )
            {
                case 'get':
                    if ( !isset( $_GET[$nonce] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    {
                        return false;
                    }
                    $nonce = $_GET[$nonce]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    break;

                case 'post':
                    if ( !isset( $_POST[$nonce] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    {
                        return false;
                    }
                    $nonce = $_POST[$nonce]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    break;

                case 'request':
                    if ( !isset( $_REQUEST[$nonce] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    {
                        return false;
                    }
                    $nonce = $_REQUEST[$nonce]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    break;

                case 'server':
                    if ( !isset( $_SERVER[$nonce] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    {
                        return false;
                    }
                    $nonce = $_SERVER[$nonce]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    break;
            }
        }
        elseif ( 'param' === $source and !isset( $nonce ) )
        {
            return false;
        }

        return !empty( $nonce ) and wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), $action );
    }
    public static function get_sites()
    {
        if ( function_exists( 'get_sites' ) and function_exists( 'get_current_network_id' ) )
        {
            $site_ids = get_sites( array( 'fields' => 'ids', 'network_id' => get_current_network_id() ) );
        }
        else
        {
            global $wpdb;
            $site_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs WHERE site_id = $wpdb->siteid;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        return $site_ids;
    }
    public static function get_domain()
    {
        $scheme    = isset( $_SERVER['REQUEST_SCHEME'] ) ? sanitize_text_field( $_SERVER['REQUEST_SCHEME'] ) . '://' : '';
        $host      = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_HOST'] ) : '';
        $subfolder = isset( $_SERVER['DOCUMENT_URI'] ) ? explode('wp-admin', sanitize_text_field( $_SERVER['DOCUMENT_URI'] ) ) : '';
        $subfolder = is_array( $subfolder ) ? $subfolder[0] : '';

        return $scheme . $host . $subfolder;
    }
    public static function get_image_sizes( $type = 'all' )
    {
        $image_sizes = array();
        $type = in_array( $type, array( 'all', 'default', 'additional' ) ) ? $type : 'all';
        if ( in_array( $type, array( 'all', 'default' ) ) )
        {
            $default_image_sizes = get_intermediate_image_sizes();

            foreach ( $default_image_sizes as $size )
            {
                $image_sizes[$size]['width']  = intval( get_option( "{$size}_size_w" ) );
                $image_sizes[$size]['height'] = intval( get_option( "{$size}_size_h" ) );
                $image_sizes[$size]['crop']   = get_option( "{$size}_crop" ) ? get_option( "{$size}_crop" ) : false;
            }
        }
        if ( in_array( $type, array( 'all', 'additional' ) ) )
        {
            global $_wp_additional_image_sizes;

            if ( isset( $_wp_additional_image_sizes ) and count( $_wp_additional_image_sizes ) )
            {
                $image_sizes = array_merge( $image_sizes, $_wp_additional_image_sizes );
            }
        }
        return $image_sizes;
    }
    public static function get_admin_color()
    {
        $css = $scheme = '';
        global $_wp_admin_css_colors;

        if ($_wp_admin_css_colors)
        {
            $colors = $_wp_admin_css_colors[get_user_option('admin_color')]->colors;

            if (!empty($colors))
            {
                foreach ($colors as $key => $color)
                {
                    $scheme .= '--m-admin-color-' . $key . ':' . $color . ';';
                }
                $css .= ":root{ " . $scheme . " }";
            }
        }

        return !empty($css) ? $css : '';
    }
    public static function is_callback_hooked( $hook, $callback, $priority = 10 )
    {
        global $wp_filter;
        if ( !isset( $wp_filter[ $hook ] ) )
        {
            return false;
        }
        if ( is_a( $wp_filter[ $hook ], 'WP_Hook' ) )
        {
            $callbacks = $wp_filter[ $hook ]->callbacks;
        }
        else
        {
            $callbacks = $wp_filter[ $hook ];
        }
        if ( !isset( $callbacks[ $priority ] ) )
        {
            return false;
        }
        foreach ( $callbacks[ $priority ] as $hooked_callback )
        {
            if ( $hooked_callback['function'] === $callback )
            {
                return true;
            }
        }

        return false;
    }
    public static function is_block_editor()
    {
        $edit_mode = false;
        if ( function_exists( 'is_gutenberg_page' ) and is_gutenberg_page() )
        {
            $edit_mode = true;
        }
        if ( function_exists( 'get_current_screen' ) )
        {
            $current_screen = get_current_screen();
            if ( $current_screen instanceof \WP_Screen and method_exists( $current_screen, 'is_block_editor' ) and $current_screen->is_block_editor() )
            {
                $edit_mode = true;
            }
        }

        return apply_filters( 'authorship/is_block_editor', $edit_mode );
    }
    public static function is_block_theme()
    {
        return current_theme_supports( 'block-templates' );
    }
    public static function is_plugin_installed( $plugin_file )
    {
        if ( !function_exists( 'get_plugins' ) )
        {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        return isset( $all_plugins[ $plugin_file ] );
    }
    public static function deprecated_once( $type, $identifier, $since, $replacement = null, $message = '' )
    {
        static $seen = array();
        $should_log = apply_filters( 'authorship/deprecated_once_should_log', true, $type, $identifier, $since, $replacement, $message );
        if ( !$should_log )
        {
            return;
        }
        $enabled = apply_filters( 'authorship/deprecated_once_enabled', true, $type, $identifier, $since, $replacement, $message );
        if ( $enabled )
        {
            $key = $type . '|' . $identifier;

            if ( isset( $seen[$key] ) )
            {
                return; // Already logged this one in this request.
            }
            $seen[$key] = true;
        }
        switch ( $type )
        {
            case 'function':
                if ( function_exists( '_deprecated_function' ) )
                {
                    _deprecated_function( $identifier, $since, $replacement );
                }
                break;

            case 'argument':
                if ( function_exists( '_deprecated_argument' ) )
                {
                    _deprecated_argument( $identifier, $since, $message ?: ($replacement ? "Use {$replacement} instead." : '') );
                }
                break;

            case 'hook':
                if ( function_exists( '_deprecated_hook' ) )
                {
                    _deprecated_hook( $identifier, $since, $replacement, $message );
                }
                break;

            default:
                _doing_it_wrong( __FUNCTION__, 'Unknown deprecation type.', $since );
        }
    }
    public static function deprecated_function_once( $function, $since, $replacement = null )
    {
        self::deprecated_once( 'function', $function, $since, $replacement );
    }
    public static function deprecated_argument_once( $owner_function, $since, $replacement = null, $message = '' )
    {
        self::deprecated_once( 'argument', $owner_function, $since, $replacement, $message );
    }
    public static function deprecated_hook_once( $hook, $since, $replacement = null, $message = '' )
    {
        self::deprecated_once( 'hook', $hook, $since, $replacement, $message );
    }

} // class
