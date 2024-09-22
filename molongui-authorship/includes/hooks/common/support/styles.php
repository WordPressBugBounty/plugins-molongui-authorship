<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_register_support_styles()
{
    $file = apply_filters( 'authorship/support/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/support-rtl.f5c9.min.css' : '/assets/css/common/support.f7d2.min.css' ) );
    $deps = array();

    Assets::register_style( $file, 'support', $deps );
}
add_action( 'admin_enqueue_scripts', 'authorship_register_support_styles' );
function authorship_enqueue_support_styles()
{
    $file = apply_filters( 'authorship/support/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/support-rtl.f5c9.min.css' : '/assets/css/common/support.f7d2.min.css' ) );

    Assets::enqueue_style( $file, 'support', true );
}
function authorship_support_extra_styles()
{
    $css = '';
    $css .= molongui_get_admin_color();
    return apply_filters( 'authorship/support/extra_styles', $css );
}