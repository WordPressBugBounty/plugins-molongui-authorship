<?php

use Molongui\Authorship\Common\Utils\Assets;
defined( 'ABSPATH' ) or exit;
function authorship_register_editor_scripts()
{
    $file = apply_filters( 'authorship/editor/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/editor.f1ce.min.js' );

    Assets::register_script( $file, 'editor' );
}
function authorship_enqueue_editor_scripts()
{
    $file = apply_filters( 'authorship/editor/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/editor.f1ce.min.js' );

    Assets::enqueue_script( $file, 'editor', true );
}
function authorship_editor_script_params()
{
    $params = array
    (
    );
    return apply_filters( 'authorship/editor/script_params', $params );
}