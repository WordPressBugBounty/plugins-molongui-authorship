<?php

use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'the_author_posts_link', function( $link )
{
    if ( is_author() )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 11 );
        $fn = 'hootkit_display_meta_info';
        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            if ( method_exists( 'Post', 'get_byline' ) )
            {
                global $post;

                $options        = Settings::get();
                $separator      = !empty( $options['co_authors_separator'] ) ? $options['co_authors_separator'] : ',';
                $last_separator = !empty( $options['co_authors_last_separator'] ) ? $options['co_authors_last_separator'] : __( "and", 'molongui-authorship' );

                return Post::get_byline( $post->ID, $separator, $last_separator, true );
            }
        }
    }

    return $link;
});