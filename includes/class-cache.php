<?php
/**
 * Cache for Brewfather API responses.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * Stores API responses in transients.
 *
 * Every entry is written twice: a fresh copy that expires after the configured
 * cache timeout, and a stale copy kept for a week. The stale copy is served when
 * Brewfather can't be reached or is rate limiting us.
 *
 * Clearing bumps a generation number that is part of every transient name, so
 * all entries are invalidated at once without having to find them.
 */
class Cache {

	/**
	 * Option holding the current cache generation.
	 */
	const GENERATION_OPTION = 'blfb_cache_generation';

	/**
	 * How long the stale copy is kept, in seconds.
	 */
	const STALE_TTL = WEEK_IN_SECONDS;

	/**
	 * Returns the fresh cached value, or null if there is none.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		return $this->read( $this->name( $key, 'fresh' ) );
	}

	/**
	 * Returns the stale cached value, or null if there is none.
	 *
	 * @param string $key Cache key.
	 * @return mixed|null
	 */
	public function get_stale( $key ) {
		return $this->read( $this->name( $key, 'stale' ) );
	}

	/**
	 * Stores a value. Values must not be false (transients use false for "missing").
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 */
	public function set( $key, $value ) {
		set_transient( $this->name( $key, 'fresh' ), $value, $this->ttl() );
		set_transient( $this->name( $key, 'stale' ), $value, self::STALE_TTL );
	}

	/**
	 * Invalidates every cached entry, fresh and stale.
	 */
	public function clear() {
		update_option( self::GENERATION_OPTION, $this->generation() + 1, false );
	}

	/**
	 * The configured cache timeout in seconds. At least one minute.
	 *
	 * @return int
	 */
	public function ttl() {
		$minutes = absint( get_option( Options::CACHE_TIMEOUT, Options::DEFAULT_CACHE_MINUTES ) );

		return max( 1, $minutes ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Reads a transient, mapping "missing" to null.
	 *
	 * @param string $name Transient name.
	 * @return mixed|null
	 */
	private function read( $name ) {
		$value = get_transient( $name );

		return false === $value ? null : $value;
	}

	/**
	 * Builds the transient name for a key.
	 *
	 * @param string $key  Cache key.
	 * @param string $kind Either 'fresh' or 'stale'.
	 * @return string
	 */
	private function name( $key, $kind ) {
		return 'blfb_' . $kind . '_' . $this->generation() . '_' . md5( $key );
	}

	/**
	 * The current cache generation.
	 *
	 * @return int
	 */
	private function generation() {
		return (int) get_option( self::GENERATION_OPTION, 0 );
	}
}
