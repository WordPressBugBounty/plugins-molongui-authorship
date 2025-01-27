<?php
/*!
 * Provides administrative functionality for managing user data in the context of Molongui Authorship.
 *
 * This file is responsible for handling various tasks related to user management in the admin area.
 *
 * Note: This class does not maintain state and should not be used to store user data.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      5.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Admin\Post_Count_Updater;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Admin_User extends \Molongui\Authorship\Common\Utils\User
{
    const EDIT_USER_SCRIPT   = MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-user.xxxx.min.js';
    const EDIT_AVATAR_SCRIPT = MOLONGUI_AUTHORSHIP_FOLDER . '/assets/js/edit-avatar.a05b.min.js';
    use Singleton;
    public function __construct()
    {
        add_action( 'admin_enqueue_scripts', array( $this, 'register_user_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_user_scripts' ) );
        add_filter( 'authorship/edit_user_script_params', array( $this, 'edit_user_script_params' ), 1 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_avatar_scripts' ) );
        add_filter( 'authorship/edit_avatar_script_params', array( $this, 'edit_avatar_script_params' ), 1 );
        add_filter( 'manage_users_columns', array( $this, 'edit_admin_columns' ) );
        add_action( 'manage_users_custom_column', array( $this, 'fill_admin_columns' ), 10, 3 );
        add_filter( 'user_profile_picture_description', array( $this, 'picture_description' ), 10, 2 );
        add_action( 'edit_user_profile', array( $this, 'add_custom_profile_fields' ), 0 ); // Edit user screen
        add_action( 'show_user_profile', array( $this, 'add_custom_profile_fields' ), 0 ); // Profile screen
        add_action( 'profile_update', array( $this, 'save_custom_fields' ) );
        add_action( 'delete_user', array( $this, 'save_user_posts_id' ), 10, 2 );
        add_action( 'deleted_user', array( $this, 'remove_custom_fields' ), 10, 2 );
        add_action( 'user_register' , array( __CLASS__, 'update_user_count' ) ); // Fires immediately after a new user is registered.
        add_action( 'profile_update', array( __CLASS__, 'update_user_count' ) ); // Fires immediately after an existing user is updated.
        add_action( 'deleted_user'  , array( __CLASS__, 'update_user_count' ) ); // Fires immediately after a user is deleted from the database.
        add_action( 'set_user_role' , array( __CLASS__, 'update_user_count' ) ); // Fires after the user's role has changed using the "Change role to..." quick setting
        add_action( 'admin_notices', array( __CLASS__, 'post_as_others_admin_notice' ) );
        add_action( 'user_register' , array( __CLASS__, 'clear_object_cache' ), 0 ); // Fires immediately after a new user is registered.
        add_action( 'profile_update', array( __CLASS__, 'clear_object_cache' ), 0 ); // Fires immediately after an existing user is updated.
        add_action( 'deleted_user'  , array( __CLASS__, 'clear_object_cache' ), 0 ); // Fires immediately after a user is deleted from the database.
        if ( Settings::is_enabled( 'co-authors' ) )
        {
            add_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX, 4 );
            add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), PHP_INT_MAX, 4 );
        }
    }
    public function edit_admin_columns( $column_headers )
    {
        unset( $column_headers['posts'] );
        $column_headers['molongui-entries'] = __( "Entries", 'molongui-authorship' );
        if ( Settings::is_enabled( 'author-box' ) )
        {
            $column_headers['molongui-box'] = __( "Author Box", 'molongui-authorship' );
        }
        $column_headers['user-id'] = __( 'ID' );

        return $column_headers;
    }
    public function fill_admin_columns( $value, $column, $ID )
    {
        if ( $column == 'user-id' )
        {
            return $ID;
        }
        elseif ( $column == 'molongui-entries' )
        {
            $html = '';
            $post_types = Settings::enabled_post_types( 'all', 'object' );
            $post_types_id = array_column( $post_types, 'id' );
            foreach ( array( 'post', 'page' ) as $post_type )
            {
                if ( !in_array( $post_type, $post_types_id ) )
                {
                    $post_type_obj = get_post_type_object( $post_type );
                    $post_types    = array_merge( $post_types, array( array( 'id' => $post_type, 'label' => $post_type_obj->label, 'singular' => $post_type_obj->labels->singular_name ) ) );
                }
            }
            foreach ( $post_types as $post_type )
            {
                $count = get_user_meta( $ID, 'molongui_author_' . $post_type['id'] . '_count', true );
                $link  = admin_url( 'edit.php?post_type=' . $post_type['id'] . '&author=' . $ID );
                if ( $count > 0 )
                {
                    $html .= '<div><a href="' . $link . '">' . $count . ' ' . ( $count == 1 ? $post_type['singular'] : $post_type['label'] ) . '</a></div>';
                }
            }
            if ( !$html ) $html = __( "None" );

            return $html;
        }
        elseif ( $column == 'molongui-box' )
        {
            switch ( get_user_meta( $ID, 'molongui_author_box_display', true ) )
            {
                case 'show':
                    $icon = 'visibility';
                    $tip  = __( "Visible", 'molongui-authorship' );
                    break;

                case 'hide':
                    $icon = 'hidden';
                    $tip  = __( "Hidden", 'molongui-authorship' );
                    break;

                default:
                    $icon = 'admin-generic';
                    $tip  = __( "Visibility depends on global plugin settings", 'molongui-authorship' );
                    break;
            }

            $html  = '<div class="m-tooltip">';
            $html .= '<span class="dashicons dashicons-'.$icon.'"></span>';
            $html .= '<span class="m-tooltip__text m-tooltip__top m-tooltip__w100">'.$tip.'</span>';
            $html .= '</div>';

            return $html;
        }

        return $value;
    }
    public function add_custom_profile_fields( $user )
    {
        if ( is_object( $user ) )
        {
            if ( !current_user_can( 'edit_user', $user->ID ) )
            {
                if ( !current_user_can( 'read', $user->ID ) or get_current_user_id() !== $user->ID )
                {
                    return;
                }
            }
            $match = array_intersect( $user->roles, Settings::enabled_user_roles() );
            if ( empty( $match ) )
            {
                return;
            }
        }
        else
        {
            if ( 'add-new-user' !== $user )
            {
                return;
            }

            $user     = new \stdClass();
            $user->ID = 0;
        }
        $this->enqueue_user_scripts();
        wp_nonce_field('molongui_authorship_update_user', 'molongui_authorship_update_user_nonce');
        if ( Settings::is_enabled( 'user-profile' ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-plugin-fields.php';
        }
        elseif ( Settings::is_enabled( 'local-avatar' ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-profile-picture.php';
        }
    }
    function picture_description( $description, $profileuser )
    {
        $add          = ' ';
        $user_profile = Settings::is_enabled( 'user-profile' );
        $local_avatar = Settings::is_enabled( 'local-avatar' );
        if ( $user_profile and $local_avatar )
        {
            $add .= sprintf( __( 'Or you can upload a custom profile picture using %sMolongui Authorship field%s.', 'molongui-authorship' ), '<a href="#molongui-user-fields">', '</a>' );
        }
        elseif ( !$user_profile and $local_avatar )
        {
            $add .= __( 'Or you can upload a custom profile using the "Local Avatar" field below.', 'molongui-authorship' );
        }
        else
        {
            $add .= sprintf( __( 'Or you can upload a custom profile picture enabling Molongui Authorship "Local Avatar" feature %shere%s.', 'molongui-authorship' ), '<a href="' . Settings::url() . '" target="_blank">', '</a>' );
        }

        return $description . $add;
    }
    function save_custom_fields( $user_id )
    {
        if ( !current_user_can( 'edit_user', $user_id ) )
        {
            if ( !current_user_can( 'read', $user_id ) or get_current_user_id() !== $user_id )
            {
                return $user_id;
            }
        }
        if ( !WP::verify_nonce( 'molongui_authorship_update_user' ) )
        {
            return $user_id;
        }
        if ( Settings::is_enabled( 'user-profile' ) )
        {
            update_user_meta( $user_id, 'molongui_author_phone', ( isset( $_POST['molongui_author_phone'] ) ? sanitize_text_field( $_POST['molongui_author_phone'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_job', ( isset( $_POST['molongui_author_job'] ) ? sanitize_text_field( $_POST['molongui_author_job'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_company', ( isset( $_POST['molongui_author_company'] ) ? sanitize_text_field( $_POST['molongui_author_company'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_company_link', ( isset( $_POST['molongui_author_company_link'] ) ? sanitize_url( $_POST['molongui_author_company_link'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_custom_link', ( isset( $_POST['molongui_author_custom_link'] ) ? sanitize_url( $_POST['molongui_author_custom_link'] ) : '' ) );

            foreach ( Social::get( 'enabled' ) as $id => $network )
            {
                if ( !empty( $_POST['molongui_author_' . $id] ) )
                {
                    update_user_meta( $user_id, 'molongui_author_' . $id, sanitize_text_field( $_POST['molongui_author_' . $id] ) );
                }
                else
                {
                    delete_user_meta( $user_id, 'molongui_author_' . $id );
                }
            }
            $checkboxes = array
            (
                'molongui_author_show_meta_mail',
                'molongui_author_show_meta_phone',
                'molongui_author_show_icon_mail',
                'molongui_author_show_icon_web',
                'molongui_author_show_icon_phone',
                'molongui_author_archived',
            );
            foreach ( $checkboxes as $checkbox )
            {
                if ( isset( $_POST[$checkbox] ) )
                {
                    update_user_meta( $user_id, $checkbox, sanitize_text_field( $_POST[$checkbox] ) );
                }
                else
                {
                    delete_user_meta( $user_id, $checkbox );
                }
            }
            update_post_meta( $user_id, 'molongui_author_box_display', 'default' );
            do_action( 'authorship/user/save', $user_id, $_POST );
        }
        if ( Settings::is_enabled( 'local-avatar' ) )
        {
            if ( current_user_can( 'upload_files', $user_id ) )
            {
                if ( isset( $_POST['molongui_author_image_id']   ) )
                {
                    update_user_meta( $user_id, 'molongui_author_image_id'  , sanitize_text_field( $_POST['molongui_author_image_id'] ) );
                }
                if ( isset( $_POST['molongui_author_image_url']  ) )
                {
                    update_user_meta( $user_id, 'molongui_author_image_url' , sanitize_url( $_POST['molongui_author_image_url'] )  );
                }
                if ( isset( $_POST['molongui_author_image_edit'] ) )
                {
                    update_user_meta( $user_id, 'molongui_author_image_edit', sanitize_url( $_POST['molongui_author_image_edit'] ) );
                }
            }
        }
    }
    function save_user_posts_id( $user_id, $reassign )
    {
        if ( null === $reassign )
        {
            return;
        }

        $author     = new Author( $user_id, 'user' );
        $user_posts = $author->get_posts( array( 'fields' => 'ids', 'post_type' => 'all' ) );

        add_filter( 'authorship/admin/user/delete', function() use ( $user_posts )
        {
            return $user_posts;
        });
    }
    function remove_custom_fields( $user_id, $reassign )
    {
        if ( null === $reassign )
        {
            return;
        }
        $post_ids = apply_filters( 'authorship/admin/user/delete', array() );
        if ( empty( $post_ids ) )
        {
            return;
        }
        $old_usr = 'user-' . $user_id;
        $new_usr = 'user-' . $reassign;
        foreach ( $post_ids as $post_id )
        {
            delete_post_meta( $post_id, '_molongui_author', $old_usr );
            if ( get_post_meta( $post_id, '_molongui_main_author', true ) === $old_usr )
            {
                update_post_meta( $post_id, '_molongui_main_author', $new_usr, $old_usr );
                update_post_meta( $post_id, '_molongui_author', $new_usr );
            }
        }
        $count_updater = Post_Count_Updater::instance();
        $count_updater::update_author_post_counter( array( 'id' => $reassign, 'type' => 'user' ), 'post' );
    }
    public static function post_as_others_admin_notice()
    {
        if ( array_key_exists( 'posting-as-others', $_GET ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php printf( __( "You are not allowed to post on behalf of others. Ask your administrator to enable that option for you on %sAuthors > Settings > Users > Permissions%s if you wish to remove your name as the post author.", 'molongui-authorship' ), '<code><strong>', '</strong></code>' ); ?></p>
            </div>
        <?php endif;
    }
    public function edit_others_posts( $allcaps, $caps, $args, $user )
    {
        if ( !is_user_logged_in() )
        {
            return $allcaps;
        }
        $cap     = $args[0];                         // The capability being checked
        $user_id = $args[1];                         // The user ID being checked
        $post_id = isset( $args[2] ) ? $args[2] : 0; // The post ID being checked, if available

        $postType = empty( $post_id ) ? Post::get_post_type() : Post::get_post_type( $post_id );
        $obj      = get_post_type_object( $postType );
        if ( !$obj or 'revision' === $obj->name )
        {
            return $allcaps;
        }
        if ( !empty( $user->allcaps[$obj->cap->edit_others_posts] ) )
        {
            return $allcaps;
        }
        global $in_comment_loop;
        if ( $in_comment_loop )
        {
            return $allcaps;
        }
        $caps_to_modify = array
        (
            $obj->cap->edit_post,
            'edit_post',
            $obj->cap->edit_others_posts,
            'authorship_granted_edit_others_posts',
        );
        if ( !in_array( $cap, $caps_to_modify ) )
        {
            return $allcaps;
        }

        $author = new Author( $user_id );
        if ( $author->is_coauthor_for( $post_id ) )
        {
            $post_status = get_post_status( $post_id );

            if ( 'publish' === $post_status and isset( $obj->cap->edit_published_posts ) and !empty( $user->allcaps[$obj->cap->edit_published_posts] ) )
            {
                $allcaps[$obj->cap->edit_published_posts] = true;
            }
            elseif ( 'private' === $post_status and isset( $obj->cap->edit_private_posts ) and !empty( $user->allcaps[$obj->cap->edit_private_posts] ) )
            {
                $allcaps[$obj->cap->edit_private_posts] = true;
            }
            $allcaps[$obj->cap->edit_others_posts] = true;
            $allcaps['authorship_granted_edit_others_posts'] = true;
        }

        return $allcaps;
    }
    public static function map_meta_cap( $caps, $cap, $user_id, $args )
    {
        if ( in_array( $cap, array( 'edit_post', 'edit_others_posts' ) ) and in_array('edit_others_posts', $caps, true ) )
        {
            if ( isset( $args[0] ) )
            {
                $post_id = (int)$args[0];
                $post_authors = Post::get_authors( $post_id, 'id' );
                $allowEdit    = is_array( $post_authors ) ? in_array( $user_id, $post_authors ) : false;

                if ( $allowEdit )
                {
                    foreach ( $caps as &$item )
                    {
                        if ( $item === 'edit_others_posts' )
                        {
                            $item = 'edit_posts';
                        }
                    }
                }

                $caps = apply_filters( 'authorship/post/filter_map_meta_cap', $caps, $cap, $user_id, $post_id );
            }
        }

        return $caps;
    }
    public static function update_user_count()
    {
        $user_roles = Settings::enabled_user_roles();

        /*!
         * FILTER HOOK
         * Allows the use of WP_User_Query instead of a custom SQL query to count the number of users.
         *
         * When dealing with a large number of users, using the WP_User_Query can become slow. A more efficient way
         * to get the user count based on roles is to run a custom SQL query directly on the database. This approach
         * bypasses the overhead of WP_User_Query and can be significantly faster.
         *
         * Some empirical numbers:
         *
         *   Number of Users    Custom SQL    WP_User_Query
         *   -----------------------------------------------
         *   5,000              ~0.30 s       ~0.30 s
         *   50,000             ~0.30 s       ~0.50 s
         *   100,000            ~0.30 s       ~0.77 s
         */
        if ( apply_filters( 'molongui_authorship/user_count_custom_sql_query', true ) )
        {
            global $wpdb;
            $roles_placeholders = implode(' OR ', array_fill( 0, count( $user_roles ), 'meta_value LIKE %s' ) );

            $role_like_clauses = array();
            foreach ( $user_roles as $role )
            {
                $role_like_clauses[] = '%' . $role . '%';
            }

            $sql = $wpdb->prepare(
                "
                SELECT COUNT( DISTINCT user_id )
                FROM $wpdb->usermeta
                WHERE meta_key = '{$wpdb->prefix}capabilities'
                AND ( $roles_placeholders )
                ",
                $role_like_clauses
            );
            $user_count = $wpdb->get_var( $sql );
        }
        else
        {
            $user_query = new \WP_User_Query( array
            (
                'role__in' => $user_roles,
                'fields'   => 'ID', // We only need the IDs to count the users
            ));
            $user_count = $user_query->get_total();
        }

        update_option( 'molongui_authorship_user_count', $user_count, false );
    }
    public function register_user_scripts()
    {
        $file = apply_filters( 'authorship/edit_user/script', self::EDIT_USER_SCRIPT );

        Assets::register_script( $file, 'edit_user' );
    }
    public function enqueue_user_scripts()
    {
        $screen = get_current_screen();
        if ( !in_array( $screen->id, array( 'profile', 'users', 'user', 'user-edit' ) ) )
        {
            return;
        }
        $file = apply_filters( 'authorship/edit_user/script', self::EDIT_USER_SCRIPT );

        Assets::enqueue_script( $file, 'edit_user', true );
    }
    public static function edit_user_script_params()
    {
        $params = array
        (
        );
        return apply_filters( 'authorship/edit_user/script_params', $params );
    }
    public function register_avatar_scripts()
    {
        $file = apply_filters( 'authorship/edit_avatar/script', self::EDIT_AVATAR_SCRIPT );

        Assets::register_script( $file, 'edit_avatar' );
    }
    public static function enqueue_avatar_scripts()
    {
        $file = apply_filters( 'authorship/edit_avatar/script', self::EDIT_AVATAR_SCRIPT );

        Assets::enqueue_script( $file, 'edit_avatar', true );
    }
    public static function edit_avatar_script_params()
    {
        $params = array
        (
            'remove' => __( "Remove", 'molongui-authorship' ),
            'edit'   => __( "Edit", 'molongui-authorship' ),
            'upload' => __( "Upload Avatar", 'molongui-authorship' ),
        );
        return apply_filters( 'authorship/edit_avatar/script_params', $params );
    }
    public static function clear_object_cache()
    {
        Cache::clear( 'posts' );
        Cache::clear( 'users' );
    }
    public function can_post_as_others( $user = 0 )
    {
        $post_as_others = false;

        $user = self::get( $user );

        if ( $user instanceof \WP_User )
        {
            remove_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX );
            if ( user_can( $user, 'edit_others_posts' ) )
            {
                $post_as_others = true;
            }
            add_filter( 'user_has_cap', array( $this, 'edit_others_posts' ), PHP_INT_MAX, 4 );
        }

        /*!
         * DEPRECATED
         * This filter hook is scheduled for removal in version 5.2.0. Update any dependencies accordingly.
         *
         * @since      4.8.0
         * @deprecated 5.0.0
         */
        if ( has_filter( 'authorship/can_post_as_others' ) and apply_filters( 'molongui_authorship/apply_filters_deprecated', true ) )
        {
            $post_as_others = apply_filters_deprecated( 'authorship/can_post_as_others', array( $post_as_others ), '5.0.0', 'molongui_authorship/can_post_as_others' );
        }

        /*!
         * FILTER HOOK
         * Allows filtering whether the user can post as another author.
         *
         * @since 5.0.0
         */
        return apply_filters( 'molongui_authorship/can_post_as_others', $post_as_others );
    }
    public static function get_user_count()
    {
        return get_option( 'molongui_authorship_user_count', 0 );
    }

} // class
Admin_User::instance();
