<?php
/**
 * Chunking Tab View.
 */

$settings = \UBC\RAG\Settings::get_settings();
$post_types = get_post_types( [ 'public' => true ], 'objects' );
if ( ! isset( $post_types['attachment'] ) ) {
	$post_types['attachment'] = get_post_type_object( 'attachment' );
}

$strategies = [
	'paragraph' => __( 'Paragraph (Best for general text)', 'ubc-rag' ),
	'sentence'  => __( 'Sentence (Granular)', 'ubc-rag' ),
	'word'      => __( 'Word Count (Fixed size)', 'ubc-rag' ),
	'character' => __( 'Character Count (Precise size)', 'ubc-rag' ),
	'page'      => __( 'Page/Slide (For PDF/PPTX - Falls back to Paragraph for DOCX)', 'ubc-rag' ),
];
?>

<form method="post" action="options.php">
	<?php settings_fields( 'ubc_rag_settings_group' ); ?>
	
	<p><?php esc_html_e( 'Configure how content is split into chunks for each content type.', 'ubc-rag' ); ?></p>

	<?php foreach ( $post_types as $slug => $pt ) : ?>
		<?php 
		if ( ! $pt ) continue;
		// Only show if enabled in Content Types (optional UX choice, but let's show all for now)
		$config = isset( $settings['content_types'][ $slug ] ) ? $settings['content_types'][ $slug ] : [];
		$strategy = isset( $config['chunking_strategy'] ) ? $config['chunking_strategy'] : 'paragraph';
		$chunk_size = isset( $config['chunking_settings']['chunk_size'] ) ? $config['chunking_settings']['chunk_size'] : 3;
		$overlap = isset( $config['chunking_settings']['overlap'] ) ? $config['chunking_settings']['overlap'] : 0;
		?>
		<div class="card" style="margin-bottom: 20px;">
			<h3><?php echo esc_html( $pt->labels->name ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="chunking_strategy_<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Strategy', 'ubc-rag' ); ?></label></th>
					<td>
						<select name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[content_types][<?php echo esc_attr( $slug ); ?>][chunking_strategy]" id="chunking_strategy_<?php echo esc_attr( $slug ); ?>">
							<?php foreach ( $strategies as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $strategy, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="chunk_size_<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Chunk Size', 'ubc-rag' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[content_types][<?php echo esc_attr( $slug ); ?>][chunking_settings][chunk_size]" type="number" id="chunk_size_<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $chunk_size ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Number of paragraphs, sentences, words, or characters depending on strategy.', 'ubc-rag' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="overlap_<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Overlap', 'ubc-rag' ); ?></label></th>
					<td>
						<input name="<?php echo esc_attr( \UBC\RAG\Settings::OPTION_KEY ); ?>[content_types][<?php echo esc_attr( $slug ); ?>][chunking_settings][overlap]" type="number" id="overlap_<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $overlap ); ?>" class="small-text">
						<p class="description"><?php esc_html_e( 'Amount of overlap between chunks (words/characters only).', 'ubc-rag' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	<?php endforeach; ?>

	<?php submit_button(); ?>
</form>
