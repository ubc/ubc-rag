<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a href="?page=ubc-rag-settings&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=content_types" class="nav-tab <?php echo $active_tab === 'content_types' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Content Types', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=chunking" class="nav-tab <?php echo $active_tab === 'chunking' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Chunking', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=embedding" class="nav-tab <?php echo $active_tab === 'embedding' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Embedding', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=storage" class="nav-tab <?php echo $active_tab === 'storage' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Storage', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Advanced', 'ubc-rag' ); ?></a>
	</h2>

	<div class="tab-content">
		<?php
		switch ( $active_tab ) {
			case 'dashboard':
				echo '<p>' . esc_html__( 'Dashboard content coming soon.', 'ubc-rag' ) . '</p>';
				break;
			case 'content_types':
				echo '<p>' . esc_html__( 'Content Types settings coming soon.', 'ubc-rag' ) . '</p>';
				break;
			case 'chunking':
				echo '<p>' . esc_html__( 'Chunking settings coming soon.', 'ubc-rag' ) . '</p>';
				break;
			case 'embedding':
				echo '<p>' . esc_html__( 'Embedding settings coming soon.', 'ubc-rag' ) . '</p>';
				break;
			case 'storage':
				echo '<p>' . esc_html__( 'Storage settings coming soon.', 'ubc-rag' ) . '</p>';
				break;
			case 'advanced':
				echo '<p>' . esc_html__( 'Advanced settings coming soon.', 'ubc-rag' ) . '</p>';
				break;
			default:
				echo '<p>' . esc_html__( 'Invalid tab.', 'ubc-rag' ) . '</p>';
				break;
		}
		?>
	</div>
</div>
