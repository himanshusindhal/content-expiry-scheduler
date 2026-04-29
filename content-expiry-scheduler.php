<?php
/**
 * Plugin Name:       Content Expiry Scheduler
 * Plugin URI:        https://github.com/himanshusindhal/content-expiry-scheduler
 * Description:       Set an expiry date on any post, page, or custom post type. On expiry, auto-draft it, redirect visitors, or show a custom message.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Himanshu Sindhal
 * Author URI:        https://profiles.wordpress.org/sindhalhimanshu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       content-expiry-scheduler
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The main plugin class.
 */
class Content_Expiry_Scheduler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Define constants.
	 */
	private function define_constants() {
		define( 'CES_VERSION', '1.0.0' );
		define( 'CES_PATH', plugin_dir_path( __FILE__ ) );
		define( 'CES_URL', plugin_dir_url( __FILE__ ) );
		define( 'CES_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once CES_PATH . 'includes/class-ces-log.php';
		require_once CES_PATH . 'includes/class-ces-settings.php';
		require_once CES_PATH . 'includes/class-ces-metabox.php';
		require_once CES_PATH . 'includes/class-ces-cron.php';
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		
		// Initialize classes
		new CES_Log();
		new CES_Settings();
		new CES_Metabox();
		new CES_Cron();

		// Activation hook
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
	}

	/**
	 * Load translation files.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'content-expiry-scheduler', false, dirname( CES_BASENAME ) . '/languages' );
	}

	/**
	 * Activation callback.
	 */
	public function activate() {
		// Create log table
		CES_Log::create_table();
		
		// Schedule cron
		if ( ! wp_next_scheduled( 'ces_run_expiry_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'ces_run_expiry_check' );
		}
	}
}

/**
 * Initialize the plugin.
 */
function ces_init() {
	new Content_Expiry_Scheduler();
}
add_action( 'plugins_loaded', 'ces_init' );
