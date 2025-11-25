<?php

namespace UBC\RAG\Interfaces;

/**
 * Chunker Interface.
 */
interface ChunkerInterface {
	/**
	 * Chunk content according to strategy.
	 *
	 * @param string $content  The content to chunk.
	 * @param array  $settings Strategy-specific settings (chunk_size, overlap, etc.).
	 * @param array  $metadata Content metadata (post_id, post_type, etc.).
	 * @return array Array of chunks with metadata.
	 */
	public function chunk( string $content, array $settings, array $metadata ): array;
}
