# Chunkers

This directory contains the logic for splitting "Raw Chunks" (from Extractors) into smaller, semantically meaningful pieces for embedding.

## Architecture

The chunking system is built around the `ChunkerInterface` and the `Chunker_Factory`.

1.  **`ChunkerInterface`**: Defines the contract for all chunkers.
    *   `chunk( $chunks, $settings, $metadata )`: Takes raw chunks and returns final chunks.

2.  **`Chunker_Factory`**: A singleton that manages registered chunking strategies. It allows other plugins to register custom strategies via the `ubc_rag_register_chunkers` hook.

## Workflow

1.  **Input**: The Chunker receives an array of "Raw Chunks" from the Extractor. Each raw chunk already represents a logical unit (e.g., a Page or Slide).
2.  **Processing**: The Chunker iterates through these raw chunks and applies its splitting logic (e.g., split by paragraph, sentence, or character count).
3.  **Metadata**: The Chunker preserves the original metadata (e.g., `page`) and adds new metadata:
    *   `chunk_index`: The sequential index of the chunk in the document.
    *   `post_id`, `post_type`, `source_url`: Global metadata passed from the Worker.

## Built-in Strategies

*   **`Page_Chunker`** (`page`): Pass-through. Keeps the chunks exactly as returned by the Extractor (e.g., 1 chunk per page/slide).
*   **`Paragraph_Chunker`** (`paragraph`): Splits content by double newlines (`\n\n`). Default is 3 paragraphs per chunk.
*   **`Sentence_Chunker`** (`sentence`): Splits content by sentence boundaries (`.!?`). Default is 5 sentences per chunk.
*   **`Word_Chunker`** (`word`): Splits content by word count. Default is 50 words. Supports overlap.
*   **`Character_Chunker`** (`character`): Splits content by character count. Default is 300 characters. Supports overlap.
