<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_the_author_posts_link', function( $link, $original_link )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn   = 'the_author_posts_link';
    $file = '/themes/creativo/archive.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            $link = $original_link;
        }
    }

    return $link;
}, 10, 2 );