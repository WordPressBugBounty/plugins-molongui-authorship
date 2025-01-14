<?php
/*!
 * Conditional Tag Functions for Molongui Authorship Plugin.
 *
 * This file contains various conditional functions that check for specific author-related conditions within the
 * WordPress environment. These functions are designed to extend WordPress's existing functionality by providing
 * custom checks for guest authors, multiple authors, and other author-related conditions.
 *
 * Each function follows WordPress coding standards and is intended to be used within theme or plugin templates to
 * enhance author-based functionality.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

use Molongui\Authorship\Author;
use Molongui\Authorship\Author_Box;
use Molongui\Authorship\Guest_Author;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
/*!
 * Determines whether the lead author for the current post, or a specified post, is a guest author.
 * @since 5.0.0
 */
function molongui_is_guest_post( $post_id = null )
{
    return Post::is_guest( $post_id );
}
/*!
 * Determines whether the current post, or a specified post, has one or more guest authors.
 * @since 5.0.0
 */
function molongui_has_guest_author( $post_id = null )
{
    return Post::has_guest_author( $post_id );
}
/*!
 * Determines whether the current post, or a specified post, has multiple authors.
 * @since 5.0.0
 */
function molongui_has_coauthors( $post_id = null )
{
    return Post::has_multiple_authors( $post_id );
}
/*!
 * Determines whether the current post, or a specified post, has an author box.
 * @since 5.0.0
 */
function molongui_has_author_box( $post_id = null )
{
    return !Author_Box::hide();
}
/*!
 * Determines whether the query is for an existing guest author archive page.
 * @since 5.0.0
 */
function molongui_is_guest_author()
{
    return Guest_Author::is_guest_archive();
}
function molongui_has_local_avatar( $id, $type = 'user' )
{
    $author = new Author( $id, $type );
    return $author->has_avatar();
}
function molongui_has_posts( $id, $type = 'user', $post_types = array() )
{
    $author = new Author( $id, $type );
    return $author->has_posts( $post_types );
}
function molongui_is_author_archived( $id, $type = 'user' )
{
    $author = new Author( $id, $type );
    return $author->is_archived();
}