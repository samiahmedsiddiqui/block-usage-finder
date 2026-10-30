<?php
/**
 * Plugin Name:       Block Usage Finder
 * Plugin URI:        https://github.com/samiahmedsiddiqui/block-usage-finder
 * Description:       Debugging tool under Tools that lists which blocks are used on which posts, templates and patterns, including blocks that are no longer registered.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sami Ahmed Siddiqui
 * Author URI:        https://www.linkedin.com/in/sami-ahmed-siddiqui/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       block-usage-finder
 * Update URI:        https://github.com/samiahmedsiddiqui/block-usage-finder
 *
 * @package block-usage-finder
 */

defined( 'ABSPATH' ) || exit;

define( 'BLOCK_USAGE_FINDER_VERSION', '1.0.0' );
define( 'BLOCK_USAGE_FINDER_URL', plugin_dir_url( __FILE__ ) );

require __DIR__ . '/includes/class-block-usage-updater.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require __DIR__ . '/includes/class-block-usage-cli.php';
}

/**
 * Add the Block Usage page under Tools.
 */
function block_usage_menu() {
	add_management_page(
		__( 'Block Usage', 'block-usage-finder' ),
		__( 'Block Usage', 'block-usage-finder' ),
		'manage_options',
		'block-usage-finder',
		'block_usage_page'
	);
}
add_action( 'admin_menu', 'block_usage_menu' );

/**
 * Post types that can be scanned, keyed by name.
 *
 * Includes the block theme types that are hidden from the admin menus.
 *
 * @return string[] Post type name => label.
 */
function block_usage_post_types() {
	$types   = array();
	$objects = get_post_types( array( 'show_ui' => true ), 'objects' );

	foreach ( array( 'wp_template', 'wp_template_part', 'wp_navigation' ) as $name ) {
		$object = get_post_type_object( $name );

		if ( $object ) {
			$objects[ $name ] = $object;
		}
	}

	foreach ( $objects as $name => $object ) {
		if ( 'attachment' !== $name ) {
			$types[ $name ] = $object->labels->name;
		}
	}

	return $types;
}

/**
 * Post statuses that can be scanned, keyed by name.
 *
 * @return string[] Post status name => label.
 */
function block_usage_statuses() {
	$statuses = array();

	foreach ( get_post_stati( array(), 'objects' ) as $name => $object ) {
		if ( 'auto-draft' !== $name ) {
			$statuses[ $name ] = $object->label;
		}
	}

	return $statuses;
}

/**
 * Count every block in a parsed block list, including nested blocks.
 *
 * @param array $blocks Parsed blocks.
 * @param array $counts Block name => number of uses, passed by reference.
 */
function block_usage_count( $blocks, &$counts ) {
	foreach ( $blocks as $block ) {
		if ( ! empty( $block['blockName'] ) ) {
			$counts[ $block['blockName'] ] = ( $counts[ $block['blockName'] ] ?? 0 ) + 1;
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			block_usage_count( $block['innerBlocks'], $counts );
		}
	}
}

/**
 * Scan one batch of posts and group the blocks they use.
 *
 * @param string   $post_type Post type to scan.
 * @param string[] $statuses  Post statuses to scan.
 * @param int      $after     Only scan posts with an ID above this one.
 * @param int      $limit     Maximum number of posts to read.
 *
 * @return array {
 *     @type int   $scanned    Number of posts containing blocks in this batch.
 *     @type int   $last       ID of the last post read, to pass as $after for the next batch.
 *     @type bool  $done       Whether there are no more posts to read.
 *     @type array $blocks     Block name => list of posts using it.
 *     @type array $registered Block name => whether the block is registered.
 * }
 */
function block_usage_scan( $post_type, $statuses, $after, $limit ) {
	global $wpdb;

	$blocks       = array();
	$registered   = array();
	$registry     = WP_Block_Type_Registry::get_instance();
	$last         = $after;
	$like         = '%' . $wpdb->esc_like( '<!-- wp:' ) . '%';
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$sql          = "SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( {$placeholders} ) AND post_content LIKE %s AND ID > %d ORDER BY ID ASC LIMIT %d";

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->prepare( $sql, array_merge( array( $post_type ), $statuses, array( $like, $after, $limit ) ) )
	);

	foreach ( $rows as $row ) {
		$last   = (int) $row->ID;
		$counts = array();
		$link   = get_edit_post_link( $last, 'raw' );

		block_usage_count( parse_blocks( $row->post_content ), $counts );

		foreach ( $counts as $name => $uses ) {
			if ( ! isset( $registered[ $name ] ) ) {
				$registered[ $name ] = $registry->is_registered( $name );
			}

			$blocks[ $name ][] = array(
				'id'     => $last,
				'title'  => $row->post_title,
				'status' => $row->post_status,
				'type'   => $post_type,
				'count'  => $uses,
				'link'   => $link ? $link : '',
			);
		}
	}

	return array(
		'scanned'    => count( $rows ),
		'last'       => $last,
		'done'       => count( $rows ) < $limit,
		'blocks'     => $blocks,
		'registered' => $registered,
	);
}

/**
 * Answer a scan request for one batch of posts.
 */
function block_usage_ajax_scan() {
	check_ajax_referer( 'block_usage_scan', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	$post_types = block_usage_post_types();
	$statuses   = block_usage_statuses();
	$post_type  = isset( $_POST['scan_type'] ) ? sanitize_key( wp_unslash( $_POST['scan_type'] ) ) : '';
	$chosen     = isset( $_POST['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['statuses'] ) ) : array();
	$after      = isset( $_POST['after'] ) ? absint( $_POST['after'] ) : 0;
	$chosen     = array_values( array_intersect( $chosen, array_keys( $statuses ) ) );

	if ( ! isset( $post_types[ $post_type ] ) || empty( $chosen ) ) {
		wp_send_json_error( null, 400 );
	}

	wp_send_json_success( block_usage_scan( $post_type, $chosen, $after, 100 ) );
}
add_action( 'wp_ajax_block_usage_scan', 'block_usage_ajax_scan' );

/**
 * Load the scripts and styles on the Block Usage page only.
 *
 * @param string $hook Current admin page hook.
 */
function block_usage_assets( $hook ) {
	if ( 'tools_page_block-usage-finder' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'block-usage-finder',
		BLOCK_USAGE_FINDER_URL . 'assets/block-usage-finder.css',
		array(),
		BLOCK_USAGE_FINDER_VERSION
	);

	wp_enqueue_script(
		'block-usage-finder',
		BLOCK_USAGE_FINDER_URL . 'assets/block-usage-finder.js',
		array( 'wp-i18n' ),
		BLOCK_USAGE_FINDER_VERSION,
		true
	);

	wp_set_script_translations( 'block-usage-finder', 'block-usage-finder' );

	wp_localize_script(
		'block-usage-finder',
		'blockUsageFinder',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'block_usage_scan' ),
			'postTypes' => block_usage_post_types(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'block_usage_assets' );

/**
 * Render the Block Usage page.
 */
function block_usage_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_types = block_usage_post_types();
	$statuses   = block_usage_statuses();
	?>
	<div class="wrap block-usage">
		<h1><?php esc_html_e( 'Block Usage', 'block-usage-finder' ); ?></h1>

		<form id="bu-form">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bu-post-type"><?php esc_html_e( 'Post type', 'block-usage-finder' ); ?></label></th>
					<td>
						<select name="scan_type" id="bu-post-type">
							<option value="__all"><?php esc_html_e( 'All post types', 'block-usage-finder' ); ?></option>
							<?php foreach ( $post_types as $name => $label ) : ?>
								<option value="<?php echo esc_attr( $name ); ?>" <?php selected( 'page', $name ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Templates and template parts are listed only when they have been customized and saved in the database.', 'block-usage-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post status', 'block-usage-finder' ); ?></th>
					<td>
						<?php foreach ( $statuses as $name => $label ) : ?>
							<label class="block-usage__option">
								<input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( 'publish', $name ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="bu-scan"><?php esc_html_e( 'Scan', 'block-usage-finder' ); ?></button>
				<button type="button" class="button" id="bu-stop" hidden><?php esc_html_e( 'Stop', 'block-usage-finder' ); ?></button>
			</p>
		</form>

		<div id="bu-progress" class="block-usage__progress" hidden>
			<progress id="bu-bar" max="100"></progress>
			<span id="bu-status" role="status"></span>
		</div>

		<div id="bu-notice" role="alert"></div>

		<div id="bu-results" hidden>
			<p id="bu-summary"></p>

			<div class="block-usage__filters">
				<label>
					<?php esc_html_e( 'Block name', 'block-usage-finder' ); ?>
					<input type="search" id="bu-filter" placeholder="namespace/block-name">
				</label>
				<label>
					<?php esc_html_e( 'Sort by', 'block-usage-finder' ); ?>
					<select id="bu-sort">
						<option value="name"><?php esc_html_e( 'Block name', 'block-usage-finder' ); ?></option>
						<option value="posts"><?php esc_html_e( 'Number of posts', 'block-usage-finder' ); ?></option>
						<option value="uses"><?php esc_html_e( 'Total uses', 'block-usage-finder' ); ?></option>
					</select>
				</label>
				<label><input type="checkbox" id="bu-missing"> <?php esc_html_e( 'Only blocks that are not registered', 'block-usage-finder' ); ?></label>
				<label><input type="checkbox" id="bu-core"> <?php esc_html_e( 'Include core blocks', 'block-usage-finder' ); ?></label>
				<label><input type="checkbox" id="bu-group"> <?php esc_html_e( 'Group by namespace', 'block-usage-finder' ); ?></label>
				<button type="button" class="button" id="bu-export"><?php esc_html_e( 'Export CSV', 'block-usage-finder' ); ?></button>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Block', 'block-usage-finder' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'block-usage-finder' ); ?></th>
						<th><?php esc_html_e( 'Used on', 'block-usage-finder' ); ?></th>
					</tr>
				</thead>
				<tbody id="bu-rows"></tbody>
				<tfoot>
					<tr>
						<th colspan="3" id="bu-totals"></th>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
	<?php
}
