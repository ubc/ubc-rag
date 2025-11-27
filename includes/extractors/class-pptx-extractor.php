<?php

namespace UBC\RAG\Extractors;

use PhpOffice\PhpPresentation\IOFactory;
use Exception;

/**
 * Extractor for PPTX files.
 */
class Pptx_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type or MIME type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return 'application/vnd.openxmlformats-officedocument.presentationml.presentation' === $type;
	}

	/**
	 * Extract content from a PPTX attachment.
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
			$reader = IOFactory::createReader( 'PowerPoint2007' );
			$presentation = $reader->load( $file_path );
			$chunks = [];

			foreach ( $presentation->getAllSlides() as $index => $slide ) {
				$slide_text = '';
				foreach ( $slide->getShapeCollection() as $shape ) {
					$slide_text .= $this->process_shape( $shape );
				}
				
				if ( ! empty( trim( $slide_text ) ) ) {
					$chunks[] = [
						'content'  => trim( $slide_text ),
						'metadata' => [ 'page' => $index + 1 ], // Slides are pages in this context
					];
				}
			}

			$this->log( "Extracted " . count( $chunks ) . " slides from PPTX $content_id" );

			return $chunks;
		} catch ( \Throwable $e ) {
			$this->handle_error( "PPTX Parsing Error: " . $e->getMessage() );
			return [];
		}
	}

	/**
	 * Recursively process a shape to extract text.
	 *
	 * @param \PhpOffice\PhpPresentation\Shape\AbstractShape $shape The shape to process.
	 * @return string Extracted text.
	 */
	private function process_shape( $shape ) {
		$text = '';

		// Handle RichText (Standard Text Box)
		if ( $shape instanceof \PhpOffice\PhpPresentation\Shape\RichText ) {
			foreach ( $shape->getParagraphs() as $paragraph ) {
				$text .= $paragraph->getPlainText() . "\n";
			}
		}
		// Handle Tables
		elseif ( $shape instanceof \PhpOffice\PhpPresentation\Shape\Table ) {
			foreach ( $shape->getRows() as $row ) {
				foreach ( $row->getCells() as $cell ) {
					$text .= $this->process_shape( $cell ); // Cells act like shapes
				}
				$text .= "\n";
			}
		}
		// Handle Groups (Recursive)
		elseif ( $shape instanceof \PhpOffice\PhpPresentation\Shape\Group ) {
			foreach ( $shape->getShapeCollection() as $child ) {
				$text .= $this->process_shape( $child );
			}
		}
		// Handle generic container (like Table Cell)
		elseif ( method_exists( $shape, 'getShapeCollection' ) ) {
			foreach ( $shape->getShapeCollection() as $child ) {
				$text .= $this->process_shape( $child );
			}
		}

		return $text;
	}
}
