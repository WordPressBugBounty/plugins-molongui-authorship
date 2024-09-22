<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'molongui_edit_main_query_only', function( $default, &$query )
{
    if ( !$query->is_author() ) return $default;
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 11 );
    if ( empty( $dbt ) ) return $default;
    $fn    = 'build';
    $class = 'UncodeLoopQueryBuilder';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
    {
        return false;
    }
    return $default;
}, 10, 2 );