<?php

namespace UBC\RAG;

/**
 * Worker class.
 * Handles processing of queued jobs.
 */
class Worker {

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'rag_plugin_index_item', [ $this, 'process_item' ], 10, 4 );
	}

	/**
	 * Process a single item from the queue.
	 *
	 * @param int    $site_id      Site ID.
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @param string $operation    Operation (update/delete).
	 * @return void
	 */
	public function process_item( $site_id, $content_id, $content_type, $operation ) {

		if ( ! $content_id || ! $content_type ) {
			return;
		}

		Logger::log( sprintf( 'Processing job for %s %d (%s)', $content_type, $content_id, $operation ) );

		// Handle delete operations separately.
		if ( 'delete' === $operation ) {
			$this->handle_deletion( $content_id, $content_type );
			return;
		}

		// 1. Get Extractor.
		$factory = \UBC\RAG\Extractors\Extractor_Factory::get_instance();
		
		// For attachments, we need to look up the extractor by MIME type.
		if ( 'attachment' === $content_type ) {
			$mime_type = get_post_mime_type( $content_id );
			$extractor = $factory->get_extractor( $mime_type );
		} else {
			$extractor = $factory->get_extractor( $content_type );
		}

		if ( ! $extractor ) {
			Logger::log( "No extractor found for type: $content_type" );
			return;
		}

		// 2. Extract Content.
		$chunks = $extractor->extract( $content_id );

		if ( empty( $chunks ) ) {
			Logger::log( "No content extracted for $content_type $content_id" );
			return;
		}

		// 3. Calculate Hash & Check for Changes.
		$new_hash = Hasher::calculate_hash( $chunks );
		$status_record = Status::get_status( $content_id, $content_type );

		// If fully indexed and unchanged, skip.
		if ( $status_record && $status_record->content_hash === $new_hash && 'indexed' === $status_record->status ) {
			Logger::log( "Content unchanged and already indexed for $content_type $content_id. Skipping processing." );
			Status::set_status( $content_id, $content_type, 'indexed' ); 
			return;
		}

		// Check if we are resuming an incomplete job.
		$is_resuming = ( $status_record && $status_record->content_hash === $new_hash && 'processing' === $status_record->status );

		if ( ! $is_resuming ) {
			// New content or hash changed.
			// Update hash and set status to processing.
			Status::set_status( $content_id, $content_type, 'processing', [ 'content_hash' => $new_hash ] );
			
			// Clear old vectors since content changed.
			$store_factory = Vector_Store_Factory::get_instance();
			$vector_store = $store_factory->get_active_store();
			if ( $vector_store ) {
				$settings = Settings::get_settings();
				$store_settings = isset( $settings['vector_store'] ) ? $settings['vector_store'] : [];
				$collection_name = isset( $store_settings['collection_name'] ) ? $store_settings['collection_name'] : 'rag_collection';
				
				$vector_store->delete_by_filter( $collection_name, [
					'content_id'   => $content_id,
					'content_type' => $content_type,
				] );
			}
		} else {
			Logger::log( "Resuming processing for $content_type $content_id." );
		}

		// 4. Chunk Content.
		$settings = Settings::get_settings();
		$content_type_config = isset( $settings['content_types'][ $content_type ] )
			? $settings['content_types'][ $content_type ]
			: [];
		$strategy = isset( $content_type_config['chunking_strategy'] )
			? $content_type_config['chunking_strategy']
			: 'paragraph'; // Fallback if not configured
		$chunk_settings = isset( $content_type_config['chunking_settings'] )
			? $content_type_config['chunking_settings']
			: [ 'chunk_size' => 3 ]; // Default

		$chunker_factory = Chunker_Factory::get_instance();
		$chunker = $chunker_factory->get_chunker( $strategy );

		$global_metadata = [
			'post_id'   => $content_id,
			'post_type' => $content_type,
			'source_url' => 'attachment' === $content_type ? wp_get_attachment_url( $content_id ) : get_permalink( $content_id ),
		];

		$final_chunks = $chunker->chunk( $chunks, $chunk_settings, $global_metadata );

		// 5. Log extracted chunks (Preview).
		Logger::log( "Generated " . count( $final_chunks ) . " final chunks using '$strategy' strategy." );


		// 6. Generate Embeddings & Store Vectors.
		$provider_slug = isset( $settings['embedding']['provider'] ) ? $settings['embedding']['provider'] : '';
		
		if ( empty( $provider_slug ) ) {
			Logger::log( "No embedding provider configured. Skipping embedding." );
			Status::set_status( $content_id, $content_type, 'indexed', [ 'error_message' => 'No embedding provider configured' ] );
			return;
		}

		$embedding_factory = Embedding_Factory::get_instance();
		$provider = $embedding_factory->get_provider( $provider_slug );

		if ( ! $provider ) {
			Logger::log( "Embedding provider '$provider_slug' not found." );
			Status::set_status( $content_id, $content_type, 'failed', [ 'error_message' => "Provider '$provider_slug' not found" ] );
			return;
		}

		// Get Vector Store.
		$store_factory = Vector_Store_Factory::get_instance();
		$vector_store = $store_factory->get_active_store();
		
		if ( ! $vector_store ) {
			Logger::log( "No active vector store found." );
			Status::set_status( $content_id, $content_type, 'failed', [ 'error_message' => "No active vector store found" ] );
			return;
		}

		// Get provider-specific settings.
		$provider_settings = isset( $settings['embedding'][ $provider_slug ] ) ? $settings['embedding'][ $provider_slug ] : [];
		
		// Get vector store settings.
		$store_settings = isset( $settings['vector_store'] ) ? $settings['vector_store'] : [];
		$collection_name = isset( $store_settings['collection_name'] ) ? $store_settings['collection_name'] : 'rag_collection';

		// Determine where to start.
		$filter = [
			'content_id'   => $content_id,
			'content_type' => $content_type,
		];
		$max_index = $vector_store->get_max_chunk_index( $collection_name, $filter );
		$start_index = ( null === $max_index ) ? 0 : $max_index + 1;
		$total_chunks = count( $final_chunks );

		if ( $start_index >= $total_chunks ) {
			Logger::log( "All chunks already embedded for $content_type $content_id." );
			// Ensure status is indexed.
			$dimensions = $provider->get_dimensions( $provider_settings );
			$model = isset( $provider_settings['model'] ) ? $provider_settings['model'] : 'unknown';
			Status::set_status( $content_id, $content_type, 'indexed', [
				'embedding_model'      => $model,
				'embedding_dimensions' => $dimensions,
				'chunk_count'          => $total_chunks,
			] );
			return;
		}

		$batch_size = 3; // Process 3 chunks at a time (reduced to avoid timeout).
		$start_time = time();
		$time_limit = 15; // 15 seconds safe limit (leaving buffer for PHP's 30s default).

		try {
			Logger::log( "Starting embedding from index $start_index of $total_chunks." );

			for ( $i = $start_index; $i < $total_chunks; $i += $batch_size ) {
				// Check time limit.
				if ( time() - $start_time > $time_limit ) {
					Logger::log( "Time limit reached. Rescheduling job for $content_type $content_id at index $i." );
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( time(), 'rag_plugin_index_item', [ $site_id, $content_id, $content_type, $operation ] );
					}
					return;
				}

				// Prepare batch.
				$current_batch = [];
				for ( $j = 0; $j < $batch_size; $j++ ) {
					$idx = $i + $j;
					if ( $idx >= $total_chunks ) break;
					$chunk = $final_chunks[$idx];
					$chunk['chunk_index'] = $idx;
					$current_batch[] = $chunk;
				}

				// Generate embeddings.
				Logger::log( "Calling embed() with settings: " . json_encode( $provider_settings ) );
				$embeddings = $provider->embed( $current_batch, $provider_settings );

				// Prepare vectors for storage.
				$vectors_to_store = [];
				foreach ( $embeddings as $k => $embedding ) {
					$chunk_data = $current_batch[$k];
					
					// Construct payload/metadata.
					$payload = [
						'content_id'   => $content_id,
						'content_type' => $content_type,
						'chunk_index'  => $chunk_data['chunk_index'],
						'chunk_text'   => $chunk_data['content'],
						'metadata'     => $chunk_data['metadata'],
					];
					
					// ID generation strategy:
					// For Qdrant, we might want UUIDs. For MySQL, auto-increment is fine but we don't pass ID.
					// Let's generate a deterministic UUID based on content_id + chunk_index if needed, 
					// but for now let the store handle it or pass null.
					// Qdrant prefers UUIDs.
					
					$vectors_to_store[] = [
						'id'      => null, // Store can generate or we can generate UUID here.
						'vector'  => $embedding,
						'payload' => $payload,
					];
				}

				// Store vectors.
				$vector_store->insert_vectors( $collection_name, $vectors_to_store );
				
				Logger::log( "Embedded and stored batch ending at index " . ( $i + count( $current_batch ) - 1 ) );
			}

			// All done.
			$dimensions = $provider->get_dimensions( $provider_settings );
			$model = isset( $provider_settings['model'] ) ? $provider_settings['model'] : 'unknown';

			Status::set_status( $content_id, $content_type, 'indexed', [
				'embedding_model'      => $model,
				'embedding_dimensions' => $dimensions,
				'chunk_count'          => $total_chunks,
			] );
			
			Logger::log( "Successfully indexed $content_type $content_id." );

		} catch ( \Exception $e ) {
			$error_message = $e->getMessage();
			Logger::log( "Embedding generation failed: " . $error_message );

			// Get current retry count from status.
			$status = Status::get_status( $content_id, $content_type );
			$attempt_count = ( $status && isset( $status->retry_count ) ) ? (int) $status->retry_count + 1 : 1;

			// Limit retries to 4 attempts max.
			$max_attempts = 4;

			if ( $attempt_count < $max_attempts ) {
				// Queue for retry with exponential backoff.
				Status::set_status(
					$content_id,
					$content_type,
					'failed',
					[
						'error_message' => $error_message,
						'retry_count'   => $attempt_count,
					]
				);

				// Queue a retry job (see Retry_Queue class).
				if ( class_exists( 'UBC\\RAG\\Retry_Queue' ) ) {
					Retry_Queue::queue_retry( $content_id, $content_type, $attempt_count, $error_message );
					Logger::log( "Queued retry (attempt $attempt_count/$max_attempts)" );
				} else {
					Logger::log( "Retry_Queue class not found, manual retry needed" );
				}
			} else {
				// Too many retries - give up.
				Status::set_status(
					$content_id,
					$content_type,
					'failed',
					[
						'error_message' => "Failed after $max_attempts attempts: $error_message",
						'retry_count'   => $max_attempts,
					]
				);
				Logger::log( "Gave up after $max_attempts attempts" );
			}

			// DO NOT RETHROW - Allow next job in queue to process.
			// Job is marked as failed in status table for manual/auto retry later.
			return;
		}
	}

	/**
	 * Handle deletion of a content item.
	 * Removes vectors from storage and cleans up status.
	 *
	 * @param int    $content_id   Content ID.
	 * @param string $content_type Content Type.
	 * @return void
	 */
	private function handle_deletion( $content_id, $content_type ) {
		Logger::log( "Handling deletion for $content_type $content_id" );

		// Get vector store.
		$store_factory = Vector_Store_Factory::get_instance();
		$vector_store = $store_factory->get_active_store();

		if ( ! $vector_store ) {
			Logger::log( "No active vector store found, cannot delete vectors." );
			// Still clean up status.
			Status::delete_status( $content_id, $content_type );
			return;
		}

		// Delete vectors matching this content.
		// Note: collection_name parameter is ignored by most stores which use their own naming scheme.
		// Passing empty string lets each store determine the correct collection.
		$filter = [
			'content_id'   => $content_id,
			'content_type' => $content_type,
		];

		$deleted_count = $vector_store->delete_by_filter( '', $filter );

		if ( $deleted_count > 0 ) {
			Logger::log( "Deleted $deleted_count vectors for $content_type $content_id." );
		} else {
			Logger::log( "No vectors found to delete for $content_type $content_id (already removed or never indexed)." );
		}

		// Clean up status record.
		Status::delete_status( $content_id, $content_type );
		Logger::log( "Cleaned up status record for $content_type $content_id." );
	}
}
