<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Common\Modules\Settings;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function authorship_get_byline( $pid = null, $separator = null, $last_separator = null, $linked = false )
{
    $byline = '';
    if ( is_null( $pid ) or !is_integer( $pid ) or !$pid )
    {
        $pid = Post::get_id();
        if ( !$pid )
        {
            return $byline;
        }
    }
    $post_authors = authorship_get_post_authors( $pid );
    if ( !$post_authors )
    {
        return $byline;
    }

    $count = count( $post_authors );
    $show_remaining = false;
    switch ( Settings::get( 'byline_multiauthor_display', 'all' ) )
    {
        case 'main':
            $names_to_display = 1;
            break;
        case '1':
            $names_to_display = 1;
            break;
        case '2':
            $names_to_display = min( 2, $count );
            break;
        case '3':
            $names_to_display = min( 3, $count );
            break;
        case 'all':
        default:
            $names_to_display = $count;
            break;
    }
    $byline_authors = $post_authors;
    if ( $names_to_display < $count )
    {
        $byline_authors = array_slice( $post_authors, 0, $names_to_display );
        $show_remaining = true;
    }
    list( $separator, $last_separator ) = authorship_get_byline_separators( $separator, $last_separator, $names_to_display );

    $i = 0;
    foreach ( $byline_authors as $author )
    {
        $divider = ( $i == 0 ? '' : ( $i == ( $names_to_display - 1 ) ? $last_separator : $separator ) );
        $author_class = new Author( $author->id, $author->type );
        if ( $linked )
        {
            $item = $author_class->get_link();
        }
        else
        {
            $item = esc_html( $author_class->get_name() );
        }
        $item = apply_filters( 'authorship/byline_item', $divider.$item, $item, $divider, $i, $author, $names_to_display );
        $byline .= $item;
        ++$i;
    }
    if ( $show_remaining and $count < $names_to_display )
    {
        $byline .= ' ' . sprintf( __( '%s %d more', 'molongui-authorship' ), $last_separator, $count - $names_to_display );
    }
    Debug::console_log( array('post_id' => $pid, 'post_authors' => $post_authors, 'names_to_display' => $names_to_display, 'separator' => $separator, 'last_separator' => $last_separator ), _x( "Byline information", 'debug message', 'molongui-authorship' ) );
    return apply_filters( 'authorship/post_byline', $byline, $pid, $post_authors );
}
if ( !function_exists( 'get_byline' ) )
{
    function get_byline( $pid = null, $separator = null, $last_separator = null, $linked = false )
    {
        return authorship_get_byline( $pid = null, $separator = null, $last_separator = null, $linked = false );
    }
}
function authorship_get_byline_separators( $separator = null, $last_separator = null, $count = null )
{
    $options = Settings::get();

    if ( is_null( $separator ) )
    {
        $separator = ( !empty( $options['byline_multiauthor_separator'] ) ? $options['byline_multiauthor_separator'] : ',' );
    }
    if ( is_null( $last_separator ) )
    {
        $last_separator = ( !empty( $options['byline_multiauthor_last_separator'] ) ? $options['byline_multiauthor_last_separator'] : __( 'and', 'molongui-authorship' ) );
    }
    $space = apply_filters( 'authorship/byline_separator_autospace', '__return_true' ) ? ' ' : '';

    $separator      = $separator.$space;
    $last_separator = $space.$last_separator.$space;
    $separator      = apply_filters( 'authorship/byline_separator', $separator, $count );
    $last_separator = apply_filters( 'authorship/byline_last_separator', $last_separator, $count );

    return array( $separator, $last_separator );
}
function authorship_add_byline_support()
{
    $add_byline_support = Settings::get( 'add_byline_support', true );
    if ( apply_filters( 'authorship/add_byline_support', $add_byline_support ) )
    {
        require_once MOLONGUI_AUTHORSHIP_DIR . 'includes/compat/byline.php';
    }
}
function authorship_byline_takeover()
{
    return ( authorship_is_feature_enabled( 'guest' ) or authorship_is_feature_enabled( 'multi' ) );
}