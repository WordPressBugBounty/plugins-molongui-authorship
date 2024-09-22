<?php
defined( 'ABSPATH' ) or exit;
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    if ( is_author() or is_guest_author() )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn    = 'get_author';
        $class = 'td_module';

        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
        {
            $aim = 'byline';
        }
    }

    return $aim;
}, 10, 4 );

add_filter( '_authorship/get_user_by/post_id', function( $post_id, $user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 10 );
    $fn    = 'get_author';
    $class = 'td_module';

    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {

        if ( isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class )
             and
             isset( $dbt[$key]['object'] ) and isset( $dbt[$key]['object']->post ) and isset( $dbt[$key]['object']->post->ID ) )
        {
            return $dbt[$key]['object']->post->ID;
        }
    }
    return $post_id;
}, 10, 4 );
add_filter( 'molongui_edit_main_query_only', function( $default, &$query )
{
    if ( !$query->is_author() ) return $default;
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
    if ( empty( $dbt ) ) return $default;
    $fn    = 'render';
    $class = 'td_block';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
    {
        return false;
    }
    return $default;
}, 10, 2 );