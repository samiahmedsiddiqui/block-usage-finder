=== Block Usage Finder ===
Contributors: sasiddiqui
Tags: blocks, gutenberg, debug, block editor
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows which blocks are used on which posts, templates and patterns, including blocks that are no longer registered.

== Description ==

Block Usage Finder adds a **Tools > Block Usage** page for debugging block content.

* Scan one post type or all of them, with a multi-select post status filter.
* Includes patterns, navigation menus, templates and template parts. Templates and template parts are listed only when they have been customized and saved in the database.
* Show only blocks that are not registered, to find content left behind by removed plugins or themes.
* Optionally include core blocks.
* Filter by block name, sort by name, number of posts or total uses, and group by namespace.
* See which posts use each block, how many times, with edit links. Nested blocks are counted.
* Export the results as a CSV file.
* The scan runs in batches with a Stop button, so large sites do not time out.
* A WP-CLI command, `wp block-usage-finder scan`, for servers.
* Scan results are not cached or stored.
* Shows update notices from the plugin's GitHub releases. Disable them with `add_filter( 'block_usage_check_updates', '__return_false' );`.

== Installation ==

1. Upload the `block-usage-finder` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Tools > Block Usage.

== Changelog ==

= 1.0.0 =
* Initial release.
* Scan one post type or all of them, with templates, template parts and navigation menus.
* Scan in batches with progress and a Stop button.
* Filter, sort and group the results, collapse long post lists and export CSV.
* WP-CLI command: `wp block-usage-finder scan`.
* Update notices from the plugin's GitHub releases.
