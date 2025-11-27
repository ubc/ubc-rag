# Embeddings

This directory contains the Embedding Provider implementations for the UBC RAG plugin.

## Structure

- **`class-ollama-provider.php`**: Implementation of the Ollama provider.

## Adding a New Provider

To add a new provider, create a class that implements `UBC\RAG\Interfaces\EmbeddingProviderInterface` and register it using the `ubc_rag_register_embedding_providers` hook.

See `docs/register-custom-embedding-provider.md` for a detailed guide.
