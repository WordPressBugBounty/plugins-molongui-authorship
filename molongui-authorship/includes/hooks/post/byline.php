<?php
defined( 'ABSPATH' ) or exit;
if ( !authorship_byline_takeover() ) return;
function authorship_the_author_posts_link( $link )
{
    $original_link = $link;
    $link = null;
    $link = apply_filters( 'authorship/pre_the_author_posts_link', $link, $original_link );
    if ( null !== $link ) return $link;

    $link = authorship_get_byline( null, null, null, true );

    return empty( $link ) ? $original_link : $link;
}
add_filter( 'the_author_posts_link', 'authorship_the_author_posts_link', PHP_INT_MAX );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'get_the_author_posts_link';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        return $original_link;
    }

    return $link;
}, 10, 4 );