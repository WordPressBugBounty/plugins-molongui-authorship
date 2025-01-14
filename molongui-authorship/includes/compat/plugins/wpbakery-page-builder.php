<?php

use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_action( 'init', function()
{
    remove_filter( 'vc_gitem_template_attribute_post_author', 'vc_gitem_template_attribute_post_author', 10 );
    remove_filter( 'vc_gitem_template_attribute_post_author_href', 'vc_gitem_template_attribute_post_author_href', 10 );
}, 999 );
add_filter( 'vc_gitem_template_attribute_post_author', function( $value, $data )
{
    extract( array_merge( array
    (
        'post' => null,
        'data' => '',
    ), $data ) );

    if ( !empty( $post->ID ) )
    {
        return Post::get_byline( $post->ID, '', '', false );
    }
    return $value;

}, 999, 2 );
add_filter( 'vc_gitem_template_attribute_post_author_href', function( $value, $data )
{
    extract( array_merge( array
    (
        'post' => null,
        'data' => '',
    ), $data ) );

    if ( isset( $post->ID ) and $post->ID )
    {
        $main = Post::get_main_author( $post->ID );
        $author = new Molongui\Authorship\Author( $main->id, $main->type );
        return $author->get_archive_url();
    }
    return $value;

}, 999, 2 );