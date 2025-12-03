<?php

namespace UBC\RAG;

use UBC\RAG\Logger;

/**
 * Factory for managing and registering content types.
 * Mirrors the pattern used by Extractor_Factory.
 */
class Content_Type_Factory {

	/**
	 * Singleton instance.
	 *
	 * @var Content_Type_Factory
	 */
	private static $instance = null;

	/**
	 * Registered content types.
	 *
	 * @var array
	 */
	private $content_types = [];

	/**
	 * Get the singleton instance.
	 *
	 * @return Content_Type_Factory
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
		// Allow other plugins (and this one) to register content types.
		do_action( 'ubc_rag_register_content_types', $this );
	}

	/**
	 * Register a content type.
	 *
	 * @param string $slug The content type slug (e.g., 'post', 'page', 'attachment', 'link').
	 * @param array  $args Arguments for the content type:
	 *                      - label (string): Human-readable name.
	 *                      - description (string): Description of the content type.
	 *                      - extractor (string): Extractor identifier (maps to extractor factory).
	 *                      - default_enabled (bool): Whether enabled by default.
	 * @return void
	 *
	 * @example
	 * $factory->register_content_type( 'post', [
	 *     'label'            => __( 'Posts', 'ubc-rag' ),
	 *     'description'      => __( 'WordPress blog posts', 'ubc-rag' ),
	 *     'extractor'        => 'post',
	 *     'default_enabled'  => true,
	 * ] );
	 */
	public function register_content_type( $slug, $args = [] ) {
		// Validate slug.
		if ( empty( $slug ) ) {
			Logger::log( 'Error: Cannot register content type with empty slug.' );
			return;
		}

		// Merge with defaults.
		$defaults = [
			'label'            => $slug,
			'description'      => '',
			'extractor'        => '',
			'default_enabled'  => false,
		];

		$args = wp_parse_args( $args, $defaults );

		// Store the registration.
		$this->content_types[ $slug ] = $args;
		// Logger::log( "Registered content type: $slug" );
	}

	/**
	 * Get all registered content types.
	 *
	 * @return array Registered content types keyed by slug.
	 */
	public function get_registered_content_types() {
		return $this->content_types;
	}

	/**
	 * Check if a content type is registered.
	 *
	 * @param string $content_type The content type slug.
	 * @return bool True if registered, false otherwise.
	 */
	public function is_registered( $content_type ) {
		return isset( $this->content_types[ $content_type ] );
	}

	/**
	 * Get the registration data for a specific content type.
	 *
	 * @param string $content_type The content type slug.
	 * @return array|null The registration data, or null if not found.
	 */
	public function get_registration( $content_type ) {
		return isset( $this->content_types[ $content_type ] ) ? $this->content_types[ $content_type ] : null;
	}
}
