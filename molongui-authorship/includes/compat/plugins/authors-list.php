<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    if ( defined( 'AUTHORS_LIST_VERSION' ) and version_compare( AUTHORS_LIST_VERSION,'2.0.0', '<' ) )
    {
        $fn = 'authors_list_sc';
    }
    else
    {
        $fn = 'shortcode_content';
    }

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'authorship/get_the_author_description/skip', function ( $default, $description, $user_id, $original_user_id )
{
    if ( defined( 'AUTHORS_LIST_VERSION' ) and version_compare( AUTHORS_LIST_VERSION,'2.0.0', '<' ) )
    {
        $fn = 'authors_list_sc';
    }
    else
    {
        $fn = 'shortcode_content';
    }

    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) ) return true;
    return $default;

}, 10, 4 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    if ( defined( 'AUTHORS_LIST_VERSION' ) and version_compare( AUTHORS_LIST_VERSION,'2.0.0', '<' ) )
    {
        $fn = 'authors_list_sc';
    }
    else
    {
        $fn = 'shortcode_content';
    }

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );
add_filter( '_authorship/post_count/author', function( $data, $count, $userid, $post_type, $public_only )
{
    list( $author_id, $author_type ) = $data;

    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
    if ( defined( 'AUTHORS_LIST_VERSION' ) and version_compare( AUTHORS_LIST_VERSION,'2.0.0', '<' ) )
    {
        $fn = 'authors_list_sc';
    }
    else
    {
        $fn = 'shortcode_content';
    }

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        $author_id   = $userid;
        $author_type = 'user';
    }
    return array( $author_id, $author_type );

}, 10, 5 );