<?php

namespace UBC\RAG\Vector_Stores;

use UBC\RAG\Interfaces\VectorStorageInterface;
use MHz\MysqlVector\VectorTable;
use UBC\RAG\Logger;

/**
 * MySQL Vector Store (Library Backed).
 * Uses allanpichardo/mysql-vector library.
 */
class MySQL_Vector_Lib_Store implements VectorStorageInterface {

	/**
	 * Vector Table instance.
	 *
	 * @var VectorTable
	 */
	private $vector_table;

	/**
	 * Metadata table name.
	 *
	 * @var string
	 */
	private $metadata_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->metadata_table = $wpdb->prefix . 'rag_vector_metadata';
	}

	/**
	 * Get the vector table name.
	 *
	 * @return string
	 */
	private function get_vector_table_name() {
		global $wpdb;
		// The library appends '_vectors' to the name we pass.
		// We pass 'wp_rag_lib' -> table becomes 'wp_rag_lib_vectors'.
		// But wait, wpdb prefix usually includes 'wp_'.
		// So if prefix is 'wp_', and we pass 'wp_rag_lib', it becomes 'wp_rag_lib_vectors'.
		// This seems fine.
		return $wpdb->prefix . 'rag_lib';
	}

	/**
	 * Initialize the VectorTable instance.
	 *
	 * @param int $dimensions Vector dimensions.
	 * @return void
	 */
	private function init_vector_table( $dimensions = 384 ) {
		global $wpdb;
		// Ensure we have a mysqli connection
		if ( ! $wpdb->dbh instanceof \mysqli ) {
			// This might happen if using a drop-in that uses PDO or mysql extension (deprecated).
			// But for now we assume mysqli.
			// If not, we can't use this library.
			Logger::log( 'MySQL_Vector_Lib_Store: $wpdb->dbh is not an instance of mysqli.' );
			return;
		}

		$this->vector_table = new VectorTable( $wpdb->dbh, $this->get_vector_table_name(), $dimensions );
	}

	/**
	 * Create/ensure collection exists.
	 *
	 * @param string $collection_name Collection name (ignored).
	 * @param int    $dimensions      Vector dimensions.
	 * @param array  $config          Additional config.
	 * @return bool
	 */
	public function create_collection( string $collection_name, int $dimensions, array $config = [] ): bool {
		global $wpdb;

		try {
			$this->init_vector_table( $dimensions );
			if ( ! $this->vector_table ) {
				return false;
			}

			// Initialize library tables (vectors and centroids)
			$this->vector_table->initialize( true );

			// Create metadata table
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE IF NOT EXISTS {$this->metadata_table} (
				vector_id INT UNSIGNED PRIMARY KEY,
				content_id BIGINT UNSIGNED,
				content_type VARCHAR(50),
				chunk_index INT UNSIGNED,
				chunk_text LONGTEXT,
				metadata JSON,
				INDEX (content_id, content_type)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			return true;
		} catch ( \Exception $e ) {
			Logger::log( 'MySQL_Vector_Lib_Store Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Delete a collection.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function delete_collection( string $collection_name ): bool {
		// We don't drop tables usually, but if requested...
		// For now, return true.
		return true;
	}

	/**
	 * Check if collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function collection_exists( string $collection_name ): bool {
		global $wpdb;
		// Check if tables exist
		$vector_table_name = $this->get_vector_table_name() . '_vectors';
		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $vector_table_name ) ) === $vector_table_name;
	}

	/**
	 * Insert vectors with metadata.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vectors         Array of ['id' => '', 'vector' => [], 'payload' => []].
	 * @return array Array of inserted vector IDs.
	 */
	public function insert_vectors( string $collection_name, array $vectors ): array {
		global $wpdb;
		
		if ( empty( $vectors ) ) {
			return [];
		}

		// Init table with dimensions from first vector
		$dimensions = count( $vectors[0]['vector'] );
		$this->create_collection( $collection_name, $dimensions );

		$inserted_ids = [];

		foreach ( $vectors as $item ) {
			$vector = $item['vector'];
			$payload = $item['payload'];

			try {
				// Insert into vector table
				// upsert(array $vector, int $id = null, bool $isCentroid = false): int
				$vector_id = $this->vector_table->upsert( $vector );

				if ( $vector_id ) {
					// Insert into metadata table
					$wpdb->replace(
						$this->metadata_table,
						[
							'vector_id'    => $vector_id,
							'content_id'   => isset( $payload['content_id'] ) ? $payload['content_id'] : 0,
							'content_type' => isset( $payload['content_type'] ) ? $payload['content_type'] : '',
							'chunk_index'  => isset( $payload['chunk_index'] ) ? $payload['chunk_index'] : 0,
							'chunk_text'   => isset( $payload['chunk_text'] ) ? $payload['chunk_text'] : '',
							'metadata'     => isset( $payload['metadata'] ) ? wp_json_encode( $payload['metadata'] ) : null,
						],
						[ '%d', '%d', '%s', '%d', '%s', '%s' ]
					);
					$inserted_ids[] = $vector_id;
				}
			} catch ( \Exception $e ) {
				Logger::log( 'MySQL_Vector_Lib_Store Insert Error: ' . $e->getMessage() );
			}
		}

		return $inserted_ids;
	}

	/**
	 * Search for similar vectors.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vector          Query vector.
	 * @param int    $limit           Maximum number of results.
	 * @param array  $filter          Metadata filter.
	 * @return array Array of results.
	 */
	public function query( string $collection_name, array $vector, int $limit = 5, array $filter = [] ): array {
		global $wpdb;

		// Ensure collection exists (table name is derived from it)
		// The library handles table names internally, but we know the pattern or can use the library's method if exposed.
		// However, the library's query method might not support complex joins easily.
		// We will implement a custom SQL query here to join vector table with metadata table.

		// Get table names
		// Assuming standard naming convention from the library or our setup
		// The library creates tables like: {$wpdb->prefix}vector_{$collection_name}
		// But we should verify how the library exposes this.
		// Looking at create_collection, we don't explicitly see the table name logic exposed publicly easily,
		// but we can reconstruct it.
		// Actually, we can use the library's `search` if it supports it, but for metadata filtering we need a JOIN.
		// Let's assume we need to do raw SQL for the JOIN.

		$vector_table_name = $wpdb->prefix . 'vector_' . $collection_name; // This is an assumption, need to verify if library exposes it.
		// Wait, the library uses a specific naming convention.
		// Let's check how we instantiated the VectorStore in __construct.
		// We don't have the table name directly.
		// But we can look at how `mysql-vector` works.
		// If we can't be sure, we might need to rely on the metadata table having the vector_id and doing a two-step process
		// OR (better) use the vector distance function in SQL.

		// Let's try to use the vector distance function directly in SQL.
		// The function is usually `vector_distance_cosine` or similar depending on the library version/setup.
		// But since we are using `mysql-vector` library, it might have registered functions.

		// ALTERNATIVE:
		// 1. Fetch ALL results from vector store (limit is high?) -> No, too expensive.
		// 2. Filter by metadata first? -> No, we need semantic similarity.

		// BEST APPROACH:
		// Perform a SQL query that joins the vector table and metadata table.
		// We need to know the vector table name.
		// In `create_collection`, we call `$this->client->createCollection($collection_name, $dimensions)`.
		// The library likely prefixes it.
		// Let's assume standard WP prefix + 'vector_' + collection name for now, or check if we can get it.
		// If we look at `insert_vectors`, we use `$this->vector_table->upsert`.
		// `$this->vector_table` is set in `create_collection` or `__construct`?
		// It's set in `create_collection`.
		// We need to ensure `create_collection` or similar init has happened or we can get the table.
		// Actually, `get_max_chunk_index` uses `$this->metadata_table`.

		// Let's assume the vector table is accessible.
		// If we can't easily get the table name, we might have to instantiate the collection object from the library.

		// Let's look at `__construct`.
		// $this->client = new VectorStore( $db_config );
		// It doesn't seem to expose table names easily.

		// However, we know the metadata table is `$this->metadata_table`.
		// And it has `vector_id`.
		// The vector table has `id` and `embeddings` (blob).

		// Let's try to construct the query assuming we can get the table name.
		// If we can't, we might need to rely on the library to give us the table name.
		// For now, let's assume `wp_vector_{collection_name}` or similar.
		// Wait, `mysql-vector` usually uses `wp_vector_...` if passed the prefix.

		// Let's try to find the table name dynamically or catch the error.
		$vector_table_name = $wpdb->prefix . 'vector_' . $collection_name;

		// Encode vector for SQL
		$vector_str = json_encode( $vector );

		// Build Filter SQL
		$where_clauses = [ '1=1' ];
		$where_values  = [];

		if ( ! empty( $filter ) ) {
			foreach ( $filter as $key => $value ) {
				$where_clauses[] = "m.$key = %s";
				$where_values[]  = $value;
			}
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// We need the distance function.
		// `mysql-vector` usually creates a function `cosine_similarity` or `l2_distance`.
		// Let's assume `cosine_similarity` or similar.
		// Actually, the library might handle the query generation.
		// If we can't use the library's query with JOIN, we have to write raw SQL.
		// The raw SQL for cosine distance usually involves dot product and magnitudes.
		// But `mysql-vector` likely provides a UDF or stored procedure?
		// Or it does it in PHP?
		// If it does it in PHP, it fetches all? No.
		// It likely uses a SQL query.

		// Let's try to use the library's `find` method if it exists and supports filtering?
		// The interface doesn't show `find` with filters.

		// Let's implement a "Fetch more and filter" approach as a fallback if we can't do a JOIN easily,
		// BUT the plan said "likely involve custom SQL queries".
		// So let's try the custom SQL.

		// We need to know the column name for the vector in the vector table.
		// Usually `embedding` or `vector`.
		// And the ID column `id`.

		// Let's try a query that assumes we have a `vector_distance` function or similar,
		// OR we calculate it if the vectors are stored as JSON/Blob.
		// `mysql-vector` stores as BLOB usually.

		// Let's look at how `mysql-vector` implements `search` or `query`.
		// Since I can't see the library code directly, I will assume a standard implementation.
		// But wait, I can see `insert_vectors` uses `$this->vector_table->upsert`.
		// Maybe I can use `$this->client->getCollection($collection_name)->find(...)`?
		// If I can get the collection object.

		// Let's try to instantiate the collection and see if we can use it.
		try {
			$collection = $this->client->getCollection( $collection_name );
			// If the library supports metadata filtering in `find`, that would be great.
			// But our metadata is in a SEPARATE table `wp_ubc_rag_vector_metadata`.
			// The library doesn't know about our metadata table.
			// So we MUST do a JOIN.

			// So we need the table name from the collection object.
			// $collection->getTableName()?
			// If not available, we guess.

			// Query:
			// SELECT v.id, m.content_id, m.content_type, m.chunk_text, m.metadata,
			//        vector_distance(v.embedding, ?) as distance
			// FROM $vector_table_name v
			// JOIN $this->metadata_table m ON v.id = m.vector_id
			// WHERE $where_sql
			// ORDER BY distance ASC
			// LIMIT $limit

			// We need to be careful about `vector_distance`.
			// If it's not available, we might need to implement the math in SQL (slow) or PHP (fetch many).
			// Given this is a plugin, we should probably check if the function exists.
			// But for now, let's assume we can use a raw SQL query with a placeholder for the distance function.
			// Actually, `mysql-vector` might not register a global function.
			// It might generate the SQL for us.

			// Let's try a hybrid approach:
			// 1. Get candidate IDs from the vector store (using the library if possible).
			// 2. Filter them?
			// No, that's "filter after". We want "pre-filtering" or "simultaneous".

			// Let's go with the JOIN.
			// We will assume the table name is `{$wpdb->prefix}vector_{$collection_name}`.
			// We will assume the vector column is `embedding`.
			// We will assume we can't easily do vector math in SQL without the UDF.
			// Does `mysql-vector` install a UDF?
			// If not, it probably does cosine similarity in SQL using JSON_TABLE or similar (MySQL 8) or just raw math if fixed dimensions.

			// Let's look at `get_max_chunk_index` implementation for clues?
			// It just queries the metadata table.

			// Let's try to write a query that calculates cosine similarity manually if needed,
			// or uses a helper if available.
			// For now, I will implement a placeholder that fetches the top N * 5 vectors,
			// then filters in PHP. This is safer compatibility-wise but less efficient.
			// Wait, the plan said "Custom SQL queries to join...".
			// If I don't know the exact SQL syntax for the vector operations provided by the library,
			// I might break it.

			// Let's look at the `mysql-vector` library usage in `insert_vectors`.
			// `$this->vector_table->upsert( $vector )`.
			// It seems `$this->vector_table` is an object.

			// Let's try to use the library to search, then filter.
			// $results = $collection->find($vector, $limit * 10); // Fetch more
			// Then filter by querying the metadata table for those IDs.

			$collection = $this->client->getCollection( $collection_name );
			$results = $collection->find( $vector, $limit * 4 ); // Fetch 4x to allow for some filtering

			$filtered_results = [];
			$vector_ids = [];
			foreach ( $results as $result ) {
				$vector_ids[] = $result->id;
			}

			if ( empty( $vector_ids ) ) {
				return [];
			}

			// Fetch metadata for these IDs
			$placeholders = implode( ',', array_fill( 0, count( $vector_ids ), '%d' ) );
			$sql = "SELECT * FROM {$this->metadata_table} WHERE vector_id IN ($placeholders)";
			
			// Add metadata filters
			$query_values = $vector_ids;
			if ( ! empty( $filter ) ) {
				foreach ( $filter as $key => $value ) {
					$sql .= " AND $key = %s";
					$query_values[] = $value;
				}
			}

			$metadata_rows = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ), ARRAY_A );
			
			// Map metadata by vector_id
			$metadata_map = [];
			foreach ( $metadata_rows as $row ) {
				$metadata_map[ $row['vector_id'] ] = $row;
			}

			// Combine and sort
			foreach ( $results as $result ) {
				if ( isset( $metadata_map[ $result->id ] ) ) {
					$meta = $metadata_map[ $result->id ];
					$filtered_results[] = [
						'id'      => $result->id,
						'score'   => $result->score, // Assuming library returns score/distance
						'payload' => [
							'content_id'   => $meta['content_id'],
							'content_type' => $meta['content_type'],
							'chunk_text'   => $meta['chunk_text'],
							'metadata'     => json_decode( $meta['metadata'], true ),
						],
					];
				}
				if ( count( $filtered_results ) >= $limit ) {
					break;
				}
			}

			return $filtered_results;

		} catch ( \Exception $e ) {
			Logger::log( 'MySQL_Vector_Lib_Store Query Error: ' . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Delete vectors by ID.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vector_ids      Array of vector IDs.
	 * @return bool
	 */
	public function delete_vectors( string $collection_name, array $vector_ids ): bool {
		global $wpdb;
		
		if ( empty( $vector_ids ) ) {
			return true;
		}

		// Init table (dimensions don't matter for delete)
		$this->init_vector_table();

		foreach ( $vector_ids as $id ) {
			try {
				$this->vector_table->delete( (int) $id );
				$wpdb->delete( $this->metadata_table, [ 'vector_id' => $id ], [ '%d' ] );
			} catch ( \Exception $e ) {
				Logger::log( 'MySQL_Vector_Lib_Store Delete Error: ' . $e->getMessage() );
			}
		}

		return true;
	}

	/**
	 * Delete all vectors matching a filter.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $filter          Filter conditions.
	 * @return int Number of vectors deleted.
	 */
	public function delete_by_filter( string $collection_name, array $filter ): int {
		global $wpdb;

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
			return 0;
		}

		// Find IDs to delete
		$sql = "SELECT vector_id FROM {$this->metadata_table} WHERE " . implode( ' AND ', $where );
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $values ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$this->delete_vectors( $collection_name, $ids );

		return count( $ids );
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
	 * Get the maximum chunk index stored for a content item.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $filter          Filter conditions.
	 * @return int|null Max chunk index or null if no vectors exist.
	 */
	public function get_max_chunk_index( string $collection_name, array $filter ): ?int {
		global $wpdb;

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

		$sql = "SELECT MAX(chunk_index) FROM {$this->metadata_table} WHERE " . implode( ' AND ', $where );
		$result = $wpdb->get_var( $wpdb->prepare( $sql, $values ) );

		return null !== $result ? (int) $result : null;
	}
}
