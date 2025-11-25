<?php

namespace UBC\RAG;

/**
 * Autoloader class.
 */
class Autoloader {

	/**
	 * Run the autoloader.
	 *
	 * @return void
	 */
	public static function run() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload a class.
	 *
	 * @param string $class_name The class name to load.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		// Check if the class is in our namespace.
		if ( 0 !== strpos( $class_name, 'UBC\RAG\\' ) ) {
			return;
		}

		// Remove the namespace from the class name.
		$relative_class = substr( $class_name, strlen( 'UBC\RAG\\' ) );

		// Map the class name to a file path.
		// We are following a structure where:
		// UBC\RAG\Plugin -> includes/class-plugin.php
		// UBC\RAG\Admin\Admin_Menu -> includes/admin/class-admin-menu.php
		// UBC\RAG\Interfaces\ChunkerInterface -> includes/interfaces/interface-chunker.php

		$parts = explode( '\\', $relative_class );
		$file_name = '';
		$path = UBC_RAG_PATH . 'includes/';

		// Handle sub-namespaces (directories).
		for ( $i = 0; $i < count( $parts ) - 1; $i++ ) {
			$path .= strtolower( str_replace( '_', '-', $parts[ $i ] ) ) . '/';
		}

		// Handle the class name (file).
		$class_file = end( $parts );
		
		// Check for Interface convention.
		if ( strpos( $class_file, 'Interface' ) !== false ) {
			$file_name = 'interface-' . strtolower( str_replace( [ '_', 'Interface' ], [ '-', '' ], $class_file ) ) . '.php';
		} else {
			$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_file ) ) . '.php';
		}

		$file = $path . $file_name;

		// Require the file if it exists.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

Autoloader::run();
