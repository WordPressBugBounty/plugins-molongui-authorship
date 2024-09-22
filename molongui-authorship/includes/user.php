<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class User extends \Molongui\Authorship\Common\Utils\User
{
    public function __construct()
    {
        add_filter( 'manage_users_columns', array( $this, 'edit_admin_columns' ) );
        add_action( 'manage_users_custom_column', array( $this, 'fill_admin_columns' ), 10, 3 );
        add_filter( 'user_profile_picture_description', array( $this, 'picture_description' ), 10, 2 );
        add_action( 'edit_user_profile', array( $this, 'add_custom_profile_fields' ), 0 ); // Edit user screen
        add_action( 'show_user_profile', array( $this, 'add_custom_profile_fields' ), 0 ); // Profile screen
        add_action( 'profile_update', array( $this, 'save_custom_fields' ) );
        add_action( 'delete_user', array( $this, 'save_user_posts_id' ), 10, 2 );
        add_action( 'deleted_user', array( $this, 'remove_custom_fields' ), 10, 2 );
        add_action( 'user_register' , array( __CLASS__, 'authorship_user_clear_object_cache' ), 0 ); // Fires immediately after a new user is registered.
        add_action( 'profile_update', array( __CLASS__, 'authorship_user_clear_object_cache' ), 0 ); // Fires immediately after an existing user is updated.
        add_action( 'deleted_user'  , array( __CLASS__, 'authorship_user_clear_object_cache' ), 0 ); // Fires immediately after a user is deleted from the database.
        add_action( 'user_register' , array( __CLASS__, 'update_user_count' ) ); // Fires immediately after a new user is registered.
        add_action( 'profile_update', array( __CLASS__, 'update_user_count' ) ); // Fires immediately after an existing user is updated.
        add_action( 'deleted_user'  , array( __CLASS__, 'update_user_count' ) ); // Fires immediately after a user is deleted from the database.
        add_action( 'set_user_role' , array( __CLASS__, 'update_user_count' ) ); // Fires after the user's role has changed using the "Change role to..." quick setting
        add_action( 'admin_notices', array( __CLASS__, 'post_as_others_admin_notice' ) );
        if ( authorship_is_feature_enabled( 'multi' ) )
        {
            add_filter( 'user_has_cap', array( __CLASS__, 'edit_others_posts' ), PHP_INT_MAX, 4 );
            add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
        }
    }
    public function edit_admin_columns( $column_headers )
    {
        unset( $column_headers['posts'] );
        $column_headers['molongui-entries'] = __( "Entries", 'molongui-authorship' );
        if ( authorship_is_feature_enabled( 'box' ) )
        {
            $column_headers['molongui-box'] = __( "Author Box", 'molongui-authorship' );
        }
        $column_headers['user-id'] = __( 'ID' );

        return $column_headers;
    }
    public function fill_admin_columns( $value, $column, $ID )
    {
        if ( $column == 'user-id' ) return $ID;
        elseif ( $column == 'molongui-entries' )
        {
            $html = '';
            $post_types = molongui_supported_post_types( MOLONGUI_AUTHORSHIP_PREFIX, 'all', true );
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
                if ( !current_user_can( 'read', $user_id ) or get_current_user_id() !== $user_id )
                {
                    return;
                }
            }
            $match = array_intersect( $user->roles, apply_filters( 'authorship/user/roles', array( 'administrator', 'editor', 'author', 'contributor' ) ) );
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

            $user     = new stdClass();
            $user->ID = 0;
        }
        authorship_enqueue_edit_user_scripts();
        wp_nonce_field('molongui_authorship_update_user', 'molongui_authorship_update_user_nonce');
        if ( authorship_is_feature_enabled( 'user_profile' ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-plugin-fields.php';
        }
        elseif ( authorship_is_feature_enabled( 'avatar' ) )
        {
            include MOLONGUI_AUTHORSHIP_DIR . 'views/user/html-admin-profile-picture.php';
        }
    }
    function picture_description( $description, $profileuser )
    {
        $add          = ' ';
        $user_profile = authorship_is_feature_enabled( 'user_profile' );
        $local_avatar = authorship_is_feature_enabled( 'avatar' );
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
            $add .= sprintf( __( 'Or you can upload a custom profile picture enabling Molongui Authorship "Local Avatar" feature %shere%s.', 'molongui-authorship' ), '<a href="' . authorship_options_url( 'users' ) . '" target="_blank">', '</a>' );
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
        if ( authorship_is_feature_enabled( 'user_profile' ) )
        {
            update_user_meta( $user_id, 'molongui_author_phone', ( isset( $_POST['molongui_author_phone'] ) ? sanitize_text_field( $_POST['molongui_author_phone'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_job', ( isset( $_POST['molongui_author_job'] ) ? sanitize_text_field( $_POST['molongui_author_job'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_company', ( isset( $_POST['molongui_author_company'] ) ? sanitize_text_field( $_POST['molongui_author_company'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_company_link', ( isset( $_POST['molongui_author_company_link'] ) ? sanitize_url( $_POST['molongui_author_company_link'] ) : '' ) );
            update_user_meta( $user_id, 'molongui_author_custom_link', ( isset( $_POST['molongui_author_custom_link'] ) ? sanitize_url( $_POST['molongui_author_custom_link'] ) : '' ) );

            foreach ( authorship_get_social_networks( 'enabled' ) as $id => $network )
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
        if ( authorship_is_feature_enabled( 'avatar' ) )
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
        authorship_update_post_counters( 'all', $new_usr );
    }
    public static function authorship_user_clear_object_cache()
    {
        authorship_clear_cache( 'users' );
        authorship_clear_cache( 'posts' );
    }
    public static function can_post_as_others( $user = 0 )
    {
        $post_as_others = false;

        $user = self::get( $user );

        if ( $user instanceof \WP_User )
        {
            remove_filter( 'user_has_cap', array( __CLASS__, 'edit_others_posts' ), PHP_INT_MAX );
            if ( user_can( $user, 'edit_others_posts' ) )
            {
                $post_as_others = true;
            }
            add_filter( 'user_has_cap', array( __CLASS__, 'edit_others_posts' ), PHP_INT_MAX, 4 );
        }

        /*!
         * FILTER HOOK
         *
         * Allows filtering whether the user can post as another author.
         *
         * @since 4.8.0
         */
        return apply_filters( 'authorship/can_post_as_others', $post_as_others );
    }
    public static function post_as_others_admin_notice()
    {
        if ( array_key_exists( 'posting-as-others', $_GET ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p><?php printf( __( "You are not allowed to post on behalf of others. Ask your administrator to enable that option for you on %sAuthors > Settings > Users > Permissions%s if you wish to remove your name as the post author.", 'molongui-authorship' ), '<code><strong>', '</strong></code>' ); ?></p>
            </div>
        <?php endif;
    }
    public static function edit_others_posts( $allcaps, $caps, $args, $user )
    {
        $cap     = $args[0];                         // The capability being checked
        $post_id = isset( $args[2] ) ? $args[2] : 0; // The post ID being checked, if available

        $postType = empty( $post_id ) ? Post::get_post_type() : Post::get_post_type( $post_id );
        $obj      = get_post_type_object( $postType );
        if ( !$obj or 'revision' == $obj->name )
        {
            return $allcaps;
        }
        if ( !is_user_logged_in() )
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
        $post_authors = authorship_get_post_authors( $post_id, 'id' );
        $allowEdit    = is_array( $post_authors ) ? in_array( $user->ID, $post_authors ) : false;

        if ( $allowEdit )
        {
            $post_status = get_post_status( $post_id );

            if ( 'publish' == $post_status and isset( $obj->cap->edit_published_posts ) and !empty( $user->allcaps[$obj->cap->edit_published_posts] ) )
            {
                $allcaps[$obj->cap->edit_published_posts] = true;
            }
            elseif ( 'private' == $post_status and isset( $obj->cap->edit_private_posts ) and !empty( $user->allcaps[$obj->cap->edit_private_posts] ) )
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
                $post_authors = authorship_get_post_authors( $post_id, 'id' );
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
        $user_roles = apply_filters( 'authorship/user/roles', array( 'administrator', 'editor', 'author', 'contributor' ) );

        /*!
         * FILTER HOOK
         *
         * Allows the use of WP_User_Query instead of a custom SQL query to count the number of users.
         *
         * When dealing with a large number of users, using the WP_User_Query can become slow. A more efficient way
         * to get the user count based on roles is to run a custom SQL query directly on the database. This approach
         * bypasses the overhead of WP_User_Query and can be significantly faster.
         *
         * Some empirical numbers:
         *
         *   Number of users    Approach        Execution time
         *   5,000              custom SQL      ~0.30 seconds
         *   50,000             custom SQL      ~0.30 seconds
         *   100,000            custom SQL      ~0.30 seconds
         *   5,000              WP_User_Query   ~0.30 seconds
         *   50,000             WP_User_Query   ~0.50 seconds
         *   100,000            WP_User_Query   ~0.77 seconds
         *
         * @since 4.9.5
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
    public static function get_user_count()
    {
        return get_option( 'molongui_authorship_user_count', 0 );
    }

} // class
new User();





/*
 * This is for the PRO add-on:
function authorship_pro_post_as_others( $default )
{
    $options = authorship_get_options();

    if ( $options['post_as_others'] )
    {
        $enabled_roles = $options['user_roles_post_as_others'];
        $match         = array_intersect( $enabled_roles, self::get_roles() );
        if ( !empty( $match ) )
        {
            return true;
        }
    }

    return $default;
}
*/