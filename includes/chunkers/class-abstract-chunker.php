<?php

namespace UBC\RAG\Chunkers;

use UBC\RAG\Interfaces\ChunkerInterface;

/**
 * Abstract base class for Chunkers.
 */
abstract class Abstract_Chunker implements ChunkerInterface {

	/**
	 * Helper to merge global metadata with chunk-specific metadata.
	 *
	 * @param array $global_metadata Global metadata (post_id, etc.).
	 * @param array $chunk_metadata  Chunk-specific metadata (page, etc.).
	 * @param int   $chunk_index     Index of the chunk within the document.
	 * @return array Merged metadata.
	 */
	protected function merge_metadata( array $global_metadata, array $chunk_metadata, int $chunk_index ): array {
		return array_merge( $global_metadata, $chunk_metadata, [ 'chunk_index' => $chunk_index ] );
	}
}
