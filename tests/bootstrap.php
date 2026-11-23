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
		'roles'      => array(
			'administrator' => new WP_Role( 'administrator', array( 'manage_options' => true, 'read' => true ) ),
			'subscriber'    => new WP_Role( 'subscriber', array( 'read' => true ) ),
		),
		'users'      => array(), // ID => role name.
		'current'    => 0,       // Current user ID; 0 is a Visitor.
	);
	$GLOBALS['blfb_test']['role_names'] = new stdClass();
	$GLOBALS['blfb_test']['role_names']->role_names = array();
}

class WP_Role {
	public $name;
	public $capabilities;

	public function __construct( $name, $capabilities ) {
		$this->name         = $name;
		$this->capabilities = $capabilities;
	}

	public function add_cap( $cap ) {
		$this->capabilities[ $cap ] = true;
	}

	public function remove_cap( $cap ) {
		unset( $this->capabilities[ $cap ] );
	}
}

function get_role( $name ) {
	return isset( $GLOBALS['blfb_test']['roles'][ $name ] ) ? $GLOBALS['blfb_test']['roles'][ $name ] : null;
}

function add_role( $name, $display_name, $capabilities = array() ) {
	$GLOBALS['blfb_test']['roles'][ $name ]                   = new WP_Role( $name, $capabilities );
	$GLOBALS['blfb_test']['role_names']->role_names[ $name ] = $display_name;
	return $GLOBALS['blfb_test']['roles'][ $name ];
}

function remove_role( $name ) {
	unset( $GLOBALS['blfb_test']['roles'][ $name ], $GLOBALS['blfb_test']['role_names']->role_names[ $name ] );
}

function wp_roles() {
	return $GLOBALS['blfb_test']['role_names'];
}

/**
 * Creates a fake user with one role and returns its ID.
 */
function make_user( $role ) {
	$id                                  = count( $GLOBALS['blfb_test']['users'] ) + 1;
	$GLOBALS['blfb_test']['users'][ $id ] = $role;
	return $id;
}

function user_can( $user, $cap ) {
	$id = is_object( $user ) ? $user->ID : (int) $user;
	if ( ! isset( $GLOBALS['blfb_test']['users'][ $id ] ) ) {
		return false;
	}
	$role = get_role( $GLOBALS['blfb_test']['users'][ $id ] );
	return $role && ! empty( $role->capabilities[ $cap ] );
}

function is_user_logged_in() {
	return 0 !== $GLOBALS['blfb_test']['current'];
}

function get_current_user_id() {
	return $GLOBALS['blfb_test']['current'];
}

function _x( $text, $context, $domain = 'default' ) {
	return $text;
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

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) );
}

function checked( $checked, $current = true, $echo = true ) {
	return (string) $checked === (string) $current ? ' checked=\'checked\'' : '';
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
require dirname( __DIR__ ) . '/includes/class-access.php';
require dirname( __DIR__ ) . '/includes/class-settings.php';
