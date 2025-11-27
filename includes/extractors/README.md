# Extractors

This directory contains the logic for extracting text content from various source types (WordPress posts, PDFs, DOCX, PPTX, etc.).

## Architecture

The extraction system is built around the `ExtractorInterface` and the `Extractor_Factory`.

1.  **`ExtractorInterface`**: Defines the contract for all extractors.
    *   `supports( $type )`: Returns true if the extractor handles the given MIME type.
    *   `extract( $source )`: Returns an array of structured chunks.

2.  **`Extractor_Factory`**: A singleton that manages registered extractors. It allows other plugins to register custom extractors via the `ubc_rag_register_extractors` hook.

## Return Format

Extractors do **not** return a simple string. They return an **array of "Raw Chunks"**. This allows us to preserve structural metadata (like page numbers or slide numbers) before the content is passed to the Chunker.

```php
[
    [
        'content'  => 'Text content...',
        'metadata' => [ 'page' => 1 ],
    ],
    // ...
]
```

## Built-in Extractors

*   **`Post_Extractor`**: Handles WordPress Posts and Pages. Returns a single chunk with `page: 1`.
*   **`PDF_Extractor`**: Uses `smalot/pdfparser`. Returns one chunk per page.
*   **`Pptx_Extractor`**: Uses `phpoffice/phppresentation`. Returns one chunk per slide.
*   **`Docx_Extractor`**: Uses `phpoffice/phpword`. Returns a single chunk (due to DOCX format limitations regarding pagination).
*   **`Text_Extractor`**: Handles `.txt` files.
*   **`Markdown_Extractor`**: Handles `.md` files.
