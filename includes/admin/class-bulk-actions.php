<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Status;

/**
 * Bulk Actions class.
 */
class Bulk_Actions {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Add bulk actions to posts and pages.
		add_filter( 'bulk_actions-edit-post', [ $this, 'register_bulk_actions' ] );
		add_filter( 'bulk_actions-edit-page', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-edit-post', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_filter( 'handle_bulk_actions-edit-page', [ $this, 'handle_bulk_actions' ], 10, 3 );

		// Add bulk actions to media library.
		add_filter( 'bulk_actions-upload', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handle_bulk_actions' ], 10, 3 );
		
		// Add admin notice for bulk actions.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Register bulk actions.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['ubc_rag_index']   = __( 'Index with RAG', 'ubc-rag' );
		$bulk_actions['ubc_rag_deindex'] = __( 'Remove from RAG Index', 'ubc-rag' );
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to URL to redirect to.
	 * @param string $doaction    Action being performed.
	 * @param array  $post_ids    Array of post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'ubc_rag_index' !== $doaction && 'ubc_rag_deindex' !== $doaction ) {
			return $redirect_to;
		}

		$processed = 0;
		$action    = ( 'ubc_rag_index' === $doaction ) ? 'index' : 'delete';

		foreach ( $post_ids as $post_id ) {
			$post_type = get_post_type( $post_id );
			
			// For indexing, we check if it's skipped.
			if ( 'index' === $action ) {
				// Remove skip flag if present.
				delete_post_meta( $post_id, '_ubc_rag_skip_indexing' );
				
				// Queue job.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time(), 'rag_plugin_index_item', [ get_current_blog_id(), $post_id, $post_type, 'update' ] );
					
					// Update status to queued.
					Status::set_status( $post_id, $post_type, 'queued' );
					$processed++;
				}
			} elseif ( 'delete' === $action ) {
				// Queue deletion job.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time(), 'rag_plugin_index_item', [ get_current_blog_id(), $post_id, $post_type, 'delete' ] );
					
					// Update status to queued (or we could just delete it, but async is safer).
					// Actually, for deletion, we might want to mark it as 'queued' for deletion?
					// Or just let the job handle it.
					// Let's set status to 'queued' so user sees something happening.
					Status::set_status( $post_id, $post_type, 'queued' );
					$processed++;
				}
			}
		}

		$redirect_to = add_query_arg(
			[
				'ubc_rag_bulk_action' => $action,
				'ubc_rag_processed'   => $processed,
			],
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Show admin notices.
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['ubc_rag_bulk_action'] ) || ! isset( $_GET['ubc_rag_processed'] ) ) {
			return;
		}

		$action    = sanitize_text_field( $_GET['ubc_rag_bulk_action'] );
		$processed = (int) $_GET['ubc_rag_processed'];

		if ( 'index' === $action ) {
			$message = sprintf(
				_n(
					'%d item queued for indexing.',
					'%d items queued for indexing.',
					$processed,
					'ubc-rag'
				),
				$processed
			);
		} else {
			$message = sprintf(
				_n(
					'%d item queued for removal from index.',
					'%d items queued for removal from index.',
					$processed,
					'ubc-rag'
				),
				$processed
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
