<?php
/**
 * Dashboard Tab View.
 */

$stats = \UBC\RAG\Status::get_statistics();
?>

<div class="rag-dashboard">
	<div class="card">
		<h2><?php esc_html_e( 'System Status', 'ubc-rag' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total Items Tracked', 'ubc-rag' ); ?></td>
					<td><?php echo esc_html( $stats['total'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Successfully Indexed', 'ubc-rag' ); ?></td>
					<td style="color: green; font-weight: bold;"><?php echo esc_html( $stats['indexed'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Processing / Queued', 'ubc-rag' ); ?></td>
					<td style="color: orange; font-weight: bold;"><?php echo esc_html( $stats['processing'] + $stats['queued'] ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Failed', 'ubc-rag' ); ?></td>
					<td style="color: red; font-weight: bold;"><?php echo esc_html( $stats['failed'] ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="card" style="margin-top: 20px;">
		<h2><?php esc_html_e( 'Quick Actions', 'ubc-rag' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View Job Queue', 'ubc-rag' ); ?>
			</a>
		</p>
	</div>
</div>
