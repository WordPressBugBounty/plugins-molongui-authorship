<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'molongui_edit_main_query_only', function( $default, &$wp_query )
{
    if ( $wp_query->is_author and wp_doing_ajax() )
    {
            return false;
    }
    return $default;

}, 10, 2 );


