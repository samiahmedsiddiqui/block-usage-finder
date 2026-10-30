# Block Usage Finder

A WordPress debugging tool that shows which blocks are used on which posts, including blocks whose plugin or theme code has been removed.

Find it under **Tools > Block Usage**.

## Features

- Scan one post type at a time, with a multi-select post status filter (published, draft, private and so on).
- Filter by block name, for example `acf/` or `my-plugin/hero`.
- Show only blocks that are not registered, to find content left behind by removed plugins or themes.
- Optionally include core blocks, which are hidden by default.
- Each block lists the posts that use it, how many times, and an edit link.
- Nested blocks are counted.
- Nothing is cached or stored. Each scan reads the current content, and no options are saved.

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
