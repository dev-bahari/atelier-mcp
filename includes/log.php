<?php
/**
 * Change log, snapshots and rollback. Every write goes through atelier_write().
 *
 * @package AtelierMCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log table name.
 *
 * @return string
 */
function atelier_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'atelier_mcp_log';
}

/**
 * Creates or upgrades the log table.
 */
function atelier_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		'CREATE TABLE ' . atelier_log_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		tool varchar(64) NOT NULL,
		route varchar(255) NOT NULL,
		summary varchar(255) NOT NULL,
		revert_by_delete tinyint(1) NOT NULL DEFAULT 0,
		snapshot longtext NOT NULL,
		PRIMARY KEY  (id)
		) {$wpdb->get_charset_collate()};"
	);
	update_option( 'atelier_mcp_db', '1', false );
}

/**
 * Runs a core REST request internally, as the current user.
 *
 * @param string $method HTTP method.
 * @param string $route  Route, e.g. "/wp/v2/pages/12".
 * @param array  $params Params.
 * @return array|WP_Error
 */
function atelier_rest( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'POST' === $method ) {
		$request->set_body_params( $params );
	} else {
		$request->set_query_params( $params );
	}
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		return $response->as_error();
	}
	return rest_get_server()->response_to_data( $response, false );
}

/**
 * The current values of $fields on a REST item ("raw" when available).
 *
 * @param array $item   REST item (context=edit).
 * @param array $fields Fields to pick.
 * @return array
 */
function atelier_pick( $item, $fields ) {
	$out = array();
	foreach ( $fields as $key => $value ) {
		if ( 'meta' === $key ) {
			$out['meta'] = array_intersect_key( (array) ( $item['meta'] ?? array() ), $value );
		} elseif ( array_key_exists( $key, $item ) ) {
			$out[ $key ] = is_array( $item[ $key ] ) && array_key_exists( 'raw', $item[ $key ] ) ? $item[ $key ]['raw'] : $item[ $key ];
		}
	}
	return $out;
}

/**
 * Inserts a log row.
 *
 * @param string $tool             Tool.
 * @param string $route            REST route of the item.
 * @param string $summary          Human summary.
 * @param bool   $revert_by_delete Rollback deletes the item (it was created by this change).
 * @param array  $snapshot         Values before the change.
 * @return int Change ID.
 */
function atelier_log_insert( $tool, $route, $summary, $revert_by_delete, $snapshot ) {
	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		atelier_log_table(),
		array(
			'created_at'       => current_time( 'mysql', true ),
			'user_id'          => get_current_user_id(),
			'tool'             => $tool,
			'route'            => $route,
			'summary'          => mb_substr( $summary, 0, 255 ),
			'revert_by_delete' => $revert_by_delete ? 1 : 0,
			'snapshot'         => wp_json_encode( $snapshot ),
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Snapshot, write through core REST, log.
 *
 * @param string $tool    Tool name.
 * @param string $route   Item route, or collection route when $create.
 * @param array  $fields  REST fields to write.
 * @param string $summary Human summary.
 * @param bool   $create  Create a new item.
 * @return array|WP_Error
 */
function atelier_write( $tool, $route, $fields, $summary, $create = false ) {
	$before = array();
	$revert = $create;
	if ( ! $create ) {
		$item = atelier_rest( 'GET', $route, array( 'context' => 'edit' ) );
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		$before = atelier_pick( $item, $fields );
		// A template still served from the theme file: rollback = delete the customization.
		$revert = isset( $item['source'] ) && 'theme' === $item['source'];
	}

	$result = atelier_rest( 'POST', $route, $fields );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( $create ) {
		$route .= '/' . $result['id'];
	}

	return array(
		'change_id' => atelier_log_insert( $tool, $route, $summary, $revert, $before ),
		'id'        => $result['id'],
		'status'    => $result['status'] ?? null,
		'link'      => $result['link'] ?? null,
	);
}

/**
 * What a write would change, without saving.
 *
 * @param string $route  Item route.
 * @param array  $fields Fields.
 * @return array|WP_Error
 */
function atelier_dry_run( $route, $fields ) {
	$item = atelier_rest( 'GET', $route, array( 'context' => 'edit' ) );
	if ( is_wp_error( $item ) ) {
		return $item;
	}
	return array(
		'dry_run' => true,
		'before'  => atelier_pick( $item, $fields ),
		'after'   => $fields,
	);
}

/**
 * Reverts one change. The rollback is itself logged, so it can be undone too.
 *
 * @param int $change_id Change ID.
 * @return array|WP_Error
 */
function atelier_rollback_change( $change_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . atelier_log_table() . ' WHERE id = %d', $change_id ) ); // phpcs:ignore WordPress.DB
	if ( ! $row ) {
		return new WP_Error( 'atelier_not_found', 'Change not found. Use atelier_list_changes to see valid change_id values.' );
	}
	if ( $row->revert_by_delete ) {
		// Templates and media have no trash; created posts go to the trash.
		$force  = (bool) preg_match( '#/(templates|template-parts|media)/#', $row->route );
		$result = atelier_rest( 'DELETE', $row->route, array( 'force' => $force ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'change_id' => atelier_log_insert( 'rollback', $row->route, "Rollback of #{$row->id}: removed", false, array() ),
			'reverted'  => (int) $row->id,
		);
	}
	$result = atelier_write( 'rollback', $row->route, json_decode( $row->snapshot, true ), "Rollback of #{$row->id}" );
	if ( ! is_wp_error( $result ) ) {
		$result['reverted'] = (int) $row->id;
	}
	return $result;
}

/**
 * Write guard: rate limit per user.
 *
 * @return true|WP_Error
 */
function atelier_guard() {
	// ponytail: fixed window that restarts on each write; use a sliding log if abuse shows up.
	$key   = 'atelier_rl_' . get_current_user_id();
	$count = (int) get_transient( $key );
	if ( $count >= 60 ) {
		return new WP_Error( 'atelier_rate_limited', 'Rate limit: 60 writes per minute. Wait a minute and retry.' );
	}
	set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
	return true;
}
