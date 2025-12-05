<?php

namespace UBC\RAG\Admin;

/**
 * Admin class.
 */
class Admin {

	/**
	 * Initialize admin functionality.
	 *
	 * @return void
	 */
	public function init() {
		$admin_menu = new Admin_Menu();
		add_action( 'admin_menu', [ $admin_menu, 'add_menu' ] );
		add_action( 'admin_init', [ $admin_menu, 'register_settings' ] );
		add_action( 'wp_ajax_ubc_rag_test_connection', [ $admin_menu, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_rag_retry_item', [ $admin_menu, 'ajax_retry_item' ] );
		add_action( 'wp_ajax_rag_retry_all', [ $admin_menu, 'ajax_retry_all' ] );
		add_action( 'wp_ajax_ubc_rag_search_test', [ $admin_menu, 'ajax_search_test' ] );

		// Initialize bulk actions.
		$bulk_actions = new Bulk_Actions();
		$bulk_actions->init();

		// Initialize status columns.
		$status_columns = new Status_Columns();
		$status_columns->init();

		// Initialize media modal.
		$media_modal = new Media_Modal();
		$media_modal->init();

		// Initialize meta boxes.
		$meta_boxes = new Meta_Boxes();
		$meta_boxes->init();

		// Initialize link table integration (for link managers that provide hooks).
		$link_table_integration = new Link_Table_Integration();
		$link_table_integration->init();

		add_action( 'wp_ajax_rag_quick_action', [ $this, 'ajax_quick_action' ] );
	}

	/**
	 * AJAX handler for quick actions (index/de-index).
	 *
	 * @return void
	 */
	public function ajax_quick_action() {
		check_ajax_referer( 'rag_retry', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}

		$content_id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$content_type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$action       = isset( $_POST['todo'] ) ? sanitize_text_field( $_POST['todo'] ) : '';

		if ( ! $content_id || ! $content_type || ! $action ) {
			wp_send_json_error( 'Missing parameters.' );
			return;
		}

		if ( 'index' === $action ) {
			// Remove skip flag.
			// For links, we use delete_metadata; for posts, delete_post_meta.
			if ( 'link' === $content_type ) {
				delete_metadata( 'link', $content_id, '_ubc_rag_skip_indexing' );
			} else {
				delete_post_meta( $content_id, '_ubc_rag_skip_indexing' );
			}

			// Queue job using Queue::push.
			if ( \UBC\RAG\Queue::push( $content_id, $content_type, 'update' ) ) {
				wp_send_json_success( 'Queued for indexing' );
			} else {
				wp_send_json_error( 'Failed to queue indexing job' );
			}
		} elseif ( 'deindex' === $action ) {
			// Queue deletion using Queue::push.
			if ( \UBC\RAG\Queue::push( $content_id, $content_type, 'delete' ) ) {
				wp_send_json_success( 'Queued for removal' );
			} else {
				wp_send_json_error( 'Failed to queue deletion job' );
			}
		} else {
			wp_send_json_error( 'Invalid action' );
		}
	}
}
