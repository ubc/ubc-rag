<?php

namespace UBC\RAG\Interfaces;

/**
 * Embedding Provider Interface.
 */
interface EmbeddingProviderInterface {
	/**
	 * Generate embeddings for chunks of text.
	 *
	 * @param array $chunks   Array of chunk data (text + metadata).
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings (same order as input).
	 * @throws \Exception On failure.
	 */
	public function embed( array $chunks, array $settings ): array;

	/**
	 * Generate embeddings for a batch of chunks.
	 *
	 * This is intended for bulk operations where latency is less critical than throughput.
	 * Providers may implement this using batch APIs or simply loop through chunks.
	 *
	 * @param array $chunks   Array of chunk data.
	 * @param array $settings Provider-specific settings.
	 * @return array Array of embeddings.
	 * @throws \Exception On failure.
	 */
	public function embed_batch( array $chunks, array $settings ): array;

	/**
	 * Get the dimension size of embeddings from this provider.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return int
	 */
	public function get_dimensions( array $settings ): int;

	/**
	 * Test connection with current settings.
	 *
	 * @param array $settings Provider-specific settings.
	 * @return bool
	 * @throws \Exception With details on failure.
	 */
	public function test_connection( array $settings ): bool;
}
