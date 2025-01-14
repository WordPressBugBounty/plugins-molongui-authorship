<?php

use Molongui\Authorship\Common\Utils\WP;
defined( 'WP_UNINSTALL_PLUGIN' ) or exit;
if ( function_exists( 'is_multisite' ) and is_multisite() )
{
	foreach ( WP::get_sites() as $site_id )
	{
		switch_to_blog( $site_id );
		molongui_authorship_uninstall_single_site();
		restore_current_blog();
	}
}
else
{
	molongui_authorship_uninstall_single_site();
}
function molongui_authorship_uninstall_single_site()
{
	global $wpdb;

    $plugin_name   = 'molongui-authorship';
    $plugin_prefix = 'molongui_authorship';
    $options       = get_option( $plugin_prefix.'_options' );

    if ( isset( $options['uninstall'] ) and is_string( $options['uninstall'] ) )
    {
        $uninstall = explode( ',', $options['uninstall'] );
    }
    else
    {
        return;
    }
    if ( in_array( 'config', $uninstall ) )
	{
        $like = $plugin_prefix.'_%';
        $wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '{$like}';" );
	}
    if ( in_array( 'data', $uninstall ) )
	{
		$ids = $wpdb->get_results
        (
        "SELECT ID
                FROM {$wpdb->prefix}posts
                WHERE post_type LIKE 'guest_author'
                ",
                ARRAY_A
		);
		if ( !empty( $ids ) )
        {
            $postids = '';
            foreach ( $ids as $key => $id )
            {
                if ( $key == 0 ) $postids = $id['ID'];
                else $postids = $postids . ', ' . $id['ID'];
            }
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE post_id IN ( $postids );" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}posts WHERE ID IN ( $postids );" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_author';" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_main_author';" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '%_molongui_guest_author%';" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_author_box_display';" );
            $wpdb->query( "DELETE FROM {$wpdb->prefix}postmeta WHERE meta_key = '_molongui_author_box_position';" );
        }
	}
    $like = '_transient_'.$plugin_name.'%';
	$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '{$like}';" );
}
