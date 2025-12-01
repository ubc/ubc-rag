<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<h2 class="nav-tab-wrapper">
		<a href="?page=ubc-rag-settings&tab=dashboard" class="nav-tab <?php echo $active_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=content_types" class="nav-tab <?php echo $active_tab === 'content_types' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Content Types', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=chunking" class="nav-tab <?php echo $active_tab === 'chunking' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Chunking', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=embedding" class="nav-tab <?php echo $active_tab === 'embedding' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Embedding', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=storage" class="nav-tab <?php echo $active_tab === 'storage' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Storage', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=search" class="nav-tab <?php echo $active_tab === 'search' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Search', 'ubc-rag' ); ?></a>
		<a href="?page=ubc-rag-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Advanced', 'ubc-rag' ); ?></a>
	</h2>

	<div class="tab-content">
		<?php
		switch ( $active_tab ) {
			case 'dashboard':
				require_once plugin_dir_path( __FILE__ ) . 'dashboard-tab.php';
				break;
			case 'content_types':
				require_once plugin_dir_path( __FILE__ ) . 'content-types-tab.php';
				break;
			case 'chunking':
				require_once plugin_dir_path( __FILE__ ) . 'chunking-tab.php';
				break;
			case 'embedding':
				require_once plugin_dir_path( __FILE__ ) . 'embedding-tab.php';
				break;
			case 'storage':
				require_once plugin_dir_path( __FILE__ ) . 'storage-tab.php';
				break;
			case 'search':
				require_once plugin_dir_path( __FILE__ ) . 'search-tab.php';
				break;
			case 'advanced':
				require_once plugin_dir_path( __FILE__ ) . 'advanced-tab.php';
				break;
			default:
				echo '<p>' . esc_html__( 'Invalid tab.', 'ubc-rag' ) . '</p>';
				break;
		}
		?>
	</div>
</div>
