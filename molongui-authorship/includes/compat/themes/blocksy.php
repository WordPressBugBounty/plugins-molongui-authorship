<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'get_the_author_display_name', function( $default )
{
    $i    = 3;
    $fn   = 'get_the_author_meta';
    $file = '/blocksy/inc/components/hero/elements.php';
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );
    if ( isset( $dbt[$i]['function'] ) and ( $dbt[$i]['function'] == $fn ) and
         isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    ) {
        return Author_Filters::filter_the_archive_title( $default );
    }
    return $default;
}, 10, 2 );
add_filter( 'get_the_author_user_description', function( $default )
{
    $i    = 3;
    $fn   = 'get_the_author_meta';
    $file = '/blocksy/inc/components/hero/elements.php';
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 4 );
    if ( isset( $dbt[$i]['function'] ) and ( $dbt[$i]['function'] == $fn ) and
         isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    ) {
        return Author_Filters::filter_the_archive_description( $default );
    }
    return $default;
}, 10, 2 );