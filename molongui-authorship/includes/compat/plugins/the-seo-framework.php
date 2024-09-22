<?php
defined( 'ABSPATH' ) or exit;
add_filter( 'authorship/pre_get_user_by', function( $user, $original_user, $field, $value )
{
    $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
    $fn    = 'get_author_canonical_url';
    $class = 'The_SEO_Framework\Generate_Url';

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
    $fn    = 'get_author_canonical_url';
    $class = 'The_SEO_Framework\Generate_Url';

    if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) and
        isset( $dbt[$i]['class'] ) and ( $dbt[$i]['class'] == $class ) )
    {
        $link = $original_link;
    }

    return $link;
}, 10, 4 );
add_filter( 'the_seo_framework_title_from_generation', function ( $generated, $args )
{
    global $wp_query;

    if ( is_guest_author() and isset( $wp_query->guest_author_id ) )
    {
        if ( 'Untitled' === $generated )
        {
            $author = new Molongui\Authorship\Author( $wp_query->guest_author_id, 'guest' );
            $display_name = $author->get_name();

            $prefix = __( 'Author:' );
            $prefix = apply_filters_ref_array( 'the_seo_framework_generated_archive_title_prefix', array( $prefix, get_queried_object() ) );

            $generated = $prefix . ' ' . $display_name;
        }
    }

    return $generated;
}, 10, 2 );