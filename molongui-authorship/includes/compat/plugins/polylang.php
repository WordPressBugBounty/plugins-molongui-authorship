<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Post;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Polylang
{
    public function __construct()
    {
        add_filter( 'pll_get_post_types', array( __CLASS__, 'disable_guest_author_translation' ), 10, 2 );
        add_filter( 'pll_copy_post_metas', array( __CLASS__, 'copy_custom_post_meta' ), 10, 4 );

        add_filter( 'authorship/author/id', array( __CLASS__, 'get_guest_translation_id' ), 10, 2 );
    }
    public static function copy_custom_post_meta( $keys, $sync, $from, $to )
    {
        if ( !$sync )
        {
            Post::copy_custom_meta( $from, $to );
        }

        return $keys;
    }
    public static function disable_guest_author_translation( $post_types, $is_settings )
    {
        $options = authorship_get_options();

        if ( isset( $options['pll_translate_guests'] ) and !$options['pll_translate_guests'] )
        {
            unset( $post_types['guest_author'] );
        }

        return $post_types;
    }
    public static function get_guest_translation_id( $id, $type )
    {
        if ( empty( $id ) or empty( $type ) )
        {
            return $id;
        }

        if ( 'guest' === $type and function_exists ( 'pll_get_post' ) )
        {
            $translated_id = pll_get_post( $id );

            if ( !empty( $translated_id ) and is_int( $translated_id ) and $translated_id != $id )
            {
                $id = $translated_id;
            }
            else
            {
            }
        }

        return $id;
    }
    public static function remove_lang_from_query( $query )
    {
        if ( !molongui_is_request( 'ajax' ) ) return;

        $dbt   = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $fn    = 'get_posts_count';
        $class = 'Molongui\Authorship\Author';
        if ( ( $key = array_search( $fn, array_column( $dbt, 'function' ) ) and
               isset( $dbt[$key]['class'] ) and ( $dbt[$key]['class'] == $class ) )
             or
             !apply_filters( 'authorship/pll_filter_query', true ) )
        {
            $query->set( 'lang', '' );
        }
    }
} // class
new Polylang;