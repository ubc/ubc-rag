<?php

namespace UBC\RAG\Admin;

use UBC\RAG\Settings;

/**
 * Admin Menu class.
 */
class Admin_Menu {

	/**
	 * Add menu item.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'RAG Indexing', 'ubc-rag' ),
			__( 'RAG Indexing', 'ubc-rag' ),
			'manage_options',
			'ubc-rag-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'ubc_rag_settings_group', Settings::OPTION_KEY );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// Get current tab.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';

		// Load view.
		require_once UBC_RAG_PATH . 'includes/admin/views/settings-page.php';
	}
}
