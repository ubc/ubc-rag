<?php

namespace UBC\RAG\Chunkers;

use UBC\RAG\Logger;

/**
 * Chunks content by page (pass-through).
 */
class Page_Chunker extends Abstract_Chunker {

	/**
	 * Chunk content by page.
	 *
	 * @param array $chunks   Array of raw chunks.
	 * @param array $settings Settings (ignored).
	 * @param array $metadata Global metadata.
	 * @return array Final chunks.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array {
		$final_chunks = [];
		$global_chunk_index = 0;

		// Check if we have a single large chunk (likely DOCX or similar where page detection failed).
		// 3000 characters is roughly 750 tokens, reduced from 4000 to fit time budget.
		if ( count( $chunks ) === 1 && strlen( $chunks[0]['content'] ) > 3000 ) {
			Logger::log( 'Single large chunk detected (length: ' . strlen( $chunks[0]['content'] ) . '). Falling back to Paragraph chunking.' );
			
			// Instantiate Paragraph Chunker
			$fallback_chunker = new Paragraph_Chunker();
			// Use default settings for paragraph chunking if not explicitly provided, 
			// but we can try to respect some global defaults if we had them. 
			// For now, we'll use a sensible default of 3 paragraphs per chunk.
			$fallback_settings = [ 'chunk_size' => 3 ]; 
			
			return $fallback_chunker->chunk( $chunks, $fallback_settings, $metadata );
		}

		foreach ( $chunks as $raw_chunk ) {
			// Just pass through the raw chunk (which represents a page/slide)
			// But ensure we merge metadata and add chunk index
			$final_chunks[] = [
				'content'  => $raw_chunk['content'],
				'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
			];
		}

		return $final_chunks;
	}
}
