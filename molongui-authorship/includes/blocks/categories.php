<?php

namespace Molongui\Authorship\Blocks;

defined( 'ABSPATH' ) || exit;  

class Categories {

	const SLUG = 'molongui-authorship';

	public function __construct() {
		add_filter( 'block_categories_all', array( $this, 'register' ) );
	}

	public function register( $categories ) {
		$filtered_categories = array();
		$insert_at           = null;

		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::SLUG === $category['slug'] ) {
				continue;
			}

			$filtered_categories[] = $category;

			if ( isset( $category['slug'] ) && 'design' === $category['slug'] ) {
				$insert_at = count( $filtered_categories );
			}
		}

		$molongui_category = array(
			'slug'  => self::SLUG,
			'title' => __( 'Molongui Authorship', 'molongui-authorship' ),
		);

		if ( is_null( $insert_at ) ) {
			$filtered_categories[] = $molongui_category;
		} else {
			array_splice( $filtered_categories, $insert_at, 0, array( $molongui_category ) );
		}

		return $filtered_categories;
	}
}

new Categories();
