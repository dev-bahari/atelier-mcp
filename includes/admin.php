<?php
/**
 * Settings page: on/off switch and ready-to-copy connection commands.
 *
 * @package AtelierMCP
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	static function () {
		register_setting(
			'atelier_mcp',
			'atelier_mcp_enabled',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}
);

add_action(
	'admin_menu',
	static function () {
		add_options_page( 'Atelier MCP', 'Atelier MCP', 'manage_options', 'atelier-mcp', 'atelier_render_settings' );
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( class_exists( 'WP\MCP\Core\McpAdapter' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		wp_admin_notice(
			sprintf(
				/* translators: %s: download URL */
				__( 'Atelier MCP needs the official <strong>MCP Adapter</strong> plugin. <a href="%s">Download it</a>, then go to Plugins → Add New → Upload.', 'atelier-mcp' ),
				'https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip'
			),
			array( 'type' => 'error' )
		);
	}
);

/**
 * Renders the settings page.
 */
function atelier_render_settings() {
	$url  = rest_url( 'atelier-mcp/v1/mcp' );
	$user = wp_get_current_user()->user_login;
	?>
	<div class="wrap">
		<h1>Atelier MCP</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'atelier_mcp' ); ?>
			<label>
				<input type="checkbox" name="atelier_mcp_enabled" value="1" <?php checked( get_option( 'atelier_mcp_enabled', true ) ); ?>>
				<?php esc_html_e( 'Enable the MCP endpoint', 'atelier-mcp' ); ?>
			</label>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Connect', 'atelier-mcp' ); ?></h2>
		<ol>
			<li>
				<?php
				printf(
					/* translators: %s: profile URL */
					wp_kses_post( __( 'Create an <a href="%s">Application Password</a> (Users → Profile). We recommend a dedicated user with the Editor role.', 'atelier-mcp' ) ),
					esc_url( admin_url( 'profile.php#application-passwords-section' ) )
				);
				?>
			</li>
			<li><?php esc_html_e( 'Encode "user:application-password" in Base64 and use it below in place of BASE64.', 'atelier-mcp' ); ?></li>
		</ol>
		<p><strong>Endpoint:</strong> <code><?php echo esc_html( $url ); ?></code></p>

		<h3>Claude Code</h3>
		<pre><code>claude mcp add --transport http atelier <?php echo esc_html( $url ); ?> --header "Authorization: Basic BASE64"</code></pre>

		<h3>Codex (~/.codex/config.toml)</h3>
		<pre><code>[mcp_servers.atelier]
url = "<?php echo esc_html( $url ); ?>"
http_headers = { Authorization = "Basic BASE64" }</code></pre>

		<h3>Claude Desktop</h3>
		<pre><code>{
  "mcpServers": {
    "atelier": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote"],
      "env": {
        "WP_API_URL": "<?php echo esc_html( $url ); ?>",
        "WP_API_USERNAME": "<?php echo esc_html( $user ); ?>",
        "WP_API_PASSWORD": "application-password"
      }
    }
  }
}</code></pre>
	</div>
	<?php
}
