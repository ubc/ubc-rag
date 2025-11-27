<?php

namespace UBC\RAG\Extractors;

/**
 * Extractor for Markdown files.
 */
class Markdown_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type or MIME type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return in_array( $type, [ 'text/markdown', 'text/x-markdown' ], true );
	}

	/**
	 * Extract content from a Markdown attachment.
	 *
	 * @param mixed $source Attachment ID.
	 * @return array Extracted chunks.
	 */
	public function extract( $source ): array {
		$content_id = $source;
		$file_path = get_attached_file( $content_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->handle_error( "File not found for attachment $content_id" );
			return [];
		}

		$text = file_get_contents( $file_path );

		if ( false === $text ) {
			$this->handle_error( "Failed to read file $file_path" );
			return [];
		}

		$this->log( "Extracted content from Markdown file $content_id" );

		return [
			[
				'content'  => trim( $text ),
				'metadata' => [ 'page' => 1 ],
			],
		];
	}
}
