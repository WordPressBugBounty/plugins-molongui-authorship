<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Cache
{
    public static function get( $key )
    {
        if ( empty( $key ) ) return false;
        return wp_cache_get( $key, MOLONGUI_AUTHORSHIP_NAME );
    }
    public static function set( $key, $data )
    {
        if ( empty( $key ) or empty( $data ) ) return false;
        return wp_cache_set( $key, $data, MOLONGUI_AUTHORSHIP_NAME );
    }
    public static function clear( $key )
    {
        if ( empty( $key ) )
        {
            return;
        }
        $hashes = get_option( MOLONGUI_AUTHORSHIP_PREFIX . '_cache_' . $key, array() );
        delete_option( MOLONGUI_AUTHORSHIP_PREFIX . '_cache_' . $key );
        foreach ( $hashes as $hash ) wp_cache_delete( $key . '_' . $hash, MOLONGUI_AUTHORSHIP_NAME );
    }
    public static function query( $args, $object )
    {
        $cache = apply_filters( 'authorship/cache', true );
        if ( !$cache )
        {
            if ( 'users' === $object ) return get_users( $args );
            else return new \WP_Query( $args );
        }
        else
        {
            $hash = md5( serialize( $args ) );
            $key  = $object . '_' . $hash;
            $data = self::get( $key );
            if ( false === $data )
            {
                if ( 'users' === $object ) $data = get_users( $args );
                else $data = new \WP_Query( $args );
                self::set( $key, $data );
                $db_key = MOLONGUI_AUTHORSHIP_PREFIX . '_cache_' . $object;
                $hashes = get_option( $db_key, array() );
                $update = update_option( $db_key, !in_array( $hash, $hashes ) ? array_merge( $hashes, array( $hash ) ) : $hashes, true );
            }

            return $data;
        }
    }
    public static function clear_3rd_party_cache()
    {
        if ( function_exists( 'w3tc_flush_all' ) )
        {
            w3tc_flush_all();
        }
        if ( function_exists( 'wpfc_clear_all_cache' ) )
        {
            wpfc_clear_all_cache( true );
        }
        if ( function_exists( 'wp_cache_clean_cache' ) )
        {
            global $file_prefix;
            wp_cache_clean_cache( $file_prefix, true );
        }
        if ( function_exists( 'rocket_clean_domain' ) )
        {
            rocket_clean_domain();
        }
        if ( function_exists( 'rocket_clean_minify' ) )
        {
            rocket_clean_minify();
        }
        if ( class_exists( 'WP_Optimize' ) and method_exists( 'WP_Optimize', 'get_page_cache' ) )
        {
            if ( class_exists( 'WPO_Page_Cache' ) and method_exists( 'WPO_Page_Cache', 'purge' ) )
            {
                WP_Optimize()->get_page_cache()->purge();
            }
        }
        if ( class_exists( '\LiteSpeed\Purge' ) )
        {
            do_action('litespeed_purge_all'); //\LiteSpeed\Purge::purge_all();
        }
        if ( class_exists( 'autoptimizeCache' ) and method_exists( 'autoptimizeCache', 'clearall' ) )
        {
            autoptimizeCache::clearall();
        }
        if ( function_exists( 'sg_cachepress_purge_everything' ) )
        {
            sg_cachepress_purge_everything();
        }
        do_action( 'breeze_clear_all_cache' );
        do_action( 'wphb_clear_page_cache' );
        if ( class_exists( 'comet_cache' ) and method_exists( 'comet_cache', 'clear' ) )
        {
            comet_cache::clear();
        }
    }

} // class