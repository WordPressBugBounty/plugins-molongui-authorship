<?php

use Molongui\Authorship\Common\Utils\Assets;
defined( 'ABSPATH' ) or exit;
function authorship_register_editor_styles()
{
    $file = apply_filters( 'authorship/editor/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/editor-rtl.98c7.min.css' : '/assets/css/editor.f6f4.min.css' ) );

    Assets::register_style( $file, 'editor' );
}
add_action( 'admin_enqueue_scripts', 'authorship_register_editor_styles' );
function authorship_enqueue_editor_styles()
{
    $file = apply_filters( 'authorship/editor/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/editor-rtl.98c7.min.css' : '/assets/css/editor.f6f4.min.css' ) );

    Assets::enqueue_style( $file, 'editor' );
}
function authorship_editor_extra_styles()
{
    $options = authorship_get_options();
    $css     = '';
    return apply_filters( 'authorship/editor/extra_styles', $css, $options );
}