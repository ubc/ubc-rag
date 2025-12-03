<?php

namespace UBC\RAG;

/**
 * Content Monitor class.
 */
class Content_Monitor {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		// Posts and Pages.
		add_action( 'save_post', [ $this, 'handle_save_post' ], 10, 3 );
		add_action( 'wp_trash_post', [ $this, 'handle_delete_post' ] );
		add_action( 'before_delete_post', [ $this, 'handle_delete_post' ] );

		// Attachments.
		add_action( 'add_attachment', [ $this, 'handle_save_attachment' ] );
		add_action( 'edit_attachment', [ $this, 'handle_save_attachment' ] );
		add_action( 'delete_attachment', [ $this, 'handle_delete_attachment' ] );

		// Links/Bookmarks.
		add_action( 'add_link', [ $this, 'handle_save_link' ] );
		add_action( 'edit_link', [ $this, 'handle_save_link' ] );
		add_action( 'delete_link', [ $this, 'handle_delete_link' ] );
	}

	/**
	 * Handle save post.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_save_post( $post_id, $post, $update ) {
		// Ignore auto-saves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Debounce: Check if we've already processed this post in this request.
		static $processed_posts = [];
		if ( isset( $processed_posts[ $post_id ] ) ) {
			return;
		}
		$processed_posts[ $post_id ] = true;

		// Check if content type is enabled.
		$post_type = $post->post_type;

		if ( ! Content_Type_Helper::is_content_type_enabled( $post_type ) ) {
			return;
		}

		// Only index published posts.
		if ( 'publish' !== $post->post_status ) {
			// If it was indexed and now it's not published, we should probably remove it.
			// For now, let's just ignore non-published.
			// TODO: Handle un-publishing (transition_post_status hook might be better for that).
			return;
		}

		Logger::log( sprintf( 'Post saved: %d (%s)', $post_id, $post_type ) );

		// Update status to queued.
		Status::set_status( $post_id, $post_type, 'queued' );

		// Push to queue.
		Queue::push( $post_id, $post_type, 'update' );
	}

	/**
	 * Handle delete post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function handle_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$post_type = $post->post_type;

		// Check settings (though for delete we might want to clean up regardless).
		if ( ! Content_Type_Helper::is_content_type_enabled( $post_type ) ) {
			return;
		}

		Logger::log( sprintf( 'Post deleted: %d (%s)', $post_id, $post_type ) );

		// Push delete job.
		Queue::push( $post_id, $post_type, 'delete' );
	}

	/**
	 * Handle save attachment.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function handle_save_attachment( $post_id ) {
		if ( ! Content_Type_Helper::is_content_type_enabled( 'attachment' ) ) {
			return;
		}

		Logger::log( sprintf( 'Attachment saved: %d', $post_id ) );

		Status::set_status( $post_id, 'attachment', 'queued' );
		Queue::push( $post_id, 'attachment', 'update' );
	}

	/**
	 * Handle delete attachment.
	 *
	 * @param int $post_id Attachment ID.
	 * @return void
	 */
	public function handle_delete_attachment( $post_id ) {
		if ( ! Content_Type_Helper::is_content_type_enabled( 'attachment' ) ) {
			return;
		}

		Logger::log( sprintf( 'Attachment deleted: %d', $post_id ) );

		Queue::push( $post_id, 'attachment', 'delete' );
	}

	/**
	 * Handle link creation or update.
	 *
	 * @param int $link_id Link ID.
	 * @return void
	 */
	public function handle_save_link( $link_id ) {
		// Check if link manager is enabled.
		if ( ! Content_Type_Helper::is_link_manager_enabled() ) {
			return;
		}

		if ( ! Content_Type_Helper::is_content_type_enabled( 'link' ) ) {
			return;
		}

		Logger::log( sprintf( 'Link saved: %d', $link_id ) );

		Status::set_status( $link_id, 'link', 'queued' );
		Queue::push( $link_id, 'link', 'update' );
	}

	/**
	 * Handle link deletion.
	 *
	 * @param int $link_id Link ID.
	 * @return void
	 */
	public function handle_delete_link( $link_id ) {
		// Check if link manager is enabled (for consistency, though link may already be deleted).
		if ( ! Content_Type_Helper::is_content_type_enabled( 'link' ) ) {
			return;
		}

		Logger::log( sprintf( 'Link deleted: %d', $link_id ) );

		Queue::push( $link_id, 'link', 'delete' );
	}
}
