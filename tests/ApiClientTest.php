<?php

use BrewLogForBrewfather\Api_Client;
use BrewLogForBrewfather\Cache;
use BrewLogForBrewfather\Options;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase {

	protected function setUp(): void {
		reset_wp();
	}

	private function client() {
		return new Api_Client( 'user', 'key', new Cache() );
	}

	private function requests() {
		return $GLOBALS['blfb_test']['requests'];
	}

	private function items( $count, $prefix = 'id' ) {
		$items = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$items[] = array( '_id' => $prefix . $i, 'name' => "Item $i" );
		}
		return $items;
	}

	/**
	 * Removes the fresh copies from the cache, as if the timeout had passed.
	 */
	private function expire_fresh_cache() {
		foreach ( array_keys( $GLOBALS['blfb_test']['transients'] ) as $name ) {
			if ( 0 === strpos( $name, 'blfb_fresh_' ) ) {
				unset( $GLOBALS['blfb_test']['transients'][ $name ] );
			}
		}
	}

	public function test_without_credentials_returns_error_and_makes_no_request() {
		$result = ( new Api_Client( '', 'key', new Cache() ) )->get_recipes();

		$this->assertSame( 'blfb_not_configured', $result->get_error_code() );
		$this->assertCount( 0, $this->requests() );
	}

	public function test_from_settings_reads_saved_credentials() {
		update_option( Options::USER_ID, 'saved-user' );
		update_option( Options::API_KEY, 'saved-key' );
		queue_response( 200, array() );

		Api_Client::from_settings()->get_recipes();

		$auth = $this->requests()[0][1]['headers']['Authorization'];
		$this->assertSame( 'Basic ' . base64_encode( 'saved-user:saved-key' ), $auth );
	}

	public function test_sends_basic_auth_and_include() {
		queue_response( 200, array() );

		$this->client()->get_recipes( array( 'abv', 'ibu' ) );

		list( $url, $args ) = $this->requests()[0];
		$this->assertSame( 'https://api.brewfather.app/v2/recipes?include=abv%2Cibu&limit=50', $url );
		$this->assertSame( 'Basic ' . base64_encode( 'user:key' ), $args['headers']['Authorization'] );
	}

	public function test_fetches_every_page() {
		queue_response( 200, $this->items( 50 ) );
		queue_response( 200, $this->items( 3, 'next' ) );

		$result = $this->client()->get_batches();

		$this->assertCount( 53, $result );
		$this->assertCount( 2, $this->requests() );
		$this->assertStringContainsString( 'start_after=id49', $this->requests()[1][0] );
	}

	public function test_passes_status_filter() {
		queue_response( 200, array() );

		$this->client()->get_batches( array(), 'Fermenting' );

		$this->assertStringContainsString( 'status=Fermenting', $this->requests()[0][0] );
	}

	public function test_second_call_is_served_from_cache() {
		queue_response( 200, $this->items( 2 ) );

		$first  = $this->client()->get_recipes();
		$second = $this->client()->get_recipes();

		$this->assertSame( $first, $second );
		$this->assertCount( 1, $this->requests() );
	}

	public function test_different_arguments_are_cached_separately() {
		queue_response( 200, $this->items( 1 ) );
		queue_response( 200, $this->items( 2 ) );

		$this->client()->get_batches( array(), 'Fermenting' );
		$this->client()->get_batches( array(), 'Completed' );

		$this->assertCount( 2, $this->requests() );
	}

	public function test_clearing_the_cache_fetches_again() {
		queue_response( 200, $this->items( 1 ) );
		queue_response( 200, $this->items( 2 ) );

		$this->client()->get_recipes();
		( new Cache() )->clear();
		$result = $this->client()->get_recipes();

		$this->assertCount( 2, $result );
		$this->assertCount( 2, $this->requests() );
	}

	public function test_cache_uses_configured_timeout() {
		update_option( Options::CACHE_TIMEOUT, 15 );
		queue_response( 200, array() );

		$this->client()->get_recipes();

		$fresh = array_filter(
			$GLOBALS['blfb_test']['transients'],
			function ( $name ) {
				return 0 === strpos( $name, 'blfb_fresh_' );
			},
			ARRAY_FILTER_USE_KEY
		);
		$this->assertSame( 900, array_values( $fresh )[0][1] );
	}

	public function test_cache_timeout_defaults_to_an_hour_and_is_at_least_a_minute() {
		$this->assertSame( 3600, ( new Cache() )->ttl() );

		update_option( Options::CACHE_TIMEOUT, 0 );
		$this->assertSame( 60, ( new Cache() )->ttl() );
	}

	public function test_rejected_credentials() {
		queue_response( 401 );

		$result = $this->client()->get_recipes();

		$this->assertSame( 'blfb_unauthorized', $result->get_error_code() );
		$this->assertNotSame( '', $result->get_error_message() );
	}

	public function test_missing_item() {
		queue_response( 404 );

		$result = $this->client()->get_batch( 'abc' );

		$this->assertSame( 'blfb_not_found', $result->get_error_code() );
		$this->assertSame( 'https://api.brewfather.app/v2/batches/abc', $this->requests()[0][0] );
	}

	public function test_rate_limit_pauses_requests_for_retry_after() {
		queue_response( 429, null, array( 'Retry-After' => '120' ) );

		$first  = $this->client()->get_recipes();
		$second = $this->client()->get_batches();

		$this->assertSame( 'blfb_rate_limited', $first->get_error_code() );
		$this->assertSame( 'blfb_rate_limited', $second->get_error_code() );
		$this->assertCount( 1, $this->requests() );
		$this->assertSame( 120, $GLOBALS['blfb_test']['transients'][ Api_Client::RATE_LIMIT_TRANSIENT ][1] );
	}

	public function test_serves_stale_copy_when_brewfather_fails() {
		queue_response( 200, $this->items( 2 ) );
		$this->client()->get_recipes();
		$this->expire_fresh_cache();

		queue_response( 500 );
		$result = $this->client()->get_recipes();

		$this->assertCount( 2, $result );
	}

	public function test_serves_stale_copy_while_rate_limited() {
		queue_response( 200, $this->items( 2 ) );
		$this->client()->get_recipes();
		$this->expire_fresh_cache();
		set_transient( Api_Client::RATE_LIMIT_TRANSIENT, 1, 60 );

		$result = $this->client()->get_recipes();

		$this->assertCount( 2, $result );
		$this->assertCount( 1, $this->requests() );
	}

	public function test_network_failure() {
		$GLOBALS['blfb_test']['responses'][] = new WP_Error( 'http_request_failed', 'timeout' );

		$result = $this->client()->get_recipes();

		$this->assertSame( 'blfb_unreachable', $result->get_error_code() );
	}

	public function test_inventory_uses_type_path_and_rejects_unknown_types() {
		queue_response( 200, array() );

		$this->client()->get_inventory( 'hops' );
		$unknown = $this->client()->get_inventory( 'barrels' );

		$this->assertStringStartsWith( 'https://api.brewfather.app/v2/inventory/hops?', $this->requests()[0][0] );
		$this->assertSame( 'blfb_not_found', $unknown->get_error_code() );
		$this->assertCount( 1, $this->requests() );
	}
}
