<?php

namespace UBC\RAG\Extractors;

use PhpOffice\PhpWord\IOFactory;
use Exception;

/**
 * Extractor for DOCX files.
 */
class Docx_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type or MIME type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' === $type;
	}

	/**
	 * Extract content from a DOCX attachment.
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
			$phpWord = IOFactory::load( $file_path );
			$text = '';

			foreach ( $phpWord->getSections() as $section ) {
				foreach ( $section->getElements() as $element ) {
					$text .= $this->extract_text_from_element( $element );
					$text .= "\n\n";
				}
			}

			$this->log( "Extracted content from DOCX $content_id" );

			// DOCX doesn't support page numbers easily, so we return one chunk.
			return [
				[
					'content'  => trim( $text ),
					'metadata' => [ 'page' => 1 ],
				],
			];
		} catch ( \Throwable $e ) {
			$this->handle_error( "DOCX Parsing Error: " . $e->getMessage() . ". Attempting fallback extraction." );
			return $this->fallback_extract( $file_path );
		}
	}

	/**
	 * Fallback extraction using ZipArchive and XML parsing.
	 *
	 * @param string $file_path Path to the DOCX file.
	 * @return array Extracted chunks.
	 */
	private function fallback_extract( $file_path ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->handle_error( "ZipArchive class not found. Cannot perform fallback extraction." );
			return [];
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			$this->handle_error( "Could not open DOCX as zip archive." );
			return [];
		}

		// Try to find the main document XML.
		$xml_content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( ! $xml_content ) {
			$this->handle_error( "Could not find word/document.xml in DOCX archive." );
			return [];
		}

		// Parse XML.
		$dom = new \DOMDocument();
		// Suppress warnings for invalid XML structure (which is likely why the main library failed).
		libxml_use_internal_errors( true );
		$dom->loadXML( $xml_content );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );
		$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		// Extract all text nodes (<w:t>).
		$nodes = $xpath->query( '//w:t' );
		$text = '';

		foreach ( $nodes as $node ) {
			$text .= $node->nodeValue . ' ';
		}

		$text = trim( $text );

		if ( empty( $text ) ) {
			$this->handle_error( "Fallback extraction yielded no text." );
			return [];
		}

		$this->log( "Successfully performed fallback extraction for DOCX." );

		return [
			[
				'content'  => $text,
				'metadata' => [ 'page' => 1, 'extraction_method' => 'fallback' ],
			],
		];
	}

	/**
	 * Recursively extract text from a PHPWord element.
	 *
	 * @param mixed $element The element to extract text from.
	 * @return string The extracted text.
	 */
	private function extract_text_from_element( $element ): string {
		$text = '';

		if ( method_exists( $element, 'getText' ) ) {
			// Some elements like TextRun might have getText but return an object or array if not simple text.
			// We need to be careful. Ideally, we check class types.
			// However, standard Text elements return string.
			$content = $element->getText();
			if ( is_string( $content ) ) {
				$text .= $content . ' ';
			}
		}

		// Handle containers (TextRun, Link, etc.)
		if ( method_exists( $element, 'getElements' ) ) {
			foreach ( $element->getElements() as $child ) {
				$text .= $this->extract_text_from_element( $child );
			}
		}

		// Handle Tables
		if ( $element instanceof \PhpOffice\PhpWord\Element\Table ) {
			foreach ( $element->getRows() as $row ) {
				foreach ( $row->getCells() as $cell ) {
					foreach ( $cell->getElements() as $cellElement ) {
						$text .= $this->extract_text_from_element( $cellElement );
					}
					$text .= " | "; // Cell separator
				}
				$text .= "\n"; // Row separator
			}
		}

		return $text;
	}
}
