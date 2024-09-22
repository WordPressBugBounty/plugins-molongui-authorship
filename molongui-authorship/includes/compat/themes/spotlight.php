<?php
defined( 'ABSPATH' ) or exit;
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn  = 'csco_get_post_meta';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) ) $aim = 'byline';
    return $aim;
}, 10, 4 );