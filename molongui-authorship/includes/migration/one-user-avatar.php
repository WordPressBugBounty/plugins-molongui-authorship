<?php
/*!
 * Migration tool for One User Avatar plugin data into Molongui Authorship.
 *
 * This file handles retrieving, converting, and optionally cleaning up user avatar metadata from the One User Avatar
 * plugin to integrate with Molongui Authorship.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/migration
 * @since      5.1.0
 */

namespace Molongui\Authorship\Migration;

use Molongui\Authorship\Common\Utils\Helpers;
use Molongui\Authorship\Common\Utils\Singleton;
class One_User_Avatar
{
    protected $name = 'One User Avatar';
    protected $path = 'one-user-avatar/one-user-avatar.php';
    protected $id = 'oua';
    protected $postmeta = '_wp_attachment_wp_user_avatar';
    use Utils;
    use Singleton;
    public function __construct()
    {
        if ( apply_filters( 'molongui_authorship/enable_one_user_avatar_migration', true ) )
        {
            if ( is_admin() )
            {
                require_once 'one-user-avatar/cron.php';
            }
            if ( defined( 'WP_CLI' ) && WP_CLI )
            {
                add_filter( 'molongui_authorship/migrate_one_user_avatar', '__return_true' );
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
    public function get_postmeta_key()
    {
        return $this->postmeta;
    }
    public function get_user_attachment_relationships()
    {
        global $wpdb;

        return $wpdb->get_results( "
            SELECT post_id, meta_value 
            FROM $wpdb->postmeta 
            WHERE meta_key = '_wp_attachment_wp_user_avatar'
        ", ARRAY_A );
    }
    public function update_user_metadata( $meta )
    {
        $user_id       = $meta['user_id'];
        $attachment_id = $meta['attachment_id'];

        if ( empty( $user_id ) or empty( $attachment_id ) )
        {
            return;
        }

        update_user_meta( $user_id, 'molongui_author_image_id', $attachment_id );
        update_user_meta( $user_id, 'molongui_author_image_url', Helpers::get_attachment_image_url( $attachment_id ) );
        update_user_meta( $user_id, 'molongui_author_image_edit', Helpers::get_attachment_edit_url( $attachment_id ) );

        error_log( sprintf( "*** Migrated custom avatar for user #%s.", $user_id ) );
        if ( apply_filters( 'molongui_authorship/delete_oua_meta', false ) )
        {
            global $wpdb, $blog_id;
            $wpdb->delete(
                $wpdb->postmeta,
                array
                (
                    'meta_key'   => $this->get_postmeta_key(),
                    'meta_value' => $user_id
                ),
                array
                (
                    '%s', // data type for meta_key
                    '%d'  // data type for meta_value (if $user_id is numeric)
                )
            );
            $wpdb->delete(
                $wpdb->usermeta,
                array
                (
                    'user_id'  => $user_id,
                    'meta_key' => $wpdb->prepare( $wpdb->get_blog_prefix( $blog_id ) . '%s', 'user_avatar' )
                ),
                array
                (
                    '%d', // data type for 'user_id'
                    '%s'  // data type for 'meta_key'
                )
            );
        }
    }

}// class
new One_User_Avatar();
