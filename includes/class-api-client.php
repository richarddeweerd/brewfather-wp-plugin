<?php
/**
 * Read-only client for the Brewfather API v2.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads recipes, batches and inventory from Brewfather, with caching.
 *
 * Every public method returns the decoded data, or a WP_Error whose message
 * can be shown to site visitors as is. All responses are cached (see Cache),
 * because Brewfather allows only about 500 calls per hour per API key.
 *
 * @link https://docs.brewfather.app/api
 */
class Api_Client {

	/**
	 * Base URL of the Brewfather API.
	 */
	const BASE_URL = 'https://api.brewfather.app/v2/';

	/**
	 * Items per page; the API's maximum.
	 */
	const PAGE_SIZE = 50;

	/**
	 * Safety limit on pages fetched for one list (1000 items).
	 */
	const MAX_PAGES = 20;

	/**
	 * Transient set while Brewfather is rate limiting us.
	 */
	const RATE_LIMIT_TRANSIENT = 'blfb_rate_limited';

	/**
	 * Inventory types the API knows.
	 */
	const INVENTORY_TYPES = array( 'fermentables', 'hops', 'miscs', 'yeasts' );

	/**
	 * Brewfather user ID.
	 *
	 * @var string
	 */
	private $user_id;

	/**
	 * Brewfather API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Response cache.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param string $user_id Brewfather user ID.
	 * @param string $api_key Brewfather API key.
	 * @param Cache  $cache   Response cache.
	 */
	public function __construct( $user_id, $api_key, Cache $cache ) {
		$this->user_id = trim( (string) $user_id );
		$this->api_key = trim( (string) $api_key );
		$this->cache   = $cache;
	}

	/**
	 * Creates a client using the credentials saved in the plugin settings.
	 *
	 * @return self
	 */
	public static function from_settings() {
		return new self(
			get_option( Options::USER_ID, '' ),
			get_option( Options::API_KEY, '' ),
			new Cache()
		);
	}

	/**
	 * Whether credentials have been entered.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->user_id && '' !== $this->api_key;
	}

	/**
	 * All recipes.
	 *
	 * @param string[] $fields  Extra fields to include, e.g. array( 'abv', 'ibu' ).
	 * @return array|WP_Error
	 */
	public function get_recipes( array $fields = array() ) {
		return $this->get_list( 'recipes', $this->include_query( $fields ) );
	}

	/**
	 * One recipe with all its fields.
	 *
	 * @param string $id Recipe ID.
	 * @return array|WP_Error
	 */
	public function get_recipe( $id ) {
		return $this->get_one( 'recipes/' . rawurlencode( $id ) );
	}

	/**
	 * All batches, optionally filtered by status.
	 *
	 * @param string[] $fields  Extra fields to include, e.g. array( 'recipe._id' ).
	 * @param string   $status  Brewfather status, e.g. 'Fermenting'. Empty for all.
	 * @return array|WP_Error
	 */
	public function get_batches( array $fields = array(), $status = '' ) {
		$query = $this->include_query( $fields );
		if ( '' !== $status ) {
			$query['status'] = $status;
		}

		return $this->get_list( 'batches', $query );
	}

	/**
	 * One batch with all its fields.
	 *
	 * @param string $id Batch ID.
	 * @return array|WP_Error
	 */
	public function get_batch( $id ) {
		return $this->get_one( 'batches/' . rawurlencode( $id ) );
	}

	/**
	 * Inventory items of one type.
	 *
	 * @param string   $type    One of INVENTORY_TYPES.
	 * @param string[] $fields  Extra fields to include.
	 * @return array|WP_Error
	 */
	public function get_inventory( $type, array $fields = array() ) {
		if ( ! in_array( $type, self::INVENTORY_TYPES, true ) ) {
			return $this->error( 'blfb_not_found' );
		}

		return $this->get_list( 'inventory/' . $type, $this->include_query( $fields ) );
	}

	/**
	 * Checks the credentials with one small, uncached request.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return $this->error( 'blfb_not_configured' );
		}

		$result = $this->request( 'recipes', array( 'limit' => 1 ) );

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Fetches every page of a list endpoint.
	 *
	 * @param string $path  Path below BASE_URL.
	 * @param array  $query Query arguments.
	 * @return array|WP_Error
	 */
	private function get_list( $path, array $query ) {
		return $this->cached(
			$path . '?' . http_build_query( $query ),
			function () use ( $path, $query ) {
				$items = array();
				$query = array_merge( $query, array( 'limit' => self::PAGE_SIZE ) );

				for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
					$page_items = $this->request( $path, $query );
					if ( is_wp_error( $page_items ) ) {
						return $page_items;
					}

					$items = array_merge( $items, $page_items );

					$last = end( $page_items );
					if ( count( $page_items ) < self::PAGE_SIZE || empty( $last['_id'] ) ) {
						break;
					}
					$query['start_after'] = $last['_id'];
				}

				return $items;
			}
		);
	}

	/**
	 * Fetches a single item.
	 *
	 * @param string $path Path below BASE_URL.
	 * @return array|WP_Error
	 */
	private function get_one( $path ) {
		return $this->cached(
			$path,
			function () use ( $path ) {
				return $this->request( $path, array() );
			}
		);
	}

	/**
	 * Returns the cached value for $key, or loads, caches and returns it.
	 *
	 * Falls back to the stale copy when loading fails, except for "not found".
	 *
	 * @param string   $key    Cache key.
	 * @param callable $loader Returns the value or a WP_Error.
	 * @return array|WP_Error
	 */
	private function cached( $key, callable $loader ) {
		if ( ! $this->is_configured() ) {
			return $this->error( 'blfb_not_configured' );
		}

		$value = $this->cache->get( $key );
		if ( null !== $value ) {
			return $value;
		}

		if ( get_transient( self::RATE_LIMIT_TRANSIENT ) ) {
			$value = $this->error( 'blfb_rate_limited' );
		} else {
			$value = $loader();
		}

		if ( ! is_wp_error( $value ) ) {
			$this->cache->set( $key, $value );
			return $value;
		}

		if ( 'blfb_not_found' !== $value->get_error_code() ) {
			$stale = $this->cache->get_stale( $key );
			if ( null !== $stale ) {
				return $stale;
			}
		}

		return $value;
	}

	/**
	 * Makes one GET request and decodes the JSON response.
	 *
	 * @param string $path  Path below BASE_URL.
	 * @param array  $query Query arguments.
	 * @return array|WP_Error
	 */
	private function request( $path, array $query ) {
		$url = self::BASE_URL . $path;
		if ( $query ) {
			$url .= '?' . http_build_query( $query );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth, required by the Brewfather API.
					'Authorization' => 'Basic ' . base64_encode( $this->user_id . ':' . $this->api_key ),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->error( 'blfb_unreachable', $response );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			return is_array( $data ) ? $data : $this->error( 'blfb_unreachable' );
		}

		if ( 401 === $code || 403 === $code ) {
			return $this->error( 'blfb_unauthorized' );
		}

		if ( 404 === $code ) {
			return $this->error( 'blfb_not_found' );
		}

		if ( 429 === $code ) {
			$retry_after = absint( wp_remote_retrieve_header( $response, 'retry-after' ) );
			set_transient( self::RATE_LIMIT_TRANSIENT, 1, max( MINUTE_IN_SECONDS, $retry_after ) );
			return $this->error( 'blfb_rate_limited' );
		}

		return $this->error( 'blfb_unreachable', array( 'status' => $code ) );
	}

	/**
	 * Builds the `include` query argument.
	 *
	 * @param string[] $fields  Field names.
	 * @return array
	 */
	private function include_query( array $fields ) {
		return $fields ? array( 'include' => implode( ',', $fields ) ) : array();
	}

	/**
	 * Creates an error with a message that can be shown to site visitors.
	 *
	 * @param string $code Error code.
	 * @param mixed  $data Extra data for debugging.
	 * @return WP_Error
	 */
	private function error( $code, $data = null ) {
		return new WP_Error( $code, self::error_message( $code ), $data );
	}

	/**
	 * The visitor-friendly message for one of the client's error codes.
	 *
	 * @param string $code Error code, e.g. 'blfb_unauthorized'.
	 * @return string Empty for unknown codes.
	 */
	public static function error_message( $code ) {
		$messages = array(
			'blfb_not_configured' => __( 'Brewfather is not set up yet. An administrator needs to enter the API credentials.', 'brew-log-for-brewfather' ),
			'blfb_unauthorized'   => __( 'Brewfather rejected the API credentials. An administrator needs to check them.', 'brew-log-for-brewfather' ),
			'blfb_not_found'      => __( 'This item could not be found in Brewfather.', 'brew-log-for-brewfather' ),
			'blfb_rate_limited'   => __( 'Brewfather is busy right now. Please try again later.', 'brew-log-for-brewfather' ),
			'blfb_unreachable'    => __( 'Brewfather could not be reached. Please try again later.', 'brew-log-for-brewfather' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}
}
