<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fns   = array( 'xprofile_filter_comments', 'bp_core_get_user_displaynames' );

    if ( array_intersect( $fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'molongui_authorship_dont_filter_the_author_display_name', function( $leave, $display_name, $user_id, $original_user_id, $post, $dbt )
{
    $fn = 'xprofile_filter_comments';
    if ( array_search( $fn, array_column( $dbt, 'function' ) ) ) return true;
    return false;
}, 10, 6 );
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fns = array( 'get_the_author_meta', 'bp_core_get_username', 'bp_core_get_user_domain' );
    if ( !array_search( 'comment_form', array_column( $dbt, 'function' ) ) )
    {
        return $user;
    }

    if ( array_intersect( $fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );