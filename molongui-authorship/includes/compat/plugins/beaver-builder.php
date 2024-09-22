<?php
defined( 'ABSPATH' ) or exit;
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn   = 'get_the_author_meta';
    $file = '/bb-plugin/modules/post-grid/includes/post-feed.php';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
        isset( $dbt[$key]['file'] ) and substr_compare( $dbt[$key]['file'], $file, strlen( $dbt[$key]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    ){
        return 'byline';
    }

    return $aim;
}, 10, 4 );