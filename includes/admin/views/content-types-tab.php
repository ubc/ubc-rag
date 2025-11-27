<?php
/**
 * Content Types Tab View.
 */

$settings = \UBC\RAG\Settings::get_settings();
$post_types = get_post_types( [ 'public' => true ], 'objects' );
// Add attachment if not present (it's usually not public in the same way)
if ( ! isset( $post_types['attachment'] ) ) {
	$post_types['attachment'] = get_post_type_object( 'attachment' );
}
?>

<form method="post" action="options.php">
	<?php settings_fields( 'ubc_rag_settings_group' ); ?>
	
	<p><?php esc_html_e( 'Select which content types should be indexed.', 'ubc-rag' ); ?></p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Content Type', 'ubc-rag' ); ?></th>
				<th><?php esc_html_e( 'Enable Indexing', 'ubc-rag' ); ?></th>
				<th><?php esc_html_e( 'Auto-Index New Content', 'ubc-rag' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $post_types as $slug => $pt ) : ?>
				<?php 
				if ( ! $pt ) continue;
				$enabled = isset( $settings['content_types'][ $slug ]['enabled'] ) ? $settings['content_types'][ $slug ]['enabled'] : false;
				$auto_index = isset( $settings['content_types'][ $slug ]['auto_index'] ) ? $settings['content_types'][ $slug ]['auto_index'] : false;
				?>
				<tr>
					<td><?php echo esc_html( $pt->labels->name ); ?> (<code><?php echo esc_html( $slug ); ?></code>)</td>
					<td>
						<label class="switch">
							<input type="checkbox" name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[content_types][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $enabled ); ?>>
							<span class="slider round"></span>
						</label>
					</td>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[content_types][<?php echo esc_attr( $slug ); ?>][auto_index]" value="1" <?php checked( $auto_index ); ?>>
							<?php esc_html_e( 'Automatically index when published/updated', 'ubc-rag' ); ?>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php submit_button(); ?>
</form>
