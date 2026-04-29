<?php
/**
 * Logging system for expired content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CES_Log {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_log_page' ] );
		add_action( 'admin_init', [ $this, 'handle_undo_action' ] );
	}

	/**
	 * Create the log table in database.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ces_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			post_title text NOT NULL,
			post_type varchar(20) NOT NULL,
			expired_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			action_taken varchar(50) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add an entry to the log.
	 */
	public static function add_entry( $post_id, $action ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ces_log';

		$wpdb->insert(
			$table_name,
			[
				'post_id'      => $post_id,
				'post_title'   => get_the_title( $post_id ),
				'post_type'    => get_post_type( $post_id ),
				'expired_at'   => current_time( 'mysql' ),
				'action_taken' => $action,
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);

		// Cleanup old logs (> 90 days)
		$wpdb->query( $wpdb->prepare( 
			"DELETE FROM $table_name WHERE expired_at < DATE_SUB(NOW(), INTERVAL 90 DAY)" 
		) );
	}

	/**
	 * Add log page to Tools menu.
	 */
	public function add_log_page() {
		add_management_page(
			__( 'Expiry Log', 'content-expiry-scheduler' ),
			__( 'Expiry Log', 'content-expiry-scheduler' ),
			'manage_options',
			'ces-expiry-log',
			[ $this, 'render_log_page' ]
		);
	}

	/**
	 * Handle the undo (re-publish) action.
	 */
	public function handle_undo_action() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'ces-expiry-log' && isset( $_GET['action'] ) && $_GET['action'] === 'undo' ) {
			check_admin_referer( 'ces_undo_expiry' );

			$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
			if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
				wp_update_post( [
					'ID'          => $post_id,
					'post_status' => 'publish',
				] );
				
				// Clear expiry date so it doesn't expire immediately again
				delete_post_meta( $post_id, '_ces_expiry_date' );
				delete_post_meta( $post_id, '_ces_processed' );

				wp_redirect( admin_url( 'tools.php?page=ces-expiry-log&message=undone' ) );
				exit;
			}
		}
	}

	/**
	 * Render the log page HTML.
	 */
	public function render_log_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ces_log';
		
		$filter_type = isset( $_GET['post_type_filter'] ) ? sanitize_text_field( $_GET['post_type_filter'] ) : '';
		
		$query = "SELECT * FROM $table_name";
		if ( ! empty( $filter_type ) ) {
			$query .= $wpdb->prepare( " WHERE post_type = %s", $filter_type );
		}
		$query .= " ORDER BY expired_at DESC LIMIT 100";
		
		$logs = $wpdb->get_results( $query );
		$post_types = $wpdb->get_col( "SELECT DISTINCT post_type FROM $table_name" );

		?>
		<div class="wrap">
			<h1><?php _e( 'Content Expiry Log', 'content-expiry-scheduler' ); ?></h1>
			
			<?php if ( isset( $_GET['message'] ) && $_GET['message'] === 'undone' ) : ?>
				<div class="updated notice is-dismissible"><p><?php _e( 'Post re-published and expiry cleared.', 'content-expiry-scheduler' ); ?></p></div>
			<?php endif; ?>

			<form method="get" action="">
				<input type="hidden" name="page" value="ces-expiry-log">
				<div class="tablenav top">
					<div class="alignleft actions">
						<select name="post_type_filter">
							<option value=""><?php _e( 'All Post Types', 'content-expiry-scheduler' ); ?></option>
							<?php foreach ( $post_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_type, $type ); ?>><?php echo esc_html( $type ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="submit" class="button" value="<?php _e( 'Filter', 'content-expiry-scheduler' ); ?>">
					</div>
				</div>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Post Title', 'content-expiry-scheduler' ); ?></th>
						<th><?php _e( 'Post Type', 'content-expiry-scheduler' ); ?></th>
						<th><?php _e( 'Expired At', 'content-expiry-scheduler' ); ?></th>
						<th><?php _e( 'Action Taken', 'content-expiry-scheduler' ); ?></th>
						<th><?php _e( 'Actions', 'content-expiry-scheduler' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $log->post_title ); ?></strong></td>
								<td><?php echo esc_html( $log->post_type ); ?></td>
								<td><?php echo esc_html( $log->expired_at ); ?></td>
								<td><?php echo esc_html( ucfirst( $log->action_taken ) ); ?></td>
								<td>
									<?php 
									$undo_url = wp_nonce_url( admin_url( 'tools.php?page=ces-expiry-log&action=undo&post_id=' . $log->post_id ), 'ces_undo_expiry' );
									?>
									<a href="<?php echo $undo_url; ?>" class="button button-small"><?php _e( 'Undo & Re-publish', 'content-expiry-scheduler' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php _e( 'No expiry logs found.', 'content-expiry-scheduler' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
