<?php
/*!
 * Utility class to manage and retrieve author data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Authors
{
    public static function find()
    {
        global $wp_query;
        $authors = array();
        if ( !empty( $wp_query->query_vars['guest-author-name'] ) )
        {
            if ( $guest = Author::get_by( 'name', $wp_query->query_vars['guest-author-name'], 'guest', false ) )
            {
                $authors[0]       = new \stdClass();
                $authors[0]->id   = (int)$guest->ID;
                $authors[0]->type = 'guest';
                $authors[0]->ref  = 'guest-'.$guest->ID;
            }
            else
            {
                if ( $user = Author::get_by( 'user_nicename', $wp_query->query_vars['guest-author-name'] ) )
                {
                    $authors[0]       = new \stdClass();
                    $authors[0]->id   = (int)$user->ID;
                    $authors[0]->type = 'user';
                    $authors[0]->ref  = 'user-'.$user->ID;
                }
            }
        }
        elseif ( is_author() and !empty( $wp_query->query_vars['author_name'] ) )
        {
            $authors[0]       = new \stdClass();
            $authors[0]->id   = 0;
            $authors[0]->type = 'user';
            $authors[0]->ref  = 'user-0';
            if ( $user = Author::get_by( 'user_nicename', $wp_query->query_vars['author_name'] ) )
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

            $authors = Post::get_authors( $post_id );
        }
        if ( empty( $authors ) or $authors[0]->id == 0 )
        {
            return false;
        }
        return $authors;
    }
    public static function get_users( $args = null )
    {
        $defaults = array
        (
            'role__in' => Settings::enabled_user_roles(),
            'include'  => apply_filters( 'authorship/user/include', '' ),
            'exclude'  => apply_filters( 'authorship/user/exclude', '' ),
            'order'    => apply_filters( 'authorship/user/order', 'ASC' ),
            'orderby'  => apply_filters( 'authorship/user/orderby', 'name' ),
            'site_id'  => get_current_blog_id(),
            'language' => apply_filters( 'authorship/get_users/language', Helpers::get_language() ),
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
    public static function get_guests( $args = null )
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
            'language'       => apply_filters( 'authorship/get_guests/language', Helpers::get_language() ),
        );

        $parsed_args = wp_parse_args( $args, $defaults );
        $parsed_args['post_type'] = MOLONGUI_AUTHORSHIP_CPT;
        $guests = Cache::query( $parsed_args, 'guests' );
        if ( $parsed_args['dropdown'] )
        {
            global $post;
            $post_authors = Post::get_authors( $post->ID, 'id' );
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
    public static function get_authors( $type = 'authors', $include_users = array(), $exclude_users = array(), $include_guests = array(), $exclude_guests = array(), $order = 'ASC', $orderby = 'name', $get_data = false, $min_post_count = 0, $post_types = array( 'post' ) )
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
            $users = self::get_users( $args ); // Array of WP_User objects.
            if ( $get_data )
            {
                foreach ( $users as $user )
                {
                    $author    = new Author( $user, 'user' );
                    $authors[] = $author->get_data();
                    if ( $min_post_count )
                    {
                        $skip = false;
                        end( $authors );
                        $key = key( $authors );

                        if ( 1 === (int)$min_post_count )
                        {
                            if ( !$author->has_posts( $post_types ) )
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
                    $authors[] = array
                    (
                        'id'   => $user->ID,
                        'type' => 'user',
                        'ref'  => 'user-'.$user->ID,
                        'name' => $user->display_name,
                    );
                }
            }
        }
        if ( ( $type == 'authors' or $type == 'guests' ) and !empty( $options['guest_author_enabled'] ) )
        {
            if ( isset( $orderby ) and $orderby == 'include' )
            {
                $orderby = 'post__in';
            }
            $guests = self::get_guests( array( 'post__in' => $include_guests, 'post__not_in' => $exclude_guests, 'order' => $order, 'orderby' => $orderby ) ); // Array of stdClass objects.
            if ( $get_data )
            {
                foreach ( $guests as $guest )
                {
                    $author    = new Author( $guest, 'guest' );
                    $authors[] = $author->get_data();
                    if ( $min_post_count )
                    {
                        $skip = false;
                        end( $authors );
                        $key = key( $authors );

                        if ( 1 === (int)$min_post_count )
                        {
                            if ( !$author->has_posts( $post_types ) )
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
                foreach ( $guests as $guest )
                {
                    $authors[] = array
                    (
                        'id'   => $guest->ID,
                        'type' => 'guest',
                        'ref'  => 'guest-'.$guest->ID,
                        'name' => $guest->post_title,
                    );
                }
            }
        }

        /*!
         * FILTER HOOK
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
                    return $a[$orderby] - $b[$orderby];
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
    public static function get_archived_users()
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
    public static function get_archived_guests()
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
    public static function get_random_author( $type = 'author', $user_role = array() )
    {
        if ( 'user' === $type )
        {
            return self::get_random_user_by_role( $user_role );
        }
        elseif ( 'guest' === $type )
        {
            return self::get_random_guest();
        }
        else
        {
            $random_author = self::get_random_user_by_role( $user_role );

            if ( !$random_author )
            {
                return self::get_random_guest();
            }
        }
        return false;
    }
    public static function get_random_user_by_role( $role_in = array() )
    {
        $user_query = new \WP_User_Query( array
        (
            'role__in' => $role_in, // The role to filter by
            'orderby'  => 'rand',   // Random order
            'number'   => 1,        // Limit to 1 user
        ));
        if ( !empty( $user_query->get_results() ) )
        {
            $random_user = $user_query->get_results()[0];
            return $random_user;
        }
        return false;
    }
    public static function get_random_guest()
    {
        $args = array
        (
            'post_type'      => Guest_Author::get_post_type(), // The post type to filter by
            'posts_per_page' => 1,                             // Limit to 1 post
            'orderby'        => 'rand',                        // Order by random
            'post_status'    => 'publish',                     // Only published posts
            'fields'         => 'all',                         // Get full post objects
        );
        $random_posts = get_posts( $args );
        if ( !empty( $random_posts ) )
        {
            return $random_posts[0];
        }
        return false;
    }

}
