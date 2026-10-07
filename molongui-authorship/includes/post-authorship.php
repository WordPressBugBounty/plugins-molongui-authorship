<?php

namespace Molongui\Authorship;

defined( 'ABSPATH' ) || exit;  

final class Post_Authorship {

	const MAIN_AUTHOR_META_KEY = '_molongui_main_author';

	const AUTHOR_META_KEY = '_molongui_author';

	const STATE_LEGACY = 'legacy';

	const STATE_RESOLVED = 'resolved';

	const STATE_UNRESOLVED = 'unresolved';

	const STATE_INCONSISTENT = 'inconsistent';

	private static $syncing_post_author = array();

	private static $raw_authorship_cache = array();

	private static $authorship_cache = array();

	private static $cache_invalidation_hooks_registered = false;

	private static $publication_hooks_registered = false;

	public static function register_publication_guards() {
		if ( self::$publication_hooks_registered ) {
			return;
		}

		self::$publication_hooks_registered = true;

		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_publication_status' ), PHP_INT_MAX, 3 );
	}

	private static function register_cache_invalidation_hooks() {
		if ( self::$cache_invalidation_hooks_registered ) {
			return;
		}

		self::$cache_invalidation_hooks_registered = true;

		add_action( 'added_post_meta', array( __CLASS__, 'invalidate_cache_on_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'invalidate_cache_on_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'invalidate_cache_on_meta_change' ), 10, 3 );
		add_action( 'clean_post_cache', array( __CLASS__, 'invalidate_cache_on_post_change' ), 10, 1 );
	}

	public static function invalidate_cache_on_meta_change( $meta_id, $post_id, $meta_key ) {
		if ( self::MAIN_AUTHOR_META_KEY !== $meta_key && self::AUTHOR_META_KEY !== $meta_key ) {
			return;
		}

		self::clear_request_cache( $post_id );
	}

	public static function invalidate_cache_on_post_change( $post_id ) {
		self::clear_request_cache( $post_id );
	}

	private static function clear_request_cache( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		unset( self::$raw_authorship_cache[ $post_id ], self::$authorship_cache[ $post_id ] );
	}

	public static function guard_publication_status( $data, $postarr, $unsanitized_postarr = array() ) {
		if ( empty( $data['post_status'] ) || ! self::post_status_requires_main_author( $data['post_status'] ) ) {
			return $data;
		}

		$post_id   = ! empty( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$post_type = ! empty( $data['post_type'] ) ? sanitize_key( $data['post_type'] ) : '';

		if ( $post_id && ! empty( self::$syncing_post_author[ $post_id ] ) ) {
			return $data;
		}

		if ( ! $post_type && ! empty( $postarr['post_type'] ) ) {
			$post_type = sanitize_key( $postarr['post_type'] );
		}

		if ( ! $post_type || ! Post::byline_takeover() || ! Post::is_post_type_enabled( $post_type, $post_id ) ) {
			return $data;
		}

		$authorship = self::get_publication_authorship_candidate( $post_id, $postarr, $data );

		if ( is_wp_error( $authorship ) ) {
			$authorship = array(
				'main'    => '',
				'authors' => array(),
			);
		}

		return self::protect_publication_status( $data, $post_id, $authorship );
	}

	public static function prepare_submitted_authorship( $post_id, $post_authors, $submitted_main_author = null ) {
		$post_id      = absint( $post_id );
		$post_authors = is_array( $post_authors ) ? $post_authors : array();
		$authors      = array();

		foreach ( $post_authors as $author_ref ) {
			$author_ref = sanitize_text_field( wp_unslash( $author_ref ) );

			if ( ! self::is_valid_reference( $author_ref ) || ! self::author_exists( $author_ref ) ) {
				continue;
			}

			if ( ! in_array( $author_ref, $authors, true ) ) {
				$authors[] = $author_ref;
			}
		}

		if ( null !== $submitted_main_author ) {
			$main_author = sanitize_text_field( wp_unslash( $submitted_main_author ) );

			if ( '' !== $main_author && ( ! self::is_valid_reference( $main_author ) || ! self::author_exists( $main_author ) ) ) {
				return new \WP_Error(
					'molongui_authorship_invalid_submitted_main_author',
					__( 'The selected main author is invalid or no longer exists.', 'molongui-authorship' )
				);
			}
		} elseif ( $post_id && self::STATE_UNRESOLVED === self::get_state( $post_id ) ) {
			$main_author = '';
		} else {
			$main_author = ! empty( $authors ) ? reset( $authors ) : '';
		}

		return self::normalize_authorship( $main_author, $authors );
	}

	public static function get_publication_authorship_candidate( $post_id, $postarr, $data = array() ) {
		$post_id               = absint( $post_id );
		$postarr               = is_array( $postarr ) ? $postarr : array();
		$data                  = is_array( $data ) ? $data : array();
		$has_submitted_authors = array_key_exists( 'molongui_post_authors', $postarr );
		$has_submitted_main    = array_key_exists( 'molongui_main_author', $postarr );

		if ( $has_submitted_authors || $has_submitted_main ) {
			$submitted_authors = $has_submitted_authors ? $postarr['molongui_post_authors'] : array();
			$submitted_main    = $has_submitted_main ? $postarr['molongui_main_author'] : null;
			$authorship        = self::prepare_submitted_authorship( $post_id, $submitted_authors, $submitted_main );
		} elseif ( $post_id ) {
			$authorship = self::get_authorship( $post_id, true );
		} else {
			$user_id = ! empty( $data['post_author'] ) ? absint( $data['post_author'] ) : 0;

			if ( ! $user_id && ! empty( $postarr['post_author'] ) ) {
				$user_id = absint( $postarr['post_author'] );
			}

			if ( self::is_usable_user_id( $user_id ) ) {
				$reference = self::build_reference( $user_id, 'user' );

				$authorship = array(
					'main'    => $reference,
					'authors' => array( $reference ),
				);
			} else {
				$authorship = array(
					'main'    => '',
					'authors' => array(),
				);
			}
		}

		if ( is_wp_error( $authorship ) ) {
			return $authorship;
		}

		$authorship = apply_filters(
			'molongui_authorship/publication_authorship_candidate',
			$authorship,
			$post_id,
			$postarr,
			$data
		);

		if ( is_wp_error( $authorship ) ) {
			return $authorship;
		}

		if ( ! is_array( $authorship ) || ! array_key_exists( 'main', $authorship ) || ! isset( $authorship['authors'] ) || ! is_array( $authorship['authors'] ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_publication_candidate',
				__( 'The publication authorship candidate is invalid.', 'molongui-authorship' )
			);
		}

		return self::normalize_authorship( $authorship['main'], $authorship['authors'] );
	}

	public static function post_status_requires_main_author( $post_status ) {
		$statuses = array_merge( array( 'publish', 'future', 'private' ), get_post_stati( array( 'public' => true ), 'names' ) );
		$statuses = array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );

		$statuses = apply_filters( 'molongui_authorship/post_statuses_requiring_main_author', $statuses );
		$statuses = is_array( $statuses ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) ) : array();

		return in_array( sanitize_key( $post_status ), $statuses, true );
	}

	public static function protect_publication_status( $data, $post_id, $authorship ) {
		if ( empty( $data['post_status'] ) || ! self::post_status_requires_main_author( $data['post_status'] ) ) {
			return $data;
		}

		$main_author = isset( $authorship['main'] ) ? $authorship['main'] : '';
		$authors     = isset( $authorship['authors'] ) && is_array( $authorship['authors'] ) ? $authorship['authors'] : array();

		if ( self::is_publishable_authorship( $main_author, $authors ) ) {
			return $data;
		}

		$requested_status = sanitize_key( $data['post_status'] );

		$fallback_status = apply_filters( 'molongui_authorship/unresolved_main_fallback_status', 'draft', absint( $post_id ), $requested_status, $authorship );
		$fallback_status = sanitize_key( $fallback_status );

		if ( ! $fallback_status || ! get_post_status_object( $fallback_status ) || self::post_status_requires_main_author( $fallback_status ) ) {
			$fallback_status = 'draft';
		}

		$data['post_status'] = $fallback_status;

		do_action( 'molongui_authorship/post_publication_blocked', absint( $post_id ), $requested_status, $fallback_status, $authorship );

		return $data;
	}

	private static function get_raw_authorship_data( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return array(
				'main_meta_exists'   => false,
				'author_meta_exists' => false,
				'main_values'        => array(),
				'author_rows'        => array(),
				'state'              => self::STATE_INCONSISTENT,
			);
		}

		self::register_cache_invalidation_hooks();

		if ( isset( self::$raw_authorship_cache[ $post_id ] ) ) {
			return self::$raw_authorship_cache[ $post_id ];
		}

		$main_exists   = metadata_exists( 'post', $post_id, self::MAIN_AUTHOR_META_KEY );
		$authors_exist = metadata_exists( 'post', $post_id, self::AUTHOR_META_KEY );
		$main_values   = get_post_meta( $post_id, self::MAIN_AUTHOR_META_KEY, false );
		$author_rows   = get_post_meta( $post_id, self::AUTHOR_META_KEY, false );

		$raw = array(
			'main_meta_exists'   => $main_exists,
			'author_meta_exists' => $authors_exist,
			'main_values'        => is_array( $main_values ) ? array_values( $main_values ) : array(),
			'author_rows'        => is_array( $author_rows ) ? array_values( $author_rows ) : array(),
		);

		$raw['state'] = self::classify_raw_authorship_data( $raw );

		self::$raw_authorship_cache[ $post_id ] = $raw;

		return $raw;
	}

	private static function classify_raw_authorship_data( $raw ) {
		$main_exists   = ! empty( $raw['main_meta_exists'] );
		$authors_exist = ! empty( $raw['author_meta_exists'] );
		$main_values   = ! empty( $raw['main_values'] ) && is_array( $raw['main_values'] ) ? $raw['main_values'] : array();
		$author_rows   = ! empty( $raw['author_rows'] ) && is_array( $raw['author_rows'] ) ? $raw['author_rows'] : array();

		if ( ! $main_exists && ! $authors_exist ) {
			return self::STATE_LEGACY;
		}

		if ( ! $main_exists ) {
			return self::STATE_INCONSISTENT;
		}

		if ( 1 !== count( $main_values ) ) {
			return self::STATE_INCONSISTENT;
		}

		$main_author = (string) reset( $main_values );

		if ( '' === $main_author ) {
			return self::STATE_UNRESOLVED;
		}

		if ( ! self::is_valid_reference( $main_author ) ) {
			return self::STATE_INCONSISTENT;
		}

		$normalized = self::normalize_authorship( $main_author, $author_rows );

		if ( is_wp_error( $normalized ) || $normalized['authors'] !== array_values( $author_rows ) ) {
			return self::STATE_INCONSISTENT;
		}

		return self::STATE_RESOLVED;
	}

	public static function get_state( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return self::STATE_INCONSISTENT;
		}

		$raw = self::get_raw_authorship_data( $post_id );

		return $raw['state'];
	}

	public static function get_authorship( $post_id, $legacy_fallback = true ) {
		$post_id   = absint( $post_id );
		$cache_key = $legacy_fallback ? 1 : 0;

		if ( $post_id && isset( self::$authorship_cache[ $post_id ][ $cache_key ] ) ) {
			return self::$authorship_cache[ $post_id ][ $cache_key ];
		}

		$raw   = self::get_raw_authorship_data( $post_id );
		$state = $raw['state'];

		$data = array(
			'post_id'            => $post_id,
			'state'              => $state,
			'main_meta_exists'   => $raw['main_meta_exists'],
			'author_meta_exists' => $raw['author_meta_exists'],
			'main'               => '',
			'authors'            => array(),
			'is_legacy_fallback' => false,
		);

		if ( ! $post_id ) {
			return $data;
		}

		if ( self::STATE_LEGACY === $state ) {
			if ( $legacy_fallback ) {
				$legacy_reference = self::get_legacy_reference( $post_id );

				if ( $legacy_reference ) {
					$data['main']               = $legacy_reference;
					$data['authors']            = array( $legacy_reference );
					$data['is_legacy_fallback'] = true;
				}
			}

			self::$authorship_cache[ $post_id ][ $cache_key ] = $data;

			return $data;
		}

		$main_values = $raw['main_values'];
		$authors     = $raw['author_rows'];
		$main        = 1 === count( $main_values ) ? (string) reset( $main_values ) : '';

		if ( self::is_valid_reference( $main ) ) {
			$normalized = self::normalize_authorship( $main, $authors );

			if ( ! is_wp_error( $normalized ) ) {
				$data['main']    = $normalized['main'];
				$data['authors'] = $normalized['authors'];
			}
		} else {
			$data['authors'] = self::normalize_author_references( $authors );
		}

		self::$authorship_cache[ $post_id ][ $cache_key ] = $data;

		return $data;
	}

	public static function get_main_author_ref( $post_id, $legacy_fallback = true ) {
		$authorship = self::get_authorship( $post_id, $legacy_fallback );

		return $authorship['main'];
	}

	public static function get_author_refs( $post_id, $legacy_fallback = true ) {
		$authorship = self::get_authorship( $post_id, $legacy_fallback );

		return $authorship['authors'];
	}

	public static function normalize_authorship( $main_author, $authors ) {
		$main_author = (string) $main_author;
		$authors     = is_array( $authors ) ? $authors : array();

		if ( '' !== $main_author && ! self::is_valid_reference( $main_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_main_author',
				__( 'The main author reference is invalid.', 'molongui-authorship' )
			);
		}

		$authors = self::normalize_author_references( $authors );

		if ( '' !== $main_author ) {
			$authors = array_values( array_diff( $authors, array( $main_author ) ) );
			array_unshift( $authors, $main_author );
		}

		return array(
			'main'    => $main_author,
			'authors' => $authors,
		);
	}

	public static function set_authorship( $post_id, $main_author, $authors, $context = 'update' ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_post',
				__( 'The post to update does not exist.', 'molongui-authorship' )
			);
		}

		$normalized = self::normalize_authorship( $main_author, $authors );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$old_authorship = self::get_authorship( $post_id, false );

		do_action( 'molongui_authorship/before_post_authorship_update', $post_id, $normalized, $old_authorship, $context );

		delete_post_meta( $post_id, self::MAIN_AUTHOR_META_KEY );
		add_post_meta( $post_id, self::MAIN_AUTHOR_META_KEY, $normalized['main'], true );

		delete_post_meta( $post_id, self::AUTHOR_META_KEY );

		foreach ( $normalized['authors'] as $author_ref ) {
			add_post_meta( $post_id, self::AUTHOR_META_KEY, $author_ref, false );
		}

		self::clear_request_cache( $post_id );

		do_action( 'molongui_authorship/after_post_authorship_update', $post_id, $normalized, $old_authorship, $context );

		return $normalized;
	}


	public static function set_main_author( $post_id, $main_author, $context = 'set_main_author' ) {
		if ( ! self::is_valid_reference( $main_author ) || ! self::author_exists( $main_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_main_author',
				__( 'The selected main author does not exist.', 'molongui-authorship' )
			);
		}

		$authorship = self::get_authorship( $post_id, true );

		return self::set_authorship( $post_id, $main_author, $authorship['authors'], $context );
	}

	public static function replace_author( $post_id, $removed_author, $replacement_author, $context = 'replace_author' ) {
		if ( ! self::is_valid_reference( $removed_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to replace is invalid.', 'molongui-authorship' )
			);
		}

		if ( ! self::is_valid_reference( $replacement_author ) || ! self::author_exists( $replacement_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_replacement_author',
				__( 'The replacement author does not exist.', 'molongui-authorship' )
			);
		}

		$authorship = self::get_authorship( $post_id, true );

		if ( $removed_author === $replacement_author ) {
			return array(
				'main'    => $authorship['main'],
				'authors' => $authorship['authors'],
			);
		}

		$authors = $authorship['authors'];

		if ( ! in_array( $removed_author, $authors, true ) && $authorship['main'] !== $removed_author ) {
			return array(
				'main'    => $authorship['main'],
				'authors' => $authors,
			);
		}

		$authors = array_values( array_diff( $authors, array( $replacement_author ) ) );
		$position = array_search( $removed_author, $authors, true );

		if ( false !== $position ) {
			$authors[ $position ] = $replacement_author;
		} else {
			array_unshift( $authors, $replacement_author );
		}

		$main_author = $authorship['main'] === $removed_author ? $replacement_author : $authorship['main'];

		return self::set_authorship( $post_id, $main_author, $authors, $context );
	}

	public static function remove_author( $post_id, $removed_author, $context = 'remove_author' ) {
		if ( ! self::is_valid_reference( $removed_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to remove is invalid.', 'molongui-authorship' )
			);
		}

		$authorship = self::get_authorship( $post_id, true );
		$authors    = array_values( array_diff( $authorship['authors'], array( $removed_author ) ) );
		$main_author = $authorship['main'] === $removed_author ? '' : $authorship['main'];

		return self::set_authorship( $post_id, $main_author, $authors, $context );
	}

	public static function apply_author_deletion_action( $removed_author, $action, $replacement_author = '', $context = 'author_deletion' ) {
		$removed_author     = (string) $removed_author;
		$removed_data       = self::parse_reference( $removed_author );
		$action             = sanitize_key( $action );
		$replacement_author = (string) $replacement_author;

		if ( ! $removed_data ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to delete is invalid.', 'molongui-authorship' )
			);
		}

		if ( ! in_array( $action, array( 'delete', 'reassign', 'remove' ), true ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_deletion_action',
				__( 'The requested author deletion action is invalid.', 'molongui-authorship' )
			);
		}

		if ( 'reassign' === $action ) {
			if (
				! self::is_valid_reference( $replacement_author )
				|| $removed_author === $replacement_author
				|| ! self::author_exists( $replacement_author )
			) {
				return new \WP_Error(
					'molongui_authorship_invalid_replacement_author',
					__( 'The replacement author does not exist or cannot be used.', 'molongui-authorship' )
				);
			}
		} else {
			$replacement_author = '';
		}

		$replacement_data = '' !== $replacement_author ? self::parse_reference( $replacement_author ) : false;
		$excluded_user_ids = 'user' === $removed_data['type'] ? array( $removed_data['id'] ) : array();
		$post_ids          = self::get_author_deletion_candidate_post_ids( $removed_author );
		$summary           = array(
			'action'               => $action,
			'removed_author'       => $removed_author,
			'replacement_author'   => $replacement_author,
			'processed_posts'      => array(),
			'editorial_posts'      => array(),
			'technical_only_posts' => array(),
			'deleted_posts'        => array(),
			'drafted_posts'        => array(),
			'legacy_posts'         => array(),
			'post_types'           => array(),
		);

		do_action( 'molongui_authorship/before_author_deletion_action', $removed_author, $action, $replacement_author, $context );

		if ( 'user' === $removed_data['type'] ) {
			do_action(
				'molongui_authorship/before_user_deletion_action',
				$removed_data['id'],
				$action,
				$replacement_author,
				$context
			);
		}

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$authorship = self::get_authorship( $post_id, true );
			$is_legacy  = self::STATE_LEGACY === $authorship['state'];
			$is_managed = ! $is_legacy || Post::is_post_type_enabled( $post->post_type, $post_id );

			if ( ! $is_managed ) {
				continue;
			}

			$is_main      = $authorship['main'] === $removed_author;
			$is_editorial = $is_main || in_array( $removed_author, $authorship['authors'], true );
			$is_native_owner = (
				'user' === $removed_data['type']
				&& (int) $post->post_author === (int) $removed_data['id']
			);

			if ( ! $is_editorial && ! $is_native_owner ) {
				continue;
			}

			$summary['processed_posts'][] = $post_id;

			if ( $is_legacy ) {
				$summary['legacy_posts'][] = $post_id;
			}

			if ( $is_editorial ) {
				$summary['editorial_posts'][] = $post_id;
				$summary['post_types'][]      = $post->post_type;
			} else {
				$summary['technical_only_posts'][] = $post_id;
			}

			if (
				$is_legacy
				&& 'user' === $removed_data['type']
				&& 'reassign' === $action
				&& $replacement_data
				&& 'user' === $replacement_data['type']
			) {
				continue;
			}

			if ( 'delete' === $action && $is_main ) {
				$new_authorship = self::remove_author( $post_id, $removed_author, $context );

				if ( is_wp_error( $new_authorship ) ) {
					return $new_authorship;
				}

				$current_post_author = absint( $post->post_author );

				if ( ! self::is_usable_user_id( $current_post_author, $excluded_user_ids ) ) {
					$synced_owner = self::sync_post_author( $post_id, $new_authorship['main'], $context, $excluded_user_ids );

					if ( is_wp_error( $synced_owner ) ) {
						return $synced_owner;
					}
				}

				$preserved_post_author = self::is_usable_user_id( $current_post_author, $excluded_user_ids )
					? $current_post_author
					: 0;
				$preserve_native_owner = null;

				if ( $preserved_post_author ) {
					$preserve_native_owner = function ( $fallback_user_id, $filtered_post_id ) use ( $post_id, $preserved_post_author ) {
						if ( absint( $filtered_post_id ) === $post_id ) {
							return $preserved_post_author;
						}

						return $fallback_user_id;
					};

					add_filter( 'molongui_authorship/post_author_fallback_user_id', $preserve_native_owner, 1, 2 );
				}

				try {
					$deleted = wp_delete_post( $post_id );
				} finally {
					if ( $preserve_native_owner ) {
						remove_filter( 'molongui_authorship/post_author_fallback_user_id', $preserve_native_owner, 1 );
					}
				}

				if ( false === $deleted || null === $deleted ) {
					return new \WP_Error(
						'molongui_authorship_post_delete_failed',
						__( 'One or more posts authored by the deleted author could not be deleted.', 'molongui-authorship' ),
						array( 'post_id' => $post_id )
					);
				}

				$summary['deleted_posts'][] = $post_id;
				continue;
			}

			$new_authorship = $authorship;

			if ( 'reassign' === $action && $is_editorial ) {
				$new_authorship = self::replace_author( $post_id, $removed_author, $replacement_author, $context );
			} elseif ( ( 'remove' === $action || 'delete' === $action ) && $is_editorial ) {
				if ( $is_main && self::post_status_requires_main_author( $post->post_status ) ) {
					$drafted = wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						),
						true
					);

					if ( is_wp_error( $drafted ) ) {
						return $drafted;
					}

					$summary['drafted_posts'][] = $post_id;
				}

				$new_authorship = self::remove_author( $post_id, $removed_author, $context );
			}

			if ( is_wp_error( $new_authorship ) ) {
				return $new_authorship;
			}

			$current_post_author = absint( get_post_field( 'post_author', $post_id ) );
			$main_changed        = $new_authorship['main'] !== $authorship['main'];
			$new_main_data       = self::parse_reference( $new_authorship['main'] );
			$sync_native_owner   = $is_native_owner;

			if ( $main_changed ) {
				if ( $new_main_data && 'user' === $new_main_data['type'] ) {
					$sync_native_owner = true;
				} elseif ( ! self::is_usable_user_id( $current_post_author, $excluded_user_ids ) ) {
					$sync_native_owner = true;
				}
			}

			if ( $sync_native_owner ) {
				$synced_owner = self::sync_post_author( $post_id, $new_authorship['main'], $context, $excluded_user_ids );

				if ( is_wp_error( $synced_owner ) ) {
					return $synced_owner;
				}
			}
		}

		$summary_post_keys = array(
			'processed_posts',
			'editorial_posts',
			'technical_only_posts',
			'deleted_posts',
			'drafted_posts',
			'legacy_posts',
		);

		foreach ( $summary as $key => $values ) {
			if ( is_array( $values ) && in_array( $key, $summary_post_keys, true ) ) {
				$summary[ $key ] = array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
			}
		}

		$summary['post_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $summary['post_types'] ) ) ) );

		do_action( 'molongui_authorship/after_author_deletion_action', $summary, $context );

		if ( 'user' === $removed_data['type'] ) {
			do_action( 'molongui_authorship/after_user_deletion_action', $summary, $context );
		}

		return $summary;
	}

	public static function apply_user_deletion_action( $user_id, $action, $replacement_author = '', $context = 'wordpress_user_deletion' ) {
		$user_id        = absint( $user_id );
		$removed_author = self::build_reference( $user_id, 'user' );

		if ( ! $user_id || ! self::is_valid_reference( $removed_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to delete is invalid.', 'molongui-authorship' )
			);
		}

		return self::apply_author_deletion_action( $removed_author, $action, $replacement_author, $context );
	}

	public static function apply_guest_deletion_action( $guest_id, $action, $replacement_author = '', $context = 'guest_author_deletion' ) {
		$guest_id        = absint( $guest_id );
		$removed_author = self::build_reference( $guest_id, 'guest' );

		if ( ! $guest_id || ! self::is_valid_reference( $removed_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to delete is invalid.', 'molongui-authorship' )
			);
		}

		return self::apply_author_deletion_action( $removed_author, $action, $replacement_author, $context );
	}

	public static function apply_author_deletion( $removed_author, $replacement_author = '', $context = 'author_deletion' ) {
		if ( ! self::is_valid_reference( $removed_author ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_removed_author',
				__( 'The author reference to delete is invalid.', 'molongui-authorship' )
			);
		}

		return self::apply_author_deletion_action(
			$removed_author,
			'' !== (string) $replacement_author ? 'reassign' : 'remove',
			$replacement_author,
			$context
		);
	}

	private static function get_author_deletion_candidate_post_ids( $author_ref ) {
		global $wpdb;

		$parsed = self::parse_reference( $author_ref );

		if ( ! $parsed ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$editorial_post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key IN ( %s, %s )
				AND meta_value = %s",
				self::MAIN_AUTHOR_META_KEY,
				self::AUTHOR_META_KEY,
				$author_ref
			)
		);

		$post_ids = array_map( 'absint', (array) $editorial_post_ids );

		if ( 'user' === $parsed['type'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$native_post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID
					FROM {$wpdb->posts}
					WHERE post_author = %d",
					$parsed['id']
				)
			);

			$post_ids = array_merge( $post_ids, array_map( 'absint', (array) $native_post_ids ) );
		}

		return array_values( array_unique( array_filter( $post_ids ) ) );
	}

	public static function author_has_explicit_relations( $author_ref ) {
		global $wpdb;

		if ( ! self::is_valid_reference( $author_ref ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key IN ( %s, %s )
				AND meta_value = %s
				LIMIT 1",
				self::MAIN_AUTHOR_META_KEY,
				self::AUTHOR_META_KEY,
				$author_ref
			)
		);

		return ! empty( $post_id );
	}

	public static function get_unmanaged_native_post_ids( $user_id, $authored_content_only = false ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type
				FROM {$wpdb->posts}
				WHERE post_author = %d",
				$user_id
			)
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$post_ids = array_map( 'absint', wp_list_pluck( $rows, 'ID' ) );
		update_meta_cache( 'post', $post_ids );

		$unmanaged = array();

		foreach ( $rows as $row ) {
			$post_id   = absint( $row->ID );
			$post_type = sanitize_key( $row->post_type );

			if ( self::STATE_LEGACY !== self::get_state( $post_id ) || Post::is_post_type_enabled( $post_type, $post_id ) ) {
				continue;
			}

			if ( $authored_content_only ) {
				if ( Guest_Author::get_post_type() === $post_type ) {
					continue;
				}

				$post_type_object = get_post_type_object( $post_type );
				$delete_with_user = $post_type_object && true === $post_type_object->delete_with_user;

				if ( ! $delete_with_user && ! post_type_supports( $post_type, 'author' ) ) {
					continue;
				}
			}

			$unmanaged[] = $post_id;
		}

		return array_values( array_unique( array_filter( $unmanaged ) ) );
	}

	public static function has_unmanaged_native_authored_content( $user_id ) {
		return ! empty( self::get_unmanaged_native_post_ids( $user_id, true ) );
	}


	public static function initialize_legacy( $post_id, $context = 'legacy_initialization' ) {
		$post_id = absint( $post_id );

		if ( self::STATE_LEGACY !== self::get_state( $post_id ) ) {
			return new \WP_Error(
				'molongui_authorship_not_legacy',
				__( 'The post already contains Molongui authorship data and cannot be initialized as legacy content.', 'molongui-authorship' )
			);
		}

		$initialize = apply_filters( 'molongui_authorship/initialize_legacy_post', true, $post_id, $context );

		if ( ! $initialize ) {
			return new \WP_Error(
				'molongui_authorship_legacy_initialization_skipped',
				__( 'Legacy authorship initialization was skipped.', 'molongui-authorship' )
			);
		}

		$reference = self::get_legacy_reference( $post_id );

		if ( ! $reference ) {
			return new \WP_Error(
				'molongui_authorship_missing_legacy_author',
				__( 'The post does not have a valid WordPress author to initialize from.', 'molongui-authorship' )
			);
		}

		return self::set_authorship( $post_id, $reference, array( $reference ), $context );
	}

	public static function resolve_post_author( $post_id, $main_author = '', $context = 'update', $exclude_user_ids = array() ) {
		$post_id          = absint( $post_id );
		$exclude_user_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $exclude_user_ids ) ) ) );
		$parsed_main      = self::parse_reference( $main_author );

		if ( $parsed_main && 'user' === $parsed_main['type'] && self::is_usable_user_id( $parsed_main['id'], $exclude_user_ids ) ) {
			return $parsed_main['id'];
		}

		$filtered_user_id = apply_filters(
			'molongui_authorship/post_author_fallback_user_id',
			0,
			$post_id,
			(string) $main_author,
			$context,
			$exclude_user_ids
		);

		$filtered_user_id = absint( $filtered_user_id );

		if ( self::is_usable_user_id( $filtered_user_id, $exclude_user_ids ) ) {
			return $filtered_user_id;
		}

		$current_user_id = get_current_user_id();

		if ( self::is_usable_user_id( $current_user_id, $exclude_user_ids ) ) {
			return $current_user_id;
		}

		return self::get_administrative_fallback_user_id( $exclude_user_ids );
	}

	public static function sync_post_author( $post_id, $main_author = null, $context = 'update', $exclude_user_ids = array() ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return new \WP_Error(
				'molongui_authorship_invalid_post',
				__( 'The post to update does not exist.', 'molongui-authorship' )
			);
		}

		if ( null === $main_author ) {
			$main_author = self::get_main_author_ref( $post_id, true );
		}

		$user_id = self::resolve_post_author( $post_id, $main_author, $context, $exclude_user_ids );

		if ( ! $user_id ) {
			return new \WP_Error(
				'molongui_authorship_missing_post_author_fallback',
				__( 'No valid WordPress user is available to own the post.', 'molongui-authorship' )
			);
		}

		$current_post_author = absint( get_post_field( 'post_author', $post_id ) );

		if ( $current_post_author === $user_id ) {
			return $user_id;
		}

		if ( ! empty( self::$syncing_post_author[ $post_id ] ) ) {
			return $user_id;
		}

		self::$syncing_post_author[ $post_id ] = true;

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_author' => $user_id,
			),
			true
		);

		unset( self::$syncing_post_author[ $post_id ] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		do_action( 'molongui_authorship/post_author_synchronized', $post_id, $user_id, $current_post_author, $main_author, $context );

		return $user_id;
	}

	public static function is_publishable_authorship( $main_author, $authors ) {
		$normalized = self::normalize_authorship( $main_author, $authors );

		if ( is_wp_error( $normalized ) || empty( $normalized['main'] ) ) {
			return false;
		}

		if ( ! in_array( $normalized['main'], $normalized['authors'], true ) ) {
			return false;
		}

		return self::author_exists( $normalized['main'] );
	}

	public static function has_publishable_authorship( $post_id ) {
		$authorship = self::get_authorship( $post_id, true );

		return self::is_publishable_authorship( $authorship['main'], $authorship['authors'] );
	}

	public static function build_reference( $author_id, $author_type ) {
		$author_id   = absint( $author_id );
		$author_type = strtolower( (string) $author_type );

		if ( ! $author_id || ! in_array( $author_type, array( 'user', 'guest' ), true ) ) {
			return '';
		}

		return $author_type . '-' . $author_id;
	}

	public static function parse_reference( $author_ref ) {
		$author_ref = (string) $author_ref;

		if ( ! preg_match( '/^(user|guest)-([1-9][0-9]*)$/', $author_ref, $matches ) ) {
			return false;
		}

		return array(
			'type' => $matches[1],
			'id'   => absint( $matches[2] ),
			'ref'  => $author_ref,
		);
	}

	public static function is_valid_reference( $author_ref ) {
		return false !== self::parse_reference( $author_ref );
	}

	public static function author_exists( $author_ref ) {
		$parsed = self::parse_reference( $author_ref );

		if ( ! $parsed ) {
			return false;
		}

		if ( 'user' === $parsed['type'] ) {
			return false !== self::get_native_user_data( 'id', $parsed['id'] );
		}

		$guest = get_post( $parsed['id'] );

		return $guest instanceof \WP_Post && Guest_Author::get_post_type() === $guest->post_type;
	}

	private static function get_legacy_reference( $post_id ) {
		$post_author = absint( get_post_field( 'post_author', $post_id ) );

		if ( ! self::is_usable_user_id( $post_author ) ) {
			return '';
		}

		return self::build_reference( $post_author, 'user' );
	}

	private static function normalize_author_references( $authors ) {
		$normalized = array();

		foreach ( (array) $authors as $author_ref ) {
			$author_ref = (string) $author_ref;

			if ( ! self::is_valid_reference( $author_ref ) || in_array( $author_ref, $normalized, true ) ) {
				continue;
			}

			$normalized[] = $author_ref;
		}

		return $normalized;
	}

	private static function is_usable_user_id( $user_id, $exclude_user_ids = array() ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || in_array( $user_id, (array) $exclude_user_ids, true ) ) {
			return false;
		}

		return false !== self::get_native_user_data( 'id', $user_id );
	}

	private static function get_native_user_data( $field, $value ) {
		return \WP_User::get_data_by( $field, $value );
	}

	private static function get_administrative_fallback_user_id( $exclude_user_ids = array() ) {
		$args = array(
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
			'fields'  => 'ID',
			'exclude' => array_values( array_filter( array_map( 'absint', (array) $exclude_user_ids ) ) ),
		);

		if ( is_multisite() ) {
			$args['blog_id'] = get_current_blog_id();
		}

		$administrator_ids = get_users( $args );

		foreach ( $administrator_ids as $administrator_id ) {
			if ( self::is_usable_user_id( $administrator_id, $exclude_user_ids ) ) {
				return absint( $administrator_id );
			}
		}

		if ( is_multisite() && function_exists( 'get_super_admins' ) ) {
			$super_admin_ids = array();

			foreach ( (array) get_super_admins() as $login ) {
				$userdata = self::get_native_user_data( 'login', $login );

				if ( $userdata && self::is_usable_user_id( $userdata->ID, $exclude_user_ids ) ) {
					$super_admin_ids[] = absint( $userdata->ID );
				}
			}

			if ( ! empty( $super_admin_ids ) ) {
				sort( $super_admin_ids, SORT_NUMERIC );
				return reset( $super_admin_ids );
			}
		}

		return 0;
	}
}
