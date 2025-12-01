<?php

namespace UBC\RAG;

use UBC\RAG\Interfaces\VectorStorageInterface;
use UBC\RAG\Vector_Stores\MySQL_Vector_Store;
use UBC\RAG\Vector_Stores\Qdrant_Vector_Store;

/**
 * Vector Store Factory.
 * Manages vector storage providers.
 */
class Vector_Store_Factory {

	/**
	 * Instance of this class.
	 *
	 * @var Vector_Store_Factory
	 */
	private static $instance = null;

	/**
	 * Registered stores.
	 *
	 * @var array
	 */
	private $stores = [];

	/**
	 * Get instance.
	 *
	 * @return Vector_Store_Factory
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->register_stores();
		
		// Allow other plugins to register stores.
		do_action( 'ubc_rag_register_vector_stores', $this );
	}

	/**
	 * Register built-in stores.
	 *
	 * @return void
	 */
	private function register_stores() {
		$this->register_store( 'mysql', MySQL_Vector_Lib_Store::class );
		
		// We will register Qdrant later when the class exists, or check existence here.
		// For now, let's assume we'll create it shortly.
		if ( class_exists( 'UBC\RAG\Vector_Stores\Qdrant_Vector_Store' ) ) {
			$this->register_store( 'qdrant', \UBC\RAG\Vector_Stores\Qdrant_Vector_Store::class );
		}
	}

	/**
	 * Register a store.
	 *
	 * @param string $slug  Store slug.
	 * @param string $class Class name.
	 * @return void
	 */
	public function register_store( $slug, $class ) {
		$this->stores[ $slug ] = $class;
	}

	/**
	 * Get a store instance.
	 *
	 * @param string $slug Store slug.
	 * @return VectorStorageInterface|null
	 */
	public function get_store( $slug ) {
		if ( ! isset( $this->stores[ $slug ] ) ) {
			return null;
		}

		$class = $this->stores[ $slug ];
		if ( ! class_exists( $class ) ) {
			return null;
		}

		return new $class();
	}
	
	/**
	 * Get the active store based on settings.
	 * 
	 * @return VectorStorageInterface|null
	 */
	public function get_active_store() {
		$settings = Settings::get_settings();
		$slug = isset( $settings['vector_store']['provider'] ) ? $settings['vector_store']['provider'] : 'mysql';
		
		// Fallback to mysql if not set or not found (safety net)
		$store = $this->get_store( $slug );
		if ( ! $store && 'mysql' !== $slug ) {
			$store = $this->get_store( 'mysql' );
		}
		
		return $store;
	}
	
	/**
	 * Get registered stores.
	 * 
	 * @return array
	 */
	public function get_registered_stores() {
		return $this->stores;
	}

	/**
	 * Get the standardized collection name.
	 * Format: site_{blog_id}_{hash}
	 *
	 * @return string
	 */
	public function get_collection_name() {
		$blog_id  = get_current_blog_id();
		$site_url = get_site_url();
		$hash     = substr( hash( 'sha256', $site_url ), 0, 8 );
		return "site_{$blog_id}_{$hash}";
	}
}
