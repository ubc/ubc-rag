<?php
/**
 * Advanced Tab View.
 */

$settings = \UBC\RAG\Settings::get_settings();
?>

<form method="post" action="options.php">
	<?php settings_fields( 'ubc_rag_settings_group' ); ?>
	
	<h3><?php esc_html_e( 'Processing Settings', 'ubc-rag' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="rag_max_file_size"><?php esc_html_e( 'Max File Size (MB)', 'ubc-rag' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[processing][max_file_size_mb]" type="number" id="rag_max_file_size" value="<?php echo esc_attr( $settings['processing']['max_file_size_mb'] ); ?>" class="small-text">
				<p class="description"><?php esc_html_e( 'Maximum file size for attachments to be processed.', 'ubc-rag' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rag_retry_attempts"><?php esc_html_e( 'Retry Attempts', 'ubc-rag' ); ?></label></th>
			<td>
				<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[processing][retry_attempts]" type="number" id="rag_retry_attempts" value="<?php echo esc_attr( $settings['processing']['retry_attempts'] ); ?>" class="small-text" max="5" min="0">
				<p class="description"><?php esc_html_e( 'Number of times to retry failed jobs.', 'ubc-rag' ); ?></p>
			</td>
		</tr>
	</table>

	<hr>

	<h3><?php esc_html_e( 'Failed Items & Retries', 'ubc-rag' ); ?></h3>
	<?php
	$failed_items = \UBC\RAG\Retry_Queue::get_failed_items();
	$failed_count = count( $failed_items );
	?>
	<p class="description">
		<?php
		if ( $failed_count > 0 ) {
			printf(
				esc_html( _n( '%d item has failed indexing.', '%d items have failed indexing.', $failed_count, 'ubc-rag' ) ),
				$failed_count
			);
		} else {
			esc_html_e( 'No failed items. Everything is indexed successfully!', 'ubc-rag' );
		}
		?>
	</p>

	<?php if ( $failed_count > 0 ) : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Content Type', 'ubc-rag' ); ?></th>
					<th><?php esc_html_e( 'Content ID', 'ubc-rag' ); ?></th>
					<th><?php esc_html_e( 'Error', 'ubc-rag' ); ?></th>
					<th><?php esc_html_e( 'Retries', 'ubc-rag' ); ?></th>
					<th><?php esc_html_e( 'Failed At', 'ubc-rag' ); ?></th>
					<th><?php esc_html_e( 'Action', 'ubc-rag' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $failed_items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['content_type'] ); ?></td>
						<td><?php echo esc_html( $item['content_id'] ); ?></td>
						<td>
							<span title="<?php echo esc_attr( $item['error_message'] ); ?>">
								<?php echo esc_html( substr( $item['error_message'], 0, 50 ) ); ?>
								<?php if ( strlen( $item['error_message'] ) > 50 ) : ?>
									...
								<?php endif; ?>
							</span>
						</td>
						<td><?php echo esc_html( (int) $item['retry_count'] ); ?>/4</td>
						<td><?php echo esc_html( $item['updated_at'] ); ?></td>
						<td>
							<button type="button" class="button button-small rag-retry-btn"
									data-content-id="<?php echo esc_attr( $item['content_id'] ); ?>"
									data-content-type="<?php echo esc_attr( $item['content_type'] ); ?>">
								<?php esc_html_e( 'Retry Now', 'ubc-rag' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top: 1em;">
			<button type="button" class="button button-primary" id="rag-retry-all-btn">
				<?php esc_html_e( 'Retry All Failed Items', 'ubc-rag' ); ?>
			</button>
		</p>

		<script>
		jQuery(function($) {
			$('.rag-retry-btn').on('click', function(e) {
				e.preventDefault();
				var contentId = $(this).data('content-id');
				var contentType = $(this).data('content-type');

				if (!confirm('<?php esc_html_e( 'Retry this item?', 'ubc-rag' ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'rag_retry_item',
						nonce: '<?php echo wp_create_nonce( 'rag_retry' ); ?>',
						content_id: contentId,
						content_type: contentType,
					},
					success: function(response) {
						if (response.success) {
							alert('<?php esc_html_e( 'Item queued for retry!', 'ubc-rag' ); ?>');
							location.reload();
						} else {
							alert('<?php esc_html_e( 'Error: ', 'ubc-rag' ); ?>' + (response.data || 'Unknown error'));
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Request failed', 'ubc-rag' ); ?>');
					}
				});
			});

			$('#rag-retry-all-btn').on('click', function(e) {
				e.preventDefault();

				if (!confirm('<?php esc_html_e( 'Retry all failed items?', 'ubc-rag' ); ?>')) {
					return;
				}

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'rag_retry_all',
						nonce: '<?php echo wp_create_nonce( 'rag_retry' ); ?>',
					},
					success: function(response) {
						if (response.success) {
							alert('<?php esc_html_e( 'Items queued for retry!', 'ubc-rag' ); ?>');
							location.reload();
						} else {
							alert('<?php esc_html_e( 'Error: ', 'ubc-rag' ); ?>' + (response.data || 'Unknown error'));
						}
					},
					error: function() {
						alert('<?php esc_html_e( 'Request failed', 'ubc-rag' ); ?>');
					}
				});
			});
		});
		</script>
	<?php endif; ?>

	<hr>

	<h3><?php esc_html_e( 'Debugging', 'ubc-rag' ); ?></h3>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Debug Log', 'ubc-rag' ); ?></th>
			<td>
				<?php
				$log_file = WP_CONTENT_DIR . '/rag-debug.log';
				if ( file_exists( $log_file ) ) {
					$log_url = content_url( 'rag-debug.log' );
					echo '<a href="' . esc_url( $log_url ) . '" class="button" target="_blank">' . esc_html__( 'View Log', 'ubc-rag' ) . '</a>';
					echo ' <span class="description">' . sprintf( __( 'Size: %s', 'ubc-rag' ), size_format( filesize( $log_file ) ) ) . '</span>';
				} else {
					echo '<p class="description">' . esc_html__( 'No log file found.', 'ubc-rag' ) . '</p>';
				}
				?>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
