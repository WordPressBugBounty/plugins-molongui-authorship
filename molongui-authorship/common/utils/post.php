<?php

namespace Molongui\Authorship\Common\Utils;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Post
{
    public static function get( $post = null )
    {
        $post = get_post( $post );
        if ( !$post or empty( $post->ID ) )
        {
            global $wp_query;

            if ( empty( $wp_query ) )
            {
                return null;
            }

            if ( isset( $wp_query->queried_object ) )
            {
                $post = $wp_query->queried_object;
            }
            elseif ( !empty( $wp_query->is_singular ) and !empty( $wp_query->post ) )
            {
                $post = $wp_query->post;
            }
        }

        if ( !$post )
        {
            return null;
        }

        return $post;
    }
    public static function get_id( $post = null )
    {
        if ( is_int( $post ) )
        {
            return $post;
        }

        $post = self::get( $post );

        if ( !$post or !isset( $post->ID ) or $post->ID == 0 )
        {
            return null;
        }

        return (int) $post->ID;
    }
    public static function retrieve_post_type( $post_or_id )
    {
        $post = null;

        if ( is_numeric( $post_or_id ) )
        {
            $post_or_id = (int)$post_or_id;

            if ( !empty( $post_or_id ) )
            {
                $post = get_post( $post_or_id );
            }
        }
        else
        {
            $post = $post_or_id;
        }

        if ( !$post instanceof \WP_Post )
        {
            return null;
        }

        return $post->post_type;
    }
    public static function get_post_type( $post_or_id = null )
    {
        if ( isset( $post_or_id ) )
        {
            return self::retrieve_post_type( $post_or_id );
        }
        global $post, $typenow, $pagenow, $current_screen, $wp_query;

        $post_id   = isset( $_REQUEST['post'] ) ? (int)$_REQUEST['post'] : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_type = null;

        if ( is_object( $post ) and $post instanceof \WP_Post and $post->post_type )
        {
            $post_type = $post->post_type;
        }
        elseif ( $typenow )
        {
            $post_type = $typenow;
        }
        elseif ( $current_screen and !empty( $current_screen->post_type ) )
        {
            $post_type = $current_screen->post_type;
        }
        elseif ( isset( $_REQUEST['post_type'] ) and !empty( $_REQUEST['post_type'] ) and is_string( $_REQUEST['post_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            $post_type = sanitize_key( $_REQUEST['post_type'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        elseif ( 'post.php' == $pagenow and !empty( $post_id ) )
        {
            $post_type = self::retrieve_post_type( $post_id );
        }
        elseif ( 'edit.php' == $pagenow and empty( $_REQUEST['post_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        {
            $post_type = 'post';
        }
        elseif ( isset( $wp_query ) and is_author() )
        {
            $post_type = 'post';
        }

        return $post_type;
    }
    public static function get_post_types( $type = 'all', $output = 'names', $setting = false )
    {
        $wp_post_types     = ( ( $type == 'wp'  or $type == 'all' ) ? get_post_types( array( 'public' => true, '_builtin' => true  ), $output ) : array() );
        $custom_post_types = ( ( $type == 'cpt' or $type == 'all' ) ? get_post_types( array( 'public' => true, '_builtin' => false ), $output ) : array() );
        $post_types = array_merge( $wp_post_types, $custom_post_types );
        if ( $setting )
        {
            $options = array();

            foreach ( $post_types as $post_type )
            {
                $options[] = array( 'id' => $post_type->name, 'label' => $post_type->labels->name );
            }

            return $options;
        }
        return $post_types;
    }
    public static function copy_custom_meta( $from_post_id, $to_post_id )
    {
        $from_post_meta = get_post_meta( $from_post_id );
        $core_meta = array
        (
            '_wp_page_template',
            '_thumbnail_id',
        );

        foreach ( $from_post_meta as $meta_key => $values )
        {
            if ( 0 === strpos( $meta_key, '_molongui' ) or in_array( $meta_key, $core_meta, true ) )
            {
                $value = $values[0];
                $value = maybe_unserialize( $value );
                update_metadata( 'post', $to_post_id, $meta_key, $value );
            }
        }
    }
    public static function can_save_post( $post_id )
    {
        if ( is_null( $post_id ) or empty( $_POST ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( !isset( $_POST['post_ID'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( (int)$_POST['post_ID'] !== (int)$post_id ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( !isset( $_POST['post_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
        {
            return false;
        }
        if ( wp_is_post_revision( $post_id ) !== false )
        {
            return false;
        }
        if ( 'page' == $_POST['post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            if ( !current_user_can( 'edit_page', $post_id ) )
            {
                return false;
            }
        }
        elseif ( !current_user_can( 'edit_post', $post_id ) )
        {
            return false;
        }
        return true;
    }
    public static function clone_post( $post_id = null, $status = null )
    {
        $redirect = false;
        if ( empty( $post_id ) )
        {
            $post_id = isset( $_GET['post'] ) ? sanitize_key( $_GET['post'] ) : ( isset( $_POST['post'] ) ? sanitize_key( $_POST['post'] ) : null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing

            if ( empty( $post_id ) )
            {
                wp_die( "No post to duplicate has been supplied!" );
            }

            $redirect = true;
        }
        $post = get_post( $post_id );
        if ( isset( $post ) and $post != null )
        {
            $current_user    = wp_get_current_user();
            $new_post_author = $current_user->ID;
            $args = array
            (
                'comment_status' => $post->comment_status,
                'ping_status'    => $post->ping_status,
                'post_author'    => $new_post_author,
                'post_content'   => $post->post_content,
                'post_excerpt'   => $post->post_excerpt,
                'post_name'      => $post->post_name,
                'post_parent'    => $post->post_parent,
                'post_password'  => $post->post_password,
                'post_status'    => empty( $status ) ? $post->post_status : $status,
                'post_title'     => $post->post_title,
                'post_type'      => $post->post_type,
                'to_ping'        => $post->to_ping,
                'menu_order'     => $post->menu_order,
            );
            $new_post_id = wp_insert_post( $args );
            $taxonomies = get_object_taxonomies( $post->post_type );
            foreach ( $taxonomies as $taxonomy )
            {
                $post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
                wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
            }
            global $wpdb;
            $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id" );
            if ( count( $post_meta_infos ) != 0 )
            {
                $sql_query = "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value) ";
                foreach ( $post_meta_infos as $meta_info )
                {
                    $meta_key = $meta_info->meta_key;
                    $meta_value = addslashes($meta_info->meta_value );
                    $sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
                }
                $sql_query .= implode( " UNION ALL ", $sql_query_sel );
                $wpdb->query( $sql_query );
            }

            do_action( 'authorship/post_cloned', $new_post_id, $post_id );

            if ( $redirect )
            {
                wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
                exit;
            }
            else
            {
                return $new_post_id;
            }
        }
        else
        {
            if ( $redirect )
            {
                wp_die( 'Post duplication failed, could not find original post: ' . esc_html( $post_id ) );
            }
            else
            {
                return false;
            }
        }
    }

} // class