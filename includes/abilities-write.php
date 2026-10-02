<?php
/**
 * Write abilities (phase 2): content, SEO, global styles, templates, menus, media, changes, rollback, publish.
 *
 * Approval mode (default): nothing the visitors see changes. New content is a draft; edits to
 * published content go to a draft copy that a human publishes; site-wide design changes are dry_run only.
 *
 * @package AtelierMCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the agent may publish and change live design directly.
 *
 * @return bool
 */
function atelier_full_access() {
	return 'full' === get_option( 'atelier_mcp_mode', 'approval' );
}

/**
 * Write ability definitions (same shape as atelier_read_abilities(), plus annotations).
 *
 * @return array
 */
function atelier_write_abilities() {
	if ( ! get_option( 'atelier_mcp_writes', true ) ) {
		return array();
	}

	$write   = array(
		'readonly'    => false,
		'destructive' => false,
		'idempotent'  => true,
	);
	$dry_run = array(
		'type'        => 'boolean',
		'default'     => false,
		'description' => 'Return before/after without saving.',
	);
	$content = array(
		'title'           => array( 'type' => 'string' ),
		'content'         => array(
			'type'        => 'string',
			'description' => 'Serialized block markup (<!-- wp:paragraph --><p>…</p><!-- /wp:paragraph -->). Use block types and patterns from atelier_list_patterns.',
		),
		'excerpt'         => array( 'type' => 'string' ),
		'slug'            => array( 'type' => 'string' ),
		'parent'          => array( 'type' => 'integer' ),
		'template'        => array(
			'type'        => 'string',
			'description' => 'Template slug, e.g. "page-no-title". Empty for default.',
		),
		'seo_title'       => array( 'type' => 'string' ),
		'seo_description' => array(
			'type'      => 'string',
			'maxLength' => 320,
		),
	);
	$mode    = atelier_full_access()
		? 'Full access mode: changes go live.'
		: 'Approval mode: if the post is published, changes are saved to a draft copy that a human must publish.';

	$abilities = array(
		'atelier/create-content'       => array(
			'Create content',
			'Create a page, post or other post type. Status is "draft" unless the site is in full access mode.',
			'edit_posts',
			$content + array(
				'post_type' => array(
					'type'    => 'string',
					'default' => 'page',
				),
				'status'    => array(
					'type'    => 'string',
					'enum'    => array( 'draft', 'pending', 'publish' ),
					'default' => 'draft',
				),
			),
			'atelier_create_content',
			array( 'idempotent' => false ) + $write,
		),
		'atelier/update-content'       => array(
			'Update content',
			"Update title, block content, excerpt, slug, template or SEO of a post/page. Send only the fields to change; content replaces the whole body. $mode",
			'edit_posts',
			array(
				'id'      => array(
					'type'     => 'integer',
					'required' => true,
				),
				'dry_run' => $dry_run,
			) + $content,
			'atelier_update_content',
			$write,
		),
		'atelier/update-seo-meta'      => array(
			'Update SEO meta',
			"Set SEO title and meta description of a post/page (Yoast, Rank Math, or Atelier's own tags when no SEO plugin). $mode",
			'edit_posts',
			array(
				'id'              => array(
					'type'     => 'integer',
					'required' => true,
				),
				'seo_title'       => $content['seo_title'],
				'seo_description' => $content['seo_description'],
				'dry_run'         => $dry_run,
			),
			'atelier_update_content',
			$write,
		),
		'atelier/update-global-styles' => array(
			'Update global styles',
			'Patch theme.json user styles. "settings"/"styles" objects are deep-merged into the current ones; lists (e.g. color.palette) are replaced whole. Site-wide: only dry_run in approval mode.',
			'edit_theme_options',
			array(
				'settings' => array(
					'type'        => 'object',
					'description' => 'e.g. {"color":{"palette":{"custom":[{"slug":"brand","name":"Brand","color":"#0a7"}]}}}',
				),
				'styles'   => array(
					'type'        => 'object',
					'description' => 'e.g. {"color":{"background":"#fff"},"elements":{"h1":{"typography":{"fontSize":"3rem"}}}}',
				),
				'dry_run'  => $dry_run,
			),
			'atelier_update_global_styles',
			$write,
		),
		'atelier/update-template'      => array(
			'Update template',
			'Replace the block markup of a template or template part (ids from atelier_list_templates). Rollback restores the theme version. Site-wide: only dry_run in approval mode.',
			'edit_theme_options',
			array(
				'id'      => array(
					'type'     => 'string',
					'required' => true,
				),
				'type'    => array(
					'type'    => 'string',
					'enum'    => array( 'wp_template', 'wp_template_part' ),
					'default' => 'wp_template',
				),
				'content' => array(
					'type'     => 'string',
					'required' => true,
				),
				'dry_run' => $dry_run,
			),
			'atelier_update_template',
			$write,
		),
		'atelier/update-menu'          => array(
			'Update menu',
			'Replace the inner blocks of a navigation menu (ids from atelier_list_menus), e.g. <!-- wp:navigation-link {"label":"Shop","url":"/shop"} /-->. Site-wide: only dry_run in approval mode.',
			'edit_theme_options',
			array(
				'id'      => array(
					'type'     => 'integer',
					'required' => true,
				),
				'content' => array(
					'type'     => 'string',
					'required' => true,
				),
				'title'   => array( 'type' => 'string' ),
				'dry_run' => $dry_run,
			),
			'atelier_update_menu',
			$write,
		),
		'atelier/upload-media'         => array(
			'Upload media',
			'Add an image to the media library from a public URL or base64 data. Alt text is required.',
			'upload_files',
			array(
				'url'      => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'data'     => array(
					'type'        => 'string',
					'description' => 'Base64 file contents (instead of url).',
				),
				'filename' => array(
					'type'        => 'string',
					'description' => 'Required with data, e.g. "hero.webp".',
				),
				'alt'      => array(
					'type'      => 'string',
					'minLength' => 1,
					'required'  => true,
				),
				'title'    => array( 'type' => 'string' ),
			),
			'atelier_upload_media',
			array( 'idempotent' => false ) + $write,
		),
		'atelier/list-changes'         => array(
			'List changes',
			'Log of changes made through Atelier MCP, newest first. Use the id with atelier_rollback.',
			'edit_posts',
			array(
				'limit'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
				'offset' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
			),
			'atelier_list_changes',
			null, // Read-only.
		),
		'atelier/rollback'             => array(
			'Rollback change',
			'Undo one change by change_id: restores previous values, or removes what the change created. The rollback is logged and can itself be undone.',
			'edit_posts',
			array(
				'change_id' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
			'atelier_rollback',
			array(
				'destructive' => true,
				'idempotent'  => false,
			) + $write,
		),
	);

	if ( atelier_full_access() ) {
		$abilities['atelier/publish'] = array(
			'Publish',
			'Publish a draft. If it is a draft copy of a published post, its changes are applied to the original.',
			'publish_posts',
			array(
				'id' => array(
					'type'     => 'integer',
					'required' => true,
				),
			),
			'atelier_publish',
			$write,
		);
	}

	return $abilities;
}

/**
 * Meta keys for SEO title and description.
 *
 * @return string[]
 */
function atelier_seo_keys() {
	$keys = array(
		'yoast'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
		'rankmath' => array( 'rank_math_title', 'rank_math_description' ),
	);
	return $keys[ atelier_seo_plugin() ] ?? array( '_atelier_seo_title', '_atelier_seo_description' );
}

// Expose SEO meta to REST so writes go through core validation and permissions.
add_action(
	'init',
	static function () {
		foreach ( atelier_seo_keys() as $key ) {
			register_post_meta(
				'',
				$key,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}
);

// Atelier's own SEO tags, only when no SEO plugin handles them.
add_filter(
	'pre_get_document_title',
	static function ( $title ) {
		$custom = ! atelier_seo_plugin() && is_singular() ? get_post_meta( get_queried_object_id(), '_atelier_seo_title', true ) : '';
		return $custom ? $custom : $title;
	}
);
add_action(
	'wp_head',
	static function () {
		$description = ! atelier_seo_plugin() && is_singular() ? get_post_meta( get_queried_object_id(), '_atelier_seo_description', true ) : '';
		if ( $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}
	},
	1
);

/**
 * REST route of a post (item or collection).
 *
 * @param string   $post_type Post type.
 * @param int|null $id        Post ID, null for the collection.
 * @return string|WP_Error
 */
function atelier_post_route( $post_type, $id = null ) {
	$object = get_post_type_object( $post_type );
	if ( ! $object || empty( $object->show_in_rest ) ) {
		return new WP_Error( 'atelier_bad_post_type', sprintf( 'Post type "%s" does not exist or is not available in the REST API.', $post_type ) );
	}
	$route = '/' . ( $object->rest_namespace ?: 'wp/v2' ) . '/' . ( $object->rest_base ?: $post_type );
	return $id ? "$route/$id" : $route;
}

/**
 * Maps tool input to REST fields.
 *
 * @param array $input Input.
 * @return array
 */
function atelier_content_fields( $input ) {
	$fields = array_intersect_key( (array) $input, array_flip( array( 'title', 'content', 'excerpt', 'slug', 'parent', 'template' ) ) );
	$keys   = atelier_seo_keys();
	foreach ( array( 'seo_title' => $keys[0], 'seo_description' => $keys[1] ) as $field => $key ) {
		if ( isset( $input[ $field ] ) ) {
			$fields['meta'][ $key ] = $input[ $field ];
		}
	}
	return $fields;
}

/**
 * Create content.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_create_content( $input ) {
	$type  = atelier_arg( $input, 'post_type', 'page' );
	$route = atelier_post_route( $type );
	if ( is_wp_error( $route ) ) {
		return $route;
	}
	$fields           = atelier_content_fields( $input );
	$fields['status'] = atelier_arg( $input, 'status', 'draft' );
	if ( 'publish' === $fields['status'] && ! atelier_full_access() ) {
		return new WP_Error( 'atelier_approval_mode', 'Approval mode: create it as "draft" and ask a human to publish it in wp-admin.' );
	}
	return atelier_write( 'create_content', $route, $fields, sprintf( 'Create %s "%s"', $type, $fields['title'] ?? '' ), true );
}

/**
 * Update content or SEO meta. In approval mode, published posts are edited through a draft copy.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_update_content( $input ) {
	$post = get_post( (int) atelier_arg( $input, 'id' ) );
	if ( ! $post ) {
		return new WP_Error( 'atelier_not_found', 'Post not found. Use atelier_list_content to find valid ids.' );
	}
	$route = atelier_post_route( $post->post_type, $post->ID );
	if ( is_wp_error( $route ) ) {
		return $route;
	}
	$fields = atelier_content_fields( $input );
	if ( ! $fields ) {
		return new WP_Error( 'atelier_nothing_to_update', 'Send at least one field to change (title, content, excerpt, slug, parent, template, seo_title, seo_description).' );
	}
	if ( atelier_arg( $input, 'dry_run', false ) ) {
		return atelier_dry_run( $route, $fields );
	}
	if ( ! atelier_full_access() && in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ) {
		return atelier_write_draft_copy( $post, $fields );
	}
	return atelier_write( 'update_content', $route, $fields, sprintf( 'Update %s #%d', $post->post_type, $post->ID ) );
}

/**
 * Saves changes to a draft copy of a published post (reusing an existing copy).
 *
 * @param WP_Post $post   Published post.
 * @param array   $fields REST fields.
 * @return array|WP_Error
 */
function atelier_write_draft_copy( $post, $fields ) {
	$copy = get_posts(
		array(
			'post_type'   => $post->post_type,
			'post_status' => 'draft',
			'meta_key'    => '_atelier_draft_of', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => $post->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	if ( $copy ) {
		$result = atelier_write( 'update_content', atelier_post_route( $post->post_type, $copy[0] ), $fields, sprintf( 'Update draft copy of #%d', $post->ID ) );
	} else {
		$seo    = array();
		foreach ( atelier_seo_keys() as $key ) {
			$seo[ $key ] = (string) get_post_meta( $post->ID, $key, true );
		}
		$fields['meta'] = ( $fields['meta'] ?? array() ) + $seo;
		$fields        += array(
			'title'    => $post->post_title,
			'content'  => $post->post_content,
			'excerpt'  => $post->post_excerpt,
			'parent'   => $post->post_parent,
			'template' => get_page_template_slug( $post ),
		);
		$fields['status'] = 'draft';
		$result           = atelier_write( 'create_content', atelier_post_route( $post->post_type ), $fields, sprintf( 'Draft copy of #%d', $post->ID ), true );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $result['id'], '_atelier_draft_of', $post->ID );
		}
	}
	if ( ! is_wp_error( $result ) ) {
		$result['original_id'] = $post->ID;
		$result['note']        = sprintf( 'Approval mode: original #%d is unchanged. A human must review draft #%d in wp-admin and click Publish; the changes are then applied to the original and the copy is trashed.', $post->ID, $result['id'] );
	}
	return $result;
}

// When a human publishes a draft copy, apply it to the original and trash the copy.
add_action(
	'wp_after_insert_post',
	static function ( $post_id, $post, $update, $before ) {
		if ( 'publish' !== $post->post_status || ( $before && 'publish' === $before->post_status ) ) {
			return;
		}
		$original = get_post( (int) get_post_meta( $post_id, '_atelier_draft_of', true ) );
		if ( ! $original ) {
			return;
		}
		$meta = array();
		foreach ( atelier_seo_keys() as $key ) {
			$meta[ $key ] = (string) get_post_meta( $post_id, $key, true );
		}
		atelier_write(
			'approve_draft',
			atelier_post_route( $original->post_type, $original->ID ),
			array(
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'meta'    => $meta,
			),
			sprintf( 'Approved draft #%d into #%d', $post_id, $original->ID )
		);
		delete_post_meta( $post_id, '_atelier_draft_of' );
		wp_trash_post( $post_id );
	},
	10,
	4
);

/**
 * Site-wide design writes: dry_run only in approval mode.
 *
 * @param string $tool    Tool.
 * @param string $route   Route.
 * @param array  $fields  Fields.
 * @param string $summary Summary.
 * @param array  $input   Tool input.
 * @return array|WP_Error
 */
function atelier_design_write( $tool, $route, $fields, $summary, $input ) {
	if ( atelier_arg( $input, 'dry_run', false ) ) {
		return atelier_dry_run( $route, $fields );
	}
	if ( ! atelier_full_access() ) {
		return new WP_Error( 'atelier_approval_mode', 'Approval mode: site-wide design changes (styles, templates, menus) go live immediately, so only dry_run is allowed. Show the proposal to the user; they can apply it in the Site Editor or enable Full access in Settings → Atelier MCP.' );
	}
	return atelier_write( $tool, $route, $fields, $summary );
}

/**
 * Deep merge: objects merge recursively, lists are replaced.
 *
 * @param array $base  Current.
 * @param array $patch Patch.
 * @return array
 */
function atelier_merge( $base, $patch ) {
	foreach ( $patch as $key => $value ) {
		$base[ $key ] = is_array( $value ) && ! wp_is_numeric_array( $value ) && isset( $base[ $key ] ) && ( is_array( $base[ $key ] ) || is_object( $base[ $key ] ) )
			? atelier_merge( (array) $base[ $key ], $value )
			: $value;
	}
	return $base;
}

/**
 * Update global styles.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_update_global_styles( $input ) {
	if ( ! wp_theme_has_theme_json() ) {
		return new WP_Error( 'atelier_no_theme_json', 'The active theme has no theme.json (classic theme).' );
	}
	$route   = '/wp/v2/global-styles/' . WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
	$current = atelier_rest( 'GET', $route, array( 'context' => 'edit' ) );
	if ( is_wp_error( $current ) ) {
		return $current;
	}
	$fields = array();
	foreach ( array( 'settings', 'styles' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$fields[ $key ] = atelier_merge( (array) ( $current[ $key ] ?? array() ), (array) $input[ $key ] );
		}
	}
	if ( ! $fields ) {
		return new WP_Error( 'atelier_nothing_to_update', 'Send "settings" and/or "styles".' );
	}
	return atelier_design_write( 'update_global_styles', $route, $fields, 'Update global styles', $input );
}

/**
 * Update template or template part.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_update_template( $input ) {
	$base = 'wp_template_part' === atelier_arg( $input, 'type' ) ? 'template-parts' : 'templates';
	$id   = atelier_arg( $input, 'id' );
	return atelier_design_write( 'update_template', "/wp/v2/$base/$id", array( 'content' => atelier_arg( $input, 'content' ) ), "Update $base $id", $input );
}

/**
 * Update navigation menu.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_update_menu( $input ) {
	$fields = array_intersect_key( (array) $input, array_flip( array( 'content', 'title' ) ) );
	$id     = (int) atelier_arg( $input, 'id' );
	return atelier_design_write( 'update_menu', "/wp/v2/navigation/$id", $fields, "Update menu #$id", $input );
}

/**
 * Upload media from URL or base64.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_upload_media( $input ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$url  = atelier_arg( $input, 'url' );
	$data = atelier_arg( $input, 'data' );
	$name = sanitize_file_name( (string) atelier_arg( $input, 'filename', $url ? basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) : '' ) );
	if ( ! $url && ! $data ) {
		return new WP_Error( 'atelier_no_file', 'Send "url" or "data" (base64) with "filename".' );
	}
	if ( ! $name ) {
		return new WP_Error( 'atelier_no_filename', 'Send "filename" with an extension, e.g. "hero.webp".' );
	}

	if ( $url ) {
		$tmp = download_url( $url ); // Uses wp_safe_remote_get: blocks private/local addresses.
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
	} else {
		$binary = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $binary ) {
			return new WP_Error( 'atelier_bad_base64', '"data" is not valid base64.' );
		}
		$tmp = wp_tempnam( $name );
		file_put_contents( $tmp, $binary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	// media_handle_sideload checks the real file type against the allowed mime types.
	$id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		0,
		atelier_arg( $input, 'title' )
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return $id;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( atelier_arg( $input, 'alt' ) ) );

	return array(
		'change_id' => atelier_log_insert( 'upload_media', "/wp/v2/media/$id", "Upload $name", true, array() ),
		'id'        => $id,
		'url'       => wp_get_attachment_url( $id ),
	);
}

/**
 * List logged changes.
 *
 * @param array $input Input.
 * @return array
 */
function atelier_list_changes( $input ) {
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB
		$wpdb->prepare(
			'SELECT id, created_at, user_id, tool, route, summary FROM ' . atelier_log_table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			(int) atelier_arg( $input, 'limit', 20 ),
			(int) atelier_arg( $input, 'offset', 0 )
		),
		ARRAY_A
	);
	return array( 'items' => $rows );
}

/**
 * Rollback.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_rollback( $input ) {
	return atelier_rollback_change( (int) atelier_arg( $input, 'change_id' ) );
}

/**
 * Publish (full access mode only).
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_publish( $input ) {
	$post = get_post( (int) atelier_arg( $input, 'id' ) );
	if ( ! $post ) {
		return new WP_Error( 'atelier_not_found', 'Post not found.' );
	}
	$original = (int) get_post_meta( $post->ID, '_atelier_draft_of', true );
	$result   = atelier_write( 'publish', atelier_post_route( $post->post_type, $post->ID ), array( 'status' => 'publish' ), sprintf( 'Publish #%d', $post->ID ) );
	if ( ! is_wp_error( $result ) && $original ) {
		$result['note'] = sprintf( 'Draft copy applied to original #%d and trashed.', $original );
		$result['id']   = $original;
	}
	return $result;
}
