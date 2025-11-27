<?php

namespace UBC\RAG;

/**
 * Retry Queue Manager
 * Handles retrying failed embedding jobs with exponential backoff.
 */
class Retry_Queue {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'rag_plugin_retry_item', [ $this, 'process_retry' ], 10, 5 );
	}

	/**
	 * Queue an item for retry.
	 *
	 * @param int    $content_id    Content ID.
	 * @param string $content_type  Content type.
	 * @param int    $attempt_count Current attempt count (starts at 1).
	 * @param string $error_message Error message from failure.
	 * @return int|null Action ID or null.
	 */
	public static function queue_retry( $content_id, $content_type, $attempt_count = 1, $error_message = '' ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			Logger::log( 'ActionScheduler not available for retry' );
			return null;
		}

		// Exponential backoff: 5 min, 15 min, 60 min, 4 hours
		$backoff_map = [
			1 => 300,      // 5 minutes
			2 => 900,      // 15 minutes
			3 => 3600,     // 1 hour
			4 => 14400,    // 4 hours
		];

		$delay = isset( $backoff_map[ $attempt_count ] )
			? $backoff_map[ $attempt_count ]
			: 86400; // 24 hours max

		$retry_time = time() + $delay;
		$site_id = get_current_blog_id();

		Logger::log(
			sprintf(
				'Queuing retry for %s %d (attempt %d) in %d seconds. Error: %s',
				$content_type,
				$content_id,
				$attempt_count,
				$delay,
				substr( $error_message, 0, 100 )
			)
		);

		// Use different group for retries to avoid blocking main queue
		$retry_group = 'rag_retry_site_' . $site_id;

		return as_schedule_single_action(
			$retry_time,
			'rag_plugin_retry_item',
			[
				'site_id'         => $site_id,
				'content_id'      => $content_id,
				'content_type'    => $content_type,
				'attempt_count'   => $attempt_count,
				'original_error'  => $error_message,
			],
			$retry_group
		);
	}

	/**
	 * Process a retry attempt.
	 *
	 * @param int    $site_id           Site ID.
	 * @param int    $content_id        Content ID.
	 * @param string $content_type      Content type.
	 * @param int    $attempt_count     Attempt count.
	 * @param string $original_error    Original error message.
	 * @return void
	 */
	public function process_retry( $site_id, $content_id, $content_type, $attempt_count, $original_error ) {
		Logger::log(
			sprintf(
				'Processing retry for %s %d (attempt %d)',
				$content_type,
				$content_id,
				$attempt_count
			)
		);

		// Mark as queued again (so it's retried).
		Status::set_status( $content_id, $content_type, 'queued' );

		// Queue the original index job (will process from scratch).
		Queue::push( $content_id, $content_type, 'update' );

		Logger::log( "Retry job re-queued for $content_type $content_id" );
	}

	/**
	 * Get failed items from the current site.
	 *
	 * @return array Array of failed status records.
	 */
	public static function get_failed_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'rag_index_status';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE status = %s ORDER BY updated_at DESC",
				'failed'
			),
			ARRAY_A
		);
	}

	/**
	 * Retry a specific failed item immediately.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content type.
	 * @return int|null Action ID.
	 */
	public static function retry_now( $content_id, $content_type ) {
		Logger::log( "Immediate retry requested for $content_type $content_id" );

		Status::set_status( $content_id, $content_type, 'queued' );

		return Queue::push( $content_id, $content_type, 'update' );
	}

	/**
	 * Retry all failed items on this site.
	 *
	 * @return int Number of items queued for retry.
	 */
	public static function retry_all_failed() {
		$failed = self::get_failed_items();
		$count = 0;

		foreach ( $failed as $item ) {
			if ( self::retry_now( (int) $item['content_id'], $item['content_type'] ) ) {
				$count++;
			}
		}

		Logger::log( "Queued $count failed items for retry" );
		return $count;
	}

	/**
	 * Get count of failed items.
	 *
	 * @return int
	 */
	public static function get_failed_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'rag_index_status';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE status = %s",
				'failed'
			)
		);
	}
}
