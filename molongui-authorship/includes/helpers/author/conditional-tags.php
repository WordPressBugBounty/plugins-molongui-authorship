<?php

use Molongui\Authorship\Author;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly

if ( !function_exists( 'is_guest_author' ) )
{
    function is_guest_author()
    {
        _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_is_guest_author()' );
        return molongui_is_guest_author();
    }
}

if ( !function_exists( 'molongui_is_guest' ) )
{
    function molongui_is_guest( $author = null )
    {
        _deprecated_function( __FUNCTION__, '5.0.0' );
        if ( empty( $author ) ) return false;
        if ( $author instanceof \WP_User ) return false;
        if ( $author instanceof \WP_Post ) return true;
        if ( is_object( $author ) ) return ( ( !empty( $author->type ) and $author->type == 'guest' ) ? true : false );
        if ( is_string( $author ) ) if ( strncmp( $author, 'guest', strlen( 'guest' ) ) === 0 ) return true;
        return false;
    }
}

if ( !function_exists( 'has_local_avatar' ) )
{
    function has_local_avatar( $author_id = null, $author_type = 'user' )
    {
        _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_has_local_avatar()' );
        return molongui_has_local_avatar( $author_id, $author_type );
    }
}

if ( !function_exists( 'authorship_author_has_posts' ) )
{
    function authorship_author_has_posts( $author, $post_types )
    {
        $has_posts = false;
        foreach ( $post_types as $post_type )
        {
            if ( !empty( $author['post_count'][$post_type] ) )
            {
                $has_posts = true;
                break;
            }
        }

        return $has_posts;
    }
}

if ( !function_exists( 'authorship_author_name_exists' ) )
{
    function authorship_author_name_exists( $id, $type )
    {
        global $wpdb;
        $user_displayname_check  = false;
        $guest_displayname_check = false;
        $author = new Author( $id, $type );
        $name   = $author->get_name();
        if ( $type == 'user' )
        {
            $user_displayname_check  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE display_name = %s AND ID != '{$id}' LIMIT 1", $name ) );
            $guest_displayname_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = '".MOLONGUI_AUTHORSHIP_CPT."' LIMIT 1", $name ) );
        }
        else
        {
            $user_displayname_check  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE display_name = %s LIMIT 1", $name ) );
            $guest_displayname_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = '".MOLONGUI_AUTHORSHIP_CPT."' AND ID != '{$id}' LIMIT 1", $name ) );
        }
        if ( !$user_displayname_check and !$guest_displayname_check ) return false;
        if (  $user_displayname_check and !$guest_displayname_check ) return 'user';
        if ( !$user_displayname_check and  $guest_displayname_check ) return 'guest';
        if (  $user_displayname_check and  $guest_displayname_check ) return 'both';
    }
}
function authorship_is_author_archived( $id = null, $type = 'user' )
{
    if ( empty( $id ) )
    {
        if ( !$authors = molongui_find_authors() )
        {
            return false;
        }
        $id   = $authors[0]->id;
        $type = $authors[0]->type;
    }

    $author   = new Author( $id, $type );
    $archived = $author->get_meta( 'archived' );

    return $archived ? true : false;
}

if ( !function_exists( 'is_author_archived' ) )
{
    function is_author_archived( $id, $type )
    {
        authorship_is_author_archived( $id, $type );
    }
}