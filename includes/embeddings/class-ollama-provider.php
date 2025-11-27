<?php

namespace UBC\RAG\Embeddings;

use UBC\RAG\Interfaces\EmbeddingProviderInterface;
use UBC\RAG\Logger;

/**
 * Ollama Embedding Provider.
 */
class Ollama_Provider implements EmbeddingProviderInterface {

	/**
	 * Default endpoint.
	 *
	 * @var string
	 */
	const DEFAULT_ENDPOINT = 'http://localhost:11434';

	/**
	 * Default model.
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'nomic-embed-text';

	/**
	 * Generate embeddings for chunks of text.
	 *
	 * @param array $chunks   Array of chunk data (text + metadata).
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings (same order as input).
	 * @throws \Exception On failure after retries.
	 */
	public function embed( array $chunks, array $settings ): array {
		// Check time budget to avoid PHP timeout (default 30 seconds).
		$execution_time_so_far = ( function_exists( 'microtime' ) ? microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] : 0 );
		$time_budget_remaining = 25 - $execution_time_so_far; // Leave 5 second buffer for 30s default timeout.

		if ( $time_budget_remaining < 4 ) {
			throw new \Exception( 'Insufficient time budget remaining for embedding (timeout risk)' );
		}

		$endpoint = isset( $settings['endpoint'] ) && ! empty( $settings['endpoint'] ) ? $settings['endpoint'] : self::DEFAULT_ENDPOINT;
		$model    = isset( $settings['model'] ) && ! empty( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;
		$api_url  = rtrim( $endpoint, '/' ) . '/api/embeddings';

		$embeddings = [];

		// Request delay between embeddings (allows Ollama to free memory).
		// Reduced default from 2 to 0.5 seconds to fit within time budget with smaller batches.
		$request_delay = isset( $settings['request_delay_seconds'] ) ? (float) $settings['request_delay_seconds'] : 0.5;

		foreach ( $chunks as $chunk ) {
			$text = $chunk['content'];
			$text_length = strlen( $text );

			Logger::log( "Ollama: Embedding chunk ($text_length chars)" );

			$body = wp_json_encode(
				[
					'model'  => $model,
					'prompt' => $text,
				]
			);

			// RETRY LOGIC WITH SHORT DELAYS (to avoid timeout)
			$max_retries = 2; // Reduced from 3 to 2 retries
			$retry_delay = 0.25; // Reduced from 1 to 0.25 seconds
			$embedding_result = null;

			for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
				try {
					$response = wp_remote_post(
						$api_url,
						[
							'body'    => $body,
							'headers' => [
								'Content-Type' => 'application/json',
							],
							'timeout' => 60, // Reduced from 120 to 60 seconds
						]
					);

					if ( is_wp_error( $response ) ) {
						throw new \Exception( $response->get_error_message() );
					}

					$response_code = wp_remote_retrieve_response_code( $response );
					if ( 200 !== $response_code ) {
						$response_body = wp_remote_retrieve_body( $response );
						throw new \Exception( "HTTP $response_code: $response_body" );
					}

					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( ! isset( $data['embedding'] ) ) {
						throw new \Exception( 'Invalid response format: no embedding field' );
					}

					$embedding_result = $data['embedding'];
					Logger::log( "Ollama: Embedding succeeded on attempt $attempt" );
					break; // Success!

				} catch ( \Exception $e ) {
					$error = $e->getMessage();
					Logger::log( "Ollama: Embedding attempt $attempt failed: $error" );

					if ( $attempt < $max_retries ) {
						Logger::log( "Ollama: Retrying in {$retry_delay}s..." );
						usleep( (int) ( $retry_delay * 1000000 ) ); // Use microsleep to avoid blocking
						$retry_delay = min( 0.5, $retry_delay * 2 ); // Cap at 0.5 seconds
					} else {
						Logger::log( "Ollama: Failed after $max_retries attempts" );
						throw $e; // Final failure
					}
				}
			}

			if ( null === $embedding_result ) {
				throw new \Exception( 'Failed to generate embedding after retries' );
			}

			$embeddings[] = $embedding_result;

			// Rate limiting: Give Ollama time to free memory (using microsleep).
			usleep( (int) ( $request_delay * 1000000 ) );
		}

		return $embeddings;
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

		// Default fallback.
		return 768;
	}

	/**
	 * Test connection with current settings.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return bool
	 * @throws \Exception With details on failure.
	 */
	public function test_connection( array $settings ): bool {
		$endpoint = isset( $settings['endpoint'] ) && ! empty( $settings['endpoint'] ) ? $settings['endpoint'] : self::DEFAULT_ENDPOINT;
		$model    = isset( $settings['model'] ) && ! empty( $settings['model'] ) ? $settings['model'] : self::DEFAULT_MODEL;
		$api_url  = rtrim( $endpoint, '/' ) . '/api/embeddings';

		// Test with a simple prompt.
		$body_data = [
			'model'  => $model,
			'prompt' => 'Hello world',
		];
		$request_body = wp_json_encode( $body_data );

		Logger::log( "Ollama Test Connection: Starting test for endpoint '$api_url' with model '$model'." );
		Logger::log( "Ollama Test Connection: Request Body: $request_body" );

		$args = [
			'body'    => $request_body,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 10,
		];

		if ( ! empty( $settings['api_key'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $settings['api_key'];
		}

		$response = wp_remote_post( $api_url, $args );

		if ( is_wp_error( $response ) ) {
			Logger::log( "Ollama Test Connection: Request failed. Error: " . $response->get_error_message() );
			throw new \Exception( 'Connection failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		Logger::log( "Ollama Test Connection: Response Code: $response_code" );
		Logger::log( "Ollama Test Connection: Response Body: " . substr( $response_body, 0, 500 ) . ( strlen( $response_body ) > 500 ? '...' : '' ) );

		if ( 200 !== $response_code ) {
			throw new \Exception( "Connection failed with status $response_code" );
		}

		Logger::log( "Ollama Test Connection: Success." );

		return true;
	}
}
