<?php

namespace UBC\RAG\Vector_Stores;

use UBC\RAG\Interfaces\VectorStorageInterface;
use UBC\RAG\Settings;
use UBC\RAG\Logger;

/**
 * Qdrant Vector Store.
 * Stores vectors in a Qdrant instance.
 */
class Qdrant_Vector_Store implements VectorStorageInterface {

	/**
	 * Qdrant URL.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * API Key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 * 
	 * @param array $config Optional configuration override.
	 */
	public function __construct( $config = [] ) {
		$settings = Settings::get_settings();
		$store_settings = isset( $settings['vector_store'] ) ? $settings['vector_store'] : [];
		
		// Merge override config if provided (e.g. from connection test).
		if ( ! empty( $config ) ) {
			$this->url = isset( $config['url'] ) ? untrailingslashit( $config['url'] ) : ( isset( $store_settings['qdrant']['url'] ) ? untrailingslashit( $store_settings['qdrant']['url'] ) : 'http://localhost:6333' );
			$this->api_key = isset( $config['api_key'] ) ? $config['api_key'] : ( isset( $store_settings['qdrant']['api_key'] ) ? $store_settings['qdrant']['api_key'] : '' );
		} else {
			$this->url = isset( $store_settings['qdrant']['url'] ) ? untrailingslashit( $store_settings['qdrant']['url'] ) : 'http://localhost:6333';
			$this->api_key = isset( $store_settings['qdrant']['api_key'] ) ? $store_settings['qdrant']['api_key'] : '';
		}
	}

	/**
	 * Get the standardized collection name.
	 * Format: site_{blog_id}_{hash}
	 * 
	 * @return string
	 */
	public function get_collection_name(): string {
		$blog_id = get_current_blog_id();
		$site_url = get_site_url();
		$hash = substr( hash( 'sha256', $site_url ), 0, 8 );
		return "site_{$blog_id}_{$hash}";
	}

	/**
	 * Make a request to Qdrant.
	 *
	 * @param string $endpoint   Endpoint path.
	 * @param string $method     HTTP method.
	 * @param array  $body       Request body.
	 * @param bool   $log_errors Whether to log errors (default true).
	 * @return array|false Response data or false on error.
	 */
	private function request( $endpoint, $method = 'GET', $body = null, $log_errors = true ) {
		$url = $this->url . $endpoint;
		
		$args = [
			'method'  => $method,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 30,
		];

		if ( ! empty( $this->api_key ) ) {
			$args['headers']['api-key'] = $this->api_key;
		}

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( $log_errors ) {
				Logger::log( "Qdrant Request Error: " . $response->get_error_message() );
			}
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			if ( $log_errors ) {
				Logger::log( "Qdrant Error ($code): " . $body );
			}
			return false;
		}

		return $data;
	}

	/**
	 * Create/ensure collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @param int    $dimensions      Vector dimensions.
	 * @param array  $config          Additional config.
	 * @return bool
	 */
	public function create_collection( string $collection_name, int $dimensions, array $config = [] ): bool {
		if ( $this->collection_exists( $collection_name ) ) {
			return true;
		}

		$payload = [
			'vectors' => [
				'size' => $dimensions,
				'distance' => isset( $config['distance'] ) ? $config['distance'] : 'Cosine',
			],
		];

		Logger::log( "Creating Qdrant collection: $collection_name with dimensions: $dimensions" );
		$response = $this->request( "/collections/$collection_name", 'PUT', $payload );
		return $response && isset( $response['status'] ) && 'ok' === $response['status'];
	}

	/**
	 * Delete a collection.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function delete_collection( string $collection_name ): bool {
		$response = $this->request( "/collections/$collection_name", 'DELETE' );
		return $response && isset( $response['status'] ) && 'ok' === $response['status'];
	}

	/**
	 * Check if collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function collection_exists( string $collection_name ): bool {
		// Suppress errors because 404 is expected if collection doesn't exist.
		$response = $this->request( "/collections/$collection_name", 'GET', null, false );
		return $response && isset( $response['status'] ) && 'ok' === $response['status'];
	}

	/**
	 * Insert vectors with metadata.
	 *
	 * @param string $collection_name Collection name (ignored, uses standardized name).
	 * @param array  $vectors         Array of ['id' => '', 'vector' => [], 'payload' => []].
	 * @return array Array of inserted vector IDs.
	 */
	public function insert_vectors( string $collection_name, array $vectors ): array {
		// Enforce standardized collection name.
		$collection_name = $this->get_collection_name();

		// Ensure collection exists before inserting.
		// We need dimensions from the first vector to create it if missing.
		if ( ! empty( $vectors ) && isset( $vectors[0]['vector'] ) ) {
			$dimensions = count( $vectors[0]['vector'] );
			$this->create_collection( $collection_name, $dimensions );
		}

		$points = [];
		$ids = [];

		foreach ( $vectors as $item ) {
			// Generate UUID if not provided.
			$id = isset( $item['id'] ) && ! empty( $item['id'] ) ? $item['id'] : wp_generate_uuid4();
			
			$points[] = [
				'id'      => $id,
				'vector'  => $item['vector'],
				'payload' => $item['payload'],
			];
			$ids[] = $id;
		}

		$payload = [
			'points' => $points,
		];

		// Use upsert (PUT /collections/{name}/points).
		// Wait, Qdrant API for upsert is PUT /collections/{collection_name}/points?wait=true
		$response = $this->request( "/collections/$collection_name/points?wait=true", 'PUT', $payload );

		if ( $response && isset( $response['status'] ) && 'ok' === $response['status'] ) {
			return $ids;
		}

		return [];
	}

	/**
	 * Delete vectors by ID.
	 *
	 * @param string $collection_name Collection name (ignored).
	 * @param array  $vector_ids      Array of vector IDs.
	 * @return bool
	 */
	public function delete_vectors( string $collection_name, array $vector_ids ): bool {
		$collection_name = $this->get_collection_name();
		$payload = [
			'points' => $vector_ids,
		];

		$response = $this->request( "/collections/$collection_name/points/delete?wait=true", 'POST', $payload );
		return $response && isset( $response['status'] ) && 'ok' === $response['status'];
	}

	/**
	 * Delete all vectors matching a filter.
	 *
	 * @param string $collection_name Collection name (ignored, uses site-specific name).
	 * @param array  $filter          Filter conditions.
	 * @return int Number of vectors deleted.
	 */
	public function delete_by_filter( string $collection_name, array $filter ): int {
		$collection_name = $this->get_collection_name();

		if ( ! $this->collection_exists( $collection_name ) ) {
			return 0; // Nothing to delete
		}

		// First, count how many vectors match the filter before deleting.
		$must = [];
		foreach ( $filter as $key => $value ) {
			$must[] = [
				'key' => $key,
				'match' => [
					'value' => $value,
				],
			];
		}

		// Scroll to count matching vectors.
		$count_payload = [
			'filter' => [
				'must' => $must,
			],
			'limit' => 1,
			'with_payload' => false,
		];

		$count_response = $this->request( "/collections/$collection_name/points/scroll", 'POST', $count_payload );
		$deleted_count = 0;

		if ( $count_response && isset( $count_response['result']['points'] ) ) {
			// Get total count from response if available.
			if ( isset( $count_response['result']['points'] ) && ! empty( $count_response['result']['points'] ) ) {
				// Do a proper count by scrolling with large limit to get actual count.
				$full_count_payload = [
					'filter' => [
						'must' => $must,
					],
					'limit' => 10000,
					'with_payload' => false,
				];
				$full_response = $this->request( "/collections/$collection_name/points/scroll", 'POST', $full_count_payload );
				$deleted_count = ( $full_response && isset( $full_response['result']['points'] ) ) ? count( $full_response['result']['points'] ) : 0;
			}
		}

		// Now delete the vectors.
		$delete_payload = [
			'filter' => [
				'must' => $must,
			],
		];

		$response = $this->request( "/collections/$collection_name/points/delete?wait=true", 'POST', $delete_payload );

		if ( $response && isset( $response['status'] ) && 'ok' === $response['status'] ) {
			return $deleted_count;
		}

		return 0;
	}

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function test_connection(): bool {
		// 1. Basic connectivity check.
		$response = $this->request( '/collections', 'GET' );
		if ( ! $response ) {
			return false;
		}

		// 2. CRUD Test.
		$test_collection = 'rag_test_connection_' . time();
		$dimensions = 4; // Small dimension for testing.
		
		// Create
		if ( ! $this->create_collection( $test_collection, $dimensions ) ) {
			Logger::log( "Test Connection: Failed to create test collection." );
			return false;
		}
		Logger::log( "Test Connection: Created test collection '$test_collection'." );

		// Insert
		// We use a manual request to ensure we are writing to the test collection, 
		// as insert_vectors() enforces the site-specific collection name.
		$test_id = wp_generate_uuid4();
		
		$payload = [
			'points' => [
				[
					'id' => $test_id,
					'vector' => [0.1, 0.2, 0.3, 0.4],
					'payload' => ['test' => 'data'],
				]
			]
		];
		$insert_response = $this->request( "/collections/$test_collection/points?wait=true", 'PUT', $payload );
		
		if ( ! $insert_response || ! isset( $insert_response['status'] ) || 'ok' !== $insert_response['status'] ) {
			Logger::log( "Test Connection: Failed to insert vector." );
			$this->delete_collection( $test_collection );
			return false;
		}
		Logger::log( "Test Connection: Inserted test vector." );

		// Search/Retrieve
		$search_response = $this->request( "/collections/$test_collection/points/$test_id", 'GET' );
		if ( ! $search_response || ! isset( $search_response['result']['id'] ) ) {
			Logger::log( "Test Connection: Failed to retrieve vector." );
			$this->delete_collection( $test_collection );
			return false;
		}
		Logger::log( "Test Connection: Retrieved test vector." );

		// Delete Collection
		$this->delete_collection( $test_collection );
		Logger::log( "Test Connection: Deleted test collection." );

		return true;
	}

	/**
	 * Get the maximum chunk index stored for a content item.
	 *
	 * @param string $collection_name Collection name (ignored).
	 * @param array  $filter          Filter conditions.
	 * @return int|null Max chunk index or null if no vectors exist.
	 */
	public function get_max_chunk_index( string $collection_name, array $filter ): ?int {
		$collection_name = $this->get_collection_name();
		
		if ( ! $this->collection_exists( $collection_name ) ) {
			return null; // No collection means no chunks
		}

		// We need to scroll/search for points matching the filter and find the max chunk_index.
		// This is expensive in Qdrant if we don't have an aggregation.
		// However, we can sort by chunk_index descending and take 1.
		
		$must = [];
		foreach ( $filter as $key => $value ) {
			$must[] = [
				'key' => $key,
				'match' => [
					'value' => $value,
				],
			];
		}

		$payload = [
			'filter' => [
				'must' => $must,
			],
			'limit' => 1,
			'with_payload' => true,
			'sort' => [
				[
					'key' => 'chunk_index',
					'order' => 'desc',
				],
			],
		];
		
		// Use scroll API or search API? Search requires vector. Scroll is better for filtering.
		// But scroll doesn't support sorting in all versions easily? 
		// Actually, scroll API supports filter but order is by ID usually.
		// Use /points/scroll.
		// Wait, Qdrant scroll doesn't support sorting by payload field easily in older versions.
		// But we can use the "recommend" or "search" if we had a vector.
		// 
		// Alternative: Fetch all points for this content (usually < 100) and find max in PHP.
		// Since we chunk by 5-10 paragraphs, a long post might have 20-50 chunks.
		// Fetching all is safe enough for now.
		
		// Let's try to fetch all IDs and payloads for this content.
		
		$payload = [
			'filter' => [
				'must' => $must,
			],
			'limit' => 1000, // Reasonable limit for one post
			'with_payload' => true,
			'with_vector' => false,
		];
		
		$response = $this->request( "/collections/$collection_name/points/scroll", 'POST', $payload );
		
		if ( ! $response || ! isset( $response['result']['points'] ) ) {
			return null;
		}
		
		$points = $response['result']['points'];
		if ( empty( $points ) ) {
			return null;
		}
		
		$max_index = -1;
		foreach ( $points as $point ) {
			if ( isset( $point['payload']['chunk_index'] ) ) {
				$idx = (int) $point['payload']['chunk_index'];
				if ( $idx > $max_index ) {
					$max_index = $idx;
				}
			}
		}
		
		return $max_index >= 0 ? $max_index : null;
	}
}
