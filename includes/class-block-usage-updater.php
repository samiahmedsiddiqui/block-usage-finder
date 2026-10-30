<?php
/**
 * Update checks against the GitHub releases of this plugin.
 *
 * @package block-usage-finder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offers new GitHub releases as plugin updates.
 */
class Block_Usage_Updater {

	const RELEASE_API = 'https://api.github.com/repos/samiahmedsiddiqui/block-usage-finder/releases/latest';
	const PACKAGE_URL = 'https://github.com/samiahmedsiddiqui/block-usage-finder/releases/download/';
	const ASSET_NAME  = 'block-usage-finder.zip';

	/**
	 * Hook into the plugin update check.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
	}

	/**
	 * Answer WordPress's update check for this plugin.
	 *
	 * @param array|false $update      Update data from an earlier filter.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin file relative to the plugins directory.
	 *
	 * @return array|false Update data, or false when there is none.
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( dirname( __DIR__ ) . '/block-usage-finder.php' ) !== $plugin_file ) {
			return $update;
		}

		/**
		 * Filters whether the plugin checks GitHub for updates.
		 *
		 * @param bool $enabled Whether to check. Default true.
		 */
		if ( ! apply_filters( 'block_usage_check_updates', true ) ) {
			return false;
		}

		$release = self::latest_release();

		if ( ! $release ) {
			return false;
		}

		return array(
			'id'      => $plugin_data['UpdateURI'],
			'slug'    => 'block-usage-finder',
			'plugin'  => $plugin_file,
			'version' => $release['version'],
			'url'     => $release['url'],
			'package' => $release['package'],
		);
	}

	/**
	 * Fetch the latest release from GitHub.
	 *
	 * @return array|false Version, release page URL and zip URL, or false on failure.
	 */
	private static function latest_release() {
		$response = wp_remote_get(
			self::RELEASE_API,
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $release['tag_name'] ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
			return false;
		}

		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			$package = $asset['browser_download_url'] ?? '';

			if ( self::ASSET_NAME === ( $asset['name'] ?? '' ) && 0 === strpos( $package, self::PACKAGE_URL ) ) {
				return array(
					'version' => ltrim( $release['tag_name'], 'v' ),
					'url'     => esc_url_raw( $release['html_url'] ?? '' ),
					'package' => $package,
				);
			}
		}

		return false;
	}
}

Block_Usage_Updater::init();
