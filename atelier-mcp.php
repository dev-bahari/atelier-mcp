<?php
/**
 * Plugin Name:       Atelier MCP
 * Plugin URI:        https://github.com/bahariaqua/atelier-mcp
 * Description:       Connect Claude, Codex or any MCP client to WordPress to audit, redesign and optimize your site.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Bahari S.A.S.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       atelier-mcp
 */

defined( 'ABSPATH' ) || exit;

define( 'ATELIER_MCP_VERSION', '0.1.0' );

require __DIR__ . '/includes/abilities-read.php';
require __DIR__ . '/includes/admin.php';

add_action(
	'wp_abilities_api_categories_init',
	static function () {
		wp_register_ability_category(
			'atelier',
			array(
				'label'       => __( 'Atelier MCP', 'atelier-mcp' ),
				'description' => __( 'Audit, redesign and optimize the site.', 'atelier-mcp' ),
			)
		);
	}
);

add_action( 'wp_abilities_api_init', 'atelier_register_read_abilities' );

// The MCP transport comes from the official MCP Adapter plugin (WordPress/mcp-adapter).
add_action(
	'mcp_adapter_init',
	static function ( $adapter ) {
		if ( ! get_option( 'atelier_mcp_enabled', true ) ) {
			return;
		}
		$adapter->create_server(
			'atelier-mcp',
			'atelier-mcp/v1',
			'mcp',
			'Atelier MCP',
			'Audit, redesign and optimize this WordPress site.',
			ATELIER_MCP_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			null,
			atelier_ability_names(),
			array(),
			array(),
			'atelier_transport_permission'
		);
	}
);

/**
 * Who may open the MCP connection at all. Each tool checks its own capability too.
 *
 * @return true|WP_Error
 */
function atelier_transport_permission() {
	if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
		return new WP_Error( 'atelier_https_required', __( 'Atelier MCP only accepts HTTPS connections.', 'atelier-mcp' ), array( 'status' => 403 ) );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'atelier_forbidden', __( 'Authenticate with an Application Password of a user who can edit posts.', 'atelier-mcp' ), array( 'status' => 401 ) );
	}
	return true;
}

// MCP tool names: "atelier/site-info" -> "atelier_site_info".
add_filter(
	'mcp_adapter_tool_name',
	static function ( $name, $ability ) {
		return 0 === strpos( $ability->get_name(), 'atelier/' ) ? str_replace( array( '/', '-' ), '_', $ability->get_name() ) : $name;
	},
	10,
	2
);
