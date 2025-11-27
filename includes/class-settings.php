<?php

namespace UBC\RAG;

/**
 * Settings class.
 */
class Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'rag_plugin_settings';

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = self::get_defaults();
		$settings = get_option( self::OPTION_KEY, [] );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings New settings.
	 * @return bool
	 */
	public static function update_settings( $settings ) {
		return update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return [
			'version' => UBC_RAG_VERSION,
			'vector_store' => [
				'provider' => 'mysql',
				'qdrant'   => [
					'url'             => 'http://localhost:6333',
					'api_key'         => '',
					'collection_name' => 'rag_collection',
					'distance_metric' => 'Cosine',
				],
				'mysql'    => [
					'enabled' => true,
				],
			],
			'embedding' => [
				'provider' => '',
				'openai'   => [
					'api_key'       => '',
					'model'         => 'text-embedding-3-small',
					'dimensions'    => 1536,
					'use_batch_api' => true,
				],
				'ollama'   => [
					'endpoint'       => 'http://localhost:11434',
					'model'          => 'nomic-embed-text',
					'dimensions'     => 768,
					'api_key'        => '',
					'context_window' => 8192,
				],
			],
			'content_types' => [
				'post' => [
					'enabled'           => true,
					'auto_index'        => true,
					'chunking_strategy' => 'semantic',
					'chunking_settings' => [
						'chunk_size' => 1000,
						'overlap'    => 200,
					],
				],
				'page' => [
					'enabled'           => true,
					'auto_index'        => true,
					'chunking_strategy' => 'semantic',
					'chunking_settings' => [
						'chunk_size' => 1000,
						'overlap'    => 200,
					],
				],
				'attachment' => [
					'enabled'           => true,
					'auto_index'        => true,
					'chunking_strategy' => 'page',
					'chunking_settings' => [
						'chunk_size' => 1500, // Reduced from 2000 to fit time budget
						'overlap'    => 250,  // Reduced from 300 to match smaller chunks
					],
				],
			],
			'content_options' => [
				'include_excerpts'                     => true,
				'include_comments'                     => false,
				'include_media_metadata'               => true,
				'minimum_user_role_for_index_control'  => 'editor',
			],
			'processing' => [
				'max_file_size_mb' => 50,
				'retry_attempts'   => 2,
			],
			'onboarding_completed' => false,
		];
	}
}
