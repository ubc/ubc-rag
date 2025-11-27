<?php
/**
 * Plugin Name: UBC RAG
 * Plugin URI:  https://github.com/ubc-ctlt/ubc-rag
 * Description: Retrieval Augmented Generation (RAG) plugin for WordPress Multisite. Indexes content into a vector database for AI chatbots.
 * Version:     1.0.0
 * Author:      UBC CTLT
 * Author URI:  https://ctlt.ubc.ca
 * Text Domain: ubc-rag
 * Domain Path: /languages
 * License:     GPL-2.0+
 */

namespace UBC\RAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'UBC_RAG_VERSION', '1.0.0' );
define( 'UBC_RAG_FILE', __FILE__ );
define( 'UBC_RAG_PATH', plugin_dir_path( __FILE__ ) );
define( 'UBC_RAG_URL', plugin_dir_url( __FILE__ ) );

// Load Action Scheduler.
if ( file_exists( UBC_RAG_PATH . 'libraries/action-scheduler/action-scheduler.php' ) ) {
	require_once UBC_RAG_PATH . 'libraries/action-scheduler/action-scheduler.php';
}

// Load Composer dependencies.
if ( file_exists( UBC_RAG_PATH . 'vendor/autoload.php' ) ) {
	require_once UBC_RAG_PATH . 'vendor/autoload.php';
}

// Require the autoloader.
require_once UBC_RAG_PATH . 'includes/class-autoloader.php';

// Initialize the plugin.
function run_ubc_rag() {
	$plugin = new Plugin();
	$plugin->run();
}
run_ubc_rag();

// Activation hook.
register_activation_hook( __FILE__, [ 'UBC\RAG\Installer', 'activate' ] );

// Deactivation hook.
register_deactivation_hook( __FILE__, [ 'UBC\RAG\Installer', 'deactivate' ] );
