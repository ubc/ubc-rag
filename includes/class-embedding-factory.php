<?php

namespace UBC\RAG;

use UBC\RAG\Interfaces\EmbeddingProviderInterface;
use UBC\RAG\Logger;

/**
 * Factory for creating Embedding Provider instances.
 */
class Embedding_Factory {

	/**
	 * Singleton instance.
	 *
	 * @var Embedding_Factory
	 */
	private static $instance = null;

	/**
	 * Registered providers.
	 *
	 * @var array
	 */
	private $providers = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return Embedding_Factory
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
		// Allow other plugins (and this one) to register providers.
		do_action( 'ubc_rag_register_embedding_providers', $this );
	}

	/**
	 * Register a provider.
	 *
	 * @param string $slug       Provider slug (e.g., 'openai', 'ollama').
	 * @param string $class_name Fully qualified class name implementing EmbeddingProviderInterface.
	 * @return void
	 */
	public function register_provider( $slug, $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			Logger::log( "Error: Embedding Provider class $class_name not found." );
			return;
		}

		if ( ! in_array( EmbeddingProviderInterface::class, class_implements( $class_name ), true ) ) {
			Logger::log( "Error: Class $class_name does not implement EmbeddingProviderInterface." );
			return;
		}

		$this->providers[ $slug ] = $class_name;
	}

	/**
	 * Get a provider instance by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return EmbeddingProviderInterface|null
	 */
	public function get_provider( string $slug ) {
		if ( isset( $this->providers[ $slug ] ) ) {
			$class = $this->providers[ $slug ];
			return new $class();
		}

		return null;
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array
	 */
	public function get_registered_providers() {
		return $this->providers;
	}
}
