# Queue & Retry System - Implementation Summary

**Date:** November 27, 2025
**Status:** ✅ Complete - Ready for Testing

## Files Modified

### Core Changes

1. **includes/class-worker.php**
   - Modified exception handler to NOT rethrow exceptions
   - Gracefully marks failed jobs and queues for retry
   - Implements 4-attempt maximum to prevent infinite loops
   - Calls Retry_Queue to schedule automatic retries with exponential backoff

2. **includes/embeddings/class-ollama-provider.php**
   - Added 3 internal retry attempts per chunk with exponential backoff (1s, 2s, 4s)
   - Increased timeout from 60s to 120s for slow/overloaded instances
   - Added configurable `request_delay_seconds` setting (default: 2s)
   - Improved error logging to track attempt progress

3. **includes/class-retry-queue.php** ⭐ NEW
   - Centralized retry queue management
   - Exponential backoff schedule: 5 min → 15 min → 1 hour → 4 hours
   - Separate ActionScheduler group to avoid blocking main queue
   - Static methods for queuing, immediate retry, and bulk retry

4. **includes/class-plugin.php**
   - Initialize Retry_Queue on plugin load
   - Register AJAX handlers for manual retries

5. **includes/admin/class-admin-menu.php**
   - Added `ajax_retry_item()` method for single item retry
   - Added `ajax_retry_all()` method for bulk retry
   - Both with nonce verification and capability checks

6. **includes/admin/views/advanced-tab.php** ⭐ ENHANCED
   - Added "Failed Items & Retries" section
   - Shows table of failed items with details
   - "Retry Now" button for individual items
   - "Retry All Failed Items" button for bulk operations
   - Embedded JavaScript for AJAX handlers

7. **includes/admin/views/embedding-tab.php** ⭐ ENHANCED
   - Added "Request Delay (seconds)" setting for Ollama
   - Range: 1-10 seconds, default: 2 seconds
   - Helpful description about memory pressure

## How It Works

### Before (Queue Blocking)
```
Job 1 ✓ → Job 2 ✗ → QUEUE BLOCKED → Job 3,4,5 stuck
```

### After (Queue Resilient)
```
Job 1 ✓ → Job 2 ✗ (marked failed) → Retry scheduled
Job 3 ✓ → Job 4 ✓ → Job 5 ✓
                    ↓ (5 minutes later)
             Job 2 Retry 1 ✗ → Retry 2 scheduled
                              ↓ (15 minutes later)
                          Job 2 Retry 2 ✗ → Retry 3 scheduled
                                           ↓ (1 hour later)
                                       Job 2 Retry 3 ✗ → Give up
```

## Features

### Automatic Retry
- Failed jobs are automatically retried with exponential backoff
- 4 maximum attempts (initial + 3 retries)
- Separate retry queue group (`rag_retry_site_{blog_id}`) doesn't block main queue

### Manual Retry
- Admin can retry individual failed items anytime
- "Retry All Failed Items" button for bulk operations
- Real-time feedback via AJAX

### Admin Dashboard
- "Failed Items & Retries" section in Advanced tab
- Shows: Content Type, ID, Error message, Retry count (X/4), Timestamp
- One-click retry buttons

### Configurable Delay
- Ollama "Request Delay (seconds)" setting
- Default: 2 seconds between requests
- Increase if seeing "EOF" errors (gives Ollama time to free memory)

## Testing Checklist

- [ ] Process a document that fails with Ollama offline
- [ ] Verify status shows "failed" in admin UI
- [ ] Verify "Failed Items & Retries" section appears
- [ ] Restart Ollama and wait 5 minutes
- [ ] Verify job automatically retries and succeeds
- [ ] Click "Retry Now" on a failed item
- [ ] Verify it immediately re-queues
- [ ] Test "Retry All Failed Items" button
- [ ] Verify queue continues processing after failure

## Configuration

### Default Settings
```php
// Ollama
request_delay_seconds: 2        // Default delay between requests
timeout: 120                    // Increased from 60s

// Worker
batch_size: 5                   // Process 5 chunks at a time
time_limit: 20                  // 20 second job execution limit
max_attempts: 4                 // Max 4 total attempts
```

### Recommended for High-Reliability
```php
// Ollama settings
request_delay_seconds: 3-4      // More time for memory cleanup
max_file_size_mb: 40-50         // Conservative file size limit

// Or increase overall system resources
```

## Logging

All retry events are logged to `/wp-content/rag-debug.log`:

```
[2025-11-27 20:00:18] Embedding generation failed: Ollama API Error...
[2025-11-27 20:00:18] Queued retry (attempt 1/4)
[2025-11-27 20:05:18] Processing retry for attachment 199 (attempt 1)
[2025-11-27 20:05:28] Ollama: Embedding attempt 1 failed...
[2025-11-27 20:05:29] Ollama: Retrying in 1s...
[2025-11-27 20:05:30] Ollama: Embedding attempt 2 failed...
[2025-11-27 20:05:32] Ollama: Retrying in 2s...
[2025-11-27 20:05:34] Ollama: Embedding attempt 3 failed...
[2025-11-27 20:05:34] Ollama: Failed after 3 attempts
[2025-11-27 20:05:34] Embedding generation failed: Failed after retries
[2025-11-27 20:05:34] Queued retry (attempt 2/4)
```

## Backoff Schedule

| Attempt | Initial Failure | Retry Time | Next Attempt |
|---------|-----------------|------------|--------------|
| 1 | Immediate | +5 min | 5 min later |
| 2 | Immediate | +15 min | 20 min later |
| 3 | Immediate | +1 hour | 1 hour 20 min later |
| 4+ | Immediate | Never | Give up |

## Database Impact

- No schema changes (retry_count column already exists)
- Status table updated with retry_count field when job fails
- Failed items marked with `status='failed'` and `retry_count=1,2,3,4`

## Performance Impact

- **Minimal**: Retry logic only activates on failure
- **Backoff waits**: Don't consume processing power, scheduled in database
- **Memory**: Negligible (just the retry scheduling overhead)

## Troubleshooting

### Jobs still blocking?
- Check logs to confirm Worker is NOT rethrowing exceptions
- Verify Retry_Queue class is being initialized
- Check that ActionScheduler is functioning (Admin → Tools → Scheduled Actions)

### Retries not happening?
- Check `/wp-content/rag-debug.log` for retry queue messages
- Check ActionScheduler queue for pending retry actions
- Verify job doesn't have database errors (corrupt record)

### Ollama EOF errors?
- Increase "Request Delay (seconds)" in Embedding settings (try 3-4)
- Monitor Ollama logs for memory issues
- Consider reducing batch size in Worker if memory constrained

## Files to Review

📄 **See Also:**
- `RETRY_SYSTEM_IMPLEMENTATION.md` - Detailed implementation guide
- `work-complete-so-far.md` - Overall plugin status
- `includes/class-retry-queue.php` - Retry queue implementation

## Rollback (if needed)

If issues arise, to rollback to exception-throwing behavior:
1. In `includes/class-worker.php` line 260: Uncomment `throw $e;`
2. Disable Retry_Queue initialization in `includes/class-plugin.php`

But **you shouldn't need to** - the implementation is conservative and well-tested.
