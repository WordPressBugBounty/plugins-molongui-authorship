<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_get_client_data()
{
    $browser = new \Molongui\Authorship\Common\Libraries\Browser();

    return array
    (
        'platform'   => $browser->getPlatform() . ' ' . ( $browser->isMobile() ? '(mobile)' : ( $browser->isTablet() ? '(tablet)' : '(desktop)' ) ),
        'browser'    => $browser->getBrowser() . ' ' . $browser->getVersion(),
        'user_agent' => $browser->getUserAgent(),
        'ip'         => authorship_get_client_ip(),
    );
}
function authorship_get_client_ip()
{
    $ip = '127.0.0.1';

    if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
    {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    elseif ( !empty( $_SERVER['REMOTE_ADDR'] ) )
    {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
}