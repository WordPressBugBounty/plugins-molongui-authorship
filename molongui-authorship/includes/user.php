<?php
/*!
 * Utility functions to handle and retrieve WP_User data, while filtering and overriding core WordPress user data.
 *
 * Note: This class does not maintain state and should not be used to store user data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      4.8.0
 */

namespace Molongui\Authorship;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class User extends \Molongui\Authorship\Common\Utils\User
{
    public function __construct()
    {
        if ( Post::byline_takeover() )
        {
            add_filter( '_authorship/get_user_by', array( $this, 'get_user_by' ), 10, 3 );
            add_filter( '_authorship/no_userdata', array( $this, 'no_userdata' ), 10, 3 );
        }
    }
    public function get_user_by( $user, $field, $value )
    {
        $original_user = $user;
        global $pagenow;
        if ( is_admin() and !wp_doing_ajax()
             or 'wp-login.php' == $pagenow
             or in_array( $field, array( 'login', 'url' ) )
        )  return $original_user;
        global $in_comment_loop;
        if ( $in_comment_loop )
        {
            return $original_user;
        }
        $_user = apply_filters( 'authorship/pre_get_user_by', null, $original_user, $field, $value );
        if ( null !== $_user )
        {
            return $_user;
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        if ( apply_filters( '_authorship/get_user_by/delete_cache', true, $user, $field, $value ) )
        {
            if ( !empty( $user->ID )            ) wp_cache_delete( $user->ID, 'users' );
            if ( !empty( $user->user_login )    ) wp_cache_delete( $user->user_login, 'userlogins' );
            if ( !empty( $user->user_email )    ) wp_cache_delete( $user->user_email, 'useremail' );
            if ( !empty( $user->user_nicename ) ) wp_cache_delete( $user->user_nicename, 'userslugs' );
        }

        /*!
         * [PRIVATE] FILTER HOOK
         * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
         * If you choose to use it, you do so at your own risk, as it may cause code issues.
         */
        $post_id = apply_filters( '_authorship/get_user_by/post_id', Post::get_id(), $user, $field, $value );
        global $wp_query;
        if ( is_object( $wp_query ) and $wp_query->is_author )
        {
            $author_id   = !empty( $user ) ? $user->ID : 0;
            $author_type = 'user';

            if ( molongui_is_guest_author() )
            {
                $author_id   = $wp_query->guest_author_id;
                $author_type = 'guest';
            }

            $author = new Author( $author_id, $author_type );
            $aim = 'info';
            if ( in_the_loop() )
            {
                if ( !empty( $post_id ) )
                {
                    $aim = 'byline';
                }
            }

            /*!
             * [PRIVATE] FILTER HOOK
             * For internal use only. This filter may be changed or removed at any time without notice or deprecation.
             * If you choose to use it, you do so at your own risk, as it may cause code issues.
             */
            $aim = apply_filters( '_authorship/get_user_by/aim', $aim, $user, $field, $value );

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
                    return $user;
                }
            }
            if ( molongui_is_guest_author() )
            {
                $user = new \WP_User();
                $user->guest_id         = $author_id;
                $user->display_name     = ( !empty( $display_name ) ? $display_name : $author->get_display_name() );
                $user->user_url         = $author->get_website();
                $user->description      = $author->get_description();
                $user->user_description = $user->description;
                $user->user_nicename    = ( !empty( $user_nicename ) ? $user_nicename : $author->get_slug() );
                $user->nickname         = $user->display_name;
                $user->user_email       = $author->get_email();
                $user->first_name       = $author->get_first_name();
                $user->last_name        = $author->get_last_name();
                $user->user_registered  = get_the_date( '', $author_id );
            }
            else
            {
                if ( 'byline' === $aim )
                {
                    $user->ID            = ( ( !empty( $post_main_author ) and isset( $post_main_author->id ) ) ? $post_main_author->id : $user->ID ); // We need to restore user ID that might have been altered above.
                    $user->display_name  = ( !empty( $display_name ) ? $display_name : $author->get_name() );
                    $user->user_nicename = $author->get_slug();
                    $user->nickname      = $user->display_name;
                }
                else
                {
                    if ( !empty( $wp_query->query_vars['author'] ) )
                    {
                        $user = new \WP_User( $wp_query->query_vars['author'] );
                    }
                }
            }

            return $user;
        }
        elseif ( ( ( is_object( $wp_query ) and $wp_query->is_home ) )
            or ( is_object( $wp_query ) and $wp_query->is_main_query() and get_option( 'page_for_posts' ) == $wp_query->get_queried_object_id() )
            or ( is_object( $wp_query ) and $wp_query->is_singular and Post::is_post_type_enabled( '', $post_id ) )
            or ( is_object( $wp_query ) and $wp_query->is_search )
            or ( is_object( $wp_query ) and $wp_query->is_category )
            or ( is_object( $wp_query ) and $wp_query->is_tag )
        ){
            if ( empty( $post_id ) )
            {
                return $user;
            }
            $main_author = Post::get_main_author( $post_id );

            if ( empty( $main_author ) or !isset( $main_author->id ) )
            {
                return $user;
            }

            $author_class = new Author( $main_author->id, $main_author->type );

            $dbt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
            if ( Post::has_multiple_authors( $post_id ) )
            {
                $user->display_name     = Post::get_byline( $post_id );
                $user->user_url         = $author_class->get_website(); //Author_Filters::the_author_link( $user->user_url, $post_id );
                $user->description      = '';
                $user->user_description = $user->description;
                $user->user_nicename    = $author_class->get_slug();
                $user->nickname         = $user->display_name;
                if ( 'guest' === $main_author->type )
                {
                    $user->guest_id = $author_class->get_id();
                    if ( !in_array('get_avatar_data', array_column($dbt, 'function' ) ) )
                    {
                        $user->user_email = $author_class->get_email();
                    }
                }
                return $user;
            }
            elseif ( Post::is_guest( $post_id ) )
            {
                $user->guest_id         = $author_class->get_id();
                $user->display_name     = $author_class->get_display_name();
                $user->user_url         = $author_class->get_website();
                $user->description      = $author_class->get_description();
                $user->user_description = $user->description;
                $user->user_nicename    = $author_class->get_slug();
                $user->nickname         = $user->display_name;
                if ( isset( $dbt[1]['function'] ) and $dbt[1]['function'] != 'get_avatar_data' )
                {
                    $user->user_email = $author_class->get_email();
                }
                return $user;
            }
        }
        return $user;
    }
    public function no_userdata( $false, $field, $value )
    {
        global $wp_query;
        if ( isset( $wp_query ) and !empty( $wp_query->is_guest_author ) and !empty( $wp_query->guest_author_id ) )
        {
            $user = Guest_Author::get_guest_by( $field, $value );
            $user->ID = $wp_query->guest_author_id;

            return $user;
        }
        else
        {
            return $false;
        }
    }

} // class
new User();