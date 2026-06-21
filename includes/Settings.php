<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Settings page and option schema.
 *
 * @package OGify
 */

final class Settings {
	const OPTION = 'ogify_settings';

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'enabled'              => true,
			'post_types'           => array( 'post' ),
			'show_author_photo'    => true,
			'show_author_name'     => true,
			'show_reading_time'    => true,
			'show_site_name'       => true,
			'bg_type'              => 'solid',
			'bg_color'             => '#0f172a',
			'bg_gradient_from'     => '#0f172a',
			'bg_gradient_to'       => '#3b0764',
			'text_color'           => '#ffffff',
			'accent_color'         => '#22d3ee',
			'reading_wpm'          => 200,
			'site_name_text'       => get_bloginfo( 'name' ),
			'default_author_photo' => 0,
		);
	}

	/**
	 * Get saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get(): array {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public static function add_page(): void {
		add_options_page(
			esc_html__( 'OGify', 'ogify' ),
			esc_html__( 'OGify', 'ogify' ),
			'manage_options',
			'ogify',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the option and fields.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'ogify_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		add_settings_section( 'ogify_general', esc_html__( 'General', 'ogify' ), '__return_false', 'ogify' );
		add_settings_section( 'ogify_content', esc_html__( 'Card Content', 'ogify' ), '__return_false', 'ogify' );
		add_settings_section( 'ogify_design', esc_html__( 'Design', 'ogify' ), '__return_false', 'ogify' );

		add_settings_field( 'ogify_enabled', esc_html__( 'Enabled', 'ogify' ), array( __CLASS__, 'render_checkbox_field' ), 'ogify', 'ogify_general', array( 'key' => 'enabled', 'label' => __( 'Generate Open Graph images', 'ogify' ) ) );
		add_settings_field( 'ogify_post_types', esc_html__( 'Post Types', 'ogify' ), array( __CLASS__, 'render_post_types_field' ), 'ogify', 'ogify_general' );
		add_settings_field( 'ogify_reading_wpm', esc_html__( 'Reading Speed', 'ogify' ), array( __CLASS__, 'render_number_field' ), 'ogify', 'ogify_general', array( 'key' => 'reading_wpm', 'min' => 50, 'max' => 600 ) );

		add_settings_field( 'ogify_show_author_photo', esc_html__( 'Author Photo', 'ogify' ), array( __CLASS__, 'render_checkbox_field' ), 'ogify', 'ogify_content', array( 'key' => 'show_author_photo', 'label' => __( 'Show the author photo', 'ogify' ) ) );
		add_settings_field( 'ogify_show_author_name', esc_html__( 'Author Name', 'ogify' ), array( __CLASS__, 'render_checkbox_field' ), 'ogify', 'ogify_content', array( 'key' => 'show_author_name', 'label' => __( 'Show the author name', 'ogify' ) ) );
		add_settings_field( 'ogify_show_reading_time', esc_html__( 'Reading Time', 'ogify' ), array( __CLASS__, 'render_checkbox_field' ), 'ogify', 'ogify_content', array( 'key' => 'show_reading_time', 'label' => __( 'Show the reading time', 'ogify' ) ) );
		add_settings_field( 'ogify_show_site_name', esc_html__( 'Site Name', 'ogify' ), array( __CLASS__, 'render_checkbox_field' ), 'ogify', 'ogify_content', array( 'key' => 'show_site_name', 'label' => __( 'Show the site name', 'ogify' ) ) );
		add_settings_field( 'ogify_site_name_text', esc_html__( 'Site Name Text', 'ogify' ), array( __CLASS__, 'render_text_field' ), 'ogify', 'ogify_content', array( 'key' => 'site_name_text' ) );
		add_settings_field( 'ogify_default_author_photo', esc_html__( 'Default Author Photo', 'ogify' ), array( __CLASS__, 'render_media_field' ), 'ogify', 'ogify_content' );

		add_settings_field( 'ogify_bg_type', esc_html__( 'Background Type', 'ogify' ), array( __CLASS__, 'render_bg_type_field' ), 'ogify', 'ogify_design' );
		add_settings_field( 'ogify_bg_color', esc_html__( 'Background Color', 'ogify' ), array( __CLASS__, 'render_color_field' ), 'ogify', 'ogify_design', array( 'key' => 'bg_color' ) );
		add_settings_field( 'ogify_bg_gradient_from', esc_html__( 'Gradient Start', 'ogify' ), array( __CLASS__, 'render_color_field' ), 'ogify', 'ogify_design', array( 'key' => 'bg_gradient_from' ) );
		add_settings_field( 'ogify_bg_gradient_to', esc_html__( 'Gradient End', 'ogify' ), array( __CLASS__, 'render_color_field' ), 'ogify', 'ogify_design', array( 'key' => 'bg_gradient_to' ) );
		add_settings_field( 'ogify_text_color', esc_html__( 'Text Color', 'ogify' ), array( __CLASS__, 'render_color_field' ), 'ogify', 'ogify_design', array( 'key' => 'text_color' ) );
		add_settings_field( 'ogify_accent_color', esc_html__( 'Accent Color', 'ogify' ), array( __CLASS__, 'render_color_field' ), 'ogify', 'ogify_design', array( 'key' => 'accent_color' ) );
	}

	/**
	 * Sanitize the option array.
	 *
	 * @param mixed $input Raw option value from the Settings API.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		foreach ( array( 'enabled', 'show_author_photo', 'show_author_name', 'show_reading_time', 'show_site_name' ) as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] );
		}

		$public_post_types   = get_post_types( array( 'public' => true ), 'names' );
		$selected_post_types = array();

		foreach ( isset( $input['post_types'] ) ? (array) $input['post_types'] : array() as $post_type ) {
			if ( is_scalar( $post_type ) ) {
				$selected_post_types[] = sanitize_key( (string) $post_type );
			}
		}

		$output['post_types'] = array_values( array_intersect( $selected_post_types, $public_post_types ) );

		$bg_type           = isset( $input['bg_type'] ) && is_scalar( $input['bg_type'] ) ? (string) $input['bg_type'] : '';
		$output['bg_type'] = in_array( $bg_type, array( 'solid', 'gradient' ), true ) ? $bg_type : $defaults['bg_type'];

		foreach ( array( 'bg_color', 'bg_gradient_from', 'bg_gradient_to', 'text_color', 'accent_color' ) as $key ) {
			$raw_color      = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$color          = sanitize_hex_color( $raw_color );
			$output[ $key ] = $color ? $color : $defaults[ $key ];
		}

		$wpm                   = isset( $input['reading_wpm'] ) && is_scalar( $input['reading_wpm'] ) ? absint( $input['reading_wpm'] ) : $defaults['reading_wpm'];
		$output['reading_wpm'] = min( 600, max( 50, $wpm ) );

		$site_name_text                 = array_key_exists( 'site_name_text', $input ) && is_scalar( $input['site_name_text'] ) ? (string) $input['site_name_text'] : $defaults['site_name_text'];
		$output['site_name_text']       = sanitize_text_field( $site_name_text );
		$output['default_author_photo'] = isset( $input['default_author_photo'] ) && is_scalar( $input['default_author_photo'] ) ? absint( $input['default_author_photo'] ) : $defaults['default_author_photo'];

		return $output;
	}

	/**
	 * Enqueue assets for the settings screen.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_ogify' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_style( 'ogify-admin', OGIFY_URL . 'assets/admin.css', array(), OGIFY_VERSION );
		wp_enqueue_script( 'ogify-admin', OGIFY_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), OGIFY_VERSION, true );
		wp_localize_script(
			'ogify-admin',
			'ogifyAdmin',
			array(
				'mediaTitle'  => __( 'Choose author photo', 'ogify' ),
				'mediaButton' => __( 'Use this image', 'ogify' ),
				'previewAlt'  => __( 'Selected author photo', 'ogify' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap ogify-settings">';
		echo '<h1>' . esc_html__( 'OGify', 'ogify' ) . '</h1>';
		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
		settings_fields( 'ogify_settings' );
		do_settings_sections( 'ogify' );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_checkbox_field( array $args ): void {
		$settings = self::get();
		$key      = $args['key'];
		$field_id = 'ogify-' . str_replace( '_', '-', $key );

		printf(
			'<input type="hidden" name="%1$s[%2$s]" value="0"><label for="%3$s"><input id="%3$s" type="checkbox" name="%1$s[%2$s]" value="1" %4$s> %5$s</label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $field_id ),
			checked( ! empty( $settings[ $key ] ), true, false ),
			esc_html( $args['label'] )
		);
	}

	/**
	 * Render public post type checkboxes.
	 *
	 * @return void
	 */
	public static function render_post_types_field(): void {
		$settings   = self::get();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[post_types][]" value="">';
		echo '<fieldset class="ogify-checkboxes">';

		foreach ( $post_types as $post_type ) {
			$name  = $post_type->name;
			$label = $post_type->labels->singular_name ? $post_type->labels->singular_name : $post_type->label;

			printf(
				'<label><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $name ),
				checked( in_array( $name, $settings['post_types'], true ), true, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_number_field( array $args ): void {
		$settings = self::get();
		$key      = $args['key'];
		$field_id = 'ogify-' . str_replace( '_', '-', $key );

		printf(
			'<input id="%1$s" type="number" name="%2$s[%3$s]" value="%4$s" min="%5$s" max="%6$s" step="1" class="small-text">',
			esc_attr( $field_id ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $settings[ $key ] ),
			esc_attr( $args['min'] ),
			esc_attr( $args['max'] )
		);
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_text_field( array $args ): void {
		$settings = self::get();
		$key      = $args['key'];
		$field_id = 'ogify-' . str_replace( '_', '-', $key );

		printf(
			'<input id="%1$s" type="text" name="%2$s[%3$s]" value="%4$s" class="regular-text">',
			esc_attr( $field_id ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $settings[ $key ] )
		);
	}

	/**
	 * Render a color picker field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public static function render_color_field( array $args ): void {
		$settings = self::get();
		$key      = $args['key'];
		$field_id = 'ogify-' . str_replace( '_', '-', $key );

		printf(
			'<input id="%1$s" type="text" name="%2$s[%3$s]" value="%4$s" class="wp-color-picker" data-default-color="%5$s">',
			esc_attr( $field_id ),
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $settings[ $key ] ),
			esc_attr( self::defaults()[ $key ] )
		);
	}

	/**
	 * Render background type radios.
	 *
	 * @return void
	 */
	public static function render_bg_type_field(): void {
		$settings = self::get();
		$options  = array(
			'solid'    => __( 'Solid', 'ogify' ),
			'gradient' => __( 'Gradient', 'ogify' ),
		);

		echo '<fieldset class="ogify-radios">';

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[bg_type]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $settings['bg_type'], $value, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Render the default author photo picker.
	 *
	 * @return void
	 */
	public static function render_media_field(): void {
		$settings      = self::get();
		$attachment_id = absint( $settings['default_author_photo'] );
		$image         = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => esc_attr__( 'Default author photo preview', 'ogify' ) ) ) : '';

		echo '<div class="ogify-media-field" data-ogify-media-field>';
		printf(
			'<input type="hidden" name="%1$s[default_author_photo]" value="%2$d" data-ogify-media-id>',
			esc_attr( self::OPTION ),
			esc_attr( $attachment_id )
		);
		echo '<div class="ogify-media-preview" data-ogify-media-preview>';

		if ( $image ) {
			echo wp_kses_post( $image );
		}

		echo '</div>';
		echo '<div class="ogify-media-actions">';
		printf(
			'<button type="button" class="button" data-ogify-media-select data-title="%1$s" data-button="%2$s" data-alt="%3$s">%4$s</button>',
			esc_attr__( 'Choose default author photo', 'ogify' ),
			esc_attr__( 'Use this image', 'ogify' ),
			esc_attr__( 'Selected author photo', 'ogify' ),
			esc_html__( 'Choose Image', 'ogify' )
		);
		printf(
			'<button type="button" class="button" data-ogify-media-remove%1$s>%2$s</button>',
			$attachment_id ? '' : ' hidden',
			esc_html__( 'Remove', 'ogify' )
		);
		echo '</div>';
		echo '</div>';
	}
}
