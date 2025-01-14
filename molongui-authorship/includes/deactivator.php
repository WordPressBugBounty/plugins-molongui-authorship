<?php
/*!
 * Handles the deactivation process for Molongui Authorship plugin.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes
 * @since      1.0.0
 */

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Cache;
use Molongui\Authorship\Common\Utils\WP;

defined( 'ABSPATH' ) or exit; // Exit if accessed directly
class Deactivator
{
    public static function deactivate( $network_wide )
    {
	    if ( function_exists('is_multisite') and is_multisite() and $network_wide )
	    {
		    if ( !is_super_admin() ) return;
		    foreach ( WP::get_sites() as $site_id )
		    {
			    switch_to_blog( $site_id );
				self::deactivate_single_blog();
			    restore_current_blog();
		    }
	    }
	    else
	    {
		    if ( !current_user_can( 'activate_plugins' ) ) return;

			self::deactivate_single_blog();
	    }
    }
	private static function deactivate_single_blog()
	{
        global $wpdb;
        Cache::clear( 'posts' );
        Cache::clear( 'users' );
        Cache::clear( 'guests' );
		delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-activated' );
		delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-updated' );
        delete_option( 'molongui_authorship_update_post_authors' );
        delete_option( 'molongui_authorship_update_post_authorship_complete' );
        delete_option( 'molongui_authorship_update_post_authorship_running' );
        delete_option( 'molongui_authorship_update_post_counters' );
        delete_option( 'molongui_authorship_update_posts_count_complete' );
        delete_option( 'molongui_authorship_update_posts_count_running' );

        $likes = array
        (
            'molongui_authorship_update_post_authors_batch_%',
            'molongui_authorship_update_post_authorship_batch_%',
            'molongui_authorship_add_author_error_%',
            'molongui_authorship_add_author_input_%',
        );
        foreach( $likes as $like )
        {
            $wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '{$like}';" );
        }
	}

} // class