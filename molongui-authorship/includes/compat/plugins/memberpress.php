<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'get_user_by';
    $class = 'MeprUtils';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $class = array( 'MeprUser', 'MeprLoginCtrl', 'MeprAppCtrl' );

    if ( array_intersect( $class, array_column( $dbt, 'class' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
if ( is_plugin_active( 'memberpress-corporate/main.php' ) )
{
    add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
    {
        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn    = 'current_user_has_access';
        $class = 'MPCA_Corporate_Account';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
             and
             isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
        {
            return $original_user;
        }

        return $user;
    }, 10, 4 );
}