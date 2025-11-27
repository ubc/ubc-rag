<?php

namespace UBC\RAG\Chunkers;

/**
 * Chunks content by paragraph count.
 */
class Paragraph_Chunker extends Abstract_Chunker {

	/**
	 * Chunk content by paragraphs.
	 *
	 * @param array $chunks   Array of raw chunks.
	 * @param array $settings Settings (chunk_size).
	 * @param array $metadata Global metadata.
	 * @return array Final chunks.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array {
		$final_chunks = [];
		$chunk_size   = isset( $settings['chunk_size'] ) ? (int) $settings['chunk_size'] : 3;

		$global_chunk_index = 0;

		foreach ( $chunks as $raw_chunk ) {
			$content = $raw_chunk['content'];
			
			// Split by double newlines
			$paragraphs = preg_split( '/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY );
			
			$count = count( $paragraphs );
			$start = 0;

			if ( $count <= $chunk_size ) {
				$final_chunks[] = [
					'content'  => $content,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];
				continue;
			}

			while ( $start < $count ) {
				$chunk_paragraphs = array_slice( $paragraphs, $start, $chunk_size );
				$chunk_text       = implode( "\n\n", $chunk_paragraphs );

				$final_chunks[] = [
					'content'  => $chunk_text,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];

				$start += $chunk_size;
			}
		}



		// Safety check: If any chunk is still too large (e.g. > 1500 chars), split by words.
		// This handles "smushed" transcripts that look like one giant paragraph.
		$safe_chunks = [];
		$max_chars   = 1500; // Reduced from 2000 (approx 375 tokens) to fit time budget
		$word_limit  = 250;  // Reduced from 300 for smaller chunks

		foreach ( $final_chunks as $chunk ) {
			if ( strlen( $chunk['content'] ) > $max_chars ) {
				\UBC\RAG\Logger::log( "Chunk too large (" . strlen( $chunk['content'] ) . " chars). Falling back to Word splitting for this chunk." );
				
				$words = explode( ' ', $chunk['content'] );
				$word_count = count( $words );
				$w_start = 0;

				while ( $w_start < $word_count ) {
					$chunk_words = array_slice( $words, $w_start, $word_limit );
					$chunk_text  = implode( ' ', $chunk_words );
					
					$safe_chunks[] = [
						'content'  => $chunk_text,
						'metadata' => $this->merge_metadata( $metadata, $chunk['metadata'], $global_chunk_index++ ),
					];
					
					$w_start += $word_limit;
				}
			} else {
				$safe_chunks[] = $chunk;
			}
		}

		return $safe_chunks;
	}
}
