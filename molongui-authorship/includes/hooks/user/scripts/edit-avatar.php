<?php

use Molongui\Authorship\Common\Utils\Assets;
defined( 'ABSPATH' ) or exit;
function authorship_register_edit_avatar_scripts()
{
    $file = apply_filters( 'authorship/edit_avatar/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-avatar.a05b.min.js' );

    Assets::register_script( $file, 'edit_avatar' );
}
add_action( 'admin_enqueue_scripts', 'authorship_register_edit_avatar_scripts' );
function authorship_enqueue_edit_avatar_scripts()
{
    $file = apply_filters( 'authorship/edit_avatar/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-avatar.a05b.min.js' );

    Assets::enqueue_script( $file, 'edit_avatar', true );
}
function authorship_edit_avatar_script_params()
{
    $params = array
    (
        'remove' => __( "Remove", 'molongui-authorship' ),
        'edit'   => __( "Edit", 'molongui-authorship' ),
        'upload' => __( "Upload Avatar", 'molongui-authorship' ),
    );
    return apply_filters( 'authorship/edit_avatar/script_params', $params );
}