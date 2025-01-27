<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'molongui_authorship/display_author_box', function( $default )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'publisher_inject_location';
    if ( in_the_loop() )
    {
        if ( array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            return false;
        }
    }

    return $default;
}, 10, 1 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn    = 'add_user_archive_items';
    $class = 'BF_Breadcrumb';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );
add_filter( 'authorship/byline/dom_tree', function()
{
   $dom_tree = '<i class="post-author author">{%ma_authorName}</i>';

   return $dom_tree;
});
add_filter( 'authorship/get_avatar_data/skip', function( $default, $args, $dbt )
{
    $fn   = 'get_avatar';
    $file = '/publisher/views/general/shortcodes/bs-login.php';
    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            return true;
        }
    }
    return $default;
}, 10, 3 );
