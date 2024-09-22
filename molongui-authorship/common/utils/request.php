<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Request
{
    public static function get( $key = null, $default = null )
    {
        return self::readArray( __FUNCTION__, $key, $default );
    }
    public static function post( $key = null, $default = null )
    {
        return self::readArray( __FUNCTION__, $key, $default );
    }
    public static function request( $key = null, $default = null )
    {
        return self::readArray( __FUNCTION__, $key, $default );
    }
    public static function server( $key = null, $default = null )
    {
        return self::readArray( __FUNCTION__, $key, $default );
    }
    public static function cookie( $key = null, $default = null )
    {
        return self::readArray( __FUNCTION__, $key, $default );
    }
    protected static function readArray( $type, $key, $default )
    {
        $type = strtoupper( $type );
        if ( !in_array( $type, ['GET', 'POST', 'REQUEST', 'SERVER', 'COOKIE'] ) )
        {
            return $default;
        }
        $superglobal = $GLOBALS['_' . $type];

        if ( isset( $superglobal[$key] ) )
        {
            if ( $superglobal[$key] !== '' )
            {
                return $superglobal[$key];
            }
            else
            {
                return true;
            }
        }
        else
        {
            return $default;
        }
    }
    public static function is_rest_api_request()
    {
        global $wp_version;

        if ( version_compare( $wp_version, '6.5', '>=' ) )
        {
            return wp_is_serving_rest_request();
        }
        else
        {
            if ( empty( $_SERVER['REQUEST_URI'] ) )
            {
                return false;
            }

            $rest_prefix         = trailingslashit( rest_get_url_prefix() );
            $is_rest_api_request = ( false !== strpos( sanitize_text_field( $_SERVER['REQUEST_URI'] ), $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

            return apply_filters( 'authorship/is_rest_api_request', $is_rest_api_request );
        }
    }
    public static function is_from( $type )
    {
        switch ( $type )
        {
            case 'admin':
            case 'backend':
                return ( is_admin() and !wp_doing_ajax() );
            case 'ajax':
                return ( is_admin() and wp_doing_ajax() );
            case 'api':
                return self::is_rest_api_request();
            case 'cron':
                return defined( 'DOING_CRON' );
            case 'customizer':
                return ( is_customize_preview() );
            case 'front':
            case 'frontend':
                return ( !is_admin() or defined( 'DOING_AJAX' ) ) and !self::is_rest_api_request() and !defined( 'DOING_CRON' );
        }
    }
}