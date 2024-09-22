<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'molongui_authorship_do_filter_name', function( $leave, &$args )
{
    if ( $leave ) return $leave;
    if ( isset( $args['dbt'][4]['function'] ) and ( $args['dbt'][4]['function'] == 'get_content_part' ) )
    {
        $args['display_name'] = authorship_filter_archive_title( $args['display_name'] );
        return true;
    }
    return false;
}, 10, 2 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'get_content_part';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        $link = authorship_filter_author_page_link( $original_link );
    }

    return $link;
}, 10, 4 );