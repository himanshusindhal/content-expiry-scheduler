<?php
/**
 * Expiry engine handled via WP-Cron and AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CES_Cron {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'ces_run_expiry_check', [ $this, 'check_expired_content' ] );
		add_action( 'template_redirect', [ $this, 'handle_frontend_expiry' ] );
		add_filter( 'the_content', [ $this, 'handle_message_replacement' ] );
		
		// AJAX for timer completion
		add_action( 'wp_ajax_ces_trigger_expiry', [ $this, 'ajax_trigger_expiry' ] );
		add_action( 'wp_ajax_nopriv_ces_trigger_expiry', [ $this, 'ajax_trigger_expiry' ] );
		
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
	}

	/**
	 * Enqueue frontend timer script.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		$expiry_date = get_post_meta( $post_id, '_ces_expiry_date', true );
		
		if ( empty( $expiry_date ) ) {
			return;
		}

		// Convert expiry date to UTC timestamp for JS
		$expiry_timestamp = strtotime( $expiry_date );
		// Get the offset in seconds from UTC
		$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
		// UTC timestamp of the expiry
		$utc_expiry_timestamp = $expiry_timestamp - $offset;

		$now = current_time( 'timestamp' );
		
		// If already expired, no need for timer (handled by template_redirect or the_content)
		if ( $expiry_timestamp <= $now ) {
			return;
		}

		// Inline script for timer
		$script = "
		(function() {
			function initCESTimer() {
				var expiryTimeUTC = " . ( $utc_expiry_timestamp * 1000 ) . ";
				var postId = " . $post_id . ";
				var timerExecuted = false;

				function checkTimer() {
					if (timerExecuted) return;
					
					var nowUTC = new Date().getTime();
					if (nowUTC >= expiryTimeUTC) {
						timerExecuted = true;
						triggerExpiry();
					}
				}

				function triggerExpiry() {
					var xhr = new XMLHttpRequest();
					xhr.open('POST', '" . admin_url( 'admin-ajax.php' ) . "', true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onload = function() {
						if (xhr.status === 200) {
							try {
								var response = JSON.parse(xhr.responseText);
								if (response.success) {
									if (response.data.action === 'redirect' && response.data.url) {
										window.location.href = response.data.url;
									} else {
										window.location.reload();
									}
								}
							} catch (e) {
								console.error('CES: Failed to parse response');
							}
						}
					};
					xhr.send('action=ces_trigger_expiry&post_id=' + postId + '&nonce=" . wp_create_nonce( 'ces_expiry_nonce' ) . "');
				}

				setInterval(checkTimer, 1000);
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', initCESTimer);
			} else {
				initCESTimer();
			}
			
			document.addEventListener('ajaxComplete', initCESTimer);
			document.addEventListener('pjax:end', initCESTimer);
		})();";

		wp_add_inline_script( 'jquery-core', $script );
	}

	/**
	 * AJAX handler for when timer hits zero.
	 */
	public function ajax_trigger_expiry() {
		check_ajax_referer( 'ces_expiry_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		if ( $this->is_expired( $post_id ) ) {
			$this->process_expiry( $post_id );
			
			$action = get_post_meta( $post_id, '_ces_expiry_action', true );
			$options = get_option( 'ces_settings' );
			if ( ! $action || $action === 'default' ) {
				$action = isset( $options['default_action'] ) ? $options['default_action'] : 'draft';
			}

			$data = [ 'action' => $action ];
			if ( $action === 'redirect' ) {
				$url = get_post_meta( $post_id, '_ces_expiry_redirect', true );
				if ( empty( $url ) ) {
					$url = isset( $options['default_redirect'] ) ? $options['default_redirect'] : '';
				}
				$data['url'] = $url;
			}

			wp_send_json_success( $data );
		}

		wp_send_json_error( 'Not yet expired' );
	}

	/**
	 * Background task to check and process expired content.
	 */
	public function check_expired_content() {
		$now = current_time( 'mysql' );

		$args = [
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => '_ces_expiry_date',
					'value'   => $now,
					'compare' => '<=',
					'type'    => 'DATETIME',
				],
			],
		];

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->process_expiry( get_the_ID() );
			}
			wp_reset_postdata();
		}
	}

	/**
	 * Process the expiry action for a specific post.
	 */
	public function process_expiry( $post_id ) {
		if ( get_post_meta( $post_id, '_ces_processed', true ) ) {
			return;
		}

		$action = get_post_meta( $post_id, '_ces_expiry_action', true );
		$options = get_option( 'ces_settings' );

		if ( ! $action || $action === 'default' ) {
			$action = isset( $options['default_action'] ) ? $options['default_action'] : 'draft';
		}

		if ( $action === 'draft' ) {
			wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'draft',
			] );
		}

		CES_Log::add_entry( $post_id, $action );
		update_post_meta( $post_id, '_ces_processed', true );
	}

	/**
	 * Handle redirects for expired content on the frontend.
	 */
	public function handle_frontend_expiry() {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $this->is_expired( $post_id ) ) {
			return;
		}

		$action = get_post_meta( $post_id, '_ces_expiry_action', true );
		$options = get_option( 'ces_settings' );

		if ( ! $action || $action === 'default' ) {
			$action = isset( $options['default_action'] ) ? $options['default_action'] : 'draft';
		}

		if ( $action === 'redirect' ) {
			$url = get_post_meta( $post_id, '_ces_expiry_redirect', true );
			if ( empty( $url ) ) {
				$url = isset( $options['default_redirect'] ) ? $options['default_redirect'] : '';
			}

			if ( ! empty( $url ) ) {
				wp_redirect( $url, 301 );
				exit;
			} else {
				$this->process_expiry( $post_id );
				wp_redirect( home_url() );
				exit;
			}
		} elseif ( $action === 'draft' ) {
			$this->process_expiry( $post_id );
			wp_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Handle message replacement for expired content.
	 */
	public function handle_message_replacement( $content ) {
		if ( ! is_singular() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $this->is_expired( $post_id ) ) {
			return $content;
		}

		$action = get_post_meta( $post_id, '_ces_expiry_action', true );
		$options = get_option( 'ces_settings' );

		if ( ! $action || $action === 'default' ) {
			$action = isset( $options['default_action'] ) ? $options['default_action'] : 'draft';
		}

		if ( $action === 'message' ) {
			$message = get_post_meta( $post_id, '_ces_expiry_message', true );
			if ( empty( $message ) ) {
				$message = isset( $options['default_message'] ) ? $options['default_message'] : '';
			}

			if ( ! empty( $message ) ) {
				return '<div class="ces-expiry-message">' . wp_kses_post( $message ) . '</div>';
			}
		}

		return $content;
	}

	/**
	 * Check if a post is expired.
	 */
	private function is_expired( $post_id ) {
		$expiry_date = get_post_meta( $post_id, '_ces_expiry_date', true );
		if ( empty( $expiry_date ) ) {
			return false;
		}

		$expiry_timestamp = strtotime( $expiry_date );
		$now = current_time( 'timestamp' );

		return ( $expiry_timestamp <= $now );
	}
}
