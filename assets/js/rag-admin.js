jQuery(document).ready(function ($) {
    // Retry Item
    $(document).on('click', '.ubc-rag-retry-item', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var type = $btn.data('type');

        $btn.text('Queuing...');

        $.post(ubcRagAdmin.ajaxUrl, {
            action: 'rag_retry_item',
            nonce: ubcRagAdmin.nonce,
            content_id: id,
            content_type: type
        }, function (response) {
            if (response.success) {
                $btn.replaceWith('<span class="dashicons dashicons-clock"></span> Queued');
            } else {
                $btn.text('Retry Failed');
                alert(response.data);
            }
        });
    });

    // Re-index Button (Meta Box)
    $(document).on('click', '.ubc-rag-reindex-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var type = $btn.data('type');

        $btn.prop('disabled', true).text('Queuing...');

        $.post(ubcRagAdmin.ajaxUrl, {
            action: 'rag_retry_item', // Re-using retry logic as it just queues it
            nonce: ubcRagAdmin.nonce,
            content_id: id,
            content_type: type
        }, function (response) {
            if (response.success) {
                $btn.text('Queued for Re-indexing');
            } else {
                $btn.prop('disabled', false).text('Re-index Now');
                alert(response.data);
            }
        });
    });

    // Quick Actions (Row Links)
    $(document).on('click', '.ubc-rag-quick-action', function (e) {
        e.preventDefault();
        var $link = $(this);
        var id = $link.data('id');
        var type = $link.data('type');
        var action = $link.data('action'); // index or deindex

        $link.text('Processing...');

        $.post(ubcRagAdmin.ajaxUrl, {
            action: 'rag_quick_action',
            nonce: ubcRagAdmin.nonce,
            id: id,
            type: type,
            todo: action
        }, function (response) {
            if (response.success) {
                $link.text('Queued');
                // Optional: Reload page or update row status via AJAX?
                // For now, just show queued.
                setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                $link.text('Failed');
                alert(response.data);
            }
        });
    });
});
