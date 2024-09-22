<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_register_options_scripts()
{
    do_action( 'authorship/options/enqueue_required_deps' );
    authorship_enqueue_semantic();
    Assets::enqueue_sweetalert();
    $deps = apply_filters( 'authorship/options/script_deps', array() );
    $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );

    Assets::register_script( $file, 'options', $deps );
}
add_action( 'admin_enqueue_scripts', 'authorship_register_options_scripts' );
function authorship_enqueue_options_scripts()
{
    $file = apply_filters( 'authorship/options/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/common/options.xxxx.min.js' );

    Assets::enqueue_script( $file, 'options', true );
}
function authorship_options_script_params()
{
    $params = array
    (
        'plugin_id'      => MOLONGUI_AUTHORSHIP_PREFIX,
        'plugin_version' => MOLONGUI_AUTHORSHIP_VERSION,
        'is_pro'         => did_action( 'authorship_pro/loaded' ),
        'options_page'   => esc_url( admin_url( 'admin.php?page=' . MOLONGUI_AUTHORSHIP_NAME . '&tab=' . MOLONGUI_AUTHORSHIP_PREFIX . '_pro_' . 'license' ) ),
        'cm_settings' => array
        (
            'custom_css' => wp_enqueue_code_editor( array( 'type' => 'text/css', 'codemirror' => array( 'mode' => 'css', 'autofocus' => true ) ) ),
            'custom_php' => wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php', 'codemirror' => array( 'mode' => 'php', 'autofocus' => true ) ) ),
        ),
        1 => __( "Premium feature", 'molongui-authorship' ),
        2 => __( "This feature is available only for Premium users. Upgrade to Premium to unlock it!", 'molongui-authorship' ),
        10001 => '', // unused?
        10002 => __( "Saving", 'molongui-authorship' ),
        10003 => __( "You are about to leave this page without saving. All changes will be lost.", 'molongui-authorship' ),
        10004 => __( "WARNING: You are about to delete all your settings! Please confirm this action.", 'molongui-authorship' ),
        10005 => MOLONGUI_AUTHORSHIP_PREFIX.'_',
        10006 => __( "WARNING: You are about to restore your backup. This will overwrite all your settings! Please confirm this action.", 'molongui-authorship' ),
        10007 => __( "WARNING: You are about to delete your backup. All unsaved options will be lost. We recommend that you save your options before deleting a backup. Please confirm this action.", 'molongui-authorship' ),
        10008 => __( "WARNING: You are about to create a backup. All unsaved options will be lost. We recommend that you save your options before deleting a backup. Please confirm this action.", 'molongui-authorship' ),
        10009 => __( "Delete", 'molongui-authorship' ),
        10010 => MOLONGUI_AUTHORSHIP_PREFIX,
        10011 => wp_create_nonce( 'mfw_import_options_nonce' ),
        10012 => __( "File upload failed", 'molongui-authorship' ),
        10013 => __( "Failed to load file.", 'molongui-authorship' ),
        10014 => __( "Wrong file type", 'molongui-authorship' ),
        10015 => __( "Only valid .JSON files are accepted.", 'molongui-authorship' ),
        10016 => __( "Warning", 'molongui-authorship' ),
        10017 => __( "You are about to restore your settings. This will overwrite all your existing configuration! Please confirm this action.", 'molongui-authorship' ),
        10018 => __( "Cancel", 'molongui-authorship' ),
        10019 => __( "OK", 'molongui-authorship' ),
        10020 => __( "Success!", 'molongui-authorship' ),
        10021 => __( "Plugin settings have been imported successfully. Click on the OK button and the page will be reloaded automatically.", 'molongui-authorship' ),
        10022 => __( "Error", 'molongui-authorship' ),
        10023 => __( "Something went wrong and plugin settings couldn't be restored. Please, make sure uploaded file has content and try uploading the file again.", 'molongui-authorship' ),
        10024 => sprintf( __( "Either the uploaded backup file is for another plugin or it is from a newer version of the plugin. Please, make sure you are uploading a file generated with %s version lower or equal to %s.", 'molongui-authorship' ), MOLONGUI_AUTHORSHIP_TITLE, MOLONGUI_AUTHORSHIP_VERSION ),
        10025 => __( "Some settings couldn't be restored. Please, try uploading the file again.", 'molongui-authorship' ),
        10026 => __( "You are about to restore plugin default settings. This will overwrite all your existing configuration! Please confirm this action.", 'molongui-authorship' ),
        10027 => wp_create_nonce( 'mfw_reset_options_nonce' ),
        10028 => __( "Plugin settings have been restored to defaults successfully. Click on the OK button and the page will be reloaded automatically.", 'molongui-authorship' ),
        10029 => __( "Something went wrong and plugin defaults couldn't be restored. Please, try again.", 'molongui-authorship' ),
        10030 => __( "Something went wrong and couldn't connect to the server. Please, try again.", 'molongui-authorship' ),
        20000 => wp_create_nonce( 'mfw_license_nonce' ),
        20001 => __( "Something is missing...", 'molongui-authorship' ),
        20002 => __( "You need to provide both values, License Key and PIN", 'molongui-authorship' ),
        20003 => __( "Activated!", 'molongui-authorship' ),
        20004 => __( "Oops... activation failed", 'molongui-authorship' ),
        20005 => __( "Oops!", 'molongui-authorship' ),
        20006 => __( "Something went wrong and the license has not been activated.", 'molongui-authorship' ),
        20007 => __( "Deactivate license", 'molongui-authorship' ),
        20008 => __( "Submit to deactivate your license now", 'molongui-authorship' ),
        20009 => __( "No, cancel!", 'molongui-authorship' ),
        20010 => __( "Yes, deactivate it!", 'molongui-authorship' ),
        20011 => __( "Deactivated!", 'molongui-authorship' ),
        20012 => __( "Oops... something weird happened!", 'molongui-authorship' ),
        20013 => __( "Something went wrong and the license has not been deactivated.", 'molongui-authorship' ),
        20014 => __( "Activate", 'molongui-authorship' ),
        20015 => __( "Deactivate", 'molongui-authorship' ),
        20016 => __( "Error" ),
        20017 => __( "License PIN must contain only digits", 'molongui-authorship' ),
    );
    return apply_filters( 'authorship/options/script_params', $params );
}
function authorship_menu_target_blank()
{
    ob_start();
    ?>
    <script type="text/javascript">
        (function($)
        {
            $( 'a[href="https://www.molongui.com/help/docs/"]' ).attr( 'target', '_blank' );
            $( 'a[href="https://demos.molongui.com/"]' ).attr( 'target', '_blank' );
        })( jQuery );
    </script>
    <?php
    echo preg_replace( '/\s+/S', ' ', ob_get_clean() );
}
add_action( 'admin_footer', 'authorship_menu_target_blank' );
add_filter( 'authorship/option/textarea', function( $output, $option )
{


    return $output;
}, 10, 2 );