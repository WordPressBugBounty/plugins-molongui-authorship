<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) || exit;  






add_filter( 'authorship/pre_the_author_posts_link', function( $link, $original_link )
{
    $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn   = 'penci_get_the_author_posts_link';
    $file = '/themes/soledad/inc/templates/about_author.php';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            $link = $original_link;
        }
    }

    return $link;
}, 10, 2 );

add_filter( 'molongui_authorship_do_filter_name', function( $leave, &$args )
{
    if ( $leave ) return $leave;

    $fn   = 'get_the_author';
    $file = '/themes/soledad/author.php';
    $dbt  = $args['dbt'];


    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0
    )
    {
        $args['display_name'] = Author_Filters::filter_the_archive_title( $args['display_name'] );

        return true;
    }

    return false;
}, 10, 2 );

add_filter( 'authorship/get_avatar_data/skip', function( $default, $avatar, $dbt )
{
    if ( is_author() and !molongui_is_guest_author() ) return true;

    return false;
}, 10, 3 );
