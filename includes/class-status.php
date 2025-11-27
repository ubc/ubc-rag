<?php

namespace UBC\RAG;

/**
 * Status class.
 */
class Status {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private static $table_name;

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private static function get_table_name() {
		if ( ! isset( self::$table_name ) ) {
			global $wpdb;
			self::$table_name = $wpdb->prefix . 'rag_index_status';
		}
		return self::$table_name;
	}

	/**
	 * Set status for a content item.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @param string $status       Status (queued, processing, completed, failed).
	 * @param array  $data         Additional data to update.
	 * @return int|false The number of rows updated, or false on error.
	 */
	public static function set_status( $content_id, $content_type, $status, $data = [] ) {
		global $wpdb;

		$table = self::get_table_name();
		$now   = current_time( 'mysql' );

		// Check if record exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE content_id = %d AND content_type = %s",
				$content_id,
				$content_type
			)
		);

		$defaults = [
			'status'     => $status,
			'updated_at' => $now,
		];

		$update_data = array_merge( $defaults, $data );

		if ( $exists ) {
			return $wpdb->update(
				$table,
				$update_data,
				[
					'content_id'   => $content_id,
					'content_type' => $content_type,
				]
			);
		} else {
			// For new records, we need some required fields if not provided.
			$insert_data = array_merge(
				[
					'content_id'           => $content_id,
					'content_type'         => $content_type,
					'content_hash'         => '',
					'chunking_strategy'    => '',
					'embedding_model'      => '',
					'embedding_dimensions' => 0,
					'created_at'           => $now,
				],
				$update_data
			);

			return $wpdb->insert( $table, $insert_data );
		}
	}

	/**
	 * Get status for a content item.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @return object|null Status row or null.
	 */
	public static function get_status( $content_id, $content_type ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE content_id = %d AND content_type = %s",
				$content_id,
				$content_type
			)
		);
	}

	/**
	 * Delete status record for a content item.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @return int|false The number of rows deleted, or false on error.
	 */
	public static function delete_status( $content_id, $content_type ) {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->delete(
			$table,
			[
				'content_id'   => $content_id,
				'content_type' => $content_type,
			],
			[ '%d', '%s' ]
		);
	}

	/**
	 * Get statistics.
	 *
	 * @return array
	 */
	public static function get_statistics() {
		global $wpdb;
		$table = self::get_table_name();

		$stats = [
			'total'      => 0,
			'indexed'    => 0,
			'processing' => 0,
			'failed'     => 0,
			'queued'     => 0,
		];

		// Check if table exists first.
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			return $stats;
		}

		$results = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM $table GROUP BY status" );

		foreach ( $results as $row ) {
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = (int) $row->count;
			}
			$stats['total'] += (int) $row->count;
		}

		return $stats;
	}
}
