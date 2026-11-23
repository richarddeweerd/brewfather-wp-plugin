<?php
/**
 * Removes all plugin data when the plugin is deleted.
 *
 * @package BrewLogForBrewfather
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'blfb_version' );
delete_option( 'blfb_user_id' );
delete_option( 'blfb_api_key' );
delete_option( 'blfb_cache_timeout' );
delete_option( 'blfb_cache_generation' );
delete_option( 'blfb_hide_ingredients_visitors' );
delete_option( 'blfb_hide_ingredients_users' );
delete_transient( 'blfb_rate_limited' );
// Cached responses are transients with an expiry; WordPress removes them on its own.

// Batch notes.
$blfb_notes = get_posts(
	array(
		'post_type'      => 'blfb_batch_note',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $blfb_notes as $blfb_note_id ) {
	wp_delete_post( $blfb_note_id, true );
}

require_once __DIR__ . '/includes/class-access.php';
BrewLogForBrewfather\Access::uninstall();
