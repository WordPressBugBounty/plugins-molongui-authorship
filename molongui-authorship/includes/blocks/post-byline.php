<?php

namespace Molongui\Authorship\Blocks;

use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Post;

defined( 'ABSPATH' ) || exit;  

class Post_Byline_Block {

	const NAME = 'molongui-authorship/post-byline';

	const MIN_WP_VERSION = '6.3';

	const SCRIPT_HANDLE = 'molongui-authorship-post-byline-block';

	private $javascript = '/assets/js/post-byline-block.07cc.min.js';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
		add_filter( 'authorship/post_byline_block_script_params', array( $this, 'editor_script_params' ) );
	}

	public function register() {
		if ( ! self::is_supported() ) {
			return;
		}

		Assets::register_script(
			MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript,
			'post_byline_block',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			self::SCRIPT_HANDLE
		);

		$args = array(
			'title'           => __( 'Post Byline', 'molongui-authorship' ),
			'description'     => __( 'Displays the Molongui byline for the current post.', 'molongui-authorship' ),
			'keywords'        => array(
				__( 'author', 'molongui-authorship' ),
				__( 'byline', 'molongui-authorship' ),
				__( 'co-author', 'molongui-authorship' ),
			),
			'render_callback' => array( $this, 'render' ),
		);

		register_block_type( MOLONGUI_AUTHORSHIP_DIR . 'blocks/post-byline', $args );
	}

	public static function is_supported() {
		global $wp_version;

		return version_compare( $wp_version, self::MIN_WP_VERSION, '>=' );
	}

	public function editor_script_params( $params ) {
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$params['pro_active']          = '0';
		$params['prefix_placeholder'] = '';
		$params['suffix_placeholder'] = '';
		$params['upgrade_url']        = esc_url_raw(
			add_query_arg(
				array(
					'utm_source'   => 'WordPress',
					'utm_campaign' => 'liteplugin',
					'utm_medium'   => 'block-editor',
					'utm_content'  => 'postBylineBlock',
				),
				MOLONGUI_AUTHORSHIP_WEB
			)
		);

		return $params;
	}

	public function render( $attributes, $content, $block ) {
		unset( $content );

		$post_id = 0;

		if ( $block instanceof \WP_Block && ! empty( $block->context['postId'] ) ) {
			$post_id = absint( $block->context['postId'] );
		}

		if ( empty( $post_id ) ) {
			$post_id = Post::get_id();
		}

		if ( empty( $post_id ) ) {
			return '';
		}

		$byline = \molongui_get_the_author_posts_link( $post_id, null, null );

		if ( '' === $byline ) {
			return '';
		}

		$byline = apply_filters(
			'molongui_authorship/post_byline_block_content',
			$byline,
			is_array( $attributes ) ? $attributes : array(),
			$post_id
		);

		if ( '' === $byline ) {
			return '';
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'wp-block-molongui-authorship-post-byline m-post-byline',
			)
		);

		return sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			wp_kses_post( $byline )
		);
	}
}

new Post_Byline_Block();
