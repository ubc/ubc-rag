<?php

namespace UBC\RAG\Extractors;

use UBC\RAG\Interfaces\ExtractorInterface;

/**
 * Extractor for WordPress Comments.
 *
 * Demonstrates how to implement a content extractor for a custom content type.
 * This example can be used as a template for external plugin developers creating
 * their own extractors.
 */
class Comment_Extractor extends Abstract_Extractor {

	/**
	 * Check if this extractor supports the given type.
	 *
	 * @param string $type Content type.
	 * @return bool
	 */
	public function supports( $type ): bool {
		return 'comment' === $type;
	}

	/**
	 * Extract content from a comment.
	 *
	 * @param mixed $source Comment ID.
	 * @return array Extracted chunks.
	 */
	public function extract( $source ): array {
		$comment = get_comment( $source );

		if ( ! $comment ) {
			$this->handle_error( "Comment not found: $source" );
			return [];
		}

		// Get the post that this comment belongs to.
		$post = get_post( $comment->comment_post_ID );
		$post_title = $post ? $post->post_title : 'Unknown Post';

		// Extract comment data.
		$author = ! empty( $comment->comment_author ) ? $comment->comment_author : 'Anonymous';
		$content = ! empty( $comment->comment_content ) ? $comment->comment_content : '';

		// Build the content string combining author, post context, and comment text.
		$full_content = "Comment by $author\n";
		$full_content .= "On: $post_title\n\n";
		$full_content .= $content;

		$this->log( "Extracted content from comment {$comment->comment_ID}" );

		// Return as single chunk.
		return [
			[
				'content'  => $full_content,
				'metadata' => [
					'page'              => 1,
					'comment_id'        => (int) $comment->comment_ID,
					'comment_author'    => $comment->comment_author,
					'comment_author_email' => $comment->comment_author_email,
					'comment_author_url' => $comment->comment_author_url,
					'post_id'           => (int) $comment->comment_post_ID,
					'post_title'        => $post_title,
					'approved'          => (bool) $comment->comment_approved,
				],
			],
		];
	}
}
