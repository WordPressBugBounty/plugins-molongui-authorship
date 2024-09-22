<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Guest_Author
{
    const POST_TYPE = 'guest_author';
    public function __construct()
    {
        if ( authorship_is_feature_enabled( 'guest' ) )
        {
            add_action( 'init', array( $this, 'register_post_type' ) );
            add_filter( 'post_type_link', array( $this, 'post_link' ), 10, 4 );
            add_filter( 'post_updated_messages', array( $this, 'custom_messages' ) );
            add_action( 'admin_menu', array( $this, 'remove_menu_item' ) );
            add_action( 'transition_post_status', array( $this, 'maybe_update_guest_count' ), 10, 3 );
        }
    }
    function register_post_type()
    {
        $options = Settings::get();
        $labels = array
        (
            'name'					=> _x( "Guest Authors", 'post type general name', 'molongui-authorship' ),
            'singular_name'			=> _x( "Guest Author", 'post type singular name', 'molongui-authorship' ),
            'menu_name'				=> __( "Guest Authors", 'molongui-authorship' ),
            'name_admin_bar'		=> __( "Guest Author", 'molongui-authorship' ),
            'all_items'				=> ( ( !empty( $options['guests_menu_level'] ) and $options['guests_menu_level'] != 'top' ) ? __( "Guest Authors", 'molongui-authorship' ) : __( "All Guest Authors", 'molongui-authorship' ) ),
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
        if ( $options['guests_menu'] )
        {
            $show_in_menu = ( ( !empty( $options['guests_menu_level'] ) and $options['guests_menu_level'] !== 'top' ) ? $options['guests_menu_level'] : true );
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
            'supports'		 		=> authorship_is_feature_enabled( 'avatar' ) ? array( 'thumbnail' ) : array( '' ),
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
    function post_link( $post_link, $post, $leavename, $sample )
    {
        if ( self::POST_TYPE === $post->post_type )
        {
            $guest = new Author( $post->ID, 'guest', $post );
            $post_link = $guest->get_url();
        }

        return $post_link;
    }
    function custom_messages( $msg )
    {
        $msg[self::POST_TYPE] = array
        (
            0  => '',                                                   // Unused. Messages start at index 1.
            1  => __( "Guest author updated.", 'molongui-authorship' ),
            2  => "Custom field updated.",                              // Probably better do not touch
            3  => "Custom field deleted.",                              // Probably better do not touch
            4  => __( "Guest author updated.", 'molongui-authorship' ),
            5  => __( "Guest author restored to revision", 'molongui-authorship' ),
            6  => __( "Guest author published.", 'molongui-authorship' ),
            7  => __( "Guest author saved.", 'molongui-authorship' ),
            8  => __( "Guest author submitted.", 'molongui-authorship' ),
            9  => __( "Guest author scheduled.", 'molongui-authorship' ),
            10 => __( "Guest author draft updated.", 'molongui-authorship' ),
        );

        return $msg;
    }
    function remove_menu_item()
    {
        $menu_level = Settings::get( 'guests_menu_level' );

        $slug = 'edit.php?post_type='.self::POST_TYPE;

        if ( !current_user_can( 'edit_others_pages' ) and !current_user_can( 'edit_others_posts' ) )
        {
            if ( 'top' !== $menu_level )
            {
                if ( 'users.php' === $menu_level )
                {
                    $menu_level = 'profile.php';
                }

                remove_submenu_page( $menu_level, $slug );
            }
            else
            {
                remove_menu_page( $slug );
            }
        }
    }
    public function maybe_update_guest_count( $new_status, $old_status, $post )
    {
        if ( self::POST_TYPE === $post->post_type and ( ( $new_status === 'publish' and $old_status !== 'publish' ) or ( $new_status !== 'publish' and $old_status === 'publish' ) ) )
        {
            self::update_guest_count();
        }
    }
    public static function update_guest_count()
    {
        /*!
         * FILTER HOOK
         *
         * Allows the use of 'wp_count_posts' instead of a custom SQL query to count the number of guest authors.
         *
         * When dealing with a large number of guests, using 'wp_count_posts' can become slow. A more efficient way
         * to get the guest count is to run a custom SQL query directly on the database. This approach bypasses
         * the overhead of 'wp_count_posts' and can be significantly faster.
         *
         * @since 4.9.5
         */
        if ( apply_filters( 'molongui_authorship/guest_count_custom_sql_query', true ) )
        {
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s",
                self::POST_TYPE, 'publish'
            );
            $guest_count = $wpdb->get_var( $query );
        }
        else
        {
            $guest_count = wp_count_posts( self::POST_TYPE );
            $guest_count = isset( $guest_count->publish ) ? $guest_count->publish : 0;
        }

        update_option( 'molongui_authorship_guest_count', $guest_count, false );
    }
    public static function get_guest_count()
    {
        return get_option( 'molongui_authorship_guest_count', 0 );
    }

} // class
new Guest_Author();
