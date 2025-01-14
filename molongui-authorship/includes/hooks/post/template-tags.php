<?php
defined( 'ABSPATH' ) or exit;
function authorship_template_tags()
{
    if ( apply_filters( 'authorship/template_tags', true ) )
    {
        function get_the_molongui_author( $pid = null, $separator = '', $last_separator = '', $before = '', $after = '' )
        {
            _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_get_the_author()' );
            return molongui_get_the_author( $pid, $separator, $last_separator, $before, $after );
        }
        function the_molongui_author( $pid = null, $separator = '', $last_separator = '', $before = '', $after = '' )
        {
            _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_the_author()' );
            molongui_the_author( $pid, $separator, $last_separator, $before, $after );
        }
        function get_the_molongui_author_posts_link( $pid = null, $separator = null, $last_separator = null, $before = '', $after = '' )
        {
            _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_get_the_author_posts_link()' );
            return molongui_get_the_author_posts_link( $pid, $separator, $last_separator, $before, $after );
        }
        function the_molongui_author_posts_link( $pid = null, $separator = null, $last_separator = null, $before = '', $after = '' )
        {
            _deprecated_function( __FUNCTION__, '5.0.0', 'molongui_the_author_posts_link()' );
            echo molongui_the_author_posts_link( $pid, $separator, $last_separator, $before, $after );
        }
    }
}
//add_action( 'authorship/init', 'authorship_template_tags' );