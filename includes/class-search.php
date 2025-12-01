<?php
namespace UBC\RAG;

use UBC\RAG\Interfaces\VectorStorageInterface;
use UBC\RAG\Interfaces\EmbeddingProviderInterface;

/**
 * Class Search
 *
 * Handles search operations against the vector store.
 *
 * @package UBC\RAG
 */
class Search {

	/**
	 * Vector Store instance.
	 *
	 * @var VectorStorageInterface
	 */
	private $vector_store;

	/**
	 * Embedding Provider instance.
	 *
	 * @var EmbeddingProviderInterface
	 */
	private $embedding_provider;

	/**
	 * Constructor.
	 *
	 * @param VectorStorageInterface     $vector_store       Vector store instance.
	 * @param EmbeddingProviderInterface $embedding_provider Embedding provider instance.
	 */
	public function __construct( VectorStorageInterface $vector_store, EmbeddingProviderInterface $embedding_provider ) {
		$this->vector_store       = $vector_store;
		$this->embedding_provider = $embedding_provider;
	}

	/**
	 * Search for content.
	 *
	 * @param string $query  Search query string.
	 * @param int    $limit  Maximum number of results.
	 * @param array  $filter Metadata filter (e.g. ['content_type' => 'post']).
	 * @return array Search results.
	 */
	public function search( string $query, int $limit = 5, array $filter = [] ): array {
		if ( empty( $query ) ) {
			return [];
		}

		try {
			// 1. Generate embedding for the query
			// Providers expect an array of chunks, where each chunk is an associative array with 'content' key.
			$embeddings = $this->embedding_provider->embed( [ [ 'content' => $query ] ], [] ); // Settings might be needed?
			
			if ( empty( $embeddings ) || ! isset( $embeddings[0] ) ) {
				return [];
			}

			$query_vector = $embeddings[0];

			// 2. Query the vector store
			// Get the standardized collection name from the factory.
			$collection_name = \UBC\RAG\Vector_Store_Factory::get_instance()->get_collection_name();

			$results = $this->vector_store->query( $collection_name, $query_vector, $limit, $filter );

			return $results;

		} catch ( \Exception $e ) {
			Logger::log( 'Search Error: ' . $e->getMessage() );
			return [];
		}
	}
}
