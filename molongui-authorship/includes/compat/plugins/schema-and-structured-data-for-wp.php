<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_filter( 'get_the_author_display_name', function( $default )
{
    $i  = 4;
    $fn = 'saswp_author_output';
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 6 );
    if ( isset( $dbt[$i]['function'] ) and ( $dbt[$i]['function'] == $fn ) )
    {
        return Author_Filters::filter_the_archive_title( $default );
    }
    return $default;
}, 10, 2 );
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn  = 'saswp_author_output';
    if ( ( is_author() or molongui_is_guest_author() ) and
         $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
    {
        $link = Author_Filters::the_author_page_link( $original_link );
    }

    return $link;
}, 10, 4 );
add_filter( 'saswp_modify_breadcrumb_output', function( $input )
{
    if ( is_author() or molongui_is_guest_author() )
    {
        $input['@id'] = Author_Filters::the_author_page_link( $input['@id'] ).'#breadcrumb';
        $input['itemListElement']['1']['item']['@id']  = Author_Filters::the_author_page_link( $input['itemListElement']['1']['@id'] );
        $input['itemListElement']['1']['item']['name'] = Author_Filters::filter_the_archive_title( $input['itemListElement']['1']['name'] );
    }

    return $input;
});