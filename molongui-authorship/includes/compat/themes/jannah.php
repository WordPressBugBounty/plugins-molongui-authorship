<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'tie_author_box';

    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return $original_link;
    }

    return $link;
}, 10, 4 );