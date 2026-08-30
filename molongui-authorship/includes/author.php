<?php
/*!
 * Author Data Model.
 *
 * This class serves as the data model for handling and retrieving author information, both for registered WordPress
 * users and guest authors, providing a unified interface to interact with author-related data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      2.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Admin_Post;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Request_LRU_Cache;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
final class Author
{
    const USER_META_PREFIX = 'molongui_author_';
    const GUEST_META_PREFIX = '_molongui_guest_author_';
    const GUEST_CPT = 'guest_author';
    const RELATION_META_KEY = '_molongui_author';
    private $id = null;
    private $type = null;
    private $wp_object = null;
    private $is_user   = false;
    private $is_guest  = false;
    private $is_dummy  = false;
    private $is_virtual = false;
    private static $allowed_user_core_keys = array
    (
        'ID',
        'user_nicename',
        'user_email',
        'user_url',
        'user_registered',
        'display_name',
    );
    private static $allowed_guest_core_keys = array
    (
        'ID',
        'post_date',
        'post_content',
        'post_title',
        'post_name',
        'post_modified',
    );
    private static $allowed_user_meta_keys = array
    (
        'first_name',
        'last_name',
        'description',
        'nickname',
        'ID',
        'display_name',
        'user_login',
        'user_nicename',
    );
    private static $enabled_social_keys = null;
    private $author = null;
    private $user_login = null;
    private $user_nicename = null;
    private $slug = null;
    private $email = null;
    private $archive_url = null;
    private $link = null;
    private $display_name = null;
    private $first_name = null;
    private $last_name = null;
    private $description = null;
    private $website = null;
    private $avatar = null;
    private $user_roles = null;
    private $post_count = null;
    private $metas;
    private $data;
    private $has_data;
    private $prefetched_core = array();
    private $prefetched_meta = array();
    private static $request_cache = null;
    public function __construct( $seed = 0, $type = 'user' )
    {
        $seed = apply_filters( 'authorship/author/id', $seed, $type );
        $type = apply_filters( 'authorship/author/type', $type, $seed );

        if ( $seed instanceof \WP_User )
        {
            $this->id        = (int) $seed->ID;
            $this->type      = 'user';
            $this->wp_object = $seed;
        }
        elseif ( $seed instanceof \WP_Post )
        {
            $this->id        = (int) $seed->ID;
            $this->type      = 'guest';
            $this->wp_object = $seed;
        }
        elseif ( is_numeric( $seed ) && !empty( $seed ) && in_array( $type, array( 'user', 'guest', 'virtual' ), true ) )
        {
            $this->id   = (int) $seed;
            $this->type = $type;
        }
        elseif ( 'dummy' === $type )
        {
            $this->id   = 0;
            $this->type = $type;
            $this->seed_dummy_cache( $this->dummy_defaults() );
        }
        else
        {
            $post_authors = Authors::find();
            $main_author  = is_array( $post_authors ) ? reset( $post_authors ) : false;

            if ( is_object( $main_author )
                && !empty( $main_author->id )
                && !empty( $main_author->type )
            )
            {
                $this->id   = $main_author->id;
                $this->type = $main_author->type;
            }
        }
        $this->is_user    = ( $this->type === 'user'    );
        $this->is_guest   = ( $this->type === 'guest'   );
        $this->is_dummy   = ( $this->type === 'dummy'   );
        $this->is_virtual = ( $this->type === 'virtual' );

        if ( $this->is_dummy || $this->is_virtual )
        {
            $this->is_user = true;
        }
    }
    public function prime_core( array $core )
    {
        foreach ( $core as $k => $v )
        {
            $this->prefetched_core[$k] = ( $v === null ? '' : $v );
        }
    }
    public function prime_meta( array $meta )
    {
        foreach ( $meta as $k => $v )
        {
            $this->prefetched_meta[$k] = ( $v === null ? '' : $v );
        }
    }
private function get_prefetched_core( $key )
{
    return isset( $this->prefetched_core[$key] ) ? $this->prefetched_core[$key] : null;
}
private function get_prefetched_meta( $key )
{
    return isset( $this->prefetched_meta[$key] ) ? $this->prefetched_meta[$key] : null;
}
    public static function request_cache()
    {
        if ( self::$request_cache === null )
        {
            $enabled  = ( defined('MOLONGUI_RLRU_DISABLED') && MOLONGUI_RLRU_DISABLED ) ? false : true;
            $capacity = defined('MOLONGUI_RLRU_CAPACITY') ? (int) MOLONGUI_RLRU_CAPACITY : 5000;

            self::$request_cache = new Request_LRU_Cache( $capacity, $enabled, 'author' );
        }
        return self::$request_cache;
    }
    public static function set_request_cache( $cache )
    {
        self::$request_cache = $cache;
    }
    private function cache_segment()
    {
        return 'author-' . $this->type . ':' . $this->id;
    }
    private function cache_remember( $key, $producer )
    {
        $cache   = self::request_cache();
        $segment = $this->cache_segment();

        return $cache->remember( $segment, $key, $producer );
    }
    public static function prime_cache( array $payload )
    {
        $id   = isset( $payload['id'] )   ? (int) $payload['id'] : 0;
        $type = isset( $payload['type'] ) ? strtolower( (string) $payload['type'] ) : '';
        if ( ! in_array( $type, array( 'user', 'guest', 'dummy', 'virtual' ), true ) )
        {
            return false;
        }

        $core     = ( isset( $payload['core'] )     && is_array( $payload['core'] ) )     ? $payload['core']     : array();
        $meta     = ( isset( $payload['meta'] )     && is_array( $payload['meta'] ) )     ? $payload['meta']     : array();
        $computed = ( isset( $payload['computed'] ) && is_array( $payload['computed'] ) ) ? $payload['computed'] : array();
        $segment = 'author-' . $type . ':' . $id; // same as $this->cache_segment()
        $cache   = self::request_cache();         // Request_LRU_Cache instance
        if ( ! empty( $core ) )
        {
            foreach ( $core as $field => $value )
            {
                $cache->set( $segment, 'core:' . $field, ( $value === null ? '' : $value ) );
            }
        }
        if ( isset( $meta['avatar'] ) )
        {
            $w   = Settings::get( 'author_box_avatar_width' );
            $h   = Settings::get( 'author_box_avatar_height' );
            $key = 'avatar:box:local:gravatar:' . $w . 'x' . $h;

            $cache->set( $segment, 'computed:' . $key, $meta['avatar'] );
        }
        if ( ! empty( $meta ) )
        {
            foreach ( $meta as $mkey => $mval )
            {
                $cache->set( $segment, 'meta:' . $mkey, ( $mval === null ? '' : $mval ) );
                $cache->set( $segment, 'computed:meta:' . $mkey, ( $mval === null ? '' : $mval ) );
            }
        }
        if ( ! empty( $computed ) )
        {
            foreach ( $computed as $ckey => $cval )
            {
                $cache->set( $segment, 'computed:' . $ckey, ( $cval === null ? '' : $cval ) );
            }
        }

        return true;
    }
    protected function get_core_value( $field )
    {
        $key = 'core:' . $field;

        return $this->cache_remember( $key, function () use ( $field )
        {
            if ( isset( $this->prefetched_core ) && array_key_exists( $field, $this->prefetched_core ) )
            {
                return $this->prefetched_core[ $field ];
            }
            if ( $this->is_user )
            {
                $user = $this->get_wp_object(); // however you lazy-load WP_User
                $val  = $user && isset( $user->$field ) ? $user->$field : '';
            }
            else
            {
                $val = get_post_field( $field, $this->id );
            }
            return ( $val === null ) ? '' : $val;
        } );
    }
    protected function get_meta_value( $meta_key )
    {
        $key = 'meta:' . $meta_key;

        return $this->cache_remember( $key, function () use ( $meta_key )
        {
            if ( isset( $this->prefetched_meta ) && array_key_exists( $meta_key, $this->prefetched_meta ) )
            {
                return $this->prefetched_meta[ $meta_key ];
            }
            if ( $this->is_user )
            {
                $val = get_user_meta( $this->id, $meta_key, true );
            }
            else
            {
                $val = get_post_meta( $this->id, $meta_key, true );
            }

            return ( $val === null ) ? '' : $val;
        } );
    }
    protected function get_computed_value( $label, $producer, $filter_tag = '' )
    {
        $key = 'computed:' . $label;

        return $this->cache_remember( $key, function () use ( $label, $producer, $filter_tag )
        {
            $value = apply_filters( 'molongui_authorship/pre_compute_value', null, $label, $this );
            if ( $value === null )
            {
                $value = call_user_func( $producer );
            }
            if ( $value === null )
            {
                $value = '';
            }
            if ( is_string( $filter_tag ) && $filter_tag !== '' )
            {
                $value = apply_filters( $filter_tag, $value, $this );
            }

            return $value;
        } );
    }
    protected function qualify_meta_key( $short_key )
    {
        if ( $this->type === 'user' )
        {
            return self::USER_META_PREFIX . $short_key;
        }

        return self::GUEST_META_PREFIX . $short_key;
    }
    private function get_all_plugin_meta()
    {
        $prefix = $this->is_user ? self::USER_META_PREFIX : self::GUEST_META_PREFIX;

        return $this->cache_remember( 'meta:all_plugin', function () use ( $prefix )
        {
            $all_meta = $this->is_user ? get_user_meta( $this->id ) : get_post_meta( $this->id );

            if ( ! is_array( $all_meta ) || empty( $all_meta ) )
            {
                return array();
            }

            $plugin_meta = array();

            foreach ( $all_meta as $meta_key => $values )
            {
                if ( ! is_string( $meta_key ) || strpos( $meta_key, $prefix ) !== 0 )
                {
                    continue;
                }
                if ( ! is_array( $values ) )
                {
                    $values = array( $values );
                }

                $plugin_meta[ $meta_key ] = $values;
                if ( ! array_key_exists( $meta_key, $this->prefetched_meta ) )
                {
                    $first = isset( $values[0] ) ? $values[0] : '';
                    $this->prefetched_meta[ $meta_key ] = ( $first === null ) ? '' : $first;
                }
            }

            return $plugin_meta;
        } );
    }
    final public function get_id()
    {
        return $this->id;
    }
    final public function get_type()
    {
        return $this->type;
    }
    public function get_user_login()
    {
        return $this->get_computed_value(
            'login',
            function ()
            {
                $login = ( $this->is_user )
                    ? (string) $this->get_core_value( 'user_login' )
                    : '';

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/login' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_user_login' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $login = apply_filters_deprecated( 'molongui_authorship/get_author_user_login', array( $login, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/login' );
                }

                return $login;
            },
            'molongui_authorship/author/login'
        );
    }
    public function get_nicename()
    {
        return $this->get_computed_value(
            'slug',
            function ()
            {
                $slug = ( $this->is_user )
                    ? (string) $this->get_core_value( 'user_nicename' )
                    : (string) $this->get_core_value( 'post_name' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/slug' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_slug' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $slug = apply_filters_deprecated( 'molongui_authorship/get_author_slug', array( $slug, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/slug' );
                }

                return $slug;
            },
            'molongui_authorship/author/slug'
        );
    }
    public function get_slug()
    {
        return $this->get_nicename();
    }
    public function get_email()
    {
        return $this->get_computed_value(
            'email',
            function ()
            {
                $email = ( $this->is_user )
                    ? (string) $this->get_core_value( 'user_email' )
                    : (string) $this->get_meta_value( self::GUEST_META_PREFIX . 'mail' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/email' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_email' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $email = apply_filters_deprecated( 'molongui_authorship/get_author_email', array( $email, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/email' );
                }

                return $email;
            },
            'molongui_authorship/author/email'
        );
    }
    public function get_website()
    {
        return $this->get_computed_value(
            'website',
            function ()
            {
                $website = ( $this->is_user )
                    ? (string) $this->get_core_value( 'user_url' )
                    : (string) $this->get_meta_value( '_molongui_guest_author_web' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/website' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_website' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $website = apply_filters_deprecated( 'molongui_authorship/get_author_website', array( $website, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/website' );
                }

                return $website;
            },
            'molongui_authorship/author/website'
        );
    }
    public function get_display_name( $ignore_format = false )
    {
        return $this->get_computed_value(
            'display_name',
            function () use ( $ignore_format )
            {
                if ( $ignore_format )
                {
                    $display_name = ( $this->is_user )
                        ? (string) $this->get_core_value( 'display_name' )
                        : (string) $this->get_core_value( 'post_title' );
                }
                else
                {
                    /*!
                     * DEPRECATED
                     * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                     *
                     * @deprecated 5.2.0 Use 'molongui_authorship/author/display_name_format' instead
                     */
                    $format = 'display_name';
                    if ( has_filter( 'authorship/author/name/format' ) && apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                    {
                        $format = apply_filters_deprecated( 'authorship/author/name/format', array( $format ), '5.2.0', 'molongui_authorship/author/display_name_format' );
                    }
                    $format = apply_filters( 'molongui_authorship/author/display_name_format', $format, $this );
                    switch ( $format )
                    {
                        case 'first_name_first':
                            $first_name   = $this->get_first_name();
                            $last_name    = $this->get_last_name();
                            $display_name = $first_name . '&nbsp;' . $last_name;
                            break;

                        case 'last_name_first':
                            $first_name = $this->get_first_name();
                            $last_name  = $this->get_last_name();
                            if ( empty( $first_name ) && empty( $last_name ) )
                            {
                                $display_name = $this->get_display_name( true );
                            }
                            else
                            {
                                $display_name  = $last_name;
                                $display_name .= ( !empty( $first_name ) ) ? ',&nbsp;' . $first_name : '';
                            }
                            break;

                        case 'display_name':
                        default:
                            $display_name = ( $this->is_user )
                                ? (string) $this->get_core_value( 'display_name' )
                                : (string) $this->get_core_value( 'post_title' );
                            break;
                    }
                }

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/display_name' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_display_name' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $display_name = apply_filters_deprecated( 'molongui_authorship/get_author_display_name', array( $display_name, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/display_name' );
                }

                return $display_name;
            },
            'molongui_authorship/author/display_name'
        );
    }
    public function get_first_name()
    {
        return $this->get_computed_value(
            'first_name',
            function ()
            {
                $first_name = ( $this->is_user )
                    ? (string) $this->get_meta( 'first_name' )
                    : (string) $this->get_meta( '_molongui_guest_author_first_name' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/first_name' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_first_name' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $first_name = apply_filters_deprecated( 'molongui_authorship/get_author_first_name', array( $first_name, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/first_name' );
                }

                return $first_name;
            },
            'molongui_authorship/author/first_name'
        );
    }
    public function get_last_name()
    {
        return $this->get_computed_value(
            'last_name',
            function ()
            {
                $last_name = ( $this->is_user )
                    ? (string) $this->get_meta( 'last_name' )
                    : (string) $this->get_meta( '_molongui_guest_author_last_name' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/last_name' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_last_name' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $last_name = apply_filters_deprecated( 'molongui_authorship/get_author_last_name', array( $last_name, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/last_name' );
                }

                return $last_name;
            },
            'molongui_authorship/author/last_name'
        );
    }
    public function get_archive_url()
    {
        return $this->get_computed_value(
            'archive_url',
            function ()
            {
                if ( $this->is_user )
                {
                    add_filter( 'authorship/pre_author_link', array( Helpers::class, 'short_circuit' ), 10, 4 );
                    add_filter( 'molongui_authorship_dont_filter_name', '__return_true' );
                    add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );

                    $url = get_author_posts_url( $this->id );
                    $url = is_string( $url ) ? $url : '';

                    remove_filter( 'authorship/pre_author_link', array( Helpers::class, 'short_circuit' ), 10 );
                    remove_filter( 'molongui_authorship_dont_filter_name', '__return_true' );
                    remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                }
                else
                {
                    $url = '#molongui-disabled-link';
                }

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/archive_url' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_archive_url' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $url = apply_filters_deprecated(
                        'molongui_authorship/get_author_archive_url',
                        array( $url, $this->id, $this->type, $this ),
                        '5.2.0',
                        'molongui_authorship/author/archive_url' );
                }

                return $url;
            },
            'molongui_authorship/author/archive_url'
        );
    }
    public function get_link()
    {
        return $this->get_computed_value(
            'link',
            function ()
            {
                $url  = $this->get_archive_url();
                $name = $this->get_display_name();
                if ( $url === '' || $name === '' )
                {
                    return '';
                }
                $html = sprintf(
                    '<a href="%s" rel="author" class="molongui-author-link" data-author-id="%d">%s</a>',
                    esc_url( $url ),
                    (int) $this->id,
                    esc_html( $name )
                );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/link' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_link' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $html = apply_filters_deprecated( 'molongui_authorship/get_author_link', array( $html, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/link' );
                }

                return $html;
            },
            'molongui_authorship/author/link'
        );
    }
    public function get_description()
    {
        return $this->get_computed_value(
            'description',
            function ()
            {
                $description = ( $this->is_user )
                    ? (string) $this->get_meta( 'description' )
                    : (string) $this->get_core_value( 'post_content' );

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/description' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_description' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $description = apply_filters_deprecated( 'molongui_authorship/get_author_description', array( $description, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/description' );
                }

                return $description;
            },
            'molongui_authorship/author/description'
        );
    }
    public function get_social_profile( $network )
    {
        $network = sanitize_key( $network );

        if ( empty( $network ) )
        {
            return '';
        }

        return $this->get_computed_value(
            'social_profile:' . $network,
            function() use ( $network )
            {
                $value = (string) $this->get_meta( $network );

                if ( '' !== trim( $value ) )
                {
                    return $value;
                }
                if ( 'user' !== $this->type )
                {
                    return '';
                }
                $compatible_meta = User::get_compatible_social_meta_keys();

                if ( empty( $compatible_meta[ $network ] ) || !is_array( $compatible_meta[ $network ] ) )
                {
                    return '';
                }
                foreach ( $compatible_meta[ $network ] as $meta_key )
                {
                    if ( !is_string( $meta_key ) || '' === $meta_key )
                    {
                        continue;
                    }

                    $external_value = $this->get_meta_value( $meta_key );

                    if ( !is_scalar( $external_value ) )
                    {
                        continue;
                    }

                    $external_value = trim( (string) $external_value );

                    if ( '' !== $external_value )
                    {
                        return $external_value;
                    }
                }

                return '';
            },
            "molongui_authorship/author/social_profile/{$network}"
        );
    }
    public function get_social()
    {
        return $this->get_computed_value(
            'social',
            function()
            {
                $enabled = Social::get( 'enabled' );

                if ( empty( $enabled ) || !is_array( $enabled ) )
                {
                    return array();
                }

                $links = array();
                foreach ( $enabled as $network => $flag )
                {
                    if ( empty( $flag ) )
                    {
                        continue;
                    }

                    $raw = $this->get_social_profile( $network );

                    if ( '' === $raw )
                    {
                        continue;
                    }
                    $url = $this->build_social_url( $network, $raw );

                    if ( '' === $url )
                    {
                        continue;
                    }

                    $links[$network] = $url;
                }

                return $links;
            },
            'molongui_authorship/author/social'
        );
    }
    public function get_core( $field, $default = '' )
    {
        $normalized = $this->normalize_core_field_name( $field );
        if ( $normalized === '' )
        {
            return (string) $default;
        }
        $label = 'core:' . $normalized;

        return $this->get_computed_value(
            $label,
            function()
            use ( $normalized, $default )
            {
                $val = $this->get_core_value( $normalized );
                if ( $val === '' )
                {
                    $val = (string) $default;
                }
                return (string) $val;
            },
            "molongui_authorship/author/{$normalized}"
        );
    }
    public function get_meta( $key )
    {
        if ( empty( $key ) )
        {
            Debug::console_log( "Which meta value do you want to retrieve? You need to provide a 'key' attribute.", __FUNCTION__ );
            return '';
        }

        $key = (string) $key;
        if ( $key === 'all' )
        {
            return $this->get_all_plugin_meta();
        }
        $label = 'meta:' . $key;
        if ( $this->type === 'user' )
        {
            $map = $this->get_user_virtual_meta_map();

            if ( isset( $map[$key] ) )
            {
                $core_field = (string) $map[$key];

                return $this->get_computed_value(
                    $label,
                    function() use ( $core_field, $key )
                    {
                        $value = (string) $this->get_core_value( $core_field );

                        /*!
                         * DEPRECATED
                         * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                         *
                         * @since      5.0.0
                         * @deprecated 5.2.0 Use "molongui_authorship/author/{$key}" instead
                         */
                        if ( has_filter( "molongui_authorship/get_author_meta_{$key}" ) && apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                        {
                            $value = apply_filters_deprecated(
                                "molongui_authorship/get_author_meta_{$key}",
                                array( $value, $this->id, $this->type, $this ),
                                '5.2.0',
                                "molongui_authorship/author/{$key}"
                            );
                        }

                        return $value;
                    },
                    "molongui_authorship/author/{$key}"
                );
            }
        }
        $is_full_user   = ( strpos( $key, self::USER_META_PREFIX ) === 0 );
        $is_full_guest  = ( strpos( $key, self::GUEST_META_PREFIX ) === 0 );
        $keep_native    = ( $this->type === 'user' && in_array( $key, self::$allowed_user_meta_keys, true ) );
        $final_meta_key = ( $is_full_user || $is_full_guest || $keep_native  ) ? $key : $this->qualify_meta_key( $key );

        return $this->get_computed_value(
            $label,
            function () use ( $final_meta_key, $key )
            {
                if ( $this->is_user && apply_filters( '_molongui_authorship/author/use_get_the_author_meta', false, $key, $this ) )
                {
                    add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                    $raw = get_the_author_meta( $final_meta_key, $this->id );
                    remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );

                    $value = is_string( $raw ) ? $raw : (string) $raw;
                }
                else
                {
                    $value = (string) $this->get_meta_value( $final_meta_key );
                }

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.0
                 * @deprecated 5.2.0 Use "molongui_authorship/author/{$key}" instead
                 */
                if ( has_filter( "molongui_authorship/get_author_meta_{$key}" ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $value = apply_filters_deprecated(
                        "molongui_authorship/get_author_meta_{$key}",
                        array( $value, $this->id, $this->type, $this ),
                        '5.2.0',
                        "molongui_authorship/author/{$key}" );
                }

                return $value;
            },
            "molongui_authorship/author/{$key}"
        );
    }
    public function get_wp_object()
    {
        if ( $this->wp_object )
        {
            return $this->wp_object;
        }

        if ( $this->type === 'user' )
        {
            add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
            $this->wp_object = get_user_by( 'id', $this->id ) ?: null;
            remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );

        } else
        {
            $post            = get_post( $this->id );
            $this->wp_object = ( $post && $post->post_type === self::GUEST_CPT) ? $post : null;
        }
        if ( ( !$this->wp_object or !is_object( $this->wp_object ) ) )
        {
            $this->wp_object = new \WP_User();
            Debug::console_log( sprintf( "No %s exists with the given ID (%s).", ( $this->type == 'guest' ? __( 'guest author', 'molongui-authorship' ) : __( 'user', 'molongui-authorship' ) ), $this->id ), __FUNCTION__ );
        }

        return $this->wp_object;
    }
    public function get_user_roles()
    {
        if ( isset( $this->user_roles ) )
        {
            return $this->user_roles;
        }

        switch ( $this->type )
        {
            case 'user':
                $user_roles  = $this->get_wp_object()->roles;
                break;

            case 'guest':
                $user_roles = array( _x( "Guest Author", 'User role', 'molongui-authorship' ) );
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
         *
         * @since      5.0.0
         * @deprecated 5.2.0 Use 'molongui_authorship/author/user_roles' instead
         */
        if ( has_filter( 'molongui_authorship/get_author_user_roles' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false, __FUNCTION__ ) )
        {
            $user_roles = apply_filters_deprecated( 'molongui_authorship/get_author_user_roles', array( $user_roles, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/user_roles' );
        }
        $user_roles = apply_filters( 'molongui_authorship/author/user_roles', $user_roles, $this );

        $this->user_roles = $user_roles;

        return $user_roles;
    }
    public function get_avatar( $size = 'full', $context = 'screen', $source = null, $default = null )
    {
        $options = Settings::get();
        $size    = apply_filters( 'authorship/get_avatar/size', $size, $options );
        $context = apply_filters( 'authorship/get_avatar/context', $context, $options );
        $source  = apply_filters( 'authorship/get_avatar/source', $source, $options );
        $default = apply_filters( 'authorship/get_avatar/default', $default, $options );
        if ( is_array( $size ) )
        {
            $width  = max( 1, (int) $size[0] );
            $height = max( 1, (int) $size[1] );
            $resolved_size = array( $width, $height );
        }
        else
        {
            $sizes  = wp_get_registered_image_subsizes();
            $slug   = ( is_string( $size ) && isset( $sizes[ $size ] ) ) ? $size : 'authorship-box-avatar';
            $width  = isset( $sizes[$slug]['width']  ) ? (int) $sizes[$slug]['width']  : 150;
            $height = isset( $sizes[$slug]['height'] ) ? (int) $sizes[$slug]['height'] : 150;
            $resolved_size = $slug;
        }
        if ( 'box' === $context )
        {
            if ( apply_filters( 'molongui_authorship/load_author_box_styles', true ) )
            {
                if ( isset( $options['author_box_avatar_width'], $options['author_box_avatar_height'] ) )
                {
                    $width  = (int) $options['author_box_avatar_width'];
                    $height = (int) $options['author_box_avatar_height'];
                    $resolved_size = array( $width, $height );
                }
            }
        }
        $attr = array( 'width' => $width, 'height' => $height );
        if ( !empty( $options['seo_settings_enabled'] ) && !empty( $options['schema_markup_enabled'] ) )
        {
            $attr['itemprop'] = 'image';
        }

        /*!
         * FILTER HOOK
         * Allows filtering the attributes for the returned <img> HTML tag.
         *
         * add_filter( 'molongui_authorship/author_avatar_attr', function( $attr )
         * {
         *    //$attr = array_merge( $attr, array( 'extra_attr' => 'style="border-radius:100%;"' ) ); // This doesn't work!
         *      $attr = array_merge( $attr, array( 'style' => 'border-radius:100%;' ) );
         *
         *      $attr = array_merge( $attr, array( 'class' => 'myCustomClass' ) );
         *
         *      return $attr;
         * }
         *
         * @param array  $attr    HTML attributes to insert in the IMG element. Is not sanitized.
         * @param int    $id      The author id.
         * @param int    $type    The author type, either 'user' or 'guest'.
         * @param string $size    The size for the image {thumbnail | medium | medium_large | large | full | "array(x,y)"}.
         * @param string $context Usage context {screen | box | url}.
         * @param Author $this    The current author instance.
         * @since 5.0.14
         */
        $attr = apply_filters(
            'molongui_authorship/author_avatar_attr',
            $attr,
            $this->id,
            $this->type,
            ( is_array( $resolved_size ) ? $resolved_size : array( $width, $height ) ),
            $context,
            $this
        );
        $configured_source = !empty( $options['author_box_avatar_source'] ) ? $options['author_box_avatar_source'] : '';
        $source = $source ? $source : ( $configured_source ? $configured_source : 'local' );
        $avatar_fallback = $default;
        if ( empty( $avatar_fallback ) && !empty( $options['author_box_avatar_fallback'] ) )
        {
            $avatar_fallback = $options['author_box_avatar_fallback'];
        }
        if ( empty( $avatar_fallback ) ) $avatar_fallback = 'none';

        if ( $this->is_dummy )
        {
            return $this->get_dummy_avatar( $attr, $options, $resolved_size, $context, $source, $avatar_fallback, $width, $height );
        }
        $label = 'avatar:' . $context . ':' . $source . ':' . $avatar_fallback . ':' . $width . 'x' . $height;
        $avatar = $this->get_computed_value(
            $label,
            function () use ( $attr, $options, $resolved_size, $context, $source, $avatar_fallback, $width, $height )
            {
                $result = '';
                if ( 'gravatar' === $source )
                {
                    $result = ( 'url' === $context )
                        ? get_avatar_url( $this->get_email() )
                        : $this->get_gravatar( $attr, $options );
                }
                elseif ( 'acronym' === $source )
                {
                    $result = ( 'url' === $context )
                        ? ''
                        : $this->get_acronym( $attr, $options );
                }
                else // 'local' (or unknown → treat as 'local')
                {
                    if ( Settings::is_enabled( 'local-avatar' ) )
                    {
                        if ( $this->is_user )
                        {
                            $img_id = get_user_meta( $this->id, 'molongui_author_image_id', true );
                            if ( $img_id )
                            {
                                $result = ( 'url' === $context )
                                    ? wp_get_attachment_url( $img_id )
                                    : wp_get_attachment_image( $img_id, $resolved_size, false, $attr );
                            }
                        }
                        elseif ( $this->is_guest )
                        {
                            if ( has_post_thumbnail( $this->id ) )
                            {
                                $result = ( 'url' === $context )
                                    ? get_the_post_thumbnail_url( $this->id, $resolved_size )
                                    : get_the_post_thumbnail( $this->id, $resolved_size, $attr );
                            }
                        }
                    }
                    if ( '' === $result )
                    {
                        if ( 'gravatar' === $avatar_fallback )
                        {
                            $result = ( 'url' === $context )
                                ? get_avatar_url( $this->get_email() )
                                : $this->get_gravatar( $attr, $options );
                        }
                        elseif ( 'acronym' === $avatar_fallback )
                        {
                            $result = ( 'url' === $context )
                                ? ''
                                : $this->get_acronym( $attr, $options );
                        }
                        else
                        {
                            $result = ''; // 'none' (and any other unrecognized token)
                        }
                    }
                }

                if ( !isset( $result ) )
                {
                    $result = '';
                }
                return apply_filters( 'molongui_authorship/get_author_avatar',
                    $result,
                    $this->id,
                    $this->type,
                    ( is_array( $resolved_size ) ? $resolved_size : array( $width, $height ) ),
                    $context,
                    $this
                );
            },
            ''
        );
        $this->avatar = $avatar;
        return $avatar;
    }
    public function get_gravatar( $attr, $options = array() )
    {
        if ( empty( $options ) )
        {
            $options = Settings::get();
        }
        $attr['force_display'] = true;
        $size  = get_option( 'thumbnail_size_w', 96 );
        $has_w = !empty( $attr['width'] );
        $has_h = !empty( $attr['height'] );

        if     (  $has_w and  $has_h ) $size = min( $attr['width'], $attr['height'] );
        elseif (  $has_w and !$has_h ) $size = $attr['width'];
        elseif ( !$has_w and  $has_h ) $size = $attr['height'];
        $attr['extra_attr']  = '';
        if ( !empty( Settings::get( 'seo_settings_enabled' ) ) and !empty( Settings::get( 'schema_markup_enabled' ) ) )
        {
            $attr['extra_attr'] .= 'itemprop = "image"';
        }
        $default = $options['author_box_avatar_default_gravatar'];
        if ( 'random' === $default )
        {
            $defaults = array( 'mp', 'identicon', 'monsterid', 'wavatar', 'retro', 'robohash', 'blank' );
            $default  = $defaults[array_rand( $defaults )];
        }
        add_filter( 'authorship/get_avatar_data/skip', '__return_true' );
        $gravatar = get_avatar( $this->get_email(), $size, $default, false, $attr );
        remove_filter( 'authorship/get_avatar_data/skip', '__return_true' );
        return ( !$gravatar ? '' : $gravatar );
    }
    public function get_acronym( $attr, $options = array() )
    {
        $name = $this->get_display_name();

        if ( empty( $name ) )
        {
            return '';
        }
        if ( empty( $options ) )
        {
            $options = Settings::get();
        }

        $class  = empty( $attr['class'] )  ? '' : $attr['class'];
        $style  = empty( $attr['style'] )  ? '' : $attr['style'];
        $width  = empty( $attr['width'] )  ? '' : ' width:'  . $attr['width'].'px;';
        $height = empty( $attr['height'] ) ? '' : ' height:' . $attr['height'].'px;';
        $html  = '';
        $html .= '<div data-avatar-type="acronym" class="' . $class . ' acronym-container" style="' . $style . $width . $height . '">';
        $html .= '<div>';
        $html .= Helpers::get_acronym( $name );
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
    public function get_data()
    {
        if ( !empty( $this->has_data ) )
        {
            return $this->data;
        }
        $default_fields = array
        (
            'id',
            'type',
            'display_name',
            'first_name',
            'last_name',
            'description',
            'slug',
            'email',
            'phone',
            'website',
            'custom_link',
            'archive_url',
            'avatar',
            'company',
            'company_link',
            'post_counts',
            'user_roles',
            'user_login',
            'show_meta_phone',
            'social_profiles',
            'job',             // @deprecated: Use 'position' instead
            'box_display',     // @deprecated: Use 'author_box_display' instead
            'show_meta_mail',  // @deprecated: Use 'show_meta_email' instead
            'show_icon_mail',  // @deprecated: Use 'show_social_email' instead
            'show_icon_web',   // @deprecated: Use 'show_social_web' instead
            'show_icon_phone', // @deprecated: Use 'show_social_phone' instead
            'archived',        // @deprecated: Use 'is_archived' instead
        );

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
         *
         * @since      4.5.2
         * @deprecated 5.2.0
         */
        if ( has_filter( 'molongui_authorship/get_author_data_fields' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
        {
            $default_fields = apply_filters_deprecated( 'molongui_authorship/get_author_data_fields', array( $default_fields ), '5.2.0', 'molongui_authorship/author/default_fields' );
        }
        $fields = apply_filters( 'molongui_authorship/author/default_fields', $default_fields, $this );
        $social_profile_keys   = self::get_enabled_social_profile_keys();
        $social_profile_lookup = array_flip( $social_profile_keys );

        if ( ( $key = array_search( 'social_profiles', $fields, true ) ) !== false )
        {
            $fields = array_merge( $fields, $social_profile_keys );
            unset( $fields[$key] );
            $fields = array_values( $fields );
        }
        if ( !empty( $fields ) )
        {
            foreach ( $fields as $field )
            {
                if ( isset( $social_profile_lookup[$field] ) )
                {
                    $this->data[$field] = $this->get_social_profile( $field );
                    continue;
                }
                $method = 'get_' . $field;
                $params = array();

                if ( 'avatar' === $field )
                {
                    $params = array( 'thumbnail', 'box' );
                }

                if ( method_exists( $this, $method ) )
                {
                    $this->data[$field] = $this->{$method}(...$params);
                }
                else
                {
                    $this->data[$field] = $this->get_meta( $field );
                }
            }

            $this->has_data = $fields;
        }

        /*!
         * FILTER HOOK
         * Allows filtering the fetched data before it is returned.
         *
         * @param string $data The author's data.
         * @param int    $id   The author's id.
         * @param int    $type The author's type, either 'user' or 'guest'.
         * @param Author $this The current author instance.
         * @since 5.0.0
         */
        return apply_filters( 'molongui_authorship/get_author_data', $this->data, $this->id, $this->type, $this->author );
    }
    public function get_posts( $args = null )
    {

        $defaults = array
        (
            'cat'                 => '',
            'fields'              => 'all',
            'ignore_sticky_posts' => true,
            'meta_query'          => array(),
            'no_found_rows'       => true,   // We'll flip to false only when pagination is clearly requested.
            'offset'              => 0,
            'order'               => 'DESC',
            'orderby'             => 'date',
            'post__in'            => array(),
            'post__not_in'        => array(),
            'post_type'           => 'post',
            'post_status'         => '',
            'posts_per_page'      => -1,
'author_id'           => $this->id,
'author_type'         => $this->type,
'site_id'             => apply_filters( 'authorship/get_posts/blog_id', get_current_blog_id() ),
'language'            => apply_filters( 'authorship/get_posts/language', Helpers::get_language() ),

        );
        $a = wp_parse_args( $args, $defaults );

        $fields = is_string( $a['fields'] ) ? strtolower( $a['fields'] ) : 'all';
        if ( ! in_array( $fields, array( 'all', 'ids', 'id=>parent' ), true ) )
        {
            $fields = 'all';
        }

        $order = strtoupper( (string) $a['order'] );
        if ( $order !== 'ASC' && $order !== 'DESC' )
        {
            $order = 'DESC';
        }

        $orderby = is_string( $a['orderby'] ) ? strtolower( trim( $a['orderby'] ) ) : 'date';
        $allowed_orderby = array
        (
            'none',
            'id',
            'author',
            'title',
            'name',
            'date',
            'modified',
            'parent',
            'rand',
            'comment_count',
            'menu_order',
            'meta_value',
            'meta_value_num',
            'post__in',
        );
        if ( ! in_array( $orderby, $allowed_orderby, true ) )
        {
            $orderby = 'date';
        }

        $posts_per_page = (int) $a['posts_per_page'];
        if ( $posts_per_page <= -1 )
        {
            $posts_per_page = -1;
        }

        $offset = (int) $a['offset'];
        if ( $offset < 0 )
        {
            $offset = 0;
        }

        $cat_args = Post::sanitize_cat_query_args( $a['cat'] );

        $post__in = $a['post__in']; // accept array|string|int.
        if ( is_string( $post__in ) )
        {
            $post__in = array_filter( array_map( 'absint', explode( ',', $post__in ) ) );
        }
        elseif ( is_numeric( $post__in ) )
        {
            $post__in = array( absint( $post__in ) );
        }
        else
        {
            $post__in = array_filter( array_map( 'absint', (array) $post__in ) );
        }
        $post__in = array_values( array_unique( $post__in ) );

        $post__not_in = $a['post__not_in']; // accept array|string|int.
        if ( is_string( $post__not_in ) )
        {
            $post__not_in = array_filter( array_map( 'absint', explode( ',', $post__not_in ) ) );
        }
        elseif ( is_numeric( $post__not_in ) )
        {
            $post__not_in = array( absint( $post__not_in ) );
        }
        else
        {
            $post__not_in = array_filter( array_map( 'absint', (array) $post__not_in ) );
        }
        $post__not_in = array_values( array_unique( $post__not_in ) );

        $meta_query = is_array( $a['meta_query'] ) ? $a['meta_query'] : array();

        $ignore_sticky_posts = (bool) $a['ignore_sticky_posts'];
        $no_found_rows_raw   = (bool) $a['no_found_rows'];

        if ( is_string( $a['post_type'] ) && $a['post_type'] !== '' )
        {
            switch ( $a['post_type'] )
            {
                case 'all':
                    $post_types = Post::get_post_types();
                    break;

                case 'selected':
                    $post_types = Settings::enabled_post_types();
                    break;

                case 'related':
                    break;

                default:
                    $post_types = array( sanitize_key( $a['post_type'] ) );
                    break;
            }
        }
        elseif ( is_array( $a['post_type'] ) )
        {
            $post_types = array();
            foreach ( $a['post_type'] as $pt )
            {
                $pt = sanitize_key( $pt );
                if ( $pt !== '' )
                {
                    $post_types[] = $pt;
                }
            }
            $post_types = array_values( array_unique( $post_types ) );
        }
        else
        {
            $post_types = array( 'post' );
        }

        $post_status = Post::sanitize_post_status_arg( $a['post_status'] );
        $no_found_rows = ( $posts_per_page > 0 ) ? false : $no_found_rows_raw;
        $raw_args = wp_parse_args( $args, array() );

        $caller_set_order          = array_key_exists( 'order', $raw_args );
        $caller_set_orderby        = array_key_exists( 'orderby', $raw_args );
        $caller_set_posts_per_page = array_key_exists( 'posts_per_page', $raw_args );
        if ( is_string( $a['post_type'] ) && $a['post_type'] === 'related' )
        {
            $related_post_types = array_filter(
                array_map(
                    'sanitize_key',
                    array_map( 'trim', explode( ',', (string) Settings::get( 'author_box_related_posts_post_types' ) ) )
                )
            );
            if ( ! empty( $related_post_types ) )
            {
                $post_types = array_values( array_unique( $related_post_types ) );
            }
            if ( ! $caller_set_posts_per_page )
            {
                $related_count = (int) Settings::get( 'author_box_related_posts_count' );
                $posts_per_page = ( $related_count > 0 ) ? $related_count : -1;
            }
            if ( ! $caller_set_orderby )
            {
                $settings_orderby = is_string( Settings::get( 'author_box_related_posts_orderby' ) )
                    ? strtolower( trim( (string) Settings::get( 'author_box_related_posts_orderby' ) ) )
                    : 'date';
                if ( ! in_array( $settings_orderby, $allowed_orderby, true ) )
                {
                    $settings_orderby = 'date';
                }

                $orderby = $settings_orderby;
            }
            if ( ! $caller_set_order )
            {
                $settings_order = strtoupper( (string) Settings::get( 'author_box_related_posts_order' ) );
                if ( $settings_order !== 'ASC' && $settings_order !== 'DESC' )
                {
                    $settings_order = 'DESC';
                }

                $order = $settings_order;
            }
        }
        $no_found_rows = ( $posts_per_page > 0 ) ? false : $no_found_rows_raw;
        $coauthors_enabled = (bool) Settings::is_co_authors_enabled();
        $guests_enabled    = (bool) Settings::is_guest_author_enabled();

        $use_relation_meta = ( $coauthors_enabled || $guests_enabled );
        $use_native_author = ( ! $coauthors_enabled && ! $guests_enabled );

        if ( $use_relation_meta )
        {
            $ref = ( $this->type === 'user' ) ? ( 'user-' . $this->id ) : ( 'guest-' . $this->id );
            $meta_query[] = array
            (
                'key'     => self::RELATION_META_KEY,
                'value'   => $ref,
                'compare' => '==',
            );
        }
        if ( is_string( $a['post_type'] ) && $a['post_type'] === 'related' )
        {
            $current_post_id = (int) get_queried_object_id();
            if ( $current_post_id > 0 )
            {
                $post__not_in[] = $current_post_id;
                $post__not_in   = array_values( array_unique( array_filter( array_map( 'absint', $post__not_in ) ) ) );
            }
        }
        if ( $orderby === 'post__in' && empty( $post__in ) )
        {
            $orderby = 'date';
        }
        $q_args = array
        (
            'post_type'              => $post_types,
            'post_status'            => $post_status,
            'fields'                 => $fields, // 'all' | 'ids' | 'id=>parent'
            'orderby'                => $orderby,
            'order'                  => $order,
            'posts_per_page'         => $posts_per_page,
            'offset'                 => $offset,
            'ignore_sticky_posts'    => (bool) $ignore_sticky_posts,
            'no_found_rows'          => (bool) $no_found_rows,
            'meta_query'             => $meta_query,
            'post__in'               => $post__in,
            'post__not_in'           => $post__not_in,
            'suppress_filters'       => false,
            'update_post_term_cache' => ( $fields === 'all' ), // if only IDs, don't waste time filling term cache
            'update_post_meta_cache' => ( $fields === 'all' ), // if only IDs, skip meta cache
        );
        $q_args = array_merge( $q_args, $cat_args );

        if ( $use_native_author && $this->type === 'user' )
        {
            $q_args['author'] = (int) $this->id;
        }

        /*!
         * FILTER HOOK
         * Allow last-second customization of the query args.
         *
         * @param array  $q_args WP_Query args that will be used to fetch authored posts.
         * @param Author $this   The current author instance.
         * @since 5.2.0
         */
        $q_args = apply_filters( 'molongui_authorship/author/get_posts/query_args', $q_args, $this );

        $resolved = array
        (
            'fields'              => $fields,
            'ignore_sticky_posts' => $ignore_sticky_posts,
            'meta_query'          => $meta_query,
            'no_found_rows'       => $no_found_rows,
            'offset'              => $offset,
            'order'               => $order,
            'orderby'             => $orderby,
            'post__in'            => $post__in,
            'post__not_in'        => $post__not_in,
            'post_type'           => $a['post_type'],
            'post_types'          => $post_types,

            'post_status'         => $post_status,
            'posts_per_page'      => $posts_per_page,
            'author_id'           => $a['author_id'],
            'author_type'         => $a['author_type'],
            'site_id'             => $a['site_id'],
            'language'            => $a['language'],
        );
        $resolved = array_merge( $resolved, $cat_args );

        if ( $use_native_author && $this->type === 'user' )
        {
            $resolved['author'] = (int) $this->id;
        }

        /*!
         * FILTER HOOK
         * Allows filtering returned posts list before it is generated.
         *
         * Returning a non-null value will effectively short-circuit WP_Query execution, returning that value instead.
         *
         * @param mixed  $posts    The posts list. Default null.
         * @param mixed  $args     Raw method input (array|string|null).
         * @param Author $author   The current author instance.
         * @param array  $parsed   Parsed args after defaults are applied (wp_parse_args()).
         * @param array  $resolved Sanitized/normalized args as they will be used.
         * @param array  $q_args   Prepared WP_Query args that would be executed.
         * @since 5.2.0
         */
        $posts = apply_filters( 'molongui_authorship/author/pre_get_posts', null, $args, $this, $a, $resolved, $q_args );
        if ( null !== $posts )
        {
            return $posts;
        }
        $q = new \WP_Query( $q_args );
        $posts = $q->posts;

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.4.0. Update usages accordingly.
         *
         * @since      5.0.0
         * @deprecated 5.2.0
         */
        if ( has_filter( 'molongui_authorship/get_author_posts' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
        {
            $posts = apply_filters_deprecated( 'molongui_authorship/get_author_posts', array( $posts ), '5.2.0', 'molongui_authorship/author/get_posts' );
        }
        return apply_filters( 'molongui_authorship/author/get_posts', $posts, $args, $this );
    }
    public function get_posts_ids( $args = null )
    {
        $args = wp_parse_args( $args, array() );
        $args['fields'] = 'ids';
        return (array) $this->get_posts( $args );
    }
    public function get_post_counts( $post_types = '', $source = 'meta', $fallback_to_live = false )
    {
        $source           = apply_filters( 'molongui_authorship/author/get_post_counts/source', $source, $this );
        $fallback_to_live = (bool) apply_filters( 'molongui_authorship/author/get_post_counts/allow_fallback', $fallback_to_live, $this );
        $relation_meta_enabled = ( Settings::is_guest_author_enabled() || Settings::is_co_authors_enabled() );
        if ( is_string( $post_types ) )
        {
            $post_types = array( $post_types );
        }
        elseif ( !is_array( $post_types ) )
        {
            $post_types = array();
        }

        $set = array();
        foreach ( $post_types as $pt )
        {
            $pt = sanitize_key( $pt );
            if ( $pt !== '' && post_type_exists( $pt ) )
            {
                $set[$pt] = true;
            }
        }

        if ( empty( $set ) )
        {
            $set = array_fill_keys( Settings::enabled_post_types(), true ); //array( 'post' => true );
        }

        $post_types = array_keys( $set );
        $effective_source = $source;

        if ( ! $relation_meta_enabled )
        {
            if ( $this->type !== 'user' )
            {
                $zero = array();
                foreach ( $post_types as $pt )
                {
                    $zero[$pt] = 0;
                }
                return $zero;
            }
            $effective_source = $source;//'live';
            $fallback_to_live = true;//false;
        }
        return $this->get_computed_value(
            'post_counts:' . implode( ',', $post_types ) . ':' . $effective_source . ':' . ( $fallback_to_live ? '1' : '0' ) . ':' . ( $relation_meta_enabled ? 'rel' : 'native' ),
            function () use ( $post_types, $effective_source, $fallback_to_live, $relation_meta_enabled )
            {
                $counts   = array();
                $use_meta = ( $relation_meta_enabled && strtolower( $effective_source ) !== 'live' );

                foreach ( $post_types as $pt )
                {
                    if ( $use_meta )
                    {
                        $n = $this->read_stored_post_count( $pt );
                        if ( $n === 0 && $fallback_to_live )
                        {
                            $n = $this->compute_live_post_count( $pt );
                        }
                    }
                    else
                    {
                        $n = $this->compute_live_post_count( $pt );
                    }

                    $counts[$pt] = (int) $n;
                }

                /*!
                 * DEPRECATED
                 * This filter hook is scheduled for removal in version 5.4.0. Update any dependencies accordingly.
                 *
                 * @since      5.0.6
                 * @deprecated 5.2.0 Use 'molongui_authorship/author/get_post_counts' instead
                 */
                if ( has_filter( 'molongui_authorship/get_author_post_count' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true, __FUNCTION__ ) )
                {
                    $counts = apply_filters_deprecated( 'molongui_authorship/get_author_post_count', array( $counts, $post_types, $this->id, $this->type, $this ), '5.2.0', 'molongui_authorship/author/get_post_counts' );
                }

                /*!
                 * FILTER HOOK
                 * Final hook to adjust the per-type map (e.g., custom combine rules).
                 *
                 * @param array<string,int> $counts
                 * @param string[]          $post_types
                 * @param Author            $author
                 * @param string            $source
                 * @since 5.2.0
                 */
                return apply_filters( 'molongui_authorship/author/get_post_counts', $counts, $post_types, $this, $effective_source  );
            },
            ''
        );
    }
    private function read_stored_post_count( $post_type )
    {
        $key = ( $this->type === 'user' )
            ? self::USER_META_PREFIX . $post_type . '_count'
            : self::GUEST_META_PREFIX . $post_type . '_count';

        $raw = $this->get_meta_value( $key ); // raw fast path; not run through get_meta() filters
        $n   = (int) $raw;

        return max( 0, $n );
    }
    private function compute_live_post_count( $post_type )
    {
        $use_relation_meta = ( Settings::is_guest_author_enabled() || Settings::is_co_authors_enabled() );
        $strategy = $use_relation_meta ? 'relation-meta' : 'native-author';

        /*!
         * FILTER HOOK
         * Allow a site to provide an authoritative value (short-circuit).
         *
         * @param null              Filtered value must be an integer.
         * @param Author $author    Current author instance.
         * @param string $post_type Target post type.
         * @param string $strategy  Either 'relation-meta' or 'native-author'.
         * @since 5.2.0
         */
        $pre = apply_filters( 'molongui_authorship/author/get_post_counts/live_value', null, $this, $post_type, $strategy );
        if ( is_int( $pre ) )
        {
            return max( 0, $pre );
        }
        if ( ! $use_relation_meta && $this->type !== 'user' )
        {
            return 0;
        }

        global $wpdb;
        $statuses = (array) Admin_Post::get_countable_post_statuses();
        if ( empty( $statuses ) )
        {
            return 0; // Nothing to count against.
        }
        $statuses = array_map( 'sanitize_key', $statuses );
        $where = array
        (
            $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $post_type ),
            $wpdb->prepare(
                "{$wpdb->posts}.post_status IN (" . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ")",
                ...$statuses
            ),
        );
        if ( $use_relation_meta )
        {
            $ref_prefix = ( $this->type === 'user' ) ? 'user-' : 'guest-';
            $ref_value  = $ref_prefix . (int) $this->id;
            $like = '%' . $wpdb->esc_like( $ref_value ) . '%';

            $where[] = $wpdb->prepare( "{$wpdb->postmeta}.meta_key = %s", self::RELATION_META_KEY );
            $where[] = $wpdb->prepare( "{$wpdb->postmeta}.meta_value LIKE %s", $like );
        }
        else
        {
            $where[] = $wpdb->prepare( "{$wpdb->posts}.post_author = %d", (int) $this->id );
        }

        /*!
         * FILTER HOOK
         * Allow callers to tweak WHERE fragments (e.g., include more statuses or custom storage).
         *
         * @param string[] $where     Array of safe SQL fragments (no leading WHERE).
         * @param Author   $author    Current author instance.
         * @param string   $post_type Target post type.
         * @param string   $strategy  Either 'relation-meta' or 'native-author'.
         * @since 5.2.0
         */
        $where = apply_filters( 'molongui_authorship/author/get_post_counts/live_where', $where, $this, $post_type, $strategy );
        if ( $use_relation_meta )
        {
            $sql = "
                SELECT COUNT(1)
                FROM {$wpdb->posts}
                INNER JOIN {$wpdb->postmeta}
                    ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
                WHERE " . implode( ' AND ', $where );
        }
        else
        {
            $sql = "
                SELECT COUNT(1)
                FROM {$wpdb->posts}
                WHERE " . implode( ' AND ', $where );
        }

        /*!
         * FILTER HOOK
         * Last chance to override the SQL (advanced use only).
         *
         * @param string  $sql        The final SQL to execute.
         * @param Author  $author     Current author instance.
         * @param string  $post_type  Target post type.
         * @param string  $strategy   Either 'relation-meta' or 'native-author'.
         * @since 5.2.0
         */
        $sql = apply_filters( 'molongui_authorship/author/get_post_counts/live_sql', $sql, $this, $post_type, $strategy );
        $n = (int) $wpdb->get_var( $sql );

        $n = max( 0, $n );

        $this->persist_post_count( $n, $post_type );

        return $n;
    }
    public function get_post_count( $post_types = '', $source = 'meta', $fallback_to_live = false )
    {
        $map = $this->get_post_counts( $post_types, $source, $fallback_to_live );
        return (int) array_sum( $map );
    }
    public function has_posts( $post_types = '', $source = 'meta', $fallback_to_live = false )
    {
        return ( $this->get_post_count( $post_types, $source, $fallback_to_live ) > 0 );
    }
    public function persist_post_count( $value, $post_type = 'post' )
    {
        $post_type = sanitize_key( $post_type );
        $value     = (int) $value;

        switch ( $this->type )
        {
            case 'user':
                $meta_key = self::USER_META_PREFIX . $post_type . '_count';
                update_user_meta( $this->id, $meta_key, $value );
                break;

            case 'guest':
                $meta_key = self::GUEST_META_PREFIX . $post_type . '_count';
                update_post_meta( $this->id, $meta_key, $value );
                break;
        }
    }
    public function is_user()
    {
        return $this->is_user;
    }
    public function is_guest()
    {
        return $this->is_guest;
    }
    public function has_description()
    {
        return !empty( $this->get_description() );
    }
    public function has_local_avatar( $validate = false )
    {
        if ( ! Settings::is_enabled( 'local-avatar' ) )
        {
            return (bool) apply_filters( 'molongui_authorship/author/has_local_avatar', false, $this->id, $this->type, $validate, $this );
        }

        $label = 'has_local_avatar:' . ( $validate ? '1' : '0' );

        $has = $this->get_computed_value( $label, function () use ( $validate )
        {
            $result = false;

            if ( $this->is_user )
            {
                $img_id = (int) get_user_meta( $this->id, 'molongui_author_image_id', true );
                if ( $img_id > 0 )
                {
                    if ( ! $validate )
                    {
                        $result = true;
                    }
                    else
                    {
                        $url = wp_get_attachment_url( $img_id );
                        $result = ! empty( $url );
                    }
                }

            } elseif ( $this->is_guest )
            {
                if ( has_post_thumbnail( $this->id ) )
                {
                    if ( ! $validate )
                    {
                        $result = true;
                    } else {
                        $thumb_id = (int) get_post_thumbnail_id( $this->id );
                        if ( $thumb_id > 0 )
                        {
                            $url = wp_get_attachment_url( $thumb_id );
                            $result = ! empty( $url );
                        }
                    }
                }
            }

            /*!
             * FILTER HOOK
             * Allows short-circuiting even when the module is disabled (rare use-cases).
             *
             * @param bool   The value to filter before returning. Default false.
             * @param int    Author ID
             * @param string Author type
             * @param bool   Whether to verify that the attachment actually resolves to a URL
             * @param Author Current author instance.
             * @since 5.2.0
             */
            return (bool) apply_filters( 'molongui_authorship/author_has_local_avatar', $result, $this->id, $this->type, $validate, $this );
        });

        return (bool) $has;
    }
    public function is_coauthor_for( $post_id = null )
    {
        if ( !isset( $post_id ) )
        {
            $post_id = Post::get_id();
            if ( empty( $post_id ) )
            {
                return false;
            }
        }
        $post_authors = Post::get_authors( $post_id, 'id' );
        return ( is_array( $post_authors ) and in_array( $this->id, $post_authors ) );
    }
    public function is_archived()
    {
        return !empty( $this->get_meta( 'archived' ) );
    }
    public function is_display_name_available()
    {
        global $wpdb;
        $user_displayname_check  = false;
        $guest_displayname_check = false;
        $name = $this->get_display_name();
        if ( $this->is_user )
        {
            $user_displayname_check  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE display_name = %s AND ID != '{$this->id}' LIMIT 1", $name ) );
            $guest_displayname_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = '".MOLONGUI_AUTHORSHIP_CPT."' LIMIT 1", $name ) );
        }
        else
        {
            $user_displayname_check  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE display_name = %s LIMIT 1", $name ) );
            $guest_displayname_check = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = '".MOLONGUI_AUTHORSHIP_CPT."' AND ID != '{$this->id}' LIMIT 1", $name ) );
        }
        if ( !$user_displayname_check and !$guest_displayname_check ) return false;
        if (  $user_displayname_check and !$guest_displayname_check ) return 'user';
        if ( !$user_displayname_check and  $guest_displayname_check ) return 'guest';
        if (  $user_displayname_check and  $guest_displayname_check ) return 'both';
    }
    public function is_dummy()
    {
        return $this->is_dummy;
    }
    protected function seed_dummy_cache( $defaults )
    {
        $segment = $this->cache_segment();
        $cache   = self::request_cache();

        $has_core_defaults = ( ! empty( $defaults['core'] ) && is_array( $defaults['core'] ) );
        $has_meta_defaults = ( ! empty( $defaults['meta'] ) && is_array( $defaults['meta'] ) );
        $this->prefetched_core = $has_core_defaults ? $defaults['core'] : array();
        $this->prefetched_meta = $has_meta_defaults ? $defaults['meta'] : array();
        if ( $has_core_defaults )
        {
            foreach ( $defaults['core'] as $field => $value )
            {
                $cache->set( $segment, 'core:' . $field, ( $value === null ? '' : $value ) );
            }
        }
        if ( $has_meta_defaults )
        {
            if ( isset( $defaults['meta']['avatar'] ) )
            {
                $w   = Settings::get( 'author_box_avatar_width' );
                $h   = Settings::get( 'author_box_avatar_height' );
                $key = 'avatar:box:local:gravatar:'.$w.'x'.$h;
                $cache->set( $segment, 'computed:' . $key, $defaults['meta']['avatar'] );
                unset( $defaults['meta']['avatar'] );
            }

            foreach ( $defaults['meta'] as $key => $value )
            {
                $cache->set( $segment, 'computed:meta:' . $key, ( $value === null ? '' : $value ) );
            }
        }
    }
    protected static function dummy_defaults()
    {
        $defaults = array
        (
            'core' => array
            (
                'display_name'  => 'John Doe',
                'user_nicename' => 'john-doe',
                'user_email'    => 'john@example.com',
                'user_url'      => 'https://www.example.com',
            ),
            'meta' => array
            (
                'first_name'         => 'John',
                'last_name'          => 'Doe',
                'phone'              => '555-807-8464',
                'mail'               => 'john@example.com',
                'email'              => 'john@example.com',
                'website'            => 'https://www.example.com',
                'custom_link'        => 'https://www.example.com',
                'archive_url'        => '#',
                'avatar'             => '<img src="'.MOLONGUI_AUTHORSHIP_URL.'assets/img/dummy-author-avatar.jpg">',
                'job'                => 'Journalist',
                'position'           => 'Journalist',
                'company'            => 'The Daily Planet',
                'company_link'       => 'https://www.thedailyplanet.com',
                'bio'                => 'John Doe is a seasoned journalist with over 25 years at The Daily Mail. Specializing in investigative reporting and current affairs, John is known for uncovering the truth and delivering compelling stories. His work spans politics, human interest, and breaking news.',
                'description'        => 'John Doe is a seasoned journalist with over 25 years at The Daily Mail. Specializing in investigative reporting and current affairs, John is known for uncovering the truth and delivering compelling stories. His work spans politics, human interest, and breaking news.',
                'short_bio'          => 'John Doe, with 25+ years at The Daily Mail, excels in investigative reporting on politics, human interest, and breaking news.',
                'short_description'  => 'John Doe, with 25+ years at The Daily Mail, excels in investigative reporting on politics, human interest, and breaking news.',
                'post_count'         => 0,
                'post_counts'        => array(),
                'user_roles'         => array(),
                'box_display'        => 1,
                'author_box_display' => 1,
                'show_meta_mail'     => 0,
                'show_meta_email'    => 0,
                'show_meta_phone'    => 0,
                'show_icon_mail'     => 0,
                'show_social_email'  => 0,
                'show_icon_web'      => 0,
                'show_social_web'    => 0,
                'show_icon_phone'    => 0,
                'show_social_phone'  => 0,
                'archived'           => 0,
                'is_archived'        => 0,
            ),
        );
        foreach ( array_slice( array_keys( Social::get('enabled' ) ), 0, 4 ) as $key )
        {
            $defaults['meta'][$key] = '#';
        }

        /*!
         * FILTER HOOK
         * Allows customizing dummy defaults.
         *
         * @param array $defaults ['core'=>[], 'meta'=>[]]
         * @since 5.2.0
         */
        return apply_filters( 'molongui_authorship/author/dummy_defaults', $defaults );
    }
    private function get_dummy_avatar( $attr, $options, $resolved_size, $context, $source, $avatar_fallback, $width, $height )
    {
        switch ( $source )
        {
            case 'gravatar':
                $dummy_avatar = $this->get_gravatar( $attr, $options );
                break;

            case 'acronym':
                $dummy_avatar = $this->get_acronym( $attr, $options );
                break;

            case 'local':
            default:
                $dummy_avatar = $this->get_prefetched_meta( 'avatar' );
                break;
        }

        return $dummy_avatar;
    }
    public static function create_dummy( array $core = array(), array $meta = array() )
    {
        $author = new self( 0, 'dummy' );
        $defaults = self::dummy_defaults();
        if ( !empty( $defaults['core'] ) )
        {
            $author->prime_core( $defaults['core'] );
        }
        if ( !empty( $defaults['meta'] ) )
        {
            $author->prime_meta( $defaults['meta'] );
        }

        if ( !empty( $core ) )
        {
            $author->prime_core( $core );
        }
        if ( !empty( $meta ) )
        {
            $author->prime_meta( $meta );
        }

        return $author;
    }
    public static function get_dummy_data()
    {
        $dummy_data = array
        (
            'id'                 => 0,
            'type'               => 'dummy',
            'name'               => 'John Doe',
            'display_name'       => 'John Doe',
            'first_name'         => 'John',
            'last_name'          => 'Doe',
            'slug'               => 'john-doe',
            'nicename'           => 'john-doe',
            'mail'               => 'john@example.com',
            'email'              => 'john@example.com',
            'phone'              => '555-807-8464',
            'web'                => 'https://www.example.com',
            'website'            => 'https://www.example.com',
            'custom_link'        => 'https://www.example.com',
            'archive_url'        => '#',
            'avatar'             => '<img src="'.MOLONGUI_AUTHORSHIP_URL.'/assets/img/dummy-author-avatar.jpg">',
            'job'                => 'Journalist',
            'position'           => 'Journalist',
            'company'            => 'The Daily Planet',
            'company_link'       => 'https://www.thedailyplanet.com',
            'bio'                => 'John Doe is a seasoned journalist with over 25 years at The Daily Mail. Specializing in investigative reporting and current affairs, John is known for uncovering the truth and delivering compelling stories. His work spans politics, human interest, and breaking news.',
            'description'        => 'John Doe is a seasoned journalist with over 25 years at The Daily Mail. Specializing in investigative reporting and current affairs, John is known for uncovering the truth and delivering compelling stories. His work spans politics, human interest, and breaking news.',
            'short_bio'          => 'John Doe, with 25+ years at The Daily Mail, excels in investigative reporting on politics, human interest, and breaking news.',
            'short_description'  => 'John Doe, with 25+ years at The Daily Mail, excels in investigative reporting on politics, human interest, and breaking news.',
            'post_count'         => 0,
            'post_counts'        => array(),
            'user_roles'         => array(),
            'user_login'         => '',
            'box_display'        => 1,
            'author_box_display' => 1,
            'show_meta_mail'     => 1,
            'show_meta_email'    => 1,
            'show_meta_phone'    => 0,
            'show_icon_mail'     => 0,
            'show_social_email'  => 0,
            'show_icon_web'      => 0,
            'show_social_web'    => 0,
            'show_icon_phone'    => 0,
            'show_social_phone'  => 0,
            'archived'           => 0,
            'is_archived'        => 0,
        );
        foreach ( array_slice( array_keys( Social::get('enabled' ) ), 0, 4 ) as $key )
        {
            $dummy_data[$key] = '#';
        }

        return $dummy_data;
    }
    protected function normalize_core_field_name( $field )
    {
        $field = is_string( $field ) ? trim( $field ) : '';
        if ( $field === '' )
        {
            return '';
        }
        $user_allowed  = apply_filters( 'molongui_authorship/author/allowed_user_core',  self::$allowed_user_core_keys,  $this );
        $guest_allowed = apply_filters( 'molongui_authorship/author/allowed_guest_core', self::$allowed_guest_core_keys, $this );
        $alias_map = apply_filters( 'molongui_authorship/author/core_alias_map', array
        (
            'user'  => array
            (
                'id'         => 'ID',
                'name'       => 'display_name',
                'display'    => 'display_name',
                'login'      => 'user_login',
                'nicename'   => 'user_nicename',
                'slug'       => 'user_nicename',
                'email'      => 'user_email',
                'url'        => 'user_url',
                'website'    => 'user_url',
                'registered' => 'user_registered',
            ),
            'guest' => array
            (
                'id'          => 'ID',
                'title'       => 'post_title',
                'name'        => 'post_name',
                'slug'        => 'post_name',
                'nicename'    => 'post_name',
                'content'     => 'post_content',
                'bio'         => 'post_content',
                'description' => 'post_content',
                'date'        => 'post_date',
                'modified'    => 'post_modified',
            ),
        ), $this );

        $type = $this->get_type();
        $raw  = strtolower( $field );
        if ( isset( $alias_map[$type][$raw] ) )
        {
            $field = $alias_map[$type][$raw];
        }
        if ( $type === 'user' )
        {
            return in_array( $field, $user_allowed, true ) ? $field : '';
        }

        return in_array( $field, $guest_allowed, true ) ? $field : '';
    }
    private function get_user_virtual_meta_map()
    {
        static $map = null;

        if ( $map !== null )
        {
            return $map;
        }

        $map = array
        (
            'email'         => 'user_email',
            'mail'          => 'user_email',
            'user_email'    => 'user_email',

            'website'       => 'user_url',
            'web'           => 'user_url',
            'user_url'      => 'user_url',

            'display_name'  => 'display_name',
            'name'          => 'display_name',

            'slug'          => 'user_nicename',
            'nicename'      => 'user_nicename',
            'user_nicename' => 'user_nicename',

            'user_login'    => 'user_login',
            'login'         => 'user_login',
        );

        /*!
         * FILTER HOOK
         * Allows filtering the virtual meta key map for USER authors.
         *
         * The returned array must map requested meta keys to wp_users column names.
         *
         * @param array  $map    Virtual meta map.
         * @param Author $author Current author instance.
         * @since 5.2.5
         */
        $map = apply_filters( 'molongui_authorship/author/user_virtual_meta_map', $map, $this );
        if ( ! is_array( $map ) )
        {
            $map = array();
        }

        return $map;
    }
    private static function get_enabled_social_profile_keys()
    {
        if ( null !== self::$enabled_social_keys )
        {
            return self::$enabled_social_keys;
        }
        $enabled = Social::get( 'enabled' );
        $keys    = is_array( $enabled ) ? array_keys( $enabled ) : array();
        $keys = apply_filters( 'molongui_authorship/author/enabled_social_profile_keys', $keys );
        self::$enabled_social_keys = $keys;

        return self::$enabled_social_keys;
    }
    public static function flush_enabled_social_profile_keys_cache()
    {
        self::$enabled_social_keys = null;
    }
    protected function build_social_url( $network, $raw )
    {
        $raw = trim( $raw );
        if ( preg_match( '#^https?://#i', $raw ) )
        {
            return $raw;
        }
        switch ( $network )
        {
            case 'twitter':
                return 'https://twitter.com/' . ltrim( $raw, '@' );

            case 'facebook':
                return 'https://facebook.com/' . $raw;

            case 'instagram':
                return 'https://instagram.com/' . $raw;

            case 'linkedin':
                return 'https://www.linkedin.com/in/' . $raw;

            case 'youtube':
                return 'https://www.youtube.com/' . $raw;

            case 'tiktok':
                return 'https://www.tiktok.com/@' . ltrim( $raw, '@' );

            default:
                return $raw;
        }
    }
    private function load()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_wp_object()' );
        $this->author = $this->get_wp_object();
    }
    public function get()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_wp_object()' );
        return $this->get_wp_object();
    }
    public function get_mail()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_email()' );
        return $this->get_email();
    }
    public function get_web()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_website()' );
        return $this->get_website();
    }
    public function get_name()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_display_name()' );
        return $this->get_display_name();
    }
    public function get_url()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_archive_url()' );
        return $this->get_archive_url();
    }
    public function get_bio()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_description()' );
        return $this->get_description();
    }
    public function get_img()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'get_avatar()' );
        return $this->get_avatar();
    }
    public function get_all_meta()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0' );
        if ( isset( $this->metas ) and !empty( $this->metas ) )
        {
            return $this->metas;
        }
        switch ( $this->type )
        {
            case 'user':
                $this->metas = get_user_meta( $this->id );
                break;

            case 'guest':
                $this->metas = get_post_meta( $this->id );
                break;
        }

        return $this->metas;
    }
    public function has_bio()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'has_description()' );
        return $this->has_description();
    }
    public function has_avatar()
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'has_local_avatar()' );
        return $this->has_local_avatar();
    }
    public function get_post_count_via_meta( $post_type = 'post' )
    {
        return $this->read_stored_post_count( $post_type );
    }
    public function get_post_count_via_query( $post_type = 'post' )
    {
        return $this->compute_live_post_count( $post_type );
    }
    public static function get_by( $field, $value, $type = 'user', $meta = true )
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'Authors::get_author_by()' );

        if ( class_exists( 'Authors' ) and method_exists( 'Authors', 'get_author_by' ) )
        {
            $author = Authors::get_author_by( $field, $value, $type, $meta );

            if ( ! $author instanceof self )
            {
                return false;
            }

            $wp_object = $author->get_wp_object();

            if ( $type === 'guest' && $wp_object instanceof \WP_Post )
            {
                return $wp_object;
            }

            if ( $type !== 'guest' && $wp_object instanceof \WP_User )
            {
                return $wp_object;
            }
        }

        return false;
    }
    public static function get_type_by_nicename( $nicename )
    {
        WP::deprecated_function_once( __FUNCTION__, '5.2.0', 'Authors::get_author_type_by_nicename()' );

        if ( class_exists( 'Authors' ) and method_exists( 'Authors', 'get_author_type_by_nicename' ) )
        {
            return Authors::get_author_type_by_nicename( $nicename );
        }

        return 'not_found';
    }

} // class
