<div class="tab-pane">
	<h2><?php esc_html_e( 'Search Testing', 'ubc-rag' ); ?></h2>
	<p><?php esc_html_e( 'Use this tool to test the search functionality against your vector store.', 'ubc-rag' ); ?></p>

	<div class="card">
		<h3><?php esc_html_e( 'Test Search', 'ubc-rag' ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="rag-search-query"><?php esc_html_e( 'Query', 'ubc-rag' ); ?></label></th>
				<td>
					<input type="text" id="rag-search-query" class="regular-text" placeholder="<?php esc_attr_e( 'Enter search query...', 'ubc-rag' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag-search-limit"><?php esc_html_e( 'Limit', 'ubc-rag' ); ?></label></th>
				<td>
					<input type="number" id="rag-search-limit" class="small-text" value="5" min="1" max="20">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag-search-filter"><?php esc_html_e( 'Metadata Filter (JSON)', 'ubc-rag' ); ?></label></th>
				<td>
					<textarea id="rag-search-filter" class="large-text code" rows="3" placeholder='{"content_type": "post"}'></textarea>
					<p class="description"><?php esc_html_e( 'Optional. Enter valid JSON for metadata filtering.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="button" id="rag-test-search-btn" class="button button-primary"><?php esc_html_e( 'Search', 'ubc-rag' ); ?></button>
			<span class="spinner" id="rag-search-spinner"></span>
		</p>

		<div id="rag-search-results" style="margin-top: 20px; display: none;">
			<h4><?php esc_html_e( 'Results', 'ubc-rag' ); ?></h4>
			<div id="rag-search-results-content" style="background: #f0f0f1; padding: 10px; border: 1px solid #ccd0d4; max-height: 400px; overflow-y: auto;"></div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#rag-test-search-btn').on('click', function() {
		var query = $('#rag-search-query').val();
		var limit = $('#rag-search-limit').val();
		var filter = $('#rag-search-filter').val();
		var $spinner = $('#rag-search-spinner');
		var $resultsDiv = $('#rag-search-results');
		var $contentDiv = $('#rag-search-results-content');

		if (!query) {
			alert('<?php esc_html_e( 'Please enter a query.', 'ubc-rag' ); ?>');
			return;
		}

		$spinner.addClass('is-active');
		$resultsDiv.hide();
		$contentDiv.empty();

		$.post(ajaxurl, {
			action: 'ubc_rag_search_test',
			nonce: '<?php echo wp_create_nonce( 'ubc_rag_search_test' ); ?>',
			query: query,
			limit: limit,
			filter: filter
		}, function(response) {
			$spinner.removeClass('is-active');
			$resultsDiv.show();

			if (response.success) {
				if (response.data.length === 0) {
					$contentDiv.html('<p><?php esc_html_e( 'No results found.', 'ubc-rag' ); ?></p>');
				} else {
					var html = '<ol>';
					$.each(response.data, function(index, item) {
						html += '<li>';
						html += '<strong>Score:</strong> ' + item.score + '<br>';
						html += '<strong>Content ID:</strong> ' + item.payload.content_id + '<br>';
						html += '<strong>Type:</strong> ' + item.payload.content_type + '<br>';
						html += '<strong>Text:</strong> ' + item.payload.chunk_text + '<br>';
						html += '<strong>Metadata:</strong> <pre>' + JSON.stringify(item.payload.metadata, null, 2) + '</pre>';
						html += '</li><hr>';
					});
					html += '</ol>';
					$contentDiv.html(html);
				}
			} else {
				$contentDiv.html('<p style="color: red;">Error: ' + response.data + '</p>');
			}
		}).fail(function() {
			$spinner.removeClass('is-active');
			$resultsDiv.show();
			$contentDiv.html('<p style="color: red;"><?php esc_html_e( 'Request failed.', 'ubc-rag' ); ?></p>');
		});
	});
});
</script>
