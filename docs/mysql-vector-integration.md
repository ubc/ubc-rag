# MySQL Vector Integration

This document details the integration of the `mysql-vector` library into the UBC RAG plugin.

## Overview

The integration provides two new components:
1.  **MySQL Vector Store (Library)**: A robust vector storage solution using `MHz\MysqlVector\VectorTable`.
2.  **MySQL Vector Embedding Provider**: A local embedding provider using the BGE model via `MHz\MysqlVector\Nlp\Embedder`.

## Requirements

- **Composer**: You must run `composer install` to install the `allanpichardo/mysql-vector` library and its dependencies (including `onnxruntime`).
- **MySQL**: Version 5.7+ or MariaDB equivalent (supports JSON type).
- **PHP Extensions**: `mysqli` (standard in WordPress).

## Configuration

### Vector Store

1.  Navigate to **Settings > UBC RAG > Storage**.
2.  Select **MySQL Vector** from the provider list.
3.  Click **Save Changes**.
4.  Click **Test Connection** to verify that the database tables can be created and accessed.

**Tables Created:**
- `[prefix]rag_lib_vectors`: Stores the vectors, centroids, and magnitude.
- `[prefix]rag_lib_centroids`: Stores centroids for IVF (Inverted File Index) optimization.
- `[prefix]rag_vector_metadata`: Stores the content metadata (chunk text, content ID, etc.) linked to the vector ID.

### Embedding Provider

1.  Navigate to **Settings > UBC RAG > Embeddings**.
2.  Select **MySQL Vector Embeddings** from the provider list.
3.  Click **Save Changes**.
4.  **Note**: This provider runs locally on the server. It uses the BGE-micro/small ONNX model (~30MB). Ensure your server has sufficient memory and CPU.

## Architecture

### Vector Storage
The implementation separates vector data from metadata:
- **Vector Data**: Managed by the library's `VectorTable` class. This ensures efficient vector operations (cosine similarity, quantization).
- **Metadata**: Managed by `MySQL_Vector_Lib_Store` in a separate table. This allows storing arbitrary WordPress content metadata alongside the vectors.

### Embeddings
The `MySQL_Vector_Embedding_Provider` uses the `Embedder` class from the library. It lazy-loads the ONNX model only when embeddings are actually requested, minimizing the performance impact on standard page loads.
