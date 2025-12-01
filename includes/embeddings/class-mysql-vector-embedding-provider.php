<?php

namespace UBC\RAG\Embeddings;

use UBC\RAG\Interfaces\EmbeddingProviderInterface;
use MHz\MysqlVector\Nlp\Embedder;
use UBC\RAG\Logger;

/**
 * MySQL Vector Embedding Provider.
 * Uses the built-in BGE model from mysql-vector library.
 */
class MySQL_Vector_Embedding_Provider implements EmbeddingProviderInterface {

	/**
	 * Embedder instance.
	 *
	 * @var \MHz\MysqlVector\Nlp\Embedder|null
	 */
	private $embedder = null;

	/**
	 * Get the embedder instance.
	 *
	 * @return \MHz\MysqlVector\Nlp\Embedder
	 * @throws \Exception If embedder cannot be initialized.
	 */
	private function get_embedder() {
		if ( null === $this->embedder ) {
			try {
				Logger::log( 'MySQL_Vector_Embedding_Provider: Checking if Embedder class exists...' );
				if ( class_exists( '\MHz\MysqlVector\Nlp\Embedder' ) ) {
					Logger::log( 'MySQL_Vector_Embedding_Provider: Embedder class exists. Instantiating...' );
					$this->embedder = new \MHz\MysqlVector\Nlp\Embedder();
					Logger::log( 'MySQL_Vector_Embedding_Provider: Embedder instantiated successfully.' );
				} else {
					Logger::log( 'MySQL_Vector_Embedding_Provider: Embedder class NOT found.' );
					throw new \Exception( 'Embedder class not found.' );
				}
			} catch ( \Throwable $e ) {
				Logger::log( 'MySQL_Vector_Embedding_Provider Error loading Embedder: ' . $e->getMessage() );
				throw new \Exception( 'Could not initialize Embedder. Check logs for details.' );
			}
		}
		return $this->embedder;
	}

	/**
	 * Generate embeddings for chunks of text.
	 *
	 * @param array $chunks   Array of chunk data (text + metadata).
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings (same order as input).
	 * @throws \Exception On failure.
	 */
	public function embed( array $chunks, array $settings ): array {
		$embedder = $this->get_embedder();
		if ( ! $embedder ) {
			throw new \Exception( 'MySQL Vector Embedder could not be initialized.' );
		}

		$texts = [];
		foreach ( $chunks as $chunk ) {
			// Handle both string chunks and array chunks with 'content' key
			if ( is_array( $chunk ) && isset( $chunk['content'] ) ) {
				$texts[] = $chunk['content'];
			} elseif ( is_string( $chunk ) ) {
				$texts[] = $chunk;
			} else {
				$texts[] = ''; // Should not happen usually
			}
		}

		if ( empty( $texts ) ) {
			return [];
		}

		try {
			// The library's embed method takes an array of strings.
			// embed(array $text, bool $prependQuery = false): array
			// We can optionally prepend query instruction, but usually that's for search queries.
			// For documents, we might not want it.
			// But BGE usually expects "Represent this sentence for searching..." for queries.
			// For documents, it's just raw text.
			// The library defaults prependQuery to false.
			
			return $embedder->embed( $texts, false );
		} catch ( \Throwable $e ) {
			Logger::log( 'MySQL_Vector_Embedding_Provider Generation Error: ' . $e->getMessage() );
			throw new \Exception( 'Failed to generate embeddings: ' . $e->getMessage() );
		}
	}

	/**
	 * Generate embeddings for a batch of chunks.
	 *
	 * @param array $chunks   Array of chunk data.
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings.
	 * @throws \Exception On failure.
	 */
	public function embed_batch( array $chunks, array $settings ): array {
		// MySQL Vector Embedder handles batching natively in embed(), so we just call that.
		return $this->embed( $chunks, $settings );
	}

	/**
	 * Get the dimension size of embeddings from this provider.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return int
	 */
	public function get_dimensions( array $settings ): int {
		$embedder = $this->get_embedder();
		if ( $embedder ) {
			return $embedder->getDimensions();
		}
		return 384; // Default for BGE-micro/small which is likely used
	}

	/**
	 * Test connection with current settings.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return bool
	 * @throws \Exception With details on failure.
	 */
	public function test_connection( array $settings ): bool {
		$embedder = $this->get_embedder();
		if ( ! $embedder ) {
			throw new \Exception( 'Could not initialize Embedder. Check logs for details.' );
		}

		try {
			$embedder = new \MHz\MysqlVector\Nlp\Embedder();
			$embeddings = $embedder->embed( [ 'Test connection' ] );

			return ! empty( $embeddings );
		} catch ( \Exception $e ) {
			return false;
		}
	}
}
