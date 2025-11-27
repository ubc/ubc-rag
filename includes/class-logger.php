<?php

namespace UBC\RAG;

/**
 * Logger class.
 * Handles writing debug logs to a custom file.
 */
class Logger {

	/**
	 * Log a message to the custom debug file.
	 *
	 * @param string $message The message to log.
	 * @return void
	 */
	public static function log( $message ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		$log_file = WP_CONTENT_DIR . '/rag-debug.log';
		$timestamp = date( 'Y-m-d H:i:s' );
		$formatted_message = "[{$timestamp}] {$message}" . PHP_EOL;

		// Append to the log file.
		file_put_contents( $log_file, $formatted_message, FILE_APPEND );
	}
}
