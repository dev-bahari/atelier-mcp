<?php
/**
 * Removes the change log table and options.
 *
 * @package AtelierMCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}atelier_mcp_log" ); // phpcs:ignore WordPress.DB
foreach ( array( 'atelier_mcp_enabled', 'atelier_mcp_writes', 'atelier_mcp_mode', 'atelier_mcp_db' ) as $option ) {
	delete_option( $option );
}
