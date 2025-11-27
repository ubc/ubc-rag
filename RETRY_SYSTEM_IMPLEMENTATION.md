# Retry System Implementation - Complete

## Overview

A comprehensive retry system has been implemented to handle transient failures in the RAG plugin's embedding pipeline. This prevents a single failed item from blocking the entire queue and allows graceful recovery from temporary errors.

## Problem Solved

**Before:** When an embedding job failed, it would throw an exception that:
1. Caused ActionScheduler to mark the entire job queue group as failed
2. Blocked all subsequent jobs from processing
3. Required manual database intervention to clear the queue

**After:** Failed jobs now:
1. Are logged and marked as failed in the status table
2. Allow the next job in the queue to process immediately
3. Are automatically retried with exponential backoff
4. Can be manually retried from the admin UI

## Implementation Details

### 1. Worker Error Handling (includes/class-worker.php)

**Change:** Modified the exception handler to NOT rethrow exceptions.

```php
// OLD: throw $e;  // Blocked entire queue!

// NEW:
- Get current retry count from status
- If attempts < 4: Queue for automatic retry with exponential backoff
- If attempts >= 4: Mark as permanently failed
- Return gracefully to allow next job to process
```

**Backoff Schedule:**
- Attempt 1 fails → Retry in 5 minutes
- Attempt 2 fails → Retry in 15 minutes
- Attempt 3 fails → Retry in 1 hour
- Attempt 4 fails → Give up, mark permanently failed

### 2. Ollama Retry Logic (includes/embeddings/class-ollama-provider.php)

**Changes:**
- Added 3 internal retry attempts per chunk with exponential backoff (1s, 2s, 4s)
- Increased timeout from 60s to 120s to account for slow/overloaded instances
- Improved error logging to track which attempt succeeded
- Now attempts to recover from transient "EOF" errors

**Memory Improvements:**
- Removed `num_ctx` option that wasn't helping
- Configurable `request_delay_seconds` (default: 2s) to give Ollama time to free memory
- Can be tuned in Embedding settings if memory issues persist

### 3. Retry Queue Manager (includes/class-retry-queue.php)

**New Class:** Centralized retry management

**Static Methods:**
- `queue_retry($content_id, $content_type, $attempt_count, $error_message)` - Queue item for retry with exponential backoff
- `retry_now($content_id, $content_type)` - Immediately retry a failed item
- `retry_all_failed()` - Retry all failed items at once
- `get_failed_items()` - Get list of failed items with details
- `get_failed_count()` - Get count of failed items

**ActionScheduler Integration:**
- Uses separate queue group: `rag_retry_site_{blog_id}` (doesn't block main queue)
- Implements the `rag_plugin_retry_item` action hook
- Schedules retries at calculated times based on attempt count

### 4. Admin UI (includes/admin/views/advanced-tab.php)

**New Section:** "Failed Items & Retries"

Shows:
- Total failed item count
- Table with details: Content Type, Content ID, Error, Retry Count (X/4), Failed At
- "Retry Now" button for individual items
- "Retry All Failed Items" button for bulk retry

**Features:**
- Hover over error to see full error message
- Real-time updates with page reload
- Confirmation dialogs to prevent accidental retries

### 5. AJAX Handlers (includes/admin/class-admin-menu.php)

**New Methods:**
- `ajax_retry_item()` - Handle single item retry via AJAX
- `ajax_retry_all()` - Handle bulk retry via AJAX

**Security:**
- Verify nonce (`rag_retry`)
- Check `manage_options` capability
- Sanitize all inputs
- Proper error responses

### 6. Plugin Initialization (includes/class-plugin.php)

**Changes:**
- Initialize `Retry_Queue` class on plugin load
- Register both AJAX handlers

---

## Queue Architecture

### Before (Blocking)
```
ActionScheduler Job Queue (rag_site_123 group)
├─ Item 1: Processing... ✓ Success
├─ Item 2: Processing... ✗ FAILS → Exception thrown
├─ Item 3: BLOCKED (queue group halted)
├─ Item 4: BLOCKED
└─ Item 5: BLOCKED
```

### After (Resilient)
```
Main Queue (rag_site_123 group)
├─ Item 1: Processing... ✓ Success
├─ Item 2: Processing... ✗ Fails gracefully → Retry queue scheduled
├─ Item 3: Processing... ✓ Success
├─ Item 4: Processing... ✓ Success
└─ Item 5: Processing... ✓ Success

Retry Queue (rag_retry_site_123 group - separate)
└─ Item 2 retry 1: Scheduled for 5 min from now
   └─ If fails again: Retry 2 scheduled for 15 min from now
       └─ If fails again: Retry 3 scheduled for 1 hour from now
           └─ If fails again: Give up
```

---

## Database Schema

**Column Added:** `retry_count` (TINYINT UNSIGNED)
- Tracks how many retry attempts have been made
- Max value: 4 attempts
- Updated each time a retry is queued

**Status Values:**
- `queued` - Waiting to be processed
- `processing` - Currently being processed
- `indexed` - Successfully completed
- `failed` - Failed (with error_message and retry_count)

---

## Configuration Options

### Embeddings Tab (Ollama)

New setting: **Request Delay (seconds)**
- Default: 2 seconds
- Range: 1-10 seconds
- Purpose: Give Ollama time to free memory between embedding requests
- Increase this if you see "EOF" errors in the logs

### Example Scenario

**Timestamp:** 20:00:00
- Embedding 12 chunks fails with "EOF" error
- Marked as failed with retry_count=1
- Retry scheduled for 20:05:00 (5 minutes)

**Timestamp:** 20:05:00 (Retry Attempt 1)
- Item re-queued, processing starts again
- If it fails: retry_count=2, scheduled for 20:20:00 (15 minutes later)

**Timestamp:** 20:20:00 (Retry Attempt 2)
- If it fails: retry_count=3, scheduled for 21:20:00 (1 hour later)

**Timestamp:** 21:20:00 (Retry Attempt 3)
- If it fails: retry_count=4, marked permanently failed
- No more automatic retries
- Can still manually retry from admin UI

---

## Logging Improvements

### What Gets Logged

When a failure occurs:
```
[2025-11-27 20:00:18] Embedding generation failed: Ollama API Error (500): {"error":"..."}
[2025-11-27 20:00:18] Queued retry (attempt 1/4)
[2025-11-27 20:05:18] Processing retry for attachment 199 (attempt 1)
[2025-11-27 20:05:18] Post saved: 199 (attachment)
[2025-11-27 20:05:28] Ollama: Embedding attempt 1 failed: HTTP 500...
[2025-11-27 20:05:29] Ollama: Retrying in 1s...
[2025-11-27 20:05:30] Ollama: Embedding attempt 2 failed: HTTP 500...
[2025-11-27 20:05:32] Ollama: Retrying in 2s...
[2025-11-27 20:05:34] Ollama: Embedding attempt 3 failed: HTTP 500...
[2025-11-27 20:05:34] Ollama: Failed after 3 attempts
[2025-11-27 20:05:34] Embedding generation failed: Failed to generate embedding after retries
[2025-11-27 20:05:34] Queued retry (attempt 2/4)
```

---

## Testing Recommendations

### Test 1: Transient Failure Recovery
1. Start embedding a large document
2. Stop the Ollama service mid-processing
3. Observe: Job fails, is marked for retry in 5 minutes
4. Restart Ollama
5. After 5 minutes: Job automatically retries and succeeds

### Test 2: Manual Retry
1. Process a document that fails repeatedly
2. Go to Settings → Advanced tab
3. Find item in "Failed Items & Retries" table
4. Click "Retry Now"
5. Observe: Item is immediately re-queued and processes

### Test 3: Queue Continuation
1. Batch import 10 documents
2. Stop Ollama for documents 3-5
3. Observe: Documents 1-2 succeed, 3-5 fail and queue for retry
4. Documents 6-10 continue processing immediately
5. Documents 3-5 retry after 5 minutes when Ollama restarts

### Test 4: Max Retry Limit
1. Process a document with persistent errors
2. Allow it to fail and retry 4 times
3. After 4th attempt: Permanently marked as failed
4. Verify no more automatic retries occur
5. Manual retry should still work

---

## Monitoring & Maintenance

### What to Watch For

1. **Failed Items Table Growth**
   - If growing steadily → underlying issue with embedding provider
   - Check logs for patterns

2. **Stuck Retries**
   - If many retries are scheduled but not executing
   - Check ActionScheduler queue status

3. **Memory Patterns**
   - If failures happen at consistent chunk counts
   - Increase `request_delay_seconds` setting
   - Consider reducing `batch_size` in Worker

### Admin UI Monitoring

Settings → Advanced → Failed Items & Retries shows:
- Total failed count at a glance
- Last failure time per item
- Error messages for diagnosis
- One-click retry options

---

## Files Modified

| File | Changes |
|------|---------|
| includes/class-worker.php | Exception handler: no rethrow, queue retry instead |
| includes/embeddings/class-ollama-provider.php | Retry logic, increased timeout, configurable delay |
| includes/class-retry-queue.php | **NEW**: Retry queue management class |
| includes/class-plugin.php | Initialize Retry_Queue, hook AJAX handlers |
| includes/admin/class-admin-menu.php | AJAX handlers for retry actions |
| includes/admin/views/advanced-tab.php | UI for failed items & retries |

---

## Configuration for High-Reliability Deployments

For production environments with unreliable networks:

**Ollama Settings:**
- Request Delay: 3-4 seconds (allows slower recovery)
- Model: nomic-embed-text (stable, well-tested)

**Processing Settings:**
- Max File Size: Conservative (40-50MB)

**Advanced Monitoring:**
- Enable debug logging
- Monitor `wp-content/rag-debug.log` regularly
- Set up alerts for high failed item counts

---

## Rollback Instructions (if needed)

If the retry system causes issues:

1. Disable auto-retry:
   ```php
   // In Worker::process_item() catch block, comment out:
   // Retry_Queue::queue_retry(...)
   ```

2. Clear failed item queue:
   - Admin UI → Advanced → "Retry All Failed Items"
   - Or: `DELETE FROM wp_rag_index_status WHERE status = 'failed'`

3. Revert Worker behavior:
   - Uncomment: `throw $e;` (restores old behavior)

---

## Summary of Benefits

✅ **Queue No Longer Blocks** - One failure doesn't block all subsequent jobs
✅ **Automatic Recovery** - Transient errors are retried automatically
✅ **Exponential Backoff** - Prevents overwhelming failing services
✅ **Manual Intervention** - Admin can retry failed items anytime
✅ **Better Logging** - Detailed error tracking for diagnosis
✅ **Production Ready** - Max 4 attempts prevents infinite retry loops
✅ **Graceful Degradation** - System continues operating with some failures

---

Generated: 2025-11-27
Implementation Time: ~1 hour
Estimated Testing Time: 2-3 hours for full validation
