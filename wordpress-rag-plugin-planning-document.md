# WordPress RAG Plugin - Technical Planning Document

**Project:** WordPress Multisite RAG (Retrieval Augmented Generation) Plugin
**Version:** 1.0 (MVP)
**Author:** Rich (UBC CTLT)
**Date:** November 24, 2024

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [Settings & Configuration](#settings--configuration)
5. [Content Processing Pipeline](#content-processing-pipeline)
6. [Chunking Strategies](#chunking-strategies)
7. [Embedding Providers](#embedding-providers)
8. [Vector Storage](#vector-storage)
9. [Queue Management](#queue-management)
10. [Admin UI/UX](#admin-uiux)
11. [Hooks & Filters](#hooks--filters)
12. [Error Handling & Logging](#error-handling--logging)
13. [Security Considerations](#security-considerations)
14. [Critical Gotchas](#critical-gotchas)
15. [Testing Strategy](#testing-strategy)
16. [Implementation Phases](#implementation-phases)
17. [Future Enhancements](#future-enhancements)

---

## Executive Summary

### Purpose

Build a WordPress Multisite plugin that indexes website content and uploaded documents into a vector database (Qdrant or MySQL Vector), enabling RAG-powered chatbots to provide course-specific assistance using site content.

### Key Features

-   Per-site vector collections with independent configuration
-   Multiple chunking strategies (character-count, paragraph, sentence, semantic, page-based)
-   Support for multiple embedding providers (OpenAI, Ollama, MySQL Vector)
-   Asynchronous processing with ActionScheduler
-   Granular content control (per-item indexing toggles)
-   Document format support (PDF, DOC, DOCX, TXT, PPT, PPTX)
-   Comprehensive admin interface with status tracking
-   Blue/green re-indexing for configuration changes

### Technical Stack

-   **WordPress:** 6.0+ (latest preferred)
-   **PHP:** 8.2+
-   **Queue:** ActionScheduler
-   **Vector Storage:** Qdrant (primary), MySQL Vector (fallback/alternative)
-   **Document Processing:** Various PHP libraries per format
-   **Embedding APIs:** OpenAI, Ollama

---

## System Architecture

### High-Level Architecture

```
┌───────────────────────────────────────────────────────────────-──┐
│                     WordPress Multisite                          │
│                                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │   Site 1     │  │   Site 2     │  │   Site N     │            │
│  │              │  │              │  │              │            │
│  │ RAG Plugin   │  │ RAG Plugin   │  │ RAG Plugin   │            │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘            │
│         │                 │                 │                    │
│         └─────────────────┴─────────────────┘                    │
│                           │                                      │
│                    ┌──────▼──────--─┐                            │
│                    │ ActionScheduler│                            │
│                    │     Queue      │                            │
│                    └──────┬─────--──┘                            │
└───────────────────────────┼──────────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
         ┌──────▼────────┐      ┌──────▼────────┐
         │  Embedding    │      │    Vector     │
         │  Providers    │      │   Storage     │
         │               │      │               │
         │ - OpenAI      │      │ - Qdrant      │
         │ - Ollama      │      │ - MySQL Vec   │
         │ - MySQL Vec   │      │               │
         └───────────────┘      └───────────────┘
                                        │
                                        │
                                ┌───────▼────────┐
                                │   Chatbot      │
                                │   Plugins      │
                                │  (Separate)    │
                                └────────────────┘
```

### Component Responsibilities

#### 1. Content Monitor

-   Hooks into WordPress post/page/media lifecycle events
-   Detects publish, update, trash, delete actions
-   Checks indexing eligibility based on settings
-   Queues items for processing

#### 2. Queue Manager (ActionScheduler)

-   Manages asynchronous processing jobs
-   Handles retries (max 2 attempts)
-   Maintains job history and status
-   Processes one item at a time per site

#### 3. Document Processor

-   Extracts text content from various file formats
-   Handles memory management and cleanup
-   Splits large documents if necessary
-   Respects WordPress file size limits

#### 4. Content Chunker

-   Applies selected chunking strategy
-   Generates overlapping chunks
-   Preserves metadata with each chunk
-   Calculates content hashes for change detection

#### 5. Embedding Client

-   Abstracts embedding API calls
-   Supports multiple providers via interface
-   Handles batch processing for OpenAI
-   Manages API credentials per provider

#### 6. Vector Storage Client

-   Abstracts vector storage operations
-   Supports Qdrant and MySQL Vector via interface
-   Manages collections/tables
-   Handles CRUD operations for vectors

#### 7. Admin Interface

-   Settings management (tabs-based)
-   Onboarding wizard
-   Status dashboards
-   Bulk operations UI

#### 8. Status Tracker

-   Maintains indexing state in database
-   Provides status for list tables
-   Detects stale content (changed since indexed)
-   Logs operations and errors

---

## Database Schema

### Per-Site Tables

#### `wp_{blog_id}_rag_index_status`

Tracks indexing status for each piece of content.

```sql
CREATE TABLE wp_{blog_id}_rag_index_status (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id BIGINT UNSIGNED NOT NULL,
    content_type VARCHAR(50) NOT NULL, -- 'post', 'page', 'attachment', custom post types
    content_hash VARCHAR(64) NOT NULL, -- SHA256 of content for change detection
    chunking_strategy VARCHAR(50) NOT NULL,
    chunking_settings TEXT, -- JSON: chunk size, overlap, etc.
    embedding_model VARCHAR(100) NOT NULL,
    embedding_dimensions INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL, -- 'queued', 'processing', 'completed', 'failed', 'stale'
    vector_ids TEXT, -- JSON array of Qdrant/MySQL vector IDs
    chunk_count INT UNSIGNED DEFAULT 0,
    last_indexed_at DATETIME,
    error_message TEXT,
    retry_count TINYINT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    INDEX idx_content (content_id, content_type),
    INDEX idx_status (status),
    INDEX idx_content_hash (content_hash),
    INDEX idx_last_indexed (last_indexed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `wp_{blog_id}_rag_logs`

Detailed operation logs for debugging and monitoring.

```sql
CREATE TABLE wp_{blog_id}_rag_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    log_level VARCHAR(20) NOT NULL, -- 'info', 'warning', 'error', 'debug'
    operation VARCHAR(100) NOT NULL, -- 'index', 'delete', 'update', 'reindex', etc.
    content_id BIGINT UNSIGNED,
    content_type VARCHAR(50),
    message TEXT NOT NULL,
    context TEXT, -- JSON: additional context data
    created_at DATETIME NOT NULL,

    INDEX idx_level (log_level),
    INDEX idx_operation (operation),
    INDEX idx_content (content_id, content_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `wp_{blog_id}_rag_vectors` (MySQL Vector only)

Stores vectors when using MySQL Vector fallback.

```sql
CREATE TABLE wp_{blog_id}_rag_vectors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_id BIGINT UNSIGNED NOT NULL,
    content_type VARCHAR(50) NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    chunk_text TEXT NOT NULL,
    embedding BLOB NOT NULL, -- Vector stored as binary
    metadata TEXT, -- JSON: post_url, post_title, taxonomies, etc.
    created_at DATETIME NOT NULL,

    INDEX idx_content (content_id, content_type),
    INDEX idx_chunk (content_id, chunk_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Post/Attachment Meta

Store indexing preference at the individual item level.

-   **Meta Key:** `_rag_index_enabled`
-   **Values:** `1` (index), `0` (don't index), or not set (use global default)

---

## Settings & Configuration

### Settings Structure

Settings are stored in `wp_{blog_id}_options` with the key `rag_plugin_settings` as serialized array.

```php
[
    // Version for migration handling
    'version' => '1.0.0',

    // Storage Configuration
    'storage' => [
        'type' => 'qdrant', // 'qdrant' or 'mysql_vector'
        'qdrant' => [
            'url' => 'https://qdrant.example.com',
            'api_key' => 'encrypted_key',
            'collection_name' => 'site_123_hash456',
            'distance_metric' => 'Cosine', // Cosine, Euclidean, Dot
        ],
        'mysql_vector' => [
            'enabled' => false, // Can be force-enabled via wp-config
        ],
    ],

    // Embedding Configuration
    'embedding' => [
        'provider' => 'openai', // 'openai', 'ollama', 'mysql_vector'
        'openai' => [
            'api_key' => 'encrypted_key',
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'use_batch_api' => true, // Use Batch API for cost savings
        ],
        'ollama' => [
            'endpoint' => 'http://ollama-server:11434',
            'api_key' => '', // Optional
            'model' => 'nomic-embed-text',
            'dimensions' => 768,
        ],
    ],

    // Content Type Configuration
    'content_types' => [
        'post' => [
            'enabled' => true,
            'auto_index' => true,
            'chunking_strategy' => 'semantic',
            'chunking_settings' => [
                'chunk_size' => 1000, // characters
                'overlap' => 200, // characters
            ],
        ],
        'page' => [
            'enabled' => true,
            'auto_index' => true,
            'chunking_strategy' => 'semantic',
            'chunking_settings' => [
                'chunk_size' => 1000,
                'overlap' => 200,
            ],
        ],
        'attachment' => [
            'enabled' => true,
            'auto_index' => true,
            'chunking_strategy' => 'page', // for PDFs, docs, etc.
            'chunking_settings' => [
                'chunk_size' => 2000,
                'overlap' => 300,
            ],
        ],
        // Custom post types added dynamically
    ],

    // Content Inclusion Settings
    'content_options' => [
        'include_excerpts' => true,
        'include_comments' => false,
        'include_media_metadata' => true, // title, caption, alt text, description
        'minimum_user_role_for_index_control' => 'editor', // who can toggle indexing
    ],

    // Processing Settings
    'processing' => [
        'max_file_size_mb' => 50, // Respect system limits, but can set lower
        'retry_attempts' => 2,
    ],

    // Setup/Onboarding
    'onboarding_completed' => false,
]
```

### wp-config.php Options

For system-wide or network-level settings:

```php
// Enable MySQL Vector as a primary storage option (not just fallback)
define('RAG_PLUGIN_MYSQL_VECTOR_AVAILABLE', true);

// Network admin can set defaults (future enhancement)
// define('RAG_PLUGIN_NETWORK_DEFAULTS', true);
```

---

## Content Processing Pipeline

### Workflow Overview

```
Content Publish/Update
        │
        ▼
Check if indexing enabled
        │
        ├─► No → Skip
        │
        ▼ Yes
Generate content hash
        │
        ▼
Compare with stored hash
        │
        ├─► Same → Skip
        │
        ▼ Different
Queue for processing
        │
        ▼
ActionScheduler picks up job
        │
        ▼
Extract content/document
        │
        ▼
Apply chunking strategy
        │
        ▼
Generate embeddings
        │
        ▼
Store in vector DB
        │
        ▼
Update status table
```

### Content Extraction

#### Posts/Pages

1. Get post object from database
2. Apply `the_content` filter to get rendered output (expands shortcodes, Gutenberg blocks)
3. Extract images and append alt text to content
4. Include excerpt if setting enabled
5. Include comments if setting enabled
6. Strip HTML tags but preserve semantic structure (paragraph breaks)
7. Calculate content hash (SHA256)

#### Attachments (Media Files)

1. Get attachment metadata (title, caption, description, alt text)
2. Determine file type from MIME type
3. Route to appropriate document processor:
    - **PDF:** Use Smalot/PdfParser or similar
    - **DOCX/DOC:** Use PHPOffice/PHPWord
    - **PPTX/PPT:** Use PHPOffice/PHPPresentation
    - **TXT:** Direct file_get_contents
4. Extract text content
5. Combine metadata with extracted text
6. Calculate content hash

### Document Processing Libraries

| Format | Library                           | Notes                        |
| ------ | --------------------------------- | ---------------------------- |
| PDF    | `smalot/pdfparser`                | Pure PHP, no dependencies    |
| DOCX   | `phpoffice/phpword`               | Handles modern Word docs     |
| DOC    | `phpoffice/phpword` with fallback | May need additional handling |
| PPTX   | `phpoffice/phppresentation`       | Extracts slide text          |
| PPT    | `phpoffice/phppresentation`       | Limited support              |
| TXT    | Native PHP                        | Simple file read             |

### Memory Management

**Critical Gotcha:** Document processing can be memory-intensive.

**Strategies:**

1. Set PHP memory limit to at least 256MB for processing jobs
2. Use streaming where possible (especially for PDFs)
3. Clean up temp files immediately after processing
4. If memory limit reached, mark as failed with clear error message
5. For future: Consider splitting very large documents into separate processing jobs

---

## Chunking Strategies

Each strategy needs to be implemented as a class implementing a `ChunkerInterface`.

### Interface Definition

```php
interface ChunkerInterface {
    /**
     * Chunk content according to strategy
     *
     * @param string $content The content to chunk
     * @param array $settings Strategy-specific settings (chunk_size, overlap, etc.)
     * @param array $metadata Content metadata (post_id, post_type, etc.)
     * @return array Array of chunks with metadata
     */
    public function chunk(string $content, array $settings, array $metadata): array;
}
```

### Strategy Implementations

#### 1. Character Count Chunker

-   Split content every N characters
-   Apply overlap by backing up N characters from split point
-   Preserve word boundaries (don't split mid-word)

**Settings:**

-   `chunk_size`: Number of characters (default: 1000)
-   `overlap`: Number of overlapping characters (default: 200)

**Use Case:** Generic fallback, consistent chunk sizes

#### 2. Paragraph Chunker

-   Split content by paragraph breaks (double newlines)
-   Combine paragraphs until reaching character limit
-   Apply overlap by including last M characters of previous chunk

**Settings:**

-   `chunk_size`: Target characters per chunk (default: 1000)
-   `overlap`: Characters to overlap (default: 200)

**Use Case:** Blog posts, articles where paragraphs are logical units

#### 3. Sentence Chunker

-   Split content by sentences (period, question mark, exclamation point)
-   Combine sentences until reaching character limit
-   Apply overlap by including last N sentences from previous chunk

**Settings:**

-   `chunk_size`: Target characters per chunk (default: 1000)
-   `overlap`: Characters to overlap (default: 200)

**Use Case:** Academic content, formal writing

#### 4. Semantic Chunker

-   Detect semantic boundaries (HTML headings, WordPress blocks)
-   Keep related content together (content under same heading)
-   Split larger sections if they exceed limits
-   Preserve heading hierarchy in metadata

**Settings:**

-   `chunk_size`: Max characters per chunk (default: 1500)
-   `overlap`: Characters to overlap when splitting large sections (default: 200)
-   `respect_headings`: Keep heading with its content (default: true)

**Use Case:** Educational content, documentation, structured articles

#### 5. Page Chunker (Documents Only)

-   For multi-page documents (PDF, DOCX, PPTX)
-   Create one chunk per page
-   Include page number in metadata
-   Optionally combine multiple pages if small

**Settings:**

-   `pages_per_chunk`: How many pages per chunk (default: 1)
-   `min_chars_per_chunk`: Combine small pages if below this (default: 500)

**Use Case:** Slide decks, textbooks, reports

### Chunk Metadata

Each chunk includes:

```php
[
    'text' => 'The actual chunk content...',
    'chunk_index' => 0, // Position in sequence of chunks
    'chunk_total' => 10, // Total chunks for this content
    'char_start' => 0, // Character position in original (if trackable)
    'char_end' => 1000,
    'page_number' => 1, // For documents only
    'section_heading' => 'Introduction', // For semantic chunking
]
```

---

## Embedding Providers

All embedding providers implement a common interface for abstraction.

### Interface Definition

```php
interface EmbeddingProviderInterface {
    /**
     * Generate embeddings for chunks of text
     *
     * @param array $chunks Array of chunk data (text + metadata)
     * @param array $settings Provider-specific settings
     * @return array Array of embeddings (same order as input)
     * @throws EmbeddingException on failure
     */
    public function embed(array $chunks, array $settings): array;

    /**
     * Get the dimension size of embeddings from this provider
     *
     * @return int
     */
    public function getDimensions(): int;

    /**
     * Test connection with current settings
     *
     * @return bool
     * @throws EmbeddingException with details on failure
     */
    public function testConnection(): bool;
}
```

### Provider Implementations

#### 1. OpenAI Provider

**Regular API:**

-   Endpoint: `https://api.openai.com/v1/embeddings`
-   Supports batching up to 2048 inputs per request
-   Models: `text-embedding-3-small` (1536 dims), `text-embedding-3-large` (3072 dims), `text-embedding-ada-002` (1536 dims)

**Batch API** (for cost savings):

-   Upload batch of requests to OpenAI
-   Poll for completion (can take minutes to hours)
-   Download results when ready
-   **Setting:** `use_batch_api` (boolean)

**Critical Gotcha:** Batch API introduces latency. Only use for:

-   Initial bulk indexing
-   Scheduled re-indexing
-   Non-urgent updates

**Implementation Notes:**

-   Implement both regular and batch API modes
-   For batch mode, create separate ActionScheduler jobs to check completion
-   Store batch job ID in database for status tracking
-   Fall back to regular API if batch API fails

#### 2. Ollama Provider

-   Self-hosted embedding model
-   Endpoint: User-configurable (e.g., `http://ollama-server:11434`)
-   API similar to OpenAI format
-   Models: `nomic-embed-text`, `mxbai-embed-large`, etc.
-   Optional API key support

**Implementation Notes:**

-   HTTP client for REST API calls
-   No built-in batching, send one at a time
-   Timeout handling (self-hosted may be slower)
-   Test connection should verify model is loaded

#### 3. MySQL Vector Provider

-   Uses `mysql-vector` library
-   Generates embeddings using built-in model (TF-IDF or similar)
-   Both embeds and stores in same database
-   Lower quality than transformer models but no external dependency

**Implementation Notes:**

-   Only available when MySQL Vector is the storage backend
-   Dimension size depends on library implementation
-   Much faster but lower semantic quality
-   Suitable for testing or low-resource environments

### Presets

For user convenience, provide model presets:

```php
[
    'openai_small' => [
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimensions' => 1536,
        'description' => 'OpenAI Small - Good balance of cost and quality',
    ],
    'openai_large' => [
        'provider' => 'openai',
        'model' => 'text-embedding-3-large',
        'dimensions' => 3072,
        'description' => 'OpenAI Large - Highest quality, higher cost',
    ],
    'ollama_nomic' => [
        'provider' => 'ollama',
        'model' => 'nomic-embed-text',
        'dimensions' => 768,
        'description' => 'Self-hosted Nomic - Good quality, free to run',
    ],
    'mysql_vector' => [
        'provider' => 'mysql_vector',
        'model' => 'builtin',
        'dimensions' => 512,
        'description' => 'Local MySQL - Fast but lower quality',
    ],
]
```

---

## Vector Storage

### Storage Abstraction

Both Qdrant and MySQL Vector implement a common interface.

```php
interface VectorStorageInterface {
    /**
     * Create/ensure collection exists
     *
     * @param string $collection_name
     * @param int $dimensions
     * @param array $config Additional config (distance metric, etc.)
     * @return bool
     */
    public function createCollection(string $collection_name, int $dimensions, array $config = []): bool;

    /**
     * Delete a collection
     *
     * @param string $collection_name
     * @return bool
     */
    public function deleteCollection(string $collection_name): bool;

    /**
     * Check if collection exists
     *
     * @param string $collection_name
     * @return bool
     */
    public function collectionExists(string $collection_name): bool;

    /**
     * Insert vectors with metadata
     *
     * @param string $collection_name
     * @param array $vectors Array of ['id' => '', 'vector' => [], 'payload' => []]
     * @return array Array of inserted vector IDs
     */
    public function insertVectors(string $collection_name, array $vectors): array;

    /**
     * Delete vectors by ID
     *
     * @param string $collection_name
     * @param array $vector_ids
     * @return bool
     */
    public function deleteVectors(string $collection_name, array $vector_ids): bool;

    /**
     * Delete all vectors matching a filter
     *
     * @param string $collection_name
     * @param array $filter Filter conditions
     * @return int Number of vectors deleted
     */
    public function deleteByFilter(string $collection_name, array $filter): int;

    /**
     * Test connection
     *
     * @return bool
     */
    public function testConnection(): bool;
}
```

### Qdrant Implementation

**Collection Naming:**

-   Format: `site_{blog_id}_{hash}`
-   Hash: First 8 characters of SHA256 of site URL
-   Example: `site_123_a4f3e8b2`

**Collection Configuration:**

-   **Distance Metric:** Cosine (default for text embeddings)
-   **Vector Size:** Determined by embedding model
-   **On-disk payload:** True (saves memory)

**Payload Structure:**

```json
{
	"site_id": 123,
	"blog_id": 123,
	"content_id": 456,
	"content_type": "post",
	"post_title": "My Blog Post",
	"post_url": "https://example.com/my-post",
	"chunk_index": 0,
	"chunk_total": 5,
	"content_hash": "sha256hash",
	"indexed_at": "2024-11-24T10:30:00Z",
	"taxonomies": {
		"category": ["Technology", "Education"],
		"post_tag": ["ai", "wordpress"]
	},
	"char_start": 0,
	"char_end": 1000,
	"page_number": null,
	"section_heading": "Introduction"
}
```

**Indexable Payload Fields:**
Configure Qdrant to index these for filtering:

-   `content_type`
-   `taxonomies.*`
-   `indexed_at`

### MySQL Vector Implementation

**Table Per Site:** `wp_{blog_id}_rag_vectors`

**Vector Storage:**

-   Vectors stored as BLOB
-   Library handles conversion to/from float arrays
-   Similarity search via library functions

**Metadata Storage:**

-   Same structure as Qdrant payload, stored as JSON in `metadata` TEXT column
-   Query via JSON extraction functions for filtering

**Critical Gotcha:** MySQL Vector is slower for large datasets. Document this clearly in admin UI.

---

## Queue Management

### ActionScheduler Integration

ActionScheduler is a robust job queue that:

-   Handles async processing
-   Provides built-in retry logic
-   Has admin UI for monitoring
-   Supports recurring jobs
-   Is battle-tested (used by WooCommerce)

Info: https://actionscheduler.org/usage/ (see: Usage as a Library)

### Job Types

#### 1. Index Single Item

**Action:** `rag_plugin_index_item`
**Args:**

```php
[
    'site_id' => 123,
    'content_id' => 456,
    'content_type' => 'post',
    'operation' => 'create', // 'create', 'update', 'delete'
]
```

**Processing:**

1. Extract content
2. Chunk content
3. Generate embeddings
4. Store vectors
5. Update status table

**Retry Logic:**

-   Max 2 retries
-   If fails, update status to 'failed' with error message

#### 2. Bulk Re-index

**Action:** `rag_plugin_bulk_reindex`
**Args:**

```php
[
    'site_id' => 123,
    'content_types' => ['post', 'page'],
    'reason' => 'settings_changed', // 'settings_changed', 'manual', 'model_changed'
]
```

**Processing:**

1. Query all content of specified types with indexing enabled
2. Queue individual index jobs for each
3. Track progress in options table

#### 3. Batch Embedding Check (OpenAI Batch API)

**Action:** `rag_plugin_check_batch_status`
**Args:**

```php
[
    'site_id' => 123,
    'batch_id' => 'batch_abc123',
    'content_items' => [
        ['content_id' => 456, 'content_type' => 'post'],
        // ...
    ]
]
```

**Processing:**

1. Poll OpenAI Batch API for status
2. If complete, download results and store vectors
3. If still processing, reschedule check in 5 minutes
4. If failed, fall back to regular API for those items

### Scheduling Strategy

**Processing Rate:**

-   One job at a time per site (no parallel processing on single site)
-   Multiple sites can process simultaneously (ActionScheduler handles this)
-   Jobs are processed FIFO within priority group

**Priority Levels:**
While we said no priorities initially, ActionScheduler supports them if needed later:

-   High: User-triggered single item updates
-   Normal: Auto-indexing from publish events
-   Low: Bulk re-indexing operations

---

## Admin UI/UX

### Menu Structure

```
WordPress Admin
└── Settings
    └── RAG Indexing
        ├── Dashboard (main page)
        ├── Content Types (tab)
        ├── Chunking (tab)
        ├── Embedding (tab)
        ├── Storage (tab)
        └── Advanced (tab)
```

### Onboarding Wizard

**Trigger:** First activation of plugin on a site

**Steps:**

1. **Welcome**

    - Explain what the plugin does
    - Link to documentation

2. **Choose Storage**

    - Qdrant or MySQL Vector (if enabled via wp-config)
    - For Qdrant:
        - Enter Qdrant URL
        - Enter API key
        - Test connection
        - Auto-generate collection name: `site_{blog_id}_{hash}`
        - Display collection name (read-only)

3. **Choose Embedding Provider**

    - Show preset dropdown (OpenAI Small, OpenAI Large, Ollama, MySQL Vector)
    - Or "Custom Configuration"
    - Enter credentials (API key, endpoint)
    - Test connection
    - Display embedding dimensions

4. **Select Content Types**

    - Checkboxes for: Posts, Pages, Attachments
    - Show available custom post types with checkboxes
    - Set default chunking strategy per type (dropdown)

5. **Indexing Behavior**

    - Toggle: "Automatically index new published content"
    - Dropdown: "Minimum user role to control indexing" (Editor, Author, Contributor)
    - Checkbox: "Include post excerpts"
    - Checkbox: "Include comments"

6. **Review & Create Collection**

    - Summary of choices
    - Button: "Create Collection & Complete Setup"
    - On click: Create Qdrant collection, save settings, mark onboarding complete
    - Do NOT automatically start indexing

7. **Next Steps**
    - "Setup complete!"
    - Button: "Index Existing Content Now" (starts bulk index)
    - Button: "I'll Index Content Later" (goes to dashboard)

### Settings Page (Tabbed Interface)

#### Dashboard Tab

-   **Collection Status:** Name, dimension size, storage type
-   **Indexing Statistics:**
    -   Total items indexed
    -   Items queued for processing
    -   Failed items (with link to view errors)
    -   Last indexing activity timestamp
-   **Quick Actions:**
    -   Button: "Re-index All Content" (with confirmation)
    -   Button: "Clear All Vectors" (with strong warning)
    -   Button: "Test Connections" (Qdrant + Embedding API)

#### Content Types Tab

-   **Enabled Content Types:** Checkboxes for each
-   **Per-Type Settings Table:**
    | Content Type | Enabled | Auto-Index | Chunking Strategy | Actions |
    |--------------|---------|------------|-------------------|-----------|
    | Posts | ✓ | ✓ | Semantic | Configure |
    | Pages | ✓ | ✓ | Semantic | Configure |
    | Attachments | ✓ | ✓ | Page-based | Configure |
-   **Additional Options:**
    -   Include excerpts (checkbox)
    -   Include comments (checkbox)
    -   Minimum role to control indexing (dropdown)

#### Chunking Tab

**Per Content Type Configuration:**

-   Content Type dropdown selector
-   Strategy selector: Character, Paragraph, Sentence, Semantic, Page-based
-   **Strategy-Specific Settings:**
    -   Chunk size (characters)
    -   Overlap (characters)
    -   Strategy-specific options (e.g., respect headings for semantic)
-   **Preview:** Show sample chunks from a predefined example piece of text
-   Save button
-   **Warning immediately shown on change of settings (before save):** "Changing chunking settings requires re-indexing all [content type] items. This will be done automatically."

#### Embedding Tab

-   **Provider Selection:** Dropdown with presets + "Custom"
-   **OpenAI Configuration:**
    -   API Key (password field)
    -   Model dropdown (small, large, ada-002)
    -   Dimensions (read-only, auto-filled)
    -   Use Batch API (checkbox) with info tooltip
    -   Test Connection button
-   **Ollama Configuration:**
    -   Endpoint URL
    -   API Key (optional)
    -   Model name
    -   Dimensions (manual entry)
    -   Test Connection button
-   **Warning immediately shown on change of settings (before save):** "Changing embedding providers requires re-indexing all content with new embeddings."

#### Storage Tab

-   **Storage Type:** Qdrant or MySQL Vector (read-only after setup, or with stern warning)
-   **Qdrant Settings:**
    -   URL
    -   API Key (password field)
    -   Collection Name (read-only)
    -   Test Connection button
-   **MySQL Vector Settings:** (if applicable)
    -   Table status (exists/doesn't exist)
    -   Vector count

#### Advanced Tab

-   **Processing Settings:**
    -   Max file size (MB)
    -   Retry attempts
-   **Logging:**
    -   Log level dropdown (Error, Warning, Info, Debug)
    -   View Logs button (modal with log table)
    -   Clear Old Logs button
-   **Debug Information:**
    -   PHP memory limit
    -   ActionScheduler status
    -   Vector database connection status
    -   Recent errors (expandable)
-   **Danger Zone:**
    -   Delete All Data button (removes settings, status table, logs, but NOT vectors). Shows AYS.
    -   Separate button to delete all vectors. Shows AYS.
    -   Reset to Defaults button

### Post/Page Editor Integration

**Classic Editor:**

-   Meta box titled "RAG Indexing"
-   Checkbox: "Include in vector database"
-   If checked, show current status: "Not indexed", "Indexed on [date]", "Queued", "Failed: [error]"
-   Button: "Re-index Now" (if already indexed)

**Block Editor (Gutenberg):**

-   Plugin sidebar panel titled "RAG Indexing"
-   Same checkbox and status as Classic Editor
-   Uses `@wordpress/editor` and `@wordpress/components` for UI

**Display Logic:**

-   Only show if content type is enabled for indexing
-   Checkbox is checked by default if auto-indexing is enabled
-   Unchecking the box (if item was indexed) immediately queues deletion job

### Media Library Integration

#### List View

-   **New Column:** "RAG Index Status"
-   **Status Icons:**
    -   ✓ Green check: Indexed
    -   ⏳ Yellow clock: Queued
    -   ⚠️ Red X: Failed (hover shows error)
    -   -   Gray dash: Not indexed
-   **Bulk Actions:**
    -   "Index Selected Items"
    -   "Remove from Index"

#### Grid View (Tile)

-   Small status indicator icon in corner of thumbnail

#### Attachment Details Modal

-   **New Section:** "RAG Indexing"
-   Checkbox: "Include in vector database"
-   Current status display
-   Button: "Re-index Now"

### List Tables (Posts, Pages)

-   **New Column:** "RAG Status" (added via `manage_posts_columns` filter)
-   **Status Display:** Same as media library
-   **Bulk Actions:**
    -   "Index Selected Items"
    -   "Remove from Index"

---

## Hooks & Filters

### Actions

#### Content Lifecycle Hooks

```php
/**
 * Fires when content is successfully indexed
 *
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param array  $vector_ids   Array of vector IDs created
 * @param int    $site_id      Site ID
 */
do_action('rag_plugin_content_indexed', $content_id, $content_type, $vector_ids, $site_id);

/**
 * Fires when content is removed from index
 *
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param int    $site_id      Site ID
 */
do_action('rag_plugin_content_removed', $content_id, $content_type, $site_id);

/**
 * Fires when indexing fails
 *
 * @param int    $content_id     Post/attachment ID
 * @param string $content_type   Content type
 * @param string $error_message  Error message
 * @param int    $site_id        Site ID
 */
do_action('rag_plugin_indexing_failed', $content_id, $content_type, $error_message, $site_id);

/**
 * Fires before bulk re-indexing starts
 *
 * @param array $content_types Content types being re-indexed
 * @param int   $site_id       Site ID
 */
do_action('rag_plugin_bulk_reindex_started', $content_types, $site_id);

/**
 * Fires after bulk re-indexing completes
 *
 * @param array $content_types Content types re-indexed
 * @param int   $total_items   Total items processed
 * @param int   $site_id       Site ID
 */
do_action('rag_plugin_bulk_reindex_completed', $content_types, $total_items, $site_id);
```

### Filters

#### Content Processing Filters

```php
/**
 * Filter extracted content before chunking
 *
 * @param string $content      Extracted content
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param int    $site_id      Site ID
 */
$content = apply_filters('rag_plugin_extracted_content', $content, $content_id, $content_type, $site_id);

/**
 * Filter chunks before embedding
 *
 * Allows modification of chunk text or metadata
 *
 * @param array  $chunks       Array of chunk data
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param int    $site_id      Site ID
 */
$chunks = apply_filters('rag_plugin_chunks', $chunks, $content_id, $content_type, $site_id);

/**
 * Filter vector metadata before storage
 *
 * Add custom metadata to vectors
 *
 * @param array  $metadata     Vector metadata payload
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param array  $chunk_data   Original chunk data
 * @param int    $site_id      Site ID
 */
$metadata = apply_filters('rag_plugin_vector_metadata', $metadata, $content_id, $content_type, $chunk_data, $site_id);
```

#### Indexing Control Filters

```php
/**
 * Filter whether content should be indexed
 *
 * Allows programmatic exclusion of content
 *
 * @param bool   $should_index True if should be indexed
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param int    $site_id      Site ID
 */
$should_index = apply_filters('rag_plugin_should_index_content', $should_index, $content_id, $content_type, $site_id);

/**
 * Filter whether to include post excerpts
 *
 * @param bool $include_excerpts Setting value
 * @param int  $post_id          Post ID
 * @param int  $site_id          Site ID
 */
$include_excerpts = apply_filters('rag_plugin_include_excerpts', $include_excerpts, $post_id, $site_id);

/**
 * Filter retry attempt count
 *
 * @param int    $retry_count  Default retry count
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type
 * @param string $error        Error message from last attempt
 */
$retry_count = apply_filters('rag_plugin_retry_count', $retry_count, $content_id, $content_type, $error);
```

#### Configuration Filters

```php
/**
 * Filter chunking strategy settings
 *
 * @param array  $settings      Strategy settings
 * @param string $strategy_name Strategy name
 * @param string $content_type  Content type
 * @param int    $site_id       Site ID
 */
$settings = apply_filters('rag_plugin_chunking_settings', $settings, $strategy_name, $content_type, $site_id);

/**
 * Filter embedding provider settings
 *
 * @param array  $settings Provider settings
 * @param string $provider Provider name
 * @param int    $site_id  Site ID
 */
$settings = apply_filters('rag_plugin_embedding_settings', $settings, $provider, $site_id);
```

### Programmatic API

For other plugins to interact with the RAG system:

```php
/**
 * Trigger indexing of specific content
 *
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type (optional, auto-detected)
 * @param bool   $force        Force re-index even if already indexed
 * @return bool Success
 */
RAG_Plugin::index_content($content_id, $content_type = null, $force = false);

/**
 * Remove content from index
 *
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type (optional)
 * @return bool Success
 */
RAG_Plugin::remove_from_index($content_id, $content_type = null);

/**
 * Get indexing status for content
 *
 * @param int    $content_id   Post/attachment ID
 * @param string $content_type Content type (optional)
 * @return array|null Status data or null if not found
 */
RAG_Plugin::get_index_status($content_id, $content_type = null);

/**
 * Trigger bulk re-index
 *
 * @param array $content_types Content types to re-index
 * @return bool Success
 */
RAG_Plugin::bulk_reindex(array $content_types);
```

---

## Error Handling & Logging

### Error Categories

1. **Connection Errors**

    - Qdrant unreachable
    - Embedding API timeout
    - Invalid API credentials

2. **Processing Errors**

    - Document extraction failure
    - Memory limit exceeded
    - Timeout during processing
    - Invalid file format

3. **Embedding Errors**

    - API rate limit hit
    - Model not found
    - Invalid input (empty content, too long)

4. **Storage Errors**
    - Collection doesn't exist
    - Vector insertion failed
    - Dimension mismatch

### Error Handling Strategy

**User-Facing Messages:**

-   Clear, actionable error messages
-   Avoid technical jargon where possible
-   Provide next steps

**Examples:**

-   ❌ "cURL error 28: Connection timed out after 30001 milliseconds"
-   ✅ "Unable to connect to Qdrant server. Please check your connection settings and ensure the server is running."

**Technical Details:**

-   Store full technical error in logs table
-   Show summary to user
-   Provide "View Details" link for admins

### Logging System

**Log Levels:**

-   **DEBUG:** Detailed execution flow, only for development
-   **INFO:** Normal operations (indexed X items, started bulk job)
-   **WARNING:** Unusual but non-fatal (retrying after transient error)
-   **ERROR:** Operations that failed and need attention

**Log Storage:**

-   Database table: `wp_{blog_id}_rag_logs`
-   Automatic cleanup: Delete logs older than 30 days
-   Admin configurable log level

**Log Viewer:**

-   Admin UI page: "View Logs"
-   Filterable by level, operation, content_id, date range
-   Searchable
-   Export as CSV option

---

## Security Considerations

### API Key Storage

**Current Approach:** Store API keys in serialized settings (standard WordPress)

**Future Enhancement:** Consider encryption at rest using WordPress keys/salts

**Access Control:**

-   Only users with `manage_options` capability can view/edit settings
-   API keys displayed as password fields (dots, not readable)
-   Never output API keys in JavaScript or public HTML

### Input Validation

**Settings:**

-   Validate URLs (Qdrant endpoint, Ollama endpoint) with `esc_url_raw()`
-   Sanitize text inputs with `sanitize_text_field()`
-   Validate numeric inputs (chunk size, overlap, dimensions)
-   Reject negative numbers, enforce reasonable ranges

**Content Processing:**

-   Sanitize file paths to prevent directory traversal
-   Validate file types against whitelist (no executables)
-   Limit file size to prevent DOS
-   Use WordPress filesystem API for file operations

### SQL Injection Prevention

-   Use `$wpdb->prepare()` for ALL database queries
-   Never concatenate user input into SQL
-   Use parameterized queries

### XSS Prevention

-   Escape all output with appropriate functions:
    -   `esc_html()` for HTML content
    -   `esc_attr()` for HTML attributes
    -   `esc_url()` for URLs
    -   `wp_kses()` for allowed HTML
-   Never use `echo` directly with user data

### CSRF Protection

-   Use WordPress nonces for all forms and AJAX requests
-   Verify nonces before processing
-   Check user capabilities before sensitive operations

### Access Control

**Capabilities Used:**

-   `manage_options`: Required for settings, bulk operations
-   `edit_posts`: Base requirement (plus configured minimum role) for per-item indexing control
-   `upload_files`: Required for media indexing control

**Multisite Super Admin:**

-   Can access all site settings
-   Future: Could set network-wide defaults

### Rate Limiting

**Internal:**

-   Limit concurrent processing jobs (ActionScheduler config)
-   Throttle API requests if needed

**External API:**

-   Respect embedding API rate limits
-   Implement exponential backoff for retries
-   Consider batch API for OpenAI to reduce request count

---

## Critical Gotchas

### 1. Memory Management

**Problem:** Processing large documents or many items can exhaust PHP memory.

**Solutions:**

-   Set minimum memory limit: 256MB for processing jobs
-   Process items one at a time (no parallel)
-   Clean up temporary files immediately
-   For very large PDFs, consider splitting into separate jobs per N pages
-   Use streaming where possible
-   Unset large variables after use

**Detection:**

-   Catch `OutOfMemoryException`
-   Monitor memory usage with `memory_get_peak_usage()`
-   Log memory stats with each processing job

### 2. Content Hash Tracking

**Problem:** How to detect if content changed to avoid unnecessary re-indexing?

**Solution:**

-   Calculate SHA256 hash of extracted content (after rendering, before chunking)
-   Store hash in status table
-   On update, compare new hash with stored hash
-   Only re-index if different

**Edge Case:** Shortcodes or dynamic content may change hash even if author didn't edit. This is acceptable - better to re-index than miss changes.

### 3. Embedding Dimension Mismatch

**Problem:** Changing embedding models changes dimensions, which breaks existing vectors.

**Critical:** Must re-index ALL content when changing models.

**Solution:**

-   Store embedding dimensions in status table per item
-   When model changes, detect dimension mismatch
-   Force full re-index with warning
-   Use blue/green approach: new collection → populate → swap → delete old

**UI Flow:**

1. Admin changes model
2. Show warning: "This will require re-indexing all content. Estimated X minutes for Y items."
3. Confirmation dialog
4. On confirm:
    - Create new collection: `site_123_hash_temp`
    - Queue all content for re-indexing
    - When complete, rename collection (or update setting to point to new one)
    - Delete old collection

### 4. Shortcode Expansion

**Problem:** WordPress shortcodes need to be expanded to get actual content, but this happens at render time.

**Solution:**

-   Use `apply_filters('the_content', $content)` to render content
-   This expands shortcodes, processes Gutenberg blocks
-   Index the rendered output, not raw post_content

**Gotcha:** If shortcode output changes (plugin update, external data), hash will change and trigger re-index. This is correct behavior.

### 5. ActionScheduler Timeout

**Problem:** Long-running jobs (large document processing) may hit PHP max execution time.

**Solutions:**

-   Set reasonable timeout (5 minutes for single item?)
-   If timeout occurs, mark as failed
-   For large files, consider splitting: queue extraction job, then separate embedding job
-   Use ActionScheduler's built-in timeout handling

### 6. Qdrant Collection Naming Conflicts

**Problem:** Site URL could change (domain migration), causing collection name mismatch.

**Solution:**

-   Store collection name in settings, don't regenerate from URL
-   On URL change, collection name stays same (vectors persist)
-   Provide admin option to "change collection" if needed (create new + migrate)

**Migration Flow:**

-   Admin triggers "Change Collection"
-   Input new collection name or auto-generate
-   Create new collection
-   Queue migration jobs (copy vectors from old to new)
-   When complete, update settings to point to new collection
-   Optionally delete old collection

### 7. Batch API Latency

**Problem:** OpenAI Batch API can take minutes to hours for large batches.

**Solution:**

-   Make it optional (setting checkbox)
-   Use only for bulk operations, not single updates
-   Implement status checking job (ActionScheduler recurring every 5 min)
-   Show clear status in admin: "Batch processing, check back in X minutes"
-   Fall back to regular API if batch fails

**When to Use Batch API:**

-   Initial bulk indexing
-   Scheduled full re-indexing
-   When cost is more important than latency

**When NOT to Use:**

-   Single post updates
-   User-triggered manual re-index
-   Anything requiring immediate results

### 8. Blue/Green Re-indexing Race Conditions

**Problem:** Content updates during re-indexing could get lost.

**Solution:**

-   During bulk re-index, track start time
-   If content updated after re-index started, queue separate update job
-   New collection gets re-indexed content, updates go to old collection
-   After swap, replay updates to new collection
-   This is complex - for MVP, simpler approach: lock editing during re-index? Or accept potential data loss for rare edge case?

**MVP Solution:**

-   During re-index, new updates go to old collection
-   After swap, those updates are lost
-   Document this limitation
-   User can manually re-index those specific items if needed

**Future Enhancement:**

-   Implement update queue replay
-   Or real-time dual-write during re-index period

### 9. Taxonomy Term Changes

**Problem:** If post's taxonomy terms change, vectors have stale taxonomy data.

**Solution:**

-   Hook into `set_object_terms` action
-   When terms change for indexed content, queue re-index
-   Re-indexing updates all vector payloads with new terms

**Optimization:** Could update just the payloads without re-embedding, but this is complex. Simpler to re-index (content hash may not change, but force re-index anyway).

### 10. WordPress Multisite Network Activation

**Problem:** Plugin could be activated network-wide, but we want site-by-site.

**Solution:**

-   Don't support network activation in MVP
-   Each site activates individually
-   If network activated, show admin notice: "This plugin must be activated per-site. Please deactivate network-wide and activate on individual sites."

### 11. Attachment Deletion vs. Detachment

**Problem:** WordPress has "delete" vs. "detach" for attachments. Detaching removes from post but keeps file.

**Solution:**

-   Only `wp_delete_attachment` should trigger vector deletion
-   Detaching doesn't affect index status
-   Vectors remain even if attachment detached from all posts

### 12. Document Processing Library Failures

**Problem:** Some PDFs/docs are corrupted, password-protected, or use unsupported features.

**Solution:**

-   Wrap all extraction in try-catch
-   Log specific error from library
-   Mark as failed with user-friendly message: "Unable to extract text from this document"
-   Don't retry if error is permanent (corrupted file)
-   Provide admin option to skip failed files

### 13. Concurrent Queue Processing

**Problem:** ActionScheduler might try to process multiple jobs for same site simultaneously.

**Solution:**

-   Use ActionScheduler groups to ensure one job per site at a time
-   Group name: `rag_site_{blog_id}`
-   This prevents race conditions on status table

### 14. WordPress Import/Migration

**Problem:** Bulk imports may create thousands of posts at once, overwhelming the queue.

**Solution:**

-   Respect auto-index setting
-   If auto-index enabled, queue all imported items
-   Consider rate limiting: batch imports into chunks
-   Provide filter to disable auto-indexing during import:
    ```php
    add_filter('rag_plugin_should_index_content', '__return_false');
    // Do import
    remove_filter('rag_plugin_should_index_content', '__return_false');
    // Manually trigger bulk re-index after
    ```

### 15. Sitemap/Feed/Archive Rendering Performance

**Problem:** If adding status column to post list tables, queries may slow down for sites with many posts.

**Solution:**

-   Only query status for displayed items (current page)
-   Add database indexes on `wp_{blog_id}_rag_index_status.content_id`
-   Cache status results during single page load
-   Lazy load status via AJAX if needed (advanced)

---

## Testing Strategy

### Manual Testing Checklist

#### Setup & Onboarding

-   [ ] Fresh plugin installation triggers onboarding wizard
-   [ ] Can complete wizard with Qdrant
-   [ ] Can complete wizard with MySQL Vector (if enabled)
-   [ ] Can complete wizard with OpenAI
-   [ ] Can complete wizard with Ollama
-   [ ] Test connection buttons work correctly
-   [ ] Collection is created in Qdrant
-   [ ] Settings are saved correctly

#### Content Indexing

-   [ ] Publishing new post queues indexing job
-   [ ] Publishing new page queues indexing job
-   [ ] Uploading media file queues indexing job
-   [ ] Unchecking "index this" prevents indexing
-   [ ] Checking "index this" on existing content queues indexing
-   [ ] Updating indexed content triggers re-index
-   [ ] Trashing indexed content removes vectors
-   [ ] Deleting indexed content removes vectors
-   [ ] Auto-save does NOT trigger re-index

#### Document Processing

-   [ ] PDF extraction works
-   [ ] DOCX extraction works
-   [ ] PPTX extraction works
-   [ ] TXT extraction works
-   [ ] Corrupted file fails gracefully
-   [ ] Very large file handles memory correctly
-   [ ] Multiple pages in PDF create multiple chunks

#### Chunking

-   [ ] Character count chunking works
-   [ ] Paragraph chunking works
-   [ ] Sentence chunking works
-   [ ] Semantic chunking preserves headings
-   [ ] Page-based chunking for documents works
-   [ ] Overlap is applied correctly
-   [ ] Chunk metadata is complete

#### Embedding

-   [ ] OpenAI API embedding works
-   [ ] OpenAI Batch API works (with delay)
-   [ ] Ollama embedding works
-   [ ] MySQL Vector embedding works
-   [ ] API errors are caught and logged
-   [ ] Rate limiting is respected

#### Vector Storage

-   [ ] Vectors stored in Qdrant with correct metadata
-   [ ] Vectors stored in MySQL with correct metadata
-   [ ] Vector deletion works
-   [ ] Bulk deletion works (re-index scenario)
-   [ ] Collection creation/deletion works

#### Settings Changes

-   [ ] Changing chunking strategy triggers re-index warning
-   [ ] Confirmed re-index queues all content
-   [ ] Changing embedding model triggers re-index warning
-   [ ] Blue/green collection swap works
-   [ ] Settings validate correctly (bad URLs, etc.)

#### Admin UI

-   [ ] Dashboard shows correct stats
-   [ ] Status column appears in post list table
-   [ ] Status icons are correct
-   [ ] Bulk actions work (index selected, remove from index)
-   [ ] Media library status column works
-   [ ] Attachment details modal shows status
-   [ ] Log viewer displays logs correctly
-   [ ] Errors are shown clearly

#### Permissions

-   [ ] Non-admins cannot access settings
-   [ ] Minimum role setting is respected for per-item control
-   [ ] Super admin can access all sites (multisite)

#### Error Scenarios

-   [ ] Invalid Qdrant credentials show error
-   [ ] Invalid OpenAI API key shows error
-   [ ] Network timeout is handled
-   [ ] Memory limit exceeded is caught
-   [ ] Failed jobs retry twice then mark failed
-   [ ] Error messages are user-friendly

#### Edge Cases

-   [ ] Empty post doesn't break indexing
-   [ ] Post with only images (no text) handles gracefully
-   [ ] Changing post status from published to draft removes vectors
-   [ ] Switching from MySQL Vector to Qdrant works
-   [ ] Bulk re-index of large site completes
-   [ ] Plugin deactivation doesn't break site
-   [ ] Plugin reactivation preserves settings

### Automated Testing (Future)

**Unit Tests (PHPUnit):**

-   Chunking strategies
-   Content extraction
-   Hash calculation
-   Metadata generation
-   Settings validation

**Integration Tests:**

-   End-to-end indexing flow
-   API interactions (with mocks)
-   Database operations
-   Queue processing

---

## Implementation Phases

### Phase 1: Core Infrastructure (Weeks 1-2)

**Goals:** Basic plugin structure, database schema, settings framework

**Tasks:**

1. Plugin boilerplate (namespace, autoloading, activation/deactivation hooks)
2. Database schema creation (status table, logs table)
3. Settings class and storage
4. Admin menu and basic settings page structure
5. Interface definitions (Chunker, EmbeddingProvider, VectorStorage)

**Deliverable:** Plugin activates, creates tables, settings page accessible

### Phase 2: Content Monitoring & Queue (Weeks 3-4)

**Goals:** Hook into WordPress content lifecycle, queue jobs

**Tasks:**

1. Integrate ActionScheduler
2. Hook into post/page publish, update, delete
3. Hook into attachment upload, delete
4. Content eligibility checks (settings, status, content type)
5. Queue job creation
6. Basic status tracking

**Deliverable:** Content changes queue jobs, status tracked in database

### Phase 3: Content Extraction & Chunking (Weeks 5-6)

**Goals:** Extract content and chunk it

**Tasks:**

1. Post/page content extraction (rendered output)
2. Implement document processing libraries (PDF, DOCX, PPTX, TXT)
3. Implement all chunking strategies
4. Chunk metadata generation
5. Content hash calculation
6. Memory management and cleanup

**Deliverable:** Jobs extract and chunk content, ready for embedding

### Phase 4: Embedding Integration (Week 7)

**Goals:** Generate embeddings via APIs

**Tasks:**

1. Implement OpenAI provider (regular API)
2. Implement Ollama provider
3. Implement MySQL Vector provider
4. Test connection functionality
5. Error handling and retries

**Deliverable:** Chunks converted to embeddings

### Phase 5: Vector Storage (Week 8)

**Goals:** Store vectors in Qdrant or MySQL

**Tasks:**

1. Implement Qdrant client
2. Collection management (create, delete, check)
3. Vector insertion with metadata
4. Vector deletion
5. Implement MySQL Vector client
6. Abstraction layer testing

**Deliverable:** Embeddings stored in vector database, indexed content is retrievable

### Phase 6: Admin UI - Onboarding (Week 9)

**Goals:** First-time setup wizard

**Tasks:**

1. Wizard UI framework
2. Storage selection step
3. Embedding provider step
4. Content type selection step
5. Settings summary and creation
6. Onboarding completion flag

**Deliverable:** New users can set up plugin via wizard

### Phase 7: Admin UI - Settings (Week 10)

**Goals:** Tabbed settings interface

**Tasks:**

1. Dashboard tab with stats
2. Content Types tab with table
3. Chunking tab with per-type config
4. Embedding tab
5. Storage tab
6. Advanced tab with logs

**Deliverable:** Full settings management

### Phase 8: Admin UI - Status & Controls (Week 11)

**Goals:** Status indicators and bulk actions

**Tasks:**

1. Post/page list table column
2. Media library column and bulk actions
3. Post editor meta box (Classic)
4. Post editor sidebar (Gutenberg)
5. Attachment details modal section
6. Bulk re-index functionality

**Deliverable:** Users can see and control indexing status per item

### Phase 9: Advanced Features (Week 12)

**Goals:** Settings changes, blue/green re-indexing, OpenAI Batch API

**Tasks:**

1. Settings change detection and re-index triggering
2. Blue/green collection swap implementation
3. OpenAI Batch API integration
4. Batch status checking jobs
5. Warnings and confirmations for destructive actions

**Deliverable:** Advanced operations work smoothly

### Phase 10: Error Handling & Logging (Week 13)

**Goals:** Robust error handling and logging

**Tasks:**

1. Comprehensive error catching
2. User-friendly error messages
3. Detailed logging to database
4. Log viewer UI
5. Log cleanup (old entries)
6. Retry logic refinement

**Deliverable:** Errors handled gracefully, logs provide debugging info

### Phase 11: Hooks & API (Week 14)

**Goals:** Extensibility for other plugins

**Tasks:**

1. Implement all documented actions
2. Implement all documented filters
3. Programmatic API functions
4. Developer documentation

**Deliverable:** Other plugins can extend/integrate with RAG plugin

### Phase 12: Testing & Hardening (Weeks 15-16)

**Goals:** Thorough testing, bug fixes, performance optimization

**Tasks:**

1. Run through manual testing checklist
2. Test on multiple sites in multisite network
3. Test with large content volumes
4. Memory profiling and optimization
5. Security review
6. Bug fixes
7. Documentation for users

**Deliverable:** Production-ready plugin

### Phase 13: Deployment & Monitoring (Week 17)

**Goals:** Deploy to production, monitor initial usage

**Tasks:**

1. Deploy to UBC staging environment
2. Test with real course sites
3. Monitor logs and errors
4. Gather user feedback
5. Create support documentation
6. Train site admins

**Deliverable:** Plugin live in production

---

## Future Enhancements

### Post-MVP Features

1. **Custom Fields Indexing**

    - Select which custom fields to include
    - Support ACF, Pods, etc.
    - Per-field chunking strategy

2. **OCR for Scanned PDFs**

    - Detect image-based PDFs
    - OCR integration (Tesseract?)
    - Text layer extraction

3. **Multilingual Support**

    - WPML/Polylang integration
    - Separate collections per language
    - Language-specific embedding models

4. **Performance Optimizations**

    - Parallel processing (multiple workers per site)
    - Embedding caching
    - Incremental chunking (only re-chunk changed sections)

5. **Network Admin Defaults**

    - Set defaults at network level
    - Sites inherit/override
    - Centralized monitoring dashboard

6. **Advanced Filtering**

    - Index only content with specific tags/categories
    - Exclude content by author
    - Date range indexing (only recent content)

7. **Content Scheduling**

    - Schedule re-indexing (weekly, monthly)
    - Off-peak processing (nights/weekends)
    - Automatic stale content detection

8. **Analytics & Insights**

    - Indexing speed metrics
    - API cost tracking
    - Content coverage reports
    - Popular content analysis (from chatbot queries)

9. **Alternative Embedding Providers**

    - Cohere
    - Azure OpenAI
    - Anthropic (if they release embeddings)
    - HuggingFace models

10. **Vector Storage Options**

    - Pinecone
    - Weaviate
    - Milvus
    - Redis with vector search

11. **Contextual Metadata Enhancement**

    - Extract entities (people, places, dates)
    - Topic classification
    - Sentiment analysis
    - Keyword extraction

12. **Smart Re-indexing**

    - Detect which chunks changed (diff-based)
    - Only re-embed changed chunks
    - Preserve unchanged chunks

13. **Admin Notifications**

    - Email summaries (weekly indexing report)
    - Slack integration for errors
    - Dashboard widgets for quick overview

14. **Backup & Restore**

    - Export vector database
    - Import vectors from backup
    - Migrate between storage providers

15. **API for External Access**
    - REST API endpoints for status queries
    - Webhook notifications for indexing events
    - Remote trigger for re-indexing

---

## Appendix

### Recommended PHP Libraries

| Purpose     | Library            | Composer Package               |
| ----------- | ------------------ | ------------------------------ |
| Queue       | ActionScheduler    | `woocommerce/action-scheduler` |
| PDF Parser  | PDF Parser         | `smalot/pdfparser`             |
| Word Docs   | PHPWord            | `phpoffice/phpword`            |
| PowerPoint  | PHPPresentation    | `phpoffice/phppresentation`    |
| HTTP Client | Guzzle (if needed) | `guzzlehttp/guzzle`            |

### Qdrant Resources

-   **Documentation:** https://qdrant.tech/documentation/
-   **PHP Client:** May need to implement custom (REST API is straightforward)
-   **Collection API:** https://qdrant.tech/documentation/concepts/collections/
-   **Vector API:** https://qdrant.tech/documentation/concepts/points/

### OpenAI API Resources

-   **Embeddings:** https://platform.openai.com/docs/guides/embeddings
-   **Batch API:** https://platform.openai.com/docs/guides/batch
-   **Rate Limits:** https://platform.openai.com/docs/guides/rate-limits

### MySQL Vector Resources

-   **GitHub:** https://github.com/allanpichardo/mysql-vector
-   **Documentation:** https://raw.githubusercontent.com/allanpichardo/mysql-vector/refs/heads/main/README.md

### WordPress Hooks Reference

-   Post lifecycle: `publish_post`, `post_updated`, `delete_post`, `wp_trash_post`
-   Attachment: `add_attachment`, `delete_attachment`, `edit_attachment`
-   Terms: `set_object_terms`
-   Multisite: `wpmu_new_blog`, `delete_blog`

---

## Document Version History

| Version | Date       | Author | Changes                                 |
| ------- | ---------- | ------ | --------------------------------------- |
| 1.0     | 2024-11-24 | Rich   | Initial comprehensive planning document |

---

## Sign-off

**Prepared by:** Claude (Anthropic AI Assistant)
**Reviewed by:** Rich (UBC CTLT Systems Administrator)
**Status:** Draft for Review
**Next Steps:** Review, refine, begin Phase 1 implementation

---

_This document is a living specification and will be updated as implementation progresses and requirements evolve._
