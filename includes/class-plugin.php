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

		// Register default content types.
		add_action( 'ubc_rag_register_content_types', [ $this, 'register_content_types' ] );

		// Initialize Content Type Factory.
		\UBC\RAG\Content_Type_Factory::get_instance()->init();

		// Register default lifecycle hooks.
		add_action( 'ubc_rag_setup_lifecycle_hooks', [ $this, 'register_lifecycle_hooks' ] );

		// Fire the lifecycle hooks setup action.
		do_action( 'ubc_rag_setup_lifecycle_hooks' );

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
		$factory->register_extractor( 'link', '\UBC\RAG\Extractors\Link_Extractor' );
		$factory->register_extractor( 'comment', '\UBC\RAG\Extractors\Comment_Extractor' );
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
	 * Register default content types.
	 *
	 * @param \UBC\RAG\Content_Type_Factory $factory Factory instance.
	 */
	public function register_content_types( $factory ) {
		$factory->register_content_type( 'post', [
			'label'            => __( 'Posts', 'ubc-rag' ),
			'description'      => __( 'WordPress blog posts', 'ubc-rag' ),
			'extractor'        => 'post',
			'default_enabled'  => true,
		] );

		$factory->register_content_type( 'page', [
			'label'            => __( 'Pages', 'ubc-rag' ),
			'description'      => __( 'WordPress pages', 'ubc-rag' ),
			'extractor'        => 'page',
			'default_enabled'  => true,
		] );

		$factory->register_content_type( 'attachment', [
			'label'            => __( 'Attachments', 'ubc-rag' ),
			'description'      => __( 'Media files (PDF, Word, PowerPoint, etc.)', 'ubc-rag' ),
			'extractor'        => 'attachment',
			'default_enabled'  => true,
		] );

		$factory->register_content_type( 'link', [
			'label'            => __( 'Bookmarks', 'ubc-rag' ),
			'description'      => __( 'WordPress bookmarks/links (requires link manager enabled)', 'ubc-rag' ),
			'extractor'        => 'link',
			'default_enabled'  => false,
		] );

		$factory->register_content_type( 'comment', [
			'label'            => __( 'Comments', 'ubc-rag' ),
			'description'      => __( 'Blog post comments', 'ubc-rag' ),
			'extractor'        => 'comment',
			'default_enabled'  => false,
		] );
	}

	/**
	 * Register default lifecycle hooks.
	 *
	 * Sets up WordPress lifecycle hooks (save, edit, delete) for built-in content types.
	 * External plugins can hook into 'ubc_rag_setup_lifecycle_hooks' to register their own hooks.
	 *
	 * @return void
	 */
	public function register_lifecycle_hooks() {
		// Posts and Pages - save_post hook is used for both create and update
		add_action( 'save_post', function( $post_id, $post, $update ) {
			if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
				return;
			}

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

			// Only index published posts.
			if ( 'publish' !== $post->post_status ) {
				return;
			}

			// Call appropriate handler based on whether this is a new post or an update.
			if ( $update ) {
				Content_Monitor::on_content_update( $post_id, $post->post_type );
			} else {
				Content_Monitor::on_content_publish( $post_id, $post->post_type );
			}
		}, 10, 3 );

		// Posts - deletion hooks
		add_action( 'wp_trash_post', function( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
				return;
			}
			Content_Monitor::on_content_delete( $post_id, $post->post_type );
		} );

		add_action( 'before_delete_post', function( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
				return;
			}
			Content_Monitor::on_content_delete( $post_id, $post->post_type );
		} );

		// Attachments
		add_action( 'add_attachment', function( $post_id ) {
			Content_Monitor::on_content_publish( $post_id, 'attachment' );
		} );

		add_action( 'edit_attachment', function( $post_id ) {
			Content_Monitor::on_content_update( $post_id, 'attachment' );
		} );

		add_action( 'delete_attachment', function( $post_id ) {
			Content_Monitor::on_content_delete( $post_id, 'attachment' );
		} );

		// Links/Bookmarks
		add_action( 'add_link', function( $link_id ) {
			Content_Monitor::on_content_publish( $link_id, 'link' );
		} );

		add_action( 'edit_link', function( $link_id ) {
			Content_Monitor::on_content_update( $link_id, 'link' );
		} );

		add_action( 'delete_link', function( $link_id ) {
			Content_Monitor::on_content_delete( $link_id, 'link' );
		} );

		// Comments
		add_action( 'comment_post', function( $comment_id, $comment ) {
			Content_Monitor::on_content_publish( $comment_id, 'comment' );
		}, 10, 2 );

		add_action( 'edit_comment', function( $comment_id, $comment ) {
			Content_Monitor::on_content_update( $comment_id, 'comment' );
		}, 10, 2 );

		add_action( 'delete_comment', function( $comment_id ) {
			Content_Monitor::on_content_delete( $comment_id, 'comment' );
		}, 10, 1 );
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
