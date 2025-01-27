<?php
/*!
 * Public Template Tags for Molongui Authorship Plugin.
 *
 * Functions in this file can be used to display/retrieve information regarding authors for a post.
 * This file contains public template tag functions that can be used within WordPress themes to display author
 * information, such as guest authors or multiple authors, with enhanced functionality provided by the Molongui
 * Authorship plugin.
 *
 * These template tags extend WordPress's core author functions, providing support for both registered and guest authors,
 * and ensure compatibility with the plugin's custom author features.
 *
 * These functions are designed for use within theme templates and can replace the default WordPress author template
 * tags to provide enhanced author data output.
 *
 * The following template tag functions are included:
 *
 * - molongui_the_author()                : Outputs the display names of all authors for the current or given post.
 * - molongui_get_the_author()            : Retrieves the display names of all authors for the current or given post, without echoing it.
 * - molongui_the_author_posts_link()     : Displays author names with links to their archive pages.
 * - molongui_get_the_author_posts_link() : Returns the markup for displaying author names, with links to their archive pages.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
function molongui_the_author( $pid = null, $separator = '', $last_separator = '', $before = '', $after = '' )
{
    echo molongui_get_the_author( $pid, $separator, $last_separator, $before, $after );
}
function molongui_get_the_author( $pid = null, $separator = '', $last_separator = '', $before = '', $after = '' )
{
    if ( ( is_null( $pid ) or !is_integer( $pid ) ) and !in_the_loop() )
    {
        return '';
    }

    $prefix  = '';
    $suffix  = '';
    if ( !empty( $before ) and is_string( $before ) )
    {
        $prefix = $before;
    }
    elseif ( Settings::get( 'byline_custom_prefix_suffix', false ) )
    {
        $prefix = Settings::get( 'byline_prefix', '' );
    }

    /*!
     * DEPRECATED
     * This filter hook is scheduled for removal in version 5.2.0. Update usages accordingly.
     *
     * @since      2.0.0
     * @deprecated 5.0.0
     */
    if ( has_filter( 'molongui_byline_prefix' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
    {
        $prefix = apply_filters_deprecated( 'molongui_byline_prefix', array( $prefix ), '5.0.0', 'molongui_authorship/byline_prefix' );
    }

    /*!
     * FILTER HOOK
     * Allows filtering the string to add at the beginning of the post byline.
     *
     * @since 5.0.0
     */
    $prefix = apply_filters( 'molongui_authorship/byline_prefix', $prefix );
    if ( !empty( $after ) and is_string( $after ) )
    {
        $suffix = $after;
    }
    elseif ( Settings::get( 'byline_custom_prefix_suffix', false ) )
    {
        $suffix = Settings::get( 'byline_suffix', '' );
    }

    /*!
     * DEPRECATED
     * This filter hook is scheduled for removal in version 5.2.0. Update usages accordingly.
     *
     * @since      2.0.0
     * @deprecated 5.0.0
     */
    if ( has_filter( 'molongui_byline_suffix' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
    {
        $suffix = apply_filters_deprecated( 'molongui_byline_suffix', array( $suffix ), '5.0.0', 'molongui_authorship/byline_suffix' );
    }

    /*!
     * FILTER HOOK
     * Allows filtering the string to add at the beginning of the post byline.
     *
     * @since 5.0.0
     */
    $suffix = apply_filters( 'molongui_authorship/byline_suffix', $suffix );
    $linked = apply_filters( 'molongui_authorship/link_names_in_post_byline', false );
    $linked = apply_filters_deprecated( 'molongui_author_byline_linked', array( $linked ), '5.0.0', 'molongui_authorship/link_names_in_post_byline' );
    $byline = Post::get_byline( $pid, $separator, $last_separator, $linked );
    return $prefix . $byline . $suffix;
}
function molongui_the_author_posts_link( $pid = null, $separator = null, $last_separator = null, $before = '', $after = '' )
{
    echo molongui_get_the_author_posts_link( $pid, $separator, $last_separator, $before, $after );
}
function molongui_get_the_author_posts_link( $pid = null, $separator = null, $last_separator = null, $before = '', $after = '' )
{
    add_filter( 'molongui_authorship/link_names_in_post_byline', '__return_true' );
    $byline = molongui_get_the_author( $pid, $separator, $last_separator, $before, $after );
    remove_filter( 'molongui_authorship/link_names_in_post_byline', '__return_true' );

    return $byline;
}
