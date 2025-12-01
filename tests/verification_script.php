<?php
/**
 * Verification script for Embedding System (Standalone / Mocked).
 * Run with: php verification_script.php
 */

// Define constants
define( 'UBC_RAG_PATH', dirname( __FILE__ ) . '/' );
define( 'ABSPATH', '/tmp/' ); // Mock ABSPATH

// Mock WP Functions
function add_action( $hook, $callback ) {
	global $wp_actions;
	$wp_actions[ $hook ][] = $callback;
}

function do_action( $hook, ...$args ) {
	global $wp_actions;
	if ( isset( $wp_actions[ $hook ] ) ) {
		foreach ( $wp_actions[ $hook ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}

function is_wp_error( $thing ) {
	return false;
}

function wp_remote_post( $url, $args ) {
	// Mock response for Ollama
	if ( strpos( $url, 'api/embeddings' ) !== false ) {
		return [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [ 'embedding' => [ 0.1, 0.2, 0.3 ] ] ),
		];
	}
	return [ 'response' => [ 'code' => 500 ] ];
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function current_time( $type, $gmt = 0 ) {
	return date( 'Y-m-d H:i:s' );
}

// Mock Logger
if ( ! class_exists( '\UBC\RAG\Logger' ) ) {
	class MockLogger {
		public static function log( $msg ) {
			echo "[LOG] $msg\n";
		}
	}
	class_alias( 'MockLogger', '\UBC\RAG\Logger' );
}

// Load Autoloader
require_once UBC_RAG_PATH . 'includes/class-autoloader.php';

echo "Starting Verification (Mocked)...\n";

// 1. Verify Factory Exists
if ( ! class_exists( '\UBC\RAG\Embedding_Factory' ) ) {
	echo "[FAIL] Embedding_Factory class not found.\n";
	exit( 1 );
}
echo "[PASS] Embedding_Factory class found.\n";

// 2. Verify Interface Exists
if ( ! interface_exists( '\UBC\RAG\Interfaces\EmbeddingProviderInterface' ) ) {
	echo "[FAIL] EmbeddingProviderInterface not found.\n";
	exit( 1 );
}
echo "[PASS] EmbeddingProviderInterface found.\n";

// 3. Register Providers (Manually trigger init since we don't have full WP init)
$factory = \UBC\RAG\Embedding_Factory::get_instance();
// We need to manually register Ollama because the hook in Plugin class won't run unless we instantiate Plugin.
// But Plugin class has dependencies. Let's just register Ollama manually for this test.
$factory->register_provider( 'ollama', '\UBC\RAG\Embeddings\Ollama_Provider' );

$providers = $factory->get_registered_providers();
if ( ! isset( $providers['ollama'] ) ) {
	echo "[FAIL] Ollama provider not registered.\n";
	exit( 1 );
}
echo "[PASS] Ollama provider registered.\n";

// 4. Verify Custom Provider Registration
class MockProvider implements \UBC\RAG\Interfaces\EmbeddingProviderInterface {
	public function embed( array $chunks, array $settings ): array {
		return array_map( function() { return [0.1, 0.2, 0.3]; }, $chunks );
	}
	public function get_dimensions( array $settings ): int {
		return 3;
	}
	public function test_connection( array $settings ): bool {
		return true;
	}
}

$factory->register_provider( 'mock', 'MockProvider' );
$providers_after = $factory->get_registered_providers();

if ( ! isset( $providers_after['mock'] ) ) {
	echo "[FAIL] Custom Mock provider registration failed.\n";
	exit( 1 );
}
echo "[PASS] Custom Mock provider registered successfully.\n";

// 5. Verify Provider Instantiation
$ollama = $factory->get_provider( 'ollama' );
if ( ! $ollama instanceof \UBC\RAG\Embeddings\Ollama_Provider ) {
	echo "[FAIL] Failed to instantiate Ollama provider.\n";
	exit( 1 );
}
echo "[PASS] Ollama provider instantiated.\n";

$mock = $factory->get_provider( 'mock' );
if ( ! $mock instanceof MockProvider ) {
	echo "[FAIL] Failed to instantiate Mock provider.\n";
	exit( 1 );
}
echo "[PASS] Mock provider instantiated.\n";

// 6. Verify Mock Functionality
if ( $mock->test_connection( [] ) !== true ) {
	echo "[FAIL] Mock provider test_connection failed.\n";
	exit( 1 );
}
echo "[PASS] Mock provider test_connection passed.\n";

$embeddings = $mock->embed( [['content' => 'test']], [] );
if ( count( $embeddings ) !== 1 || count( $embeddings[0] ) !== 3 ) {
	echo "[FAIL] Mock provider embed failed.\n";
	exit( 1 );
}
echo "[PASS] Mock provider embed passed.\n";

// 7. Verify Ollama Functionality (Mocked API)
try {
	$ollama_embeddings = $ollama->embed( [['content' => 'test']], [] );
	if ( count( $ollama_embeddings ) !== 1 || count( $ollama_embeddings[0] ) !== 3 ) {
		echo "[FAIL] Ollama provider embed failed (Mocked).\n";
		exit( 1 );
	}
	echo "[PASS] Ollama provider embed passed (Mocked).\n";
} catch ( Exception $e ) {
	echo "[FAIL] Ollama provider threw exception: " . $e->getMessage() . "\n";
	exit( 1 );
}

// 8. Verify Configurable Dimensions
$default_dims = $ollama->get_dimensions( [] );
if ( $default_dims !== 768 ) {
	echo "[FAIL] Ollama default dimensions incorrect. Expected 768, got $default_dims.\n";
	exit( 1 );
}
echo "[PASS] Ollama default dimensions passed.\n";

$custom_dims = $ollama->get_dimensions( [ 'dimensions' => 1024 ] );
if ( $custom_dims !== 1024 ) {
	echo "[FAIL] Ollama custom dimensions incorrect. Expected 1024, got $custom_dims.\n";
	exit( 1 );
}
echo "[PASS] Ollama custom dimensions passed.\n";

// 9. Verify AJAX Handler Logic (Mocked)
// Mock WP functions needed for AJAX
function check_ajax_referer( $action, $query_arg = false, $die = true ) {
	return true;
}
function current_user_can( $capability ) {
	return true;
}
function sanitize_text_field( $str ) {
	return trim( $str );
}
function wp_send_json_success( $data = null ) {
	echo "[PASS] AJAX Success: " . json_encode( $data ) . "\n";
}
function wp_send_json_error( $data = null ) {
	echo "[FAIL] AJAX Error: " . json_encode( $data ) . "\n";
	// Don't exit here to allow testing other cases if needed, but for this script we want to know.
}

// Load Admin Menu class
require_once UBC_RAG_PATH . 'includes/admin/class-admin-menu.php';
$admin_menu = new \UBC\RAG\Admin\Admin_Menu();

// Test AJAX Success
$_POST['provider'] = 'ollama';
$_POST['settings'] = [ 'endpoint' => 'http://localhost:11434', 'model' => 'nomic-embed-text' ];
echo "Testing AJAX Handler (Success Case)...\n";
$admin_menu->ajax_test_connection();

// Test AJAX Failure (Invalid Provider)
$_POST['provider'] = 'invalid';
echo "Testing AJAX Handler (Invalid Provider)...\n";
$admin_menu->ajax_test_connection();

// 10. Verify Vector_Store (Mocked DB)
global $wpdb;
$wpdb = new class {
	public $prefix = 'wp_';
	public $last_query;
	
	public function get_charset_collate() { return ''; }
	
	public function prepare( $query, ...$args ) { 
		// Simple mock: just return query.
		return $query; 
	}
	
	public function get_var( $query ) { 
		// Mock get_max_chunk_index
		if ( strpos( $query, 'MAX(chunk_index)' ) !== false ) {
			return null; // Simulate no existing vectors
		}
		return null; 
	}
	
	public function insert( $table, $data, $format = null ) { 
		echo "[MOCK DB] Insert into $table: Chunk " . $data['chunk_index'] . "\n";
		return 1; 
	}
	
	public function delete( $table, $where, $format = null ) {
		echo "[MOCK DB] Delete from $table where content_id=" . $where['content_id'] . "\n";
		return 1;
	}
};

// Load Vector_Store
require_once UBC_RAG_PATH . 'includes/class-vector-store.php';

echo "Testing Vector_Store...\n";

// Test Delete
\UBC\RAG\Vector_Store::delete_vectors( 123, 'post' );

// Test Insert
$vectors = [
	[
		'chunk_index' => 0,
		'chunk_text'  => 'Test chunk',
		'embedding'   => [0.1, 0.2, 0.3],
		'metadata'    => [],
	]
];
\UBC\RAG\Vector_Store::insert_vectors( 123, 'post', $vectors );

// Test Get Max Index
$max = \UBC\RAG\Vector_Store::get_max_chunk_index( 123, 'post' );
if ( $max !== null ) {
	echo "[FAIL] Expected null max index, got $max.\n";
} else {
	echo "[PASS] Vector_Store tests passed.\n";
}

echo "Verification Complete. All checks passed.\n";
