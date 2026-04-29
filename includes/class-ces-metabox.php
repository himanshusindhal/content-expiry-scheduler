<?php
/**
 * Meta box handler for per-post expiry settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CES_Metabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_expiry_metabox' ] );
		add_action( 'save_post', [ $this, 'save_expiry_data' ] );
	}

	/**
	 * Add meta box to enabled post types.
	 */
	public function add_expiry_metabox() {
		$options = get_option( 'ces_settings' );
		$enabled_types = isset( $options['post_types'] ) ? $options['post_types'] : [];

		if ( empty( $enabled_types ) ) {
			return;
		}

		add_meta_box(
			'ces_expiry_metabox',
			__( 'Content Expiry', 'content-expiry-scheduler' ),
			[ $this, 'render_metabox' ],
			$enabled_types,
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box HTML.
	 */
	public function render_metabox( $post ) {
		// Add nonce for security
		wp_nonce_field( 'ces_save_metabox', 'ces_metabox_nonce' );

		// Get existing values
		$expiry_date    = get_post_meta( $post->ID, '_ces_expiry_date', true );
		$expiry_action  = get_post_meta( $post->ID, '_ces_expiry_action', true );
		$expiry_redirect = get_post_meta( $post->ID, '_ces_expiry_redirect', true );
		$expiry_message  = get_post_meta( $post->ID, '_ces_expiry_message', true );

		// Default values if not set
		if ( ! $expiry_action ) {
			$expiry_action = 'default';
		}

		?>
		<div class="ces-metabox-content">
			<p>
				<label for="ces_expiry_date"><strong><?php _e( 'Expire on:', 'content-expiry-scheduler' ); ?></strong></label><br>
				<input type="datetime-local" id="ces_expiry_date" name="ces_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>" style="width:100%; margin-top:5px;">
			</p>

			<p>
				<strong><?php _e( 'On expiry, do:', 'content-expiry-scheduler' ); ?></strong><br>
				<label><input type="radio" name="ces_expiry_action" value="default" <?php checked( $expiry_action, 'default' ); ?>> <?php _e( 'Use Global Default', 'content-expiry-scheduler' ); ?></label><br>
				<label><input type="radio" name="ces_expiry_action" value="draft" <?php checked( $expiry_action, 'draft' ); ?>> <?php _e( 'Auto-draft', 'content-expiry-scheduler' ); ?></label><br>
				<label><input type="radio" name="ces_expiry_action" value="redirect" <?php checked( $expiry_action, 'redirect' ); ?>> <?php _e( 'Redirect to URL', 'content-expiry-scheduler' ); ?></label><br>
				<label><input type="radio" name="ces_expiry_action" value="message" <?php checked( $expiry_action, 'message' ); ?>> <?php _e( 'Show custom message', 'content-expiry-scheduler' ); ?></label>
			</p>

			<div id="ces_redirect_field" style="display: <?php echo ( $expiry_action === 'redirect' ) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
				<label for="ces_expiry_redirect"><?php _e( 'Redirect URL:', 'content-expiry-scheduler' ); ?></label>
				<input type="url" id="ces_expiry_redirect" name="ces_expiry_redirect" value="<?php echo esc_url( $expiry_redirect ); ?>" class="widefat">
			</div>

			<div id="ces_message_field" style="display: <?php echo ( $expiry_action === 'message' ) ? 'block' : 'none'; ?>; margin-bottom: 10px;">
				<label for="ces_expiry_message"><?php _e( 'Custom Message:', 'content-expiry-scheduler' ); ?></label>
				<textarea id="ces_expiry_message" name="ces_expiry_message" rows="3" class="widefat"><?php echo esc_textarea( $expiry_message ); ?></textarea>
			</div>

			<div id="ces-status-badge-container" class="ces-status-badge" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
				<?php $this->render_status_badge( $post->ID, $expiry_date ); ?>
			</div>
		</div>

		<script type="text/javascript">
			(function($) {
				$('input[name="ces_expiry_action"]').on('change', function() {
					var val = $(this).val();
					$('#ces_redirect_field').toggle(val === 'redirect');
					$('#ces_message_field').toggle(val === 'message');
				});

				// Auto-refresh status badge if pending
				if ($('#ces-status-badge-container').text().indexOf('Pending') !== -1) {
					setTimeout(function() {
						location.reload();
					}, 5000);
				}
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Render the status badge.
	 */
	private function render_status_badge( $post_id, $expiry_date ) {
		if ( empty( $expiry_date ) ) {
			_e( 'No expiry scheduled.', 'content-expiry-scheduler' );
			return;
		}

		$expiry_timestamp = strtotime( $expiry_date );
		$now = current_time( 'timestamp' );
		$post_status = get_post_status( $post_id );

		if ( $post_status !== 'publish' && $expiry_timestamp <= $now ) {
			echo '<span style="color: #d63638;">' . sprintf( 
				__( 'Expired on %s · %s', 'content-expiry-scheduler' ), 
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry_timestamp ),
				ucfirst( $post_status )
			) . '</span>';
		} elseif ( $post_status === 'publish' && $expiry_timestamp > $now ) {
			$diff = human_time_diff( $now, $expiry_timestamp );
			echo '<span style="color: #46b450;">' . sprintf( 
				__( 'Active · Expires in %s', 'content-expiry-scheduler' ), 
				$diff
			) . '</span>';
		} elseif ( $post_status === 'publish' && $expiry_timestamp <= $now ) {
			// If it's published but time has passed, trigger a check immediately
			echo '<span style="color: #d63638;">' . __( 'Expired · Processing...', 'content-expiry-scheduler' ) . '</span>';
			// In a real environment, we might trigger the cron check here via AJAX or a direct call
			if ( class_exists( 'CES_Cron' ) ) {
				$cron = new CES_Cron();
				$cron->process_expiry( $post_id );
			}
		} else {
			_e( 'Scheduled for ', 'content-expiry-scheduler' );
			echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expiry_timestamp );
		}
	}

	/**
	 * Save meta box data.
	 */
	public function save_expiry_data( $post_id ) {
		// Verify nonce
		if ( ! isset( $_POST['ces_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['ces_metabox_nonce'], 'ces_save_metabox' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save fields
		if ( isset( $_POST['ces_expiry_date'] ) ) {
			$old_date = get_post_meta( $post_id, '_ces_expiry_date', true );
			$new_date = sanitize_text_field( $_POST['ces_expiry_date'] );
			update_post_meta( $post_id, '_ces_expiry_date', $new_date );
			
			// If date changed, clear processed flag
			if ( $old_date !== $new_date ) {
				delete_post_meta( $post_id, '_ces_processed' );
			}
		}

		if ( isset( $_POST['ces_expiry_action'] ) ) {
			update_post_meta( $post_id, '_ces_expiry_action', sanitize_text_field( $_POST['ces_expiry_action'] ) );
		}

		if ( isset( $_POST['ces_expiry_redirect'] ) ) {
			update_post_meta( $post_id, '_ces_expiry_redirect', esc_url_raw( $_POST['ces_expiry_redirect'] ) );
		}

		if ( isset( $_POST['ces_expiry_message'] ) ) {
			update_post_meta( $post_id, '_ces_expiry_message', wp_kses_post( $_POST['ces_expiry_message'] ) );
		}
	}
}
