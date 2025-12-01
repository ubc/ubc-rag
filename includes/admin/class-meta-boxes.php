<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Status;

/**
 * Meta Boxes class.
 */
class Meta_Boxes {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Add meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		$post_types = get_post_types( [ 'public' => true ] );
		foreach ( $post_types as $post_type ) {
			// Check if this post type is enabled in settings?
			// For now, add to all public post types, or maybe check settings.
			// Let's assume we want it on all public types for now.
			add_meta_box(
				'ubc_rag_meta_box',
				__( 'RAG Indexing', 'ubc-rag' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'ubc_rag_save_meta_box', 'ubc_rag_meta_box_nonce' );

		$status = Status::get_status( $post->ID, $post->post_type );
		$skipped = get_post_meta( $post->ID, '_ubc_rag_skip_indexing', true );
		$is_checked = ! $skipped;

		// Status Display.
		echo '<div class="ubc-rag-meta-box-status">';
		echo '<p><strong>' . esc_html__( 'Status:', 'ubc-rag' ) . '</strong> ';
		
		if ( $status ) {
			$icon  = Status::get_status_icon( $status->status );
			$label = Status::get_status_label( $status->status );
			echo $icon . ' ' . esc_html( $label );
			
			if ( 'failed' === $status->status ) {
				echo ' <a href="#" class="ubc-rag-retry-item" data-id="' . esc_attr( $post->ID ) . '" data-type="' . esc_attr( $post->post_type ) . '">' . esc_html__( 'Retry', 'ubc-rag' ) . '</a>';
			}
		} else {
			echo '<span class="dashicons dashicons-minus"></span> ' . esc_html__( 'Not Indexed', 'ubc-rag' );
		}
		echo '</p>';
		echo '</div>';

		// Index Control.
		echo '<div class="ubc-rag-meta-box-control">';
		echo '<label for="ubc_rag_index_post">';
		echo '<input type="checkbox" name="ubc_rag_index_post" id="ubc_rag_index_post" value="1" ' . checked( $is_checked, true, false ) . ' />';
		echo ' ' . esc_html__( 'Index this content', 'ubc-rag' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Uncheck to prevent this content from being indexed.', 'ubc-rag' ) . '</p>';
		echo '</div>';
		
		// Re-index Button (if already indexed or failed).
		if ( $status ) {
			echo '<div class="ubc-rag-meta-box-actions" style="margin-top: 10px;">';
			echo '<button type="button" class="button ubc-rag-reindex-btn" data-id="' . esc_attr( $post->ID ) . '" data-type="' . esc_attr( $post->post_type ) . '">' . esc_html__( 'Re-index Now', 'ubc-rag' ) . '</button>';
			echo '</div>';
		}
	}

	/**
	 * Save meta box.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['ubc_rag_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['ubc_rag_meta_box_nonce'], 'ubc_rag_save_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ubc_rag_index_post'] ) ) {
			delete_post_meta( $post_id, '_ubc_rag_skip_indexing' );
		} else {
			update_post_meta( $post_id, '_ubc_rag_skip_indexing', '1' );
		}
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'ubc-rag-sidebar',
			UBC_RAG_URL . 'assets/js/rag-sidebar.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose' ],
			UBC_RAG_VERSION,
			true
		);
		
		// Localize script with status data?
		// Gutenberg handles meta via REST, but for status (custom table), we might need to fetch it or pass it.
		// We can pass the current status via wp_localize_script.
		// But status depends on the current post ID, which we might not know if it's new.
		// If it's an existing post, we can get it.
		
		$post_id = get_the_ID();
		$status_data = null;
		if ( $post_id ) {
			$status = Status::get_status( $post_id, get_post_type( $post_id ) );
			if ( $status ) {
				$status_data = [
					'status' => $status->status,
					'label'  => Status::get_status_label( $status->status ),
					'icon'   => Status::get_status_icon( $status->status ), // This returns HTML, might be tricky in React.
				];
			}
		}

		wp_localize_script( 'ubc-rag-sidebar', 'ubcRagData', [
			'status' => $status_data,
			'labels' => [
				'title' => __( 'RAG Indexing', 'ubc-rag' ),
				'indexThis' => __( 'Index this content', 'ubc-rag' ),
				'notIndexed' => __( 'Not Indexed', 'ubc-rag' ),
				'status' => __( 'Status:', 'ubc-rag' ),
			]
		] );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style(
			'ubc-rag-admin',
			UBC_RAG_URL . 'assets/css/rag-admin.css',
			[],
			UBC_RAG_VERSION
		);
		
		// Enqueue JS for retry/reindex buttons in list tables and meta boxes.
		wp_enqueue_script(
			'ubc-rag-admin-js',
			UBC_RAG_URL . 'assets/js/rag-admin.js',
			[ 'jquery' ],
			UBC_RAG_VERSION,
			true
		);

		wp_localize_script( 'ubc-rag-admin-js', 'ubcRagAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rag_retry' ),
		] );
	}
}
