<?php
/*!
 * Co-Authors Plus migration module for Molongui Authorship.
 *
 * Provides functionality to migrate guest authors and authorship metadata from Co-Authors Plus
 * into Molongui's native authorship system.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Utils\Singleton;
class Co_Authors_Plus
{
    protected $name = 'Co-Authors Plus';
    protected $path = 'co-authors-plus/co-authors-plus.php';
    protected $id = 'cap';
    public $author_tax = 'author';
    public $guest_post_type = 'guest-author';
    public $slug_prefix_pattern = '#^cap\-#';
    use Utils;
    use Singleton;
    public function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_coauthors_plus_migration', true ) )
        {
            if ( is_admin() )
            {
                require_once 'co-authors-plus/cron.php';
            }
            if ( defined( 'WP_CLI' ) && WP_CLI )
            {
                add_filter( 'molongui_authorship/migrate_coauthors_plus', '__return_true' );
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
    public function get_guest_post_type()
    {
        return $this->guest_post_type;
    }
    public function get_prefix()
    {
        return $this->slug_prefix_pattern;
    }
    public function get_guest_authors()
    {
        global $wpdb;
        return $wpdb->get_col(
            $wpdb->prepare("
                SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
            ", $this->get_guest_post_type() )
        );
    }
    public function convert_guest( $post_id )
    {
        global $wpdb;
        $post_id = intval( $post_id );
        $wpdb->query( 'START TRANSACTION' );
        try
        {
            $wpdb->update(
                $wpdb->posts,
                array( 'post_type' => 'guest_author' ), // New post_type value
                array( 'ID' => $post_id ), // WHERE condition
                array( '%s' ), // Data format
                array( '%d' )  // WHERE condition format
            );
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->posts} 
                 SET post_name = REPLACE(post_name, 'cap-', '') 
                 WHERE ID = %d AND post_name LIKE 'cap-%%'",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 SET p.post_content = pm.meta_value
                 WHERE pm.meta_key = 'cap-description' AND p.ID = %d",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta}
                WHERE post_id = %d AND meta_key IN ('cap-description', 'cap-user_login', 'cap-linked_account')",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_key = REPLACE(meta_key, 'cap-', '_molongui_guest_author_')
                 WHERE post_id = %d AND meta_key LIKE 'cap-%%'",
                $post_id
            ));
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_key = CASE
                    WHEN meta_key = '_molongui_guest_author_user_email' THEN '_molongui_guest_author_mail'
                    WHEN meta_key = '_molongui_guest_author_website' THEN '_molongui_guest_author_web'
                 END
                 WHERE post_id = %d AND meta_key IN ('_molongui_guest_author_user_email', '_molongui_guest_author_website')",
                $post_id
            ));
            $wpdb->query( 'COMMIT' );

            error_log( sprintf( "*** Migrated guest author #%s.", $post_id ) );

        }
        catch ( \Exception $e )
        {
            $wpdb->query( 'ROLLBACK' );
            error_log( 'Error updating guest author #' . $post_id . ': ' . $e->getMessage() );
        }
    }
    public function get_posts()
    {
        global $wpdb;

        $supported_post_types = array( 'post' );
        if ( isset( $GLOBALS['coauthors_plus'] ) )
        {
            global $coauthors_plus;
            $supported_post_types = $coauthors_plus->supported_post_types();
        }
        $supported_post_types = apply_filters( 'molongui_authorship/cap_supported_post_types', $supported_post_types );
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
                if ( apply_filters( 'molongui_authorship/delete_cap_taxonomies', false ) )
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
new Co_Authors_Plus();
