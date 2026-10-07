<?php

namespace Molongui\Authorship\Blocks;

use Molongui\Authorship\Author_Box;
use Molongui\Authorship\Common\Utils\Assets;
use Molongui\Authorship\Post;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  

class Author_Box_Block {

	const NAME = 'molongui-authorship/author-box';

	const MIN_WP_VERSION = '6.3';

	const SCRIPT_HANDLE = 'molongui-authorship-author-box-block';

	private $javascript = '/assets/js/author-box-block.41b8.min.js';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	public function register() {
		if ( ! self::is_supported() ) {
			return;
		}

		Assets::register_script(
			MOLONGUI_AUTHORSHIP_FOLDER . $this->javascript,
			'author_box_block',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ),
			self::SCRIPT_HANDLE
		);

		$enabled = Settings::is_enabled( 'author-box' );

		if ( $enabled ) {
			Author_Box::instance()->register_styles();

			if ( is_admin() ) {
				wp_add_inline_style( 'molongui-authorship-box', Author_Box::instance()->extra_styles() );
			}
		}

		$args = array(
			'title'           => __( 'Author Box', 'molongui-authorship' ),
			'description'     => __( 'Displays the Molongui author box for the current post.', 'molongui-authorship' ),
			'keywords'        => array(
				__( 'author', 'molongui-authorship' ),
				__( 'bio', 'molongui-authorship' ),
				__( 'profile', 'molongui-authorship' ),
			),
			'render_callback' => array( $this, 'render' ),
		);

		if ( ! $enabled ) {
			$args['supports'] = array(
				'html'            => false,
				'multiple'        => false,
				'className'       => false,
				'customClassName' => false,
				'inserter'        => false,
			);
		}

		register_block_type( MOLONGUI_AUTHORSHIP_DIR . 'blocks/author-box', $args );
	}

	public static function is_supported() {
		global $wp_version;

		return version_compare( $wp_version, self::MIN_WP_VERSION, '>=' );
	}

	public function render( $attributes, $content, $block ) {
		unset( $attributes, $content );

		if ( ! Settings::is_enabled( 'author-box' ) ) {
			return '';
		}

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

		return Author_Box::markup( null, array(), $post_id );
	}

	public static function is_manually_placed( $post_id = null ) {
		if ( ! self::is_supported() ) {
			return false;
		}

		$post_id = Post::get_id( $post_id );

		if ( ! empty( $post_id ) ) {
			$post_content = get_post_field( 'post_content', $post_id );

			if ( is_string( $post_content ) && has_block( self::NAME, $post_content ) ) {
				return true;
			}
		}

		if ( ! wp_is_block_theme() ) {
			return false;
		}

		global $_wp_current_template_content;

		return is_string( $_wp_current_template_content )
			&& '' !== $_wp_current_template_content
			&& has_block( self::NAME, $_wp_current_template_content );
	}
}

new Author_Box_Block();
