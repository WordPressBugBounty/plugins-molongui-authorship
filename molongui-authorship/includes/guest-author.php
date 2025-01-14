<?php
/*!
 * Registers the custom post type used to store guest author information and provides utility functions related to guest
 * authors.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      4.9.5
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Guest_Author
{
    const POST_TYPE = 'guest_author';
    public function __construct()
    {
        if ( Settings::is_enabled( 'guest-author' ) )
        {
            add_action( 'init', array( $this, 'register_post_type' ) );
            add_filter( 'post_type_link', array( $this, 'post_link' ), 10, 4 );
        }
    }
    public static function get_post_type()
    {
        return self::POST_TYPE;
    }
    public function register_post_type()
    {
        $options = Settings::get();
        $labels = array
        (
            'name'					=> _x( "Guest Authors", 'post type general name', 'molongui-authorship' ),
            'singular_name'			=> _x( "Guest Author", 'post type singular name', 'molongui-authorship' ),
            'menu_name'				=> __( "Guest Authors", 'molongui-authorship' ),
            'name_admin_bar'		=> __( "Guest Author", 'molongui-authorship' ),
            'all_items'				=> ( ( !empty( $options['dashboard_guest_authors_menu_location'] ) and $options['dashboard_guest_authors_menu_location'] != 'top' ) ? __( "Guest Authors", 'molongui-authorship' ) : __( "All Guest Authors", 'molongui-authorship' ) ),
            'add_new'				=> _x( "Add New", 'Guest author custom post type', 'molongui-authorship' ),
            'add_new_item'			=> __( "Add New Guest Author", 'molongui-authorship' ),
            'edit_item'				=> __( "Edit Guest Author", 'molongui-authorship' ),
            'new_item'				=> __( "New Guest Author", 'molongui-authorship' ),
            'view_item'				=> __( "View Guest Author", 'molongui-authorship' ),
            'search_items'			=> __( "Search Guest Authors", 'molongui-authorship' ),
            'not_found'				=> __( "No Guest Authors Found", 'molongui-authorship' ),
            'not_found_in_trash'	=> __( "No Guest Authors Found in the Trash", 'molongui-authorship' ),
            'parent_item_colon'		=> '',
            'featured_image'        => _x( "Profile Image", 'Guest author custom post type', 'molongui-authorship' ),
            'set_featured_image'    => _x( "Set Profile Image", 'Guest author custom post type', 'molongui-authorship' ),
            'remove_featured_image' => _x( "Remove Profile Image", 'Guest author custom post type', 'molongui-authorship' ),
            'use_featured_image'    => _x( "Use as Profile Image", 'Guest author custom post type', 'molongui-authorship' ),
        );
        $show_in_menu = false;
        if ( $options['dashboard_guest_authors_menu'] )
        {
            $show_in_menu = ( ( !empty( $options['dashboard_guest_authors_menu_location'] ) and $options['dashboard_guest_authors_menu_location'] !== 'top' ) ? $options['dashboard_guest_authors_menu_location'] : true );
        }
        $args = array
        (
            'labels'				=> $labels,
            'description'			=> __( "Guest author custom post type by Molongui", 'molongui-authorship' ),
            'public'				=> false,
            'exclude_from_search'	=> true,
            'publicly_queryable'	=> false,
            'show_ui'				=> true,
            'show_in_menu'          => $show_in_menu,
            'show_in_nav_menus'		=> false,
            'show_in_admin_bar '	=> true,
            'menu_position'			=> 5,
            'menu_icon'				=> 'dashicons-id',
            'supports'		 		=> Settings::is_enabled( 'local-avatar' ) ? array( 'thumbnail' ) : array( '' ),
            'register_meta_box_cb'	=> '',
            'has_archive'			=> false,
            'rewrite'				=> false,//array( 'slug' => 'guest-author' ),
            'can_export'            => false,
            'query_var'             => false,
            'capability_type'       => 'post',  // https://developer.wordpress.org/reference/functions/register_post_type/#capability_type
            'map_meta_cap'          => true,    // https://developer.wordpress.org/reference/functions/register_post_type/#map_meta_cap
        );
        register_post_type( self::POST_TYPE, $args );
    }
    public function post_link( $post_link, $post, $leavename, $sample )
    {
        if ( self::POST_TYPE === $post->post_type )
        {
            $guest = new Author( $post->ID, 'guest', $post );
            $post_link = $guest->get_archive_url();
        }

        return $post_link;
    }
    public static function get_guest_by( $field, $value )
    {
        $the_query = WP::the_query();
        if ( !isset( $the_query->guest_author_id ) )
        {
            return null;
        }

        $author = new Author( $the_query->guest_author_id, 'guest' );
        $post_id = apply_filters( '_authorship/get_user_by/post_id', Post::get_id(), null, $field, $value );
        $aim = 'info';
        if ( in_the_loop() )
        {
            if ( !empty( $post_id ) )
            {
                $aim = 'byline';
            }
        }
        $aim = apply_filters( '_authorship/get_user_by/aim', $aim, null, $field, $value );

        if ( 'byline' === $aim )
        {
            if ( !empty( $post_id ) )
            {
                $post_main_author = Post::get_main_author( $post_id );
                $main_author      = new Author( $post_main_author->id, $post_main_author->type );

                $display_name  = Post::get_byline( $post_id );
                $user_nicename = $main_author->get_slug();
            }
            else
            {
                return null;
            }
        }
        $user = new \WP_User();
        $user->guest_id         = $author->get_id();
        $user->display_name     = ( !empty( $display_name ) ? $display_name : $author->get_name() );
        $user->user_url         = $author->get_website();
        $user->description      = $author->get_description();
        $user->user_description = $user->description;
        $user->user_nicename    = ( !empty( $user_nicename ) ? $user_nicename : $author->get_slug() );
        $user->nickname         = $user->display_name;
        $user->user_email       = $author->get_email();
        $user->first_name       = $author->get_first_name();
        $user->last_name        = $author->get_last_name();
        $user->user_registered  = get_the_date( '', $author->get_id() );

        return $user;
    }
    public static function get_guest_count()
    {
        return get_option( 'molongui_authorship_guest_count', 0 );
    }
    public static function is_guest_archive()
    {
        $the_query = WP::the_query();

        if ( !isset( $the_query ) )
        {
            $caller = debug_backtrace()[1]['function'];

            _doing_it_wrong( __FUNCTION__, sprintf( __( "The %s function is calling it before the query is run. Before then, it always return false." ), '<strong>'.$caller.'</strong>' ), '3.2.3' );
            return false;
        }

        return isset( $the_query->is_guest_author ) ? $the_query->is_guest_author : false;
    }

} // class
new Guest_Author();
