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

## 3. Registering Lifecycle Hooks for Content Types

If your extractor handles a **content type** (not just file attachments), you need to register hooks that listen for when that content is created, updated, or deleted.

### Register the Content Type

First, register your content type so it appears in the RAG settings UI:

```php
add_action( 'ubc_rag_register_content_types', function( $factory ) {
    $factory->register_content_type( 'mytype', [
        'label'           => __( 'My Custom Type', 'my-plugin' ),
        'description'     => __( 'Content from my plugin', 'my-plugin' ),
        'extractor'       => 'mytype',
        'default_enabled' => false,
    ]);
});
```

### Register WordPress Lifecycle Hooks

Then, hook into `ubc_rag_setup_lifecycle_hooks` to register the WordPress hooks that trigger indexing:

```php
add_action( 'ubc_rag_setup_lifecycle_hooks', function() {
    // Called when content is created/published
    add_action( 'my_plugin_content_published', function( $content_id, $content ) {
        \UBC\RAG\Content_Monitor::on_content_publish( $content_id, 'mytype' );
    }, 10, 2 );

    // Called when content is updated/edited
    add_action( 'my_plugin_content_updated', function( $content_id, $content ) {
        \UBC\RAG\Content_Monitor::on_content_update( $content_id, 'mytype' );
    }, 10, 2 );

    // Called when content is deleted
    add_action( 'my_plugin_content_deleted', function( $content_id ) {
        \UBC\RAG\Content_Monitor::on_content_delete( $content_id, 'mytype' );
    }, 10, 1 );
});
```

### Complete Example: Indexing Comments

Here's a complete example showing how to enable comment indexing in your plugin:

#### Step 1: Create the Extractor

```php
<?php

namespace My_Plugin\Extractors;

use UBC\RAG\Extractors\Abstract_Extractor;

class Comment_Extractor extends Abstract_Extractor {
    public function supports( $type ): bool {
        return 'comment' === $type;
    }

    public function extract( $source ): array {
        $comment = get_comment( $source );
        if ( ! $comment ) {
            return [];
        }

        $post = get_post( $comment->comment_post_ID );
        $author = $comment->comment_author ?: 'Anonymous';
        $content = "Comment by $author on {$post->post_title}\n\n{$comment->comment_content}";

        return [
            [
                'content'  => $content,
                'metadata' => [
                    'page'           => 1,
                    'comment_id'     => (int) $comment->comment_ID,
                    'comment_author' => $author,
                    'post_id'        => (int) $comment->comment_post_ID,
                ],
            ],
        ];
    }
}
```

#### Step 2: Register Everything in Your Plugin

```php
<?php

// Register the extractor
add_action( 'ubc_rag_register_extractors', function( $factory ) {
    $factory->register_extractor( 'comment', 'My_Plugin\\Extractors\\Comment_Extractor' );
});

// Register the content type
add_action( 'ubc_rag_register_content_types', function( $factory ) {
    $factory->register_content_type( 'comment', [
        'label'           => __( 'Comments', 'my-plugin' ),
        'description'     => __( 'Blog post comments', 'my-plugin' ),
        'extractor'       => 'comment',
        'default_enabled' => false,
    ]);
});

// Register the WordPress lifecycle hooks
add_action( 'ubc_rag_setup_lifecycle_hooks', function() {
    // When a comment is posted
    add_action( 'comment_post', function( $comment_id, $comment ) {
        \UBC\RAG\Content_Monitor::on_content_publish( $comment_id, 'comment' );
    }, 10, 2 );

    // When a comment is edited
    add_action( 'edit_comment', function( $comment_id, $comment ) {
        \UBC\RAG\Content_Monitor::on_content_update( $comment_id, 'comment' );
    }, 10, 2 );

    // When a comment is deleted
    add_action( 'delete_comment', function( $comment_id ) {
        \UBC\RAG\Content_Monitor::on_content_delete( $comment_id, 'comment' );
    }, 10, 1 );
});
```

That's it! Comments will now appear in the RAG content types tab, and when users enable them, the plugin will automatically index them as they're created, updated, or deleted.

### Key Points

- Use `on_content_publish()` when content is **created or initially published**
- Use `on_content_update()` when content is **edited or modified**
- Use `on_content_delete()` when content is **deleted**
- Each callback receives the content ID and extracts from your extractor
- Your extractor's `supports()` method determines which types it handles
- External eligibility checks and settings validation happen automatically in Content_Monitor
