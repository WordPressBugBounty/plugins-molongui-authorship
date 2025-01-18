<?php
/*!
 * This file contains the Post class, which extends the functionality of the base Post utility class to manage
 * post-related features specific to the Molongui Authorship plugin.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      4.8.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Request;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Post extends \Molongui\Authorship\Common\Utils\Post
{
    public function __construct()
    {
        if ( self::byline_takeover() )
        {
            add_action( 'wp_head', array( $this, 'inline_styles' ) );
            add_action( 'pre_get_posts', array( $this, 'filter_user_posts' ), PHP_INT_MAX );
            add_filter( 'posts_where', array( $this, 'remove_author_from_where_clause' ), 10, 2 );
            add_filter( 'the_author_posts_link', array( $this, 'filter_the_author_posts_link' ), PHP_INT_MAX );
            add_filter( 'authorship/pre_author_link', array( $this, 'keep_author_link' ), 10, 4 );
            add_action( 'wp_head', array( $this, 'add_author_meta' ), -1 );
        }
        add_filter( 'get_usernumposts', array( $this, 'filter_post_count' ), PHP_INT_MAX, 4 );
        add_action( 'wp_footer', array( $this, 'add_custom_styles' ), PHP_INT_MAX );
    }
    public function inline_styles()
    {
        $options = Settings::get();

        if ( $options['guest_author_enabled'] or $options['co_authors_enabled'] )
        {
            ?>
            <style>
                .molongui-disabled-link
                {
                    border-bottom: none !important;
                    text-decoration: none !important;
                    color: inherit !important;
                    cursor: inherit !important;
                }
                .molongui-disabled-link:hover,
                .molongui-disabled-link:hover span
                {
                    border-bottom: none !important;
                    text-decoration: none !important;
                    color: inherit !important;
                    cursor: inherit !important;
                }
            </style>
            <?php
        }
    }
    public function filter_post_count( $count, $userid, $post_type, $public_only )
    {
        $post_count = apply_filters( 'authorship/pre_post_count', null, $count, $userid, $post_type, $public_only );

        if ( !is_null( $post_count ) )
        {
            return apply_filters( 'authorship/post_count', $post_count, $count, $userid, $post_type, $public_only );
        }

        $the_query = WP::the_query();
        if ( molongui_is_guest_author() and isset( $the_query->guest_author_id ) and !in_the_loop() )
        {
            $author_type = 'guest';
            $author_id   = $the_query->guest_author_id;
        }
        else
        {
            $author_type = 'user';
            $author_id   = $userid;
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        list( $author_id, $author_type ) = apply_filters( '_authorship/post_count/author', array( $author_id, $author_type ), $count, $userid, $post_type, $public_only );
        $author     = new Author( $author_id, $author_type );
        $post_count = $author->get_post_count( $post_type, true );
        return apply_filters( 'authorship/post_count', $post_count, $count, $userid, $post_type, $public_only );
    }
    public function filter_user_posts( $wp_query )
    {
        if ( isset( $wp_query->is_guest_author ) )
        {
            return;
        }
        if ( Request::is_from( 'admin' ) )
        {
            $current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( !isset( $current_screen->id ) )
            {
                return;
            }

            if ( !( $wp_query->is_author and in_array( $current_screen->id, Settings::enabled_screens() ) ) )
            {
                return;
            }
        }
        if ( !$wp_query->is_main_query() and apply_filters_ref_array( 'molongui_edit_main_query_only', array( true, &$wp_query ) ) )
        {
            return;
        }
        if ( $wp_query->is_author )
        {
            if ( !empty( $wp_query->query_vars['author'] ) )
            {
                $author_id = $wp_query->query_vars['author'];
            }
            else
            {
                $author = get_users( array( 'nicename' => $wp_query->query_vars['author_name'] ) );
                if ( !$author )
                {
                    return;
                }

                $author_id = $author[0]->ID;
            }
            self::add_author_meta_query( $wp_query, 'user', $author_id );
            add_filter( '_authorship/posts_where', '__return_true' );
        }
    }
    public function remove_author_from_where_clause( $where, $wp_query )
    {
        if ( apply_filters( '_authorship/posts_where', false ) )
        {
            remove_filter( '_authorship/posts_where', '__return_true' );

            $_where = $where;

            if ( !empty( $wp_query->query_vars['author'] ) )
            {
                global $wpdb;
                $where = str_replace( ' AND '.$wpdb->posts.'.post_author IN ('.$wp_query->query_vars['author'].')', '', $where );
                $where = str_replace( ' AND ('.$wpdb->posts.'.post_author = '.$wp_query->query_vars['author'].')' , '', $where );
                $where = apply_filters( 'authorship/posts_where', $where, $_where, $wp_query );
            }
        }
        return $where;
    }
    public function filter_the_author_posts_link( $link )
    {
        $original_link = $link;
        $link = apply_filters( 'authorship/pre_the_author_posts_link', null, $original_link );
        if ( null !== $link )
        {
            return $link;
        }

        $link = self::get_byline( null, null, null, true );

        return empty( $link ) ? $original_link : $link;
    }
    public function keep_author_link( $link, $original_link, $author_id, $author_nicename )
    {
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        $fn  = 'get_the_author_posts_link';

        if ( $i = array_search( $fn, array_column( $dbt, 'function' ) ) )
        {
            return $original_link;
        }

        return $link;
    }
    public function add_author_meta()
    {
        if ( !Settings::get( 'seo_settings_enabled' ) )
        {
            return;
        }

        $html_meta      = Settings::get( 'html_meta_enabled' );
        $opengraph_meta = Settings::get( 'opengraph_meta_enabled' );
        $facebook_meta  = Settings::get( 'facebook_meta_enabled' );
        $twitter_meta   = Settings::get( 'twitter_meta_enabled' );
        $meta_coauthors = Settings::get( 'html_meta_coauthors' );
        if ( !$html_meta and !$opengraph_meta and !$facebook_meta and !$twitter_meta )
        {
            return;
        }

        $post_id = self::get_id();

        if ( is_null( $post_id ) )
        {
            return;
        }
        $authors = self::get_authors( $post_id );
        if ( empty( $authors ) )
        {
            return;
        }

        $meta = "";

        if ( apply_filters( 'authorship/add_html_comments', true ) )
        {
            if ( Plugin::has_pro() )
            {
                $meta .= "\n<!-- Author Meta Tags by Molongui Authorship Pro, visit: " . MOLONGUI_AUTHORSHIP_WEB . " -->\n";
            }
            else
            {
                $meta .= "\n<!-- Author Meta Tags by Molongui Authorship, visit: https://wordpress.org/plugins/molongui-authorship/ -->\n";
            }
        }
        if ( is_author() or molongui_is_guest_author() )
        {
            global $wp_query;
            if ( molongui_is_guest_author() )
            {
                $author_id = isset( $wp_query->guest_author_id ) ? $wp_query->guest_author_id : $wp_query->query_vars['author'];
                $author    = new Author( $author_id, 'guest' );
            }
            else
            {
                $author = new Author( $wp_query->get( 'author' ), 'user' );
            }
            if ( !empty( $html_meta ) )
            {
                $meta .= '<meta name="author" content="' . esc_attr( $author->get_name() ) . '">'."\n";
            }
            if ( !empty( $opengraph_meta ) )
            {
                $meta .= $this->add_opengraph_archive_meta();
            }
        }
        elseif ( is_singular() )
        {
            switch ( $meta_coauthors )
            {
                case 'main':
                    if ( !$main_author = self::get_main_author( $post_id ) )
                    {
                        return;
                    }
                    $author = new Author( $main_author->id, $main_author->type );
                    if ( !empty( $html_meta ) )
                    {
                        $meta .= '<meta name="author" content="' . esc_attr( $author->get_name() ) . '">'."\n";
                    }
                    if ( !empty( $facebook_meta ) )
                    {
                        $meta .= $this->add_facebook_author_meta( $author );
                    }
                    if ( !empty( $twitter_meta ) )
                    {
                        $meta .= $this->add_twitter_author_meta( $author );
                    }
                    if ( !empty( $opengraph_meta ) and empty( $facebook_meta ) )
                    {
                        $meta .= $this->add_opengraph_author_meta( $author );
                    }

                    break;

                case 'aio':
                    if ( !empty( $html_meta ) )
                    {
                        $meta .= '<meta name="author" content="' . esc_attr( self::get_byline( $post_id ) ) . '">'."\n";
                    }

                    foreach ( $authors as $auth )
                    {
                        $author = new Author( $auth->id, $auth->type );
                        if ( !empty( $facebook_meta ) )
                        {
                            $meta .= $this->add_facebook_author_meta( $author );
                        }
                        if ( !empty( $twitter_meta ) )
                        {
                            $meta .= $this->add_twitter_author_meta( $author );
                        }
                        if ( !empty( $opengraph_meta ) and empty( $facebook_meta ) )
                        {
                            $meta .= $this->add_opengraph_author_meta( $author );
                        }
                    }

                    break;

                case 'many':
                default:

                    foreach ( $authors as $auth )
                    {
                        $author = new Author( $auth->id, $auth->type );
                        if ( !empty( $html_meta ) )
                        {
                            $meta .= '<meta name="author" content="' . esc_attr( $author->get_name() ) . '">'."\n";
                        }
                        if ( !empty( $facebook_meta ) )
                        {
                            $meta .= $this->add_facebook_author_meta( $author );
                        }
                        if ( !empty( $twitter_meta ) )
                        {
                            $meta .= $this->add_twitter_author_meta( $author );
                        }
                        if ( !empty( $opengraph_meta ) and empty( $facebook_meta ) )
                        {
                            $meta .= $this->add_opengraph_author_meta( $author );
                        }
                    }

                    break;
            }
        }

        $meta .= "<!-- /Molongui Authorship -->\n\n";

        echo $meta;
    }
    public function add_twitter_author_meta( $author )
    {
        $meta = '';
        $tw = $author->get_meta( 'twitter' );
        if ( filter_var( $tw, FILTER_VALIDATE_URL ) )
        {
            $url = array( 'https://www.twitter.com/', 'https://twitter.com/', 'http://www.twitter.com/', 'http://twitter.com/', '//twitter.com/', 'twitter.com/' );
            $tw  = rtrim( str_replace( $url, '@', $tw ), '/' );
        }
        if ( !empty( $tw ) )
        {
            $meta .= '<meta name="twitter:creator" content="' . $tw . '" />' . "\n";
        }

        return $meta;
    }
    public function add_facebook_author_meta( $author )
    {
        $meta = '';
        $fb = $author->get_meta( 'facebook' );
        if ( filter_var( $fb, FILTER_VALIDATE_URL ) )
        {
            $url = array( 'https://www.facebook.com/', 'https://facebook.com/', 'http://www.facebook.com/', 'http://facebook.com/', '//facebook.com/', 'facebook.com/' );
            $fb  = rtrim( str_replace( $url, '', $fb ), '/' );
        }
        if ( !empty( $fb ) )
        {
            $meta .= '<meta property="article:author" content="' . esc_attr( $fb ) . '" />' . "\n";
        }

        return $meta;
    }
    public function add_opengraph_author_meta( $author )
    {
        $meta = '';
        $meta .= '<meta property="article:author" content="' . esc_attr( $author->get_name() ) . '" />' . "\n";

        return $meta;
    }
    public function add_opengraph_archive_meta()
    {
        global $wp_query;
        $author_id   = null;
        $author_type = null;
        if ( !isset( $wp_query ) )
        {
            return;
        }
        if ( molongui_is_guest_author() )
        {
            $author_id   = isset( $wp_query->guest_author_id ) ? $wp_query->guest_author_id : $wp_query->query_vars['author'];
            $author_type = 'guest';
        }
        else
        {
            $author_id   = $wp_query->get( 'author' );
            $author_type = 'user';
        }
        if ( empty( $author_id ) and empty( $author_type ) )
        {
            return;
        }
        $author = new Author( $author_id, $author_type );
        $author_name   = $author->get_name();
        $author_first  = $author->get_first_name();
        $author_last   = $author->get_last_name();
        $author_bio    = esc_html( $author->get_description() );
        $author_link   = $author->get_archive_url();
        $author_avatar = $author->get_avatar( 'full', 'url', 'local' );

        $og  = '';
        $og .= '<meta property="og:type" content="profile" />' . "\n";
        $og .= ( $author_link   ? '<meta property="og:url" content="' . esc_attr( $author_link ) . '" />'."\n" : '' );
        $og .= ( $author_avatar ? '<meta property="og:image" content="' . esc_attr( $author_avatar ) . '" />'."\n" : '' );
        $og .= ( $author_bio    ? '<meta property="og:description" content="' . esc_attr( $author_bio ) . '" />'."\n" : '' );
        $og .= ( $author_first  ? '<meta property="profile:first_name" content="' . esc_attr( $author_first ) . '" />'."\n" : '' );
        $og .= ( $author_last   ? '<meta property="profile:last_name" content="' . esc_attr( $author_last ) . '" />'."\n" : '' );
        $og .= ( $author_name   ? '<meta property="profile:username" content="' . esc_attr( $author_name ) . '" />'."\n" : '' );

        return $og;
    }
    public function add_custom_styles()
    {
        $custom_css = Settings::get_custom_css();

        if ( !empty( $custom_css ) )
        {
            echo '<style>';
            echo Helpers::minify_css( $custom_css );
            echo '</style>';
            Debug::console_log( $custom_css, "Custom CSS loaded." );
        }
    }
    public static function get_main_author( $post_id )
    {
        $options = Settings::get();
        $meta    = get_post_meta( $post_id, '_molongui_main_author', true );
        $data    = false;

        if ( empty( $meta ) or ( !$options['guest_author_enabled'] and !$options['co_authors_enabled'] ) )
        {
            if ( $post_author = get_post_field( 'post_author', $post_id ) )
            {
                $data = self::get_wp_author( $post_id );
            }
        }
        else
        {
            $split      = explode( '-', $meta );
            $data       = new \stdClass();
            $data->ID   = $split[1];
            $data->id   = $split[1];
            $data->type = $split[0];
            $data->ref  = $meta;
            if ( !self::is_post_type_enabled( '', $post_id )
                 or
                 $data->type == 'guest' and !$options['guest_author_enabled'] )
            {
                $data = self::get_wp_author( $post_id );
            }
        }

        return $data;
    }
    public static function get_wp_author( $post_id )
    {
        $data = false;

        if ( $post_author = get_post_field( 'post_author', $post_id ) )
        {
            $data       = new \stdClass();
            $data->ID   = $post_author;
            $data->id   = $post_author;
            $data->type = 'user';
            $data->ref  = $data->type.'-'.$data->id;
        }

        return $data;
    }
    public static function get_authors( $post_id = null, $key = '' )
    {
        if ( empty( $post_id ) or !is_integer( $post_id ) )
        {
            $post_id = self::get_id();
            if ( !$post_id )
            {
                return false;
            }
        }

        $data = array();
        if ( !in_array( self::get_post_type( $post_id ), Settings::enabled_post_types() ) )
        {
            $wp_author = self::get_wp_author( $post_id );

            if ( !$wp_author )
            {
                return false;
            }

            $data[] = $wp_author;
        }

        else
        {
            $main_author = self::get_main_author( $post_id );
            if ( empty( $main_author ) )
            {
                return false;
            }
            if ( !Settings::is_enabled( 'co-authors' ) )
            {
                $data[] = $main_author;
            }
            else
            {
                $authors = get_post_meta( $post_id, '_molongui_author', false );
                if ( !empty( $authors) )
                {
                    $guest_enabled = Settings::is_enabled( 'guest-author' );

                    foreach ( $authors as $author )
                    {
                        $split = explode( '-', $author );
                        if ( empty( $split[1] ) )
                        {
                            continue;
                        }
                        if ( $split[1] == $main_author->id )
                        {
                            continue;
                        }
                        if ( $split[0] === 'guest' and !$guest_enabled )
                        {
                            continue;
                        }
                        $data[] = (object) array( 'ID' => (int)$split[1], 'id' => (int)$split[1], 'type' => $split[0], 'ref' => $author );
                    }
                }
                array_unshift( $data, $main_author );
            }
        }
        if ( !$key )
        {
            return $data;
        }
        if ( !empty( $data ) )
        {
            $values = array();
            foreach ( $data as $author )
            {
                if ( is_object( $author ) and property_exists( $author, $key ) )
                {
                    $values[] = $author->$key;
                }
            }
            return $values;
        }
        else
        {
            return false;
        }
    }
    public static function get_coauthored( $authors, $get_all = false, $exclude = array(), $entry = 'post', $meta_query = array() )
    {
        $options = Settings::get();
        switch ( $entry )
        {
            case 'all':
                $entries = self::get_post_types( 'all', 'names', false );
                break;

            case 'selected':
                $entries = Settings::enabled_post_types( 'co-authors' );
                break;

            case 'related':
                $entries = explode( ",", $options['author_box_related_post_types'] );
                break;

            default:
                $entries = $entry;
                break;
        }
        if ( count( $authors ) > 1 )
        {
            $mq['authors']['relation'] = 'AND';
            foreach( $authors as $author )
            {
                $mq['authors'][] = array( 'key' => '_molongui_author', 'value' => $author->ref, 'compare' => '=' );
            }
        }
        else
        {
            $mq['authors'] = array( 'key' => '_molongui_author', 'value' => $authors, 'compare' => '=' );
        }
        if ( !empty( $meta_query ) )
        {
            $mq['authors']['relation'] = 'AND';
            $mq['authors'] = array
            (
                'key'   => $meta_query['key'],
                'value' => $meta_query['value'],
            );
        }
        $args = array
        (
            'post_type'      => $entries,
            'orderby'        => !empty( $options['author_box_related_orderby'] ) ? $options['author_box_related_orderby'] : 'date',
            'order'          => !empty( $options['author_box_related_order'] )   ? $options['author_box_related_order']   : 'desc',
            'posts_per_page' => $get_all ? '-1' : $options['author_box_related_count'],
            'post__not_in'   => $exclude,
            'meta_query'     => $mq,
            'site_id'        => get_current_blog_id(),
            'language'       => Helpers::get_language(),
        );
        $data = Cache::query( $args, 'posts' );
        if ( !empty( $data->posts ) ) foreach ( $data->posts as $post ) $posts[] = $post;
        return ( !empty( $posts ) ? $posts : array() );
    }
    public static function get_byline( $post_id = null, $separator = null, $last_separator = null, $linked = false )
    {
        $byline = '';
        if ( is_null( $post_id ) or !is_integer( $post_id ) or !$post_id )
        {
            $post_id = self::get_id();
            if ( !$post_id )
            {
                return $byline;
            }
        }
        $post_authors = self::get_authors( $post_id );
        if ( !$post_authors )
        {
            return $byline;
        }

        $count            = count( $post_authors );
        $show_remaining   = false;
        $names_to_display = 1;

        if ( !empty( Settings::get( 'co_authors_in_byline', true ) ) )
        {
            /*!
             * FILTER HOOK
             * Allows filtering the number of co-authors that should be displayed on the post byline.
             *
             * @param int|string $names_to_display The number of co-authors to display or 'all' to display all assigned authors. Default is 'all'.
             * @param string     $post_id          The post ID.
             * @param array      $post_authors     The post authors.
             * @since 5.0.0
             */
            $names_to_display = apply_filters( 'molongui_authorship/co_authors_in_byline', Settings::get( 'co_authors_in_byline_format', 'all' ), $post_id, $post_authors );
            if ( is_numeric( $names_to_display ) )
            {
                $names_to_display = min( $names_to_display, $count );
            }
            else
            {
                $names_to_display = $count;
            }
        }
        $byline_authors = $post_authors;
        if ( $names_to_display < $count )
        {
            $byline_authors = array_slice( $post_authors, 0, $names_to_display );
            $show_remaining = true;
        }
        list( $separator, $last_separator ) = self::get_byline_separators( $separator, $last_separator, $names_to_display );

        $i = 0;
        foreach ( $byline_authors as $byline_author )
        {
            $divider = ( $i == 0 ? '' : ( $i == ( $names_to_display - 1 ) ? $last_separator : $separator ) );

            $author = new Author( $byline_author->id, $byline_author->type );
            if ( $linked )
            {
                $item = $author->get_link();
            }
            else
            {
                $item = esc_html( $author->get_name() );
            }
            $item = apply_filters( 'authorship/byline_item', $divider.$item, $item, $divider, $i, $byline_author, $names_to_display );
            $byline .= $item;
            ++$i;
        }
        if ( $show_remaining and $count < $names_to_display )
        {
            $byline .= ' ' . sprintf( __( '%s %d more', 'molongui-authorship' ), $last_separator, $count - $names_to_display );
        }
        Debug::console_log( array( 'post_id' => $post_id, 'post_authors' => $post_authors, 'names_to_display' => $names_to_display, 'byline_authors' => $byline_authors, 'separator' => $separator, 'last_separator' => $last_separator, 'byline' => $byline ), "Byline information" );
        return apply_filters( 'authorship/post_byline', $byline, $post_id, $post_authors );
    }
    public static function get_byline_separators( $separator = null, $last_separator = null, $count = null )
    {
        $options = Settings::get();
        $custom  = !empty( $options['co_authors_separators'] );

        if ( is_null( $separator ) )
        {
            $separator = ',';
            if ( $custom and !empty( $options['co_authors_separator'] ) )
            {
                $separator = $options['co_authors_separator'];
            }
        }
        if ( is_null( $last_separator ) )
        {
            $last_separator = __( 'and', 'molongui-authorship' );
            if ( $custom and !empty( $options['co_authors_last_separator'] ) )
            {
                $last_separator = $options['co_authors_last_separator'];
            }
        }

        /*!
         * FILTER HOOK
         * Allows filtering the character to add after/around byline separators.
         *
         * @param string A space is added after/around byline separators by default.
         * @since 5.0.0
         */
        $space = apply_filters( 'molongui_authorship/byline_separator_space', '&nbsp;' );

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.6.18
         * @deprecated 5.0.0
         * @use        molongui_authorship/byline_separator_space
         */
        if ( !apply_filters_deprecated( 'authorship/byline_separator_autospace', array( true ), '5.0.0', 'authorship/byline_separator_space' ) )
        {
            $space = '';
        }

        $separator      = $separator.$space;
        $last_separator = $space.$last_separator.$space;

        /*!
         * FILTER HOOK
         * Allows filtering separators between co-authors names.
         *
         * The $count value is passed as additional parameter so separators may be different based on the number of
         * authors to display in the byline. This doesn't apply to automatic byline replacement ("Magic Bylines"), as
         * $count is not provided.
         *
         * @since 4.6.19
         * @since 5.0.0  Renamed from 'authorship/byline_separator' and 'authorship/byline_last_separator'.
         */
        $separator      = apply_filters( 'molongui_authorship/co_authors_separator', $separator, $count );
        $last_separator = apply_filters( 'molongui_authorship/co_authors_last_separator', $last_separator, $count );

        /*!
         * DEPRECATED
         * This filter hooks are scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.6.19
         * @deprecated 5.0.0
         * @use        molongui_authorship/co_authors_separator
         *             molongui_authorship/co_authors_last_separator
         */
        if ( apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
        {
            $separator      = apply_filters_deprecated( 'authorship/byline_separator', array( $separator, $count ), '5.0.0', 'molongui_authorship/co_authors_separator' );
            $last_separator = apply_filters_deprecated( 'authorship/byline_last_separator', array( $last_separator, $count ), '5.0.0', 'molongui_authorship/co_authors_last_separator' );
        }

        return array( $separator, $last_separator );
    }
    public static function add_byline_support()
    {
        $add_byline_support = Settings::get( 'add_byline_support', true );
        return apply_filters( 'molongui_authorship/add_byline_support', $add_byline_support );
    }
    public static function byline_takeover()
    {
        return ( Settings::is_guest_author_enabled() or Settings::is_co_authors_enabled() );
    }
    public static function get_public_post_status( $post_type = '' )
    {
        $post_status = array( 'draft', 'publish', 'future', 'pending', 'private' );

        if ( ( is_array( $post_type ) and in_array( 'attachment', $post_type ) ) or 'attachment' === $post_type )
        {
            $post_status[] = 'inherit';
        }

        return apply_filters( 'authorship/post_status', $post_status );
    }
    public static function is_post_type_enabled( $post_type = '', $post_id = null )
    {
        $post_type  = !empty( $post_type ) ? $post_type : Post::get_post_type( $post_id );
        $post_types = Settings::enabled_post_types();

        return in_array( $post_type, $post_types );
    }
    public static function add_author_meta_query( &$wp_query, $author_type = 'user', $author_id = 0 )
    {
        $author_type = in_array( $author_type, array( 'user', 'guest' ) ) ? $author_type : 'user';
        $author_id   = !empty( $author_id ) ? $author_id : ( is_admin() ? get_current_user_id() : ( !empty( $wp_query->query_vars['author'] ) ? $wp_query->query_vars['author'] : 0 ) );
        $meta_query = $wp_query->get( 'meta_query' );
        $meta_query = ( !empty( $meta_query ) and is_array( $meta_query ) ) ? $meta_query : array();
        $meta_query[] = array
        (
            array
            (
                'key'     => '_molongui_author',
                'value'   => $author_type.'-'.$author_id,
                'compare' => '==',
            ),
        );
        $meta_query = apply_filters( 'authorship/author_meta_query', $meta_query, $wp_query, $author_type, $author_id );

        $wp_query->set( 'meta_query', $meta_query );
    }
    public static function is_guest( $post_id = null )
    {
        if ( empty( $post_id ) )
        {
            global $post;

            if ( empty( $post ) )
            {
                return false;
            }

            $post_id = $post->ID;
        }
        $author = get_post_meta( $post_id, '_molongui_main_author', true );
        if ( !empty( $author ) )
        {
            if ( strncmp( $author, 'guest', strlen( 'guest' ) ) === 0 )
            {
                return true;
            }
        }
        return false;
    }
    public static function has_author_box( $post = null )
    {
        return true;
    }
    public static function has_guest_author( $post_id = null )
    {
        if ( empty( $post_id ) )
        {
            global $post;

            if ( empty( $post ) )
            {
                return false;
            }

            $post_id = $post->ID;
        }
        $authors = get_post_meta( $post_id, '_molongui_author', false );
        if ( empty( $authors ) )
        {
            return false;
        }
        foreach ( $authors as $author )
        {
            $prefix = 'guest';
            if ( strncmp( $author, $prefix, strlen( $prefix ) ) === 0 )
            {
                return true;
            }
        }
        return false;
    }
    public static function has_multiple_authors( $post_id = null )
    {
        if ( empty( $post_id ) )
        {
            global $post;
            if ( empty( $post ) )
            {
                return false;
            }
            $post_id = $post->ID;
        }

        return count( get_post_meta( $post_id, '_molongui_author', false ) ) > 1;
    }
    public static function is_multiauthor_link( $link )
    {
        $arg = '?molongui_byline=true';

        return ( strpos( $link, $arg ) !== false ? true : false );
    }

} // class
new Post();
