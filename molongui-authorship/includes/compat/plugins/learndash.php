<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn   = 'get_userdata';
    $file = '/sfwd-lms/themes/ld30/templates/focus/masthead.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            return $original_user;
        }
    }

    return $user;
}, 10, 4 );
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn  = 'learndash_notifications_shortcode_init';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );