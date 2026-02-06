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
		delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-activated' );
		delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-updated' );
        Settings::delete_job_flags();
	}

} // class
