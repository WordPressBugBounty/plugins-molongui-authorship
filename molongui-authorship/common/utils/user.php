<?php

namespace Molongui\Authorship\Common\Utils;

use Molongui\Authorship\Common\Libraries\Browser;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class User
{
    public static function get( $user = 0 )
    {
        $user_object = false;

        if ( 0 === $user and function_exists( 'wp_get_current_user' ) )
        {
            $user_object = wp_get_current_user();
        }
        elseif ( $user instanceof \WP_User )
        {
            $user_object = $user;
        }
        elseif ( $user and is_numeric( $user ) )
        {
            $user_object = get_user_by( 'id', $user );
        }
        elseif ( is_string( $user ) )
        {
            $user_object = get_user_by('login', $user );
        }

        return $user_object;
    }
    public static function get_roles( $user = 0 )
    {
        $user_roles  = array();
        $user_object = self::get( $user );

        if ( $user_object instanceof \WP_User )
        {
            $user_roles = $user_object->roles;
        }

        return $user_roles;
    }
    public static function has_role( $role, $user = 0 )
    {
        return in_array( $role, (array) self::get_roles( $user ) );
    }
    public static function get_browser_data()
    {
        $browser = new Browser();

        return array
        (
            'platform'   => $browser->getPlatform() . ' ' . ( $browser->isMobile() ? '(mobile)' : ( $browser->isTablet() ? '(tablet)' : '(desktop)' ) ),
            'browser'    => $browser->getBrowser() . ' ' . $browser->getVersion(),
            'user_agent' => $browser->getUserAgent(),
            'ip'         => self::get_ip(),
        );
    }
    public static function get_ip()
    {
        $ip = '127.0.0.1';

        if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
        {
            $ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
        }
        elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
        {
            $ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
        }
        elseif ( !empty( $_SERVER['REMOTE_ADDR'] ) )
        {
            $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
        }

        return $ip;
    }

} // class