<?php

namespace UBC\RAG\Extractors;

use UBC\RAG\Logger;
use UBC\RAG\Interfaces\ExtractorInterface;

/**
 * Factory for creating and managing extractors.
 */
class Extractor_Factory {

	/**
	 * Singleton instance.
	 *
	 * @var Extractor_Factory
	 */
	private static $instance = null;

	/**
	 * Registered extractors.
	 *
	 * @var array
	 */
	private $extractors = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return Extractor_Factory
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize the factory.
	 * Fires the registration hook.
	 */
	public function init() {
		// Allow other plugins (and this one) to register extractors.
		do_action( 'ubc_rag_register_extractors', $this );
	}

	/**
	 * Register an extractor for a specific type.
	 *
	 * @param string $type       MIME type or content type identifier (e.g., 'application/pdf', 'post').
	 * @param string $class_name Fully qualified class name implementing ExtractorInterface.
	 * @return void
	 */
	public function register_extractor( $type, $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			Logger::log( "Error: Extractor class $class_name not found." );
			return;
		}

		if ( ! in_array( ExtractorInterface::class, class_implements( $class_name ), true ) ) {
			Logger::log( "Error: Class $class_name does not implement ExtractorInterface." );
			return;
		}

		$this->extractors[ $type ] = $class_name;
		// Logger::log( "Registered extractor for $type: $class_name" );
	}

	/**
	 * Get an extractor instance for the given type.
	 *
	 * @param string $type MIME type or content type.
	 * @return ExtractorInterface|null Extractor instance or null if not found.
	 */
	public function get_extractor( $type ) {
		if ( isset( $this->extractors[ $type ] ) ) {
			$class = $this->extractors[ $type ];
			return new $class();
		}

		return null;
	}
}
