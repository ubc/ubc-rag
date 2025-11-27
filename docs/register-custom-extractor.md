# Registering Custom Extractors

The UBC RAG plugin allows external developers to register custom extractors for new file types or content sources. This guide explains how to implement and register your own extractor.

## 1. Implement the Extractor Interface

Create a class that implements the `UBC\RAG\Interfaces\ExtractorInterface`. Your class must implement two methods: `supports()` and `extract()`.

```php
<?php

namespace My_Plugin\Extractors;

use UBC\RAG\Interfaces\ExtractorInterface;

class My_Custom_Extractor implements ExtractorInterface {

    /**
     * Check if this extractor supports the given MIME type.
     *
     * @param string $type MIME type (e.g., 'application/json').
     * @return bool
     */
    public function supports( $type ): bool {
        return 'application/json' === $type;
    }

    /**
     * Extract content from the source.
     *
     * @param mixed $source Attachment ID or file path.
     * @return array Array of extracted chunks with metadata.
     */
    public function extract( $source ): array {
        $file_path = get_attached_file( $source );
        
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return [];
        }

        $content = file_get_contents( $file_path );
        $data = json_decode( $content, true );

        // Return an array of chunks.
        // Each chunk must have 'content' (string) and 'metadata' (array).
        return [
            [
                'content'  => $data['description'] ?? '',
                'metadata' => [ 'page' => 1, 'custom_field' => 'value' ],
            ],
        ];
    }
}
```

## 2. Register the Extractor

Hook into `ubc_rag_register_extractors` to register your class with the `Extractor_Factory`.

```php
add_action( 'ubc_rag_register_extractors', function( $factory ) {
    // Register for a specific MIME type
    $factory->register_extractor( 'application/json', '\My_Plugin\Extractors\My_Custom_Extractor' );
} );
```

## Return Format

The `extract()` method must return an **array of chunks**. Each chunk is an associative array with:

*   **`content`** (string): The extracted text content.
*   **`metadata`** (array): Key-value pairs of metadata (e.g., `page`, `slide`, `section`).

Example:
```php
[
    [
        'content'  => 'Text from page 1...',
        'metadata' => [ 'page' => 1 ],
    ],
    [
        'content'  => 'Text from page 2...',
        'metadata' => [ 'page' => 2 ],
    ],
]
```
