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
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Debug;
use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Plugin;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Author
{
    private $author = null;
    private $id = null;
    private $type = null;
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
    public function __construct( $id = 0, $type = 'user' )
    {
        $id   = apply_filters( 'authorship/author/id', $id, $type );
        $type = apply_filters( 'authorship/author/type', $type, $id );

        if ( $id instanceof \WP_User )
        {
            $this->id     = $id->ID;
            $this->type   = 'user';
            $this->author = $id;
            return;
        }
        elseif ( $id instanceof \WP_Post )
        {
            $this->id     = $id->ID;
            $this->type   = 'guest';
            $this->author = $id;
            return;
        }
        if ( !empty( $id ) and is_numeric( $id ) )
        {
            $this->id = $id;
        }
        else
        {
            $this->id = 0;
        }

        if ( !empty( $type ) and is_string( $type ) and in_array( $type, array( 'user', 'guest', 'dummy' ) ) )
        {
            $this->type = $type;
        }
        else
        {
            $this->type = 'user';
        }

        if ( 'dummy' === $this->type )
        {
            $this->data     = self::get_dummy_data();
            $this->has_data = true;
            foreach ( $this->data as $property_id => $property_value )
            {
                switch ( $property_id )
                {
                    case 'name':
                        $this->display_name = $property_value;
                        break;

                    case 'mail':
                        $this->email = $property_value;
                        break;

                    case 'web':
                        $this->website = $property_value;
                        break;

                    case 'bio':
                        $this->description = $property_value;
                        break;

                    default:
                        $this->metas[$property_id] = $property_value;
                }
            }
        }
        elseif ( 0 === $this->id )
        {
            $post_authors = Authors::find();

            if ( !empty( $post_authors ) )
            {
                $this->id   = $post_authors[0]->id;
                $this->type = $post_authors[0]->type;
            }
        }
    }
    private function load()
    {
        switch ( $this->type )
        {
            case 'user':
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $this->author = get_user_by( 'id', $this->id );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;

            case 'guest':
                $this->author = get_post( $this->id );
                break;
        }
        if ( ( !$this->author or !is_object( $this->author ) ) )
        {
            $this->author = new \WP_User();
            Debug::console_log( sprintf( "No %s exists with the given ID (%s).", ( $this->type == 'guest' ? __( 'guest author', 'molongui-authorship' ) : __( 'user', 'molongui-authorship' ) ), $this->id ), __FUNCTION__ );
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.2.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/get' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->author = apply_filters_deprecated( 'authorship/author/get', array( $this->author, $this->id, $this->type ), '5.0.0' );
        }
    }
    public function get()
    {
        if ( !isset( $this->author ) )
        {
            $this->load();
        }
        return $this->author;
    }
    public function get_id()
    {
        return $this->id;
    }
    public function get_type()
    {
        return $this->type;
    }
    public function get_name()
    {
        return $this->get_display_name();
    }
    public function get_display_name()
    {
        if ( isset( $this->display_name ) )
        {
            return apply_filters( 'molongui_authorship/get_author_display_name', $this->display_name, $this->id, $this->type );
        }
        $format = apply_filters( 'authorship/author/name/format', 'display_name' );
        switch ( $format )
        {
            case 'first_name_first':
                $this->display_name = $this->get_first_name() . '&nbsp;'  . $this->get_last_name();
                break;

            case 'last_name_first':
                $this->display_name = $this->get_last_name() . ',&nbsp;' . $this->get_first_name();
                break;

            case 'display_name':
            default:
                switch ( $this->type )
                {
                    case 'user':
                        $this->display_name = $this->get()->display_name;
                        break;

                    case 'guest':
                        $this->display_name = $this->get()->post_title;
                        break;
                }
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.2.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/name' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->display_name = apply_filters_deprecated( 'authorship/author/name', array( $this->display_name, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_data' );
        }
        return apply_filters( 'molongui_authorship/get_author_display_name', $this->display_name, $this->id, $this->type, $this );
    }
    public function get_first_name()
    {
        if ( isset( $this->first_name ) )
        {
            return apply_filters( 'molongui_authorship/get_author_first_name', $this->first_name, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                $this->first_name = $this->get()->first_name;
                break;

            case 'guest':
                $this->first_name = $this->get_meta( 'first_name' );
                break;
        }
        return apply_filters( 'molongui_authorship/get_author_first_name', $this->first_name, $this->id, $this->type, $this );
    }
    public function get_last_name()
    {
        if ( isset( $this->last_name ) )
        {
            return apply_filters( 'molongui_authorship/get_author_first_name', $this->last_name, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                $this->last_name = $this->get()->last_name;
                break;

            case 'guest':
                $this->last_name = $this->get_meta( 'last_name' );
                break;
        }
        return apply_filters( 'molongui_authorship/get_author_last_name', $this->last_name, $this->id, $this->type, $this );
    }
    public function get_mail()
    {
        return $this->get_email();
    }
    public function get_email()
    {
        if ( isset( $this->email ) )
        {
            return apply_filters( 'molongui_authorship/get_author_email', $this->email, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                $this->email = $this->get()->user_email;
                break;

            case 'guest':
                $this->email = get_post_meta( $this->id, '_molongui_guest_author_mail', true );
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.1.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/mail' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->email = apply_filters_deprecated( 'authorship/author/mail', array( $this->email, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_email' );
        }
        return apply_filters( 'molongui_authorship/get_author_email', $this->email, $this->id, $this->type, $this );
    }
    public function get_slug()
    {
        if ( isset( $this->slug ) )
        {
            return apply_filters( 'molongui_authorship/get_author_slug', $this->slug, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                $this->slug = $this->get()->user_nicename;
                break;

            case 'guest':
                $this->slug = $this->get()->post_name;
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.1.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/slug' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->slug = apply_filters_deprecated( 'authorship/author/slug', array( $this->slug, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_slug' );
        }
        return apply_filters( 'molongui_authorship/get_author_slug', $this->slug, $this->id, $this->type, $this );
    }
    public function get_url()
    {
        return $this->get_archive_url();
    }
    public function get_archive_url()
    {
        if ( isset( $this->archive_url ) )
        {
            return apply_filters( 'molongui_authorship/get_author_archive_url', $this->archive_url, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                add_filter( 'authorship/pre_author_link', array( Helpers::class, 'short_circuit' ), 10, 4 );
                add_filter( 'molongui_authorship_dont_filter_name', '__return_true' );
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $this->archive_url = get_author_posts_url( $this->id, $this->get()->user_nicename );
                remove_filter( 'authorship/pre_author_link', array( Helpers::class, 'short_circuit' ), 10 );
                remove_filter( 'molongui_authorship_dont_filter_name', '__return_true' );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;

            case 'guest':
                $this->archive_url = '#molongui-disabled-link';
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.1.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/url' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->archive_url = apply_filters_deprecated( 'authorship/author/url', array( $this->archive_url, $this->id, $this->type, $this->author, array() ), '5.0.0', 'molongui_authorship/get_author_archive_url' );
        }
        return apply_filters( 'molongui_authorship/get_author_archive_url', $this->archive_url, $this->id, $this->type, $this );
    }
    public function get_link()
    {
        if ( isset( $this->link ) )
        {
            return apply_filters( 'molongui_authorship/get_author_link', $this->link, $this->id, $this->type, $this );
        }

        $name = $this->get_display_name();
        $url  = $this->get_archive_url();

        if ( !empty( $name ) and !empty( $url ) )
        {
            $this->link = '<a href="'.esc_url( $url ).'">'.esc_html( $name).'</a>';
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.2.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/link' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->link = apply_filters_deprecated( 'authorship/author/link', array( $this->link, $name, $url, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_link' );
        }
        return apply_filters( 'molongui_authorship/get_author_link', $this->link, $this->id, $this->type, $this );
    }
    public function get_bio()
    {
        return $this->get_description();
    }
    public function get_description()
    {
        if ( isset( $this->description ) )
        {
            return apply_filters( 'molongui_authorship/get_author_description', $this->description, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $this->description = get_the_author_meta( 'description', $this->id );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;

            case 'guest':
                $this->description = $this->get()->post_content;
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.1.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/bio' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->description = apply_filters_deprecated( 'authorship/author/bio', array( $this->description, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_description' );
        }
        return apply_filters( 'molongui_authorship/get_author_description', $this->description, $this->id, $this->type, $this );
    }
    public function get_web()
    {
        return $this->get_website();
    }
    public function get_website()
    {
        if ( isset( $this->website ) )
        {
            return apply_filters( 'molongui_authorship/get_author_website', $this->website, $this->id, $this->type, $this );
        }
        switch ( $this->type )
        {
            case 'user':
                $this->website = $this->get()->user_url;
                break;

            case 'guest':
                $this->website = $this->get_meta( 'web' );
                break;
        }
        return apply_filters( 'molongui_authorship/get_author_website', $this->website, $this->id, $this->type, $this );
    }
    public function get_meta( $key )
    {
        if ( empty( $key ) )
        {
            Debug::console_log( "Which meta value do you want to retrieve? You need to provide a 'key' attribute.", __FUNCTION__ );
            return '';
        }
        if ( 'all' === $key )
        {
            return $this->get_all_meta();
        }
        if ( isset( $this->metas ) and !empty( $this->metas ) )
        {
            if ( isset( $this->metas[$key] ) )
            {
                return apply_filters( "molongui_authorship/get_author_meta_{$key}", $this->metas[$key], $this->id, $this->type, $this );
            }
        }

        $this->metas[$key] = '';
        switch ( $this->type )
        {
            case 'user':
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $this->metas[$key] = get_the_author_meta( 'molongui_author_'.$key, $this->id );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;

            case 'guest':
                $this->metas[$key] = get_post_meta( $this->id, '_molongui_guest_author_'.$key, true );
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      3.1.8
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/meta' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->metas[$key] = apply_filters_deprecated( 'authorship/author/meta', array( $this->metas[$key], $this->id, $this->type, $this->author, $key ), '5.0.0', 'molongui_authorship/get_author_meta_{$key}' );
        }
        return apply_filters( "molongui_authorship/get_author_meta_{$key}", $this->metas[$key], $this->id, $this->type, $this );
    }
    public function get_all_meta()
    {
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
	public function get_user_roles()
    {
        if ( isset( $this->user_roles ) )
        {
            return apply_filters( 'molongui_authorship/get_author_user_roles', $this->user_roles, $this->id, $this->type );
        }

        switch ( $this->type )
        {
            case 'user':
                $this->user_roles  = $this->get()->roles;
                break;

            case 'guest':
                $this->user_roles = array( _x( "Guest Author", 'User role', 'molongui-authorship' ) );
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.5.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/user_roles' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->user_roles = apply_filters_deprecated( 'authorship/author/user_roles', array( $this->user_roles, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_user_roles' );
        }
        return apply_filters( 'molongui_authorship/get_author_user_roles', $this->user_roles, $this->id, $this->type, $this );
    }
	public function get_user_login()
    {
        if ( isset( $this->user_login ) )
        {
            return apply_filters( 'molongui_authorship/get_author_user_login', $this->user_login, $this->id, $this->type, $this );
        }

        switch ( $this->type )
        {
            case 'user':
                $this->user_login = $this->get()->user_login;
                break;

            case 'guest':
                $this->user_login = '';
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.5.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/user_login' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->user_login = apply_filters_deprecated( 'authorship/author/user_login', array( $this->user_login, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_user_login' );
        }
        return apply_filters( 'molongui_authorship/get_author_user_login', $this->user_login, $this->id, $this->type, $this );
    }
    public function get_img()
    {
        return $this->get_avatar();
    }
	public function get_avatar( $size = 'full', $context = 'screen', $source = null, $default = null )
	{
		$attr    = array();
        $options = apply_filters( '_authorship/get_options', Settings::get() );
        $size    = apply_filters( 'authorship/get_avatar/size', $size, $options );
        $context = apply_filters( 'authorship/get_avatar/context', $context, $options );
        $source  = apply_filters( 'authorship/get_avatar/source', $source, $options );
        $default = apply_filters( 'authorship/get_avatar/default', $default, $options );
        if ( is_array( $size ) )
        {
            $width  = $size[0];
            $height = $size[1];
        }
        else
        {
            $sizes  = wp_get_registered_image_subsizes();
            $size   = in_array( $size, array_keys( $sizes ) ) ? $size : 'authorship-box-avatar';
            $width  = $sizes[$size]['width'];
            $height = $sizes[$size]['height'];
            $size   = array( $width, $height );
        }
		if ( $context == 'box' )
		{
            if ( apply_filters( 'molongui_authorship/load_author_box_styles', true ) )
            {
                $width  = $options['author_box_avatar_width'];
                $height = $options['author_box_avatar_height'];
                $size   = array( $width, $height );
            }
            if ( !empty( Settings::get( 'seo_settings_enabled' ) ) and !empty( Settings::get( 'schema_markup_enabled' ) ) )
            {
                $attr = array_merge( $attr, array( 'itemprop' => 'image' ) );
            }
		}
        switch ( !empty( $source ) ? $source : ( !empty( $options['author_box_avatar_source'] ) ? $options['author_box_avatar_source'] : '' ) )
        {
            case 'gravatar':
                if ( $context != 'url' )
                {
                    $this->avatar = $this->get_gravatar( array_merge( $attr, array( 'width' => $width, 'height' => $height ) ), $options );
                }
                else
                {
                    $this->avatar = get_avatar_url( $this->get_email() );
                }
                break;

            case 'acronym':
                if ( $context != 'url' )
                {
                    $this->avatar = $this->get_acronym( array_merge( $attr, array( 'width' => $width, 'height' => $height ) ), $options );
                }
                else
                {
                    $this->avatar = '';
                }
                break;

            case 'local':
            default:
                if ( Settings::is_enabled( 'local-avatar' ) )
                {
                    switch ( $this->type )
                    {
                        case 'user':
                            if ( $img_id = get_user_meta( $this->id, 'molongui_author_image_id', true ) )
                            {
                                if ( $context == 'url' )
                                {
                                    $this->avatar = wp_get_attachment_url( $img_id );
                                }
                                else
                                {
                                    $this->avatar = wp_get_attachment_image( $img_id, $size, false, $attr );
                                }
                            }
                            break;

                        case 'guest':
                            if ( has_post_thumbnail( $this->id ) )
                            {
                                if ( $context == 'url' )
                                {
                                    $this->avatar = get_the_post_thumbnail_url( $this->id, $size );
                                }
                                else
                                {
                                    $this->avatar = get_the_post_thumbnail( $this->id, $size, $attr );
                                }
                            }
                            break;
                    }
                }
                if ( empty( $this->avatar ) )
                {
                    $avatar_fallback = $default;
                    if ( empty( $avatar_fallback ) and !empty( $options['author_box_avatar_fallback'] ) )
                    {
                        $avatar_fallback = $options['author_box_avatar_fallback'];
                    }

                    switch ( $avatar_fallback )
                    {
                        case 'gravatar':
                            if ( $context === 'url' )
                            {
                                $this->avatar = get_avatar_url( $this->get_email() );
                            }
                            else
                            {
                                $this->avatar = $this->get_gravatar( array_merge( $attr, array( 'width' => $width, 'height' => $height ) ), $options );
                            }
                            break;

                        case 'acronym':
                            if ( $context !== 'url' )
                            {
                                $this->avatar = $this->get_acronym( array_merge( $attr, array( 'width' => $width, 'height' => $height ) ), $options );
                            }
                            break;

                        case 'none':
                        default:
                            $this->avatar = '';
                            break;
                    }
                }
                break;
        }

        if ( !isset( $this->avatar ) )
        {
            $this->avatar = '';
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.3.3
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/get_avatar' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->avatar = apply_filters_deprecated( 'authorship/author/get_avatar', array( $this->avatar, $this->id, $this->type, $this->author, $size, $context ), '5.0.0', 'molongui_authorship/get_author_avatar' );
        }
        return apply_filters( 'molongui_authorship/get_author_avatar', $this->avatar, $this->id, $this->type, $size, $context, $this );
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
            return apply_filters( 'molongui_authorship/get_author_data', $this->data, $this->id, $this->type, $this );
        }
        $fields = apply_filters( 'molongui_authorship/get_author_data_fields', array
        (
            'id',
            'type',
            'name',             // todo: Rename to 'display_name'. Change meta key too.
            'first_name',
            'last_name',
            'slug',
            'mail',             // todo: Rename to 'email'. Change meta key too.
            'phone',
            'web',              // todo: Rename to 'website'. Change meta key too.
            'custom_link',
            'archive_url',
            'avatar',
            'job',              // todo: Rename to 'position'. Change meta key too.
            'company',
            'company_link',
            'bio',              // todo: Rename to 'description'. Change meta key too.
            'post_count',
            'user_roles',
            'user_login',
            'box_display',      // todo: Rename to 'author_box_display'. Change meta key too.
            'show_meta_mail',
            'show_meta_phone',
            'show_icon_mail',   // todo: Rename to 'show_social_mail'. Change meta key too.
            'show_icon_web',    // todo: Rename to 'show_social_web'. Change meta key too.
            'show_icon_phone',  // todo: Rename to 'show_social_phone'. Change meta key too.
            'archived',         // todo: Rename to 'is_archived'. Change meta key too.
            'social_profiles',
        ));

        if ( ( $key = array_search( 'social_profiles', $fields ) ) !== false )
        {
            $fields = array_merge( $fields, array_keys( Social::get( 'enabled' ) ) );
            unset( $fields[$key] );
        }

        /*!
         * DEPRECATED
         * This action hook is scheduled for removal in version 5.2.0.
         *
         * @param      int   $this->id  Author ID.
         * @since      2.0.0
         * @since      4.2.0 Renamed hook from 'molongui_authorship_before_get_guest_author_data'
         * @deprecated 5.0.0
         */
        if ( 'guest' === $this->type )
        {
            if ( has_action( 'authorship/author/guest/before_get_data' ) )
            {
                do_action_deprecated( 'authorship/author/guest/before_get_data', array( $this->id ), '5.0.0' );
            }
        }
        if ( !empty( $fields ) )
        {
            foreach ( $fields as $field )
            {
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
         * DEPRECATED
         * This action hook is scheduled for removal in version 5.2.0.
         *
         * @param      int   $this->id Author ID.
         * @since      2.0.0
         * @since      4.2.0 Renamed hook from 'molongui_authorship_after_get_guest_author_data'
         * @deprecated 5.0.0
         */
        if ( 'guest' === $this->type )
        {
            if ( has_action( 'authorship/author/guest/after_get_data' ) )
            {
                do_action_deprecated( 'authorship/author/guest/after_get_data', array( $this->id ), '5.0.0' );
            }
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.2.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/data' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $this->data = apply_filters_deprecated( 'authorship/author/data', array( $this->data, $this->id, $this->type, $this->author ), '5.0.0', 'molongui_authorship/get_author_data' );
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
        $original_args = $args;
        $defaults = array
        (
            'cat'                 => '',
            'fields'              => 'all',
            'ignore_sticky_posts' => true,
            'meta_query'          => '',
            'no_found_rows'       => true,
            'offset'              => '',
            'order'               => 'DESC',
            'orderby'             => 'date',
            'post__in'            => '',
            'post__not_in'        => '',
            'post_type'           => 'post',
            'post_status'         => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ), // all WP default post_status
            'posts_per_page'      => '-1',
            'author_id'           => $this->id,
            'author_type'         => $this->type,
            'site_id'             => apply_filters( 'authorship/get_posts/blog_id', get_current_blog_id() ),
            'language'            => apply_filters( 'authorship/get_posts/language', Helpers::get_language() ),
        );
        $parsed_args = wp_parse_args( $original_args, $defaults );
        switch ( $parsed_args['post_type'] )
        {
            case 'all':
                $parsed_args['post_type'] = Post::get_post_types();
                break;

            case 'selected':
                $parsed_args['post_type'] = Settings::enabled_post_types();
                break;

            case 'related':
                $options                       = Settings::get();
                $parsed_args['post_type']      = explode( ",", $options['author_box_related_posts_post_types'] );
                break;
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.2.17
         * @deprecated 5.0.9
         */
        if ( has_filter( 'authorship/author/get_posts/args' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
        {
            $parsed_args = apply_filters_deprecated( 'authorship/author/get_posts/args', array( $parsed_args, $original_args, $this->id, $this->type, $this->author ), '5.0.9', 'molongui_authorship/get_posts_args' );
        }
        $parsed_args = apply_filters( 'molongui_authorship/get_posts_args', $parsed_args, $original_args, $this->id, $this->type, $this->author );
        $posts = apply_filters( 'authorship/author/pre_get_posts', null, $this->id, $this->type, $this->author, $parsed_args, $args );
        if ( null !== $posts )
        {
            return $posts;
        }
        $hash  = md5( serialize( $parsed_args ) );
        $key   = 'posts' . '_' . $hash;
        $posts = Cache::get( $key );
        if ( false === $posts )
        {
            if ( 'guest' === $this->type )
            {
                if ( !empty( $parsed_args['meta_query'] ) )
                {
                    $mq = array
                    (
                        'relation'  => 'AND',
                        array
                        (
                            'key'   => $parsed_args['meta_query']['key'],
                            'value' => $parsed_args['meta_query']['value'],
                        ),
                        array
                        (
                            'key'   => '_molongui_author',
                            'value' => 'guest-'.$this->id,
                        ),
                    );
                }
                else
                {
                    $mq = array
                    (
                        array
                        (
                            'key'   => '_molongui_author',
                            'value' => 'guest-'.$this->id,
                        ),
                    );
                }
                $args = array
                (
                    'post_type'           => $parsed_args['post_type'],
                    'post__in'            => $parsed_args['post__in'],
                    'post__not_in'        => $parsed_args['post__not_in'],
                    'post_status'         => $parsed_args['post_status'],
                    'cat'                 => $parsed_args['cat'],
                    'meta_query'          => $mq,
                    'orderby'             => $parsed_args['orderby'],
                    'order'               => $parsed_args['order'],
                    'posts_per_page'      => $parsed_args['posts_per_page'],
                    'no_found_rows'       => $parsed_args['no_found_rows'],
                    'ignore_sticky_posts' => $parsed_args['ignore_sticky_posts'],
                    'fields'              => $parsed_args['fields'],
                    'author_id'           => $parsed_args['author_id'],
                    'author_type'         => $parsed_args['author_type'],
                    'site_id'             => $parsed_args['site_id'],
                    'language'            => $parsed_args['language'],
                );
                $data = Cache::query( $args, 'posts' );
                $posts = empty( $data->posts ) ? array() : $data->posts;
            }
            else
            {
                if ( !empty( $parsed_args['meta_query'] ) )
                {
                    $mq = array
                    (
                        'relation'  => 'AND',
                        array
                        (
                            'key'    => $parsed_args['meta_query']['key'],
                            'value'  => $parsed_args['meta_query']['value'],
                        ),
                        array
                        (
                            'key'     => '_molongui_author',
                            'value'   => 'user-'.$this->id,
                            'compare' => '==',
                        ),
                    );
                }
                else
                {
                    $mq = array
                    (
                        array
                        (
                            'key'     => '_molongui_author',
                            'value'   => 'user-'.$this->id,
                            'compare' => '==',
                        ),
                    );
                }
                $args = array
                (
                    'post_type'           => $parsed_args['post_type'],
                    'post__in'            => $parsed_args['post__in'],
                    'post__not_in'        => $parsed_args['post__not_in'],
                    'post_status'         => $parsed_args['post_status'],
                    'cat'                 => $parsed_args['cat'],
                    'meta_query'          => $mq,
                    'orderby'             => $parsed_args['orderby'],
                    'order'               => $parsed_args['order'],
                    'posts_per_page'      => $parsed_args['posts_per_page'],
                    'no_found_rows'       => $parsed_args['no_found_rows'],
                    'ignore_sticky_posts' => $parsed_args['ignore_sticky_posts'],
                    'fields'              => $parsed_args['fields'],
                    'author_id'           => $parsed_args['author_id'],
                    'author_type'         => $parsed_args['author_type'],
                    'site_id'             => $parsed_args['site_id'],
                    'language'            => $parsed_args['language'],
                );
                $data = Cache::query( $args, 'posts' );
                $data = empty( $data->posts ) ? array() : $data->posts;
                $data = apply_filters( 'authorship/author/get_posts', $data, $this->id, $this->type, $this->author, $parsed_args );
                $post_ids = $parsed_args['fields'] == 'ids' ? array_unique( $data ) : array_unique( wp_list_pluck( $data, 'ID' ) );
                if ( empty( $post_ids ) )
                {
                    return array();
                }
                $args = array
                (
                    'post_type'           => $parsed_args['post_type'],
                    'post__in'            => $post_ids,
                    'post_status'         => $parsed_args['post_status'],
                    'cat'                 => $parsed_args['cat'],
                    'orderby'             => $parsed_args['orderby'],
                    'order'               => $parsed_args['order'],
                    'posts_per_page'      => $parsed_args['posts_per_page'],
                    'no_found_rows'       => $parsed_args['no_found_rows'],
                    'ignore_sticky_posts' => $parsed_args['ignore_sticky_posts'],
                    'fields'              => $parsed_args['fields'],
                    'author_id'           => $parsed_args['author_id'],
                    'author_type'         => $parsed_args['author_type'],
                    'site_id'             => $parsed_args['site_id'],
                    'language'            => $parsed_args['language'],
                );
                $posts = Cache::query( $args, 'posts' );
                $posts = empty( $posts->posts ) ? array() : $posts->posts;
            }
            Cache::set( $key, $posts );
            $db_key = MOLONGUI_AUTHORSHIP_PREFIX . '_cache_posts';
            $hashes = get_option( $db_key, array() );
            $update = update_option( $db_key, !in_array( $hash, $hashes ) ? array_merge( $hashes, array( $hash ) ) : $hashes, true );
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.2.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/posts' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $posts = apply_filters_deprecated( 'authorship/author/posts', array( $posts, $this->id, $this->type, $this->author, $parsed_args ), '5.0.0', 'molongui_authorship/get_author_posts' );
        }
        $posts = apply_filters( 'molongui_authorship/get_author_posts', $posts, $this->id, $this->type, $this, $parsed_args );

        return ( !empty( $posts ) ? $posts : array() );
    }
    public function get_post_count( $post_type = null, $use_meta = true )
    {
        $count = 0;

        if ( !isset( $post_type ) )
        {
            $post_type = Settings::enabled_post_types();
        }
        elseif ( !is_array( $post_type ) )
        {
            $post_type = array( $post_type );
        }

        if ( $use_meta )
        {
            $count = $this->get_post_count_via_meta( $post_type );
        }
        else
        {
            $count = $this->get_post_count_via_query( $post_type );
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.2.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/posts_count' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $count = apply_filters_deprecated( 'authorship/author/posts_count', array( $count, $this->id, $this->type, $post_type ), '5.0.0', 'molongui_authorship/count_author_posts' );
        }
        return apply_filters( 'molongui_authorship/get_author_post_count', $count, $post_type, $this->id, $this->type, $this );
    }
    public function get_post_counts( $post_types = null )
    {
        if ( !isset( $post_types ) )
        {
            $post_types = Settings::enabled_post_types();
        }
        elseif ( !is_array( $post_types ) )
        {
            $post_types = array( $post_types );
        }
        if ( isset( $this->post_count ) and is_array( $this->post_count ) )
        {
            $_post_types = array_keys( $this->post_count );

            if ( Helpers::array_match( $post_types, $_post_types ) )
            {
                return apply_filters( 'molongui_authorship/get_author_display_name', $this->post_count, $post_types, $this->id, $this->type, $this );
            }
        }

        $count = array();
        if ( !empty( $post_types ) )
        {
            foreach( $post_types as $post_type )
            {
                $count[$post_type] = $this->get_meta( $post_type.'_count' );
            }
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.0.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/author/post_count' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', false ) )
        {
            $count = apply_filters_deprecated( 'authorship/author/post_count', array( $count, $this->id, $this->type, $this->author, $post_types ), '5.0.0', 'molongui_authorship/get_author_posts_count' );
        }

        /*!
         * FILTER HOOK
         * Allows filtering the fetched data before it is returned.
         *
         * @param array        $count      Array of 'published' and 'private' posts by author, indexed by post type.
         * @param string|array $post_types Single post type or array of post types to count the number of posts for.
         * @param int          $id         The author's ID.
         * @param int          $type       The author's type, either 'user' or 'guest'.
         * @param Author       $this       The current author instance.
         * @since 5.0.0
         */
        $count = apply_filters( 'molongui_authorship/get_author_posts_count', $count, $post_types, $this->id, $this->type, $this );
        $this->post_count = $count;

        return $count;
    }
    public function get_post_count_via_meta( $post_type = 'post' )
    {
        $post_counts = $this->get_post_counts( $post_type );

        return array_sum( array_filter( $post_counts, 'is_numeric' ) );
    }
    public function get_post_count_via_query( $post_type = 'post' )
    {
        $post_statuses_to_count = Admin_Post::get_countable_post_statuses();

        $args = array
        (
            'fields'      => 'ids',
            'post_type'   => $post_type,
            'post_status' => $post_statuses_to_count,
        );

        return count( $this->get_posts( $args ) );
    }
    public function update_post_type_count( $value, $post_type = 'post' )
    {
        switch ( $this->type )
        {
            case 'user':
                $meta_key = 'molongui_author_'.$post_type.'_count';
                update_user_meta( $this->id, $meta_key, $value );
                break;

            case 'guest':
                $meta_key = '_molongui_guest_author_'.$post_type.'_count';
                update_post_meta( $this->id, $meta_key, $value );
                break;
        }
    }
    public static function get_by( $field, $value, $type = 'user', $meta = true )
    {
        if ( $type == 'user' )
        {
            $user_query = new \WP_User_Query
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
                    'language'   => Helpers::get_language(),
                );
            }
            else
            {
                $args = array
                (
                    $field      => $value,
                    'post_type' => MOLONGUI_AUTHORSHIP_CPT,
                    'site_id'   => get_current_blog_id(),
                    'language'  => Helpers::get_language(),
                );
            }
            $guest = Cache::query( $args, 'guests' );
            if ( $guest->have_posts() )
            {
                return ( empty( $guest->posts['0'] ) ? false : $guest->posts['0'] );
            }
        }
        return false;
    }
    public static function get_type_by_nicename( $nicename )
    {
        if ( $guest = self::get_by( 'name', $nicename, 'guest', false ) )
        {
            return 'guest';
        }
        elseif ( $author = self::get_by( 'user_nicename', $nicename ) )
        {
            return 'user';
        }
        return 'not_found';
    }
    public static function get_dummy_data()
    {
        $dummy_data = array
        (
            'id'              => 0,
            'type'            => 'dummy',
            'name'            => 'John Doe',
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'slug'            => 'john-doe',
            'mail'            => 'john@example.com',
            'phone'           => '555-807-8464',
            'web'             => 'https://www.example.com',
            'custom_link'     => 'https://www.example.com',
            'archive_url'     => '#',
            'avatar'          => '<img src="'.MOLONGUI_AUTHORSHIP_URL.'/assets/img/dummy-author-avatar.jpg">',
            'job'             => 'Journalist',
            'company'         => 'The Daily Planet',
            'company_link'    => 'https://www.thedailyplanet.com',
            'bio'             => 'John Doe is a seasoned journalist with over 25 years at The Daily Mail. Specializing in investigative reporting and current affairs, John is known for uncovering the truth and delivering compelling stories. His work spans politics, human interest, and breaking news.',
            'short_bio'       => 'John Doe, with 25+ years at The Daily Mail, excels in investigative reporting on politics, human interest, and breaking news.',
            'post_count'      => 0,
            'post_counts'     => array(),
            'user_roles'      => array(),
            'user_login'      => '',
            'box_display'     => 1,
            'show_meta_mail'  => 1,
            'show_meta_phone' => 0,
            'show_icon_mail'  => 0,
            'show_icon_web'   => 0,
            'show_icon_phone' => 0,
            'archived'        => 0,
        );
        foreach ( array_slice( array_keys( Social::get('enabled' ) ), 0, 4 ) as $key )
        {
            $dummy_data[$key] = '#';
        }

        return $dummy_data;
    }
    public function has_bio()
    {
        return !empty( $this->get_description() );
    }
    public function has_description()
    {
        return !empty( $this->get_description() );
    }
    public function has_avatar()
    {
        $has_avatar = false;

        switch( $this->type )
        {
            case 'user':
                $img = get_user_meta( $this->id, 'molongui_author_image_url', true );
                $has_avatar = !empty( $img );
                break;

            case 'guest':
                $has_avatar = has_post_thumbnail( $this->id );
                break;
        }

        return $has_avatar;
    }
    public function has_posts( $post_types = array() )
    {
        $has_posts = false;

        if ( empty( $post_types ) )
        {
            $post_types = Settings::enabled_post_types();
        }
        elseif ( is_string( $post_types ) )
        {
            $post_types = array( $post_types );
        }
        $post_count = $this->get_post_counts( $post_types );
        foreach ( $post_types as $post_type )
        {
            if ( !empty( $post_count[$post_type] ) )
            {
                $has_posts = true;
                break;
            }
        }

        return $has_posts;
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
        if ( 'user' === $this->type )
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

} // class
