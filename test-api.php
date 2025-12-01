<?php
// Test the Public API

if ( ! class_exists( '\UBC\RAG\API' ) ) {
	echo "API class not found.\n";
	exit;
}

$query = 'climate change';
echo "Searching for: $query\n";

try {
	$results = \UBC\RAG\API::search( $query, 3 );

	if ( empty( $results ) ) {
		echo "No results found.\n";
	} else {
		echo "Found " . count( $results ) . " results:\n";
		foreach ( $results as $result ) {
			echo "- ID: " . $result['id'] . " (Score: " . $result['score'] . ")\n";
			if ( isset( $result['payload']['chunk_text'] ) ) {
				echo "  Text: " . substr( $result['payload']['chunk_text'], 0, 100 ) . "...\n";
			}
		}
	}
} catch ( Exception $e ) {
	echo "Error: " . $e->getMessage() . "\n";
}
