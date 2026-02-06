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
    public static function can_save_post( $post_id, $_post = null )
    {
        if ( defined( 'DOING_AUTOSAVE' ) and DOING_AUTOSAVE )
        {
            return false;
        }
        if ( empty( $post_id ) )
        {
            return false;
        }
        if ( wp_is_post_revision( $post_id ) !== false )
        {
            return false;
        }
        if ( !empty( $_POST ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            $_post = $_POST;
        }
        if ( !isset( $_post ) or empty( $_post ) )
        {
            return false;
        }
        if ( !isset( $_post['post_ID'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( (int)$_post['post_ID'] !== (int)$post_id ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( !isset( $_post['post_type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        {
            return false;
        }
        if ( 'page' == $_post['post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
    public static function sanitize_post_status_arg( $post_status, $fallback = 'publish' )
    {
        $valid_statuses = array_keys( get_post_stati() );

        /*!
         * FILTER HOOK
         * Filters the default fallback value used when sanitizing a "post_status" query argument.
         *
         * The fallback is used only when the incoming post_status value is empty/invalid and a safe default must be
         * applied. The default fallback is an empty string, which causes WP_Query to apply its own context-aware
         * defaults:
         *
         * - On the front end, the default status is "publish".
         * - When the user is logged in, "private" may also be included (subject to capabilities and post type).
         * - Public custom post statuses may be included by default.
         * - In admin/AJAX contexts, protected statuses may be added as well (by default: "future", "draft", "pending").
         *
         * Return a string status (e.g. "publish"), "any", an array of statuses, or an empty string to defer
         * to WordPress defaults.
         *
         * @param  string|array $fallback Default fallback value. Empty string defers to WordPress defaults.
         * @return string|array           Filtered fallback value.
         */
        $fallback = apply_filters( 'authorship/post_status_fallback', '' );
        if ( is_string( $post_status ) )
        {
            $raw = trim( $post_status );

            if ( $raw === '' )
            {
                return $fallback;
            }

            if ( strtolower( $raw ) === 'any' )
            {
                return 'any';
            }

            if ( is_numeric( $raw ) )
            {
                return $fallback;
            }

            $key = sanitize_key( $raw );
            return ( $key !== '' && in_array( $key, $valid_statuses, true ) )
                ? array( $key )
                : $fallback;
        }
        if ( is_array( $post_status ) )
        {
            $out = array();

            foreach ( $post_status as $st )
            {
                if ( ! is_string( $st ) )
                {
                    continue;
                }

                $raw = trim( $st );
                if ( $raw === '' || is_numeric( $raw ) )
                {
                    continue;
                }

                $key = sanitize_key( $raw );
                if ( $key !== '' && in_array( $key, $valid_statuses, true ) )
                {
                    $out[] = $key;
                }
            }

            $out = array_values( array_unique( $out ) );
            return ! empty( $out ) ? $out : $fallback;
        }
        return $fallback;
    }
    public static function sanitize_cat_query_args( $cat, $options = array() )
    {
        $mode = isset( $options['mode'] ) ? strtolower( trim( (string) $options['mode'] ) ) : 'in';
        if ( $mode !== 'and' )
        {
            $mode = 'in';
        }

        $include = array();
        $exclude = array();
        if ( $cat === '' || $cat === null || $cat === false )
        {
            return array();
        }
        $tokens = array();

        if ( is_array( $cat ) )
        {
            $tokens = $cat;
        }
        elseif ( is_string( $cat ) )
        {
            $cat = trim( $cat );

            if ( $cat === '' )
            {
                return array();
            }
            $tokens = ( strpos( $cat, ',' ) !== false ) ? explode( ',', $cat ) : array( $cat );
        }
        elseif ( is_int( $cat ) || is_float( $cat ) )
        {
            $tokens = array( $cat );
        }
        else
        {
            return array();
        }
        foreach ( $tokens as $token )
        {
            if ( is_int( $token ) || is_float( $token ) )
            {
                $n = (int) $token;
            }
            elseif ( is_string( $token ) )
            {
                $token = trim( $token );

                if ( $token === '' || ! is_numeric( $token ) )
                {
                    continue;
                }

                $n = (int) $token;
            }
            else
            {
                continue;
            }
            if ( $n === 0 )
            {
                continue;
            }
            if ( $n < 0 )
            {
                $exclude[] = absint( $n );
            }
            else
            {
                $include[] = absint( $n );
            }
        }
        $include = array_values( array_unique( array_filter( $include ) ) );
        $exclude = array_values( array_unique( array_filter( $exclude ) ) );
        if ( empty( $include ) && empty( $exclude ) )
        {
            return array();
        }
        if ( count( $include ) === 1 && empty( $exclude ) )
        {
            return array( 'cat' => (int) $include[0] );
        }

        if ( count( $exclude ) === 1 && empty( $include ) )
        {
            return array( 'cat' => -1 * (int) $exclude[0] );
        }
        $out = array();

        if ( ! empty( $include ) )
        {
            $out_key = ( $mode === 'and' ) ? 'category__and' : 'category__in';
            $out[ $out_key ] = $include;
        }

        if ( ! empty( $exclude ) )
        {
            $out['category__not_in'] = $exclude;
        }

        return $out;
    }

} // class
