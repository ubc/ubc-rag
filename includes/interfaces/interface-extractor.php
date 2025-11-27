<?php

namespace UBC\RAG\Interfaces;

/**
 * Interface for Content Extractors.
 */
interface ExtractorInterface {

	/**
	 * Extract content from the source.
	 *
	 * @param mixed $source The source to extract from (e.g., post ID, file path).
	 * @return array An array of chunks, where each chunk is ['content' => string, 'metadata' => array].
	 */
	public function extract( $source ): array;

	/**
	 * Check if this extractor supports the given MIME type.
	 *
	 * @param string $mime_type The MIME type to check.
	 * @return bool True if supported, false otherwise.
	 */
	public function supports( $mime_type ): bool;
}
