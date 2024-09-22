<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'thrive_comments';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'molongui_authorship_dont_filter_the_author_display_name', function( $leave, $display_name, $user_id, $original_user_id, $post, $dbt )
{
    if ( $leave ) return $leave;
    if ( isset( $dbt[5]['function'] ) and $dbt[5]['function'] == 'thrive_comments' ) return true;
    return false;
}, 10, 6 );