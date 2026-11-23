<?php
/**
 * Plugin bootstrap and lifecycle hooks.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Option that stores the installed plugin version.
	 */
	const VERSION_OPTION = 'blfb_version';

	/**
	 * Runs on every request once all plugins are loaded.
	 */
	public static function init() {
		// Feature modules (settings, API client, shortcodes) register here.
	}

	/**
	 * Runs when the plugin is activated.
	 */
	public static function activate() {
		update_option( self::VERSION_OPTION, BLFB_VERSION, false );
	}

	/**
	 * Runs when the plugin is deactivated. Data is kept; see uninstall.php.
	 */
	public static function deactivate() {
		// Nothing to clean up yet.
	}
}
