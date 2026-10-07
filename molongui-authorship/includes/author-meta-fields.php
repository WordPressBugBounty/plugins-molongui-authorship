<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) || exit;  

final class Author_Meta_Fields {

	const CONTEXT_AUTHOR_BOX = 'author_box';

	const CONTEXT_AUTHOR_LIST = 'author_list';

	const GLOBAL_FIELDS_SETTING = 'author_box_meta_fields';

	const AUTHOR_VISIBILITY_META_KEY = 'box_meta_visibility';

	const VISIBILITY_DEFAULT = 'default';

	const VISIBILITY_SHOW = 'show';

	const VISIBILITY_HIDE = 'hide';

	private static $registry_cache = array();

	private static $author_visibility_cache = array();

	public static function get_fields( $context = '' ) {
		$context   = self::normalize_context( $context );
		$cache_key = '' === $context ? '__all__' : $context;

		if ( isset( self::$registry_cache[ $cache_key ] ) ) {
			return self::$registry_cache[ $cache_key ];
		}

		$fields = self::get_default_registry();

		$fields = apply_filters( 'molongui_authorship/author_meta_fields', $fields, $context );

		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		$normalized = array();

		foreach ( $fields as $field_id => $definition ) {
			$field_id = sanitize_key( $field_id );

			if ( '' === $field_id || ! is_array( $definition ) ) {
				continue;
			}

			$definition = self::normalize_definition( $field_id, $definition );

			if ( '' !== $context && ! in_array( $context, $definition['contexts'], true ) ) {
				continue;
			}

			$normalized[ $field_id ] = $definition;
		}

		uasort( $normalized, array( __CLASS__, 'sort_definitions' ) );

		self::$registry_cache[ $cache_key ] = $normalized;

		return $normalized;
	}

	public static function reset_registry_cache() {
		self::$registry_cache = array();
	}

	public static function reset_author_visibility_cache( $author = null ) {
		if ( ! $author instanceof Author ) {
			self::$author_visibility_cache = array();
			return;
		}

		unset( self::$author_visibility_cache[ self::get_author_cache_key( $author ) ] );
	}

	public static function get_field_ids( $context = '' ) {
		return array_keys( self::get_fields( $context ) );
	}

	public static function get_field( $field, $context = '' ) {
		$field  = self::canonicalize_field_id( $field, $context );
		$fields = self::get_fields( $context );

		return isset( $fields[ $field ] ) ? $fields[ $field ] : false;
	}

	public static function has_field( $field, $context = '' ) {
		return false !== self::get_field( $field, $context );
	}

	public static function canonicalize_field_id( $field, $context = '' ) {
		$field = sanitize_key( $field );

		if ( '' === $field ) {
			return '';
		}

		$fields = self::get_fields( $context );

		if ( isset( $fields[ $field ] ) ) {
			return $field;
		}

		foreach ( $fields as $field_id => $definition ) {
			if ( in_array( $field, $definition['aliases'], true ) ) {
				return $field_id;
			}
		}

		return $field;
	}

	public static function get_default_fields( $context = self::CONTEXT_AUTHOR_BOX ) {
		$context = self::normalize_context( $context );
		$fields  = self::get_fields( $context );
		$default = array();

		foreach ( $fields as $field_id => $definition ) {
			if ( ! empty( $definition['default_visible'] ) ) {
				$default[] = $field_id;
			}
		}

		return $default;
	}

	public static function normalize_field_list( $fields, $context = self::CONTEXT_AUTHOR_BOX, $default_fields = null ) {
		$context     = self::normalize_context( $context );
		$available   = self::get_field_ids( $context );
		$raw_default = $default_fields;

		if ( null === $fields || ( is_string( $fields ) && '' === trim( $fields ) ) ) {
			$fields = 'default';
		}

		if ( is_string( $fields ) ) {
			$keyword = strtolower( trim( $fields ) );

			if ( 'all' === $keyword ) {
				return $available;
			}

			if ( 'none' === $keyword ) {
				return array();
			}

			if ( 'default' === $keyword ) {
				if ( null === $raw_default ) {
					return self::get_default_fields( $context );
				}

				return self::normalize_explicit_field_list( $raw_default, $context );
			}

			$fields = explode( ',', $fields );
		}

		if ( ! is_array( $fields ) ) {
			return null === $raw_default
				? self::get_default_fields( $context )
				: self::normalize_explicit_field_list( $raw_default, $context );
		}

		return self::normalize_explicit_field_list( $fields, $context );
	}

	public static function serialize_field_list( $fields, $context = self::CONTEXT_AUTHOR_BOX ) {
		$fields = self::normalize_field_list( $fields, $context );

		return empty( $fields ) ? 'none' : implode( ',', $fields );
	}

	public static function get_global_author_box_fields( $options = null ) {
		if ( null === $options ) {
			$options = Settings::get();
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( isset( $options[ self::GLOBAL_FIELDS_SETTING ] )
			&& is_scalar( $options[ self::GLOBAL_FIELDS_SETTING ] )
			&& '' !== trim( (string) $options[ self::GLOBAL_FIELDS_SETTING ] )
		) {
			return self::normalize_field_list(
				$options[ self::GLOBAL_FIELDS_SETTING ],
				self::CONTEXT_AUTHOR_BOX,
				self::get_default_fields( self::CONTEXT_AUTHOR_BOX )
			);
		}

		$selected = self::get_default_fields( self::CONTEXT_AUTHOR_BOX );
		$fields   = self::get_fields( self::CONTEXT_AUTHOR_BOX );

		foreach ( $fields as $field_id => $definition ) {
			$legacy_option = $definition['legacy_global_option'];

			if ( '' === $legacy_option || ! array_key_exists( $legacy_option, $options ) ) {
				continue;
			}

			$legacy_visible = self::normalize_boolean( $options[ $legacy_option ] );
			$selected       = self::set_field_membership( $selected, $field_id, $legacy_visible );
		}

		return self::normalize_explicit_field_list( $selected, self::CONTEXT_AUTHOR_BOX );
	}

	public static function get_author_visibility( $author ) {
		$visibility = array();

		foreach ( self::get_field_ids( self::CONTEXT_AUTHOR_BOX ) as $field_id ) {
			$visibility[ $field_id ] = self::VISIBILITY_DEFAULT;
		}

		if ( ! $author instanceof Author ) {
			return $visibility;
		}

		$cache_key = self::get_author_cache_key( $author );

		if ( isset( self::$author_visibility_cache[ $cache_key ] ) ) {
			return self::$author_visibility_cache[ $cache_key ];
		}

		if ( self::author_visibility_meta_exists( $author ) ) {
			$stored = self::parse_author_visibility( $author->get_meta( self::AUTHOR_VISIBILITY_META_KEY ) );

			foreach ( $stored as $field_id => $state ) {
				if ( isset( $visibility[ $field_id ] ) ) {
					$visibility[ $field_id ] = $state;
				}
			}

			self::$author_visibility_cache[ $cache_key ] = $visibility;

			return $visibility;
		}

		foreach ( self::get_fields( self::CONTEXT_AUTHOR_BOX ) as $field_id => $definition ) {
			$legacy_meta = $definition['legacy_author_meta'];

			if ( '' === $legacy_meta ) {
				continue;
			}

			if ( self::normalize_boolean( $author->get_meta( $legacy_meta ) ) ) {
				$visibility[ $field_id ] = self::VISIBILITY_SHOW;
			}
		}

		self::$author_visibility_cache[ $cache_key ] = $visibility;

		return $visibility;
	}

	public static function get_author_field_visibility( $field, $author ) {
		$field = self::canonicalize_field_id( $field, self::CONTEXT_AUTHOR_BOX );

		if ( '' === $field || ! self::has_field( $field, self::CONTEXT_AUTHOR_BOX ) ) {
			return self::VISIBILITY_DEFAULT;
		}

		$visibility = self::get_author_visibility( $author );

		return isset( $visibility[ $field ] ) ? $visibility[ $field ] : self::VISIBILITY_DEFAULT;
	}

	public static function normalize_visibility( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? self::VISIBILITY_SHOW : self::VISIBILITY_HIDE;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 === (int) $value ? self::VISIBILITY_HIDE : self::VISIBILITY_SHOW;
		}

		$value = strtolower( trim( (string) $value ) );

		if ( in_array( $value, array( self::VISIBILITY_SHOW, 'yes', 'true', '1', 'on' ), true ) ) {
			return self::VISIBILITY_SHOW;
		}

		if ( in_array( $value, array( self::VISIBILITY_HIDE, 'no', 'false', '0', 'off' ), true ) ) {
			return self::VISIBILITY_HIDE;
		}

		return self::VISIBILITY_DEFAULT;
	}

	public static function parse_author_visibility( $visibility ) {
		$parsed = array();

		if ( is_string( $visibility ) ) {
			$items = '' === trim( $visibility ) ? array() : explode( ',', $visibility );

			foreach ( $items as $item ) {
				$pair = array_map( 'trim', explode( ':', $item, 2 ) );

				if ( 2 !== count( $pair ) ) {
					continue;
				}

				$parsed[ $pair[0] ] = $pair[1];
			}
		} elseif ( is_array( $visibility ) ) {
			$parsed = $visibility;
		} else {
			return array();
		}

		$normalized = array();

		foreach ( $parsed as $field => $state ) {
			$field = self::canonicalize_field_id( $field, self::CONTEXT_AUTHOR_BOX );

			if ( '' === $field || ! self::has_field( $field, self::CONTEXT_AUTHOR_BOX ) ) {
				continue;
			}

			$state = self::normalize_visibility( $state );

			if ( self::VISIBILITY_DEFAULT === $state ) {
				continue;
			}

			$normalized[ $field ] = $state;
		}

		return self::order_visibility_map( $normalized );
	}

	public static function serialize_author_visibility( $visibility ) {
		$visibility = self::parse_author_visibility( $visibility );
		$serialized = array();

		foreach ( $visibility as $field => $state ) {
			$serialized[] = $field . ':' . $state;
		}

		return implode( ',', $serialized );
	}

	public static function update_author_visibility( $author, $visibility ) {
		if ( ! $author instanceof Author || empty( $author->get_id() ) ) {
			return false;
		}

		$normalized = self::parse_author_visibility( $visibility );
		$value      = self::serialize_author_visibility( $normalized );
		$meta_key   = self::get_author_visibility_meta_key( $author->get_type() );

		if ( 'guest' === $author->get_type() ) {
			$result = update_post_meta( $author->get_id(), $meta_key, $value );
		} else {
			$result = update_user_meta( $author->get_id(), $meta_key, $value );
		}

		self::sync_legacy_author_visibility( $author, $normalized );

		self::reset_author_visibility_cache( $author );

		return $result;
	}

	private static function sync_legacy_author_visibility( $author, $visibility ) {
		foreach ( self::get_fields( self::CONTEXT_AUTHOR_BOX ) as $field_id => $definition ) {
			$legacy_meta = $definition['legacy_author_meta'];

			if ( '' === $legacy_meta ) {
				continue;
			}

			$state    = isset( $visibility[ $field_id ] ) ? $visibility[ $field_id ] : self::VISIBILITY_DEFAULT;
			$meta_key = 'guest' === $author->get_type()
				? Author::GUEST_META_PREFIX . $legacy_meta
				: Author::USER_META_PREFIX . $legacy_meta;

			if ( 'guest' === $author->get_type() ) {
				if ( self::VISIBILITY_SHOW === $state ) {
					update_post_meta( $author->get_id(), $meta_key, '1' );
				} else {
					delete_post_meta( $author->get_id(), $meta_key );
				}
			} elseif ( self::VISIBILITY_SHOW === $state ) {
				update_user_meta( $author->get_id(), $meta_key, '1' );
			} else {
				delete_user_meta( $author->get_id(), $meta_key );
			}
		}
	}

	public static function get_author_visibility_meta_key( $author_type ) {
		return 'guest' === $author_type
			? Author::GUEST_META_PREFIX . self::AUTHOR_VISIBILITY_META_KEY
			: Author::USER_META_PREFIX . self::AUTHOR_VISIBILITY_META_KEY;
	}

	public static function is_visible( $field, $author = null, $context = self::CONTEXT_AUTHOR_BOX, $args = array() ) {
		$context    = self::normalize_context( $context );
		$field      = self::canonicalize_field_id( $field, $context );
		$definition = self::get_field( $field, $context );

		if ( false === $definition ) {
			return false;
		}

		$visible = ! empty( $definition['default_visible'] );

		if ( self::CONTEXT_AUTHOR_BOX === $context ) {
			$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : null;
			$visible = in_array( $field, self::get_global_author_box_fields( $options ), true );
		}

		if ( array_key_exists( 'fields', $args ) ) {
			$default_fields = self::CONTEXT_AUTHOR_BOX === $context
				? self::get_global_author_box_fields( isset( $args['options'] ) ? $args['options'] : null )
				: self::get_default_fields( $context );

			$instance_fields = self::normalize_field_list( $args['fields'], $context, $default_fields );
			$visible         = in_array( $field, $instance_fields, true );
		}

		$apply_author_override = self::CONTEXT_AUTHOR_BOX === $context;

		if ( array_key_exists( 'apply_author_override', $args ) ) {
			$apply_author_override = (bool) $args['apply_author_override'];
		}

		if ( $apply_author_override && $author instanceof Author && ! empty( $definition['author_override'] ) ) {
			$author_state = self::get_author_field_visibility( $field, $author );

			if ( self::VISIBILITY_SHOW === $author_state ) {
				$visible = true;
			} elseif ( self::VISIBILITY_HIDE === $author_state ) {
				$visible = false;
			}
		}

		if ( ! empty( $args['visibility_overrides'] ) && is_array( $args['visibility_overrides'] ) ) {
			foreach ( $args['visibility_overrides'] as $override_field => $override_visible ) {
				$override_field = self::canonicalize_field_id( $override_field, $context );

				if ( $field !== $override_field ) {
					continue;
				}

				$visible = self::normalize_boolean( $override_visible );
				break;
			}
		}

		return (bool) apply_filters(
			'molongui_authorship/author_meta_field_visibility',
			$visible,
			$field,
			$author,
			$context,
			$args
		);
	}

	public static function get_visible_fields( $author = null, $context = self::CONTEXT_AUTHOR_BOX, $args = array() ) {
		$visible = array();

		foreach ( self::get_field_ids( $context ) as $field ) {
			if ( self::is_visible( $field, $author, $context, $args ) ) {
				$visible[] = $field;
			}
		}

		return $visible;
	}

	public static function get_value( $field, $author ) {
		if ( ! $author instanceof Author ) {
			return '';
		}

		$field      = self::canonicalize_field_id( $field );
		$definition = self::get_field( $field );

		if ( false === $definition ) {
			return '';
		}

		$source = $definition['source'];
		$value  = '';

		if ( 'callback' === $source['type'] && is_callable( $source['callback'] ) ) {
			$value = call_user_func( $source['callback'], $author, $field, $definition );
		} elseif ( 'method' === $source['type'] && method_exists( $author, $source['name'] ) ) {
			$value = call_user_func( array( $author, $source['name'] ) );
		} elseif ( 'meta' === $source['type'] && '' !== $source['name'] ) {
			$value = $author->get_meta( $source['name'] );
		}

		return apply_filters(
			'molongui_authorship/author_meta_field_value',
			$value,
			$field,
			$author,
			$definition
		);
	}

	public static function get_prefetch_fields( $fields, $context = self::CONTEXT_AUTHOR_BOX ) {
		$fields   = self::normalize_field_list( $fields, $context );
		$registry = self::get_fields( $context );
		$core     = array();
		$meta     = array();

		foreach ( $fields as $field ) {
			if ( ! isset( $registry[ $field ] ) ) {
				continue;
			}

			$prefetch = $registry[ $field ]['prefetch'];
			$core     = array_merge( $core, $prefetch['core'] );

			foreach ( $prefetch['user_meta'] as $meta_key ) {
				$meta[] = self::qualify_prefetch_meta_key( $meta_key, 'user' );
			}

			foreach ( $prefetch['guest_meta'] as $meta_key ) {
				$meta[] = self::qualify_prefetch_meta_key( $meta_key, 'guest' );
			}
		}

		return array(
			'core' => array_values( array_unique( array_filter( $core ) ) ),
			'meta' => array_values( array_unique( array_filter( $meta ) ) ),
		);
	}

	public static function get_visibility_prefetch_meta_keys() {
		$meta = array(
			self::get_author_visibility_meta_key( 'user' ),
			self::get_author_visibility_meta_key( 'guest' ),
		);

		foreach ( self::get_fields( self::CONTEXT_AUTHOR_BOX ) as $definition ) {
			if ( '' === $definition['legacy_author_meta'] ) {
				continue;
			}

			$meta[] = Author::USER_META_PREFIX . $definition['legacy_author_meta'];
			$meta[] = Author::GUEST_META_PREFIX . $definition['legacy_author_meta'];
		}

		return array_values( array_unique( $meta ) );
	}

	private static function get_default_registry() {
		$contexts = array( self::CONTEXT_AUTHOR_BOX, self::CONTEXT_AUTHOR_LIST );

		return array(
			'headline'   => array(
				'label'           => __( 'Professional headline', 'molongui-authorship' ),
				'priority'        => 10,
				'contexts'        => $contexts,
				'default_visible' => true,
				'group'           => 'headline',
				'aliases'         => array( 'professional_headline' ),
				'source'          => array(
					'type' => 'method',
					'name' => 'get_professional_headline',
				),
				'prefetch'        => array(
					'user_meta'  => array( 'professional_headline' ),
					'guest_meta' => array( 'professional_headline' ),
				),
			),
			'job'        => array(
				'label'           => __( 'Job title', 'molongui-authorship' ),
				'priority'        => 20,
				'contexts'        => $contexts,
				'default_visible' => true,
				'aliases'         => array( 'position' ),
			),
			'company'    => array(
				'label'           => __( 'Company', 'molongui-authorship' ),
				'priority'        => 30,
				'contexts'        => $contexts,
				'default_visible' => true,
				'prefetch'        => array(
					'user_meta'  => array( 'company', 'company_link' ),
					'guest_meta' => array( 'company', 'company_link' ),
				),
			),
			'department' => array(
				'label'           => __( 'Department', 'molongui-authorship' ),
				'priority'        => 40,
				'contexts'        => $contexts,
				'default_visible' => true,
				'source'          => array(
					'type' => 'method',
					'name' => 'get_department',
				),
				'prefetch'        => array(
					'user_meta'  => array( 'department' ),
					'guest_meta' => array( 'department' ),
				),
			),
			'location'   => array(
				'label'           => __( 'Location', 'molongui-authorship' ),
				'priority'        => 50,
				'contexts'        => $contexts,
				'default_visible' => true,
				'source'          => array(
					'type' => 'method',
					'name' => 'get_location',
				),
				'prefetch'        => array(
					'user_meta'  => array( 'location' ),
					'guest_meta' => array( 'location' ),
				),
			),
			'email'      => array(
				'label'                => __( 'Email', 'molongui-authorship' ),
				'priority'             => 70,
				'contexts'             => $contexts,
				'default_visible'      => false,
				'aliases'              => array( 'mail', 'email_address' ),
				'legacy_global_option' => 'author_box_meta_show_email',
				'legacy_author_meta'   => 'show_meta_mail',
				'source'               => array(
					'type' => 'method',
					'name' => 'get_email',
				),
				'prefetch'             => array(
					'core'       => array( 'user_email' ),
					'guest_meta' => array( 'mail' ),
				),
			),
			'phone'      => array(
				'label'                => __( 'Phone', 'molongui-authorship' ),
				'priority'             => 60,
				'contexts'             => $contexts,
				'default_visible'      => false,
				'legacy_global_option' => 'author_box_meta_show_phone',
				'legacy_author_meta'   => 'show_meta_phone',
			),
			'website'    => array(
				'label'           => __( 'Website', 'molongui-authorship' ),
				'priority'        => 80,
				'contexts'        => $contexts,
				'default_visible' => true,
				'aliases'         => array( 'web', 'url' ),
				'source'          => array(
					'type' => 'method',
					'name' => 'get_website',
				),
				'prefetch'        => array(
					'core'       => array( 'user_url' ),
					'guest_meta' => array( 'web' ),
				),
			),
		);
	}

	private static function normalize_definition( $field_id, $definition ) {
		$has_explicit_prefetch = isset( $definition['prefetch'] ) && is_array( $definition['prefetch'] );

		$defaults = array(
			'id'                   => $field_id,
			'label'                => $field_id,
			'priority'             => 100,
			'contexts'             => array( self::CONTEXT_AUTHOR_BOX, self::CONTEXT_AUTHOR_LIST ),
			'default_visible'      => false,
			'author_override'      => true,
			'group'                => 'meta',
			'aliases'              => array(),
			'legacy_global_option' => '',
			'legacy_author_meta'   => '',
			'source'               => array(
				'type'     => 'meta',
				'name'     => $field_id,
				'callback' => null,
			),
			'prefetch'             => array(
				'core'       => array(),
				'user_meta'  => array(),
				'guest_meta' => array(),
			),
		);

		$definition = array_merge( $defaults, $definition );

		$definition['id']              = $field_id;
		$definition['label']           = is_scalar( $definition['label'] ) ? (string) $definition['label'] : $field_id;
		$definition['priority']        = is_numeric( $definition['priority'] ) ? (int) $definition['priority'] : 100;
		$definition['default_visible'] = (bool) $definition['default_visible'];
		$definition['author_override'] = (bool) $definition['author_override'];
		$definition['group']           = sanitize_key( $definition['group'] );

		if ( '' === $definition['group'] ) {
			$definition['group'] = 'meta';
		}

		$definition['contexts'] = self::normalize_string_list( $definition['contexts'], true );
		$definition['aliases']  = self::normalize_string_list( $definition['aliases'], true );

		$definition['legacy_global_option'] = is_scalar( $definition['legacy_global_option'] )
			? sanitize_key( $definition['legacy_global_option'] )
			: '';
		$definition['legacy_author_meta'] = is_scalar( $definition['legacy_author_meta'] )
			? sanitize_key( $definition['legacy_author_meta'] )
			: '';

		if ( empty( $definition['contexts'] ) ) {
			$definition['contexts'] = $defaults['contexts'];
		}

		if ( ! is_array( $definition['source'] ) ) {
			$definition['source'] = $defaults['source'];
		} else {
			$definition['source'] = array_merge( $defaults['source'], $definition['source'] );
		}

		$definition['source']['type'] = sanitize_key( $definition['source']['type'] );
		$definition['source']['name'] = is_scalar( $definition['source']['name'] )
			? (string) $definition['source']['name']
			: '';

		if ( ! in_array( $definition['source']['type'], array( 'meta', 'method', 'callback' ), true ) ) {
			$definition['source'] = $defaults['source'];
		}

		if ( ! is_array( $definition['prefetch'] ) ) {
			$definition['prefetch'] = $defaults['prefetch'];
		} else {
			$definition['prefetch'] = array_merge( $defaults['prefetch'], $definition['prefetch'] );
		}

		if ( ! $has_explicit_prefetch && 'meta' === $definition['source']['type'] && '' !== $definition['source']['name'] ) {
			$definition['prefetch']['user_meta']  = array( $definition['source']['name'] );
			$definition['prefetch']['guest_meta'] = array( $definition['source']['name'] );
		}

		$definition['prefetch']['core']       = self::normalize_string_list( $definition['prefetch']['core'], false );
		$definition['prefetch']['user_meta']  = self::normalize_string_list( $definition['prefetch']['user_meta'], false );
		$definition['prefetch']['guest_meta'] = self::normalize_string_list( $definition['prefetch']['guest_meta'], false );

		return $definition;
	}

	private static function sort_definitions( $left, $right ) {
		if ( $left['priority'] === $right['priority'] ) {
			return strcmp( $left['id'], $right['id'] );
		}

		return ( $left['priority'] < $right['priority'] ) ? -1 : 1;
	}

	private static function normalize_explicit_field_list( $fields, $context ) {
		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
		}

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$selected = array();

		foreach ( $fields as $field ) {
			if ( ! is_scalar( $field ) ) {
				continue;
			}

			$field = self::canonicalize_field_id( $field, $context );

			if ( '' !== $field && self::has_field( $field, $context ) ) {
				$selected[ $field ] = true;
			}
		}

		$ordered = array();

		foreach ( self::get_field_ids( $context ) as $field ) {
			if ( isset( $selected[ $field ] ) ) {
				$ordered[] = $field;
			}
		}

		return $ordered;
	}

	private static function set_field_membership( $fields, $field, $include ) {
		$fields = is_array( $fields ) ? $fields : array();
		$field  = self::canonicalize_field_id( $field, self::CONTEXT_AUTHOR_BOX );

		if ( $include ) {
			$fields[] = $field;
		} else {
			$fields = array_values( array_diff( $fields, array( $field ) ) );
		}

		return array_values( array_unique( $fields ) );
	}

	private static function order_visibility_map( $visibility ) {
		$ordered = array();

		foreach ( self::get_field_ids( self::CONTEXT_AUTHOR_BOX ) as $field ) {
			if ( isset( $visibility[ $field ] ) ) {
				$ordered[ $field ] = $visibility[ $field ];
			}
		}

		return $ordered;
	}

	private static function get_author_cache_key( $author ) {
		return $author->get_type() . ':' . (int) $author->get_id();
	}

	private static function author_visibility_meta_exists( $author ) {
		$meta_key = self::get_author_visibility_meta_key( $author->get_type() );

		if ( 'guest' === $author->get_type() ) {
			return metadata_exists( 'post', $author->get_id(), $meta_key );
		}

		return metadata_exists( 'user', $author->get_id(), $meta_key );
	}

	private static function qualify_prefetch_meta_key( $meta_key, $author_type ) {
		$meta_key = trim( (string) $meta_key );

		if ( 0 === strpos( $meta_key, 'native:' ) ) {
			return substr( $meta_key, 7 );
		}

		if ( 0 === strpos( $meta_key, Author::USER_META_PREFIX ) || 0 === strpos( $meta_key, Author::GUEST_META_PREFIX ) ) {
			return $meta_key;
		}

		return 'guest' === $author_type
			? Author::GUEST_META_PREFIX . $meta_key
			: Author::USER_META_PREFIX . $meta_key;
	}

	private static function normalize_string_list( $value, $sanitize_key = false ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $item ) {
			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$item = trim( (string) $item );
			$item = $sanitize_key ? sanitize_key( $item ) : $item;

			if ( '' !== $item ) {
				$normalized[] = $item;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	private static function normalize_context( $context ) {
		return sanitize_key( (string) $context );
	}

	private static function normalize_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return 0 !== (int) $value;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'true', 'yes', 'on', 'show' ), true );
	}
}
