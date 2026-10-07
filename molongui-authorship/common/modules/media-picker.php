<?php

namespace Molongui\Authorship\Common\Modules;

defined( 'ABSPATH' ) || exit;  

class Media_Picker {

	const CSS_LTR = 'modules/media-picker/assets/css/styles.29ba.min.css';

	const CSS_RTL = 'modules/media-picker/assets/css/styles-rtl.29ba.min.css';

	const JS = 'modules/media-picker/assets/js/scripts.8147.min.js';

	public static function enqueue() {
		self::enqueue_styles();
		self::enqueue_scripts();
	}

	public static function enqueue_styles() {

		$relative_path = is_rtl()
			? self::CSS_RTL
			: self::CSS_LTR
		;

		$url = self::get_common_asset_url( $relative_path );

		if ( empty( $url ) ) {
			return;
		}

		wp_enqueue_style(
			'molongui-media-picker',
			$url,
			array(),
			self::get_common_asset_version( $relative_path )
		);
	}

	public static function enqueue_scripts() {

		$relative_path = self::JS;
		$url           = self::get_common_asset_url( $relative_path );

		if ( empty( $url ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'molongui-media-picker',
			$url,
			array( 'media-editor' ),
			self::get_common_asset_version( $relative_path ),
			true
		);
	}

	private static function get_common_asset_url( $relative_path ) {

		$plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		$common_dir  = trailingslashit( wp_normalize_path( dirname( __DIR__ ) ) );

		if ( 0 !== strpos( $common_dir, $plugins_dir ) ) {
			return '';
		}

		$relative_common_dir = ltrim(
			substr( $common_dir, strlen( $plugins_dir ) ),
			'/'
		);

		return plugins_url(
			$relative_common_dir . ltrim( $relative_path, '/' )
		);
	}

	private static function get_common_asset_version( $relative_path ) {
		$file = trailingslashit( dirname( __DIR__ ) ) . ltrim(
			$relative_path,
			'/'
		);

		if ( ! is_readable( $file ) ) {
			return null;
		}

		return (string) filemtime( $file );
	}
}  
