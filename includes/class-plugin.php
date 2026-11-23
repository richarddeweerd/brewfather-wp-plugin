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
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( Access::class, 'translate_role_name' ), 20 );

		// Activation hooks don't run when the plugin is updated in place, so
		// re-run the installer whenever the stored version differs.
		if ( get_option( self::VERSION_OPTION ) !== BLFB_VERSION ) {
			self::activate();
		}

		if ( is_admin() ) {
			Settings::init();
		}

		Frontend::init();
	}

	/**
	 * Loads the bundled translations from /languages.
	 *
	 * Language packs from translate.wordpress.org (in wp-content/languages)
	 * take precedence; the bundled files are the fallback. Runs on `init`
	 * because WordPress 6.7+ warns about loading translations earlier.
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'brew-log-for-brewfather',
			false,
			dirname( plugin_basename( BLFB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Runs when the plugin is activated.
	 */
	public static function activate() {
		Access::install();
		update_option( self::VERSION_OPTION, BLFB_VERSION, false );
	}

	/**
	 * Runs when the plugin is deactivated. Data is kept; see uninstall.php.
	 */
	public static function deactivate() {
		// Nothing to clean up yet.
	}
}
