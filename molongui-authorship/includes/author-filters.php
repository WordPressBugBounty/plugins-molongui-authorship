<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;
use Molongui\Authorship\Common\Utils\Request;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Author_Filters
{
    private $javascript = '/assets/js/byline.e0b3.min.js';
    public function __construct()
    {
        if ( Post::byline_takeover() )
        {
            add_filter( 'get_the_author_ID', array( $this, 'filter_the_author_ID' ), PHP_INT_MAX, 3 );
            add_filter( 'the_author', array( $this, 'filter_author_name' ), PHP_INT_MAX, 1 );
            add_filter( 'get_the_author_display_name', array( $this, 'filter_the_author_display_name' ), PHP_INT_MAX, 3 );
            add_filter( 'get_the_archive_title', array( __CLASS__, 'filter_the_archive_title' ), PHP_INT_MAX, 1 );

            if ( Post::add_byline_support() )
            {
                $this->set_assets();
                add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
                add_filter( "authorship/byline_script_params", array( $this, 'localize_scripts' ) );
                add_filter( 'author_link', array( $this, 'filter_the_author_link' ), PHP_INT_MAX, 3 );
            }
            add_filter( 'get_the_author_description', array( $this, 'filter_the_author_description' ), PHP_INT_MAX, 3 );
            add_filter( 'get_the_archive_description', array( __CLASS__, 'filter_the_archive_description' ), PHP_INT_MAX, 1 );
            add_filter( 'pre_get_avatar_data', array( $this, 'filter_avatar' ), PHP_INT_MAX, 2 );
        }
    }
    public function set_assets()
    {
        $this->javascript = MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript;
        $this->javascript = apply_filters( 'authorship/byline/script', $this->javascript );
    }
    public function register_scripts()
    {
        Assets::register_script( $this->javascript, 'byline' );
    }
    public function enqueue_scripts()
    {
        Assets::enqueue_script( $this->javascript, 'byline' );
    }
    public function localize_scripts()
    {
        list( $separator, $last_separator ) = Post::get_byline_separators();

        $params = array
        (
            'byline_prefix'         => !empty( Settings::get( 'byline_custom_prefix_suffix' ) ) ? Settings::get( 'byline_prefix', '' ) : '',
            'byline_suffix'         => !empty( Settings::get( 'byline_custom_prefix_suffix' ) ) ? Settings::get( 'byline_suffix', '' ) : '',
            'byline_separator'      => $separator,
            'byline_last_separator' => $last_separator,
            'byline_link_title'     => apply_filters( 'authorship/byline/link_title', __( "View all posts by", 'molongui-authorship' ) ),
            'byline_link_class'     => apply_filters( 'authorship/byline/link_class', '' ),
            'byline_dom_tree'       => apply_filters( 'authorship/byline/dom_tree', '' ),
            'byline_dom_prepend'    => apply_filters( 'authorship/byline/dom_prepend', '' ),
            'byline_dom_append'     => apply_filters( 'authorship/byline/dom_append', '' ),
            'byline_decoder'        => apply_filters( 'authorship/author_link/filter_version', 'v3' ),
        );
        return apply_filters( 'authorship/byline/script_params', $params );
    }
    public function filter_the_author_ID( $id, $user_id = null, $original_user_id = null )
    {
        if ( ( is_author() or molongui_is_guest_author() ) and !in_the_loop() )
        {
            global $wp_query;
            $author_id = isset( $wp_query->guest_author_id ) ? $wp_query->guest_author_id : $wp_query->query_vars['author'];

            return $author_id;
        }
        return $id;
    }
    public function filter_author_name( $display_name )
    {
        if ( Request::is_from( 'admin' ) )
        {
            return $display_name;
        }
        $post = Post::get();
        if ( empty( $post ) )
        {
            return $display_name;
        }
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        if ( empty( $dbt ) )
        {
            return $display_name;
        }
        $old_display_name = $display_name;
        $filter = true;
        $args = array( 'post' => $post, 'dbt' => $dbt );
        if ( apply_filters( 'molongui_authorship_dont_filter_name', false, $display_name, $post, $dbt ) )
        {
            return $display_name;
        }
        $args = array( 'display_name' => &$display_name, 'post' => $post, 'dbt' => $dbt );
        if ( apply_filters_ref_array( 'molongui_authorship_do_filter_name', array( false, &$args ) ) )
        {
            return $display_name;
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        list( $filter, $display_name ) = apply_filters( '_authorship/filter/the_author', array( true, $display_name ), $args );
        if ( !$filter )
        {
            return is_null( $display_name ) ? $old_display_name : $display_name;
        }
        if ( is_author() or molongui_is_guest_author() )
        {
            return $display_name;
        }
        return Post::get_byline();
    }
    public function filter_the_author_display_name( $display_name, $user_id = null, $original_user_id = null )
    {
        /*!
         * FILTER HOOK
         * Filters the $display_name before it is filtered by Molongui Authorship.
         *
         * Returning a non-null value will effectively short-circuit the function, returning that value instead.
         *
         * Allows escaping this function preventing Molongui Authorship to filter the 'get_the_author_{$field}'.
         *
         * @param string $_display_name    Null value. Do not escape this function by default.
         * @param string $display_name     The user display name.
         * @param int    $user_id          The user ID.
         * @param int    $original_user_id The original user ID.
         * @since 4.7.0
         * @since 5.0.0 Renamed from 'authorship/pre_get_the_author_display_name'
         */
        $_display_name = apply_filters( 'authorship/pre_the_author_display_name', null, $display_name, $user_id, $original_user_id );
        if ( null !== $_display_name )
        {
            return $_display_name;
        }
        if ( ( !empty( $original_user_id ) or $original_user_id === 0 )
             and !apply_filters( 'molongui_authorship_bypass_original_user_id_if', false )
             and !Helpers::is_block_editor()
        ){
            return $display_name;
        }
        if ( Request::is_from( 'admin' ) and !Request::is_from( 'editor' ) )
        {
            return $display_name;
        }
        global $post;
        if ( empty( $post ) or !$post->ID )
        {
            return $display_name;
        }
        $post_id = apply_filters( 'molongui_authorship_filter_the_author_display_name_post_id', $post->ID, $post, $display_name );
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 100 );
        if ( empty( $dbt ) ) return $display_name;
        if ( apply_filters( 'molongui_authorship_dont_filter_the_author_display_name', false, $display_name, $user_id, $original_user_id, $post, $dbt ) )
        {
            return $display_name;
        }
        if ( is_author() or molongui_is_guest_author() )
        {
            return $display_name;
        }
        return Post::get_byline( $post_id );
    }
    public static function filter_the_archive_title( $title )
    {
        if ( !is_author() and !molongui_is_guest_author() )
        {
            return $title;
        }

        global $wp_query;
        $options = Settings::get();
        if ( molongui_is_guest_author() and isset( $wp_query->guest_author_id ) )
        {
            $prefix  = ( !empty( $options['guest_archive_title_checkbox'] ) and !empty( $options['guest_archive_title_prefix'] ) ) ? $options['guest_archive_title_prefix'] . ' ' : '';
            $suffix  = ( !empty( $options['guest_archive_title_checkbox'] ) and !empty( $options['guest_archive_title_suffix'] ) ) ? ' ' . $options['guest_archive_title_suffix'] : '';

            return $prefix . ' ' . get_post_field( 'post_title', $wp_query->guest_author_id ) . ' ' . $suffix;
        }
        if ( $wp_query->query_vars['author'] )
        {
            add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
            $user = get_user_by( 'id', $wp_query->query_vars['author'] );
            remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
            $prefix  = ( !empty( $options['user_archive_title_checkbox'] ) and !empty( $options['user_archive_title_prefix'] ) ) ? $options['user_archive_title_prefix'] . ' ' : '';
            $suffix  = ( !empty( $options['user_archive_title_checkbox'] ) and !empty( $options['user_archive_title_suffix'] ) ) ? ' ' . $options['user_archive_title_suffix'] : '';
            return $prefix . $user->display_name . $suffix;
        }
        return $title;
    }
    public function filter_the_author_link( $link, $author_id, $author_nicename )
    {
        $original_link = $link;
        if ( Request::is_from( 'admin' ) )
        {
            return $original_link;
        }
        $link = apply_filters( 'authorship/pre_author_link', null, $original_link, $author_id, $author_nicename );
        if ( null !== $link )
        {
            return $link;
        }

        $link = self::the_author_link( $original_link );

        return empty( $link ) ? $original_link : $link;
    }
    public static function the_author_link( $link, $post_id = null )
    {
        $options = Settings::get();
        if ( empty( $options['byline_name_link'] ) )
        {
            return apply_filters( 'authorship/author_link', '#molongui-disabled-link', array( 'url' => '#molongui-disabled-link', 'link' => $link, 'pid' => $post_id ) );
        }
        if ( empty( $post_id ) )
        {
            $post = Post::get();
            if ( empty( $post ) )
            {
                return $link;
            }
            $post_id = apply_filters( 'molongui_authorship_filter_link_post_id', isset( $post->ID ) ? $post->ID : null, $post, $link );

            if ( !$post_id )
            {
                return $link;
            }
        }
        $post_authors = Post::get_authors( $post_id );
        if ( !$post_authors )
        {
            return $link;
        }
        $primary_author = null;
        if ( is_array( $post_authors ) && ! empty( $post_authors ) )
        {
            $candidate = reset( $post_authors );

            if ( is_object( $candidate ) )
            {
                $primary_author = $candidate;
            }
        }
        if ( ! $primary_author || empty( $primary_author->type ) )
        {
            return $link;
        }

        $count            = count( $post_authors );
        $show_remaining   = false;
        $names_to_display = 1;
        if ( Post::has_multiple_authors( $post_id ) and $options['co_authors_in_byline'] and apply_filters( 'molongui_authorship/link_coauthor_name', true ) )
        {
            /*! This filter is documented in includes/post.php */
            $names_to_display = apply_filters( 'molongui_authorship/co_authors_in_byline', Settings::get( 'co_authors_in_byline_format', 'all' ), $post_id, $post_authors );
            if ( is_numeric( $names_to_display ) )
            {
                $names_to_display = min( $names_to_display, count( $post_authors ) );
            }
            else
            {
                $names_to_display = count( $post_authors );
            }
            switch ( apply_filters( 'authorship/author_link/filter_version', 'v3' ) )
            {
                case 'v1':

                    $url = '';
                    $que = '%3F'; // Encoded into a valid ASCII format: '%3F' = '?'
                    $amp = '%26'; // Encoded into a valid ASCII format: '%26' = '&'

                    for ( $i = 0; $i < $names_to_display; $i++ )
                    {
                        switch ( $i )
                        {
                            case 0:
                                $function  = 'get_archive_url'; // 'get_archive_url' must be used so returned $link is a valid URL.
                                $default   = WP::home_url();    // To ensure we return a valid URL even if main author is a guest.
                                $delimiter = '';                // Do not append anything at the beginning of the returned $link.
                                $querychar = '';
                                break;

                            case 1:
                                $function  = 'get_archive_url';        // 'get_slug' could be used to return the author nicename.
                                $default   = 'molongui-disabled-link'; // Do NOT add a leading '#'!!!
                                $disabled  = $primary_author->type == 'guest' ? apply_filters( '_authorship/filter/link/disable_main', true, $primary_author->type ) : false;
                                $delimiter = $disabled ? 'molongui_byline=old'.$amp.'m_main_disabled=true'.$amp.'mca=' : 'molongui_byline=old'.$amp.'mca=';
                                $querychar = $que;
                                break;

                            default:
                                $function  = 'get_archive_url';        // 'get_slug' could be used to return the author nicename.
                                $default   = 'molongui-disabled-link'; // Do NOT add a leading '#'!!!
                                $delimiter = 'mca=';
                                $querychar = $amp;
                                break;
                        }
                        $author = new Author( $post_authors[$i]->id, $post_authors[$i]->type );
                        $data   = $author->$function();
                        $data   = $data == '#molongui-disabled-link' ? $default : $data;
                        if ( $i === 1 )
                        {
                            $tmp = parse_url( $data );
                            if ( isset( $tmp['query'] ) ) $querychar = $amp;
                        }
                        $url .= $querychar . $delimiter . $data;
                    }

                    break;
                case 'v2':

                    $url = '';
                    $que = '%3F'; // Encoded into a valid ASCII format: '%3F' = '?'
                    $amp = '%26'; // Encoded into a valid ASCII format: '%26' = '&'

                    for ( $i = 0; $i < $names_to_display; $i++ )
                    {
                        $author = new Author( $post_authors[$i]->id, $post_authors[$i]->type );
                        $data   = $author->get_archive_url();

                        switch ( $i )
                        {
                            case 0:

                                if ( '#molongui-disabled-link' == $data )
                                {
                                    $data = WP::home_url();
                                }
                                $url = esc_url( $data );

                                break;

                            case 1:

                                if ( '#molongui-disabled-link' == $data )
                                {
                                    $data = 'molongui-disabled-link';
                                }

                                $disabled = $primary_author->type == 'guest' ? apply_filters( '_authorship/filter/link/disable_main', true, $primary_author->type ) : false;
                                if ( $disabled )
                                {
                                    $url = esc_url( add_query_arg( array
                                    (
                                        'molongui_byline' => 'old',
                                        'm_main_disabled' => 'true',
                                        'mca' => $data,
                                    ), $url ) );
                                }
                                else
                                {
                                    $url = esc_url( add_query_arg( array
                                    (
                                        'molongui_byline' => 'old',
                                        'mca' => $data,
                                    ), $url ) );
                                }

                                break;

                            default:

                                if ( '#molongui-disabled-link' == $data )
                                {
                                    $data = 'molongui-disabled-link';
                                }
                                $url = esc_url( add_query_arg( 'mca', $data, $url ) );

                                break;
                        }
                    }

                    break;
                case 'v3':
                    $byline_authors = $post_authors;
                    if ( $names_to_display < $count )
                    {
                        $byline_authors = array_slice( $post_authors, 0, $names_to_display );
                        $show_remaining = true;
                    }

                    add_filter( 'wp_print_footer_scripts', function() use ( $post_id, $byline_authors, $names_to_display, $count, $show_remaining )
                    {
                        $data = array();
                        foreach( $byline_authors as $byline_author )
                        {
                            $author = new Author( $byline_author->id, $byline_author->type );
                            $data[] = array( 'type' => $author->get_type(), 'id' => $author->get_id(), 'name' => esc_html( $author->get_display_name() ), 'url' => esc_url( $author->get_archive_url() ) );
                        }
                        if ( $show_remaining and $count > $names_to_display )
                        {
                            /*! translators: Not displayed co-authors count. */
                            $data[] = array( 'type' => '', 'id' => '', 'name' => sprintf( _x( '%d more', 'Not displayed co-authors count', 'molongui-authorship' ), $count - $names_to_display ), 'url' => '' );
                        }
                        if ( !empty( $data ) )
                        {
                            echo '<script data-type="'.MOLONGUI_AUTHORSHIP_NAME.'-byline-data" data-id="'.$post_id.'">var molongui_authorship_byline_data_'.$post_id.' = '.json_encode( $data ).';' . '</script>';
                        }
                    });

                    $author     = new Author( $byline_authors[0]->id, $byline_authors[0]->type );
                    $author_url = $author->get_archive_url();

                    if ( '#molongui-disabled-link' === $author_url or empty( $author_url ) )
                    {
                        $author_url = $link;
                    }
                    $url = esc_url( add_query_arg( 'mab_v3', $post_id, $author_url ) );

                    break;
            }
        }
        elseif ( $primary_author && $primary_author->type === 'guest' )
        {
            $author = new Author( $primary_author->id, $primary_author->type );
            $url    = $author->get_archive_url();
        }
        elseif ( $primary_author && $primary_author->type === 'user' && ! in_the_loop() )
        {
            $author = new Author( $primary_author->id, $primary_author->type );
            $url    = $author->get_archive_url();
        }
        else
        {
            $url = $link;
        }
        $modifiers_tag = ( !empty( $options['byline_custom_prefix_suffix'] ) and ( !empty( $options['byline_prefix'] ) or !empty( $options['byline_suffix'] ) ) and Plugin::has_pro() ) ? '?m_bm=true' : '';
        return apply_filters( 'authorship/author_link', $url.$modifiers_tag, array( 'url' => $url, 'modifiers' => $modifiers_tag, 'link' => $link, 'pid' => $post_id, 'authors' => $post_authors ) );
    }
    public static function the_author_page_link( $link )
    {
        global $wp_query;
        if ( !is_author() and !molongui_is_guest_author() )
        {
            return $link;
        }
        if ( molongui_is_guest_author() and isset( $wp_query->guest_author_id ) )
        {
            $author = new Author( $wp_query->guest_author_id, 'guest' );
            return $author->get_archive_url();
        }
        if ( $wp_query->query_vars['author'] )
        {
            $author = new Author( $wp_query->query_vars['author'], 'user' );
            return $author->get_archive_url();
        }
        return $link;
    }
    public function filter_the_author_description( $description, $user_id = null, $original_user_id = null )
    {
        /*!
         * FILTER HOOK
         * Filters the $description before it is filtered by Molongui Authorship.
         *
         * Returning a non-null value will effectively short-circuit the function, returning that value instead.
         *
         * Allows escaping this function preventing Molongui Authorship to filter 'the_author_description'.
         *
         * @param bool   $_description     Null. Do not escape this function by default.
         * @param string $description      The user description.
         * @param int    $user_id          The user ID.
         * @param int    $original_user_id The original user ID.
         * @since 5.0.0
         */
        $_description = apply_filters( 'authorship/pre_the_author_description', null, $description, $user_id, $original_user_id );
        if ( null !== $_description )
        {
            return $_description;
        }
        if ( !is_author() and !molongui_is_guest_author() )
        {
            return $description;
        }

        $the_query = WP::the_query();
        $author_id = 0;
        if ( molongui_is_guest_author() and isset( $the_query->guest_author_id ) )
        {
            $author_type = 'guest';
            $author_id   = $the_query->guest_author_id;
        }
        elseif ( !empty( $the_query->query_vars['author'] ) )
        {
            $author_type = 'user';
            $author_id   = $the_query->query_vars['author'];
        }

        if ( empty( $author_id ) )
        {
            return $description;
        }
        switch( $author_type )
        {
            case 'guest':
                $description = get_post_field( 'post_content', $author_id );
                break;

            case 'user':
            default:

                $author = new Author( $author_id, $author_type );
                remove_filter( 'get_the_author_description', array( $this, 'filter_the_author_description' ), PHP_INT_MAX );
                $user_bio = $author->get_description();
                add_filter( 'get_the_author_description', array( $this, 'filter_the_author_description' ), PHP_INT_MAX, 3 );

                $description = nl2br( $user_bio );

                break;
        }

        return $description;
    }
    public static function filter_the_archive_description( $description )
    {
        if ( !is_author() and !molongui_is_guest_author() )
        {
            return $description;
        }

        global $wp_query;
        if ( molongui_is_guest_author() and isset( $wp_query->guest_author_id ) )
        {
            return get_post_field( 'post_content', $wp_query->guest_author_id );
        }
        if ( $wp_query->query_vars['author'] )
        {
            $user = new Author( $wp_query->query_vars['author'], 'user' );

            return nl2br( $user->get_description() );
        }
        return $description;
    }
    public function filter_avatar( $args, $id_or_email )
    {
        if ( !Settings::is_enabled( 'local-avatar' ) )
        {
            return $args;
        }
        $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        if ( empty( $dbt ) )
        {
            return $args;
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        if ( apply_filters( 'authorship/get_avatar_data/skip', false, $args, $dbt ) )
        {
            return $args;
        }

        $email        = false;
        $author       = new \stdClass();
        $local_avatar = false;
        if ( is_object( $id_or_email ) and isset( $id_or_email->comment_ID ) )
        {
            $id_or_email = get_comment( $id_or_email );
        }
        if ( is_numeric( $id_or_email ) )
        {
            $author->id     = absint( $id_or_email );
            $author->type   = 'user';
            $author->object = get_user_by( 'id', $author->id );
            if ( isset( $author->object->guest_id ) )
            {
                $author->id   = $author->object->guest_id;
                $author->type = 'guest';
            }

            if ( isset( $author->object->user_email ) )
            {
                $email = $author->object->user_email;
            }
            else
            {
                return $args;
            }
        }
        elseif ( is_string( $id_or_email ) )
        {
            if ( !$id_or_email )
            {
                if ( molongui_is_guest_author() )
                {
                    global $wp_query;
                    $author->id   = $wp_query->guest_author_id;
                    $author->type = 'guest';
                }
                else
                {
                    /*!
                     * [PRIVATE] FILTER HOOK
                     * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
                     * If you choose to use it, you do so at your own risk, as it may cause code issues.
                     */
                    $post_id = apply_filters( '_authorship/get_avatar_data/filter/post_id', null );

                    $authors = Post::get_authors( $post_id, 'id' );
                    if ( $authors )
                    {
                        $author->id   = $authors[0];
                        $author->type = 'guest';
                    }
                }
            }
            elseif ( strpos( $id_or_email, '@md5.gravatar.com' ) )
            {
                return $args;
            }
            else
            {
                $email = $id_or_email;
            }
        }
        elseif ( $id_or_email instanceof \WP_User )
        {
            $author->id     = $id_or_email->ID;
            $author->type   = 'user';
            $author->object = $id_or_email;

            $email = $author->object->user_email;
        }
        elseif ( $id_or_email instanceof \WP_Post )
        {
            $author->object = get_user_by( 'id', (int) $id_or_email->post_author );
            $author->id     = $author->object->get_id();
            $author->type   = isset( $author->object->guest_id ) ? 'guest' : 'user';

            $email = $author->object->user_email;
        }
        elseif ( $id_or_email instanceof \WP_Comment )
        {
            if ( !empty( $id_or_email->comment_author_email ) )
            {
                $email = $id_or_email->comment_author_email;
            }
            elseif ( !empty( $id_or_email->user_id ) )
            {
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $author->id     = (int) $id_or_email->user_id;
                $author->type   = 'user';
                $author->object = get_user_by( 'id', $author->id );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );

                $email = $author->object->user_email;
            }
        }
        if ( empty( $author->type ) )
        {
            if ( empty( $email ) )
            {
                return $args;
            }

            if ( $author->object = Authors::get_author_by( 'user_email', $email, 'user' ) )
            {
                $author->id   = $author->object->get_id();
                $author->type = 'user';
            }
            elseif ( $author->object = Authors::get_author_by( '_molongui_guest_author_mail', $email, 'guest' ) )
            {
                $author->id   = $author->object->get_id();
                $author->type = 'guest';
            }
            else
            {
                return $args;
            }
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        $author = apply_filters( '_authorship/get_avatar_data/filter/author', $author, $id_or_email, $dbt );
        switch ( $author->type )
        {
            case 'user':

                $user_local_avatar = get_user_meta( $author->id, 'molongui_author_image_url', true );
                $local_avatar      = $user_local_avatar ? $user_local_avatar : '';
                break;

            case 'guest':

                $local_avatar = has_post_thumbnail( $author->id ) ? get_the_post_thumbnail_url( $author->id, $args['size'] ) : '';
                add_filter( 'authorship/get_avatar_data/skip', '__return_true' );
                if ( !$local_avatar )
                {
                    $local_avatar = get_avatar_url( $email, $args );
                }
                remove_filter( 'authorship/get_avatar_data/skip', '__return_true' );
                break;
        }
        if ( $local_avatar )
        {
            $args['found_avatar'] = true;
            $args['url'] = apply_filters( 'authorship/get_avatar_data/filter/url', $local_avatar, $id_or_email, $args );
        }
        return $args;
    }

} // class
new Author_Filters();
