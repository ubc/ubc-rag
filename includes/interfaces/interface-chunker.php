<?php

namespace UBC\RAG\Interfaces;

/**
 * Chunker Interface.
 */
interface ChunkerInterface {
	/**
	 * Chunk content according to strategy.
	 *
	 * @param array $chunks   Array of raw chunks (from Extractor).
	 * @param array $settings Strategy-specific settings (chunk_size, overlap, etc.).
	 * @param array $metadata Content metadata (post_id, post_type, etc.).
	 * @return array Array of final chunks with metadata.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array;
}
