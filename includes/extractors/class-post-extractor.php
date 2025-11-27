<?php

namespace UBC\RAG\Extractors;

use UBC\RAG\Interfaces\ExtractorInterface;

/**
 * Extractor for WordPress Posts and Pages.
 */
class Post_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type or MIME type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		// We support 'post', 'page', and potentially other registered post types.
		// For now, let's stick to standard WP post types.
		// The type passed here comes from get_post_type() usually.
		return in_array( $type, [ 'post', 'page' ], true );
	}

	/**
	 * Extract content from the source.
	 *
	 * @param mixed $source Post ID or WP_Post object.
	 * @return array Extracted chunks.
	 */
	public function extract( $source ): array {
		$post = get_post( $source );

		if ( ! $post ) {
			$this->handle_error( 'Post not found: ' . $source );
			return [];
		}

		$content = $post->post_content;

		// 1. Handle Images: Extract alt text and replace the image tag with it.
		// We use a simple regex for now, but DOMDocument is more robust if needed later.
		// Pattern looks for <img ... alt="text" ... >
		$content = preg_replace_callback(
			'/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i',
			function ( $matches ) {
				$alt_text = trim( $matches[1] );
				if ( ! empty( $alt_text ) ) {
					return ' [Image: ' . $alt_text . '] ';
				}
				return '';
			},
			$content
		);

		// 2. Strip HTML tags.
		// We might want to replace block-level tags with newlines first to preserve structure.
		$content = str_replace(
			[ '</div>', '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '<br>', '<br/>', '<br />' ],
			PHP_EOL,
			$content
		);

		$text = wp_strip_all_tags( $content );

		// 3. Cleanup whitespace.
		// Replace multiple newlines with a single newline (or double for paragraphs).
		$text = preg_replace( "/[\r\n]+/", "\n\n", $text );
		$text = trim( $text );

		$this->log( 'Extracted content from post ' . $post->ID );

		// For posts, we treat the whole content as one chunk for now (or maybe split by headings later).
		// Page metadata is 1 for posts.
		return [
			[
				'content'  => $text,
				'metadata' => [ 'page' => 1 ],
			],
		];
	}
}
