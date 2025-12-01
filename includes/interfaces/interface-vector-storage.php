<?php

namespace UBC\RAG\Interfaces;

/**
 * Vector Storage Interface.
 */
interface VectorStorageInterface {
	/**
	 * Create/ensure collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @param int    $dimensions      Vector dimensions.
	 * @param array  $config          Additional config (distance metric, etc.).
	 * @return bool
	 */
	public function create_collection( string $collection_name, int $dimensions, array $config = [] ): bool;

	/**
	 * Delete a collection.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function delete_collection( string $collection_name ): bool;

	/**
	 * Check if collection exists.
	 *
	 * @param string $collection_name Collection name.
	 * @return bool
	 */
	public function collection_exists( string $collection_name ): bool;

	/**
	 * Insert vectors with metadata.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vectors         Array of ['id' => '', 'vector' => [], 'payload' => []].
	 * @return array Array of inserted vector IDs.
	 */
	public function insert_vectors( string $collection_name, array $vectors ): array;

	/**
	 * Delete vectors by ID.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vector_ids      Array of vector IDs.
	 * @return bool
	 */
	public function delete_vectors( string $collection_name, array $vector_ids ): bool;

	/**
	 * Delete all vectors matching a filter.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $filter          Filter conditions.
	 * @return int Number of vectors deleted.
	 */
	public function delete_by_filter( string $collection_name, array $filter ): int;

	/**
	 * Test connection.
	 *
	 * @return bool
	 */
	public function test_connection(): bool;

	/**
	 * Get the maximum chunk index stored for a content item.
	 * Used for resumable processing.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $filter          Filter conditions (e.g. ['content_id' => 123, 'content_type' => 'post']).
	 * @return int|null Max chunk index or null if no vectors exist.
	 */
	public function get_max_chunk_index( string $collection_name, array $filter ): ?int;

	/**
	 * Search for similar vectors.
	 *
	 * @param string $collection_name Collection name.
	 * @param array  $vector          Query vector.
	 * @param int    $limit           Maximum number of results.
	 * @param array  $filter          Metadata filter (e.g. ['content_type' => 'post']).
	 * @return array Array of results with 'id', 'score', and 'payload'.
	 */
	public function query( string $collection_name, array $vector, int $limit = 5, array $filter = [] ): array;
}
