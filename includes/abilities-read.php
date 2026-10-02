<?php
/**
 * Read-only abilities (phase 1): site info, content, styles, templates, patterns, media and audits.
 *
 * @package AtelierMCP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ability definitions: name => [label, description, capability, input properties, callback].
 *
 * @return array
 */
function atelier_read_abilities() {
	$paging = array(
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
	);

	return array(
		'atelier/site-info'         => array(
			'Site info',
			'WordPress/PHP versions, active theme (block or classic), active plugins and detected SEO plugin. Call this first.',
			'edit_posts',
			array(),
			'atelier_site_info',
		),
		'atelier/list-content'      => array(
			'List content',
			'List posts, pages or any public post type with filters. Returns id, title, status, url, modified date. Paginated (has_more, next_offset).',
			'edit_posts',
			$paging + array(
				'post_type' => array(
					'type'        => 'string',
					'default'     => 'page',
					'description' => 'e.g. "page", "post", "product".',
				),
				'status'    => array(
					'type'    => 'string',
					'enum'    => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
					'default' => 'any',
				),
				'search'    => array( 'type' => 'string' ),
			),
			'atelier_list_content',
		),
		'atelier/get-content'       => array(
			'Get content',
			'Full content of one post/page: parsed block tree (format "blocks") or raw serialized markup (format "raw"), plus SEO title/description.',
			'edit_posts',
			array(
				'id'     => array(
					'type'     => 'integer',
					'required' => true,
				),
				'format' => array(
					'type'    => 'string',
					'enum'    => array( 'blocks', 'raw' ),
					'default' => 'blocks',
				),
			),
			'atelier_get_content',
		),
		'atelier/get-global-styles' => array(
			'Get global styles',
			'Effective theme.json settings (palette, typography, spacing, layout) and global styles of the active block theme.',
			'edit_theme_options',
			array(),
			'atelier_get_global_styles',
		),
		'atelier/list-templates'    => array(
			'List templates',
			'Block templates and template parts (header, footer...). Pass "id" (e.g. "theme//single") to get one with its markup.',
			'edit_theme_options',
			array(
				'type' => array(
					'type'    => 'string',
					'enum'    => array( 'wp_template', 'wp_template_part' ),
					'default' => 'wp_template',
				),
				'id'   => array( 'type' => 'string' ),
			),
			'atelier_list_templates',
		),
		'atelier/list-patterns'     => array(
			'List patterns',
			'Registered block patterns (name, title, categories) and available block types. Use these building blocks when redesigning. Pass "name" to get one pattern with its markup.',
			'edit_posts',
			$paging + array(
				'search' => array( 'type' => 'string' ),
				'name'   => array( 'type' => 'string' ),
			),
			'atelier_list_patterns',
		),
		'atelier/list-media'        => array(
			'List media',
			'Media library images with file size, dimensions, mime type and alt text. Set missing_alt=true to only get images without alt.',
			'upload_files',
			$paging + array(
				'missing_alt' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'atelier_list_media',
		),
		'atelier/audit-seo'         => array(
			'SEO audit',
			'Audit published pages/posts: missing SEO title/description, duplicate titles, H1 count, images without alt in content.',
			'edit_posts',
			array(
				'post_type' => array(
					'type'    => 'string',
					'default' => 'page',
				),
			),
			'atelier_audit_seo',
		),
		'atelier/audit-performance' => array(
			'Performance audit',
			'Local performance audit, no external services: home response time, autoloaded options size, object cache, PHP version, plugin count and heavy or non-WebP images.',
			'manage_options',
			array(),
			'atelier_audit_performance',
		),
	);
}

/**
 * Registered ability names, for the MCP server.
 *
 * @return string[]
 */
function atelier_ability_names() {
	return array_keys( atelier_read_abilities() );
}

/**
 * Registers every read ability.
 */
function atelier_register_read_abilities() {
	foreach ( atelier_read_abilities() as $name => list( $label, $description, $cap, $props, $callback ) ) {
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'atelier',
			'execute_callback'    => $callback,
			'permission_callback' => static function () use ( $cap ) {
				return current_user_can( $cap );
			},
			'meta'                => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
		if ( $props ) {
			$args['input_schema'] = array(
				'type'                 => 'object',
				'properties'           => $props,
				'additionalProperties' => false,
			);
		}
		wp_register_ability( $name, $args );
	}
}

/**
 * Reads an input value, falling back to the schema default.
 *
 * @param mixed  $input   Ability input.
 * @param string $key     Field.
 * @param mixed  $default Default.
 * @return mixed
 */
function atelier_arg( $input, $key, $default = null ) {
	return is_array( $input ) && isset( $input[ $key ] ) ? $input[ $key ] : $default;
}

/**
 * Detected SEO plugin: yoast, rankmath or null.
 *
 * @return string|null
 */
function atelier_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'yoast';
	}
	if ( class_exists( 'RankMath' ) ) {
		return 'rankmath';
	}
	return null;
}

/**
 * SEO title/description stored for a post.
 *
 * @param int $id Post ID.
 * @return array
 */
function atelier_seo_meta( $id ) {
	$keys = array(
		'yoast'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
		'rankmath' => array( 'rank_math_title', 'rank_math_description' ),
	);
	$plugin = atelier_seo_plugin();
	if ( ! $plugin ) {
		return array(
			'plugin'      => null,
			'title'       => null,
			'description' => null,
		);
	}
	return array(
		'plugin'      => $plugin,
		'title'       => get_post_meta( $id, $keys[ $plugin ][0], true ) ?: null,
		'description' => get_post_meta( $id, $keys[ $plugin ][1], true ) ?: null,
	);
}

/**
 * Site info.
 *
 * @return array
 */
function atelier_site_info() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all     = get_plugins();
	$plugins = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		if ( isset( $all[ $file ] ) ) {
			$plugins[] = array(
				'file'    => $file,
				'name'    => $all[ $file ]['Name'],
				'version' => $all[ $file ]['Version'],
			);
		}
	}
	$theme = wp_get_theme();
	return array(
		'name'             => get_bloginfo( 'name' ),
		'url'              => home_url( '/' ),
		'language'         => get_locale(),
		'wordpress'        => get_bloginfo( 'version' ),
		'php'              => PHP_VERSION,
		'multisite'        => is_multisite(),
		'environment'      => wp_get_environment_type(),
		'theme'            => array(
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'stylesheet'  => $theme->get_stylesheet(),
			'parent'      => $theme->parent() ? $theme->get_template() : null,
			'block_theme' => wp_is_block_theme(),
		),
		'front_page'       => 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 'latest_posts',
		'seo_plugin'       => atelier_seo_plugin(),
		'active_plugins'   => $plugins,
		'theme_files_edit' => ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ),
	);
}

/**
 * List content.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_list_content( $input ) {
	$type = atelier_arg( $input, 'post_type', 'page' );
	if ( ! post_type_exists( $type ) ) {
		return new WP_Error( 'atelier_bad_post_type', sprintf( 'Unknown post_type "%s". Available: %s', $type, implode( ', ', get_post_types( array( 'public' => true ) ) ) ) );
	}
	$limit  = (int) atelier_arg( $input, 'limit', 20 );
	$offset = (int) atelier_arg( $input, 'offset', 0 );
	$query  = new WP_Query(
		array(
			'post_type'      => $type,
			'post_status'    => atelier_arg( $input, 'status', 'any' ),
			's'              => atelier_arg( $input, 'search', '' ),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'modified',
			'no_found_rows'  => false,
		)
	);
	$items  = array();
	foreach ( $query->posts as $post ) {
		$items[] = array(
			'id'       => $post->ID,
			'title'    => get_the_title( $post ),
			'status'   => $post->post_status,
			'url'      => get_permalink( $post ),
			'modified' => $post->post_modified_gmt,
			'parent'   => $post->post_parent,
		);
	}
	return array(
		'total'       => (int) $query->found_posts,
		'items'       => $items,
		'has_more'    => $offset + count( $items ) < $query->found_posts,
		'next_offset' => $offset + count( $items ),
	);
}

/**
 * Drops empty fields from parsed blocks to save tokens.
 *
 * @param array $blocks Parsed blocks.
 * @return array
 */
function atelier_compact_blocks( $blocks ) {
	$out = array();
	foreach ( $blocks as $block ) {
		if ( null === $block['blockName'] && '' === trim( $block['innerHTML'] ) ) {
			continue; // Whitespace between blocks.
		}
		$item = array( 'name' => $block['blockName'] );
		if ( $block['attrs'] ) {
			$item['attrs'] = $block['attrs'];
		}
		if ( $block['innerBlocks'] ) {
			$item['innerBlocks'] = atelier_compact_blocks( $block['innerBlocks'] );
		} else {
			$item['html'] = trim( $block['innerHTML'] );
		}
		$out[] = $item;
	}
	return $out;
}

/**
 * Get content.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_get_content( $input ) {
	$post = get_post( (int) atelier_arg( $input, 'id' ) );
	if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'atelier_not_found', 'Post not found or not editable. Use atelier_list_content to find valid ids.' );
	}
	$data = array(
		'id'       => $post->ID,
		'type'     => $post->post_type,
		'title'    => $post->post_title,
		'status'   => $post->post_status,
		'slug'     => $post->post_name,
		'url'      => get_permalink( $post ),
		'excerpt'  => $post->post_excerpt,
		'template' => get_page_template_slug( $post ) ?: 'default',
		'seo'      => atelier_seo_meta( $post->ID ),
		'modified' => $post->post_modified_gmt,
	);
	if ( 'raw' === atelier_arg( $input, 'format', 'blocks' ) ) {
		$data['content'] = $post->post_content;
	} else {
		$data['blocks'] = atelier_compact_blocks( parse_blocks( $post->post_content ) );
	}
	return $data;
}

/**
 * Global styles.
 *
 * @return array|WP_Error
 */
function atelier_get_global_styles() {
	if ( ! wp_theme_has_theme_json() ) {
		return new WP_Error( 'atelier_no_theme_json', 'The active theme has no theme.json (classic theme). Global styles are not available.' );
	}
	$settings = wp_get_global_settings();
	return array(
		'palette'    => $settings['color']['palette'] ?? array(),
		'gradients'  => $settings['color']['gradients'] ?? array(),
		'typography' => array(
			'fontFamilies' => $settings['typography']['fontFamilies'] ?? array(),
			'fontSizes'    => $settings['typography']['fontSizes'] ?? array(),
		),
		'spacing'    => $settings['spacing']['spacingSizes'] ?? array(),
		'layout'     => $settings['layout'] ?? array(),
		'styles'     => wp_get_global_styles(),
	);
}

/**
 * List templates or get one.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_list_templates( $input ) {
	if ( ! wp_is_block_theme() ) {
		return new WP_Error( 'atelier_classic_theme', 'The active theme is a classic theme; block templates are not available.' );
	}
	$type = atelier_arg( $input, 'type', 'wp_template' );
	$id   = atelier_arg( $input, 'id' );
	if ( $id ) {
		$template = get_block_template( $id, $type );
		if ( ! $template ) {
			return new WP_Error( 'atelier_not_found', sprintf( 'Template "%s" not found. Call without id to list them.', $id ) );
		}
		return array(
			'id'      => $template->id,
			'title'   => $template->title,
			'source'  => $template->source,
			'area'    => $template->area,
			'content' => $template->content,
		);
	}
	$items = array();
	foreach ( get_block_templates( array(), $type ) as $template ) {
		$items[] = array(
			'id'          => $template->id,
			'slug'        => $template->slug,
			'title'       => $template->title,
			'description' => $template->description,
			'source'      => $template->source, // "theme" or "custom" (edited in the site editor).
			'area'        => $template->area,
		);
	}
	return array( 'items' => $items );
}

/**
 * List block patterns and block types.
 *
 * @param array $input Input.
 * @return array|WP_Error
 */
function atelier_list_patterns( $input ) {
	$registry = WP_Block_Patterns_Registry::get_instance();
	$name     = atelier_arg( $input, 'name' );
	if ( $name ) {
		$pattern = $registry->get_registered( $name );
		return $pattern ? $pattern : new WP_Error( 'atelier_not_found', sprintf( 'Pattern "%s" not found.', $name ) );
	}
	$search = strtolower( (string) atelier_arg( $input, 'search', '' ) );
	$all    = array();
	foreach ( $registry->get_all_registered() as $pattern ) {
		if ( $search && false === strpos( strtolower( $pattern['name'] . ' ' . $pattern['title'] ), $search ) ) {
			continue;
		}
		$all[] = array(
			'name'       => $pattern['name'],
			'title'      => $pattern['title'],
			'categories' => $pattern['categories'] ?? array(),
		);
	}
	$offset = (int) atelier_arg( $input, 'offset', 0 );
	$items  = array_slice( $all, $offset, (int) atelier_arg( $input, 'limit', 20 ) );
	return array(
		'total'       => count( $all ),
		'items'       => $items,
		'has_more'    => $offset + count( $items ) < count( $all ),
		'next_offset' => $offset + count( $items ),
		'block_types' => array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ),
	);
}

/**
 * List media.
 *
 * @param array $input Input.
 * @return array
 */
function atelier_list_media( $input ) {
	$args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => 'image',
		'posts_per_page' => (int) atelier_arg( $input, 'limit', 20 ),
		'offset'         => (int) atelier_arg( $input, 'offset', 0 ),
	);
	if ( atelier_arg( $input, 'missing_alt', false ) ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => '_wp_attachment_image_alt',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_wp_attachment_image_alt',
				'value' => '',
			),
		);
	}
	$query = new WP_Query( $args );
	$items = array();
	foreach ( $query->posts as $post ) {
		$meta    = wp_get_attachment_metadata( $post->ID );
		$items[] = array(
			'id'       => $post->ID,
			'url'      => wp_get_attachment_url( $post->ID ),
			'mime'     => $post->post_mime_type,
			'width'    => $meta['width'] ?? null,
			'height'   => $meta['height'] ?? null,
			'bytes'    => $meta['filesize'] ?? null,
			'alt'      => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
			'attached' => $post->post_parent,
		);
	}
	return array(
		'total'       => (int) $query->found_posts,
		'items'       => $items,
		'has_more'    => $args['offset'] + count( $items ) < $query->found_posts,
		'next_offset' => $args['offset'] + count( $items ),
	);
}

/**
 * SEO audit.
 *
 * @param array $input Input.
 * @return array
 */
function atelier_audit_seo( $input ) {
	// ponytail: capped at 500 items per call; add pagination if sites grow beyond that.
	$posts  = get_posts(
		array(
			'post_type'      => atelier_arg( $input, 'post_type', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 500,
		)
	);
	$plugin = atelier_seo_plugin();
	$titles = array();
	$issues = array();
	foreach ( $posts as $post ) {
		$seo   = atelier_seo_meta( $post->ID );
		$title = $seo['title'] ?: $post->post_title;
		$titles[ strtolower( $title ) ][] = $post->ID;

		$found = array();
		if ( '' === trim( $post->post_title ) ) {
			$found[] = 'empty_title';
		}
		if ( $plugin && ! $seo['description'] ) {
			$found[] = 'missing_meta_description';
		}
		$h1 = preg_match_all( '/<h1[\s>]/i', $post->post_content );
		if ( $h1 > 1 ) {
			$found[] = "multiple_h1:$h1"; // The theme usually prints the title as the H1.
		}
		$no_alt = preg_match_all( '/<img(?![^>]*\balt="[^"]+")[^>]*>/i', $post->post_content );
		if ( $no_alt ) {
			$found[] = "images_without_alt:$no_alt";
		}
		if ( str_word_count( wp_strip_all_tags( $post->post_content ) ) < 150 ) {
			$found[] = 'thin_content';
		}
		if ( $found ) {
			$issues[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'url'    => get_permalink( $post ),
				'issues' => $found,
			);
		}
	}
	$duplicates = array();
	foreach ( $titles as $title => $ids ) {
		if ( count( $ids ) > 1 ) {
			$duplicates[] = array(
				'title' => $title,
				'ids'   => $ids,
			);
		}
	}
	return array(
		'audited'          => count( $posts ),
		'seo_plugin'       => $plugin,
		'note'             => $plugin ? null : 'No SEO plugin detected: meta descriptions cannot be checked.',
		'duplicate_titles' => $duplicates,
		'pages_with_issues' => $issues,
		'blog_public'      => (bool) get_option( 'blog_public' ),
	);
}

/**
 * Local performance audit.
 *
 * @return array
 */
function atelier_audit_performance() {
	global $wpdb;

	$start    = microtime( true );
	$response = wp_remote_get(
		home_url( '/' ),
		array(
			'timeout'   => 15,
			'sslverify' => false, // Loopback to our own site; local certs are often self-signed.
		)
	);
	$elapsed  = round( ( microtime( true ) - $start ) * 1000 );

	$autoload = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	// ponytail: scans the 500 most recent images; paginate if libraries are larger.
	$heavy  = array();
	$legacy = 0;
	$images = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => 500,
		)
	);
	foreach ( $images as $image ) {
		$meta  = wp_get_attachment_metadata( $image->ID );
		$bytes = $meta['filesize'] ?? 0;
		if ( in_array( $image->post_mime_type, array( 'image/jpeg', 'image/png' ), true ) ) {
			++$legacy;
		}
		if ( $bytes > 300 * KB_IN_BYTES ) {
			$heavy[] = array(
				'id'    => $image->ID,
				'url'   => wp_get_attachment_url( $image->ID ),
				'kb'    => round( $bytes / KB_IN_BYTES ),
				'width' => $meta['width'] ?? null,
			);
		}
	}

	return array(
		'home_response'  => is_wp_error( $response )
			? array( 'error' => $response->get_error_message() )
			: array(
				'status' => wp_remote_retrieve_response_code( $response ),
				'ms'     => $elapsed,
				'kb'     => round( strlen( wp_remote_retrieve_body( $response ) ) / KB_IN_BYTES ),
			),
		'autoload_kb'    => round( $autoload / KB_IN_BYTES ),
		'object_cache'   => (bool) wp_using_ext_object_cache(),
		'php'            => PHP_VERSION,
		'active_plugins' => count( (array) get_option( 'active_plugins', array() ) ),
		'images'         => array(
			'scanned'          => count( $images ),
			'jpeg_png'         => $legacy,
			'over_300kb'       => $heavy,
		),
		'thresholds'     => 'Targets: home < 800 ms, autoload < 800 KB, images < 300 KB, PHP >= 8.1.',
	);
}
