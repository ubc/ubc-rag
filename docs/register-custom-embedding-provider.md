# Registering Custom Embedding Providers

The UBC RAG plugin allows external developers to register custom embedding providers (e.g., OpenAI, Azure, Hugging Face). This guide explains how to implement and register your own provider.

## 1. Implement the Interface

Create a class that implements the `UBC\RAG\Interfaces\EmbeddingProviderInterface`.

```php
<?php

namespace My_Plugin\Embeddings;

use UBC\RAG\Interfaces\EmbeddingProviderInterface;

class My_Custom_Provider implements EmbeddingProviderInterface {

    /**
     * Generate embeddings for chunks of text.
     *
     * @param array $chunks   Array of chunk data (text + metadata).
     * @param array $settings Provider-specific settings.
     * @return array Array of embeddings (same order as input).
     * @throws \Exception On failure.
     */
    public function embed( array $chunks, array $settings ): array {
        $api_key = $settings['api_key'] ?? '';
        // Call your API here...
        
        return $embeddings;
    }

    /**
     * Get the dimension size of embeddings from this provider.
     *
     * @return int
     */
    public function get_dimensions(): int {
        return 1536; // Example dimension
    }

    /**
     * Test connection with current settings.
     *
     * @param array $settings Provider-specific settings.
     * @return bool
     * @throws \Exception With details on failure.
     */
    public function test_connection( array $settings ): bool {
        // Perform a lightweight API call to verify credentials.
        return true;
    }
}
```

## 2. Register the Provider

Hook into `ubc_rag_register_embedding_providers` to register your class with the `Embedding_Factory`.

```php
add_action( 'ubc_rag_register_embedding_providers', function( $factory ) {
    // Register with a unique slug
    $factory->register_provider( 'my-custom-provider', '\My_Plugin\Embeddings\My_Custom_Provider' );
} );
```

## 3. Add Settings (Optional)

Currently, the settings UI is built-in for core providers. To add settings for your custom provider, you may need to hook into the settings page render action (if available in future versions) or provide your own settings management.

*Note: Future versions of the plugin will support dynamic settings rendering for custom providers.*
