<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    if ( !is_author() and !is_guest_author() ) return $user;

    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'get_author_name';
    $class = 'FLPageDataPost';
    $file  = '/bb-theme-builder/classes/class-fl-page-data.php';

    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) /*and
         isset( $dbt[$key]['file'] ) and substr_compare( $dbt[$key]['file'], $file, strlen( $dbt[$key]['file'] )-strlen( $file ), strlen( $file ) ) === 0 */ )
    {
        return $user;
    }

    return $user;
}, 10, 4 );
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    if ( is_author() or is_guest_author() )
    {
        $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn   = 'get_the_author_meta';
        $file = 'bb-plugin/modules/post-grid/includes/post-feed.php';

        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
            isset( $dbt[$key]['file'] ) and substr_compare( $dbt[$key]['file'], $file, strlen( $dbt[$key]['file'] )-strlen( $file ), strlen( $file ) ) === 0
        ){
            $aim = 'byline';
        }
    }

    return $aim;
}, 10, 4 );
/*
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );
    $fn   = 'do_shortcode';
    $file = 'bb-theme-builder/modules/fl-author-bio/includes/frontend.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'authorship/get_avatar_data/skip', function( $default, $args, $dbt )
{
    $fn   = 'do_shortcode';
    $file = 'bb-theme-builder/modules/fl-author-bio/includes/frontend.php';
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );

    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
        isset( $dbt[$key]['file'] ) and substr_compare( $dbt[$key]['file'], $file, strlen( $dbt[$key]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    ){
        return true;
    }

    return $default;
}, 10, 3 );
*/