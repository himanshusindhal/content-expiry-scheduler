<?php
/**
 * Settings page handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CES_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add settings page to the menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Content Expiry Settings', 'content-expiry-scheduler' ),
			__( 'Content Expiry', 'content-expiry-scheduler' ),
			'manage_options',
			'content-expiry-scheduler',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting( 'ces_settings_group', 'ces_settings', [ $this, 'sanitize_settings' ] );

		add_settings_section(
			'ces_general_section',
			__( 'General Settings', 'content-expiry-scheduler' ),
			null,
			'content-expiry-scheduler'
		);

		add_settings_field(
			'post_types',
			__( 'Enabled Post Types', 'content-expiry-scheduler' ),
			[ $this, 'render_post_types_field' ],
			'content-expiry-scheduler',
			'ces_general_section'
		);

		add_settings_field(
			'default_action',
			__( 'Default Expiry Action', 'content-expiry-scheduler' ),
			[ $this, 'render_default_action_field' ],
			'content-expiry-scheduler',
			'ces_general_section'
		);

		add_settings_field(
			'default_redirect',
			__( 'Default Redirect URL', 'content-expiry-scheduler' ),
			[ $this, 'render_default_redirect_field' ],
			'content-expiry-scheduler',
			'ces_general_section'
		);

		add_settings_field(
			'default_message',
			__( 'Default Custom Message', 'content-expiry-scheduler' ),
			[ $this, 'render_default_message_field' ],
			'content-expiry-scheduler',
			'ces_general_section'
		);
	}

	/**
	 * Sanitize settings input.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];
		
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$sanitized['post_types'] = array_map( 'sanitize_text_field', $input['post_types'] );
		} else {
			$sanitized['post_types'] = [];
		}

		$sanitized['default_action'] = isset( $input['default_action'] ) ? sanitize_text_field( $input['default_action'] ) : 'draft';
		$sanitized['default_redirect'] = isset( $input['default_redirect'] ) ? esc_url_raw( $input['default_redirect'] ) : '';
		$sanitized['default_message'] = isset( $input['default_message'] ) ? wp_kses_post( $input['default_message'] ) : '';

		return $sanitized;
	}

	/**
	 * Render the post types checklist.
	 */
	public function render_post_types_field() {
		$options = get_option( 'ces_settings' );
		$enabled_types = isset( $options['post_types'] ) ? $options['post_types'] : [];
		
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		
		foreach ( $post_types as $type ) {
			if ( $type->name === 'attachment' ) continue;
			
			$checked = in_array( $type->name, $enabled_types ) ? 'checked' : '';
			echo '<label style="display:block; margin-bottom:5px;">';
			echo '<input type="checkbox" name="ces_settings[post_types][]" value="' . esc_attr( $type->name ) . '" ' . $checked . '> ';
			echo esc_html( $type->label );
			echo '</label>';
		}
	}

	/**
	 * Render the default action radio field.
	 */
	public function render_default_action_field() {
		$options = get_option( 'ces_settings' );
		$current = isset( $options['default_action'] ) ? $options['default_action'] : 'draft';
		
		$actions = [
			'draft'    => __( 'Auto-draft', 'content-expiry-scheduler' ),
			'redirect' => __( 'Redirect', 'content-expiry-scheduler' ),
			'message'  => __( 'Show Message', 'content-expiry-scheduler' ),
		];

		foreach ( $actions as $value => $label ) {
			$checked = ( $current === $value ) ? 'checked' : '';
			echo '<label style="margin-right:15px;">';
			echo '<input type="radio" name="ces_settings[default_action]" value="' . esc_attr( $value ) . '" ' . $checked . '> ';
			echo esc_html( $label );
			echo '</label>';
		}
	}

	/**
	 * Render the default redirect field.
	 */
	public function render_default_redirect_field() {
		$options = get_option( 'ces_settings' );
		$val = isset( $options['default_redirect'] ) ? $options['default_redirect'] : '';
		echo '<input type="url" name="ces_settings[default_redirect]" value="' . esc_url( $val ) . '" class="regular-text">';
		echo '<p class="description">' . __( 'Fallback URL if post-specific redirect is empty.', 'content-expiry-scheduler' ) . '</p>';
	}

	/**
	 * Render the default message field.
	 */
	public function render_default_message_field() {
		$options = get_option( 'ces_settings' );
		$val = isset( $options['default_message'] ) ? $options['default_message'] : '';
		echo '<textarea name="ces_settings[default_message]" rows="5" cols="50" class="large-text">' . esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">' . __( 'Fallback message if post-specific message is empty. Supports basic HTML.', 'content-expiry-scheduler' ) . '</p>';
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'ces_settings_group' );
				do_settings_sections( 'content-expiry-scheduler' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
