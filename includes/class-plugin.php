<?php

namespace UBC\RAG;

use UBC\RAG\Admin\Admin_Menu;

/**
 * Main Plugin Class.
 */
class Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// In the future, we might load a Loader class here if we want to abstract hook registration further.
		// For now, we'll instantiate classes directly.
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		if ( is_admin() ) {
			$admin = new \UBC\RAG\Admin\Admin();
			$admin->init();
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$content_monitor = new Content_Monitor();
		$content_monitor->init();

		$worker = new Worker();
		$worker->init();

		// Initialize retry queue manager.
		$retry_queue = new Retry_Queue();
		$retry_queue->init();

		// Register default extractors.
		add_action( 'ubc_rag_register_extractors', [ $this, 'register_extractors' ] );

		// Initialize Extractor Factory.
		\UBC\RAG\Extractors\Extractor_Factory::get_instance()->init();

		// Register default chunkers.
		add_action( 'ubc_rag_register_chunkers', [ $this, 'register_chunkers' ] );

		// Initialize Chunker Factory.
		\UBC\RAG\Chunker_Factory::get_instance()->init();

		// Register default embedding providers.
		add_action( 'ubc_rag_register_embedding_providers', [ $this, 'register_embedding_providers' ] );

		// Initialize Embedding Factory.
		\UBC\RAG\Embedding_Factory::get_instance()->init();

		// Register meta keys.
		register_meta( 'post', '_ubc_rag_skip_indexing', [
			'show_in_rest' => true,
			'single'       => true,
			'type'         => 'boolean',
			'auth_callback' => function() {
				return current_user_can( 'edit_posts' );
			}
		] );
	}

	/**
	 * Register default extractors.
	 *
	 * @param \UBC\RAG\Extractors\Extractor_Factory $factory Factory instance.
	 */
	public function register_extractors( $factory ) {
		$factory->register_extractor( 'post', '\UBC\RAG\Extractors\Post_Extractor' );
		$factory->register_extractor( 'page', '\UBC\RAG\Extractors\Post_Extractor' );
		$factory->register_extractor( 'application/pdf', '\UBC\RAG\Extractors\PDF_Extractor' );
		$factory->register_extractor( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '\UBC\RAG\Extractors\Docx_Extractor' );
		$factory->register_extractor( 'application/vnd.openxmlformats-officedocument.presentationml.presentation', '\UBC\RAG\Extractors\Pptx_Extractor' );
		$factory->register_extractor( 'text/plain', '\UBC\RAG\Extractors\Text_Extractor' );
		$factory->register_extractor( 'text/markdown', '\UBC\RAG\Extractors\Markdown_Extractor' );
		$factory->register_extractor( 'text/x-markdown', '\UBC\RAG\Extractors\Markdown_Extractor' );
	}

	/**
	 * Register default chunkers.
	 *
	 * @param \UBC\RAG\Chunker_Factory $factory Factory instance.
	 */
	public function register_chunkers( $factory ) {
		$factory->register_chunker( 'character', '\UBC\RAG\Chunkers\Character_Chunker' );
		$factory->register_chunker( 'word', '\UBC\RAG\Chunkers\Word_Chunker' );
		$factory->register_chunker( 'sentence', '\UBC\RAG\Chunkers\Sentence_Chunker' );
		$factory->register_chunker( 'paragraph', '\UBC\RAG\Chunkers\Paragraph_Chunker' );
		$factory->register_chunker( 'page', '\UBC\RAG\Chunkers\Page_Chunker' );
	}

	/**
	 * Register default embedding providers.
	 *
	 * @param \UBC\RAG\Embedding_Factory $factory Factory instance.
	 */
	public function register_embedding_providers( $factory ) {
		$factory->register_provider( 'openai', '\UBC\RAG\Embeddings\OpenAI_Provider' );
		$factory->register_provider( 'ollama', '\UBC\RAG\Embeddings\Ollama_Provider' );
		$factory->register_provider( 'mysql_vector', '\UBC\RAG\Embeddings\MySQL_Vector_Embedding_Provider' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// If we used a Loader class, we would call $this->loader->run() here.
	}
}
