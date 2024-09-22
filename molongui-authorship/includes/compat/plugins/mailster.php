<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $dir = WP_PLUGIN_DIR . '/mailster/';

    $files = array_column( $dbt, 'file' );
    foreach ( $files as $file )
    {
        if ( substr_compare( $file, $dir, 0, strlen( $dir ) ) === 0 )
        {
            return $original_user;
        }
    }

    return $user;
}, 10, 4 );