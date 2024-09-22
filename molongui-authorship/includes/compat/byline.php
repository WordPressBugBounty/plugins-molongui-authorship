<?php

use Molongui\Authorship\Common\Utils\Assets;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
if ( !authorship_byline_takeover() )
{
    return;
}
function authorship_filter_author_link( $link, $author_id, $author_nicename )
{
    $original_link = $link;
    $link = null;
    if ( molongui_is_request( 'admin' ) )
    {
        return $original_link;
    }
    $link = apply_filters( 'authorship/pre_author_link', $link, $original_link, $author_id, $author_nicename );
    if ( null !== $link )
    {
        return $link;
    }

    $link = authorship_author_link( $original_link );

    return empty( $link ) ? $original_link : $link;
}
add_filter( 'author_link', 'authorship_filter_author_link', PHP_INT_MAX, 3 );
function authorship_dont_filter_author_link( $link, $original_link, $author_id, $author_nicename )
{
    return $original_link;
}
function authorship_register_byline_scripts()
{
    $file = apply_filters( 'authorship/byline/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/byline.334a.min.js' );

    Assets::register_script( $file, 'byline' );
}
add_action( 'wp_enqueue_scripts', 'authorship_register_byline_scripts' );
function authorship_enqueue_byline_scripts()
{
    $file = apply_filters( 'authorship/byline/script', MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/byline.334a.min.js' );

    Assets::enqueue_script( $file, 'byline' );
}
add_action( 'wp_enqueue_scripts', 'authorship_enqueue_byline_scripts' );
function authorship_byline_script_params()
{
    $options = authorship_get_options();

    list( $separator, $last_separator ) = authorship_get_byline_separators();

    $params = array
    (
        'byline_prefix'         => ( !empty( $options['byline_prefix'] ) ? $options['byline_prefix'] : '' ),
        'byline_suffix'         => ( !empty( $options['byline_suffix'] ) ? $options['byline_suffix'] : '' ),
        'byline_separator'      => $separator,
        'byline_last_separator' => $last_separator,
        'byline_link_title'     => apply_filters( 'authorship/byline/link_title', __( "View all posts by", 'molongui-authorship' ) ),
        'byline_link_class'     => apply_filters( 'authorship/byline/link_class', '' ),
        'byline_dom_tree'       => apply_filters( 'authorship/byline/dom_tree', '' ),
        'byline_dom_prepend'    => apply_filters( 'authorship/byline/dom_prepend', '' ),
        'byline_dom_append'     => apply_filters( 'authorship/byline/dom_append', '' ),
        'byline_decoder'        => apply_filters( 'authorship/author_link/filter_version', 'v3' ),
    );
    return apply_filters( 'authorship/byline/script_params', $params );
}