<?php

namespace UBC\RAG;

use UBC\RAG\Interfaces\ChunkerInterface;
use UBC\RAG\Chunkers\Character_Chunker;
use UBC\RAG\Chunkers\Word_Chunker;
use UBC\RAG\Chunkers\Sentence_Chunker;
use UBC\RAG\Chunkers\Paragraph_Chunker;
use UBC\RAG\Chunkers\Page_Chunker;

/**
 * Factory for creating Chunker instances.
 */
class Chunker_Factory {

	/**
	 * Singleton instance.
	 *
	 * @var Chunker_Factory
	 */
	private static $instance = null;

	/**
	 * Registered chunkers.
	 *
	 * @var array
	 */
	private $chunkers = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return Chunker_Factory
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
		// Allow other plugins (and this one) to register chunkers.
		do_action( 'ubc_rag_register_chunkers', $this );
	}

	/**
	 * Register a chunker for a specific strategy.
	 *
	 * @param string $strategy   Strategy name (e.g., 'paragraph', 'sentence').
	 * @param string $class_name Fully qualified class name implementing ChunkerInterface.
	 * @return void
	 */
	public function register_chunker( $strategy, $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			Logger::log( "Error: Chunker class $class_name not found." );
			return;
		}

		if ( ! in_array( ChunkerInterface::class, class_implements( $class_name ), true ) ) {
			Logger::log( "Error: Class $class_name does not implement ChunkerInterface." );
			return;
		}

		$this->chunkers[ $strategy ] = $class_name;
	}

	/**
	 * Get a chunker instance by strategy.
	 *
	 * @param string $strategy Strategy name (character, word, sentence, paragraph, page).
	 * @return ChunkerInterface
	 */
	public function get_chunker( string $strategy ): ChunkerInterface {
		if ( isset( $this->chunkers[ $strategy ] ) ) {
			$class = $this->chunkers[ $strategy ];
			return new $class();
		}

		// Fallback to Page_Chunker if strategy not found
		return new Page_Chunker();
	}
}
