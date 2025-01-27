<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_authorship/display_author_box', function( $default )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'shortcode_tab';
    if ( in_the_loop() )
    {
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            return false;
        }
    }

    return $default;
});
