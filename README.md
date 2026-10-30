# Block Usage Finder

A WordPress debugging tool that shows which blocks are used on which posts, including blocks whose plugin or theme code has been removed.

Find it under **Tools > Block Usage**.

## Features

- Scan one post type or all of them, with a multi-select post status filter (published, draft, private and so on).
- Includes patterns (synced patterns), navigation menus, templates and template parts. Templates and template parts are listed only when they have been customized and saved in the database.
- Show only blocks that are not registered, to find content left behind by removed plugins or themes.
- Optionally include core blocks, which are hidden by default.
- Filter by block name, sort by name, number of posts or total uses, and group by namespace. These work on the finished scan, with no rescan.
- Each block lists the posts that use it, how many times, and an edit link. Long lists are collapsed.
- A totals row shows how many blocks are listed and how many are not registered.
- Export the listed results as a CSV file, one row per block and post.
- The scan runs in batches of 100 posts with a progress message and a Stop button, so large sites do not time out.
- Nested blocks are counted.
- A WP-CLI command for servers, see below.
- Nothing is cached or stored. Each scan reads the current content, and no options are saved.

## WP-CLI

```bash
wp block-usage-finder scan --only-missing
wp block-usage-finder scan --post-type=page --status=publish,draft --block=acf/ --format=csv
```

Options: `--post-type` (a post type or `all`, the default), `--status` (comma-separated, default `publish`), `--block`, `--only-missing`, `--with-core` and `--format` (`table`, `csv`, `json`, `yaml` or `count`). It prints one row per block and post.

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later
- A user with the `manage_options` capability

## Installation

1. Download the latest zip from the [releases page](https://github.com/samiahmedsiddiqui/block-usage-finder/releases).
2. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and activate it.

The folder inside the zip must be named `block-usage-finder`. Do not use the green **Code > Download ZIP** button, because it names the folder `block-usage-finder-main`.

## Updates from GitHub

The plugin header supports [Git Updater](https://git-updater.com/). With Git Updater installed, you can install the plugin from this repository's URL and it will show updates when a new release is published.

Without Git Updater, download the new release zip and upload it over the old one.

## Development

```bash
composer install
composer lint
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
