=== Atelier MCP ===
Contributors: bahari
Tags: mcp, ai, claude, seo, performance
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, Codex or any MCP client to WordPress to audit, redesign and optimize your site.

== Description ==

Atelier MCP exposes your site to AI agents through the Model Context Protocol (MCP), built on the WordPress Abilities API and the official MCP Adapter.

Read tools:

* atelier_site_info – versions, theme, plugins, SEO plugin.
* atelier_list_content / atelier_get_content – pages, posts and their block tree.
* atelier_get_global_styles – theme.json palette, typography, spacing.
* atelier_list_templates – block templates and template parts.
* atelier_list_patterns – block patterns and block types.
* atelier_list_media – images with size and alt text.
* atelier_audit_seo – missing meta, duplicate titles, H1, alt text.
* atelier_audit_performance – local audit, no external services.
* atelier_list_menus – navigation menus.

Write tools (can be disabled):

* atelier_create_content / atelier_update_content / atelier_update_seo_meta
* atelier_update_global_styles / atelier_update_template / atelier_update_menu
* atelier_upload_media
* atelier_list_changes / atelier_rollback – every change is logged and can be undone.
* atelier_publish – only in Full access mode.

Publishing modes:

* With approval (default, recommended): the agent only creates drafts. Edits to published pages go to a draft copy; when a human publishes it, the changes are applied to the original. Site-wide design changes are proposals (dry run) only.
* Full access: the agent publishes and changes styles, templates and menus live.

Every tool respects WordPress capabilities of the authenticated user. No data is sent to third parties by this plugin.

== Installation ==

1. Install and activate the [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases/latest) plugin.
2. Install and activate Atelier MCP.
3. Create an Application Password (Users → Profile).
4. Go to Settings → Atelier MCP and copy the connection command for Claude Code, Codex or Claude Desktop.

== Changelog ==

= 0.2.0 =
* Write tools, change log with one-click rollback, approval and full access modes, rate limit, SEO tags fallback.

= 0.1.0 =
* Read-only tools: site info, content, global styles, templates, patterns, media, SEO and performance audits.
