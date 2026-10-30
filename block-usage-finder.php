<?php
/**
 * Plugin Name:       Block Usage Finder
 * Plugin URI:        https://github.com/samiahmedsiddiqui/block-usage-finder
 * Description:       Debugging tool under Tools that lists which blocks are used on which posts, including blocks that are no longer registered.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sami Ahmed Siddiqui
 * Author URI:        https://www.linkedin.com/in/sami-ahmed-siddiqui/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       block-usage-finder
 * GitHub Plugin URI: samiahmedsiddiqui/block-usage-finder
 * Primary Branch:    main
 *
 * @package block-usage-finder
 */

defined( 'ABSPATH' ) || exit;

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
 * @return string[] Post type name => label.
 */
function block_usage_post_types() {
	$types = array();

	foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $object ) {
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
 * Scan posts and group the blocks they use.
 *
 * @param string   $post_type Post type to scan.
 * @param string[] $statuses  Post statuses to scan.
 *
 * @return array {
 *     @type int   $scanned Number of posts containing blocks.
 *     @type array $blocks  Block name => list of posts using it.
 * }
 */
function block_usage_scan( $post_type, $statuses ) {
	global $wpdb;

	$blocks       = array();
	$scanned      = 0;
	$last_id      = 0;
	$batch        = 200;
	$count        = $batch;
	$like         = '%' . $wpdb->esc_like( '<!-- wp:' ) . '%';
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$sql          = "SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( {$placeholders} ) AND post_content LIKE %s AND ID > %d ORDER BY ID ASC LIMIT %d";

	while ( $count === $batch ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $sql, array_merge( array( $post_type ), $statuses, array( $like, $last_id, $batch ) ) )
		);
		$count = count( $rows );

		foreach ( $rows as $row ) {
			$last_id = (int) $row->ID;
			$counts  = array();
			++$scanned;

			block_usage_count( parse_blocks( $row->post_content ), $counts );

			foreach ( $counts as $name => $uses ) {
				$blocks[ $name ][] = array(
					'id'     => $last_id,
					'title'  => $row->post_title,
					'status' => $row->post_status,
					'count'  => $uses,
				);
			}
		}
	}

	ksort( $blocks );

	return array(
		'scanned' => $scanned,
		'blocks'  => $blocks,
	);
}

/**
 * Render the Block Usage page.
 */
function block_usage_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_types = block_usage_post_types();
	$statuses   = block_usage_statuses();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$submitted    = isset( $_GET['scan'] );
	$post_type    = isset( $_GET['scan_type'] ) ? sanitize_key( wp_unslash( $_GET['scan_type'] ) ) : 'page';
	$chosen       = isset( $_GET['statuses'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_GET['statuses'] ) ) : array( 'publish' );
	$search       = isset( $_GET['block'] ) ? sanitize_text_field( wp_unslash( $_GET['block'] ) ) : '';
	$only_missing = ! empty( $_GET['only_missing'] );
	$with_core    = ! empty( $_GET['with_core'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! isset( $post_types[ $post_type ] ) ) {
		$post_type = 'page';
	}

	$chosen = array_values( array_intersect( $chosen, array_keys( $statuses ) ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Block Usage', 'block-usage-finder' ); ?></h1>

		<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
			<input type="hidden" name="page" value="block-usage-finder">
			<input type="hidden" name="scan" value="1">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bu-post-type"><?php esc_html_e( 'Post type', 'block-usage-finder' ); ?></label></th>
					<td>
						<select name="scan_type" id="bu-post-type">
							<?php foreach ( $post_types as $name => $label ) : ?>
								<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $post_type, $name ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post status', 'block-usage-finder' ); ?></th>
					<td>
						<?php foreach ( $statuses as $name => $label ) : ?>
							<label style="margin-right: 16px;">
								<input type="checkbox" name="statuses[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $chosen, true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bu-block"><?php esc_html_e( 'Block name', 'block-usage-finder' ); ?></label></th>
					<td>
						<input type="text" name="block" id="bu-block" class="regular-text" value="<?php echo esc_attr( $search ); ?>" placeholder="namespace/block-name">
						<p class="description"><?php esc_html_e( 'Optional. Show only blocks whose name contains this text.', 'block-usage-finder' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Options', 'block-usage-finder' ); ?></th>
					<td>
						<label style="margin-right: 16px;">
							<input type="checkbox" name="only_missing" value="1" <?php checked( $only_missing ); ?>>
							<?php esc_html_e( 'Only blocks that are not registered', 'block-usage-finder' ); ?>
						</label>
						<label>
							<input type="checkbox" name="with_core" value="1" <?php checked( $with_core ); ?>>
							<?php esc_html_e( 'Include core blocks', 'block-usage-finder' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Scan', 'block-usage-finder' ), 'primary', '' ); ?>
		</form>

		<?php
		if ( $submitted ) {
			if ( empty( $chosen ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Select at least one post status.', 'block-usage-finder' ) . '</p></div>';
			} else {
				block_usage_results( $post_type, $chosen, $search, $only_missing, $with_core );
			}
		}
		?>
	</div>
	<?php
}

/**
 * Render the scan results table.
 *
 * @param string   $post_type    Post type to scan.
 * @param string[] $statuses     Post statuses to scan.
 * @param string   $search       Block name filter.
 * @param bool     $only_missing Show only unregistered blocks.
 * @param bool     $with_core    Include core blocks.
 */
function block_usage_results( $post_type, $statuses, $search, $only_missing, $with_core ) {
	$result   = block_usage_scan( $post_type, $statuses );
	$registry = WP_Block_Type_Registry::get_instance();
	$rows     = 0;

	printf(
		'<p>%s</p>',
		esc_html(
			sprintf(
				/* translators: %d: number of posts. */
				_n( '%d post with blocks scanned.', '%d posts with blocks scanned.', $result['scanned'], 'block-usage-finder' ),
				$result['scanned']
			)
		)
	);
	?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Block', 'block-usage-finder' ); ?></th>
				<th><?php esc_html_e( 'Registered', 'block-usage-finder' ); ?></th>
				<th><?php esc_html_e( 'Used on', 'block-usage-finder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $result['blocks'] as $name => $posts ) {
				$registered = $registry->is_registered( $name );

				if ( ( ! $with_core && 0 === strpos( $name, 'core/' ) )
					|| ( $only_missing && $registered )
					|| ( '' !== $search && false === stripos( $name, $search ) ) ) {
					continue;
				}

				++$rows;
				?>
				<tr>
					<td><code><?php echo esc_html( $name ); ?></code></td>
					<td>
						<?php if ( $registered ) : ?>
							<?php esc_html_e( 'Yes', 'block-usage-finder' ); ?>
						<?php else : ?>
							<strong style="color: #b32d2e;"><?php esc_html_e( 'Missing', 'block-usage-finder' ); ?></strong>
						<?php endif; ?>
					</td>
					<td>
						<?php
						/* translators: %d: number of posts. */
						echo esc_html( sprintf( _n( '%d post', '%d posts', count( $posts ), 'block-usage-finder' ), count( $posts ) ) );
						?>
						<ul style="margin: 6px 0 0 18px; list-style: disc;">
							<?php foreach ( $posts as $post ) : ?>
								<?php
								$title = '' !== $post['title'] ? $post['title'] : __( '(no title)', 'block-usage-finder' );
								$link  = get_edit_post_link( $post['id'] );
								?>
								<li>
									<?php if ( $link ) : ?>
										<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $title ); ?>
									<?php endif; ?>
									<span class="description">
										<?php echo esc_html( sprintf( '#%d, %s, x%d', $post['id'], $post['status'], $post['count'] ) ); ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</td>
				</tr>
				<?php
			}

			if ( 0 === $rows ) :
				?>
				<tr><td colspan="3"><?php esc_html_e( 'No blocks found.', 'block-usage-finder' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
}
