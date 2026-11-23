<?php
/**
 * Plugin Name:       Brew Log for Brewfather
 * Description:       Show your Brewfather recipes, batches and stock on your WordPress site.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            MythoTec
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       brew-log-for-brewfather
 * Domain Path:       /languages
 *
 * @package BrewLogForBrewfather
 */

defined( 'ABSPATH' ) || exit;

define( 'BLFB_VERSION', '0.1.0' );
define( 'BLFB_PLUGIN_FILE', __FILE__ );
define( 'BLFB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once BLFB_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'BrewLogForBrewfather\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BrewLogForBrewfather\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'BrewLogForBrewfather\Plugin', 'init' ) );
