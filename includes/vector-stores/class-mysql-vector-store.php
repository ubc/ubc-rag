<?php

namespace UBC\RAG\Vector_Stores;

use UBC\RAG\Interfaces\VectorStorageInterface;

/**
 * MySQL Vector Store.
 * Stores vectors in a local MySQL table.
 */
class MySQL_Vector_Store implements VectorStorageInterface {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rag_vectors';
	}

	/**
	 * Create/ensure collection exists.
	 * For MySQL, this corresponds to the table existing.
	 *
	 * @param string $collection_name Collection name (ignored for MySQL single table).
	 * @param int    $dimensions      Vector dimensions.
	 * @param array  $config          Additional config.
	 * @return bool
	 */
	public function create_collection( string $collection_name, int $dimensions, array $config = [] ): bool {
		// In this MVP, the table is created by the installer.
		// We could check if table exists here.
		global $wpdb;
		$table = $this->get_table_name();
		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
	}

	/**
	 * Delete a collection.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function delete_collection( string $collection_name ): bool {
		// We don't drop the table in this implementation as it's shared.
		// Maybe truncate? But that's dangerous.
		// For now, return true as "done".
		return true;
	}

	/**
	 * Check if collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function collection_exists( string $collection_name ): bool {
		return $this->create_collection( $collection_name, 0 );
	}

	/**
	 * Insert vectors with metadata.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vectors         Array of ['id' => '', 'vector' => [], 'payload' => []].
	 *                                Payload MUST contain 'content_id', 'content_type', 'chunk_index', 'chunk_text'.
	 * @return array Array of inserted vector IDs (not really used in MySQL impl but good for interface).
	 */
	public function insert_vectors( string $collection_name, array $vectors ): array {
		global $wpdb;
		$table = $this->get_table_name();
		$now   = current_time( 'mysql' );
		$inserted_ids = [];

		foreach ( $vectors as $vector_item ) {
			$payload = $vector_item['payload'];
			
			// Validate required payload fields for MySQL schema
			if ( ! isset( $payload['content_id'], $payload['content_type'], $payload['chunk_index'], $payload['chunk_text'] ) ) {
				continue;
			}

			$result = $wpdb->insert(
				$table,
				[
					'content_id'   => $payload['content_id'],
					'content_type' => $payload['content_type'],
					'chunk_index'  => $payload['chunk_index'],
					'chunk_text'   => $payload['chunk_text'],
					'embedding'    => wp_json_encode( $vector_item['vector'] ),
					'metadata'     => isset( $payload['metadata'] ) ? wp_json_encode( $payload['metadata'] ) : null,
					'created_at'   => $now,
				],
				[
					'%d',
					'%s',
					'%d',
					'%s',
					'%s', // JSON string for embedding
					'%s',
					'%s',
				]
			);
			
			if ( $result ) {
				$inserted_ids[] = $wpdb->insert_id;
			}
		}

		return $inserted_ids;
	}

	/**
	 * Delete vectors by ID.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vector_ids      Array of vector IDs (database IDs).
	 * @return bool
	 */
	public function delete_vectors( string $collection_name, array $vector_ids ): bool {
		global $wpdb;
		$table = $this->get_table_name();
		
		if ( empty( $vector_ids ) ) {
			return true;
		}
		
		$ids_placeholder = implode( ',', array_fill( 0, count( $vector_ids ), '%d' ) );
		
		return (bool) $wpdb->query(
			$wpdb->prepare( "DELETE FROM $table WHERE id IN ($ids_placeholder)", $vector_ids )
		);
	}

	/**
	 * Delete all vectors matching a filter.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $filter          Filter conditions. Supported: 'content_id', 'content_type'.
	 * @return int Number of vectors deleted.
	 */
	public function delete_by_filter( string $collection_name, array $filter ): int {
		global $wpdb;
		$table = $this->get_table_name();
		
		$where = [];
		$values = [];
		
		if ( isset( $filter['content_id'] ) ) {
			$where[] = 'content_id = %d';
			$values[] = $filter['content_id'];
		}
		
		if ( isset( $filter['content_type'] ) ) {
			$where[] = 'content_type = %s';
			$values[] = $filter['content_type'];
		}
		
		if ( empty( $where ) ) {
			return 0; // Safety: don't delete everything if no filter
		}
		
		$sql = "DELETE FROM $table WHERE " . implode( ' AND ', $where );
		
		return (int) $wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( "SELECT 1" );
	}
	
	/**
	 * Get max chunk index.
	 * Custom method for MySQL store, might need to be part of interface or handled differently?
	 * For now, we keep it here and the Worker might need to know about it or we add it to interface?
	 * The interface doesn't have it.
	 * 
	 * Strategy: The Worker currently uses this to resume. 
	 * We should probably add `get_max_chunk_index` to the interface or a `get_existing_chunks_count` method.
	 * Or, for now, we can just implement it here and check `method_exists` in worker, or add to interface.
	 * Adding to interface is cleaner.
	 */
	public function get_max_chunk_index( string $collection_name, array $filter ): ?int {
		global $wpdb;
		$table = $this->get_table_name();
		
		$where = [];
		$values = [];
		
		if ( isset( $filter['content_id'] ) ) {
			$where[] = 'content_id = %d';
			$values[] = $filter['content_id'];
		}
		
		if ( isset( $filter['content_type'] ) ) {
			$where[] = 'content_type = %s';
			$values[] = $filter['content_type'];
		}
		
		if ( empty( $where ) ) {
			return null;
		}
		
		$sql = "SELECT MAX(chunk_index) FROM $table WHERE " . implode( ' AND ', $where );
		
		$result = $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		
		return null !== $result ? (int) $result : null;
	}
}
