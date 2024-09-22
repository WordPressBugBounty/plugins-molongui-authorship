<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'molongui_authorship_do_filter_name', function( $leave, &$args )
{
    if ( $leave ) return $leave;
    if ( isset( $args['dbt'][3]['function'] ) and ( $args['dbt'][3]['function'] == 'get_the_author' )
         and
         isset( $args['dbt'][3]['file'] ) and substr_compare( $args['dbt'][3]['file'], '/themes/agama/author.php', strlen( $args['dbt'][3]['file'] )-strlen( '/themes/agama/author.php' ), strlen( '/themes/agama/author.php' ) ) === 0
    )
    {
        $args['display_name'] = authorship_filter_archive_title( $args['display_name'] );
        return true;
    }
    return false;
}, 10, 2 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    if ( defined( 'agama_version' ) and version_compare( agama_version,'1.4.4', '<' ) )
    {
        $dbt  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn   = 'get_author_posts_url';
        $file = '/themes/agama/author.php';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
            isset( $dbt[$i]['file'] ) and substr_compare( $dbt[$i]['file'], $file, strlen( $dbt[$i]['file'] )-strlen( $file ), strlen( $file ) ) === 0 )
        {
            $link = authorship_filter_author_page_link( $original_link );
        }
    }

    return $link;
}, 10, 4 );
add_filter( 'get_the_author_user_url', function( $value )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
    if ( is_author() and in_the_loop() and isset( $dbt[5]['function'] ) and $dbt[5]['function'] == "agama_render_blog_post_meta" ) return '#molongui-disabled-link';
    return $value;
}, 10, 1 );