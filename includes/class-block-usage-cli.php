<?php
/**
 * WP-CLI command for scanning block usage.
 *
 * @package block-usage-finder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists which blocks are used on which posts.
 */
class Block_Usage_CLI {

	/**
	 * Scan posts and list the blocks they use, one row per block and post.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<post-type>]
	 * : Post type to scan, or `all`.
	 * ---
	 * default: all
	 * ---
	 *
	 * [--status=<status>]
	 * : Comma-separated post statuses to scan.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--block=<text>]
	 * : Only list blocks whose name contains this text.
	 *
	 * [--only-missing]
	 * : Only list blocks that are not registered.
	 *
	 * [--with-core]
	 * : Include core blocks.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp block-usage-finder scan --only-missing
	 *     wp block-usage-finder scan --post-type=page --status=publish,draft --block=acf/ --format=csv
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function scan( $args, $assoc_args ) {
		$post_types = block_usage_post_types();
		$statuses   = block_usage_statuses();
		$type       = WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', 'all' );
		$chosen     = array_filter( array_map( 'trim', explode( ',', WP_CLI\Utils\get_flag_value( $assoc_args, 'status', 'publish' ) ) ) );
		$search     = WP_CLI\Utils\get_flag_value( $assoc_args, 'block', '' );
		$only_miss  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'only-missing', false );
		$with_core  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'with-core', false );
		$format     = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( 'all' !== $type && ! isset( $post_types[ $type ] ) ) {
			WP_CLI::error( 'Unknown post type. Use one of: all, ' . implode( ', ', array_keys( $post_types ) ) );
		}

		$unknown = array_diff( $chosen, array_keys( $statuses ) );

		if ( empty( $chosen ) || $unknown ) {
			WP_CLI::error( 'Unknown post status. Use one of: ' . implode( ', ', array_keys( $statuses ) ) );
		}

		$types = 'all' === $type ? array_keys( $post_types ) : array( $type );
		$rows  = array();

		foreach ( $types as $post_type ) {
			$after = 0;
			$done  = false;

			while ( ! $done ) {
				$result = block_usage_scan( $post_type, $chosen, $after, 100 );
				$after  = $result['last'];
				$done   = $result['done'];

				foreach ( $result['blocks'] as $name => $posts ) {
					$registered = $result['registered'][ $name ];

					if ( ( ! $with_core && 0 === strpos( $name, 'core/' ) )
						|| ( $only_miss && $registered )
						|| ( '' !== $search && false === stripos( $name, $search ) ) ) {
						continue;
					}

					foreach ( $posts as $post ) {
						$rows[] = array(
							'block'      => $name,
							'registered' => $registered ? 'yes' : 'no',
							'post_type'  => $post['type'],
							'post_id'    => $post['id'],
							'title'      => $post['title'],
							'status'     => $post['status'],
							'uses'       => $post['count'],
						);
					}
				}
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['block'], $b['block'] ) ? strcmp( $a['block'], $b['block'] ) : $a['post_id'] - $b['post_id'];
			}
		);

		WP_CLI\Utils\format_items( $format, $rows, array( 'block', 'registered', 'post_type', 'post_id', 'title', 'status', 'uses' ) );
	}
}

WP_CLI::add_command( 'block-usage-finder', 'Block_Usage_CLI' );
