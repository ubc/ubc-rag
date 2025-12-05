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

		// 2. Handle Tables: Convert HTML tables to Markdown.
		$content = $this->convert_tables_to_markdown( $content );

		// 3. Strip HTML tags.
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

	/**
	 * Convert HTML tables to Markdown tables.
	 *
	 * @param string $content HTML content.
	 * @return string Content with tables converted to Markdown.
	 */
	private function convert_tables_to_markdown( $content ) {
		// If no tables, return early.
		if ( false === strpos( $content, '<table' ) ) {
			return $content;
		}

		// Suppress warnings for malformed HTML fragments.
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		// Hack to handle UTF-8 correctly.
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$tables = $dom->getElementsByTagName( 'table' );

		if ( 0 === $tables->length ) {
			return $content;
		}

		// We need to replace tables in the original content. 
		// Since DOMDocument might alter other HTML, it's safer to do replacements on the original string
		// or use the DOM to save the whole HTML. 
		// However, saving the whole HTML might add tags we don't want (like <html><body>).
		// A safer approach for this specific task is to iterate backwards (if modifying DOM) or just use the DOM output.
		// Given we are about to strip tags anyway, using the DOM output is fine.

		foreach ( $tables as $table ) {
			$markdown = $this->table_to_markdown( $table, $dom );
			
			// Create a text node with the markdown.
			$text_node = $dom->createTextNode( "\n\n" . $markdown . "\n\n" );
			
			// Replace the table with the text node.
			$table->parentNode->replaceChild( $text_node, $table );
		}

		// Save HTML.
		$html = $dom->saveHTML();
		
		// Remove the XML declaration we added.
		$html = str_replace( '<?xml encoding="UTF-8">', '', $html );
		
		return $html;
	}

	/**
	 * Convert a single DOMElement table to Markdown.
	 *
	 * @param \DOMElement  $table The table element.
	 * @param \DOMDocument $dom   The parent DOM document.
	 * @return string Markdown string.
	 */
	private function table_to_markdown( $table, $dom ) {
		$markdown_output = "";
		$rows = [];
		
		// Process Headers.
		$thead = $table->getElementsByTagName( 'thead' )->item( 0 );
		$headers = [];
		if ( $thead ) {
			$th_rows = $thead->getElementsByTagName( 'tr' );
			foreach ( $th_rows as $row ) {
				$cols = [];
				foreach ( $row->childNodes as $node ) {
					if ( 'th' === $node->nodeName || 'td' === $node->nodeName ) {
						// Strip tags but keep content.
						$cols[] = trim( strip_tags( $dom->saveHTML( $node ) ) );
					}
				}
				if ( ! empty( $cols ) ) {
					$headers[] = $cols;
				}
			}
		}

		// Process Body.
		$tbody = $table->getElementsByTagName( 'tbody' )->item( 0 );
		$body_rows = [];
		if ( $tbody ) {
			$tr_rows = $tbody->getElementsByTagName( 'tr' );
			foreach ( $tr_rows as $row ) {
				$cols = [];
				foreach ( $row->childNodes as $node ) {
					if ( 'th' === $node->nodeName || 'td' === $node->nodeName ) {
						$cols[] = trim( strip_tags( $dom->saveHTML( $node ) ) );
					}
				}
				if ( ! empty( $cols ) ) {
					$body_rows[] = $cols;
				}
			}
		}

		// If no thead, check if first row of tbody looks like a header? 
		// Or just treat all as body.
		// If headers exist, use them.
		if ( ! empty( $headers ) ) {
			$header_row = $headers[0];
			$markdown_output .= "| " . implode( " | ", $header_row ) . " |\n";
			$markdown_output .= "| " . implode( " | ", array_fill( 0, count( $header_row ), "---" ) ) . " |\n";
		}

		foreach ( $body_rows as $row ) {
			// Ensure row has same number of columns as header if possible, or just output.
			$markdown_output .= "| " . implode( " | ", $row ) . " |\n";
		}
		
		return $markdown_output;
	}
}
