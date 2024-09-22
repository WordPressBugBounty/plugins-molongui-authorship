<?php
defined( 'ABSPATH' ) or exit;
if ( defined( JETPACK__VERSION ) and version_compare( JETPACK__VERSION, '9.1.0', '>=' ) )
{
    add_filter( 'jetpack_content_options_featured_image_exclude_cpt', function( $excluded_post_types )
    {
        $excluded_post_types[] = 'guest_author';
        return $excluded_post_types;
    });
}
else
{
    add_action( 'init', function()
    {
        remove_filter( 'get_post_metadata', 'jetpack_featured_images_remove_post_thumbnail', true );
    }, 999 );
}
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'widget';
    $class = 'Jetpack_Widget_Authors';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) )
         and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] === $class ) )
    {
        return $original_user;
    }

    return $user;
}, 10, 4 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn    = 'widget';
    $class = 'Jetpack_Widget_Authors';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] == $class ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );