# OpenAI Embedding Provider Guide

## Overview

The OpenAI Embedding Provider enables the UBC RAG plugin to generate vector embeddings using OpenAI's state-of-the-art embedding models via their REST API.

## Getting Started

### 1. Obtain an OpenAI API Key

1. Visit [OpenAI Platform](https://platform.openai.com/api-keys)
2. Sign in or create an account
3. Generate a new API key
4. **Keep this key secure** - never share it publicly

### 2. Configure in WordPress Admin

1. Navigate to **Settings → RAG Indexing → Embedding Tab**
2. Select **openai** from the Provider dropdown
3. Paste your API key in the **API Key** field
4. Choose a model:
   - **text-embedding-3-small** (1536 dimensions) - Recommended, lowest cost
   - **text-embedding-3-large** (3072 dimensions) - Higher quality, higher cost
   - **text-embedding-ada-002** (1536 dimensions) - Legacy model
5. Click **Test Connection** to verify your setup
6. Click **Save Settings**

## Model Comparison

| Model | Dimensions | Cost | Quality | Speed | Use Case |
|-------|-----------|------|---------|-------|----------|
| **3-small** | 1536 | ✅ Lowest | ✅✅✅ | ⚡⚡⚡ | Recommended for most sites |
| **3-large** | 3072 | 💰 2x cost | ✅✅✅✅ | ⚡⚡ | Large knowledge bases, max quality |
| **ada-002** | 1536 | ~ Same | ✅✅✅ | ⚡⚡⚡ | Legacy, don't use for new projects |

## API Pricing (as of 2024)

- **text-embedding-3-small**: $0.02 per 1M tokens
- **text-embedding-3-large**: $0.13 per 1M tokens

### Cost Estimation

Example: Indexing 10,000 posts with average 2,000 words each

**Chunking Strategy: Paragraph (chunk_size=3)**
- ~150,000 chunks total
- Average 150 tokens per chunk
- Total: ~22.5M tokens
- **Cost with 3-small: $0.45**
- **Cost with 3-large: $2.93**

## Features

### Regular API (Default)

- **Batching**: Up to 2048 texts per request
- **Speed**: Immediate results
- **Reliability**: Well-tested API
- **Best for**: Normal indexing operations

### Batch API (Future Enhancement)

- **Cost**: 50% cheaper than regular API
- **Speed**: Asynchronous (takes minutes to hours)
- **Best for**: Large bulk re-indexing operations

Currently falls back to regular API. Batch API polling will be implemented in a future phase using ActionScheduler.

## Usage Examples

### Via Admin Settings

The admin settings page handles everything automatically. The provider will:
1. Read chunks from content
2. Call OpenAI API with batches
3. Store embeddings in vector database
4. Handle errors gracefully

### Via Code (Programmatic)

```php
$factory = \UBC\RAG\Embedding_Factory::get_instance();
$provider = $factory->get_provider( 'openai' );

$settings = [
    'api_key'       => 'sk-...',
    'model'         => 'text-embedding-3-small',
    'dimensions'    => 1536,
    'use_batch_api' => false,
];

$chunks = [
    [
        'content'  => 'First chunk text...',
        'metadata' => [ 'page' => 1 ],
    ],
    [
        'content'  => 'Second chunk text...',
        'metadata' => [ 'page' => 2 ],
    ],
];

try {
    $embeddings = $provider->embed( $chunks, $settings );
    // $embeddings is array of float arrays
} catch ( Exception $e ) {
    // Handle error
    Logger::log( $e->getMessage() );
}
```

## Security Considerations

### API Key Storage

Currently, API keys are stored in WordPress options (same location as other settings). For high-security environments, consider:

1. **Future Enhancement**: Implement encryption at rest using WordPress keys/salts
2. **Current Best Practice**: Use WordPress capability checks (`manage_options`)
3. **Production**: Keep API keys in separate .env file via constants
4. **Rotation**: Regularly rotate API keys via OpenAI dashboard

### Password Field Display

Admin UI shows API key input as `type="password"` - characters are hidden but still sent in plain HTTPS.

### Rate Limiting

OpenAI API has rate limits:
- **Requests per minute**: Varies by account tier
- **Tokens per minute**: Varies by account tier
- **Plugin behavior**: Processes one item at a time via ActionScheduler, naturally rate-limiting

If you hit rate limits, increase the time limit between processing jobs.

## Troubleshooting

### "Connection failed: Invalid API key"

- Verify API key is correct
- Check that key is not expired in OpenAI dashboard
- Ensure no extra whitespace in key field

### "Connection failed: 429 Too Many Requests"

- You've hit OpenAI rate limits
- Wait a minute and try again
- Contact OpenAI support if limits are too strict for your use case
- Consider using Batch API for bulk operations (future)

### "API Error (401): Unauthorized"

- Invalid API key
- Key was revoked or expired
- Generate a new key at https://platform.openai.com/api-keys

### "API Error (500): Internal Server Error"

- OpenAI servers experiencing issues
- Wait a few minutes and try again
- Check OpenAI status page: https://status.openai.com

### Slow Indexing

- OpenAI API is working correctly but slow
- Each embedding request takes ~500ms-1s
- Process timing is 20 seconds per ActionScheduler job (5 chunks per job)
- Normal behavior for first bulk index - it's asynchronous
- Speed improves after initial index for incremental updates

## Limitations

### Current (MVP)

- Batch API falls back to regular API (async polling not yet implemented)
- No fine-tuning of embeddings
- No custom model support
- Fixed dimensions per model (cannot override)

### OpenAI API Limits

- Maximum 2048 texts per request
- Maximum 8,191 tokens per text
- Very long documents will be split across requests automatically

### Cost Optimization

If you want 50% cost savings:
- Batch API will be available in future phase
- Requires patience for async processing (minutes to hours)
- Suitable for scheduled nightly re-indexing

## Best Practices

1. **Test First**: Use "Test Connection" button before bulk indexing
2. **Start Small**: Index a few posts first to verify configuration
3. **Monitor Costs**: Check OpenAI dashboard usage regularly
4. **Use 3-small**: Start with smallest model, upgrade if needed
5. **Chunking Strategy**: Smaller chunks = more API calls = higher cost
   - Paragraph chunking: ~$0.40 per 10,000 posts
   - Sentence chunking: ~$0.60 per 10,000 posts
   - Character chunking: ~$1.50 per 10,000 posts

## Monitoring

Check `/wp-content/rag-debug.log` for:
- Successful embeddings
- API errors
- Rate limiting warnings
- Performance metrics

Example log entries:
```
[2025-11-26 14:23:15] Starting embedding from index 0 of 150.
[2025-11-26 14:23:18] Embedded and stored batch ending at index 4
[2025-11-26 14:23:25] Successfully indexed post 123.
```

## Support & Resources

- [OpenAI API Docs](https://platform.openai.com/docs/guides/embeddings)
- [OpenAI Pricing](https://openai.com/pricing)
- [UBC RAG Plugin Docs](https://github.com/ubc-ctlt/ubc-rag)
- UBC CTLT Support: [ctlt@ubc.ca](mailto:ctlt@ubc.ca)

---

**Next Step**: After configuring OpenAI, configure your vector storage (Qdrant or MySQL Vector) in the Storage tab.
