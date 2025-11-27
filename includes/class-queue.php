<?php

namespace UBC\RAG;

use UBC\RAG\Logger;

/**
 * Queue class.
 */
class Queue {

	/**
	 * Action hook name.
	 */
	const ACTION_INDEX_ITEM = 'rag_plugin_index_item';

	/**
	 * Push an item to the queue.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @param string $operation    Operation (create, update, delete).
	 * @return string|int|null Action ID or null if failed.
	 */
	public static function push( $content_id, $content_type, $operation = 'update' ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			Logger::log( 'UBC RAG Error: ActionScheduler not found. Cannot queue job.' );
			return null;
		}

		// Unique group for this site to prevent race conditions if needed later.
		$group = 'rag_site_' . get_current_blog_id();

		// Args for the action.
		$args = [
			'site_id'      => get_current_blog_id(),
			'content_id'   => $content_id,
			'content_type' => $content_type,
			'operation'    => $operation,
		];

		// Check if there is already a pending action with these args.
		if ( as_has_scheduled_action( self::ACTION_INDEX_ITEM, $args, $group ) ) {
			Logger::log( sprintf( 'UBC RAG: Job already queued for %s %d (%s)', $content_type, $content_id, $operation ) );
			return null;
		}

		// Enqueue the action.
		// We use async action to process as soon as possible.
		$action_id = as_enqueue_async_action(
			self::ACTION_INDEX_ITEM,
			$args,
			$group
		);

		// Log for debugging.
		Logger::log( sprintf( 'Queued job %s for %s %d (%s)', $action_id, $content_type, $content_id, $operation ) );

		return $action_id;
	}
}
