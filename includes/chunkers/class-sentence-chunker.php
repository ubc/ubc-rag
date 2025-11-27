<?php

namespace UBC\RAG\Chunkers;

/**
 * Chunks content by sentence count.
 */
class Sentence_Chunker extends Abstract_Chunker {

	/**
	 * Chunk content by sentences.
	 *
	 * @param array $chunks   Array of raw chunks.
	 * @param array $settings Settings (chunk_size).
	 * @param array $metadata Global metadata.
	 * @return array Final chunks.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array {
		$final_chunks = [];
		$chunk_size   = isset( $settings['chunk_size'] ) ? (int) $settings['chunk_size'] : 5;
		// Overlap is harder for sentences, skipping for now or could implement later.

		$global_chunk_index = 0;

		foreach ( $chunks as $raw_chunk ) {
			$content = $raw_chunk['content'];
			
			// Simple sentence splitting. 
			// Look for [.!?] followed by a space or end of string.
			// This is not perfect (e.g. "Mr. Smith") but good enough for v1.
			// We can use a more complex regex or library if needed.
			$sentences = preg_split( '/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
			
			$count = count( $sentences );
			$start = 0;

			if ( $count <= $chunk_size ) {
				$final_chunks[] = [
					'content'  => $content,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];
				continue;
			}

			while ( $start < $count ) {
				$chunk_sentences = array_slice( $sentences, $start, $chunk_size );
				$chunk_text      = implode( ' ', $chunk_sentences );

				$final_chunks[] = [
					'content'  => $chunk_text,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];

				$start += $chunk_size;
			}
		}

		return $final_chunks;
	}
}
