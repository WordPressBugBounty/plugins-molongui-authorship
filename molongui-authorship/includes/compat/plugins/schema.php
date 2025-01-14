<?php

use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'schema_wp_author', function( $author )
{
    global $post;
    if ( !Post::has_multiple_authors( $post->ID ) )
    {
        return $author;
    }
    $post_authors = Post::get_authors( $post->ID );
    $authors = array();
    foreach ( $post_authors as $post_author )
    {
        $author_class = new \Molongui\Authorship\Author( $post_author->id, $post_author->type );
        $url_enable = function_exists( 'schema_wp_get_option' ) ? schema_wp_get_option( 'author_url_enable' ) : true;
        $url 		= $url_enable ? esc_url( $author_class->get_archive_url() ) : '';

        $author = array
        (
            '@type'	=> 'Person',
            'name'	=> $author_class->get_name(),
            'url'	=> $url
        );

        if ( $description = $author_class->get_description() )
        {
            $author['description'] = strip_tags( $description );
        }
        $gravatar_enable = function_exists( 'schema_wp_get_option' ) ? schema_wp_get_option( 'gravatar_image_enable' ) : true;

        if ( $gravatar_enable )
        {
            $image_size	= apply_filters( 'schema_wp_get_author_array_img_size', 96 );

            $image_url	= $author_class->get_avatar( $image_size, 'url' );

            if ( $image_url )
            {
                $author['image'] = array
                (
                    '@type'		=> 'ImageObject',
                    'url' 		=> $image_url,
                    'height' 	=> $image_size,
                    'width' 	=> $image_size
                );
            }
        }
        $website 	= esc_attr( stripslashes( $author_class->get_website() ) );
        $facebook 	= esc_attr( stripslashes( $author_class->get_meta( 'facebook' ) ) );
        $twitter 	= esc_attr( stripslashes( $author_class->get_meta( 'twitter' ) ) );
        $instagram 	= esc_attr( stripslashes( $author_class->get_meta( 'instagram' ) ) );
        $youtube 	= esc_attr( stripslashes( $author_class->get_meta( 'youtube' ) ) );
        $linkedin 	= esc_attr( stripslashes( $author_class->get_meta( 'linkedin' ) ) );
        $myspace 	= esc_attr( stripslashes( $author_class->get_meta( 'myspace' ) ) );
        $pinterest 	= esc_attr( stripslashes( $author_class->get_meta( 'pinterest' ) ) );
        $soundcloud = esc_attr( stripslashes( $author_class->get_meta( 'soundcloud' ) ) );
        $tumblr 	= esc_attr( stripslashes( $author_class->get_meta( 'tumblr' ) ) );
        $github 	= esc_attr( stripslashes( $author_class->get_meta( 'github' ) ) );
        if ( isset( $twitter ) && $twitter != '' )
        {
            $twitter = 'https://twitter.com/' . $twitter;
        }

        $sameAs_links = array( $website, $facebook, $twitter, $instagram, $youtube, $linkedin, $myspace, $pinterest, $soundcloud, $tumblr, $github );

        $social = array();
        foreach ( $sameAs_links as $sameAs_link )
        {
            if ( $sameAs_link != '' ) $social[] = $sameAs_link;
        }

        if ( !empty( $social ) )
        {
            $author["sameAs"] = $social;
        }

        $authors[] = $author;
    }
    return $authors;
});