<?php

namespace UBC\RAG\Extractors;

use UBC\RAG\Interfaces\ExtractorInterface;
use UBC\RAG\Logger;

/**
 * Abstract Base Class for Extractors.
 */
abstract class Abstract_Extractor implements ExtractorInterface {

	/**
	 * Log a message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	protected function log( $message ) {
		Logger::log( '[Extractor] ' . $message );
	}

	/**
	 * Handle errors during extraction.
	 *
	 * @param string $message Error message.
	 * @return string Empty string or error indicator.
	 */
	protected function handle_error( $message ) {
		$this->log( 'Error: ' . $message );
		return '';
	}
}
