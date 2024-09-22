<?php
defined( 'ABSPATH' ) or exit;
add_filter( '_authorship/get_user_by/aim', function( $aim, $user, $field, $value )
{
    if ( is_author() or is_guest_author() )
    {
          $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn = 'generate_postdata';
        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            $aim = 'info';
        }
    }

    return $aim;
}, 10, 4 );
add_filter( 'authorship/author_link', function( $url, $args )
{
    if ( '#molongui-disabled-link' !== $args['url'] ) return $url;
    $fn_1  = 'get_author_posts_url';
    $fn_2  = 'process';
    $class = 'RankMath\Schema\Author';
    $file  = 'seo-by-rank-math/includes/modules/schema/snippets/class-author.php';
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

    if ( empty( $dbt ) ) return $url;
    if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
    {
        if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
        {
            if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
            {
                $url = '';
            }
        }
    }

    return $url;
}, 10, 2 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn_1  = 'get_author_posts_url';
    $fn_2  = 'canonical';
    $class = 'RankMath\Paper\Author';

    if ( !is_guest_author() )
    {
        if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
        {
            if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
            {
                if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
                {
                    $link = $original_link;
                }
            }
        }
    }

    return $link;
}, 10, 4 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn_1  = 'get_author_posts_url';
    $fn_2  = 'process';
    $class = 'RankMath\Schema\Author';

    if ( !is_guest_author() )
    {
        if ( $j = array_search( $fn_1, array_column( $dbt, 'function' ) ) )
        {
            if ( $i = array_search( $fn_2, array_column( $dbt, 'function' ) ) )
            {
                if ( isset( $dbt[$i]['class'] ) and $dbt[$i]['class'] == $class )
                {
                    $link = $original_link;
                }
            }
        }
    }

    return $link;
}, 10, 4 );