<?php

use Molongui\Authorship\Author;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Astra
{
    public function __construct()
    {
        add_filter( 'molongui_authorship_do_filter_name', array( $this, 'filter_the_author_name' ), 10, 2 );
        add_filter( 'get_the_author_user_email', array( $this, 'filter_the_author_user_email' ), 10, 3 );
        authorship_add_byline_support();
    }
    public function filter_the_author_name( $leave, &$args )
    {
        $dbt = $args['dbt'];
        $fn  = 'astra_archive_page_info';

        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            $args['display_name'] = authorship_filter_archive_title( $args['display_name'] );
            return true;
        }
        return false;
    }
    public function filter_the_author_user_email( $value, $user_id = null, $original_user_id = null )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
        $fn  = 'astra_archive_page_info';
        if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            global $wp_query;
            $author_id = ( is_guest_author() and isset( $wp_query->guest_author_id ) ) ? $wp_query->guest_author_id : $wp_query->query_vars['author'];
            $author_class = new Author( $author_id, !empty( $wp_query->is_guest_author ) ? 'guest' : 'user' );
            return $author_class->get_mail();
        }
        return $value;
    }
    public function autop_the_author_description( $value, $user_id, $original_user_id )
    {
        if ( is_author() )
        {
            $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
            $fn  = 'astra_archive_page_info';

            if ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) )
            {
                $value = wpautop( $value ); // Doesn't add any extra spacing between new lines. Check CSS
            }
        }

        return $value;
    }
    public function filter_the_author_description( $value, $user_id = null, $original_user_id = null )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );

        $fn = 'astra_archive_page_info';
        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            global $wp_query;

            $author_type = is_guest_author() ? 'guest' : 'user';
            $author_id   = ( 'guest' === $author_type and isset( $wp_query->guest_author_id ) ) ? $wp_query->guest_author_id : $wp_query->query_vars['author'];
            $author      = new Author( $author_id, $author_type );

            remove_filter( 'get_the_author_description', array( $this, 'filter_the_author_description' ), PHP_INT_MAX, 3 );
            $author_bio  = $author->get_bio();
            add_filter( 'get_the_author_description', array( $this, 'filter_the_author_description' ), PHP_INT_MAX, 3 );

            return $author_bio;
        }

        return $value;
    }

} // class
new Astra;