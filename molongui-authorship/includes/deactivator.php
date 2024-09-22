<?php

namespace Molongui\Authorship;
\defined( 'ABSPATH' ) or exit;
class Deactivator
{
    public static function deactivate( $network_wide )
    {
	    if ( \function_exists('is_multisite') and \is_multisite() and $network_wide )
	    {
		    if ( !\is_super_admin() ) return;
		    foreach ( \molongui_get_sites() as $site_id )
		    {
			    \switch_to_blog( $site_id );
				self::deactivate_single_blog();
			    \restore_current_blog();
		    }
	    }
	    else
	    {
		    if ( !\current_user_can( 'activate_plugins' ) ) return;

			self::deactivate_single_blog();
	    }
    }
	private static function deactivate_single_blog()
	{
        global $wpdb;
        \authorship_clear_cache();
		\delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-activated' );
		\delete_transient( MOLONGUI_AUTHORSHIP_NAME.'-updated' );
        \delete_option( 'molongui_authorship_update_post_authors' );
        \delete_option( 'm_update_post_authors_complete' );
        \delete_option( 'm_update_post_authors_running' );
        \delete_option( 'molongui_authorship_update_post_counters' );
        \delete_option( 'm_update_post_counters_complete' );
        \delete_option( 'm_update_post_counters_running' );

        $likes = array
        (
            'm_update_post_authors_batch_%',
            'm_update_post_counters_batch_%',
            'molongui_authorship_add_author_error_%',
            'molongui_authorship_add_author_input_%',
        );
        foreach( $likes as $like )
        {
            $wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '{$like}';" );
        }
	}

} // class