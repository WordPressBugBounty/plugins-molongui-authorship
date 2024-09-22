<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'widget';
    $class = 'ct_mission_news_post_list';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );