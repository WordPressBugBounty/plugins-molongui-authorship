<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_register_support_scripts()
{
    $file  = apply_filters( 'authorship/support/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/support.3f45.min.js' );
    $deps  = array();
    $scope = 'support';

    Assets::register_script( $file, $scope, $deps, 'molongui_common_support' );
    add_action( "authorship/{$scope}/pre_enqueue_script", function()
    {
        authorship_enqueue_semantic();
        Assets::enqueue_sweetalert();
    });
}
add_action( 'admin_enqueue_scripts', 'authorship_register_support_scripts' );
function authorship_enqueue_support_scripts()
{
    $file  = apply_filters( 'authorship/support/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/support.3f45.min.js' );
    $scope = 'support';

    Assets::enqueue_script( $file, $scope, true, 'molongui_common_support' );
}
function authorship_support_script_params()
{
    $scope  = 'support';
    $params = array();
    return apply_filters( "authorship/{$scope}/params", $params );
}