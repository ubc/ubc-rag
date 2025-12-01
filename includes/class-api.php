<?php

namespace UBC\RAG;

use UBC\RAG\Vector_Store_Factory;
use UBC\RAG\Embedding_Factory;

/**
 * Public API for the UBC RAG plugin.
 *
 * This class provides static methods for external plugins to interact with the RAG system.
 *
 * @package UBC\RAG
 */
class API {

	/**
	 * Search the vector store for content similar to the query.
	 *
	 * This method handles the entire search pipeline:
	 * 1. Generates an embedding (vector) for the input query using the active Embedding Provider.
	 * 2. Queries the active Vector Store using the generated vector.
	 * 3. Applies any provided metadata filters to restrict results.
	 *
	 * @param string $query  The search query string. This will be converted to a vector embedding.
	 *                       Example: "climate change policy"
	 * @param int    $limit  Maximum number of results to return. Default is 5.
	 * @param array  $filter Metadata filters to apply.
	 *                       This should be an associative array where keys are metadata fields and values are the values to match.
	 *                       Example: ['post_type' => 'page', 'category' => 'news']
	 *                       Note: Filtering support depends on the underlying Vector Store implementation.
	 *
	 * @return array Array of search results. Each result is an associative array containing:
	 *               - 'id' (string|int): The unique ID of the vector/record in the vector store.
	 *               - 'score' (float): The similarity score (usually cosine similarity), ranging from 0 to 1. Higher is better.
	 *               - 'payload' (array): The metadata payload associated with the vector.
	 *                 - 'content_id' (int): The WordPress Post ID.
	 *                 - 'content_type' (string): The content type (e.g., 'post', 'page').
	 *                 - 'chunk_text' (string): The actual text content of the chunk.
	 *                 - 'metadata' (array): Additional metadata.
	 *
	 *               Example Response:
	 *               [
	 *                   [
	 *                       'id' => '12345',
	 *                       'score' => 0.89,
	 *                       'payload' => [
	 *                           'content_id' => 42,
	 *                           'content_type' => 'post',
	 *                           'chunk_text' => '...text snippet...',
	 *                           ...
	 *                       ]
	 *                   ],
	 *                   ...
	 *               ]
	 */
	public static function search( string $query, int $limit = 5, array $filter = [] ): array {
		$search_instance = self::get_search_instance();

		if ( ! $search_instance ) {
			return [];
		}

		return $search_instance->search( $query, $limit, $filter );
	}

	/**
	 * Get an instance of the Search class.
	 *
	 * @return Search|null Search instance or null if dependencies are missing.
	 */
	public static function get_search_instance() {
		$vector_store = Vector_Store_Factory::get_instance()->get_active_store();
		$embedding_provider = Embedding_Factory::get_instance()->get_active_provider();

		if ( ! $vector_store || ! $embedding_provider ) {
			return null;
		}

		return new Search( $vector_store, $embedding_provider );
	}
}
