<?php

namespace UBC\RAG\Chunkers;

/**
 * Chunks content by character count.
 */
class Character_Chunker extends Abstract_Chunker {

	/**
	 * Chunk content by characters.
	 *
	 * @param array $chunks   Array of raw chunks.
	 * @param array $settings Settings (chunk_size, overlap).
	 * @param array $metadata Global metadata.
	 * @return array Final chunks.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array {
		$final_chunks = [];
		$chunk_size   = isset( $settings['chunk_size'] ) ? (int) $settings['chunk_size'] : 300;
		$overlap      = isset( $settings['overlap'] ) ? (int) $settings['overlap'] : 0;
		
		// Ensure overlap is less than chunk size to prevent infinite loops
		if ( $overlap >= $chunk_size ) {
			$overlap = 0;
		}

		$global_chunk_index = 0;

		foreach ( $chunks as $raw_chunk ) {
			$content = $raw_chunk['content'];
			$length  = mb_strlen( $content );
			$start   = 0;

			// If content is shorter than chunk size, take it all.
			if ( $length <= $chunk_size ) {
				$final_chunks[] = [
					'content'  => $content,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];
				continue;
			}

			while ( $start < $length ) {
				$chunk_text = mb_substr( $content, $start, $chunk_size );
				
				// If we are not at the end, try to break at a space to avoid cutting words
				if ( ($start + $chunk_size) < $length ) {
					$last_space = mb_strrpos( $chunk_text, ' ' );
					if ( $last_space !== false && $last_space > ($chunk_size * 0.8) ) {
						// Only cut back if the space is in the last 20% of the chunk
						$chunk_text = mb_substr( $chunk_text, 0, $last_space );
						$step = $last_space;
					} else {
						$step = $chunk_size;
					}
				} else {
					$step = $chunk_size;
				}

				$final_chunks[] = [
					'content'  => trim( $chunk_text ),
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];

				$start += ($step - $overlap);
				
				// Prevent infinite loop if step - overlap <= 0 (shouldn't happen with check above)
				if ( ($step - $overlap) <= 0 ) {
					$start += 1; 
				}
			}
		}

		return $final_chunks;
	}
}
