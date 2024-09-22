<?php

use Molongui\Authorship\Common\Utils\WP;
use Molongui\Authorship\Common\Modules\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_save_options()
{
    if ( !WP::verify_nonce( 'mfw_save_options_nonce', 'nonce' ) )
    {
        echo 'false';
        wp_die();
    }
    if ( !current_user_can( 'manage_options' ) ) return;
    $options = wp_unslash( $_POST['data'] );
    foreach ( $options as $key => $value )
    {
        $options[$key] = apply_filters( 'authorship/sanitize_option', sanitize_text_field( $value ), $key, $value );
    }

    if ( isset( $options ) and is_array( $options ) )
    {
        $options['plugin_version'] = MOLONGUI_AUTHORSHIP_VERSION;
        $current = (array) get_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array() );
        $options = array_merge( $current, $options );
        $options = apply_filters( 'authorship/validate_options', $options, $current );
        update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', $options );

        $old = $current;
        do_action( 'authorship/options', $options, $old );
    }
    wp_die();
}
add_action( 'wp_ajax_'.MOLONGUI_AUTHORSHIP_PREFIX.'_save_options', 'authorship_save_options' );
function authorship_export_options()
{
    if ( !WP::verify_nonce( 'mfw_export_options_nonce', 'nonce' ) )
    {
        echo 'false';
        wp_die();
    }
    if ( !current_user_can( 'manage_options' ) ) return;
    $options = Settings::get_config();
    $options['plugin_id']      = MOLONGUI_AUTHORSHIP_PREFIX;
    $options['plugin_version'] = MOLONGUI_AUTHORSHIP_VERSION;
    $options = apply_filters( 'authorship/export_options', $options );
    echo wp_json_encode( $options );
    wp_die();
}
add_action( 'wp_ajax_'.MOLONGUI_AUTHORSHIP_PREFIX.'_export_options', 'authorship_export_options' );