<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Settings;

/**
 * Admin Menu class.
 */
class Admin_Menu {

	/**
	 * Add menu item.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'RAG Indexing', 'ubc-rag' ),
			__( 'RAG Indexing', 'ubc-rag' ),
			'manage_options',
			'ubc-rag-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ubc_rag_settings_group',
			Settings::OPTION_KEY,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);
	}

	/**
	 * Sanitize and validate settings before saving.
	 *
	 * @param array $input Raw settings input from form.
	 * @return array Sanitized and validated settings.
	 */
	public function sanitize_settings( $input ) {
		$settings = Settings::get_settings();

		// Sanitize vector store settings.
		if ( isset( $input['vector_store'] ) ) {
			if ( isset( $input['vector_store']['provider'] ) ) {
				$settings['vector_store']['provider'] = sanitize_text_field( $input['vector_store']['provider'] );
			}

			if ( isset( $input['vector_store']['qdrant'] ) ) {
				if ( isset( $input['vector_store']['qdrant']['url'] ) ) {
					$url = esc_url_raw( $input['vector_store']['qdrant']['url'] );
					if ( $url ) {
						$settings['vector_store']['qdrant']['url'] = $url;
					} else {
						add_settings_error(
							Settings::OPTION_KEY,
							'invalid_qdrant_url',
							__( 'Invalid Qdrant URL provided.', 'ubc-rag' )
						);
					}
				}
				if ( isset( $input['vector_store']['qdrant']['api_key'] ) ) {
					$settings['vector_store']['qdrant']['api_key'] = sanitize_text_field( $input['vector_store']['qdrant']['api_key'] );
				}
				if ( isset( $input['vector_store']['qdrant']['collection_name'] ) ) {
					$settings['vector_store']['qdrant']['collection_name'] = sanitize_text_field( $input['vector_store']['qdrant']['collection_name'] );
				}
				if ( isset( $input['vector_store']['qdrant']['distance_metric'] ) ) {
					$settings['vector_store']['qdrant']['distance_metric'] = sanitize_text_field( $input['vector_store']['qdrant']['distance_metric'] );
				}
			}
		}

		// Sanitize embedding settings.
		if ( isset( $input['embedding'] ) ) {
			if ( isset( $input['embedding']['provider'] ) ) {
				$settings['embedding']['provider'] = sanitize_text_field( $input['embedding']['provider'] );
			}

			// OpenAI settings.
			if ( isset( $input['embedding']['openai'] ) ) {
				if ( isset( $input['embedding']['openai']['api_key'] ) ) {
					$settings['embedding']['openai']['api_key'] = sanitize_text_field( $input['embedding']['openai']['api_key'] );
				}
				if ( isset( $input['embedding']['openai']['model'] ) ) {
					$settings['embedding']['openai']['model'] = sanitize_text_field( $input['embedding']['openai']['model'] );
				}
				if ( isset( $input['embedding']['openai']['dimensions'] ) ) {
					$dims = (int) $input['embedding']['openai']['dimensions'];
					// Only validate if positive, otherwise ignore (keep existing) or allow if not active provider.
					// Better: Only error if the user is trying to set it to an invalid value AND it's the active provider.
					// But simpler: If it's 0 or empty, just don't update it (keep default/previous), unless it's required.
					if ( $dims > 0 ) {
						$settings['embedding']['openai']['dimensions'] = $dims;
					} elseif ( 'openai' === $settings['embedding']['provider'] ) {
						// Only error if OpenAI is active and value is invalid.
						add_settings_error(
							Settings::OPTION_KEY,
							'invalid_openai_dimensions',
							__( 'OpenAI dimensions must be a positive number.', 'ubc-rag' )
						);
					}
				}
				if ( isset( $input['embedding']['openai']['use_batch_api'] ) ) {
					$settings['embedding']['openai']['use_batch_api'] = (bool) $input['embedding']['openai']['use_batch_api'];
				}
			}

			// Ollama settings.
			if ( isset( $input['embedding']['ollama'] ) ) {
				if ( isset( $input['embedding']['ollama']['endpoint'] ) ) {
					$url = esc_url_raw( $input['embedding']['ollama']['endpoint'] );
					if ( $url ) {
						$settings['embedding']['ollama']['endpoint'] = $url;
					} else {
						add_settings_error(
							Settings::OPTION_KEY,
							'invalid_ollama_endpoint',
							__( 'Invalid Ollama endpoint URL provided.', 'ubc-rag' )
						);
					}
				}
				if ( isset( $input['embedding']['ollama']['api_key'] ) ) {
					$settings['embedding']['ollama']['api_key'] = sanitize_text_field( $input['embedding']['ollama']['api_key'] );
				}
				if ( isset( $input['embedding']['ollama']['model'] ) ) {
					$settings['embedding']['ollama']['model'] = sanitize_text_field( $input['embedding']['ollama']['model'] );
				}
				if ( isset( $input['embedding']['ollama']['dimensions'] ) ) {
					$dims = (int) $input['embedding']['ollama']['dimensions'];
					if ( $dims > 0 ) {
						$settings['embedding']['ollama']['dimensions'] = $dims;
					} elseif ( 'ollama' === $settings['embedding']['provider'] ) {
						add_settings_error(
							Settings::OPTION_KEY,
							'invalid_ollama_dimensions',
							__( 'Ollama dimensions must be a positive number.', 'ubc-rag' )
						);
					}
				}
				if ( isset( $input['embedding']['ollama']['context_window'] ) ) {
					$ctx = (int) $input['embedding']['ollama']['context_window'];
					if ( $ctx > 0 ) {
						$settings['embedding']['ollama']['context_window'] = $ctx;
					}
				}
			}
		}

		// Sanitize content types settings.
		if ( isset( $input['content_types'] ) ) {
			foreach ( $input['content_types'] as $type => $config ) {
				if ( isset( $config['enabled'] ) ) {
					$settings['content_types'][ $type ]['enabled'] = (bool) $config['enabled'];
				}
				if ( isset( $config['auto_index'] ) ) {
					$settings['content_types'][ $type ]['auto_index'] = (bool) $config['auto_index'];
				}
				if ( isset( $config['chunking_strategy'] ) ) {
					$settings['content_types'][ $type ]['chunking_strategy'] = sanitize_text_field( $config['chunking_strategy'] );
				}
				if ( isset( $config['chunking_settings'] ) ) {
					foreach ( $config['chunking_settings'] as $setting_key => $setting_value ) {
						if ( 'chunk_size' === $setting_key || 'overlap' === $setting_key ) {
							$value = (int) $setting_value;
							if ( $value > 0 ) {
								$settings['content_types'][ $type ]['chunking_settings'][ $setting_key ] = $value;
							}
						}
					}
				}
			}
		}

		// Sanitize content options.
		if ( isset( $input['content_options'] ) ) {
			if ( isset( $input['content_options']['include_excerpts'] ) ) {
				$settings['content_options']['include_excerpts'] = (bool) $input['content_options']['include_excerpts'];
			}
			if ( isset( $input['content_options']['include_comments'] ) ) {
				$settings['content_options']['include_comments'] = (bool) $input['content_options']['include_comments'];
			}
			if ( isset( $input['content_options']['include_media_metadata'] ) ) {
				$settings['content_options']['include_media_metadata'] = (bool) $input['content_options']['include_media_metadata'];
			}
			if ( isset( $input['content_options']['minimum_user_role_for_index_control'] ) ) {
				$settings['content_options']['minimum_user_role_for_index_control'] = sanitize_text_field( $input['content_options']['minimum_user_role_for_index_control'] );
			}
		}

		// Sanitize processing settings.
		if ( isset( $input['processing'] ) ) {
			if ( isset( $input['processing']['max_file_size_mb'] ) ) {
				$size = (int) $input['processing']['max_file_size_mb'];
				if ( $size > 0 ) {
					$settings['processing']['max_file_size_mb'] = $size;
				} else {
					add_settings_error(
						Settings::OPTION_KEY,
						'invalid_file_size',
						__( 'Max file size must be a positive number.', 'ubc-rag' )
					);
				}
			}
			if ( isset( $input['processing']['retry_attempts'] ) ) {
				$retries = (int) $input['processing']['retry_attempts'];
				if ( $retries >= 0 && $retries <= 5 ) {
					$settings['processing']['retry_attempts'] = $retries;
				} else {
					add_settings_error(
						Settings::OPTION_KEY,
						'invalid_retry_attempts',
						__( 'Retry attempts must be between 0 and 5.', 'ubc-rag' )
					);
				}
			}
		}

		return $settings;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Get current tab.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';

		// Load view.
		require_once UBC_RAG_PATH . 'includes/admin/views/settings-page.php';
	}

	/**
	 * AJAX handler for testing connection.
	 *
	 * @return void
	 */
	/**
	 * AJAX handler for testing connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		// Log start of request
		\UBC\RAG\Logger::log( 'AJAX Test Connection: Request received.' );

		check_ajax_referer( 'ubc_rag_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}

		$type          = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'embedding'; // embedding or vector_store
		$provider_slug = isset( $_POST['provider'] ) ? sanitize_text_field( $_POST['provider'] ) : '';
		$settings      = isset( $_POST['settings'] ) ? (array) $_POST['settings'] : [];

		if ( empty( $provider_slug ) ) {
			wp_send_json_error( 'No provider specified.' );
			return;
		}

		try {
			// Sanitize settings.
			$clean_settings = array_map( 'sanitize_text_field', $settings );
			$success = false;

			if ( 'vector_store' === $type ) {
				// We need to temporarily mock the settings or pass them to the provider.
				// The Vector Store classes currently read from Settings::get_settings() in constructor.
				// This is a limitation. We should probably allow injecting settings or passing them to test_connection.
				// However, the interface test_connection() takes no arguments.
				// We need to refactor VectorStorageInterface::test_connection to accept config, OR
				// we instantiate the class and somehow inject config.
				// 
				// For now, let's instantiate the class. But Qdrant_Vector_Store reads from Settings in constructor.
				// We should modify Qdrant_Vector_Store to accept config in constructor or have a set_config method.
				// 
				// Let's assume we modify Qdrant_Vector_Store to accept config in constructor if provided.
				
				$factory = \UBC\RAG\Vector_Store_Factory::get_instance();
				// We can't easily inject settings into the factory-created instance if it uses `new $class()`.
				// 
				// Workaround: We can manually instantiate the class if we know it.
				if ( 'qdrant' === $provider_slug ) {
					// We need to pass the settings to Qdrant store.
					// Let's modify Qdrant_Vector_Store constructor to accept optional settings.
					$store = new \UBC\RAG\Vector_Stores\Qdrant_Vector_Store( $clean_settings );
					$success = $store->test_connection();
				} elseif ( 'mysql' === $provider_slug ) {
					$store = new \UBC\RAG\Vector_Stores\MySQL_Vector_Lib_Store();
					$success = $store->test_connection();
				} else {
					// Generic fallback if factory supports it?
					$store = $factory->get_store( $provider_slug );
					if ( $store ) {
						$success = $store->test_connection();
					}
				}

			} else {
				// Embedding provider
				$factory  = \UBC\RAG\Embedding_Factory::get_instance();
				$provider = $factory->get_provider( $provider_slug );

				if ( ! $provider ) {
					wp_send_json_error( 'Provider not found.' );
					return;
				}
				
				$success = $provider->test_connection( $clean_settings );
			}

			if ( $success ) {
				wp_send_json_success( array( 'message' => 'Connection successful' ) );
			} else {
				wp_send_json_error( array( 'message' => 'Connection failed' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler to retry a specific failed item.
	 */
	public function ajax_retry_item() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rag_retry' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		// Get parameters
		$content_id = isset( $_POST['content_id'] ) ? (int) $_POST['content_id'] : 0;
		$content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( $_POST['content_type'] ) : '';

		if ( ! $content_id || ! $content_type ) {
			wp_send_json_error( 'Missing content_id or content_type' );
			return;
		}

		// Retry the item
		$action_id = \UBC\RAG\Retry_Queue::retry_now( $content_id, $content_type );

		if ( $action_id ) {
			wp_send_json_success( 'Item queued for retry' );
		} else {
			wp_send_json_error( 'Failed to queue item' );
		}
	}

	/**
	 * AJAX handler to retry all failed items.
	 */
	public function ajax_retry_all() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'rag_retry' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			return;
		}

		// Retry all failed items
		$count = \UBC\RAG\Retry_Queue::retry_all_failed();

		wp_send_json_success( sprintf( 'Queued %d items for retry', $count ) );
	}

	/**
	 * AJAX handler for testing search.
	 *
	 * @return void
	 */
	public function ajax_search_test() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ubc_rag_search_test' ) ) {
			wp_send_json_error( 'Invalid nonce' );
			return;
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
			return;
		}

		$query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';
		$limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 5;
		$filter_json = isset( $_POST['filter'] ) ? stripslashes( $_POST['filter'] ) : '{}';
		$filter = json_decode( $filter_json, true );

		if ( empty( $query ) ) {
			wp_send_json_error( 'Empty query.' );
			return;
		}

		if ( ! is_array( $filter ) ) {
			$filter = [];
		}

		try {
			// Use the Public API to perform the search.
			// This ensures we are "dogfooding" our own API.
			if ( class_exists( '\UBC\RAG\API' ) ) {
				$results = \UBC\RAG\API::search( $query, $limit, $filter );
			} else {
				// Fallback (should not happen if plugin is loaded correctly).
				wp_send_json_error( 'API class not found.' );
				return;
			}

			wp_send_json_success( $results );

		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
