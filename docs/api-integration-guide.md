# UBC RAG Plugin API Integration Guide

This document provides a guide for developers integrating with the UBC RAG (Retrieval-Augmented Generation) plugin. It details how to perform semantic searches against the vector database and explains the data structures for requests and responses.

## Overview

The UBC RAG plugin exposes a public static API class `\UBC\RAG\API` that allows other plugins to search the indexed content. This API abstracts away the underlying vector store (e.g., Qdrant, MySQL) and embedding provider (e.g., OpenAI, Ollama), providing a consistent interface for retrieval.

## API Method

### `\UBC\RAG\API::search( string $query, int $limit = 5, array $filter = [] ): array`

Performs a semantic search against the vector store.

#### Parameters

| Parameter | Type | Description | Default |
| :--- | :--- | :--- | :--- |
| `$query` | `string` | The natural language search query. This will be converted into a vector embedding by the active provider. | **Required** |
| `$limit` | `int` | The maximum number of results to return. | `5` |
| `$filter` | `array` | Optional metadata filters to restrict the search scope. Key-value pairs matching the metadata fields. | `[]` |

#### Returns

Returns an `array` of search results. Each result is an associative array representing a matching text chunk.

---

## Request & Response Schemas

### 1. Search Request

To perform a search, call the static method from your plugin code.

**Example:**

```php
use UBC\RAG\API;

$query = "What is the university's policy on climate change?";
$limit = 3;
$filter = [
    'content_type' => 'post', // Optional: restrict to posts only
];

$results = API::search( $query, $limit, $filter );
```

### 2. Search Response

The response is an array of result objects. Each result contains the similarity score and the payload (the actual content and metadata).

**Schema:**

```php
[
    [
        'id'      => (string|int) "Unique Vector ID",
        'score'   => (float) 0.892, // Similarity score (0.0 to 1.0)
        'payload' => [
            'content_id'   => (int) 123,          // WordPress Post ID
            'content_type' => (string) "post",    // Post Type (post, page, attachment, etc.)
            'chunk_index'  => (int) 0,            // Index of this chunk in the source document
            'chunk_text'   => (string) "The university is committed to...", // The actual text content
            'metadata'     => [
                'post_id'    => (int) 123,
                'post_type'  => (string) "post",
                'source_url' => (string) "https://example.ubc.ca/climate-policy/", // Permalink
                // ... additional metadata from extractors
            ]
        ]
    ],
    // ... more results
]
```

#### Field Descriptions

*   **`id`**: The unique identifier for the vector in the database.
*   **`score`**: A float representing the cosine similarity between the query and the result. Higher values indicate greater relevance.
*   **`payload`**: The data stored with the vector.
    *   **`content_id`**: The ID of the original WordPress content (Post ID).
    *   **`content_type`**: The type of content (e.g., `'post'`, `'page'`, `'attachment'`).
    *   **`chunk_text`**: The specific segment of text that matched the query. **This is the context you should feed into the LLM.**
    *   **`metadata`**: Additional context about the source.
        *   **`source_url`**: The direct link to the content, useful for citations.

---

## Integration Example: Chatbot

When building a chatbot, you will typically follow this flow:

1.  **Receive User Input**: Get the question from the user.
2.  **Search RAG**: Call `API::search()` with the user's question.
3.  **Construct Prompt**: Format the search results into a "Context" block for the LLM.
4.  **Generate Response**: Send the user's question and the Context to the LLM.

**Code Snippet:**

```php
function get_chatbot_response( $user_question ) {
    // 1. Search the RAG system
    $results = \UBC\RAG\API::search( $user_question, 5 );

    // 2. Format Context
    $context_text = "";
    foreach ( $results as $result ) {
        $text = $result['payload']['chunk_text'];
        $source = $result['payload']['metadata']['source_url'];
        $context_text .= "Source: $source\nContent: $text\n\n";
    }

    // 3. Construct LLM Prompt
    $system_prompt = "You are a helpful assistant for UBC. Use the following context to answer the user's question. If the answer is not in the context, say you don't know.\n\nContext:\n$context_text";
    
    // 4. Call your LLM service (Pseudo-code)
    // $response = My_LLM_Client::chat( $system_prompt, $user_question );
    
    return $response;
}
```

## Filtering

You can filter results by metadata fields. This is useful if you want to restrict the chatbot to specific types of content (e.g., only official policies).

**Supported Filters:**
*   `content_id`: Match a specific post ID.
*   `content_type`: Match a specific post type (e.g., `'page'`, `'attachment'`).
*   **Taxonomies**: You can filter by taxonomy terms if they are indexed in the metadata (e.g., `category`, `post_tag`).

**Example 1: Filter by Post Type**

```php
// Search only within PDF attachments
$results = \UBC\RAG\API::search( $query, 5, [ 'content_type' => 'attachment' ] );
```

**Example 2: Filter by Category**

```php
// Search for posts in the 'news' category
$results = \UBC\RAG\API::search( $query, 5, [ 
    'post_type' => 'post',
    'category'  => 'news' 
] );
```

> [!NOTE]
> Filtering by custom fields or taxonomies (like `category`) requires that these fields are included in the metadata payload during indexing. Ensure your content is indexed with the necessary metadata.

## Error Handling

The `API::search()` method returns an empty array `[]` if:
*   The search fails.
*   No results are found.
*   The vector store or embedding provider is not configured.

Always check for an empty result set before proceeding.
