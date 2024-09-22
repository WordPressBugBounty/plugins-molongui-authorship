<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fns = array
    (
        'wo_personal_data_erase_function',   //
        'wo_personal_data_export_function',  //
        'wpoauth_method_introspection',      //
        'wpoauth_method_me',                 //
    );

    if ( array_intersect( $fns, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    $fn    = 'getUser';
    $class = 'WPOAuth2\Storage\Wordpressdb';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    $fn   = 'getUser';
    $file = 'library/class-wo-api.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            return $original_user;
        }
    }

    return $user;
}, 10, 4 );