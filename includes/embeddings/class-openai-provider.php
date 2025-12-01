<?php

namespace UBC\RAG\Embeddings;

use UBC\RAG\Interfaces\EmbeddingProviderInterface;
use UBC\RAG\Logger;

/**
 * OpenAI Embedding Provider.
 *
 * Supports both regular API and Batch API for cost-effective bulk embedding.
 */
class OpenAI_Provider implements EmbeddingProviderInterface {

	/**
	 * OpenAI API base URL.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Embeddings endpoint.
	 *
	 * @var string
	 */
	const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';

	/**
	 * Batch API endpoint.
	 *
	 * @var string
	 */
	const BATCH_ENDPOINT = 'https://api.openai.com/v1/batches';

	/**
	 * Default embedding model.
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'text-embedding-3-small';

	/**
	 * Generate embeddings for chunks of text.
	 *
	 * @param array $chunks   Array of chunk data (text + metadata).
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings (same order as input).
	 * @throws \Exception On failure.
	 */
	public function embed( array $chunks, array $settings ): array {
		$api_key = isset( $settings['api_key'] ) && ! empty( $settings['api_key'] ) ? $settings['api_key'] : '';

		if ( empty( $api_key ) ) {
			throw new \Exception( 'OpenAI API key is not configured.' );
		}

		$model = isset( $settings['model'] ) && ! empty( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;

		// Check if batch API is enabled and we have enough chunks to justify it.
		$use_batch_api = isset( $settings['use_batch_api'] ) && $settings['use_batch_api'] && count( $chunks ) >= 10;

		if ( $use_batch_api ) {
			return $this->embed_with_batch_api( $chunks, $api_key, $model );
		} else {
			return $this->embed_with_regular_api( $chunks, $api_key, $model );
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
		// OpenAI handles batching natively in embed(), so we just call that.
		return $this->embed( $chunks, $settings );
	}

	/**
	 * Generate embeddings using the regular OpenAI API.
	 *
	 * Batches up to 2048 inputs per request for cost efficiency.
	 *
	 * @param array  $chunks   Chunks to embed.
	 * @param string $api_key  OpenAI API key.
	 * @param string $model    Model name.
	 * @return array Embeddings array.
	 * @throws \Exception On failure.
	 */
	private function embed_with_regular_api( array $chunks, string $api_key, string $model ): array {
		$embeddings = [];
		$batch_size = 2048; // OpenAI batch limit.

		// Process chunks in batches.
		$total_chunks = count( $chunks );
		for ( $i = 0; $i < $total_chunks; $i += $batch_size ) {
			$batch = array_slice( $chunks, $i, $batch_size );
			$batch_embeddings = $this->call_embeddings_api( $batch, $api_key, $model );
			$embeddings = array_merge( $embeddings, $batch_embeddings );
		}

		return $embeddings;
	}

	/**
	 * Call the OpenAI embeddings API.
	 *
	 * @param array  $chunks   Chunks to embed.
	 * @param string $api_key  OpenAI API key.
	 * @param string $model    Model name.
	 * @return array Embeddings array.
	 * @throws \Exception On failure.
	 */
	private function call_embeddings_api( array $chunks, string $api_key, string $model ): array {
		$texts = array_map( function ( $chunk ) {
			return $chunk['content'];
		}, $chunks );

		$request_body = wp_json_encode(
			[
				'model' => $model,
				'input' => $texts,
			]
		);

		$response = wp_remote_post(
			self::EMBEDDINGS_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $request_body,
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			Logger::log( 'OpenAI API Error: ' . $response->get_error_message() );
			throw new \Exception( 'OpenAI API Error: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			$body = wp_remote_retrieve_body( $response );
			Logger::log( "OpenAI API Error ($response_code): $body" );
			throw new \Exception( "OpenAI API Error ($response_code): $body" );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			Logger::log( 'OpenAI API Error: Invalid response format.' );
			throw new \Exception( 'OpenAI API Error: Invalid response format.' );
		}

		// Sort by index to ensure correct order.
		$sorted_data = $data['data'];
		usort( $sorted_data, function ( $a, $b ) {
			return ( $a['index'] ?? 0 ) - ( $b['index'] ?? 0 );
		} );

		$embeddings = [];
		foreach ( $sorted_data as $item ) {
			if ( isset( $item['embedding'] ) && is_array( $item['embedding'] ) ) {
				$embeddings[] = $item['embedding'];
			}
		}

		return $embeddings;
	}

	/**
	 * Generate embeddings using OpenAI Batch API (for cost savings).
	 *
	 * Note: Batch API is asynchronous. This method creates the batch job but returns a placeholder.
	 * A separate job would need to poll for results later.
	 *
	 * @param array  $chunks   Chunks to embed.
	 * @param string $api_key  OpenAI API key.
	 * @param string $model    Model name.
	 * @return array Embeddings array (from fallback regular API).
	 * @throws \Exception On failure.
	 */
	private function embed_with_batch_api( array $chunks, string $api_key, string $model ): array {
		// For MVP, we'll fall back to regular API since batch API requires polling.
		// In the future, this would:
		// 1. Create batch job with OpenAI
		// 2. Store batch ID in database
		// 3. Create an ActionScheduler job to check status later
		// 4. Return placeholder embeddings
		Logger::log( 'Batch API requested but falling back to regular API for this batch.' );
		return $this->embed_with_regular_api( $chunks, $api_key, $model );
	}

	/**
	 * Get the dimension size of embeddings from this provider.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return int
	 */
	public function get_dimensions( array $settings ): int {
		if ( isset( $settings['dimensions'] ) && is_numeric( $settings['dimensions'] ) ) {
			return (int) $settings['dimensions'];
		}

		// Default based on model.
		$model = isset( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;

		// OpenAI embedding models have fixed dimensions.
		$dimensions_map = [
			'text-embedding-3-small'  => 1536,
			'text-embedding-3-large'  => 3072,
			'text-embedding-ada-002'  => 1536,
		];

		return $dimensions_map[ $model ] ?? 1536;
	}

	/**
	 * Test connection with current settings.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return bool
	 * @throws \Exception With details on failure.
	 */
	public function test_connection( array $settings ): bool {
		$api_key = isset( $settings['api_key'] ) && ! empty( $settings['api_key'] ) ? $settings['api_key'] : '';

		if ( empty( $api_key ) ) {
			throw new \Exception( 'OpenAI API key is required.' );
		}

		$model = isset( $settings['model'] ) && ! empty( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;

		// Test with a simple embedding request.
		$request_body = wp_json_encode(
			[
				'model' => $model,
				'input' => 'test',
			]
		);

		Logger::log( "OpenAI Test Connection: Starting test for model '$model'." );
		// Don't log full body if it contains sensitive info, but here it's just 'test'.
		Logger::log( "OpenAI Test Connection: Request Body: $request_body" );

		$response = wp_remote_post(
			self::EMBEDDINGS_ENDPOINT,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => $request_body,
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			Logger::log( "OpenAI Test Connection: Request failed. Error: " . $response->get_error_message() );
			throw new \Exception( 'Connection failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		Logger::log( "OpenAI Test Connection: Response Code: $response_code" );
		// Log response body but truncate if needed.
		Logger::log( "OpenAI Test Connection: Response Body: " . substr( $response_body, 0, 500 ) . ( strlen( $response_body ) > 500 ? '...' : '' ) );

		if ( 200 !== $response_code ) {
			$body = wp_remote_retrieve_body( $response );
			$error_data = json_decode( $body, true );

			// Try to extract error message.
			if ( isset( $error_data['error']['message'] ) ) {
				throw new \Exception( 'OpenAI API Error: ' . $error_data['error']['message'] );
			} else {
				throw new \Exception( "OpenAI API Error ($response_code): $body" );
			}
		}

		Logger::log( "OpenAI Test Connection: Success." );

		return true;
	}
}
