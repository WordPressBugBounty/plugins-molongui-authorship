<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'render';
    $class = array( 'Dynamic_News_Category_Posts_Single_Widget', 'Dynamic_News_Category_Posts_Boxed_Widget', 'Dynamic_News_Category_Posts_Columns_Widget', 'Dynamic_News_Category_Posts_Grid_Widget' );

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( in_array( $dbt[$i]['class'], $class ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );