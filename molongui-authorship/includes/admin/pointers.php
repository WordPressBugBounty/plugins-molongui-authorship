<?php
/*!
 * Provides the functionality for displaying admin hints and pointers.
 *
 * This file defines the logic for adding helpful hints and pointers in the WordPress admin interface, making it easier
 * for users to navigate and understand the plugin's settings and features.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      5.0.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Common\Modules\PointerPlus;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  

class Pointers {

	public function __construct() {
		$pointerplus = new PointerPlus( array( 'prefix' => 'molongui-authorship' ) );


		add_filter( 'molongui-authorship-pointerplus_list', array( $this, 'add_pointers' ), 10, 2 );
	}

	public function add_pointers( $pointers, $prefix ) {
		if ( apply_filters( 'molongui_authorship/show_pointers', true ) ) {
			$options = Settings::get();
			$box     = ( ! empty( $options['author_box_enabled'] ) && ! empty( $options['dashboard_appearance_submenu'] ) );

			if ( apply_filters( 'molongui_authorship/show_global_pointers', true ) ) {
				if ( Settings::get( 'dashboard_settings_enabled', true ) && Settings::get( 'dashboard_authors_menu', true ) ) {
					$pointers = array_merge(
						$pointers,
						array(
							$prefix . '_settings' => array(
								'selector'   => '#toplevel_page_authors',
								'title'      => __( 'Molongui Authorship', 'molongui-authorship' ),
								// translators: %1$s: <strong>. %2$s: </strong>.
								'text'       => sprintf( __( 'Now you can %1$smanage all your authors%2$s from this menu. Add new ones and configure the plugin to make it work like you want it to.', 'molongui-authorship' ), '<strong>', '</strong>' ),
								'icon_class' => 'dashicons-admin-settings',
								'width'      => 300,
								'next'       => ( $box ? $prefix . '_box' : $prefix . '_users' ),
							),
						)
					);
				}

				if ( Settings::is_enabled( 'author-box' ) ) {
					$pointers = array_merge(
						$pointers,
						array(
							$prefix . '_box' => array(
								'selector'   => '#menu-appearance',
								'title'      => __( 'Author Box Customization', 'molongui-authorship' ),
								// translators: %1$s: <strong>. %2$s: </strong>.
								'text'       => sprintf( __( 'Go to %1$sAppearance > Author Box%2$s. There you will find all the settings you need to customize your author boxes.', 'molongui-authorship' ), '<strong>', '</strong>' ),
								'icon_class' => 'dashicons-admin-customizer',
								'width'      => 300,
								'next'       => $prefix . '_users',
							),
						)
					);
				}

				$pointers = array_merge(
					$pointers,
					array(
						$prefix . '_users' => array(
							'selector'   => '#menu-users',
							'title'      => __( 'Additional Profile Fields', 'molongui-authorship' ),
							// translators: %1$s: <code>. %2$s: </code>. %3$s: <code>. %4$s: </code>. %5$s: <code>. %6$s: </code>.
							'text'       => sprintf( __( 'Additional profile fields (like %1$scustom avatar%2$s, %3$scompany%4$s, %5$sphone%6$s and many more) have been added to the user-edit screen', 'molongui-authorship' ), '<code>', '</code>', '<code>', '</code>', '<code>', '</code>' ),
							'icon_class' => 'dashicons-admin-users',
							'width'      => 300,
							'next'       => '',
						),
					)
				);

				if ( Settings::is_enabled( 'guest-author' ) ) {
					$pointers = array_merge(
						$pointers,
						array(
							$prefix . '_guests' => array(
								'selector'   => '#menu-posts-guest_author',
								'title'      => __( 'Guest Authors', 'molongui-authorship' ),
								'text'       => __( 'Here you can add guest authors so you can credit guest contributions without creating a WordPress user account for them.', 'molongui-authorship' ),
								'icon_class' => 'dashicons-admin-users',
								'width'      => 300,
								'next'       => '',
							),
						)
					);
				}
			}

			if ( apply_filters( 'molongui_authorship/show_edit_post_pointers', false ) ) {
				$pointers = array_merge(
					$pointers,
					array(
						$prefix . '_old_author' => array(
							'selector'   => '.post-author-selector',
							'title'      => __( 'Molongui Authorship Settings', 'molongui-authorship' ),
							'text'       => __( 'Here you can handle your authors, add new ones and configure the plugin to make it work like you want it to.', 'molongui-authorship' ),
							'icon_class' => 'dashicons-admin-users',
							'width'      => 300,
							'next'       => '',
						),
					)
				);
			}
		}

		return $pointers;
	}
}  

new Pointers();
