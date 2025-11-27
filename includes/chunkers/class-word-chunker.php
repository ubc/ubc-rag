<?php

namespace UBC\RAG\Chunkers;

/**
 * Chunks content by word count.
 */
class Word_Chunker extends Abstract_Chunker {

	/**
	 * Chunk content by words.
	 *
	 * @param array $chunks   Array of raw chunks.
	 * @param array $settings Settings (chunk_size, overlap).
	 * @param array $metadata Global metadata.
	 * @return array Final chunks.
	 */
	public function chunk( array $chunks, array $settings, array $metadata ): array {
		$final_chunks = [];
		$chunk_size   = isset( $settings['chunk_size'] ) ? (int) $settings['chunk_size'] : 50;
		$overlap      = isset( $settings['overlap'] ) ? (int) $settings['overlap'] : 0;

		if ( $overlap >= $chunk_size ) {
			$overlap = 0;
		}

		$global_chunk_index = 0;

		foreach ( $chunks as $raw_chunk ) {
			$content = $raw_chunk['content'];
			// Split by whitespace
			$words = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
			$count = count( $words );
			$start = 0;

			if ( $count <= $chunk_size ) {
				$final_chunks[] = [
					'content'  => $content,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];
				continue;
			}

			while ( $start < $count ) {
				$chunk_words = array_slice( $words, $start, $chunk_size );
				$chunk_text  = implode( ' ', $chunk_words );

				$final_chunks[] = [
					'content'  => $chunk_text,
					'metadata' => $this->merge_metadata( $metadata, $raw_chunk['metadata'], $global_chunk_index++ ),
				];

				$start += ($chunk_size - $overlap);
			}
		}

		return $final_chunks;
	}
}
