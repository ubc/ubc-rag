<?php

namespace UBC\RAG;

/**
 * Content Type Helper class.
 * Provides utility methods for content type validation and configuration.
 */
class Content_Type_Helper {

	/**
	 * Check if a content type is enabled for indexing.
	 *
	 * @param string $content_type The content type to check (e.g., 'post', 'page', 'attachment', 'link').
	 * @return bool True if the content type is enabled, false otherwise.
	 *
	 * @example
	 * if ( Content_Type_Helper::is_content_type_enabled( 'post' ) ) {
	 *     // Process the post for indexing
	 * }
	 */
	public static function is_content_type_enabled( $content_type ) {
		$settings = Settings::get_settings();

		if ( ! isset( $settings['content_types'][ $content_type ] ) ) {
			return false;
		}

		return isset( $settings['content_types'][ $content_type ]['enabled'] ) && $settings['content_types'][ $content_type ]['enabled'];
	}

	/**
	 * Get the configuration for a content type.
	 *
	 * @param string $content_type The content type to get configuration for.
	 * @return array Array containing enabled, auto_index, chunking_strategy, and chunking_settings.
	 *               Returns empty array if content type not found.
	 *
	 * @example
	 * $config = Content_Type_Helper::get_content_type_config( 'post' );
	 * if ( ! empty( $config ) ) {
	 *     $strategy = $config['chunking_strategy'];
	 * }
	 */
	public static function get_content_type_config( $content_type ) {
		$settings = Settings::get_settings();

		if ( ! isset( $settings['content_types'][ $content_type ] ) ) {
			return [];
		}

		return $settings['content_types'][ $content_type ];
	}

	/**
	 * Get the chunking strategy for a content type.
	 *
	 * @param string $content_type The content type to get the strategy for.
	 * @return string The chunking strategy, or 'paragraph' if not configured.
	 *
	 * @example
	 * $strategy = Content_Type_Helper::get_chunking_strategy( 'post' );
	 * echo "Using $strategy strategy";
	 */
	public static function get_chunking_strategy( $content_type ) {
		$config = self::get_content_type_config( $content_type );

		return isset( $config['chunking_strategy'] ) ? $config['chunking_strategy'] : 'paragraph';
	}

	/**
	 * Get the chunking settings for a content type.
	 *
	 * @param string $content_type The content type to get settings for.
	 * @return array The chunking settings (chunk_size, overlap, etc.).
	 *               Returns empty array if not configured.
	 *
	 * @example
	 * $chunking_settings = Content_Type_Helper::get_chunking_settings( 'post' );
	 * $chunk_size = $chunking_settings['chunk_size'] ?? 1000;
	 */
	public static function get_chunking_settings( $content_type ) {
		$config = self::get_content_type_config( $content_type );

		return isset( $config['chunking_settings'] ) ? $config['chunking_settings'] : [];
	}

	/**
	 * Check if the WordPress link manager is enabled.
	 *
	 * Properly respects the `pre_option_link_manager_enabled` filter
	 * that plugins use to enable the link manager.
	 *
	 * @return bool True if link manager is enabled, false otherwise.
	 *
	 * @example
	 * if ( Content_Type_Helper::is_link_manager_enabled() ) {
	 *     // Link manager is available
	 * }
	 */
	public static function is_link_manager_enabled() {
		// WordPress stores this as 'Y' when enabled, or not set when disabled.
		// Using get_option() respects the 'pre_option_link_manager_enabled' filter
		// that other plugins use to enable the link manager.
		$enabled = get_option( 'link_manager_enabled' );

		// Support both 'Y' (stored as string) and true (if stored as boolean)
		return 'Y' === $enabled || true === $enabled;
	}
}
