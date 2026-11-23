<?php
/**
 * Removes all plugin data when the plugin is deleted.
 *
 * @package BrewLogForBrewfather
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'blfb_version' );
