# OpenAI Embedding Provider - Quick Start

## 5-Minute Setup Guide

### Step 1: Get Your API Key (1 minute)
1. Go to https://platform.openai.com/api-keys
2. Sign in (or create account)
3. Click "Create new secret key"
4. Copy the key (starts with `sk-`)
5. **Save it somewhere safe** - you won't see it again

### Step 2: Configure in WordPress (2 minutes)
1. Log in to WordPress admin
2. Go to **Settings → RAG Indexing**
3. Click the **Embedding** tab
4. From "Provider" dropdown, select **openai**
5. Paste your API key
6. Choose model: **text-embedding-3-small** (recommended for first use)
7. Click **Test Connection** - should show ✅ Success!
8. Click **Save Settings**

### Step 3: Start Indexing (2 minutes)
1. Go to **Posts** or **Pages**
2. Publish a new post (or edit existing)
3. Check `/wp-content/rag-debug.log` to see progress
4. After a few seconds, you should see embedding logs

## Quick Reference

| Setting | Value | Notes |
|---------|-------|-------|
| Provider | `openai` | Select from dropdown |
| API Key | `sk-...` | From platform.openai.com |
| Model | `text-embedding-3-small` | Recommended default |
| Dimensions | `1536` | Auto-set, read-only |
| Batch API | Unchecked | Leave off for now |

## What Happens Next

1. **Content is extracted** from your posts
2. **Content is chunked** into pieces (paragraph, sentence, etc.)
3. **OpenAI API generates embeddings** (vector representations)
4. **Vectors are stored** in your vector database (Qdrant or MySQL)
5. **Chatbots can now search** your content semantically

## Costs

For 10,000 posts:
- **text-embedding-3-small**: ~$0.40 (most popular)
- **text-embedding-3-large**: ~$2.93 (highest quality)

Check `/wp-content/rag-debug.log` to monitor progress and verify it's working.

## Troubleshooting

**"Connection failed: Invalid API key"**
- Check key is from https://platform.openai.com/api-keys
- Verify no extra spaces before/after
- Make sure key starts with `sk-`

**"Connection failed: Unauthorized"**
- Key was revoked or expired
- Generate a new one at platform.openai.com

**Still not working?**
- Check `/wp-content/rag-debug.log` for error messages
- Verify WordPress can reach openai.com (check firewall)
- Try Test Connection again

## Next Steps

Once OpenAI is working:
1. Configure vector storage (Storage tab) - coming in Phase 5
2. Set up content types (Content Types tab) - coming in Phase 5
3. Configure chunking strategies (Chunking tab) - coming in Phase 5
4. Monitor logs in `/wp-content/rag-debug.log`

## Where to Find Help

- Log file: `/wp-content/rag-debug.log`
- Settings: **Settings → RAG Indexing → Embedding**
- OpenAI docs: https://platform.openai.com/docs/guides/embeddings
- Pricing: https://openai.com/pricing

---

**Status**: ✅ Ready to use - OpenAI embedding is production-ready

**Next Phase**: Vector Storage (Qdrant) configuration
