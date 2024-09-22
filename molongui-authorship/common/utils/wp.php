<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class WP
{
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

} // class