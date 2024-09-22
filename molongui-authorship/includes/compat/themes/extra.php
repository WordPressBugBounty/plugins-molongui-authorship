<?php

use Molongui\Authorship\Author;
defined('ABSPATH') or exit;
authorship_add_byline_support();
add_filter( 'authorship/pre_author_link', function( $link, $original_link, $author_id, $author_nicename )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    $fn    = 'widget';
    $class = 'ET_Authors_Widget';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
         isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] == $class ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );
add_filter( 'authorship/render_box', function( $render )
{
    $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
    if ( array_search( 'et_theme_builder_frontend_render_post_content', array_column( $dbt, 'function' ) ) )
    {
        $render = true;
    }

    return $render;
}, 10, 1 );