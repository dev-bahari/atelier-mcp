=== Atelier MCP ===
Contributors: bahari
Tags: mcp, ai, claude, seo, performance
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Claude, Codex or any MCP client to WordPress to audit, redesign and optimize your site.

== Description ==

Atelier MCP exposes your site to AI agents through the Model Context Protocol (MCP), built on the WordPress Abilities API and the official MCP Adapter.

Tools in this version (read-only):

* atelier_site_info – versions, theme, plugins, SEO plugin.
* atelier_list_content / atelier_get_content – pages, posts and their block tree.
* atelier_get_global_styles – theme.json palette, typography, spacing.
* atelier_list_templates – block templates and template parts.
* atelier_list_patterns – block patterns and block types.
* atelier_list_media – images with size and alt text.
* atelier_audit_seo – missing meta, duplicate titles, H1, alt text.
* atelier_audit_performance – local audit, no external services.

Every tool respects WordPress capabilities of the authenticated user. No data is sent to third parties by this plugin.

== Installation ==

1. Install and activate the [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases/latest) plugin.
2. Install and activate Atelier MCP.
3. Create an Application Password (Users → Profile).
4. Go to Settings → Atelier MCP and copy the connection command for Claude Code, Codex or Claude Desktop.

== Changelog ==

= 0.1.0 =
* Read-only tools: site info, content, global styles, templates, patterns, media, SEO and performance audits.
