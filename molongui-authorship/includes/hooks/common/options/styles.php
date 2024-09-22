<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_register_common_options_styles()
{
    if ( apply_filters( 'authorship/options/enqueue_colorpicker', false ) ) wp_enqueue_style( 'wp-color-picker' );
    $file = apply_filters( 'authorship/options/common_styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/options-rtl.aeec.min.css' : '/assets/css/common/options.c589.min.css' ) );
    $deps = array();

    Assets::register_style( $file, 'common_options', $deps );
}
add_action( 'admin_enqueue_scripts', 'authorship_register_common_options_styles' );
function authorship_enqueue_common_options_styles()
{
    $file = apply_filters( 'authorship/options/common_styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/common/options-rtl.aeec.min.css' : '/assets/css/common/options.c589.min.css' ) );

    Assets::enqueue_style( $file, 'common_options', true );
}
function authorship_common_options_extra_styles()
{
    $css = '';
    $css .= molongui_get_admin_color();
    return apply_filters( 'authorship/options/common_extra_styles', $css );
}