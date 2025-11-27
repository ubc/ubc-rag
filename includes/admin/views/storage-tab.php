<?php
/**
 * Storage Tab View.
 */

$settings = \UBC\RAG\Settings::get_settings();
$factory = \UBC\RAG\Vector_Store_Factory::get_instance();
$stores = $factory->get_registered_stores();
$current_store = isset( $settings['vector_store']['provider'] ) ? $settings['vector_store']['provider'] : 'mysql';
?>
<form method="post" action="options.php">
	<?php settings_fields( 'ubc_rag_settings_group' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="rag_vector_store_provider"><?php esc_html_e( 'Vector Store Provider', 'ubc-rag' ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[vector_store][provider]" id="rag_vector_store_provider">
					<?php foreach ( $stores as $slug => $class ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_store, $slug ); ?>>
							<?php echo esc_html( ucfirst( $slug ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the vector database to use.', 'ubc-rag' ); ?></p>
			</td>
		</tr>
	</table>

	<hr>

	<!-- MySQL Settings -->
	<div class="store-settings" id="store-settings-mysql" style="<?php echo 'mysql' === $current_store ? '' : 'display:none;'; ?>">
		<h3><?php esc_html_e( 'MySQL Vector Settings', 'ubc-rag' ); ?></h3>
		<p><?php esc_html_e( 'Uses the local WordPress database. No additional configuration required.', 'ubc-rag' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'ubc-rag' ); ?></th>
				<td>
					<button type="button" class="button button-secondary" id="test-mysql-connection"><?php esc_html_e( 'Test Connection', 'ubc-rag' ); ?></button>
					<span id="mysql-connection-result" style="margin-left: 10px;"></span>
				</td>
			</tr>
		</table>
	</div>

	<!-- Qdrant Settings -->
	<div class="store-settings" id="store-settings-qdrant" style="<?php echo 'qdrant' === $current_store ? '' : 'display:none;'; ?>">
		<h3><?php esc_html_e( 'Qdrant Settings', 'ubc-rag' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rag_qdrant_url"><?php esc_html_e( 'Qdrant URL', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[vector_store][qdrant][url]" type="text" id="rag_qdrant_url" value="<?php echo esc_attr( $settings['vector_store']['qdrant']['url'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'The URL of your Qdrant instance (e.g., http://localhost:6333).', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_qdrant_api_key"><?php esc_html_e( 'API Key', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[vector_store][qdrant][api_key]" type="password" id="rag_qdrant_api_key" value="<?php echo esc_attr( $settings['vector_store']['qdrant']['api_key'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. API Key if your Qdrant instance requires authentication.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_qdrant_collection"><?php esc_html_e( 'Collection Name', 'ubc-rag' ); ?></label></th>
				<td>
					<?php
					// Calculate the standardized collection name for display.
					$blog_id = get_current_blog_id();
					$site_url = get_site_url();
					$hash = substr( hash( 'sha256', $site_url ), 0, 8 );
					$collection_name = "site_{$blog_id}_{$hash}";
					?>
					<input type="text" id="rag_qdrant_collection" value="<?php echo esc_attr( $collection_name ); ?>" class="regular-text" readonly>
					<p class="description"><?php esc_html_e( 'Auto-generated collection name (site_{blog_id}_{hash}). Cannot be changed.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_qdrant_distance"><?php esc_html_e( 'Distance Metric', 'ubc-rag' ); ?></label></th>
				<td>
					<select name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[vector_store][qdrant][distance_metric]" id="rag_qdrant_distance">
						<option value="Cosine" <?php selected( $settings['vector_store']['qdrant']['distance_metric'], 'Cosine' ); ?>>Cosine</option>
						<option value="Euclid" <?php selected( $settings['vector_store']['qdrant']['distance_metric'], 'Euclid' ); ?>>Euclidean</option>
						<option value="Dot" <?php selected( $settings['vector_store']['qdrant']['distance_metric'], 'Dot' ); ?>>Dot Product</option>
					</select>
					<p class="description"><?php esc_html_e( 'Similarity metric used for vector search.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'ubc-rag' ); ?></th>
				<td>
					<button type="button" class="button button-secondary" id="test-qdrant-connection"><?php esc_html_e( 'Test Connection', 'ubc-rag' ); ?></button>
					<span id="qdrant-connection-result" style="margin-left: 10px;"></span>
				</td>
			</tr>
		</table>
	</div>

	<?php submit_button(); ?>
</form>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const storeSelect = document.getElementById('rag_vector_store_provider');
		const storeSettingsDivs = document.querySelectorAll('.store-settings');

		// Handle Store Provider Change
		if (storeSelect) {
			storeSelect.addEventListener('change', function() {
				const selected = this.value;
				storeSettingsDivs.forEach(div => {
					div.style.display = 'none';
				});
				const activeDiv = document.getElementById('store-settings-' + selected);
				if (activeDiv) {
					activeDiv.style.display = 'block';
				}
			});
		}

		// Handle Test Connection for Qdrant
		const testQdrantBtn = document.getElementById('test-qdrant-connection');
		if (testQdrantBtn) {
			testQdrantBtn.addEventListener('click', function() {
				const resultSpan = document.getElementById('qdrant-connection-result');
				const url = document.getElementById('rag_qdrant_url').value;
				const apiKey = document.getElementById('rag_qdrant_api_key').value;

				resultSpan.textContent = '<?php esc_html_e( 'Testing...', 'ubc-rag' ); ?>';
				resultSpan.style.color = '#666';

				const data = new FormData();
				data.append('action', 'ubc_rag_test_connection');
				data.append('type', 'vector_store');
				data.append('provider', 'qdrant');
				data.append('settings[url]', url);
				data.append('settings[api_key]', apiKey);
				data.append('nonce', '<?php echo wp_create_nonce( 'ubc_rag_test_connection' ); ?>');

				fetch(ajaxurl, {
					method: 'POST',
					body: data
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						resultSpan.textContent = '<?php esc_html_e( 'Success!', 'ubc-rag' ); ?>';
						resultSpan.style.color = 'green';
					} else {
						resultSpan.textContent = '<?php esc_html_e( 'Failed: ', 'ubc-rag' ); ?>' + (response.data || 'Unknown error');
						resultSpan.style.color = 'red';
					}
				})
				.catch(error => {
					resultSpan.textContent = '<?php esc_html_e( 'Error: ', 'ubc-rag' ); ?>' + error;
					resultSpan.style.color = 'red';
				});
			});
		}

		// Handle Test Connection for MySQL
		const testMysqlBtn = document.getElementById('test-mysql-connection');
		if (testMysqlBtn) {
			testMysqlBtn.addEventListener('click', function() {
				const resultSpan = document.getElementById('mysql-connection-result');

				resultSpan.textContent = '<?php esc_html_e( 'Testing...', 'ubc-rag' ); ?>';
				resultSpan.style.color = '#666';

				const data = new FormData();
				data.append('action', 'ubc_rag_test_connection');
				data.append('type', 'vector_store');
				data.append('provider', 'mysql');
				data.append('nonce', '<?php echo wp_create_nonce( 'ubc_rag_test_connection' ); ?>');

				fetch(ajaxurl, {
					method: 'POST',
					body: data
				})
				.then(response => response.json())
				.then(response => {
					if (response.success) {
						resultSpan.textContent = '<?php esc_html_e( 'Success!', 'ubc-rag' ); ?>';
						resultSpan.style.color = 'green';
					} else {
						resultSpan.textContent = '<?php esc_html_e( 'Failed: ', 'ubc-rag' ); ?>' + (response.data || 'Unknown error');
						resultSpan.style.color = 'red';
					}
				})
				.catch(error => {
					resultSpan.textContent = '<?php esc_html_e( 'Error: ', 'ubc-rag' ); ?>' + error;
					resultSpan.style.color = 'red';
				});
			});
		}
	});
</script>
