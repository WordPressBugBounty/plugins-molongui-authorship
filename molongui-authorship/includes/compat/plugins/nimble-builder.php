<?php

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'the_author_posts_link', function( $link )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 11 );
    $fn   = 'Nimble\sek_render_post';
    $file = '/nimble-builder/tmpl/modules/post_grid_module_tmpl.php';
    if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        if ( function_exists( 'molongui_get_the_author_posts_link' ) )
        {
            global $post;
            return molongui_get_the_author_posts_link( $post );
        }
    }

    return $link;
});