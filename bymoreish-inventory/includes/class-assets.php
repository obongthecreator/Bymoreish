<?php
/**
 * Asset management class for Bymoreish Inventory.
 *
 * Handles enqueuing of plugin styles and scripts via the WordPress
 * wp_enqueue_scripts hook. Loaded by the main plugin file when present.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bymoreish_Assets {

	/**
	 * Enqueue the plugin-specific CSS and JavaScript assets.
	 *
	 * Called from `bymoreish_enqueue_assets()` in the main plugin file
	 * only when the current request is for a Bymoreish page.
	 */
	public static function enqueue(): void {
		$plugin_url = BYMOREISH_PLUGIN_URL;
		$version    = BYMOREISH_VERSION;

		// Core plugin stylesheet.
		wp_enqueue_style(
			'bymoreish-style',
			$plugin_url . 'assets/css/style.css',
			[],
			$version
		);

		// Core plugin JavaScript.
		wp_enqueue_script(
			'bymoreish-main',
			$plugin_url . 'assets/js/main.js',
			[],
			$version,
			true
		);

		// Pass config to JS.
		$nonce = '';
		if ( ! session_id() ) {
			session_start();
		}
		if ( ! empty( $_SESSION['bym_nonce'] ) ) {
			$nonce = (string) $_SESSION['bym_nonce'];
		}

		wp_localize_script(
			'bymoreish-main',
			'bymConfig',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => $nonce,
				'pluginUrl' => $plugin_url,
			]
		);
	}
}
