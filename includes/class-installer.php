<?php

namespace UBC\RAG;

/**
 * Installer class.
 */
class Installer {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
	}

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Do nothing for now. We don't want to delete data on deactivation.
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table: rag_index_status
		$table_status = $wpdb->prefix . 'rag_index_status';
		$sql_status = "CREATE TABLE $table_status (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			content_id BIGINT UNSIGNED NOT NULL,
			content_type VARCHAR(50) NOT NULL,
			content_hash VARCHAR(64) NOT NULL,
			chunking_strategy VARCHAR(50) NOT NULL,
			chunking_settings TEXT,
			embedding_model VARCHAR(100) NOT NULL,
			embedding_dimensions INT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL,
			vector_ids TEXT,
			chunk_count INT UNSIGNED DEFAULT 0,
			last_indexed_at DATETIME,
			error_message TEXT,
			retry_count TINYINT UNSIGNED DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			INDEX idx_content (content_id, content_type),
			INDEX idx_status (status),
			INDEX idx_content_hash (content_hash),
			INDEX idx_last_indexed (last_indexed_at)
		) $charset_collate;";

		// Table: rag_logs
		$table_logs = $wpdb->prefix . 'rag_logs';
		$sql_logs = "CREATE TABLE $table_logs (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			log_level VARCHAR(20) NOT NULL,
			operation VARCHAR(100) NOT NULL,
			content_id BIGINT UNSIGNED,
			content_type VARCHAR(50),
			message TEXT NOT NULL,
			context TEXT,
			created_at DATETIME NOT NULL,
			INDEX idx_level (log_level),
			INDEX idx_operation (operation),
			INDEX idx_content (content_id, content_type),
			INDEX idx_created (created_at)
		) $charset_collate;";

		// Table: rag_vectors (MySQL Vector fallback)
		$table_vectors = $wpdb->prefix . 'rag_vectors';
		$sql_vectors = "CREATE TABLE $table_vectors (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			content_id BIGINT UNSIGNED NOT NULL,
			content_type VARCHAR(50) NOT NULL,
			chunk_index INT UNSIGNED NOT NULL,
			chunk_text TEXT NOT NULL,
			embedding BLOB NOT NULL,
			metadata TEXT,
			created_at DATETIME NOT NULL,
			INDEX idx_content (content_id, content_type),
			INDEX idx_chunk (content_id, chunk_index)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_status );
		dbDelta( $sql_logs );
		dbDelta( $sql_vectors );
	}
}
