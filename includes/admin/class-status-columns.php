<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Status;

/**
 * Status Columns class.
 */
class Status_Columns {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Add columns to posts and pages.
		add_filter( 'manage_post_posts_columns', [ $this, 'add_column' ] );
		add_filter( 'manage_page_posts_columns', [ $this, 'add_column' ] );
		add_action( 'manage_post_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'manage_page_posts_custom_column', [ $this, 'render_column' ], 10, 2 );

		// Add columns to media library.
		add_filter( 'manage_media_columns', [ $this, 'add_column' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_column' ], 10, 2 );

		// Add row actions.
		add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_filter( 'media_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
	}

	/**
	 * Add RAG Status column.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_column( $columns ) {
		$columns['ubc_rag_status'] = __( 'RAG Status', 'ubc-rag' );
		return $columns;
	}

	/**
	 * Render RAG Status column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'ubc_rag_status' !== $column ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		$status    = Status::get_status( $post_id, $post_type );

		if ( ! $status ) {
			echo '<span class="dashicons dashicons-minus" title="' . esc_attr__( 'Not Indexed', 'ubc-rag' ) . '"></span>';
			return;
		}

		$icon  = Status::get_status_icon( $status->status );
		$label = Status::get_status_label( $status->status );

		echo '<span class="ubc-rag-status-icon" title="' . esc_attr( $label ) . '">' . $icon . '</span>';
		
		if ( 'failed' === $status->status ) {
			echo '<br><small><a href="#" class="ubc-rag-retry-item" data-id="' . esc_attr( $post_id ) . '" data-type="' . esc_attr( $post_type ) . '">' . esc_html__( 'Retry', 'ubc-rag' ) . '</a></small>';
		}
	}

	/**
	 * Add row actions.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array Modified actions.
	 */
	public function add_row_actions( $actions, $post ) {
		$status = Status::get_status( $post->ID, $post->post_type );
		
		// If indexed, show "De-index".
		// If not indexed (or failed/queued), show "Index".
		
		$is_indexed = ( $status && 'indexed' === $status->status );
		
		if ( $is_indexed ) {
			$actions['ubc_rag_deindex'] = sprintf(
				'<a href="#" class="ubc-rag-quick-action" data-action="deindex" data-id="%d" data-type="%s">%s</a>',
				$post->ID,
				$post->post_type,
				__( 'De-index', 'ubc-rag' )
			);
		} else {
			$actions['ubc_rag_index'] = sprintf(
				'<a href="#" class="ubc-rag-quick-action" data-action="index" data-id="%d" data-type="%s">%s</a>',
				$post->ID,
				$post->post_type,
				__( 'Index with RAG', 'ubc-rag' )
			);
		}

		return $actions;
	}
}
