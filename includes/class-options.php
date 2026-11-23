<?php
/**
 * Names of the options the plugin stores.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Option names and defaults, shared by the settings page and the API client.
 */
final class Options {

	/**
	 * Brewfather user ID.
	 */
	const USER_ID = 'blfb_user_id';

	/**
	 * Brewfather API key.
	 */
	const API_KEY = 'blfb_api_key';

	/**
	 * Cache timeout in minutes.
	 */
	const CACHE_TIMEOUT = 'blfb_cache_timeout';

	/**
	 * Default cache timeout in minutes.
	 */
	const DEFAULT_CACHE_MINUTES = 60;

	/**
	 * Hide Recipe Ingredients from Visitors (bool).
	 */
	const HIDE_INGREDIENTS_VISITORS = 'blfb_hide_ingredients_visitors';

	/**
	 * Hide Recipe Ingredients from Users, and therefore also Visitors (bool).
	 */
	const HIDE_INGREDIENTS_USERS = 'blfb_hide_ingredients_users';
}
