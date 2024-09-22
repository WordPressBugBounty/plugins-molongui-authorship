<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'authorship/sanitize_option', function( $sanitized_text_field, $key, $value )
{
    $dont_sanitize = array( 'custom_css', 'custom_php' );
    if ( in_array( $key, $dont_sanitize ) )
    {
        return $value;
    }

    return $sanitized_text_field;
}, 10, 3 );
add_action( 'authorship/options', 'authorship_add_defaults' );