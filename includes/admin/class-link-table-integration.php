<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Status;
use UBC\RAG\Queue;

/**
 * Link Table Integration class.
 *
 * Integrates RAG indexing status and actions into link list tables that
 * provide the appropriate hooks (e.g., Socrates link manager).
 *
 * This class uses a hook-based approach for loose coupling - it doesn't
 * require any specific link manager plugin, but will enhance any table
 * that provides the expected filter/action hooks.
 */
class Link_Table_Integration {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Hook into link table columns (if hooks exist).
		add_filter( 'socrates_links_table_columns', [ $this, 'add_status_column' ] );
		add_filter( 'socrates_links_table_column_ubc_rag_status', [ $this, 'render_status_column' ], 10, 3 );

		// Hook into row actions.
		add_filter( 'socrates_links_table_row_actions', [ $this, 'add_row_actions' ], 10, 2 );

		// Hook into bulk actions.
		add_filter( 'socrates_links_table_bulk_actions', [ $this, 'add_bulk_actions' ] );
		add_action( 'socrates_links_table_handle_bulk_action', [ $this, 'handle_bulk_action' ], 10, 2 );

		// Add admin notices for bulk actions.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Add RAG Status column to link tables.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_status_column( $columns ) {
		$columns['ubc_rag_status'] = __( 'RAG Status', 'ubc-rag' );
		return $columns;
	}

	/**
	 * Render RAG Status column for links.
	 *
	 * @param mixed  $output      The column output (null by default).
	 * @param object $item        The link item (bookmark object).
	 * @param string $column_name The column name.
	 * @return string Column HTML.
	 */
	public function render_status_column( $output, $item, $column_name ) {
		$link_id = $item->link_id;
		$status  = Status::get_status( $link_id, 'link' );

		if ( ! $status ) {
			return '<span class="dashicons dashicons-minus" title="' . esc_attr__( 'Not Indexed', 'ubc-rag' ) . '"></span>';
		}

		$icon  = Status::get_status_icon( $status->status );
		$label = Status::get_status_label( $status->status );

		$html = '<span class="ubc-rag-status-icon" title="' . esc_attr( $label ) . '">' . $icon . '</span>';

		if ( 'failed' === $status->status ) {
			$html .= '<br><small><a href="#" class="ubc-rag-retry-item" data-id="' . esc_attr( $link_id ) . '" data-type="link">' . esc_html__( 'Retry', 'ubc-rag' ) . '</a></small>';
		}

		return $html;
	}

	/**
	 * Add row actions for links.
	 *
	 * @param array  $actions Existing actions.
	 * @param object $item    Link item (bookmark object).
	 * @return array Modified actions.
	 */
	public function add_row_actions( $actions, $item ) {
		$link_id = $item->link_id;
		$status  = Status::get_status( $link_id, 'link' );

		// If indexed, show "De-index".
		// If not indexed (or failed/queued), show "Index".
		$is_indexed = ( $status && 'indexed' === $status->status );

		if ( $is_indexed ) {
			$actions['ubc_rag_deindex'] = sprintf(
				'<a href="#" class="ubc-rag-quick-action" data-action="deindex" data-id="%d" data-type="link">%s</a>',
				$link_id,
				__( 'De-index', 'ubc-rag' )
			);
		} else {
			$actions['ubc_rag_index'] = sprintf(
				'<a href="#" class="ubc-rag-quick-action" data-action="index" data-id="%d" data-type="link">%s</a>',
				$link_id,
				__( 'Index with RAG', 'ubc-rag' )
			);
		}

		return $actions;
	}

	/**
	 * Add bulk actions for links.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['ubc_rag_index']   = __( 'Index with RAG', 'ubc-rag' );
		$bulk_actions['ubc_rag_deindex'] = __( 'Remove from RAG Index', 'ubc-rag' );
		return $bulk_actions;
	}

	/**
	 * Handle bulk actions for links.
	 *
	 * @param string $action   The action being performed.
	 * @param array  $link_ids Array of link IDs.
	 * @return void
	 */
	public function handle_bulk_action( $action, $link_ids ) {
		if ( 'ubc_rag_index' !== $action && 'ubc_rag_deindex' !== $action ) {
			return;
		}

		$processed      = 0;
		$operation      = ( 'ubc_rag_index' === $action ) ? 'update' : 'delete';
		$content_type   = 'link';
		$site_id        = get_current_blog_id();

		foreach ( $link_ids as $link_id ) {
			// For indexing, we check if it's skipped.
			if ( 'update' === $operation ) {
				// Remove skip flag if present (links use post meta table via link_id).
				delete_metadata( 'link', $link_id, '_ubc_rag_skip_indexing' );

				// Queue job.
				if ( Queue::push( $link_id, $content_type, $operation ) ) {
					$processed++;
				}
			} elseif ( 'delete' === $operation ) {
				// Queue deletion job.
				if ( Queue::push( $link_id, $content_type, $operation ) ) {
					$processed++;
				}
			}
		}

		// Store count in transient for admin notice.
		set_transient(
			'ubc_rag_link_bulk_action',
			[
				'action'    => $operation,
				'processed' => $processed,
			],
			30
		);
	}

	/**
	 * Show admin notices for bulk actions.
	 *
	 * @return void
	 */
	public function admin_notices() {
		$data = get_transient( 'ubc_rag_link_bulk_action' );

		if ( ! $data ) {
			return;
		}

		delete_transient( 'ubc_rag_link_bulk_action' );

		$action    = $data['action'];
		$processed = $data['processed'];

		if ( 'update' === $action ) {
			$message = sprintf(
				/* translators: %d: number of links */
				_n(
					'%d link queued for indexing.',
					'%d links queued for indexing.',
					$processed,
					'ubc-rag'
				),
				$processed
			);
		} else {
			$message = sprintf(
				/* translators: %d: number of links */
				_n(
					'%d link queued for removal from index.',
					'%d links queued for removal from index.',
					$processed,
					'ubc-rag'
				),
				$processed
			);
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
