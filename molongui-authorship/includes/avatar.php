<?php

namespace Molongui\Authorship;

use Molongui\Authorship\Common\Utils\Helpers;

defined( 'ABSPATH' ) || exit;  

final class Avatar {

	private $author;

	public function __construct( Author $author ) {
		$this->author = $author;
	}

	public function get( $size = 'full', $context = 'screen', $source = null, $default = null, $strict_fallback = false ) {
		$resolution = $this->get_resolution( $size, $context, $source, $default, $strict_fallback );

		return $resolution['value'];
	}

	public function get_resolution( $size = 'full', $context = 'screen', $source = null, $default = null, $strict_fallback = false ) {
		$options = Settings::get();

		$size    = apply_filters( 'authorship/get_avatar/size', $size, $options );
		$context = apply_filters( 'authorship/get_avatar/context', $context, $options );
		$source  = apply_filters( 'authorship/get_avatar/source', $source, $options );
		$default = apply_filters( 'authorship/get_avatar/default', $default, $options );

		$dimensions = $this->resolve_size( $size, $context, $options );
		$width      = $dimensions['width'];
		$height     = $dimensions['height'];
		$image_size = $dimensions['size'];

		$attr = array(
			'width'  => $width,
			'height' => $height,
		);

		if ( ! empty( $options['seo_settings_enabled'] ) && ! empty( $options['schema_markup_enabled'] ) ) {
			$attr['itemprop'] = 'image';
		}

		$attr = apply_filters(
			'molongui_authorship/author_avatar_attr',
			$attr,
			$this->author->get_id(),
			$this->author->get_type(),
			array( $width, $height ),
			$context,
			$this->author
		);

		$explicit_source = ! empty( $source );

		if ( empty( $source ) ) {
			$source = ( 'box' === $context )
				? ( ! empty( $options['author_box_avatar_source'] ) ? $options['author_box_avatar_source'] : 'local' )
				: 'auto';
		}

		$avatar_fallback = $default;
		if ( empty( $avatar_fallback ) && ( 'box' === $context || $explicit_source ) && ! empty( $options['author_box_avatar_fallback'] ) ) {
			$avatar_fallback = $options['author_box_avatar_fallback'];
		}
		if ( empty( $avatar_fallback ) ) {
			$avatar_fallback = 'none';
		}

		$label  = 'avatar:' . $context . ':' . $source . ':' . $avatar_fallback . ':' . $width . 'x' . $height;
		$label .= ':strict-' . ( $strict_fallback ? '1' : '0' );
		$label .= ':local-' . ( Settings::is_enabled( 'local-avatar' ) ? '1' : '0' );
		$label .= ':gravatar-' . ( Settings::is_enabled( 'gravatar' ) ? '1' : '0' );
		$label .= ':default-' . ( self::has_custom_default() ? (string) absint( Settings::get( 'default_avatar_id', 0 ) ) : '0' );
		$key = 'computed:' . $label;

		if ( has_filter( 'molongui_authorship/author_avatar_attr' ) ) {
			$key .= ':attr-' . md5( wp_json_encode( $attr ) );
		}

		$segment = 'author-' . $this->author->get_type() . ':' . $this->author->get_id();
		$cache   = Author::request_cache();

		return $cache->remember(
			$segment,
			$key,
			function () use ( $attr, $options, $image_size, $context, $source, $avatar_fallback, $default, $strict_fallback, $width, $height, $label ) {
				$precomputed = apply_filters( 'molongui_authorship/pre_compute_value', null, $label, $this->author );

				if ( null !== $precomputed ) {
					return array(
						'value'            => ( null === $precomputed || false === $precomputed ) ? '' : $precomputed,
						'source'           => 'filtered',
						'browser_fallback' => '',
					);
				}

				$result = $this->resolve_avatar( $image_size, $context, $attr, $options, $source, $avatar_fallback, $default, $strict_fallback );

				$result['value'] = apply_filters(
					'molongui_authorship/get_author_avatar',
					$result['value'],
					$this->author->get_id(),
					$this->author->get_type(),
					array( $width, $height ),
					$context,
					$this->author
				);

				return $result;
			}
		);
	}

	private function resolve_avatar( $resolved_size, $context, $attr, $options, $source, $fallback, $remote_default = '', $strict_fallback = false ) {
		switch ( $source ) {
			case 'auto':
				if ( $strict_fallback && 'gravatar' === $remote_default ) {
					$remote_default = '';
				}

				return $this->resolve_global_avatar( $resolved_size, $context, $attr, $options, $remote_default );

			case 'gravatar':
				if ( Settings::is_enabled( 'gravatar' ) ) {
					if ( $strict_fallback ) {
						return $this->resolve_strict_gravatar( $resolved_size, $context, $attr, $options, $fallback );
					}

					$use_box_default = ( 'box' === $context || 'none' !== $fallback );

					return $this->resolve_gravatar( $resolved_size, $context, $attr, $options, $use_box_default, $remote_default );
				}

				if ( $strict_fallback ) {
					if ( 'acronym' === $fallback && 'url' !== $context ) {
						return array(
							'value'            => $this->get_acronym( $attr, $options ),
							'source'           => 'acronym',
							'browser_fallback' => '',
						);
					}

					if ( ( 'gravatar' === $fallback || '' === $fallback ) && self::has_custom_default() ) {
						return $this->custom_default_resolution( $resolved_size, $context, $attr );
					}

					return $this->empty_resolution();
				}

				if ( self::has_custom_default() ) {
					return $this->custom_default_resolution( $resolved_size, $context, $attr );
				}

				return $this->empty_resolution();

			case 'acronym':
				if ( 'url' === $context ) {
					return $this->empty_resolution();
				}

				return array(
					'value'            => $this->get_acronym( $attr, $options ),
					'source'           => 'acronym',
					'browser_fallback' => '',
				);

			case 'local':
			default:
				if ( Settings::is_enabled( 'local-avatar' ) ) {
					$local = $this->get_local_avatar( $resolved_size, $context, $attr );
					if ( '' !== $local ) {
						return array(
							'value'            => $local,
							'source'           => 'local',
							'browser_fallback' => '',
						);
					}
				}

				return $this->resolve_explicit_fallback(
					$fallback,
					$resolved_size,
					$context,
					$attr,
					$options,
					! $strict_fallback
				);
		}
	}

	private function resolve_global_avatar( $resolved_size, $context, $attr, $options, $remote_default = '' ) {
		if ( Settings::is_enabled( 'local-avatar' ) ) {
			$local = $this->get_local_avatar( $resolved_size, $context, $attr );
			if ( '' !== $local ) {
				return array(
					'value'            => $local,
					'source'           => 'local',
					'browser_fallback' => '',
				);
			}
		}

		if ( Settings::is_enabled( 'gravatar' ) ) {
			return $this->resolve_gravatar( $resolved_size, $context, $attr, $options, false, $remote_default );
		}

		if ( self::has_custom_default() ) {
			return $this->custom_default_resolution( $resolved_size, $context, $attr );
		}

		return $this->empty_resolution();
	}

	private function resolve_explicit_fallback( $fallback, $resolved_size, $context, $attr, $options, $use_box_default = true ) {
		if ( 'gravatar' === $fallback ) {
			if ( Settings::is_enabled( 'gravatar' ) ) {
				return $this->resolve_gravatar( $resolved_size, $context, $attr, $options, $use_box_default );
			}

			if ( self::has_custom_default() ) {
				return $this->custom_default_resolution( $resolved_size, $context, $attr );
			}
		}

		if ( 'acronym' === $fallback && 'url' !== $context ) {
			return array(
				'value'            => $this->get_acronym( $attr, $options ),
				'source'           => 'acronym',
				'browser_fallback' => '',
			);
		}

		return $this->empty_resolution();
	}

	private function resolve_strict_gravatar( $resolved_size, $context, $attr, $options, $fallback ) {
		if ( 'gravatar' === $fallback || '' === $fallback ) {
			return $this->resolve_gravatar( $resolved_size, $context, $attr, $options, false );
		}

		if ( ! $this->has_valid_gravatar_email() ) {
			if ( 'acronym' === $fallback && 'url' !== $context ) {
				return array(
					'value'            => $this->get_acronym( $attr, $options ),
					'source'           => 'acronym',
					'browser_fallback' => '',
				);
			}

			return $this->empty_resolution();
		}

		$browser_fallback_html = '';
		$hide_on_error         = false;

		switch ( $fallback ) {
			case 'none':
				$default       = '404';
				$hide_on_error = true;
				break;

			case 'acronym':
				$default = '404';

				if ( 'url' !== $context ) {
					$browser_fallback_html = $this->get_acronym( $attr, $options );
				}
				break;

			default:
				$default = $fallback;
				break;
		}

		$value = $this->get_gravatar_output(
			$resolved_size,
			$context,
			$attr,
			$options,
			$default,
			'',
			$browser_fallback_html,
			$hide_on_error
		);

		return array(
			'value'            => $value,
			'source'           => '' !== $value ? 'gravatar' : 'none',
			'browser_fallback' => '',
		);
	}

	private function resolve_gravatar( $resolved_size, $context, $attr, $options, $use_box_default, $remote_default = '' ) {
		$browser_fallback = '';

		if ( ! $this->has_valid_gravatar_email() ) {
			if ( self::has_custom_default() ) {
				return $this->custom_default_resolution( $resolved_size, $context, $attr );
			}

			return $this->empty_resolution();
		}

		if ( self::has_custom_default() ) {
			$default          = '404';
			$browser_fallback = $this->get_custom_default_avatar_url( $resolved_size );
		} elseif ( $use_box_default && ! empty( $options['author_box_avatar_default_gravatar'] ) ) {
			$default = $options['author_box_avatar_default_gravatar'];
		} elseif ( ! empty( $remote_default ) ) {
			$default = $remote_default;
		} else {
			$default = get_option( 'avatar_default', 'mystery' );
		}

		$value = $this->get_gravatar_output( $resolved_size, $context, $attr, $options, $default, $browser_fallback );

		return array(
			'value'            => $value,
			'source'           => '' !== $value ? 'gravatar' : 'none',
			'browser_fallback' => $browser_fallback,
		);
	}

	private function has_valid_gravatar_email() {
		$email = $this->author->get_email();

		return ! empty( $email ) && (bool) is_email( $email );
	}

	private function get_local_avatar( $resolved_size, $context, $attr ) {
		$type = $this->author->get_type();
		$id   = $this->author->get_id();

		if ( 'dummy' === $type ) {
			if ( 'url' === $context ) {
				return '';
			}

			return $this->author->get_seeded_avatar();
		}

		if ( 'user' === $type ) {
			$image_id = absint( get_user_meta( $id, 'molongui_author_image_id', true ) );
			if ( ! $image_id ) {
				return '';
			}

			if ( 'url' === $context ) {
				$url = wp_get_attachment_image_url( $image_id, $resolved_size );
				if ( ! $url ) {
					$url = wp_get_attachment_url( $image_id );
				}

				return $url ? $url : '';
			}

			$image = wp_get_attachment_image( $image_id, $resolved_size, false, $attr );
			return $image ? $image : '';
		}

		if ( 'guest' === $type && has_post_thumbnail( $id ) ) {
			if ( 'url' === $context ) {
				$url = get_the_post_thumbnail_url( $id, $resolved_size );
				return $url ? $url : '';
			}

			$image = get_the_post_thumbnail( $id, $resolved_size, $attr );
			return $image ? $image : '';
		}

		return '';
	}

	private function get_custom_default_avatar( $resolved_size, $context, $attr ) {
		if ( ! self::has_custom_default() ) {
			return '';
		}

		$image_id = absint( Settings::get( 'default_avatar_id', 0 ) );

		if ( 'url' === $context ) {
			$url = wp_get_attachment_image_url( $image_id, $resolved_size );
			if ( ! $url ) {
				$url = wp_get_attachment_url( $image_id );
			}

			return $url ? $url : '';
		}

		$image = wp_get_attachment_image( $image_id, $resolved_size, false, $attr );
		return $image ? $image : '';
	}

	public function get_custom_default_avatar_url( $resolved_size ) {
		return $this->get_custom_default_avatar( $resolved_size, 'url', array() );
	}

	public static function has_custom_default() {
		$image_id = absint( Settings::get( 'default_avatar_id', 0 ) );

		return Settings::is_enabled( 'default-avatar' ) && $image_id && wp_attachment_is_image( $image_id );
	}

	private function get_gravatar_output( $resolved_size, $context, $attr, $options, $default, $browser_fallback = '', $browser_fallback_html = '', $hide_on_error = false ) {
		if ( 'url' !== $context ) {
			if ( $browser_fallback ) {
				$attr['extra_attr'] = $this->append_browser_fallback_attribute(
					isset( $attr['extra_attr'] ) ? $attr['extra_attr'] : '',
					$browser_fallback
				);
			} elseif ( $browser_fallback_html ) {
				$attr['extra_attr'] = $this->append_browser_fallback_html_attribute(
					isset( $attr['extra_attr'] ) ? $attr['extra_attr'] : '',
					$browser_fallback_html
				);
			} elseif ( $hide_on_error ) {
				$attr['extra_attr'] = $this->append_browser_hide_on_error_attribute(
					isset( $attr['extra_attr'] ) ? $attr['extra_attr'] : ''
				);
			}

			if ( $browser_fallback || $browser_fallback_html || $hide_on_error ) {
				do_action( 'molongui_authorship/avatar_browser_fallback_required' );
			}

			return $this->get_gravatar( $attr, $options, $default );
		}

		$size = get_option( 'thumbnail_size_w', 96 );
		if ( is_array( $resolved_size ) ) {
			$size = min( max( 1, (int) $resolved_size[0] ), max( 1, (int) $resolved_size[1] ) );
		} elseif ( ! empty( $attr['width'] ) && ! empty( $attr['height'] ) ) {
			$size = min( (int) $attr['width'], (int) $attr['height'] );
		}

		add_filter( 'authorship/get_avatar_data/skip', '__return_true' );
		$url = get_avatar_url(
			$this->author->get_email(),
			array(
				'size'    => $size,
				'default' => $default,
			)
		);
		remove_filter( 'authorship/get_avatar_data/skip', '__return_true' );

		if ( $url && '404' === (string) $default ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );

			if ( $host && preg_match( '/(^|\.)gravatar\.com$/i', $host ) ) {
				$url = add_query_arg( 'd', '404', $url );
			}
		}

		return $url ? $url : '';
	}

	public function get_gravatar( $attr, $options = array(), $default = null ) {
		if ( empty( $options ) ) {
			$options = Settings::get();
		}

		$attr['force_display'] = true;

		$size  = get_option( 'thumbnail_size_w', 96 );
		$has_w = ! empty( $attr['width'] );
		$has_h = ! empty( $attr['height'] );

		if ( $has_w && $has_h ) {
			$size = min( $attr['width'], $attr['height'] );
		} elseif ( $has_w ) {
			$size = $attr['width'];
		} elseif ( $has_h ) {
			$size = $attr['height'];
		}

		$extra_attr = isset( $attr['extra_attr'] ) ? trim( $attr['extra_attr'] ) : '';
		$attr['extra_attr'] = $extra_attr;

		if ( ! empty( Settings::get( 'seo_settings_enabled' ) ) && ! empty( Settings::get( 'schema_markup_enabled' ) ) ) {
			$attr['extra_attr'] = trim( $attr['extra_attr'] . ' itemprop="image"' );
		}

		if ( null === $default ) {
			$default = ! empty( $options['author_box_avatar_default_gravatar'] )
				? $options['author_box_avatar_default_gravatar']
				: get_option( 'avatar_default', 'mystery' );
		}

		if ( 'random' === $default ) {
			$defaults = array( 'mp', 'identicon', 'monsterid', 'wavatar', 'retro', 'robohash', 'blank' );
			$default  = $defaults[ array_rand( $defaults ) ];
		}

		add_filter( 'authorship/get_avatar_data/skip', '__return_true' );
		$gravatar = get_avatar( $this->author->get_email(), $size, $default, false, $attr );
		remove_filter( 'authorship/get_avatar_data/skip', '__return_true' );

		return $gravatar ? $gravatar : '';
	}

	public function get_acronym( $attr, $options = array() ) {
		$name = $this->author->get_full_name();

		if ( '' === $name ) {
			$name = $this->author->get_base_display_name();
		}

		if ( empty( $name ) ) {
			return '';
		}

		if ( empty( $options ) ) {
			$options = Settings::get();
		}

		$class  = empty( $attr['class'] ) ? '' : $attr['class'];
		$style  = empty( $attr['style'] ) ? '' : $attr['style'];
		$width  = empty( $attr['width'] ) ? '' : ' width:' . $attr['width'] . 'px;';
		$height = empty( $attr['height'] ) ? '' : ' height:' . $attr['height'] . 'px;';

		$html  = '<div data-avatar-type="acronym" class="' . $class . ' acronym-container" style="' . $style . $width . $height . '">';
		$html .= '<div>';
		$html .= Helpers::get_acronym( $name );
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}

	public function has_local_avatar( $validate = false ) {
		$id   = $this->author->get_id();
		$type = $this->author->get_type();

		if ( ! Settings::is_enabled( 'local-avatar' ) ) {
			return (bool) apply_filters( 'molongui_authorship/author/has_local_avatar', false, $id, $type, $validate, $this->author );
		}

		$segment = 'author-' . $type . ':' . $id;
		$key     = 'computed:has_local_avatar:' . ( $validate ? '1' : '0' );
		$cache   = Author::request_cache();

		return (bool) $cache->remember(
			$segment,
			$key,
			function () use ( $id, $type, $validate ) {
				$result = false;

				if ( 'user' === $type ) {
					$image_id = (int) get_user_meta( $id, 'molongui_author_image_id', true );
					if ( $image_id > 0 ) {
						$result = ! $validate || ! empty( wp_get_attachment_url( $image_id ) );
					}
				} elseif ( 'guest' === $type && has_post_thumbnail( $id ) ) {
					if ( ! $validate ) {
						$result = true;
					} else {
						$thumbnail_id = (int) get_post_thumbnail_id( $id );
						$result       = $thumbnail_id > 0 && ! empty( wp_get_attachment_url( $thumbnail_id ) );
					}
				}

				return (bool) apply_filters( 'molongui_authorship/author_has_local_avatar', $result, $id, $type, $validate, $this->author );
			}
		);
	}

	public function append_browser_fallback_attribute( $extra_attr, $fallback ) {
		if ( empty( $fallback ) ) {
			return (string) $extra_attr;
		}

		$attribute = sprintf( 'data-molongui-avatar-fallback="%s"', esc_url( $fallback ) );

		return trim( trim( (string) $extra_attr ) . ' ' . $attribute );
	}

	private function append_browser_fallback_html_attribute( $extra_attr, $fallback ) {
		$attribute = sprintf( 'data-molongui-avatar-fallback-html="%s"', esc_attr( $fallback ) );

		return trim( trim( (string) $extra_attr ) . ' ' . $attribute );
	}

	private function append_browser_hide_on_error_attribute( $extra_attr ) {
		return trim( trim( (string) $extra_attr ) . ' data-molongui-avatar-hide-on-error="1"' );
	}

	private function resolve_size( $size, $context, $options ) {
		if ( is_array( $size ) ) {
			$width         = max( 1, (int) $size[0] );
			$height        = max( 1, (int) $size[1] );
			$resolved_size = array( $width, $height );
		} else {
			$sizes         = wp_get_registered_image_subsizes();
			$slug          = ( is_string( $size ) && isset( $sizes[ $size ] ) ) ? $size : 'authorship-box-avatar';
			$width         = isset( $sizes[ $slug ]['width'] ) ? (int) $sizes[ $slug ]['width'] : 150;
			$height        = isset( $sizes[ $slug ]['height'] ) ? (int) $sizes[ $slug ]['height'] : 150;
			$resolved_size = $slug;
		}

		if ( 'box' === $context && apply_filters( 'molongui_authorship/load_author_box_styles', true ) ) {
			if ( isset( $options['author_box_avatar_width'], $options['author_box_avatar_height'] ) ) {
				$width         = (int) $options['author_box_avatar_width'];
				$height        = (int) $options['author_box_avatar_height'];
				$resolved_size = array( $width, $height );
			}
		}

		return array(
			'width'  => $width,
			'height' => $height,
			'size'   => $resolved_size,
		);
	}

	private function custom_default_resolution( $resolved_size, $context, $attr ) {
		$value = $this->get_custom_default_avatar( $resolved_size, $context, $attr );

		return array(
			'value'            => $value,
			'source'           => '' !== $value ? 'custom-default' : 'none',
			'browser_fallback' => '',
		);
	}

	private function empty_resolution() {
		return array(
			'value'            => '',
			'source'           => 'none',
			'browser_fallback' => '',
		);
	}
}
