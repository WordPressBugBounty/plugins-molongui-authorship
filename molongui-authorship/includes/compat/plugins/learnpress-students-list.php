<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fns   = array( 'single_course_students_list_tab_content', 'output', 'widget' );
    $class = array( 'LP_Addon_Students_List', 'LP_Students_List_Shortcode', 'LP_Students_List' );

    foreach ( $fns as $fn )
    {
        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$i]['class'] ) and ( in_array( $dbt[$i]['class'], $class ) ) )
        {
            return $original_user;
        }
    }

    return $user;
}, 10, 4 );