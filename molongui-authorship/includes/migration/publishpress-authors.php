<?php
/*!
 * Migration tool for PublishPress Authors data.
 *
 * This file defines the logic to migrate guest authors and authorship data from the PublishPress Authors plugin into
 * the Molongui Authorship format.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Settings;
class Publishpress_Authors
{
    protected $name = 'Publishpress Authors';
    protected $path = 'publishpress-authors/publishpress-authors.php';
    protected $id = 'ppma';
    public $author_tax = 'author';
    public $guest_user_role = 'ppma_guest_author';
    public $slug_prefix_pattern = ''; // The plugin doesn't add any prefix.
    use Utils;
    use Singleton;
    public function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_publishpress_authors_migration', true ) )
        {
            if ( is_admin() )
            {
                require_once 'publishpress-authors/cron.php';
            }
            if ( defined( 'WP_CLI' ) && WP_CLI )
            {
                add_filter( 'molongui_authorship/migrate_publishpress_authors', '__return_true' );
            }
        }
    }
    public function get_name()
    {
        return $this->name;
    }
    public function get_path()
    {
        return $this->path;
    }
    public function get_id()
    {
        return $this->id;
    }
    public function get_author_tax()
    {
        return $this->author_tax;
    }
    public function get_guest_user_role()
    {
        return $this->guest_user_role;
    }
    public function get_prefix()
    {
        return $this->slug_prefix_pattern;
    }
    public function get_guest_authors()
    {
        $guests = array();

        $with_user_account    = $this->get_guest_authors_with_user_account();
        $without_user_account = $this->get_guest_authors_without_user_account();
        $merged = array_merge( $with_user_account, $without_user_account );
        $guests = array_map( 'unserialize', array_unique( array_map( 'serialize', $merged ) ) );

        return $guests;
    }
    public function get_guest_authors_with_user_account()
    {
        global $wpdb;
        $sql = $wpdb->prepare( "
            SELECT u.ID
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = %s
            AND um.meta_value LIKE %s
        ", $wpdb->prefix . 'capabilities', '%' . $wpdb->esc_like( '"' . $this->get_guest_user_role() . '"' ) . '%' );
        $users = $wpdb->get_results( $sql );

        $guests = array();
        $meta_keys = array
        (
            'first_name',
            'last_name',
            'description',
            'avatar',
        );

        foreach ( $users as $user )
        {
            $user_id = $user->ID;
            $placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
            $meta_sql = $wpdb->prepare( "
                SELECT meta_key, meta_value
                FROM {$wpdb->usermeta}
                WHERE user_id = %d
                AND meta_key IN ($placeholders)
            ", array_merge( array( $user_id ), $meta_keys ) );

            $meta_results = $wpdb->get_results( $meta_sql );
            $meta = array();
            foreach ( $meta_results as $meta_row )
            {
                $meta[$meta_row->meta_key] = maybe_unserialize( $meta_row->meta_value );
            }
            $userdata = get_userdata( $user_id );
            $term = get_term_by( 'slug', $userdata->user_login, $this->get_author_tax() );

            if ( $term and !is_wp_error( $term ) )
            {
                $job_title = get_term_meta( $term->term_id, 'job_title', true );
            }
            $guests[] = (object) array
            (
                'ID'           => $userdata->ID,
                'source'       => 'user',
                'user_email'   => $userdata->user_email,
                'user_url'     => $userdata->user_url,
                'display_name' => $userdata->display_name,
                'first_name'   => isset( $meta['first_name'] ) ? $meta['first_name'] : '',
                'last_name'    => isset( $meta['last_name'] ) ? $meta['last_name'] : '',
                'description'  => isset( $meta['description'] ) ? $meta['description'] : '',
                'job_title'    => isset( $job_title ) ? $job_title : '',
                'avatar'       => isset( $meta['avatar'] ) ? $meta['avatar'] : '',
            );
        }

        return $guests;
    }
    public function get_guest_authors_without_user_account()
    {
        global $wpdb;

        $guests = array();
        $author_terms = $wpdb->get_results( $wpdb->prepare( "
            SELECT t.*
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = %s
            AND (tt.description IS NULL OR tt.description = '')
        ", 'author' ) );
        if ( empty( $author_terms ) )
        {
            error_log( 'No author terms found with empty description' );
            return $guests;
        }

        foreach ( $author_terms as $term )
        {
            $term_id = $term->term_id;
            if ( get_term_meta( $term_id, 'user_id', true ) )
            {
                continue;
            }
            $meta = get_term_meta( $term_id );
            $guests[] = (object) array
            (
                'ID'           => $term_id,
                'source'       => 'term',
                'user_email'   => isset( $meta['user_email'][0] ) ? $meta['user_email'][0] : '',
                'user_url'     => isset( $meta['user_url'][0] ) ? $meta['user_url'][0] : '',
                'display_name' => $term->name,
                'first_name'   => isset( $meta['first_name'][0] ) ? $meta['first_name'][0] : '',
                'last_name'    => isset( $meta['last_name'][0] ) ? $meta['last_name'][0] : '',
                'description'  => isset( $meta['description'][0] ) ? $meta['description'][0] : '',
                'job_title'    => isset( $meta['job_title'][0] ) ? $meta['job_title'][0] : '',
                'avatar'       => isset( $meta['avatar'][0] ) ? $meta['avatar'][0] : '',
            );
        }

        return $guests;
    }
    public function convert_guest( $item )
    {
        global $wpdb;

        $updating = false;
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT pm.post_id 
                 FROM $wpdb->postmeta pm
                 INNER JOIN $wpdb->posts p ON pm.post_id = p.ID
                 WHERE (
                    (pm.meta_key = %s AND pm.meta_value = %s) OR
                    (pm.meta_key = %s AND pm.meta_value = %s)
                 )
                 AND p.post_type = %s
                 LIMIT 1",
                '_molongui_guest_author_ppma_user_id',
                $item->ID,
                '_molongui_guest_author_ppma_term_id',
                $item->ID,
                'guest_author'
            )
        );

        if ( !$existing )
        {
            $post_id = wp_insert_post( array
            (
                'post_type'      => 'guest_author',
                'post_title'     => $item->display_name,
                'post_name'      => $item->display_name,
                'post_excerpt'   => '',
                'post_content'   => $item->description,
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
                'post_author'    => get_current_user_id(),
                'meta_input'     => array
                (
                    '_molongui_guest_author_display_name' => $item->display_name,
                    '_molongui_guest_author_first_name'   => $item->first_name,
                    '_molongui_guest_author_last_name'    => $item->last_name,
                    '_molongui_guest_author_mail'         => $item->user_email,
                    '_molongui_guest_author_web'          => $item->user_url,
                ),
            ));
        }
        else
        {
            $post_id = wp_update_post( array
            (
                'ID'           => $existing,
                'post_title'   => $item->display_name,
                'post_name'    => $item->display_name,
                'post_content' => $item->description,
                'meta_input'   => array
                (
                    '_molongui_guest_author_display_name' => $item->display_name,
                    '_molongui_guest_author_first_name'   => $item->first_name,
                    '_molongui_guest_author_last_name'    => $item->last_name,
                    '_molongui_guest_author_mail'         => $item->user_email,
                    '_molongui_guest_author_web'          => $item->user_url,
                ),
            ));

            $updating = true;
        }

        if ( is_wp_error( $post_id ) )
        {
            if ( $updating )
            {
                error_log( 'Error updating guest author post: ' . $post_id->get_error_message() );
            }
            else
            {
                error_log( 'Error creating guest author post: ' . $post_id->get_error_message() );
            }

            return;
        }
        update_post_meta( $post_id, '_molongui_guest_author_job', $item->job_title );
        if ( !$updating )
        {
            if ( 'term' == $item->source )
            {
                update_post_meta( $post_id, '_molongui_guest_author_ppma_term_id', $item->ID );
            }
            else
            {
                update_post_meta( $post_id, '_molongui_guest_author_ppma_user_id', $item->ID );
            }
        }
        if ( $item->avatar )
        {
            update_post_meta( $post_id, '_thumbnail_id', $item->avatar );
        }

        if ( !$updating )
        {
            switch ( $item->source )
            {
                case 'user':
                    error_log( sprintf( "*** Migrated guest author with user account #%s. New ID is #%s.", $item->ID, $post_id ) );
                    break;

                case 'term':
                    error_log( sprintf( "*** Migrated guest author without user account #%s. New ID is #%s.", $item->ID, $post_id ) );
                    break;

                default:
                    error_log( sprintf( "*** Migrated guest author. New ID is #%s.", $post_id ) );
                    break;
            }
        }
        else
        {
            error_log( sprintf( "*** Updated guest author with ID #%s.", $post_id ) );
        }
    }
    public function get_posts()
    {
        global $wpdb;

        $supported_post_types = array( 'post' );
        if ( class_exists( 'MultipleAuthors\Classes\Utils' ) )
        {
            $supported_post_types = \MultipleAuthors\Classes\Utils::get_enabled_post_types();
        }
        $supported_post_types = apply_filters( 'molongui_authorship/ppma_supported_post_types', $supported_post_types );
        $placeholders = implode( ',', array_fill( 0, count( $supported_post_types ), '%s' ) );
        return $wpdb->get_col(
            $wpdb->prepare( "
                SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s
                AND p.post_type IN ( $placeholders )
            ", array_merge( array( $this->get_author_tax() ), $supported_post_types ) )
        );
    }
    public function convert_postmeta( $post_id )
    {
        $author_terms = wp_get_object_terms( $post_id, 'author', array( 'orderby' => 'term_order', 'order' => 'ASC' ) );

        if ( !empty( $author_terms ) and !is_wp_error( $author_terms ) and is_array( $author_terms ) )
        {
            $post_authors = array();

            foreach ( $author_terms as $author_term )
            {
                $found = false;
                $author = $this->get_author_by_term( $author_term, 'guest' );

                if ( !empty( $author ) and is_a( $author, 'WP_Post' ) )
                {
                    $post_authors[] = $author;
                    $found = true;
                }
                else
                {
                    $author = $this->get_author_by_term( $author_term, 'user' );

                    if ( !empty( $author ) and is_a( $author, 'WP_User' ) )
                    {
                        $post_authors[] = $author;
                        $found = true;
                    }
                }
                if ( !$found )
                {
                    $this->slug_prefix_pattern = '#^cap\-#';
                    $author = $this->get_author_by_term( $author_term, 'guest' );

                    if ( !empty( $author ) and is_a( $author, 'WP_Post' ) )
                    {
                        $post_authors[] = $author;
                    }
                    else
                    {
                        $author = $this->get_author_by_term( $author_term, 'user' );

                        if ( !empty( $author ) and is_a( $author, 'WP_User' ) )
                        {
                            $post_authors[] = $author;
                        }
                    }

                    $this->slug_prefix_pattern = '';
                }

                /*!
                 * FILTER HOOK
                 * Determines whether source plugin terms should be deleted.
                 *
                 * @param bool  Whether to delete the terms. False by default.
                 * @since 5.1.0
                 */
                if ( apply_filters( 'molongui_authorship/delete_ppma_taxonomies', false ) )
                {
                    wp_remove_object_terms( $post_id, array( $author_term->term_id ), $this->get_author_tax() );
                    if ( empty( get_objects_in_term( $author_term->term_id, $this->get_author_tax() ) ) )
                    {
                        wp_delete_term( $author_term->term_id, $this->get_author_tax() );
                    }
                }
            }

            if ( !empty( $post_authors ) )
            {
                $this->delete_molongui_authorship_meta( $post_id );
                $this->set_post_main_author( $post_id, $post_authors[0]->ID, $this->get_author_type( $post_authors[0] ) );
                foreach ( $post_authors as $post_author )
                {
                    $this->set_post_author( $post_id, $post_author->ID, $this->get_author_type( $post_author ) );
                }

                error_log( "*** Migrated postmeta for post #" . $post_id );
            }
            else
            {
                error_log( "*** Failed postmeta migration for post #" . $post_id );
            }
        }
    }

} // class
new Publishpress_Authors();
