<?php

use Molongui\Authorship\Common\Utils\Assets;
defined( 'ABSPATH' ) or exit;
function authorship_register_editor_styles()
{
    $file = apply_filters( 'authorship/editor/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/editor-rtl.fcd7.min.css' : '/assets/css/editor.6bb0.min.css' ) );

    Assets::register_style( $file, 'editor' );
}
function authorship_enqueue_editor_styles()
{
    $file = apply_filters( 'authorship/editor/styles', MOLONGUI_AUTHORSHIP_FOLDER . ( is_rtl() ? '/assets/css/editor-rtl.fcd7.min.css' : '/assets/css/editor.6bb0.min.css' ) );

    Assets::enqueue_style( $file, 'editor' );
}
function authorship_editor_extra_styles()
{
    $options = authorship_get_options();
    $css     = '';
    return apply_filters( 'authorship/editor/extra_styles', $css, $options );
}