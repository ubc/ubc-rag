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
	 * Get the dimension size of embeddings from this provider.
	 *
	 * @return int
	 */
	public function get_dimensions(): int;

	/**
	 * Test connection with current settings.
	 *
	 * @return bool
	 * @throws \Exception With details on failure.
	 */
	public function test_connection(): bool;
}
