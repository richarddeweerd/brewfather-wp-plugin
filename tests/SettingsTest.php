<?php

use BrewLogForBrewfather\Api_Client;
use BrewLogForBrewfather\Cache;
use BrewLogForBrewfather\Options;
use BrewLogForBrewfather\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

	protected function setUp(): void {
		reset_wp();
	}

	public function test_empty_api_key_keeps_the_saved_one() {
		update_option( Options::API_KEY, 'saved-key' );

		$this->assertSame( 'saved-key', Settings::sanitize_api_key( '' ) );
		$this->assertSame( 'saved-key', Settings::sanitize_api_key( '   ' ) );
	}

	public function test_new_api_key_replaces_the_saved_one() {
		update_option( Options::API_KEY, 'saved-key' );

		$this->assertSame( 'new-key', Settings::sanitize_api_key( ' new-key ' ) );
	}

	public function test_user_id_is_trimmed() {
		$this->assertSame( 'abc123', Settings::sanitize_user_id( "  abc123\n" ) );
	}

	public function test_changing_credentials_clears_the_cache() {
		update_option( Options::USER_ID, 'user' );
		update_option( Options::API_KEY, 'key' );
		queue_response( 200, array( array( '_id' => 'a' ) ) );
		queue_response( 200, array() );

		Api_Client::from_settings()->get_recipes();
		Settings::clear_cache();
		Api_Client::from_settings()->get_recipes();

		$this->assertCount( 2, $GLOBALS['blfb_test']['requests'] );
	}

	public function test_connection_test_succeeds_with_one_uncached_request() {
		queue_response( 200, array() );
		queue_response( 200, array() );
		$client = new Api_Client( 'user', 'key', new Cache() );

		$this->assertTrue( $client->test_connection() );
		$this->assertTrue( $client->test_connection() );
		$this->assertCount( 2, $GLOBALS['blfb_test']['requests'] );
		$this->assertStringEndsWith( 'recipes?limit=1', $GLOBALS['blfb_test']['requests'][0][0] );
	}

	public function test_connection_test_reports_rejected_credentials() {
		queue_response( 401 );

		$result = ( new Api_Client( 'user', 'bad', new Cache() ) )->test_connection();

		$this->assertSame( 'blfb_unauthorized', $result->get_error_code() );
	}

	public function test_connection_test_without_credentials_makes_no_request() {
		$result = ( new Api_Client( '', '', new Cache() ) )->test_connection();

		$this->assertSame( 'blfb_not_configured', $result->get_error_code() );
		$this->assertCount( 0, $GLOBALS['blfb_test']['requests'] );
	}

	public function test_error_message_for_unknown_code_is_empty() {
		$this->assertSame( '', Api_Client::error_message( 'something_else' ) );
		$this->assertNotSame( '', Api_Client::error_message( 'blfb_unauthorized' ) );
	}

	public function test_cache_timeout_is_whole_minutes_and_at_least_one() {
		$this->assertSame( 15, Settings::sanitize_cache_timeout( '15' ) );
		$this->assertSame( 1, Settings::sanitize_cache_timeout( '0' ) );
		$this->assertSame( 5, Settings::sanitize_cache_timeout( '-5' ) );
		$this->assertSame( 60, Settings::sanitize_cache_timeout( '' ) );
	}
}
