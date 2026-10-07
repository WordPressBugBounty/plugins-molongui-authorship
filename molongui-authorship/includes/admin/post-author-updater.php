<?php
/*!
 * Handles the background process for updating post authorship metadata.
 *
 * This file defines the logic for updating post authorship metadata, such as guest and co-authors, ensuring the correct
 * postmeta fields are added for each post to support accurate author archive pages and queries.
 *
 * @author     Molongui
 * @package    Authorship
 * @subpackage includes/admin
 * @since      4.5.0
 */

namespace Molongui\Authorship\Admin;

use Molongui\Authorship\Common\Libraries\WP_Background_Process;
use Molongui\Authorship\Common\Utils\Singleton;
use Molongui\Authorship\Post;
use Molongui\Authorship\Post_Authorship;
use Molongui\Authorship\Settings;

defined( 'ABSPATH' ) || exit;  

class Post_Author_Updater extends WP_Background_Process
{






	public $prefix = 'molongui_authorship';

	public $action = 'update_post_authorship';

	const TRIGGER_OPTION = 'molongui_authorship_update_post_authors';

	const RUNNING_OPTION = 'molongui_authorship_update_post_authorship_running';

	const COMPLETE_OPTION = 'molongui_authorship_update_post_authorship_complete';

	const ERROR_OPTION = 'molongui_authorship_update_post_authorship_error';

	const PROGRESS_OPTION = 'molongui_authorship_update_post_authorship_progress';

	const DEFAULT_BATCH_SIZE = 100;

	protected $post_types = array();

	protected $post_statuses = array();

	use Singleton;

	protected function __construct() {
		if ( ! apply_filters( 'molongui_authorship/enable_post_author_updater', true ) ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'enable_admin_init' ), 9 );
		add_action( 'admin_init', array( $this, 'enable_ajax_request' ) );
		add_action( 'admin_notices', array( $this, 'task_status_notice' ) );

		parent::__construct();
	}


	public function enable_admin_init() {
		$this->handle_admin_init();
	}

	public function handle_admin_init() {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( get_option( self::TRIGGER_OPTION ) ) {
			$result = $this->run();

			if ( ! is_wp_error( $result ) && false !== $result ) {
				delete_option( self::TRIGGER_OPTION );
			}

			return;
		}

		$this->maybe_resume();
	}

	public function enable_ajax_request() {
		add_action( 'wp_ajax_molongui_authorship_update_post_authors', array( $this, 'handle_ajax_request' ) );
	}

	public function handle_ajax_request() {
		check_ajax_referer( 'molongui_authorship_post_author_updater_nonce', 'nonce', true );

		$result = $this->run();

		echo wp_json_encode( is_wp_error( $result ) ? 'false' : $result );
		wp_die();
	}

	public function run() {
		$this->post_types    = self::get_post_types();
		$this->post_statuses = self::get_post_statuses( $this->post_types );

		if ( ! $this->is_queue_empty() ) {
			if ( ! get_option( self::TRIGGER_OPTION ) ) {
				$this->push_to_queue(
					array(
						'cursor'        => 0,
						'processed'     => 0,
						'skipped'       => 0,
						'post_types'    => $this->post_types,
						'post_statuses' => $this->post_statuses,
					)
				);
				$this->save();
				$this->data = array();
			}

			return $this->dispatch();
		}

		if ( ! $this->has_legacy_posts() ) {
			$this->clear_process_state();

			return true;
		}

		$this->reset_progress();

		$this->push_to_queue(
			array(
				'cursor'        => 0,
				'processed'     => 0,
				'skipped'       => 0,
				'post_types'    => $this->post_types,
				'post_statuses' => $this->post_statuses,
			)
		);

		$this->save();
		$this->data = array();

		return $this->dispatch();
	}

	protected function task( $item ) {
		if ( is_numeric( $item ) ) {
			$post_id = absint( $item );
			$result  = $post_id
				? Post_Authorship::initialize_legacy( $post_id, 'legacy_background_update' )
				: new \WP_Error( 'molongui_authorship_invalid_post', __( 'The post to initialize is invalid.', 'molongui-authorship' ) );

			if ( is_wp_error( $result ) ) {
				$this->update_progress( array( 'cursor' => $post_id ), 0, 1 );
				do_action( 'molongui_authorship/post_author_updater_item_error', $post_id, $result, array() );
			} else {
				$this->update_progress( array( 'cursor' => $post_id ), 1, 0 );
			}

			return false;
		}

		$item = $this->normalize_cursor_item( $item );

		$this->post_types    = $item['post_types'];
		$this->post_statuses = $item['post_statuses'];

		$batch_size = $this->get_batch_size();
		$post_ids   = $this->get_legacy_post_ids_after(
			$item['cursor'],
			$batch_size
		);

		if ( empty( $post_ids ) ) {
			return false;
		}

		$processed_this_step = 0;
		$skipped_this_step   = 0;

		foreach ( $post_ids as $post_id ) {
			$result = Post_Authorship::initialize_legacy( $post_id, 'legacy_background_update' );

			$item['cursor'] = max( $item['cursor'], $post_id );

			if ( is_wp_error( $result ) ) {
				++$item['skipped'];
				++$skipped_this_step;

				do_action( 'molongui_authorship/post_author_updater_item_error', $post_id, $result, $item );
			} else {
				++$item['processed'];
				++$processed_this_step;
			}
		}

		$this->update_progress( $item, $processed_this_step, $skipped_this_step );

		if ( count( $post_ids ) < $batch_size ) {
			return false;
		}

		return $item;
	}

	public function dispatch() {
		if ( $this->should_use_wp_cron_healthcheck() ) {
			$result = parent::dispatch();
		} else {
			$this->clear_scheduled_event();

			$url  = add_query_arg( $this->get_query_args(), $this->get_query_url() );
			$args = $this->get_post_args();

			$args = apply_filters( 'molongui_authorship/post_author_updater_async_request_args', $args, $url );

			$result = wp_remote_post( esc_url_raw( $url ), $args );
		}

		if ( is_wp_error( $result ) ) {
			$this->record_dispatch_error( $result );

			return $result;
		}

		delete_option( self::ERROR_OPTION );
		update_option( self::RUNNING_OPTION, true, false );

		return $result;
	}

	protected function complete() {
		parent::complete();

		delete_option( self::TRIGGER_OPTION );
		delete_option( self::RUNNING_OPTION );
		delete_option( self::ERROR_OPTION );

		$progress                  = $this->get_progress();
		$progress['completed_at']  = time();
		$progress['last_activity'] = time();

		update_option( self::PROGRESS_OPTION, $progress, false );
		update_option( self::COMPLETE_OPTION, true, false );

		do_action( 'molongui_authorship/post_author_updater_complete', $progress );
	}

	protected function maybe_resume() {
		if ( $this->is_process_running() ) {
			return;
		}

		if ( $this->is_queue_empty() ) {
			if ( get_option( self::RUNNING_OPTION ) ) {
				$this->complete();
			}

			return;
		}

		$this->dispatch();
	}

	public function task_status_notice() {
		if ( get_option( self::COMPLETE_OPTION ) ) {
			$progress  = $this->get_progress();
			$processed = isset( $progress['processed'] ) ? absint( $progress['processed'] ) : 0;
			$skipped   = isset( $progress['skipped'] ) ? absint( $progress['skipped'] ) : 0;

			delete_option( self::COMPLETE_OPTION );
			delete_option( self::PROGRESS_OPTION );

			if ( $skipped ) {
				$message = sprintf(
					__( '%1$sAuthorship Data Updater%2$s – Initialization completed. %3$d posts were initialized and %4$d posts were skipped because they could not be safely initialized.', 'molongui-authorship' ),
					'<strong>',
					'</strong>',
					$processed,
					$skipped
				);

				echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';

				return;
			}

			$message = sprintf(
				__( '%1$sAuthorship Data Updater%2$s – Initialization completed. %3$d posts were initialized.', 'molongui-authorship' ),
				'<strong>',
				'</strong>',
				$processed
			);

			echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';

			return;
		}

		$error_message = get_option( self::ERROR_OPTION );

		if ( $error_message ) {
			$message = sprintf(
				__( '%1$sAuthorship Data Updater%2$s – The background worker could not be dispatched (%3$s). Molongui will retry automatically on the next admin request.', 'molongui-authorship' ),
				'<strong>',
				'</strong>',
				esc_html( $error_message )
			);

			echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';

			return;
		}

		if ( get_option( self::RUNNING_OPTION ) ) {
			$progress  = $this->get_progress();
			$processed = isset( $progress['processed'] ) ? absint( $progress['processed'] ) : 0;

			$message = sprintf(
				__( '%1$sAuthorship Data Updater%2$s – Legacy post authorship is being initialized in the background. %3$d posts have been initialized so far.', 'molongui-authorship' ),
				'<strong>',
				'</strong>',
				$processed
			);

			echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		}
	}


	public static function get_post_types() {
		$post_types = apply_filters( 'molongui_authorship/post_types_for_authorship_updater', array() );

		if ( empty( $post_types ) ) {
			$post_types = 'enabled';
		}

		if ( is_string( $post_types ) ) {
			switch ( $post_types ) {
				case 'all':
					$post_types = Post::get_post_types();
					break;

				case 'enabled':
					$post_types = Settings::enabled_post_types();
					break;

				default:
					$post_types = array( $post_types );
					break;
			}

			if ( empty( $post_types ) ) {
				$post_types = array( 'post', 'page' );
			} else {
				$post_types = array_unique( array_merge( $post_types, array( 'post', 'page' ) ) );
			}
		}

		$post_types = array_filter( array_unique( array_map( 'sanitize_key', (array) $post_types ) ) );

		return array_values( $post_types );
	}

	public static function get_post_statuses( $post_types ) {
		$post_statuses = Post::get_public_post_status( $post_types );

		$post_statuses = apply_filters(
			'molongui_authorship/post_author_updater_post_statuses',
			$post_statuses,
			$post_types
		);

		$post_statuses = array_filter( array_unique( array_map( 'sanitize_key', (array) $post_statuses ) ) );

		return array_values( $post_statuses );
	}

	protected function get_batch_size() {
		$batch_size = absint(
			apply_filters(
				'molongui_authorship/post_author_updater_batch_size',
				self::DEFAULT_BATCH_SIZE
			)
		);

		if ( ! $batch_size ) {
			$batch_size = self::DEFAULT_BATCH_SIZE;
		}

		return $batch_size;
	}

	protected function has_legacy_posts() {
		return ! empty( $this->get_legacy_post_ids_after( 0, 1 ) );
	}

	protected function get_legacy_post_ids_after( $after_post_id, $limit ) {
		global $wpdb;

		$after_post_id = absint( $after_post_id );
		$limit         = max( 1, absint( $limit ) );

		if ( empty( $this->post_types ) ) {
			$this->post_types = self::get_post_types();
		}

		if ( empty( $this->post_statuses ) ) {
			$this->post_statuses = self::get_post_statuses( $this->post_types );
		}

		if ( empty( $this->post_types ) || empty( $this->post_statuses ) ) {
			return array();
		}

		$post_type_placeholders   = implode( ', ', array_fill( 0, count( $this->post_types ), '%s' ) );
		$post_status_placeholders = implode( ', ', array_fill( 0, count( $this->post_statuses ), '%s' ) );

		$sql = "
			SELECT p.ID
			FROM {$wpdb->posts} AS p
			LEFT JOIN {$wpdb->postmeta} AS main_meta
				ON main_meta.post_id = p.ID
				AND main_meta.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} AS author_meta
				ON author_meta.post_id = p.ID
				AND author_meta.meta_key = %s
			WHERE p.ID > %d
				AND main_meta.meta_id IS NULL
				AND author_meta.meta_id IS NULL
				AND p.post_type IN ({$post_type_placeholders})
				AND p.post_status IN ({$post_status_placeholders})
			ORDER BY p.ID ASC
			LIMIT %d
		";

		$prepare_args = array_merge(
			array(
				Post_Authorship::MAIN_AUTHOR_META_KEY,
				Post_Authorship::AUTHOR_META_KEY,
				$after_post_id,
			),
			$this->post_types,
			$this->post_statuses,
			array( $limit )
		);

		$prepared_sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $prepare_args ) );

		$post_ids = $wpdb->get_col( $prepared_sql );

		return array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );
	}


	protected function should_use_wp_cron_healthcheck() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$check    = apply_filters( 'authorship/check_wp_cron', true );
		$use_cron = ! ( $check && $disabled );

		return (bool) apply_filters(
			'molongui_authorship/post_author_updater_use_wp_cron_healthcheck',
			$use_cron,
			$disabled
		);
	}

	protected function normalize_cursor_item( $item ) {
		$item = is_array( $item ) ? $item : array();

		$post_types = isset( $item['post_types'] ) ? (array) $item['post_types'] : self::get_post_types();
		$post_types = array_values( array_filter( array_unique( array_map( 'sanitize_key', $post_types ) ) ) );

		$post_statuses = isset( $item['post_statuses'] )
			? (array) $item['post_statuses']
			: self::get_post_statuses( $post_types );
		$post_statuses = array_values( array_filter( array_unique( array_map( 'sanitize_key', $post_statuses ) ) ) );

		return array(
			'cursor'        => isset( $item['cursor'] ) ? absint( $item['cursor'] ) : 0,
			'processed'     => isset( $item['processed'] ) ? absint( $item['processed'] ) : 0,
			'skipped'       => isset( $item['skipped'] ) ? absint( $item['skipped'] ) : 0,
			'post_types'    => $post_types,
			'post_statuses' => $post_statuses,
		);
	}

	protected function reset_progress() {
		$now = time();

		update_option(
			self::PROGRESS_OPTION,
			array(
				'cursor'        => 0,
				'processed'     => 0,
				'skipped'       => 0,
				'started_at'    => $now,
				'last_activity' => $now,
			),
			false
		);

		delete_option( self::COMPLETE_OPTION );
		delete_option( self::ERROR_OPTION );
	}

	protected function update_progress( $item, $processed_increment = 0, $skipped_increment = 0 ) {
		$progress = $this->get_progress();

		$progress['cursor']        = isset( $item['cursor'] ) ? absint( $item['cursor'] ) : 0;
		$progress['processed']     = ( isset( $progress['processed'] ) ? absint( $progress['processed'] ) : 0 ) + absint( $processed_increment );
		$progress['skipped']       = ( isset( $progress['skipped'] ) ? absint( $progress['skipped'] ) : 0 ) + absint( $skipped_increment );
		$progress['last_activity'] = time();

		update_option( self::PROGRESS_OPTION, $progress, false );
	}

	public function get_progress() {
		$progress = get_option( self::PROGRESS_OPTION, array() );

		return is_array( $progress ) ? $progress : array();
	}

	protected function record_dispatch_error( $error ) {
		update_option( self::ERROR_OPTION, $error->get_error_message(), false );
		delete_option( self::RUNNING_OPTION );

		do_action( 'molongui_authorship/post_author_updater_dispatch_error', $error );
	}

	protected function clear_process_state( $clear_progress = true ) {
		$this->clear_scheduled_event();

		delete_option( self::RUNNING_OPTION );
		delete_option( self::ERROR_OPTION );
		delete_option( self::COMPLETE_OPTION );

		if ( $clear_progress ) {
			delete_option( self::PROGRESS_OPTION );
		}
	}


	public static function set_default_authorship_meta( $post_id ) {
		$result = Post_Authorship::initialize_legacy( $post_id, 'legacy_background_update' );

		return ! is_wp_error( $result );
	}

}  

Post_Author_Updater::instance();
