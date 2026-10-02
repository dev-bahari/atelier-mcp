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
		register_setting(
			'atelier_mcp',
			'atelier_mcp_writes',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'atelier_mcp',
			'atelier_mcp_mode',
			array(
				'type'              => 'string',
				'default'           => 'approval',
				'sanitize_callback' => static function ( $value ) {
					return 'full' === $value ? 'full' : 'approval';
				},
			)
		);
	}
);

add_action(
	'admin_post_atelier_rollback',
	static function () {
		$id = isset( $_POST['change_id'] ) ? absint( $_POST['change_id'] ) : 0;
		check_admin_referer( 'atelier_rollback_' . $id );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'atelier-mcp' ) );
		}
		$result = atelier_rollback_change( $id );
		$notice = is_wp_error( $result ) ? $result->get_error_message() : sprintf( /* translators: %d: change id */ __( 'Change #%d reverted.', 'atelier-mcp' ), $id );
		set_transient( 'atelier_notice_' . get_current_user_id(), $notice, 60 );
		wp_safe_redirect( admin_url( 'options-general.php?page=atelier-mcp' ) );
		exit;
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
	$user   = wp_get_current_user()->user_login;
	$notice = get_transient( 'atelier_notice_' . get_current_user_id() );
	delete_transient( 'atelier_notice_' . get_current_user_id() );
	?>
	<div class="wrap">
		<h1>Atelier MCP</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'atelier_mcp' ); ?>
			<label>
				<input type="checkbox" name="atelier_mcp_enabled" value="1" <?php checked( get_option( 'atelier_mcp_enabled', true ) ); ?>>
				<?php esc_html_e( 'Enable the MCP endpoint', 'atelier-mcp' ); ?>
			</label><br>
			<label>
				<input type="checkbox" name="atelier_mcp_writes" value="1" <?php checked( get_option( 'atelier_mcp_writes', true ) ); ?>>
				<?php esc_html_e( 'Allow write tools (otherwise the agent can only read and audit)', 'atelier-mcp' ); ?>
			</label>
			<h2><?php esc_html_e( 'Publishing mode', 'atelier-mcp' ); ?></h2>
			<label>
				<input type="radio" name="atelier_mcp_mode" value="approval" <?php checked( 'full' !== get_option( 'atelier_mcp_mode', 'approval' ) ); ?>>
				<strong><?php esc_html_e( 'With approval (recommended)', 'atelier-mcp' ); ?></strong> –
				<?php esc_html_e( 'the agent only creates drafts. Edits to published pages go to a draft copy; when you publish it, the changes are applied to the original. Site-wide design changes are proposals only.', 'atelier-mcp' ); ?>
			</label><br>
			<label>
				<input type="radio" name="atelier_mcp_mode" value="full" <?php checked( 'full', get_option( 'atelier_mcp_mode', 'approval' ) ); ?>>
				<strong><?php esc_html_e( 'Full access', 'atelier-mcp' ); ?></strong> –
				<?php esc_html_e( 'the agent publishes and changes styles, templates and menus live. Every change can be reverted below.', 'atelier-mcp' ); ?>
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

		<h2><?php esc_html_e( 'Recent changes', 'atelier-mcp' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th>#</th><th><?php esc_html_e( 'Date (UTC)', 'atelier-mcp' ); ?></th><th><?php esc_html_e( 'User', 'atelier-mcp' ); ?></th><th><?php esc_html_e( 'Change', 'atelier-mcp' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( atelier_list_changes( array( 'limit' => 30 ) )['items'] as $change ) : ?>
				<tr>
					<td><?php echo (int) $change['id']; ?></td>
					<td><?php echo esc_html( $change['created_at'] ); ?></td>
					<td><?php echo esc_html( get_the_author_meta( 'user_login', $change['user_id'] ) ); ?></td>
					<td><?php echo esc_html( $change['summary'] ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="atelier_rollback">
							<input type="hidden" name="change_id" value="<?php echo (int) $change['id']; ?>">
							<?php wp_nonce_field( 'atelier_rollback_' . $change['id'] ); ?>
							<button class="button" onclick="return confirm('<?php echo esc_js( __( 'Revert this change?', 'atelier-mcp' ) ); ?>')"><?php esc_html_e( 'Revert', 'atelier-mcp' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
