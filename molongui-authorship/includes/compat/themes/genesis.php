<?php

use Molongui\Authorship\Author_Filters;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
add_action( 'wp', function()
{
    if ( is_author() or molongui_is_guest_author() )
    {
        remove_action( 'genesis_before_loop', 'genesis_do_author_title_description', 15 );
        add_action( 'genesis_before_loop', function ()
        {
            $heading = get_the_author_meta( 'headline', (int) get_query_var( 'author' ) );

            if ( empty( $heading ) and function_exists( 'genesis_a11y' ) and genesis_a11y( 'headings' ) )
            {
                $heading = get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) );
            }

            $heading = Author_Filters::filter_the_archive_title( $heading );

            $intro_text = get_the_author_meta( 'intro_text', (int) get_query_var( 'author' ) );
            $intro_text = apply_filters( 'genesis_author_intro_text_output', $intro_text ? $intro_text : '' );
            do_action( 'genesis_archive_title_descriptions', $heading, $intro_text, 'author-archive-description' );

        }, 15 );
    }
}, 99 );