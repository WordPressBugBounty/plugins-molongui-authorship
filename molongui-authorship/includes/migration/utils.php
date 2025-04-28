<?php
/*!
 * Third-Party Data Migration Utilities.
 *
 * Provides helper methods for importing author data from third-party plugins into Molongui Authorship.
 * Handles conversion of external author formats into Molongui's native structure.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
trait Utils
{
    public function get_author_by_term( $term, $author_type = 'user' )
    {
        $author      = null;
        $author_slug = $term->slug;

        if ( !empty( $this->get_prefix() ) )
        {
            $author_slug = preg_replace( $this->get_prefix(), '', $author_slug );
        }

        switch( $author_type )
        {
            case 'guest':
                $author = get_page_by_path( $author_slug, OBJECT, 'guest_author' );
                break;

            case 'user':
            default:
                add_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10, 2 );
                $author = get_user_by( 'slug', $author_slug );
                remove_filter( 'authorship/pre_get_user_by', array( Helpers::class, 'short_circuit' ), 10 );
                break;
        }

        return $author;
    }
    public function delete_molongui_authorship_meta( $post_id )
    {
        $authors_meta_deleted     = delete_post_meta( $post_id, '_molongui_author' );
        $main_author_meta_deleted = delete_post_meta( $post_id, '_molongui_main_author' );

        return ( !empty( $authors_meta_deleted ) and !empty( $main_author_meta_deleted ) );
    }
    public function set_post_main_author( $post_id, $author_id, $author_type )
    {
        $r = add_post_meta( $post_id, '_molongui_main_author', $author_type . '-' . $author_id, false );

        return !empty( $r );
    }
    public function set_post_author( $post_id, $author_id, $author_type )
    {
        $r = add_post_meta( $post_id, '_molongui_author', $author_type . '-' . $author_id, false );

        return !empty( $r );
    }
    public function get_author_type( $object )
    {
        return 'WP_User' === get_class( $object ) ? 'user' : 'guest';
    }
}
