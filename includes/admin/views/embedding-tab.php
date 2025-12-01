<?php
/**
 * Embedding Tab View.
 */

$settings = \UBC\RAG\Settings::get_settings();
$providers = \UBC\RAG\Embedding_Factory::get_instance()->get_registered_providers();
$current_provider = $settings['embedding']['provider'];
?>
<form method="post" action="options.php">
	<?php settings_fields( 'ubc_rag_settings_group' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="rag_embedding_provider"><?php esc_html_e( 'Provider', 'ubc-rag' ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][provider]" id="rag_embedding_provider">
					<option value="" <?php selected( $current_provider, '' ); ?> disabled><?php esc_html_e( 'Choose Embedding Provider', 'ubc-rag' ); ?></option>
					<?php foreach ( $providers as $slug => $class ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_provider, $slug ); ?>>
							<?php echo esc_html( ucfirst( $slug ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the embedding provider to use.', 'ubc-rag' ); ?></p>
			</td>
		</tr>
	</table>

	<hr>

	<!-- Ollama Settings -->
	<div class="provider-settings" id="provider-settings-ollama" style="<?php echo 'ollama' === $current_provider ? '' : 'display:none;'; ?>">
		<h3><?php esc_html_e( 'Ollama Settings', 'ubc-rag' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rag_ollama_endpoint"><?php esc_html_e( 'Endpoint', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][endpoint]" type="text" id="rag_ollama_endpoint" value="<?php echo esc_attr( $settings['embedding']['ollama']['endpoint'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'The URL of your Ollama instance (e.g., http://localhost:11434).', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_ollama_api_key"><?php esc_html_e( 'API Key', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][api_key]" type="password" id="rag_ollama_api_key" value="<?php echo esc_attr( $settings['embedding']['ollama']['api_key'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Optional. API Key if your Ollama instance requires authentication.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_ollama_model"><?php esc_html_e( 'Model', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][model]" type="text" id="rag_ollama_model" value="<?php echo esc_attr( $settings['embedding']['ollama']['model'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'The model to use (e.g., nomic-embed-text).', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_ollama_dimensions"><?php esc_html_e( 'Dimensions', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][dimensions]" type="number" id="rag_ollama_dimensions" value="<?php echo esc_attr( $settings['embedding']['ollama']['dimensions'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'The dimension size of the model (e.g., 768).', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_ollama_context_window"><?php esc_html_e( 'Context Window', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][context_window]" type="number" id="rag_ollama_context_window" value="<?php echo esc_attr( isset( $settings['embedding']['ollama']['context_window'] ) ? $settings['embedding']['ollama']['context_window'] : 8192 ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'The maximum context window size (num_ctx). Increase this if you encounter EOF errors with large documents. Default: 8192.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_ollama_request_delay"><?php esc_html_e( 'Request Delay (seconds)', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][ollama][request_delay_seconds]" type="number" id="rag_ollama_request_delay" min="0.1" max="10" step="0.1" value="<?php echo esc_attr( isset( $settings['embedding']['ollama']['request_delay_seconds'] ) ? $settings['embedding']['ollama']['request_delay_seconds'] : 0.5 ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Delay between embedding requests (seconds). Increase if you see EOF errors - this gives Ollama time to free memory. Default: 0.5 seconds.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'ubc-rag' ); ?></th>
				<td>
					<button type="button" class="button button-secondary" id="test-ollama-connection"><?php esc_html_e( 'Test Connection', 'ubc-rag' ); ?></button>
					<span id="ollama-connection-result" style="margin-left: 10px;"></span>
				</td>
			</tr>
		</table>
	</div>

	<!-- MySQL Vector Embedding Settings -->
	<div class="provider-settings" id="provider-settings-mysql_vector" style="<?php echo 'mysql_vector' === $current_provider ? '' : 'display:none;'; ?>">
		<h3><?php esc_html_e( 'MySQL Vector Embedding Settings', 'ubc-rag' ); ?></h3>
		<p><?php esc_html_e( 'Uses the local mysql-vector library (BGE model). No API key required.', 'ubc-rag' ); ?></p>
		<p class="description"><?php esc_html_e( 'Note: This runs locally on your server. Ensure you have sufficient memory (approx 100MB+ per process).', 'ubc-rag' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'ubc-rag' ); ?></th>
				<td>
					<button type="button" class="button button-secondary" id="test-mysql-vector-connection"><?php esc_html_e( 'Test Generation', 'ubc-rag' ); ?></button>
					<span id="mysql-vector-connection-result" style="margin-left: 10px;"></span>
					<p class="description">
						<?php
						$ffi_loaded = extension_loaded( 'ffi' );
						$ffi_enable = ini_get( 'ffi.enable' );
						echo '<strong>FFI Status:</strong> ' . ( $ffi_loaded ? 'Loaded' : 'Not Loaded' ) . ' | <strong>ffi.enable:</strong> ' . esc_html( $ffi_enable );
						?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- OpenAI Settings -->
	<div class="provider-settings" id="provider-settings-openai" style="<?php echo 'openai' === $current_provider ? '' : 'display:none;'; ?>">
		<h3><?php esc_html_e( 'OpenAI Settings', 'ubc-rag' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rag_openai_api_key"><?php esc_html_e( 'API Key', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][openai][api_key]" type="password" id="rag_openai_api_key" value="<?php echo esc_attr( $settings['embedding']['openai']['api_key'] ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Your OpenAI API key. Get one at https://platform.openai.com/api-keys', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_openai_model"><?php esc_html_e( 'Model', 'ubc-rag' ); ?></label></th>
				<td>
					<select name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][openai][model]" id="rag_openai_model">
						<option value="text-embedding-3-small" <?php selected( $settings['embedding']['openai']['model'], 'text-embedding-3-small' ); ?>><?php esc_html_e( 'text-embedding-3-small (1536 dims, cheaper)', 'ubc-rag' ); ?></option>
						<option value="text-embedding-3-large" <?php selected( $settings['embedding']['openai']['model'], 'text-embedding-3-large' ); ?>><?php esc_html_e( 'text-embedding-3-large (3072 dims, better quality)', 'ubc-rag' ); ?></option>
						<option value="text-embedding-ada-002" <?php selected( $settings['embedding']['openai']['model'], 'text-embedding-ada-002' ); ?>><?php esc_html_e( 'text-embedding-ada-002 (1536 dims, legacy)', 'ubc-rag' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'The embedding model to use. Dimensions affect vector size and search quality.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_openai_dimensions"><?php esc_html_e( 'Dimensions', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][openai][dimensions]" type="number" id="rag_openai_dimensions" value="<?php echo esc_attr( $settings['embedding']['openai']['dimensions'] ); ?>" class="small-text" readonly>
					<p class="description"><?php esc_html_e( 'Auto-set based on selected model.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rag_openai_use_batch_api"><?php esc_html_e( 'Use Batch API', 'ubc-rag' ); ?></label></th>
				<td>
					<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[embedding][openai][use_batch_api]" type="checkbox" id="rag_openai_use_batch_api" value="1" <?php checked( isset( $settings['embedding']['openai']['use_batch_api'] ) ? $settings['embedding']['openai']['use_batch_api'] : 0 ); ?>>
					<label for="rag_openai_use_batch_api"><?php esc_html_e( 'Enable for bulk indexing (cheaper but slower)', 'ubc-rag' ); ?></label>
					<p class="description"><?php esc_html_e( 'Batch API costs 50% less but processes asynchronously (takes minutes/hours). Only recommended for large bulk operations.', 'ubc-rag' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'ubc-rag' ); ?></th>
				<td>
					<button type="button" class="button button-secondary" id="test-openai-connection"><?php esc_html_e( 'Test Connection', 'ubc-rag' ); ?></button>
					<span id="openai-connection-result" style="margin-left: 10px;"></span>
				</td>
			</tr>
		</table>
	</div>

	<?php submit_button(); ?>
</form>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const providerSelect = document.getElementById('rag_embedding_provider');
		const settingsDivs = document.querySelectorAll('.provider-settings');

		// Handle Provider Change
		providerSelect.addEventListener('change', function() {
			const selected = this.value;
			settingsDivs.forEach(div => {
				div.style.display = 'none';
			});
			const activeDiv = document.getElementById('provider-settings-' + selected);
			if (activeDiv) {
				activeDiv.style.display = 'block';
			}
		});

		// Handle Test Connection for Ollama
		const testOllamaBtn = document.getElementById('test-ollama-connection');
		if (testOllamaBtn) {
			testOllamaBtn.addEventListener('click', function() {
				const resultSpan = document.getElementById('ollama-connection-result');
				const endpoint = document.getElementById('rag_ollama_endpoint').value;
				const apiKey = document.getElementById('rag_ollama_api_key').value;
				const model = document.getElementById('rag_ollama_model').value;

				resultSpan.textContent = '<?php esc_html_e( 'Testing...', 'ubc-rag' ); ?>';
				resultSpan.style.color = '#666';

				const data = new FormData();
				data.append('action', 'ubc_rag_test_connection');
				data.append('provider', 'ollama');
				data.append('settings[endpoint]', endpoint);
				data.append('settings[api_key]', apiKey);
				data.append('settings[model]', model);
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

		// Handle Test Connection for OpenAI
		const testOpenAIBtn = document.getElementById('test-openai-connection');
		if (testOpenAIBtn) {
			testOpenAIBtn.addEventListener('click', function() {
				const resultSpan = document.getElementById('openai-connection-result');
				const apiKey = document.getElementById('rag_openai_api_key').value;
				const model = document.getElementById('rag_openai_model').value;

				resultSpan.textContent = '<?php esc_html_e( 'Testing...', 'ubc-rag' ); ?>';
				resultSpan.style.color = '#666';

				const data = new FormData();
				data.append('action', 'ubc_rag_test_connection');
				data.append('provider', 'openai');
				data.append('settings[api_key]', apiKey);
				data.append('settings[model]', model);
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

		// Handle Test Connection for MySQL Vector Embedding
		const testMysqlVectorBtn = document.getElementById('test-mysql-vector-connection');
		if (testMysqlVectorBtn) {
			testMysqlVectorBtn.addEventListener('click', function() {
				const resultSpan = document.getElementById('mysql-vector-connection-result');

				resultSpan.textContent = '<?php esc_html_e( 'Testing...', 'ubc-rag' ); ?>';
				resultSpan.style.color = '#666';

				const data = new FormData();
				data.append('action', 'ubc_rag_test_connection');
				data.append('provider', 'mysql_vector');
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

		// Update OpenAI dimensions based on model selection
		const openaiModelSelect = document.getElementById('rag_openai_model');
		const openaiDimensionsInput = document.getElementById('rag_openai_dimensions');
		if (openaiModelSelect && openaiDimensionsInput) {
			const dimensionsMap = {
				'text-embedding-3-small': 1536,
				'text-embedding-3-large': 3072,
				'text-embedding-ada-002': 1536
			};

			openaiModelSelect.addEventListener('change', function() {
				const selectedModel = this.value;
				openaiDimensionsInput.value = dimensionsMap[selectedModel] || 1536;
			});
		}
	});
</script>
