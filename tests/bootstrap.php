<?php
/**
 * Minimal in-memory stand-ins for the WordPress functions the plugin uses,
 * so unit tests run without a WordPress install.
 *
 * Tests control and inspect state through $GLOBALS['blfb_test'] (see reset_wp()).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );

require dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Resets all fake WordPress state.
 */
function reset_wp() {
	$GLOBALS['blfb_test'] = array(
		'options'    => array(),
		'transients' => array(), // name => array( value, expiration ).
		'responses'  => array(), // Queue of fake HTTP responses or WP_Errors.
		'requests'   => array(), // Recorded array( url, args ).
	);
}
reset_wp();

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['blfb_test']['options'] ) ? $GLOBALS['blfb_test']['options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['blfb_test']['options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['blfb_test']['options'][ $name ] );
	return true;
}

function get_transient( $name ) {
	return isset( $GLOBALS['blfb_test']['transients'][ $name ] ) ? $GLOBALS['blfb_test']['transients'][ $name ][0] : false;
}

function set_transient( $name, $value, $expiration = 0 ) {
	$GLOBALS['blfb_test']['transients'][ $name ] = array( $value, $expiration );
	return true;
}

function delete_transient( $name ) {
	unset( $GLOBALS['blfb_test']['transients'][ $name ] );
	return true;
}

/**
 * Queues a fake HTTP response for the next wp_remote_get().
 */
function queue_response( $code, $body = null, array $headers = array() ) {
	$GLOBALS['blfb_test']['responses'][] = array(
		'code'    => $code,
		'body'    => null === $body ? '' : json_encode( $body ),
		'headers' => array_change_key_case( $headers ),
	);
}

function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['blfb_test']['requests'][] = array( $url, $args );
	if ( ! $GLOBALS['blfb_test']['responses'] ) {
		throw new RuntimeException( "Unexpected request to $url" );
	}
	return array_shift( $GLOBALS['blfb_test']['responses'] );
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['code'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function wp_remote_retrieve_header( $response, $header ) {
	$header = strtolower( $header );
	return isset( $response['headers'][ $header ] ) ? $response['headers'][ $header ] : '';
}

require dirname( __DIR__ ) . '/includes/class-options.php';
require dirname( __DIR__ ) . '/includes/class-cache.php';
require dirname( __DIR__ ) . '/includes/class-api-client.php';
