<?php

use Molongui\Authorship\Author;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;
defined( 'ABSPATH' ) or exit;
if ( !function_exists( 'molongui_get_author_by' ) )
{
    function molongui_get_author_by( $field, $value, $type = 'user', $meta = true )
    {
        if ( $type == 'user' )
        {
            $user_query = new WP_User_Query
            (
                array
                (
                    'search'        => $value,
                    'search_fields' => array( $field ),
                )
            );
            $user = $user_query->get_results();

            return ( empty( $user['0'] ) ? false : $user['0'] );
        }
        elseif ( $type == 'guest' )
        {
            if ( $meta )
            {
                $args = array
                (
                    'post_type'  => MOLONGUI_AUTHORSHIP_CPT,
                    'meta_query' => array
                    (
                        array
                        (
                            'key'     => $field,
                            'value'   => $value,
                            'compare' => '=',
                        ),
                    ),
                    'site_id'    => get_current_blog_id(),
                    'language'   => array( Helpers::class, 'get_language' ),
                );
            }
            else
            {
                $args = array
                (
                    $field      => $value,
                    'post_type' => MOLONGUI_AUTHORSHIP_CPT,
                    'site_id'   => get_current_blog_id(),
                    'language'  => array( Helpers::class, 'get_language' ),
                );
            }
            $guest = Cache::query( $args, 'guests' );
            if ( $guest->have_posts() ) return ( empty( $guest->posts['0'] ) ? false : $guest->posts['0'] );
        }
        return false;
    }
}
if ( !function_exists( 'molongui_get_author_type_by_nicename' ) )
{
    function molongui_get_author_type_by_nicename( $nicename )
    {
        if ( $guest = molongui_get_author_by( 'name', $nicename, 'guest', false ) )
        {
            return 'guest';
        }
        elseif ( $author = molongui_get_author_by( 'user_nicename', $nicename ) )
        {
            return 'user';
        }
        return 'not_found';
    }
}
function molongui_find_authors()
{
    global $wp_query;
    $authors = array();
    if ( !empty( $wp_query->query_vars['guest-author-name'] ) )
    {
        if ( $guest = molongui_get_author_by( 'name', $wp_query->query_vars['guest-author-name'], 'guest', false ) )
        {
            $authors[0]       = new stdClass();
            $authors[0]->id   = (int)$guest->ID;
            $authors[0]->type = 'guest';
            $authors[0]->ref  = 'guest-'.$guest->ID;
        }
        else
        {
            if ( $user = molongui_get_author_by( 'user_nicename', $wp_query->query_vars['guest-author-name'] ) )
            {
                $authors[0]       = new stdClass();
                $authors[0]->id   = (int)$user->ID;
                $authors[0]->type = 'user';
                $authors[0]->ref  = 'user-'.$user->ID;
            }
        }
    }
    elseif ( is_author() and !empty( $wp_query->query_vars['author_name'] ) )
    {
        $authors[0]       = new stdClass();
        $authors[0]->id   = 0;
        $authors[0]->type = 'user';
        $authors[0]->ref  = 'user-0';
        if ( $user = molongui_get_author_by( 'user_nicename', $wp_query->query_vars['author_name'] ) )
        {
            $authors[0]->id  = (int)$user->ID;
            $authors[0]->ref = 'user-'.$user->ID;
        }
    }
    else
    {
        $post_id = Post::get_id();

        if ( empty( $post_id ) )
        {
            return false;
        }

        $authors = authorship_get_post_authors( $post_id );
    }
    if ( empty( $authors ) or $authors[0]->id == 0 )
    {
        return false;
    }
    return $authors;
}
if ( !function_exists('authorship_get_users') )
{
    function authorship_get_users( $args = null )
    {
        $defaults = array
        (
            'role__in' => apply_filters( 'authorship/user/roles', array( 'administrator', 'editor', 'author', 'contributor' ) ),
            'include'  => apply_filters( 'authorship/user/include', '' ),
            'exclude'  => apply_filters( 'authorship/user/exclude', '' ),
            'order'    => apply_filters( 'authorship/user/order', 'ASC' ),
            'orderby'  => apply_filters( 'authorship/user/orderby', 'name' ),
            'site_id'  => get_current_blog_id(),
            'language' => apply_filters( 'authorship/get_users/language', array( Helpers::class, 'get_language' ) ),
        );

        $parsed_args = wp_parse_args( $args, $defaults );
        $parsed_args['order'] = strtolower( $parsed_args['order'] );
        $users = Cache::query( $parsed_args, 'users' );
        if ( is_array( $parsed_args['role__in'] ) and in_array( 'molongui_no_role', $parsed_args['role__in'] ) )
        {
            $no_role_ids = wp_get_users_with_no_role(); // Array of user IDs as strings.

            if ( !empty( $no_role_ids ) )
            {
                if ( isset( $parsed_args['fields'] ) and 'ID' === $parsed_args['fields'] )
                {
                    $no_role_users = $no_role_ids;

                    $users = array_merge( $users, $no_role_users );
                    sort( $users );
                }
                else
                {
                    $no_role_users = Cache::query( array( 'include' => $no_role_ids ), 'users' );

                    $users = array_merge( $users, $no_role_users );
                    $field = $parsed_args['orderby'];
                    usort( $users, function($a, $b) use ( $field ) { return strcasecmp( $a->$field, $b->$field ); } );
                }

                if ( $parsed_args['order'] == 'desc' ) $users = array_reverse( $users );
            }
        }
        return $users;
    }
}
if ( !function_exists( 'molongui_get_guests' ) )
{
    function molongui_get_guests( $args = null )
    {
        $defaults = array
        (
            'post_type'      => MOLONGUI_AUTHORSHIP_CPT,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'post__in'       => apply_filters( 'authorship/guest/include', '' ),
            'post__not_in'   => apply_filters( 'authorship/guest/exclude', '' ),
            'fields'         => 'all',
            'order'          => apply_filters( 'authorship/guest/order', 'ASC' ),
            'orderby'        => apply_filters( 'authorship/guest/orderby', 'title' ),
            'no_found_rows'  => true,
            'dropdown'       => false,
            'site_id'        => get_current_blog_id(),
            'language'       => apply_filters( 'authorship/get_guests/language', array( Helpers::class, 'get_language' ) ),
        );

        $parsed_args = wp_parse_args( $args, $defaults );
        $parsed_args['post_type'] = MOLONGUI_AUTHORSHIP_CPT;
        $guests = Cache::query( $parsed_args, 'guests' );
        if ( $parsed_args['dropdown'] )
        {
            global $post;
            $post_authors = authorship_get_post_authors( $post->ID, 'id' );
            $output = '';
            if ( $guests->have_posts() )
            {
                $output .= '<select name="_molongui_author" class="multiple">';
                foreach( $guests->posts as $guest )
                {
                    $output .= '<option value="' . $guest->ID . '" ' . ( in_array( $guest->ID, $post_authors ) ? 'selected' : '' ) . '>' . $guest->post_title . '</option>';
                }
                $output .= '</select>';
                $output .= '<div><ul id="molongui-authors" class="sortable"></ul></div>';
            }
            return $output;
        }
        return $guests->posts;
    }
}
if ( !function_exists( 'molongui_get_authors' ) )
{
    function molongui_get_authors( $type = 'authors', $include_users = array(), $exclude_users = array(), $include_guests = array(), $exclude_guests = array(), $order = 'ASC', $orderby = 'name', $get_data = false, $min_post_count = 0, $post_types = array( 'post' ) )
    {
        $authors = array();
        $options = Settings::get();

        $original_orderby = $orderby;
        if ( !empty( $orderby ) )
        {
            switch ( $orderby )
            {
                case 'id':
                    $orderby = 'ID';
                break;
                case 'rand':
                    $orderby = 'ID';
                break;
                case 'post_count':
                    $min_post_count = !empty( $min_post_count ) ? (int)$min_post_count : 1;
                break;
                case 'first_name':
                case 'last_name':
                case 'mail':
                case 'job':
                case 'company':
                    $get_data = true;
                break;
            }
        }
        if ( $min_post_count )
        {
            $get_data = true;
        }
        if ( $type == 'authors' or $type == 'users' )
        {
            $args = array
            (
                'include' => $include_users,
                'exclude' => $exclude_users,
                'order'   => $order,
                'orderby' => $orderby,
            );
            $users = authorship_get_users( $args ); // Array of WP_User objects.
            if ( $get_data )
            {
                foreach ( $users as $user )
                {
                    $author    = new Author( $user->ID, 'user', $user );
                    $authors[] = $author->get_data();
                    if ( $min_post_count )
                    {
                        $skip = false;
                        end( $authors );
                        $key = key( $authors );

                        if ( 1 === (int)$min_post_count )
                        {
                            if ( !authorship_author_has_posts( $authors[$key], $post_types ) )
                            {
                                $skip = true;
                            }
                        }
                        else
                        {
                            $total_post_count = 0;
                            foreach ( $post_types as $post_type )
                            {
                                $total_post_count = $total_post_count + (int)$authors[$key]['post_count'][$post_type];
                            }

                            if ( $total_post_count < (int)$min_post_count )
                            {
                                $skip = true;
                            }
                        }
                        if ( $skip )
                        {
                            unset( $authors[$key] );
                            continue;
                        }
                    }
                }
            }
            else
            {
                foreach ( $users as $user )
                {
                    $authors[] = array( 'id' => $user->ID, 'type' => 'user', 'ref' => 'user-'.$user->ID, 'name' => $user->display_name );
                }
            }
        }
        if ( ( $type == 'authors' or $type == 'guests' ) and !empty( $options['guest_authors'] ) )
        {
            if ( isset( $orderby ) and $orderby == 'include' ) $orderby = 'post__in';
            $guests = molongui_get_guests( array( 'post__in' => $include_guests, 'post__not_in' => $exclude_guests, 'order' => $order, 'orderby' => $orderby ) ); // Array of stdClass objects.
            if ( $get_data )
            {
                foreach ( $guests as $guest )
                {
                    $author    = new Author( $guest->ID, 'guest', $guest );
                    $authors[] = $author->get_data();
                    if ( $min_post_count )
                    {
                        $skip = false;
                        end( $authors );
                        $key = key( $authors );

                        if ( 1 === (int)$min_post_count )
                        {
                            if ( !authorship_author_has_posts( $authors[$key], $post_types ) )
                            {
                                $skip = true;
                            }
                        }
                        else
                        {
                            $total_post_count = 0;
                            foreach ( $post_types as $post_type )
                            {
                                $total_post_count = $total_post_count + (int)$authors[$key]['post_count'][$post_type];
                            }

                            if ( $total_post_count < (int)$min_post_count )
                            {
                                $skip = true;
                            }
                        }
                        if ( $skip )
                        {
                            unset( $authors[$key] );
                            continue;
                        }
                    }
                }
            }
            else
            {
                foreach ( $guests as $guest ) $authors[] = array( 'id' => $guest->ID, 'type' => 'guest', 'ref' => 'guest-'.$guest->ID, 'name' => $guest->post_title );
            }
        }

        /*!
         * FILTER HOOK
         *
         * Allows filtering the number of authors to be returned. Value 0 (all) is supported, but should be used with
         * caution on larger sites.
         *
         * @param bool  By default, return all authors.
         * @since 4.9.6
         */
        if ( $limit = apply_filters( 'molongui_authorship/get_authors_limit', 0 ) )
        {
            $authors = array_slice( $authors, 0, $limit );
        }
        
        /*!
         * FILTER HOOK
         *
         * Allows filtering the number of authors to be returned. Value 0 (all) is supported, but should be used with
         * caution on larger sites.
         *
         * @param bool  By default, return all authors.
         * @since 4.9.6
         */
        /*!
         * FILTER HOOK
         *
         * Allows preventing the list of authors to be further sorted.
         *
         * @param bool  By default, skip sorting if items are already ordered.
         * @since 4.7.8
         */
        if ( apply_filters( 'authorship/get_authors/dont_sort', in_array( $orderby, array( 'include', 'post__in' ) ) ) )
        {
            return $authors;
        }
        switch ( $original_orderby )
        {
            case 'user_roles':

                usort( $authors, function ( $a, $b ) use ( $orderby )
                {
                    return strcasecmp( $a[$orderby][0], $b[$orderby][0] );
                });

            break;

            case 'post_count':

                usort( $authors, function ( $a, $b ) use ( $orderby, $post_types )
                {
                    return $a[$orderby][$post_types[0]] - $b[$orderby][$post_types[0]];
                });

            break;

            case 'id':
            case 'ID':

                $key = 'id';
                usort( $authors, function ( $a, $b ) use ( $key )
                {
                    return $a[$key] - $b[$key];
                });

            break;

            case 'rand':
            case 'random':
                shuffle( $authors );

            break;

            default:
                usort( $authors, function ( $a, $b ) use ( $orderby )
                {
                    return strcasecmp( $a[$orderby], $b[$orderby] );
                });

            break;
        }
        if ( 'desc' == $order )
        {
            $authors = array_reverse( $authors );
        }
        return $authors;
    }
}
if ( !function_exists( 'authorship_get_archived_users' ) )
{
    function authorship_get_archived_users()
    {
        $archived_users = get_users( array
        (
            'fields'      => 'ids',
            'numberposts' => -1,
            'meta_key'    => 'molongui_author_archived',
            'meta_value'  => 1,
        ));

        return $archived_users;
    }
}
if ( !function_exists( 'authorship_get_archived_guests' ) )
{
    function authorship_get_archived_guests()
    {
        $archived_guests = get_posts( array
        (
            'fields'      => 'ids',
            'numberposts' => -1,
            'post_type'   => 'guest_author',
            'meta_key'    => '_molongui_guest_author_archived',
            'meta_value'  => 1,
        ));

        return $archived_guests;
    }
}
function authorship_decode_author_ref( $post_id )
{

}