# Registering Custom Chunkers

The UBC RAG plugin allows external developers to register custom chunking strategies. This guide explains how to implement and register your own chunker.

## 1. Implement the Chunker Interface

Create a class that implements the `UBC\RAG\Interfaces\ChunkerInterface`. Your class must implement the `chunk()` method.

```php
<?php

namespace My_Plugin\Chunkers;

use UBC\RAG\Interfaces\ChunkerInterface;

class My_Custom_Chunker implements ChunkerInterface {

    /**
     * Chunk content according to strategy.
     *
     * @param array $chunks   Array of raw chunks (from Extractor).
     * @param array $settings Strategy-specific settings (e.g., chunk_size).
     * @param array $metadata Global metadata (post_id, etc.).
     * @return array Array of final chunks with metadata.
     */
    public function chunk( array $chunks, array $settings, array $metadata ): array {
        $final_chunks = [];
        $global_chunk_index = 0;

        foreach ( $chunks as $raw_chunk ) {
            $content = $raw_chunk['content'];
            
            // Implement your custom splitting logic here.
            // For example, splitting by specific delimiter:
            $parts = explode( '---', $content );

            foreach ( $parts as $part ) {
                $final_chunks[] = [
                    'content'  => trim( $part ),
                    // Merge global metadata with raw chunk metadata (e.g., page number)
                    // and add the chunk index.
                    'metadata' => array_merge( 
                        $metadata, 
                        $raw_chunk['metadata'], 
                        [ 'chunk_index' => $global_chunk_index++ ] 
                    ),
                ];
            }
        }

        return $final_chunks;
    }
}
```

## 2. Register the Chunker

Hook into `ubc_rag_register_chunkers` to register your class with the `Chunker_Factory`.

```php
add_action( 'ubc_rag_register_chunkers', function( $factory ) {
    // Register with a unique strategy key
    $factory->register_chunker( 'my-custom-strategy', '\My_Plugin\Chunkers\My_Custom_Chunker' );
} );
```

## Usage

Once registered, your strategy can be selected in the plugin settings (future) or used programmatically by passing the strategy key (e.g., `'my-custom-strategy'`) to the `Chunker_Factory`.
