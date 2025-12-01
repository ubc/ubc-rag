# Document Extraction Details

This document provides technical details on how the UBC RAG plugin extracts text content from various file formats. It covers the libraries used, the extraction strategy, and fallback mechanisms for handling errors.

## Overview

The extraction process is the first step in the RAG pipeline. When a document is uploaded or a post is saved, the appropriate `Extractor` is selected based on the MIME type. The extractor converts the binary or structured content into a list of text "chunks" (usually pages or slides) which are then passed to the chunking system.

## Supported Formats

### 1. Microsoft Word (.docx)

*   **Primary Library**: `PhpOffice\PhpWord`
*   **Strategy**: Iterates through sections and elements (paragraphs, tables, text runs) to extract text.
*   **Pagination**: DOCX does not natively support fixed pagination. The extractor returns the entire document content as a **single chunk** with `page: 1`.
*   **Fallback Mechanism**:
    *   **Trigger**: Occurs if `PhpOffice` throws an exception (e.g., due to malformed XML, missing namespaces, or complex MathML tags).
    *   **Method**: The system attempts to open the `.docx` file as a ZIP archive (using `ZipArchive`), reads the raw `word/document.xml` file, and parses it using `DOMDocument` with error suppression enabled. It extracts all text from `<w:t>` nodes.
    *   **Limitation**: The fallback method extracts raw text without preserving complex formatting or table structures, but ensures content is indexed even if the file structure is slightly invalid.

### 2. PDF Documents (.pdf)

*   **Primary Library**: `Smalot\PdfParser`
*   **Strategy**: Parses the PDF structure and extracts text page by page.
*   **Pagination**: Returns **one chunk per page**. Metadata includes the page number.
*   **Notes**: Scanned PDFs (images) are **not** supported (no OCR is performed).

### 3. PowerPoint Presentations (.pptx)

*   **Primary Library**: `PhpOffice\PhpPresentation`
*   **Strategy**: Iterates through slides and extracts text from shapes and text boxes.
*   **Pagination**: Returns **one chunk per slide**. Metadata includes the slide number.

### 4. WordPress Posts & Pages

*   **Primary Library**: Native WordPress functions (`get_post`, `strip_shortcodes`).
*   **Strategy**: Fetches the post content, strips shortcodes, and removes HTML tags.
*   **Pagination**: Returns a single chunk.

### 5. Text & Markdown (.txt, .md)

*   **Primary Library**: Native PHP string functions.
*   **Strategy**: Reads the file content directly.
*   **Pagination**: Returns a single chunk.

## Error Handling

All extractors implement a standard error handling flow:
1.  **Validation**: Checks if the file exists and is readable.
2.  **Extraction**: Attempts to parse the file using the primary library.
3.  **Logging**: Errors are logged to `rag-debug.log`.
4.  **Fallback**: (Currently only for DOCX) If the primary method fails, a fallback is attempted.
5.  **Failure**: If all methods fail, an empty array is returned, and the item is marked as `failed` in the processing queue.

## Extensibility

Developers can register custom extractors for new MIME types using the `ubc_rag_register_extractors` filter. See the [Developer Guide](developer-guide.md) for details.
