<?php

namespace UBC\RAG\Extractors;

use Smalot\PdfParser\Parser;
use Exception;

/**
 * Extractor for PDF files.
 */
class PDF_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type or MIME type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return 'application/pdf' === $type;
	}

	/**
	 * Extract content from a PDF attachment.
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

		try {
			$parser = new Parser();
			$pdf    = $parser->parseFile( $file_path );
			$chunks = [];

			$pages = $pdf->getPages();
			foreach ( $pages as $index => $page ) {
				$text = $page->getText();
				if ( ! empty( trim( $text ) ) ) {
					$chunks[] = [
						'content'  => trim( $text ),
						'metadata' => [ 'page' => $index + 1 ], // Pages are 0-indexed in array, but 1-indexed for humans
					];
				}
			}

			$this->log( "Extracted " . count( $chunks ) . " pages from PDF $content_id" );

			return $chunks;
		} catch ( Exception $e ) {
			$this->handle_error( "PDF Parsing Error: " . $e->getMessage() );
			return [];
		}
	}
}
