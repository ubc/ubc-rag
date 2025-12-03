<?php

namespace UBC\RAG\Extractors;

use UBC\RAG\Interfaces\ExtractorInterface;

/**
 * Extractor for WordPress Links (Bookmarks).
 *
 * Extracts content from WordPress links/bookmarks when the link manager is enabled.
 */
class Link_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return 'link' === $type;
	}

	/**
	 * Extract content from a link.
	 *
	 * @param mixed $source Link ID.
	 * @return array Extracted chunks.
	 */
	public function extract( $source ): array {
		// Check if link manager is enabled.
		if ( ! \UBC\RAG\Content_Type_Helper::is_link_manager_enabled() ) {
			$this->log( 'Link manager is not enabled.' );
			return [];
		}

		global $wpdb;

		// Retrieve the link from the database.
		$link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->links} WHERE link_id = %d", $source ) );

		if ( ! $link ) {
			$this->handle_error( "Link not found: $source" );
			return [];
		}

		// Extract link categories.
		$categories = wp_get_object_terms( $link->link_id, 'link_category', [ 'fields' => 'names' ] );
		if ( is_wp_error( $categories ) ) {
			$categories = [];
		}

		// Build the content string.
		$link_name = $link->link_name ? $link->link_name : 'Unnamed Link';
		$link_url = $link->link_url ? $link->link_url : '';
		$description = $link->link_description ? $link->link_description : '';

		$content = $link_name;
		if ( ! empty( $link_url ) ) {
			$content .= "\n[Source: " . $link_url . "]";
		}
		if ( ! empty( $description ) ) {
			$content .= "\n\nDescription: " . $description;
		}

		$this->log( "Extracted content from link {$link->link_id}: {$link->link_name}" );

		// Return as single chunk.
		return [
			[
				'content'  => $content,
				'metadata' => [
					'page'         => 1,
					'link_id'      => (int) $link->link_id,
					'link_url'     => $link_url,
					'link_rating'  => (int) $link->link_rating,
					'categories'   => $categories,
				],
			],
		];
	}
}
