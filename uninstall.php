<?php
/**
 * Cleanup on plugin uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings
delete_option( 'ces_settings' );

// Remove all post meta
$posts = get_posts( [
	'post_type'      => 'any',
	'meta_key'       => '_ces_expiry_date',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'post_status'    => 'any',
] );

foreach ( $posts as $id ) {
	delete_post_meta( $id, '_ces_expiry_date' );
	delete_post_meta( $id, '_ces_expiry_action' );
	delete_post_meta( $id, '_ces_expiry_redirect' );
	delete_post_meta( $id, '_ces_expiry_message' );
	delete_post_meta( $id, '_ces_processed' );
}

// Drop log table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ces_log" );

// Clear scheduled cron
wp_clear_scheduled_hook( 'ces_run_expiry_check' );
