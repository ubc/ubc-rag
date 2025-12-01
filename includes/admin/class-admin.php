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

		$post_id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post_type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$action    = isset( $_POST['todo'] ) ? sanitize_text_field( $_POST['todo'] ) : '';

		if ( ! $post_id || ! $post_type || ! $action ) {
			wp_send_json_error( 'Missing parameters.' );
			return;
		}

		if ( 'index' === $action ) {
			// Remove skip flag.
			delete_post_meta( $post_id, '_ubc_rag_skip_indexing' );
			
			// Queue job.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), 'rag_plugin_index_item', [ get_current_blog_id(), $post_id, $post_type, 'update' ] );
				\UBC\RAG\Status::set_status( $post_id, $post_type, 'queued' );
				wp_send_json_success( 'Queued for indexing' );
			} else {
				wp_send_json_error( 'Action Scheduler not available' );
			}
		} elseif ( 'deindex' === $action ) {
			// Queue deletion.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), 'rag_plugin_index_item', [ get_current_blog_id(), $post_id, $post_type, 'delete' ] );
				\UBC\RAG\Status::set_status( $post_id, $post_type, 'queued' );
				wp_send_json_success( 'Queued for removal' );
			} else {
				wp_send_json_error( 'Action Scheduler not available' );
			}
		} else {
			wp_send_json_error( 'Invalid action' );
		}
	}
}
