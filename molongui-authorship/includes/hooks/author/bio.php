<?php

use Molongui\Authorship\Author;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !authorship_byline_takeover() )
{
    return;
}
function authorship_filter_the_author_description( $description, $user_id = null, $original_user_id = null )
{
    /*!
     * FILTER HOOK
     *
     * Filters the $description before it is filtered by Molongui Authorship.
     *
     * Returning a non-null value will effectively short-circuit the function, returning that value instead.
     *
     * Allows escaping this function preventing Molongui Authorship to filter 'the_author_description'.
     *
     * @param bool   $_description     Null. Do not escape this function by default.
     * @param string $description      The user description.
     * @param int    $user_id          The user ID.
     * @param int    $original_user_id The original user ID.
     * @since 5.0.0
     */
    $_description = apply_filters( 'authorship/pre_the_author_description', null, $description, $user_id, $original_user_id );
    if ( null !== $_description )
    {
        return $_description;
    }
    if ( !is_author() and !is_guest_author() )
    {
        return $description;
    }

    $the_query = \Molongui\Authorship\Common\Utils\WP::the_query();
    $author_id = 0;
    if ( is_guest_author() and isset( $the_query->guest_author_id ) )
    {
        $author_type = 'guest';
        $author_id   = $the_query->guest_author_id;
    }
    elseif ( $the_query->query_vars['author'] )
    {
        $author_type = 'user';
        $author_id   = $the_query->query_vars['author'];
    }

    if ( empty( $author_id ) )
    {
        return $description;
    }
    switch( $author_type )
    {
        case 'guest':
            $description = get_post_field( 'post_content', $author_id );
            break;

        case 'user':
        default:

            $author = new Author( $author_id, $author_type );
            remove_filter( 'get_the_author_description', 'authorship_filter_the_author_description', 999 );
            $user_bio = $author->get_bio();
            add_filter( 'get_the_author_description', 'authorship_filter_the_author_description', 999, 3 );

            $description = nl2br( $user_bio );

            break;
    }

    return $description;
}
function authorship_filter_archive_description( $description )
{
    global $wp_query;
    if ( !is_author() and !is_guest_author() )
    {
        return $description;
    }
    if ( is_guest_author() and isset( $wp_query->guest_author_id ) )
    {
        return get_post_field( 'post_content', $wp_query->guest_author_id );
    }
    if ( $wp_query->query_vars['author'] )
    {
        $user = new Author( $wp_query->query_vars['author'], 'user' );

        return nl2br( $user->get_bio() );
    }
    return $description;
}
//add_filter( 'get_the_archive_description', 'authorship_filter_archive_description', 999, 1 );