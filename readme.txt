=== Block Usage Finder ===
Contributors: sasiddiqui
Tags: blocks, gutenberg, debug, block editor
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows which blocks are used on which posts, including blocks that are no longer registered.

== Description ==

Block Usage Finder adds a **Tools > Block Usage** page for debugging block content.

* Scan one post type at a time, with a multi-select post status filter.
* Filter by block name.
* Show only blocks that are not registered, to find content left behind by removed plugins or themes.
* Optionally include core blocks.
* See which posts use each block, how many times, with edit links. Nested blocks are counted.
* Nothing is cached or stored.

== Installation ==

1. Upload the `block-usage-finder` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to Tools > Block Usage.

== Changelog ==

= 1.0.0 =
* Initial release.
