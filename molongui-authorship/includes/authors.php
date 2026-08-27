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
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Authors
{
    public static function find()
    {
        global $wp_query;
        $authors = array();
        if ( !empty( $wp_query->query_vars['guest-author-name'] ) )
        {
            $guest = self::get_author_by( 'name', $wp_query->query_vars['guest-author-name'], 'guest', false );

            if ( $guest instanceof Author )
            {
                $authors[0]       = new \stdClass();
                $authors[0]->id   = (int) $guest->get_id();
                $authors[0]->type = 'guest';
                $authors[0]->ref  = 'guest-'.$guest->get_id();
            }
            else
            {
                $user = self::get_author_by( 'user_nicename', $wp_query->query_vars['guest-author-name'] );

                if ( $user instanceof Author )
                {
                    $authors[0]       = new \stdClass();
                    $authors[0]->id   = (int) $user->get_id();
                    $authors[0]->type = 'user';
                    $authors[0]->ref  = 'user-'.$user->get_id();
                }
            }
        }
        elseif ( is_author() and !empty( $wp_query->query_vars['author_name'] ) )
        {
            $authors[0]       = new \stdClass();
            $authors[0]->id   = 0;
            $authors[0]->type = 'user';
            $authors[0]->ref  = 'user-0';
            if ( $user = self::get_author_by( 'user_nicename', $wp_query->query_vars['author_name'] ) )
            {
                $authors[0]->id  = (int) $user->get_id();
                $authors[0]->ref = 'user-'.$user->get_id();
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
        if ( empty( $authors ) || !is_array( $authors ) )
        {
            return false;
        }

        $first_author = reset( $authors );

        if ( !is_object( $first_author ) || empty( $first_author->id ) )
        {
            return false;
        }
        return $authors;
    }
    public static function get_author_by( $field, $value, $type = 'user', $meta = true )
    {
        $type = ( $type === 'guest' ) ? 'guest' : 'user';
        $cache   = Author::request_cache();
        $segment = 'get_author_by|' . $type . '|' . ( $meta ? 'meta' : 'core' ) . '|' . $field;
        $key     = (string) $value;

        $result = $cache->remember( $segment, $key, function () use ( $field, $value, $type, $meta )
        {
            if ( $type === 'user' )
            {
                $user = null;
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                switch ( $field )
                {
                    case 'ID':
                    case 'id':
                    case 'user_id':
                        $user = get_user_by( 'id', (int) $value );
                        break;

                    case 'user_login':
                    case 'login':
                        $user = get_user_by( 'login', $value );
                        break;

                    case 'user_nicename':
                    case 'slug':
                        $user = get_user_by( 'slug', $value );
                        break;

                    case 'user_email':
                    case 'email':
                        $user = get_user_by( 'email', $value );
                        break;
                }
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                if ( ! $user instanceof \WP_User )
                {
                    $query = new \WP_User_Query( array
                    (
                        'search'        => $value,
                        'search_fields' => array( $field ),
                        'number'        => 1,
                        'count_total'   => false,
                        'fields'        => 'all',
                    ) );
                    $results = $query->get_results();
                    $user    = empty( $results[0] ) ? null : $results[0];
                }

                if ( ! $user instanceof \WP_User )
                {
                    return false;
                }

                return new Author( $user, 'user' );
            }

            $post = null;
            if ( ! $meta )
            {
                switch ( $field )
                {
                    case 'ID':
                    case 'id':
                        $post = get_post( (int) $value );
                        if ( $post instanceof \WP_Post && $post->post_type !== Guest_Author::get_post_type() )
                        {
                            $post = null;
                        }
                        break;

                    case 'name':
                    case 'post_name':
                    case 'slug':
                        $post = get_page_by_path( $value, OBJECT, Guest_Author::get_post_type() );
                        break;
                }
                if ( ! $post instanceof \WP_Post )
                {
                    $args = array
                    (
                        'post_type'              => Guest_Author::get_post_type(),
                        'posts_per_page'         => 1,
                        'no_found_rows'          => true,
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    );
                    if ( $field === 'name' || $field === 'post_name' || $field === 'slug' )
                    {
                        $args['name'] = $value;
                    }
                    elseif ( $field === 'post_title' || $field === 'title' )
                    {
                        $args['s'] = $value;
                    }
                    else
                    {
                        $args[ $field ] = $value;
                    }

                    $query = new \WP_Query( $args );

                    if ( $query->have_posts() && ! empty( $query->posts[0] ) && $query->posts[0] instanceof \WP_Post )
                    {
                        $post = $query->posts[0];
                    }
                }
            }
            else
            {
                $args = array
                (
                    'post_type'              => Guest_Author::get_post_type(),
                    'posts_per_page'         => 1,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'meta_query'             => array
                    (
                        array
                        (
                            'key'     => $field,
                            'value'   => $value,
                            'compare' => '=',
                        ),
                    ),
                );

                $query = new \WP_Query( $args );

                if ( $query->have_posts() && ! empty( $query->posts[0] ) && $query->posts[0] instanceof \WP_Post )
                {
                    $post = $query->posts[0];
                }
            }

            if ( ! $post instanceof \WP_Post )
            {
                return false;
            }

            return new Author( $post, 'guest' );
        } );

        return ( $result instanceof Author ) ? $result : false;
    }
    public static function get_author_type_by_nicename( $nicename )
    {
        $nicename = (string) $nicename;

        if ( $nicename === '' )
        {
            return 'not_found';
        }
        $cache   = Author::request_cache();
        $segment = 'get_author_type_by_nicename';
        $key     = $nicename;

        return $cache->remember( $segment, $key, function () use ( $nicename )
        {
            $guest = self::get_author_by( 'name', $nicename, 'guest', false );
            if ( $guest instanceof Author )
            {
                return 'guest';
            }
            $user = self::get_author_by( 'user_nicename', $nicename, 'user', false );
            if ( $user instanceof Author )
            {
                return 'user';
            }
            return 'not_found';
        } );
    }
    public static function get_authors( $type_or_args = 'authors', $include_users = array(), $exclude_users = array(), $include_guests = array(), $exclude_guests = array(), $order = 'ASC', $orderby = 'name', $get_data = false, $min_post_count = 0, $post_types = array( 'post' ) )
    {
        if ( is_array( $type_or_args ) )
        {
            return self::_get_authors( $type_or_args ); // returns Author[]
        }
        WP::deprecated_function_once(
            __METHOD__ . ' (positional signature)',
            '5.2.0',
            __CLASS__ . '::get_authors( array $args )'
        );
        $legacy_type = is_string( $type_or_args ) ? strtolower( $type_or_args ) : 'authors';
        if ( $legacy_type !== 'authors' && $legacy_type !== 'users' && $legacy_type !== 'guests' )
        {
            $legacy_type = 'authors';
        }
        $args = array
        (
            'type'             => $legacy_type,
            'include_users'    => is_array( $include_users )  ? $include_users  : array(),
            'exclude_users'    => is_array( $exclude_users )  ? $exclude_users  : array(),
            'include_guests'   => is_array( $include_guests ) ? $include_guests : array(),
            'exclude_guests'   => is_array( $exclude_guests ) ? $exclude_guests : array(),
            'order'            => ( strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC' ),
            'orderby'          => is_string( $orderby ) ? strtolower( $orderby ) : 'name',
            'min_post_count'   => (int) $min_post_count,
            'post_types'       => is_array( $post_types ) ? $post_types : array( 'post' ),
            'exclude_archived' => true,
            'dont_sort'        => false,
            'prefetch'         => array
            (
                'core' => array( 'display_name', 'user_nicename', 'post_title' ),
                'meta' => array(),
            ),
        );
        if ( !empty( $get_data ) || (int) $min_post_count > 0 )
        {
            $fields = apply_filters( 'molongui_authorship/get_author_data_fields', array
            (
                'id','type','display_name','first_name','last_name','slug','email','phone','website','custom_link',
                'archive_url','avatar','position','company','company_link','description','post_count','user_roles',
                'user_login',
                'name','mail','web','job','bio',
            ) );
            $core_wishlist = array( 'display_name','user_nicename','user_email','user_url','post_title','post_name','post_content' );
            $meta_wishlist_users = array(
                'first_name','last_name','description',
                'molongui_author_phone','molongui_author_custom_link',
                'molongui_author_position','molongui_author_company','molongui_author_company_link',
                'molongui_author_archived',
            );
            $meta_wishlist_guests = array(
                '_molongui_guest_author_first_name','_molongui_guest_author_last_name',
                '_molongui_guest_author_bio', '_molongui_guest_author_phone',
                '_molongui_guest_author_web','_molongui_guest_author_custom_link',
                '_molongui_guest_author_job','_molongui_guest_author_company','_molongui_guest_author_company_link',
                '_molongui_guest_author_archived',
            );
            $need_post_counts = in_array( 'post_count', $fields, true ) || (int) $args['min_post_count'] > 0;
            $pts = ! empty( $args['post_types'] ) ? (array) $args['post_types'] : array( 'post' );
            if ( $need_post_counts )
            {
                foreach ( $pts as $pt )
                {
                    $meta_wishlist_users[]  = 'molongui_author_' . $pt . '_count';
                    $meta_wishlist_guests[] = '_molongui_guest_author_' . $pt . '_count';
                }
            }
            if ( class_exists( '\Molongui\Authorship\Social' ) )
            {
                $enabled = \Molongui\Authorship\Social::get( 'enabled' );
                if ( is_array( $enabled ) )
                {
                    foreach ( array_keys( $enabled ) as $slug )
                    {
                        $meta_wishlist_users[]  = 'molongui_author_' . $slug;
                        $meta_wishlist_guests[] = '_molongui_guest_author_' . $slug;
                    }
                }
            }
            if ( empty( $args['prefetch'] ) || ! is_array( $args['prefetch'] ) )
            {
                $args['prefetch'] = array( 'core' => array(), 'meta' => array() );
            }
            $args['prefetch']['core'] = array_values( array_unique( array_merge(
                is_array( $args['prefetch']['core'] ) ? $args['prefetch']['core'] : array(),
                $core_wishlist
            ) ) );
            $args['prefetch']['meta'] = array_values( array_unique( array_merge(
                is_array( $args['prefetch']['meta'] ) ? $args['prefetch']['meta'] : array(),
                $meta_wishlist_users,
                $meta_wishlist_guests
            ) ) );
        }
        $authors = self::_get_authors( $args );
        if ( !empty( $get_data ) || (int) $min_post_count > 0 )
        {
            if ( function_exists( '_deprecated_argument' ) )
            {
                _deprecated_argument(
                    __METHOD__,
                    '5.2.0',
                    __( 'Using positional $get_data is deprecated; pass Authors::get_authors( array( "get_data" => true, ... ) ) instead.', 'molongui-authorship' )
                );
            }
            $out = array();
            foreach ( $authors as $a )
            {
                $out[] = $a->get_data();
            }

            return $out;
        }
        $out = array();
        foreach ( $authors as $a )
        {
            $out[] = array
            (
                'id'   => $a->get_id(),
                'type' => $a->get_type(),
                'ref'  => $a->get_type() . '-' . $a->get_id(),
                'name' => $a->get_display_name(),
            );
        }

        return $out;
    }
    public static function _get_authors( array $args = array() )
    {

        $a = wp_parse_args( $args, array
        (
            'type'             => 'authors',
            'include_users'    => array(),
            'exclude_users'    => array(),
            'include_guests'   => array(),
            'exclude_guests'   => array(),
            'order'            => 'ASC',
            'orderby'          => 'name',
            'exclude_archived' => false,
            'min_post_count'   => 0,
            'post_types'       => array( 'post' ),
            'dont_sort'        => false,
            'prefetch'         => array
            (
                'core' => array(),    // auto-filled below for users/guests independently
                'meta' => array(),    // auto-filled below based on needs
            ),
            'roles'            => Settings::enabled_user_roles(),
            'language'         => apply_filters( 'authorship/get_authors/language', '' ),
        ) );
        $type = is_string( $a['type'] ) ? strtolower( $a['type'] ) : 'authors';
        if ( $type !== 'authors' && $type !== 'users' && $type !== 'guests' )
        {
            $type = 'authors';
        }
        $ord = strtoupper( (string) $a['order'] );
        if ( $ord !== 'ASC' && $ord !== 'DESC' )
        {
            $ord = 'ASC';
        }
        $oby = is_string( $a['orderby'] ) ? strtolower( $a['orderby'] ) : 'name';
        if ( $oby === 'id' || $oby === 'ids' ) { $oby = 'id'; }
        if ( $oby === 'display_name' ) { $oby = 'name'; }
        if ( $oby === 'mail' || $oby === 'email' || $oby === 'user_email' ) { $oby = 'mail'; }
        if ( $oby === 'random' ) { $oby = 'rand'; }
        $include_users  = is_array( $a['include_users'] )  ? array_values( array_unique( array_map( 'absint', $a['include_users'] ) ) ) : array();
        $exclude_users  = is_array( $a['exclude_users'] )  ? array_values( array_unique( array_map( 'absint', $a['exclude_users'] ) ) ) : array();
        $include_guests = is_array( $a['include_guests'] ) ? array_values( array_unique( array_map( 'absint', $a['include_guests'] ) ) ) : array();
        $exclude_guests = is_array( $a['exclude_guests'] ) ? array_values( array_unique( array_map( 'absint', $a['exclude_guests'] ) ) ) : array();
        $roles = is_array( $a['roles'] ) ? array_values( array_unique( array_map( 'sanitize_key', $a['roles'] ) ) ) : Settings::enabled_user_roles();
        if ( empty( $roles ) )
        {
            $roles = Settings::enabled_user_roles();
        }
        $post_types = array();
        if ( ! empty( $a['post_types'] ) && is_array( $a['post_types'] ) )
        {
            foreach ( $a['post_types'] as $pt )
            {
                $pt = sanitize_key( (string) $pt );
                if ( $pt !== '' )
                {
                    $post_types[] = $pt;
                }
            }
        }
        if ( empty( $post_types ) )
        {
            $post_types = array( 'post' );
        }
        $prefetch_core_users  = array();
        $prefetch_core_guests = array();
        $prefetch_meta_users  = array();
        $prefetch_meta_guests = array();

        if ( ! empty( $a['prefetch']['core'] ) && is_array( $a['prefetch']['core'] ) )
        {
            $prefetch_core_users  = $a['prefetch']['core'];
            $prefetch_core_guests = $a['prefetch']['core'];
        }
        if ( ! empty( $a['prefetch']['meta'] ) && is_array( $a['prefetch']['meta'] ) )
        {
            foreach ( $a['prefetch']['meta'] as $mk )
            {
                if ( strpos( $mk, 'molongui_author_' ) === 0 )
                {
                    $prefetch_meta_users[] = $mk;
                }
                elseif ( strpos( $mk, '_molongui_guest_author_' ) === 0 )
                {
                    $prefetch_meta_guests[] = $mk;
                }
            }
        }
        self::_ensure_core_for_orderby( $prefetch_core_users,  $oby ); // adds display_name, etc. if needed
        self::_ensure_core_for_orderby( $prefetch_core_guests, $oby ); // adds post_title, etc. if needed
        self::_ensure_meta_for_orderby( $prefetch_meta_users,  $oby );
        self::_ensure_meta_for_orderby( $prefetch_meta_guests, $oby );
        if ( ! empty( $a['exclude_archived'] ) )
        {
            $prefetch_meta_users[]  = Author::USER_META_PREFIX  . 'archived';
            $prefetch_meta_guests[] = Author::GUEST_META_PREFIX . 'archived';
        }
        $min_post_count = (int) $a['min_post_count'];
        if ( $min_post_count > 0 || $oby === 'post_count' )
        {
            if ( ! empty( $a['post_types'] ) )
            {
                foreach ( $a['post_types'] as $pt )
                {
                    $prefetch_meta_users[]  = Author::USER_META_PREFIX  . $pt . '_count';
                    $prefetch_meta_guests[] = Author::GUEST_META_PREFIX . $pt . '_count';
                }
            }
        }
        $prefetch_core_users  = array_values( array_unique( $prefetch_core_users ) );
        $prefetch_core_guests = array_values( array_unique( $prefetch_core_guests ) );
        $prefetch_meta_users  = array_values( array_unique( $prefetch_meta_users ) );
        $prefetch_meta_guests = array_values( array_unique( $prefetch_meta_guests ) );
        $users_min   = array( 'ids' => array(), 'rows' => array() );
        $guests_min  = array( 'ids' => array(), 'rows' => array() );

        if ( $type === 'authors' || $type === 'users' )
        {
            $users_min = self::_fetch_users_core( array
            (
                'include'          => (array) $a['include_users'],
                'exclude'          => (array) $a['exclude_users'],
                'roles'            => is_array( $a['roles'] ) ? $a['roles'] : array(),
                'orderby'          => $oby,
                'order'            => $ord,
                'prefetch_core'    => $prefetch_core_users,
                'orderby_meta_map' => self::_map_orderby_to_user_meta_key( $oby ), // null if not meta-driven
                'language'         => $a['language'],
            ) );
        }

        if ( $type === 'authors' || $type === 'guests' )
        {
            $guests_min = self::_fetch_guests_core( array
            (
                'include'          => (array) $a['include_guests'],
                'exclude'          => (array) $a['exclude_guests'],
                'orderby'          => $oby,
                'order'            => $ord,
                'prefetch_core'    => $prefetch_core_guests,
                'orderby_meta_map' => self::_map_orderby_to_guest_meta_key( $oby ), // null if not meta-driven
                'language'         => $a['language'],
            ) );
        }
        $user_meta_map  = ! empty( $users_min['ids']  ) && ! empty( $prefetch_meta_users )
            ? self::_prefetch_meta_bulk( 'user',  $users_min['ids'], $prefetch_meta_users )
            : array();

        $guest_meta_map = ! empty( $guests_min['ids'] ) && ! empty( $prefetch_meta_guests )
            ? self::_prefetch_meta_bulk( 'guest', $guests_min['ids'], $prefetch_meta_guests )
            : array();
        $authors = array(); // array of [ 'a' => Author, 'o' => original_order, 't' => 'user'|'guest' ]
        $seq     = 0;

        if ( ! empty( $users_min['rows'] ) )
        {
            foreach ( $users_min['rows'] as $row )
            {
                $id     = (int) $row['ID'];
                $author = new Author( $id, 'user' );
                if ( ! empty( $prefetch_core_users ) )
                {
                    $bag = array();
                    foreach ( $prefetch_core_users as $k )
                    {
                        if ( isset( $row[ $k ] ) )
                        {
                            $bag[ $k ] = $row[ $k ];
                        }
                    }
                    if ( ! empty( $bag ) )
                    {
                        $author->prime_core( $bag );
                    }
                }
                if ( isset( $user_meta_map[ $id ] ) && ! empty( $user_meta_map[ $id ] ) )
                {
                    $author->prime_meta( $user_meta_map[ $id ] );
                }

                $authors[] = array( 'a' => $author, 'o' => $seq++, 't' => 'user' );
            }
        }

        if ( ! empty( $guests_min['rows'] ) )
        {
            foreach ( $guests_min['rows'] as $row )
            {
                $id     = (int) $row['ID'];
                $author = new Author( $id, 'guest' );
                if ( ! empty( $prefetch_core_guests ) )
                {
                    $bag = array();
                    foreach ( $prefetch_core_guests as $k )
                    {
                        if ( isset( $row[ $k ] ) )
                        {
                            $bag[ $k ] = $row[ $k ];
                        }
                    }
                    if ( ! empty( $bag ) )
                    {
                        $author->prime_core( $bag );
                    }
                }
                if ( isset( $guest_meta_map[ $id ] ) && ! empty( $guest_meta_map[ $id ] ) )
                {
                    $author->prime_meta( $guest_meta_map[ $id ] );
                }

                $authors[] = array( 'a' => $author, 'o' => $seq++, 't' => 'guest' );
            }
        }

        if ( empty( $authors ) )
        {
            return array();
        }
        if ( ! empty( $a['exclude_archived'] ) )
        {
            $include_beats_archived = apply_filters( 'molongui_authorship/include_beats_archived', false, $a );
            if ( ! $include_beats_archived )
            {
                $authors = array_values( array_filter( $authors, function ( $row )
                {
                    $author = $row['a'];
                    return empty( $author->get_meta( 'archived' ) );
                } ) );
            }
        }
        $post_types = is_array( $a['post_types'] ) ? $a['post_types'] : array( 'post' );
        $post_types = array_values( array_unique( $post_types ) );

        $postCountMap          = array(); // cache full sums by ID if needed for sorting
        $need_full_postcount   = ( $oby === 'post_count' );
        $threshold             = (int) $a['min_post_count'];

        if ( $threshold > 0 )
        {
            $authors = array_values( array_filter( $authors, function ( $row ) use ( $threshold, $post_types, &$postCountMap, $need_full_postcount )
            {
                $author = $row['a'];
                $id     = $author->get_type() . '-' . $author->get_id();

                if ( isset( $postCountMap[ $id ] ) )
                {
                    return ( $postCountMap[ $id ] >= $threshold );
                }

                $sum = 0;
                if ( $author->get_type() === 'user' )
                {
                    foreach ( $post_types as $pt )
                    {
                        $sum += (int) $author->get_meta( 'molongui_author_' . $pt . '_count' );
                        if ( ! $need_full_postcount && $sum >= $threshold )
                        {
                            $postCountMap[ $id ] = $sum; // partial ok for filtering
                            return true;
                        }
                    }
                }
                else
                {
                    foreach ( $post_types as $pt )
                    {
                        $sum += (int) $author->get_meta( '_molongui_guest_author_' . $pt . '_count' );
                        if ( ! $need_full_postcount && $sum >= $threshold )
                        {
                            $postCountMap[ $id ] = $sum; // partial ok for filtering
                            return true;
                        }
                    }
                }
                $postCountMap[ $id ] = $sum;
                return ( $sum >= $threshold );
            } ) );

            if ( empty( $authors ) )
            {
                return array();
            }
        }
        if ( ! empty( $a['dont_sort'] ) )
        {
            if ( $oby === 'include' )
            {
                $order_map = array();
                $idx       = 0;

                foreach ( (array) $a['include_users'] as $uid )  { $order_map[ 'user:'  . (int) $uid ]  = $idx++; }
                foreach ( (array) $a['include_guests'] as $gid ) { $order_map[ 'guest:' . (int) $gid ] = $idx++; }

                usort( $authors, function ( $x, $y ) use ( $order_map )
                {
                    $kx = $x['t'] . ':' . $x['a']->get_id();
                    $ky = $y['t'] . ':' . $y['a']->get_id();

                    $ix = isset( $order_map[ $kx ] ) ? $order_map[ $kx ] : PHP_INT_MAX;
                    $iy = isset( $order_map[ $ky ] ) ? $order_map[ $ky ] : PHP_INT_MAX;

                    if ( $ix === $iy ) { return $x['o'] - $y['o']; } // stable
                    return ( $ix < $iy ) ? -1 : 1;
                } );

                return array_map( function ( $row ) { return $row['a']; }, $authors );
            }

            if ( $oby === 'rand' || $oby === 'random' )
            {
                shuffle( $authors );
            }
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'include' )
        {
            $order_map = array();
            $idx       = 0;

            foreach ( (array) $a['include_users'] as $uid )  { $order_map[ 'user:'  . (int) $uid ]  = $idx++; }
            foreach ( (array) $a['include_guests'] as $gid ) { $order_map[ 'guest:' . (int) $gid ] = $idx++; }

            usort( $authors, function ( $x, $y ) use ( $order_map )
            {
                $kx = $x['t'] . ':' . $x['a']->get_id();
                $ky = $y['t'] . ':' . $y['a']->get_id();

                $ix = isset( $order_map[ $kx ] ) ? $order_map[ $kx ] : PHP_INT_MAX;
                $iy = isset( $order_map[ $ky ] ) ? $order_map[ $ky ] : PHP_INT_MAX;

                if ( $ix === $iy ) { return $x['o'] - $y['o']; } // stable
                return ( $ix < $iy ) ? -1 : 1;
            } );

            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'rand' || $oby === 'random' )
        {
            shuffle( $authors );
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'none' )
        {
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        $sortable = array();
        $i        = 0;

        foreach ( $authors as $row )
        {
            $author = $row['a'];
            $key    = '';

            switch ( $oby )
            {
                case 'id':           $key = (int) $author->get_id(); break;
                case 'name':         $key = (string) $author->get_display_name(); break;
                case 'first_name':   $key = (string) $author->get_first_name(); break;
                case 'last_name':    $key = (string) $author->get_last_name(); break;
                case 'mail':         $key = (string) $author->get_email(); break;
                case 'job':          $key = (string) $author->get_meta( 'job' ); break;
                case 'company':      $key = (string) $author->get_meta( 'company' ); break;
                case 'job_position': $key = (string) $author->get_meta( 'job_position' ); break;

                case 'post_count':
                    $id = $author->get_type() . '-' . $author->get_id();
                    if ( isset( $postCountMap[ $id ] ) )
                    {
                        $key = (int) $postCountMap[ $id ];
                    }
                    else
                    {
                        $sum = 0;
                        if ( $author->get_type() === 'user' )
                        {
                            foreach ( $post_types as $pt ) { $sum += (int) $author->get_meta( 'molongui_author_' . $pt . '_count' ); }
                        }
                        else
                        {
                            foreach ( $post_types as $pt ) { $sum += (int) $author->get_meta( '_molongui_guest_author_' . $pt . '_count' ); }
                        }
                        $postCountMap[ $id ] = $sum;
                        $key = $sum;
                    }
                    break;

                default:
                    $key = '';
                    break;
            }

            $sortable[] = array( 'k' => $key, 'o' => $row['o'], 'i' => $i++, 'a' => $author );
        }
        $numeric = ( $oby === 'id' || $oby === 'post_count' );

        if ( $numeric )
        {
            usort( $sortable, function ( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] ) { return $x['o'] - $y['o']; }
                return ( $ord === 'ASC' ) ? ( $x['k'] - $y['k'] ) : ( $y['k'] - $x['k'] );
            } );
        }
        else
        {
            usort( $sortable, function ( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] ) { return $x['o'] - $y['o']; }
                $cmp = strcasecmp( (string) $x['k'], (string) $y['k'] );
                return ( $ord === 'ASC' ) ? $cmp : -$cmp;
            } );
        }

        return array_map( function ( $row ) { return $row['a']; }, $sortable );
    }
    private static function _fetch_users_core( array $args )
    {
        $args = wp_parse_args( $args, array
        (
            'include'          => array(),
            'exclude'          => array(),
            'orderby'          => 'display_name',
            'order'            => 'ASC',
            'prefetch_core'    => array(),
            'orderby_meta_map' => '',
            'roles'            => array(),

            'language'         => '',
        ) );
        $args['include']          = array_map( 'intval', (array) $args['include'] );
        $args['exclude']          = array_map( 'intval', (array) $args['exclude'] );
        $args['roles']            = array_map( 'sanitize_key', (array) $args['roles'] );
        $args['orderby']          = strtolower( (string) $args['orderby'] );
        $args['order']            = strtoupper( (string) $args['order'] );
        $args['prefetch_core']    = array_map( 'sanitize_key', (array) $args['prefetch_core'] );
        $args['orderby_meta_map'] = sanitize_key( $args['orderby_meta_map'] );
        $args['language']         = sanitize_text_field( $args['language'] );
        if ( $args['order'] !== 'ASC' && $args['order'] !== 'DESC' )
        {
            $args['order'] = 'ASC';
        }

        $orderby_input = $args['orderby'];

        if ( $orderby_input === 'id' )
        {
            $orderby_input = 'ID';
        }
        if ( $orderby_input === 'include' && empty( $args['include'] ) )
        {
            $orderby_input = 'display_name';
        }
        $allowed_orderby = array
        (
            'display_name' => 'display_name',
            'ID'           => 'ID',
            'include'      => 'include',
            'nicename'     => 'nicename',
            'email'        => 'email',
            'registered'   => 'registered',
        );
        $orderby = isset( $allowed_orderby[$orderby_input] )
            ? $allowed_orderby[$orderby_input]
            : 'display_name';
        $allowed_fields = array
        (
            'user_nicename',
            'user_email',
            'user_url',
            'user_registered',
            'display_name',
        );
        $fields = array( 'ID' );
        foreach ( $args['prefetch_core'] as $f )
        {
            if ( in_array( $f, $allowed_fields, true ) && !in_array( $f, $fields, true ) )
            {
                $fields[] = $f;
            }
        }
        $roles_filtered = array();
        foreach ( $args['roles'] as $r )
        {
            if ( $r !== 'molongui_no_role' )
            {
                $roles_filtered[] = $r;
            }
        }

        $uq_args = array
        (
            'fields'        => $fields,
            'include'       => $args['include'],
            'exclude'       => $args['exclude'],
            'role__in'      => $roles_filtered,
            'orderby'       => $args['orderby_meta_map'] ? 'meta_value' : $orderby,
            'meta_key'      => $args['orderby_meta_map'] ? $args['orderby_meta_map'] : '',
            'order'         => $args['order'],
            'number'        => -1,
            'count_total'   => false,
            'cache_results' => true,
        );

        $uq = new \WP_User_Query( $uq_args );
        $rows = array();
        $ids  = array();

        foreach ( (array) $uq->get_results() as $obj )
        {
            $row = array( 'ID' => (int) $obj->ID );
            foreach ( $fields as $f )
            {
                if ( $f === 'ID' )
                {
                    continue;
                }
                if ( isset( $obj->$f ) )
                {
                    $row[$f] = (string) $obj->$f;
                }
            }

            $rows[] = $row;
            $ids[]  = $row['ID'];
        }

        return array( 'ids' => $ids, 'rows' => $rows );
    }
    private static function _fetch_users_core_by_ids( array $ids, array $cols )
    {
        global $wpdb;

        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
        if ( empty( $ids ) )
        {
            return array();
        }
        $allowed = array( 'display_name', 'user_nicename', 'user_email', 'user_url', 'user_login' );
        $cols    = array_values( array_intersect( $cols, $allowed ) );
        $select_cols = array( 'ID' );
        foreach ( $cols as $c )
        {
            $select_cols[] = $c;
        }

        $in_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql             = 'SELECT ' . implode( ',', $select_cols ) .
            ' FROM ' . $wpdb->users .
            ' WHERE ID IN (' . $in_placeholders . ')';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $ids ), ARRAY_A );

        if ( empty( $rows ) )
        {
            return array();
        }
        $by_id = array();
        foreach ( $rows as $r )
        {
            $by_id[(int)$r['ID']] = $r;
        }

        $ordered = array();
        foreach ( $ids as $id )
        {
            if ( isset( $by_id[$id] ) )
            {
                $ordered[] = $by_id[$id];
            }
        }

        return $ordered;
    }
    private static function _fetch_guests_core( array $args )
    {
        global $wpdb;
        $args = wp_parse_args( $args, array
        (
            'include'          => array(),
            'exclude'          => array(),
            'orderby'          => 'display_name',
            'order'            => 'ASC',
            'prefetch_core'    => array(),
            'orderby_meta_map' => '',

            'language'         => '',
        ) );
        $include          = array_map( 'intval', (array) $args['include'] );
        $exclude          = array_map( 'intval', (array) $args['exclude'] );
        $orderby          = strtolower( (string) $args['orderby'] );
        $order            = strtoupper( (string) $args['order'] );
        $prefetch_core_in = array_map( 'sanitize_key', (array) $args['prefetch_core'] );
        $orderby_meta_key = sanitize_key( $args['orderby_meta_map'] );
        $language         = sanitize_text_field( $args['language'] );
        if ( $order !== 'ASC' && $order !== 'DESC' )
        {
            $order = 'ASC';
        }

        if ( $orderby === 'id' )
        {
            $orderby = 'ID';
        }
        if ( $orderby === 'include' && empty( $include ) )
        {
            $orderby = 'display_name';
        }
        $allowed_orderby = array
        (
            'display_name' => 'post_title',  // friendly mirror of users.display_name
            'ID'           => 'ID',
            'include'      => 'include',
            'nicename'     => 'post_name',
            'registered'   => 'post_date',
        );
        $orderby = isset( $allowed_orderby[$orderby] ) ? $allowed_orderby[$orderby] : 'post_title';
        $allowed_core = array
        (
            'post_date',
            'post_date_gmt',     // mainly for sitemaps
            'post_content',      // (author bio). Include only if you *really* need it.
            'post_title',
            'post_name',
            'post_modified',     // mainly for sitemaps
            'post_modified_gmt', // mainly for sitemaps
        );
        $select_cols = array( 'ID' );
        foreach ( $prefetch_core_in as $f )
        {
            if ( in_array( $f, $allowed_core, true ) && !in_array( $f, $select_cols, true ) )
            {
                $select_cols[] = $f;
            }
        }
        $select_sql = array( 'p.ID' );
        foreach ( $select_cols as $f )
        {
            if ( $f === 'ID' )
            {
                continue;
            }
            $select_sql[] = 'p.' . $f;
        }
        $select_sql = implode( ', ', $select_sql );
        $where = array
        (
            $wpdb->prepare( 'p.post_type = %s', Guest_Author::get_post_type() ),
            $wpdb->prepare( 'p.post_status = %s', 'publish' ),
        );
        if ( !empty( $include ) )
        {
            $in_placeholders = implode( ',', array_fill( 0, count( $include ), '%d' ) );
            $where[] = $wpdb->prepare( "p.ID IN ($in_placeholders)", $include );
        }
        if ( !empty( $exclude ) )
        {
            $ex_placeholders = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );
            $where[] = $wpdb->prepare( "p.ID NOT IN ($ex_placeholders)", $exclude );
        }
        $join  = '';
        $order_by_sql = '';
        if ( $orderby_meta_key )
        {
            $join = $wpdb->prepare(
                " LEFT JOIN {$wpdb->postmeta} pm ON (pm.post_id = p.ID AND pm.meta_key = %s) ",
                $orderby_meta_key
            );
            $order_by_sql = " ORDER BY pm.meta_value " . $order . " ";
        }
        else
        {
            if ( $orderby === 'include' && !empty( $include ) )
            {
                $in_list = implode( ',', array_map( 'intval', $include ) );
                $order_by_sql = " ORDER BY FIELD(p.ID, $in_list) ";
            }
            else
            {
                $order_by_sql = " ORDER BY p.$orderby $order ";
            }
        }
        $sql =
            "SELECT $select_sql
         FROM {$wpdb->posts} p
         $join
         WHERE " . implode( ' AND ', $where ) .
            $order_by_sql;

        /*!
         * Allow last-mile customization of the core guests SQL (expert use only).
         * Keep changes minimal; this is part of a hot path.
         *
         * @param string $sql   The prepared SQL string.
         * @param array  $args  The sanitized/normalized input args.
         * @since 5.2.0
         */
        $sql = apply_filters( 'molongui_authorship/fetch_guests_core/sql', $sql, array
        (
            'include'          => $include,
            'exclude'          => $exclude,
            'orderby'          => $orderby,
            'order'            => $order,
            'prefetch_core'    => $select_cols,
            'orderby_meta_map' => $orderby_meta_key,
            'language'         => $language,
        ) );
        $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $rows = array();
        $ids  = array();

        foreach ( (array) $results as $row )
        {
            $out = array( 'ID' => (int) $row['ID'] );
            foreach ( $select_cols as $f )
            {
                if ( $f === 'ID' )
                {
                    continue;
                }
                if ( array_key_exists( $f, $row ) )
                {
                    $out[$f] = (string) $row[$f];
                }
            }
            $rows[] = $out;
            $ids[]  = $out['ID'];
        }

        return array( 'ids' => $ids, 'rows' => $rows );
    }
    private static function _ensure_core_for_orderby( array &$prefetch_core, $orderby )
    {
        switch ( strtolower( $orderby ) )
        {
            case 'name':
                $prefetch_core[] = 'display_name';
                $prefetch_core[] = 'post_title';
                break;

            case 'id':
                break;

            case 'mail':
                $prefetch_core[] = 'user_email';
                break;

            case 'none':
            case 'include':
            case 'rand':
            case 'post_count':
            case 'first_name':
            case 'last_name':
            case 'job':
            case 'company':
            case 'job_position':
            default:
                break;
        }
    }
    private static function _ensure_meta_for_orderby( array &$prefetch_meta, $orderby )
    {
        switch ( strtolower( $orderby ) )
        {
            case 'first_name':
                $prefetch_meta[] = 'first_name'; // user native
                $prefetch_meta[] = '_molongui_guest_author_first_name';
                break;

            case 'last_name':
                $prefetch_meta[] = 'last_name'; // user native
                $prefetch_meta[] = '_molongui_guest_author_last_name';
                break;

            case 'mail':
                $prefetch_meta[] = '_molongui_guest_author_mail';
                break;

            case 'job':
                $prefetch_meta[] = 'molongui_author_job';
                $prefetch_meta[] = '_molongui_guest_author_job';
                break;

            case 'company':
                $prefetch_meta[] = 'molongui_author_company';
                $prefetch_meta[] = '_molongui_guest_author_company';
                break;

            case 'job_position':
                $prefetch_meta[] = 'molongui_author_job_position';
                $prefetch_meta[] = '_molongui_guest_author_job_position';
                break;

            default:
                break;
        }
    }
    private static function _map_orderby_to_user_meta_key( $orderby )
    {
        switch ( strtolower( $orderby ) )
        {
            case 'first_name':   return 'first_name'; // built-in usermeta
            case 'last_name':    return 'last_name';  // built-in usermeta
            case 'job':          return 'molongui_author_job';
            case 'company':      return 'molongui_author_company';
            case 'job_position': return 'molongui_author_job_position';
            default:             return null;
        }
    }
    private static function _map_orderby_to_guest_meta_key( $orderby )
    {
        switch ( strtolower( $orderby ) )
        {
            case 'first_name':   return '_molongui_guest_author_first_name';
            case 'last_name':    return '_molongui_guest_author_last_name';
            case 'mail':         return '_molongui_guest_author_mail';
            case 'job':          return '_molongui_guest_author_job';
            case 'company':      return '_molongui_guest_author_company';
            case 'job_position': return '_molongui_guest_author_job_position';
            default:             return null;
        }
    }
    private static function _prefetch_meta_bulk_OLD( $type, array $ids, array $keys )
    {
        $ids  = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
        $keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );

        if ( empty( $ids ) || empty( $keys ) )
        {
            return array();
        }

        global $wpdb;

        if ( $type === 'user' )
        {
            $table = $wpdb->usermeta;
            $col_id = 'user_id';
        } else
        {
            $table = $wpdb->postmeta;
            $col_id = 'post_id';
        }

        $in_ids  = implode( ',', array_map( 'intval', $ids ) );
        $in_keys = implode( ',', array_map( function ( $k ) { return "'" . esc_sql( $k ) . "'"; }, $keys ) );

        $sql = "SELECT {$col_id} AS object_id, meta_key, meta_value
            FROM {$table}
            WHERE {$col_id} IN ({$in_ids}) AND meta_key IN ({$in_keys})";

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        $out = array();
        foreach ( $ids as $id )
        {
            $out[ (int) $id ] = array();
        }

        foreach ( $rows as $r )
        {
            $oid = (int) $r['object_id'];
            $k   = (string) $r['meta_key'];
            $out[ $oid ][ $k ] = ( $r['meta_value'] === null ? '' : $r['meta_value'] );
        }

        return $out;
    }
    private static function _prefetch_meta_bulk( $type, array $ids, array $keys, $hydrate_wp_cache = true )
    {
        global $wpdb;
        $meta_type = ( $type === 'user' ) ? 'user' : 'post'; // default to 'post' for guests
        $table     = ( $meta_type === 'user' ) ? $wpdb->usermeta : $wpdb->postmeta;
        $col_id    = ( $meta_type === 'user' ) ? 'user_id'     : 'post_id';
        $cache_grp = $meta_type . '_meta'; // 'user_meta' | 'post_meta'
        $ids  = array_values( array_unique( array_filter( array_map( 'intval', $ids ), function( $v ){ return $v > 0; } ) ) );
        $keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
        if ( empty( $ids ) || empty( $keys ) )
        {
            $out = array();
            foreach ( $ids as $id )
            {
                $row = array();
                foreach ( $keys as $k ) { $row[ $k ] = ''; }
                $out[ (int) $id ] = $row;
            }
            return $out;
        }
        $ids_sql  = implode( ',', array_map( 'intval', $ids ) );
        $keys_sql = implode( ',', array_map( function( $k ){ return "'" . esc_sql( $k ) . "'"; }, $keys ) );
        $sql = "
        SELECT {$col_id} AS object_id, meta_key, meta_value
        FROM {$table}
        WHERE {$col_id} IN ( {$ids_sql} )
          AND meta_key  IN ( {$keys_sql} )
    ";
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        $out = array();
        foreach ( $rows as $r )
        {
            $oid  = (int) $r['object_id'];
            $mkey = (string) $r['meta_key'];
            $mval = isset( $r['meta_value'] ) ? $r['meta_value'] : '';
            if ( ! isset( $out[ $oid ] ) )
            {
                $out[ $oid ] = array();
            }
            if ( ! array_key_exists( $mkey, $out[ $oid ] ) || $out[ $oid ][ $mkey ] === '' )
            {
                $out[ $oid ][ $mkey ] = ( $mval === null ) ? '' : (string) $mval;
            }
        }
        foreach ( $ids as $oid )
        {
            $oid = (int) $oid;
            if ( ! isset( $out[ $oid ] ) )
            {
                $out[ $oid ] = array();
            }
            foreach ( $keys as $k )
            {
                if ( ! array_key_exists( $k, $out[ $oid ] ) )
                {
                    $out[ $oid ][ $k ] = '';
                }
            }
        }
        if ( $hydrate_wp_cache && apply_filters( 'molongui_authorship/prefetch/hydrate_wp_meta_cache', true, $type ) )
        {
            foreach ( $ids as $oid )
            {
                $oid    = (int) $oid;
                $bucket = wp_cache_get( $oid, $cache_grp );
                if ( $bucket === false || ! is_array( $bucket ) )
                {
                    continue;
                }
                if ( isset( $out[ $oid ] ) && is_array( $out[ $oid ] ) )
                {
                    foreach ( $out[ $oid ] as $mkey => $mval )
                    {
                        $bucket[ $mkey ] = is_array( $mval ) ? array_values( $mval ) : array( $mval );
                    }

                    wp_cache_set( $oid, $bucket, $cache_grp );
                }
            }
        }

        return $out;
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
    public static function get_users( $args = array() )
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_authors([\'type\' => \'users\'])' );

        $args['type'] = 'users';

        $authors = self::get_authors( $args );
        if ( isset( $args['fields'] ) && $args['fields'] === 'ids' )
        {
            $ids = array();
            foreach ( $authors as $author )
            {
                $ids[] = $author->get_id();
            }
            return $ids;
        }

        return $authors;
    }
    public static function get_guests( $args = array() )
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_authors([\'type\' => \'guests\'])' );

        $args['type'] = 'guests';

        $authors = self::get_authors( $args );
        if ( isset( $args['fields'] ) && $args['fields'] === 'ids' )
        {
            $ids = array();
            foreach ( $authors as $author )
            {
                $ids[] = $author->get_id();
            }
            return $ids;
        }

        return $authors;
    }
    public static function _legacy_get_users( $args = null )
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
    public static function _legacy_get_guests( $args = null )
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
    public static function _legacy_get_authors( $type = 'authors', $include_users = array(), $exclude_users = array(), $include_guests = array(), $exclude_guests = array(), $order = 'ASC', $orderby = 'display_name', $get_data = false, $min_post_count = 0, $post_types = array( 'post' ) )
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
                case 'email':
                case 'job':
                case 'company':
                    $get_data = true;
                    break;
            }
        }
        if ( $min_post_count )
        {
            $get_data = true;

            if ( $min_post_count > 1 )
            {
                add_filter( 'molongui_authorship/get_author_data_fields', function( $fields )
                {
                    $fields[] = 'post_counts';
                    return $fields;
                });
            }
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
            $users = self::_legacy_get_users( $args ); // Array of WP_User objects.
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
                                $total_post_count = $total_post_count + (int)$authors[$key]['post_counts'][$post_type];
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
            $guests = self::_legacy_get_guests( array( 'post__in' => $include_guests, 'post__not_in' => $exclude_guests, 'order' => $order, 'orderby' => $orderby ) ); // Array of stdClass objects.
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
                                $total_post_count = $total_post_count + (int)$authors[$key]['post_counts'][$post_type];
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
                    $a_val = isset( $a[$orderby] ) ? $a[$orderby] : '';
                    $b_val = isset( $b[$orderby] ) ? $b[$orderby] : '';
                    $a_val = is_scalar( $a_val) ? (string) $a_val : '';
                    $b_val = is_scalar( $b_val ) ? (string) $b_val : '';

                    return strcasecmp( $a_val, $b_val );
                });

                break;
        }
        if ( 'desc' == $order )
        {
            $authors = array_reverse( $authors );
        }
        return $authors;
    }
    public static function _unpublished_get_users( array $args = array() )
    {
        $a = wp_parse_args( $args, array
        (
            'include'          => array(),
            'exclude'          => array(),
            'roles'            => Settings::enabled_user_roles(),
            'order'            => 'ASC',
            'orderby'          => 'name',
            'exclude_archived' => false,
            'min_post_count'   => 0,
            'post_types'       => array( 'post' ),
            'dont_sort'        => false,
            'prefetch'         => array
            (
                'core' => array( 'display_name', 'user_nicename', 'user_email', 'user_url' ),
                'meta' => array(),
            ),
            'language'         => apply_filters( 'authorship/get_users/language', '' ),
        ) );

        $ord = strtoupper( $a['order'] );
        if ( $ord !== 'ASC' && $ord !== 'DESC' )
        {
            $ord = 'ASC';
        }

        $oby = strtolower( $a['orderby'] );
        if ( $oby === 'ID' )
        {
            $oby = 'id';
        }
        if ( $oby === 'email' )
        {
            $oby = 'mail';
        }
        $roles           = is_array( $a['roles'] ) ? $a['roles'] : array();
        $want_no_role    = in_array( 'molongui_no_role', $roles, true );
        $roles_for_query = array_values( array_filter( $roles, function( $r )
        {
            return ($r !== 'molongui_no_role');
        } ) );
        $prefetch_core = is_array( $a['prefetch']['core'] ) ? $a['prefetch']['core'] : array();
        $prefetch_meta = is_array( $a['prefetch']['meta'] ) ? $a['prefetch']['meta'] : array();

        self::_ensure_core_for_orderby( $prefetch_core, $oby );
        self::_ensure_meta_for_orderby( $prefetch_meta, $oby );

        if ( !empty( $a['exclude_archived'] ) )
        {
            $prefetch_meta[] = 'molongui_author_archived';
        }
        if ( (int)$a['min_post_count'] > 0 && !empty( $a['post_types'] ) )
        {
            foreach ( $a['post_types'] as $pt )
            {
                $prefetch_meta[] = 'molongui_author_' . $pt . '_count';
            }
        }

        $prefetch_core = array_values( array_unique( $prefetch_core ) );
        $prefetch_meta = array_values( array_unique( $prefetch_meta ) );
        $users_min_rows = array();
        $users_min_ids  = array();

        if ( !empty( $roles_for_query ) )
        {
            $users_min = self::_fetch_users_core( array
            (
                'include'          => $a['include'],
                'exclude'          => $a['exclude'],
                'roles'            => $roles_for_query,
                'orderby'          => $oby,
                'order'            => $ord,
                'prefetch_core'    => $prefetch_core,
                'orderby_meta_map' => self::_map_orderby_to_user_meta_key( $oby ),
                'language'         => $a['language'],
            ) );

            $users_min_rows = isset( $users_min['rows'] ) ? $users_min['rows'] : array();
            $users_min_ids  = isset( $users_min['ids'] ) ? $users_min['ids'] : array();
        }
        if ( $want_no_role )
        {
            $no_role_ids = wp_get_users_with_no_role(); // array of user IDs as strings.
            if ( !empty( $a['include'] ) )
            {
                $no_role_ids = array_values( array_intersect( array_map( 'intval', $no_role_ids ), array_map( 'intval', $a['include'] ) ) );
            } else
            {
                $no_role_ids = array_map( 'intval', $no_role_ids );
            }

            if ( !empty( $a['exclude'] ) )
            {
                $no_role_ids = array_values( array_diff( $no_role_ids, array_map( 'intval', $a['exclude'] ) ) );
            }
            if ( !empty( $users_min_ids ) )
            {
                $no_role_ids = array_values( array_diff( $no_role_ids, $users_min_ids ) );
            }

            if ( !empty( $no_role_ids ) )
            {
                $no_role_rows = self::_fetch_users_core_by_ids( $no_role_ids, $prefetch_core );
                if ( $oby === 'include' && !empty( $a['include'] ) )
                {
                    $map = array();
                    foreach ( $no_role_rows as $r )
                    {
                        $map[(int)$r['ID']] = $r;
                    }
                    $ordered = array();
                    foreach ( $a['include'] as $inc_id )
                    {
                        $inc_id = (int)$inc_id;
                        if ( isset( $map[$inc_id] ) )
                        {
                            $ordered[] = $map[$inc_id];
                            unset( $map[$inc_id] );
                        }
                    }
                    $no_role_rows = array_merge( $ordered, array_values( $map ) );
                }
                $users_min_rows = array_merge( $users_min_rows, $no_role_rows );
                $users_min_ids  = array_merge( $users_min_ids, $no_role_ids );
            }
        }
        if ( empty( $users_min_ids ) && empty( $users_min_rows ) )
        {
            return array();
        }
        $meta_map = !empty( $prefetch_meta )
            ? self::_prefetch_meta_bulk( 'user', $users_min_ids, $prefetch_meta )
            : array();
        $authors     = array();
        $order_index = 0;

        foreach ( $users_min_rows as $row )
        {
            $id     = (int)$row['ID'];
            $author = new Author( $id, 'user' );
            if ( !empty( $prefetch_core ) )
            {
                $core_bag = array();
                foreach ( $prefetch_core as $k )
                {
                    if ( isset( $row[$k] ) )
                    {
                        $core_bag[$k] = $row[$k];
                    }
                }
                if ( !empty( $core_bag ) )
                {
                    $author->prime_core( $core_bag );
                }
            }
            if ( isset( $meta_map[$id] ) && !empty( $meta_map[$id] ) )
            {
                $author->prime_meta( $meta_map[$id] );
            }

            $authors[] = array( 'a' => $author, 'o' => $order_index++ );
        }
        if ( !empty( $a['exclude_archived'] ) )
        {
            $authors = array_values( array_filter( $authors, function( $row )
            {
                $author = $row['a'];
                return empty( $author->get_meta( 'archived' ) );
            } ) );
        }

        if ( (int)$a['min_post_count'] > 0 && !empty( $a['post_types'] ) )
        {
            $threshold  = (int)$a['min_post_count'];
            $post_types = $a['post_types'];

            $authors = array_values( array_filter( $authors, function( $row ) use ( $threshold, $post_types )
            {
                $author = $row['a'];

                $sum = 0;
                foreach ( $post_types as $pt )
                {
                    $sum += (int)$author->get_meta( 'molongui_author_' . $pt . '_count' );
                    if ( $sum >= $threshold )
                    {
                        return true;
                    }
                }
                return false;
            } ) );
        }
        if ( !empty( $a['dont_sort'] ) )
        {
            return array_map( function( $row )
            {
                return $row['a'];
            }, $authors );
        }
        if ( $oby === 'include' )
        {
            return array_map( function( $row )
            {
                return $row['a'];
            }, $authors );
        }
        if ( $oby === 'rand' || $oby === 'random' )
        {
            shuffle( $authors );
            return array_map( function( $row )
            {
                return $row['a'];
            }, $authors );
        }
        if ( $oby === 'none' )
        {
            return array_map( function( $row )
            {
                return $row['a'];
            }, $authors );
        }
        $sortable = array();
        $i        = 0;

        foreach ( $authors as $row )
        {
            $author = $row['a'];

            switch ( $oby )
            {
                case 'id':
                    $k = (int)$author->get_id();
                    break;
                case 'name':
                    $k = (string)$author->get_display_name();
                    break;
                case 'first_name':
                    $k = (string)$author->get_first_name();
                    break;
                case 'last_name':
                    $k = (string)$author->get_last_name();
                    break;
                case 'mail':
                    $k = (string)$author->get_email();
                    break;
                case 'job':
                    $k = (string)$author->get_meta( 'job' );
                    break;
                case 'company':
                    $k = (string)$author->get_meta( 'company' );
                    break;
                case 'job_position':
                    $k = (string)$author->get_meta( 'job_position' );
                    break;
                case 'post_count':
                    $sum = 0;
                    foreach ( $a['post_types'] as $pt )
                    {
                        $sum += (int)$author->get_meta( 'molongui_author_' . $pt . '_count' );
                    }
                    $k = $sum;
                    break;
                default:
                    $k = '';
                    break;
            }

            $sortable[] = array( 'k' => $k, 'o' => $row['o'], 'i' => $i++, 'a' => $author );
        }

        $numeric = ($oby === 'id' || $oby === 'post_count');
        if ( $numeric )
        {
            usort( $sortable, function( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] )
                {
                    return $x['o'] - $y['o'];
                }
                return ($ord === 'ASC') ? ($x['k'] - $y['k']) : ($y['k'] - $x['k']);
            } );
        } else
        {
            usort( $sortable, function( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] )
                {
                    return $x['o'] - $y['o'];
                }
                $cmp = strcasecmp( (string)$x['k'], (string)$y['k'] );
                return ($ord === 'ASC') ? $cmp : -$cmp;
            } );
        }

        return array_map( function( $row )
        {
            return $row['a'];
        }, $sortable );
    }
    public static function _unpublished_get_guests( array $args = array() )
    {
        $a = wp_parse_args( $args, array
        (
            'include'          => array(),
            'exclude'          => array(),
            'order'            => 'ASC',
            'orderby'          => 'name',
            'exclude_archived' => true,
            'min_post_count'   => 0,
            'post_types'       => array( 'post' ),
            'dont_sort'        => false,
            'prefetch'         => array
            (
                'core' => array( 'post_title', 'post_name', 'post_content' ),
                'meta' => array(),
            ),
            'language'         => apply_filters( 'authorship/get_guests/language', '' ),
        ) );
        $ord = strtoupper( (string) $a['order'] );
        $ord = ( $ord === 'DESC' ? 'DESC' : 'ASC' );
        $oby = strtolower( (string) $a['orderby'] );
        if ( $oby === 'id' || $oby === 'ID' ) { $oby = 'id'; }
        if ( $oby === 'email' ) { $oby = 'mail'; }
        if ( $oby === 'random' ) { $oby = 'rand'; }

        $exclude_archived  = ! empty( $a['exclude_archived'] );
        $min_post_count    = (int) $a['min_post_count'];
        $post_types_input  = is_array( $a['post_types'] ) ? $a['post_types'] : array();
        $post_types        = array_values( array_filter( array_map( 'sanitize_key', $post_types_input ) ) );
        if ( empty( $post_types ) ) { $post_types = array( 'post' ); }

        $dont_sort = ! empty( $a['dont_sort'] );
        $prefetch_core = array_values( array_unique( is_array( $a['prefetch']['core'] ) ? $a['prefetch']['core'] : array() ));
        $prefetch_meta = array_values( array_unique( is_array( $a['prefetch']['meta'] ) ? $a['prefetch']['meta'] : array() ));
        self::_ensure_core_for_orderby( $prefetch_core, $oby );   // e.g., ensure 'post_title' if orderby=name
        self::_ensure_meta_for_orderby( $prefetch_meta, $oby );   // e.g., ensure first_name/last_name/job/...

        if ( $exclude_archived )
        {
            $prefetch_meta[] = '_molongui_guest_author_archived';
        }
        if ( $min_post_count > 0 && ! empty( $post_types ) )
        {
            foreach ( $post_types as $pt )
            {
                $prefetch_meta[] = '_molongui_guest_author_' . $pt . '_count';
            }
        }
        $prefetch_core = array_values( array_unique( $prefetch_core ) );
        $prefetch_meta = array_values( array_unique( $prefetch_meta ) );
        $guests_min = self::_fetch_guests_core( array
        (
            'include'          => is_array( $a['include'] ) ? array_map( 'intval', $a['include'] ) : array(),
            'exclude'          => is_array( $a['exclude'] ) ? array_map( 'intval', $a['exclude'] ) : array(),
            'orderby'          => $oby,
            'order'            => $ord,
            'prefetch_core'    => $prefetch_core,
            'orderby_meta_map' => self::_map_orderby_to_guest_meta_key( $oby ),
            'language'         => $a['language'],
        ) );

        if ( empty( $guests_min['ids'] ) )
        {
            return array();
        }
        $meta_map = ! empty( $prefetch_meta )
            ? self::_prefetch_meta_bulk( 'guest', $guests_min['ids'], $prefetch_meta )
            : array();
        $authors      = array();
        $order_index  = 0;       // preserves original query order as a stable tiebreaker
        $postCountMap = array(); // local cache for post_count sums to avoid recomputing

        foreach ( $guests_min['rows'] as $row )
        {
            $id     = (int) $row['ID'];
            $author = new Author( $id, 'guest' );
            if ( ! empty( $prefetch_core ) )
            {
                $core_bag = array();
                foreach ( $prefetch_core as $k )
                {
                    if ( isset( $row[ $k ] ) )
                    {
                        $core_bag[ $k ] = $row[ $k ];
                    }
                }
                if ( ! empty( $core_bag ) )
                {
                    $author->prime_core( $core_bag );
                }
            }
            if ( isset( $meta_map[ $id ] ) && ! empty( $meta_map[ $id ] ) )
            {
                $author->prime_meta( $meta_map[ $id ] );
            }

            $authors[] = array( 'a' => $author, 'o' => $order_index++ );
        }
        if ( $exclude_archived )
        {
            $authors = array_values( array_filter( $authors, function ( $row )
            {
                $author = $row['a'];
                return empty( $author->get_meta( 'archived' ) );
            } ) );
        }

        if ( $min_post_count > 0 )
        {
            $threshold           = $min_post_count;
            $need_full_postcount = ( $oby === 'post_count' );
            $authors = array_values( array_filter( $authors, function ( $row ) use ( $threshold, $post_types, &$postCountMap, $need_full_postcount )
            {
                $author = $row['a'];
                $id     = $author->get_id();
                if ( isset( $postCountMap[ $id ] ) )
                {
                    return ( $postCountMap[ $id ] >= $threshold );
                }

                $sum = 0;
                foreach ( $post_types as $pt )
                {
                    $sum += (int) $author->get_meta( '_molongui_guest_author_' . $pt . '_count' );
                    if ( ! $need_full_postcount && $sum >= $threshold )
                    {
                        $postCountMap[ $id ] = $sum; // partial is fine for filtering
                        return true;
                    }
                }
                $postCountMap[ $id ] = $sum;
                return ( $sum >= $threshold );
            } ) );
        }
        if ( $dont_sort )
        {
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'include' )
        {
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'rand' )
        {
            shuffle( $authors );
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        if ( $oby === 'none' )
        {
            return array_map( function ( $row ) { return $row['a']; }, $authors );
        }
        $sortable = array();

        foreach ( $authors as $row )
        {
            $author = $row['a'];
            $key    = '';

            switch ( $oby )
            {
                case 'id':
                    $key = (int) $author->get_id();
                    break;

                case 'name':
                    $key = (string) $author->get_display_name();
                    break;

                case 'first_name':
                    $key = (string) $author->get_first_name();
                    break;

                case 'last_name':
                    $key = (string) $author->get_last_name();
                    break;

                case 'mail':
                    $key = (string) $author->get_email();
                    break;

                case 'job':
                    $key = (string) $author->get_meta( 'job' );
                    break;

                case 'company':
                    $key = (string) $author->get_meta( 'company' );
                    break;

                case 'job_position':
                    $key = (string) $author->get_meta( 'job_position' );
                    break;

                case 'post_count':
                    $id = $author->get_id();
                    if ( isset( $postCountMap[ $id ] ) )
                    {
                        $key = (int) $postCountMap[ $id ];
                    }
                    else
                    {
                        $sum = 0;
                        foreach ( $post_types as $pt )
                        {
                            $sum += (int) $author->get_meta( '_molongui_guest_author_' . $pt . '_count' );
                        }
                        $postCountMap[ $id ] = $sum;
                        $key = $sum;
                    }
                    break;

                default:
                    $key = '';
                    break;
            }

            $sortable[] = array( 'k' => $key, 'o' => $row['o'], 'a' => $author );
        }
        $numeric = ( $oby === 'id' || $oby === 'post_count' );

        if ( $numeric )
        {
            usort( $sortable, function ( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] ) { return $x['o'] - $y['o']; }
                return ( $ord === 'ASC' ) ? ( $x['k'] - $y['k'] ) : ( $y['k'] - $x['k'] );
            } );
        }
        else
        {
            usort( $sortable, function ( $x, $y ) use ( $ord )
            {
                if ( $x['k'] === $y['k'] ) { return $x['o'] - $y['o']; }
                $cmp = strcasecmp( (string) $x['k'], (string) $y['k'] );
                return ( $ord === 'ASC' ) ? $cmp : -$cmp;
            } );
        }

        return array_map( function ( $row ) { return $row['a']; }, $sortable );
    }

}
