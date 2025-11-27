<?php

namespace UBC\RAG;

/**
 * Hasher class.
 */
class Hasher {

	/**
	 * Calculate a hash for the given chunks.
	 *
	 * @param array $chunks Array of chunks (from Extractor).
	 * @return string SHA-256 hash.
	 */
	public static function calculate_hash( array $chunks ): string {
		$content = '';
		foreach ( $chunks as $chunk ) {
			$content .= $chunk['content'];
		}
		return hash( 'sha256', $content );
	}
}
