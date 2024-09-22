<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_get_options()
{
    $options = (array) get_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array() );

    if ( empty( $options ) ) $options = authorship_get_defaults();
    $options = apply_filters( 'authorship/get_options', $options );

    return $options;
}
function authorship_get_defaults()
{
    $fw_options = array
    (
        'custom_css'  => '',
        'custom_php'  => '',
        'keep_config' => true,
        'keep_data'   => true,
    );

    return apply_filters( 'authorship/default_options', $fw_options );
}
function authorship_add_defaults()
{
    $options  = authorship_get_options();
    $defaults = authorship_get_defaults();
    update_option( MOLONGUI_AUTHORSHIP_PREFIX.'_options', array_merge( $defaults, $options ) );
}
function authorship_get_config()
{
    global $wpdb;
    $entries = $wpdb->get_results
    (
        $wpdb->prepare( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s", MOLONGUI_AUTHORSHIP_PREFIX.'_%' ),
        ARRAY_A
    );

    if ( !empty( $entries ) )
    {
        $options = array();
        foreach ( $entries as $entry ) $options[$entry['option_name']] = maybe_unserialize( $entry['option_value'] );
    }

    return empty( $options ) ? false : $options;
}