<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Author_Filters;
use Molongui\Authorship\Common\Utils\Request;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( Request::is_from( 'admin' ) )
{
    return;
}
$options = Settings::get();
if ( !empty( $options['opengraph_meta_enabled'] ) )
{
    add_filter( 'wpseo_opengraph_type' , 'authorship_wpseo_remove_opengraph', 999, 1 );
    add_filter( 'wpseo_opengraph_desc' , 'authorship_wpseo_remove_opengraph', 999, 1 );
    add_filter( 'wpseo_opengraph_url'  , 'authorship_wpseo_remove_opengraph', 999, 1 );
    add_filter( 'wpseo_opengraph_image', 'authorship_wpseo_remove_opengraph', 999, 1 );
    if ( !function_exists( 'authorship_wpseo_remove_opengraph' ) )
    {
        function authorship_wpseo_remove_opengraph( $value )
        {
            if ( is_author() or molongui_is_guest_author() ) return false;
            return $value;
        }
    }
}
add_filter( 'wpseo_schema_author'      , 'molongui_authorship_wpseo_schema_author'   , 999, 2 );
add_filter( 'wpseo_replacements'       , 'molongui_authorship_wpseo_replacements'    , 999, 2 );
add_filter( 'wpseo_canonical'          , 'molongui_authorship_wpseo_canonical'       , 999, 1 );
add_filter( 'wpseo_adjacent_rel_url'   , 'molongui_authorship_wpseo_adjacent_rel_url', 999, 2 );
add_filter( 'wpseo_opengraph_url'      , 'molongui_authorship_wpseo_opengraph_url'   , 999, 1 );
function molongui_authorship_wpseo_schema_author( $graph_piece, $context = null )
{
    if ( is_null( $context ) )
    {
        global $post;
        $pid = $post->ID;
    }
    else
    {
        $pid = $context->indexable->object_id;
    }
    $post_authors = Post::get_authors( $pid );
    if ( !$post_authors or !is_array( $post_authors ) )
    {
        return $graph_piece;
    }
    if ( is_array( $post_authors ) and count( $post_authors ) <= 1 )
    {
        return $graph_piece;
    }
    $authors = array();
    $i = 0;
    foreach ( $post_authors as $post_author )
    {
        $author = new Author( $post_author->id, $post_author->type );

        $authors[$i]['@type'] = array( 'Person' );
        $authors[$i]['@id']   = $graph_piece['@id']; //'https://evistaging.wpengine.com/#/schema/person/682e44182a7c6f403c727e43c0b612a5';
        $authors[$i]['name']  = $author->get_display_name();
        $authors[$i]['image'] = array
        (
            '@type'      => 'ImageObject',
            '@id'        => isset( $graph_piece['image']['@id'] ) ? $graph_piece['image']['@id'] : '', //'https://evistaging.wpengine.com/#personlogo',
            'inLanguage' => get_locale(),
            'url'        => $author->get_avatar( 'full', 'url' ),
            'caption'    => $author->get_display_name(),
        );

        ++$i;
    }

    return $authors;
}
function molongui_authorship_wpseo_replacements( $replacements, $args = null )
{
    if ( !is_author() ) return $replacements;
    if ( molongui_is_guest_author() ) return $replacements;
    if ( isset( $replacements['%%name%%'] ) )
    {
        $author = new Molongui\Authorship\Author( get_query_var( 'author', 0 ), 'user' );
        $replacements['%%name%%'] = $author->get_display_name();
    }
    return $replacements;
}
function molongui_authorship_wpseo_canonical( $canonical )
{
    if ( !is_author() and !molongui_is_guest_author() ) return $canonical;
    $canonical = molongui_authorship_get_actual_author_data( 'url', $canonical );
    return $canonical;
}
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'generate_canonical';

    if ( ( is_author() or molongui_is_guest_author() ) and
         $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        $link = Author_Filters::the_author_page_link( $original_link );
    }

    return $link;
}, 10, 4 );
function molongui_authorship_wpseo_adjacent_rel_url( $url, $rel )
{
    if ( !is_author() and !molongui_is_guest_author() ) return $url;
    if ( !Post::is_multiauthor_link( $url ) ) return $url;
    if ( substr( $url, -1 ) == '/' )
    {
        $parts = explode( "/", substr( $url, 0, -1 ) );
        $trailing = '/';
    }
    else
    {
        $parts = explode( "/", $url );
        $trailing = '';
    }
    $page = array_pop( $parts );
    $query_arg = array_pop( $parts );
    $url = molongui_authorship_get_actual_author_data( 'url', $url );
    $url = $url.( substr( $url, -1 ) == '/' ? '' : '/' ).$query_arg.'/'.$page.$trailing;
    return $url;
}
function molongui_authorship_wpseo_opengraph_url( $url )
{
    if ( !is_author() and !molongui_is_guest_author() ) return $url;
    $url = molongui_authorship_get_actual_author_data( 'url', $url );
    return $url;
}
function molongui_authorship_get_actual_author_data( $key, $default )
{
    if ( molongui_is_guest_author() )
    {
        if ( $guest_name = get_query_var( 'guest-author-name', 0 ) )
        {
            $guest = Molongui\Authorship\Authors::get_author_by( 'name', $guest_name, 'guest', false );
            if ( $guest instanceof Author )
            {
                switch ( $key )
                {
                    case 'url':
                        return $guest->get_archive_url();
                        break;

                    case 'img':
                        return $guest->get_avatar();
                        break;
                }
            }
        }
    }
    elseif ( 'url' == $key )
    {
        if ( Post::is_multiauthor_link( ( $default ) ) )
        {
            $author = new Molongui\Authorship\Author( get_query_var( 'author', 0 ), 'user' );
            return $author->get_archive_url();
        }
    }
    return $default;
}
add_filter( 'wpseo_breadcrumb_links', function( $crumbs )
{

    if ( is_author() or molongui_is_guest_author() )
    {
        $prefix = \WPSEO_Options::get( 'breadcrumbs-archiveprefix' );
        $prefix = empty( $prefix ) ? '' : $prefix . ' ';
        $last = key( array_slice( $crumbs, -1, 1, true ) ); //$last = array_key_last( $crumbs );

        if ( molongui_is_guest_author() and !in_the_loop() )
        {
            if ( $author = get_query_var( 'guest-author-name', 0 ) )
            {
                $guest = Molongui\Authorship\Authors::get_author_by( 'name', $author, 'guest', false );

                if ( $guest instanceof Author )
                {
                    $crumbs[$last]['text'] = $prefix . esc_html( $guest->get_display_name() );
                }
            }
        }
        else
        {
            $author = new Molongui\Authorship\Author( get_query_var( 'author', 0 ), 'user' );
            $crumbs[$last]['text'] = $prefix . esc_html( $author->get_display_name() );
        }
    }

    return $crumbs;
});
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn    = 'get_sitemap_links';
    $class = 'WPSEO_Author_Sitemap_Provider';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] == $class ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );
if ( !function_exists( 'authorship_wpseo_schema_graph_pieces' ) )
{
    function authorship_wpseo_schema_graph_pieces( $pieces, $context )
    {
        return $pieces;
    }
}
if ( !function_exists( 'authorship_wpseo_opengraph_img' ) )
{
    function authorship_wpseo_opengraph_img( $url )
    {
        if ( !is_author() and !molongui_is_guest_author() ) return $url;
        $url = molongui_authorship_get_actual_author_data( 'img', $url );
        return $url;
    }
}
