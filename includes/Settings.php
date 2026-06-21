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
			'template'             => 'classic',
			'bg_type'              => 'solid',
			'bg_color'             => '#0c0a09',
			'bg_gradient_from'     => '#0c0a09',
			'bg_gradient_to'       => '#1c1310',
			'text_color'           => '#faf7f2',
			'accent_color'         => '#fbbf24',
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
		add_filter( 'plugin_action_links_' . plugin_basename( OGIFY_FILE ), array( __CLASS__, 'add_settings_link' ) );
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public static function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=ogify' ) ),
			esc_html__( 'Settings', 'ogify' )
		);
		array_unshift( $links, $settings_link );

		return $links;
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
	 * Register the option.
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

		if ( ! isset( $input['post_types'] ) ) {
			$output['post_types'] = self::get()['post_types'];
		} else {
			$public_post_types   = get_post_types( array( 'public' => true ), 'names' );
			$selected_post_types = array();

			foreach ( (array) $input['post_types'] as $post_type ) {
				if ( is_scalar( $post_type ) ) {
					$selected_post_types[] = sanitize_key( (string) $post_type );
				}
			}

			$output['post_types'] = array_values( array_intersect( $selected_post_types, $public_post_types ) );
		}

		$bg_type           = isset( $input['bg_type'] ) && is_scalar( $input['bg_type'] ) ? (string) $input['bg_type'] : '';
		$output['bg_type'] = in_array( $bg_type, array( 'solid', 'gradient' ), true ) ? $bg_type : $defaults['bg_type'];

		$template           = isset( $input['template'] ) && is_scalar( $input['template'] ) ? (string) $input['template'] : '';
		$output['template'] = in_array( $template, array( 'classic', 'centered', 'minimal' ), true ) ? $template : $defaults['template'];

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
				'mediaTitle'   => __( 'Choose author photo', 'ogify' ),
				'mediaButton'  => __( 'Use this image', 'ogify' ),
				'previewAlt'   => __( 'Selected author photo', 'ogify' ),
				'emptyPreview' => __( 'No image selected', 'ogify' ),
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

		$settings      = self::get();
		$card_image    = new CardImage();
		$avatar_source = $card_image->resolve_avatar_source( get_current_user_id() );

		echo '<div class="wrap ogify-settings">';
		echo '<h1>' . esc_html__( 'OGify', 'ogify' ) . '</h1>';
		echo '<div class="ogify-layout">';

		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post" class="ogify-form">';
		settings_fields( 'ogify_settings' );

		echo '<div class="postbox ogify-card">';
		echo '<h2 class="ogify-card__title">' . esc_html__( 'General', 'ogify' ) . '</h2>';

		$enabled_description = 'ogify-enabled-description';
		echo '<div class="ogify-field">';
		self::render_checkbox_field(
			array(
				'key'            => 'enabled',
				'label'          => __( 'Generate Open Graph share cards', 'ogify' ),
				'description_id' => $enabled_description,
			)
		);
		echo '<p id="' . esc_attr( $enabled_description ) . '" class="description">' . esc_html__( 'Creates a 1200x630 card when posts are shared.', 'ogify' ) . '</p>';
		echo '</div>';

		$wpm_description = 'ogify-reading-wpm-description';
		echo '<div class="ogify-field">';
		echo '<label class="ogify-field__label" for="ogify-reading-wpm">' . esc_html__( 'Reading speed', 'ogify' ) . '</label>';
		self::render_number_field(
			array(
				'key'            => 'reading_wpm',
				'min'            => 50,
				'max'            => 600,
				'description_id' => $wpm_description,
			)
		);
		echo '<p id="' . esc_attr( $wpm_description ) . '" class="description">' . esc_html__( 'Words per minute — used to estimate reading time.', 'ogify' ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<div class="postbox ogify-card">';
		echo '<h2 class="ogify-card__title">' . esc_html__( 'Card content', 'ogify' ) . '</h2>';
		echo '<fieldset class="ogify-field ogify-fieldset">';
		echo '<legend>' . esc_html__( 'Show on the card', 'ogify' ) . '</legend>';
		echo '<div class="ogify-toggle-list">';
		self::render_checkbox_field( array( 'key' => 'show_author_photo', 'label' => __( 'Show the author photo', 'ogify' ) ) );
		self::render_checkbox_field( array( 'key' => 'show_author_name', 'label' => __( 'Show the author name', 'ogify' ) ) );
		self::render_checkbox_field( array( 'key' => 'show_reading_time', 'label' => __( 'Show the reading time', 'ogify' ) ) );
		self::render_checkbox_field( array( 'key' => 'show_site_name', 'label' => __( 'Show the site name', 'ogify' ) ) );
		echo '</div>';
		echo '</fieldset>';

		$site_name_description = 'ogify-site-name-text-description';
		echo '<div class="ogify-field">';
		echo '<label class="ogify-field__label" for="ogify-site-name-text">' . esc_html__( 'Site name', 'ogify' ) . '</label>';
		self::render_text_field(
			array(
				'key'            => 'site_name_text',
				'description_id' => $site_name_description,
			)
		);
		echo '<p id="' . esc_attr( $site_name_description ) . '" class="description">' . esc_html__( 'Overrides the site title shown on the card.', 'ogify' ) . '</p>';
		echo '</div>';

		echo '<div class="ogify-field">';
		echo '<span class="ogify-field__label">' . esc_html__( 'Default Author Photo', 'ogify' ) . '</span>';
		self::render_media_field( array( 'active_source' => $avatar_source ) );
		echo '</div>';
		echo '</div>';

		echo '<div class="postbox ogify-card">';
		echo '<h2 class="ogify-card__title">' . esc_html__( 'Design', 'ogify' ) . '</h2>';
		echo '<fieldset class="ogify-field ogify-fieldset">';
		echo '<legend>' . esc_html__( 'Card template', 'ogify' ) . '</legend>';
		self::render_template_field();
		echo '</fieldset>';

		echo '<fieldset class="ogify-field ogify-fieldset">';
		echo '<legend>' . esc_html__( 'Background type', 'ogify' ) . '</legend>';
		self::render_bg_type_field();
		echo '</fieldset>';

		echo '<div class="ogify-field ogify-when-solid"';
		if ( 'solid' !== $settings['bg_type'] ) {
			echo ' hidden';
		}
		echo '>';
		echo '<label class="ogify-field__label" for="ogify-bg-color">' . esc_html__( 'Background color', 'ogify' ) . '</label>';
		self::render_color_field( array( 'key' => 'bg_color' ) );
		echo '</div>';

		echo '<div class="ogify-field ogify-when-gradient"';
		if ( 'gradient' !== $settings['bg_type'] ) {
			echo ' hidden';
		}
		echo '>';
		echo '<div class="ogify-gradient-fields">';
		echo '<div>';
		echo '<label class="ogify-field__label" for="ogify-bg-gradient-from">' . esc_html__( 'Gradient start', 'ogify' ) . '</label>';
		self::render_color_field( array( 'key' => 'bg_gradient_from' ) );
		echo '</div>';
		echo '<div>';
		echo '<label class="ogify-field__label" for="ogify-bg-gradient-to">' . esc_html__( 'Gradient end', 'ogify' ) . '</label>';
		self::render_color_field( array( 'key' => 'bg_gradient_to' ) );
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ogify-field">';
		echo '<label class="ogify-field__label" for="ogify-text-color">' . esc_html__( 'Title & body text', 'ogify' ) . '</label>';
		self::render_color_field( array( 'key' => 'text_color' ) );
		echo '</div>';

		echo '<div class="ogify-field">';
		echo '<label class="ogify-field__label" for="ogify-accent-color">' . esc_html__( 'Reading-time tag / accents', 'ogify' ) . '</label>';
		self::render_color_field( array( 'key' => 'accent_color' ) );
		echo '</div>';
		echo '</div>';

		submit_button();
		echo '</form>';

		echo '<div class="postbox ogify-preview">';
		echo '<h2 class="ogify-card__title">' . esc_html__( 'Preview', 'ogify' ) . '</h2>';

		if ( ! Plugin::has_gd() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Preview unavailable because the GD image library is missing.', 'ogify' ) . '</p></div>';
		} else {
			$preview_url = $card_image->preview_url();

			if ( '' !== $preview_url ) {
				printf(
					'<p class="ogify-preview__image"><img src="%1$s" alt="%2$s" width="%3$s" height="%4$s"></p>',
					esc_url( $preview_url ),
					esc_attr__( 'OGify preview image', 'ogify' ),
					esc_attr( (string) CardImage::WIDTH ),
					esc_attr( (string) CardImage::HEIGHT )
				);
			} else {
				echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Preview unavailable right now.', 'ogify' ) . '</p></div>';
			}

			echo '<p class="description">' . esc_html__( 'This reflects your saved settings. Save changes to regenerate.', 'ogify' ) . '</p>';
			echo '<p class="description">' . esc_html__( '1200 x 630 — used by Facebook, X and LinkedIn.', 'ogify' ) . '</p>';
		}

		echo '</div>';
		echo '</div>';
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

		if ( ! empty( $args['description_id'] ) && is_scalar( $args['description_id'] ) ) {
			printf(
				'<input type="hidden" name="%1$s[%2$s]" value="0"><label class="ogify-switch" for="%3$s"><input id="%3$s" type="checkbox" name="%1$s[%2$s]" value="1"%4$s aria-describedby="%6$s"><span class="ogify-switch__track" aria-hidden="true"></span><span class="ogify-switch__label">%5$s</span></label>',
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				esc_attr( $field_id ),
				checked( ! empty( $settings[ $key ] ), true, false ),
				esc_html( $args['label'] ),
				esc_attr( (string) $args['description_id'] )
			);
			return;
		}

		printf(
			'<input type="hidden" name="%1$s[%2$s]" value="0"><label class="ogify-switch" for="%3$s"><input id="%3$s" type="checkbox" name="%1$s[%2$s]" value="1"%4$s><span class="ogify-switch__track" aria-hidden="true"></span><span class="ogify-switch__label">%5$s</span></label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			esc_attr( $field_id ),
			checked( ! empty( $settings[ $key ] ), true, false ),
			esc_html( $args['label'] )
		);
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

		if ( ! empty( $args['description_id'] ) && is_scalar( $args['description_id'] ) ) {
			printf(
				'<input id="%1$s" type="number" name="%2$s[%3$s]" value="%4$s" min="%5$s" max="%6$s" step="1" class="small-text" aria-describedby="%7$s">',
				esc_attr( $field_id ),
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				esc_attr( $settings[ $key ] ),
				esc_attr( $args['min'] ),
				esc_attr( $args['max'] ),
				esc_attr( (string) $args['description_id'] )
			);
			return;
		}

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

		if ( ! empty( $args['description_id'] ) && is_scalar( $args['description_id'] ) ) {
			printf(
				'<input id="%1$s" type="text" name="%2$s[%3$s]" value="%4$s" class="regular-text" aria-describedby="%5$s">',
				esc_attr( $field_id ),
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				esc_attr( $settings[ $key ] ),
				esc_attr( (string) $args['description_id'] )
			);
			return;
		}

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

		echo '<div class="ogify-radios">';

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[bg_type]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $settings['bg_type'], $value, false ),
				esc_html( $label )
			);
		}

		echo '</div>';
	}

	/**
	 * Render template radios.
	 *
	 * @return void
	 */
	public static function render_template_field(): void {
		$settings = self::get();
		$options  = array(
			'classic'  => __( 'Classic', 'ogify' ),
			'centered' => __( 'Centered', 'ogify' ),
			'minimal'  => __( 'Minimal', 'ogify' ),
		);

		echo '<div class="ogify-radios">';

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="radio" name="%1$s[template]" value="%2$s" %3$s> %4$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $value ),
				checked( $settings['template'], $value, false ),
				esc_html( $label )
			);
		}

		echo '</div>';
	}

	/**
	 * Render the default author photo picker.
	 *
	 * @return void
	 */
	public static function render_media_field( array $args = array() ): void {
		$settings       = self::get();
		$attachment_id  = absint( $settings['default_author_photo'] );
		$image          = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => __( 'Default author photo preview', 'ogify' ) ) ) : '';
		$description_id = 'ogify-default-author-photo-description';
		$active_source  = ! empty( $args['active_source'] ) && is_scalar( $args['active_source'] ) ? (string) $args['active_source'] : 'none';

		echo '<div class="ogify-media-field" data-ogify-media-field>';
		printf(
			'<input type="hidden" name="%1$s[default_author_photo]" value="%2$d" data-ogify-media-id>',
			esc_attr( self::OPTION ),
			esc_attr( $attachment_id )
		);
		echo '<div class="ogify-media-preview" data-ogify-media-preview>';

		if ( $image ) {
			echo wp_kses_post( $image );
		} else {
			echo '<span class="ogify-media-empty">' . esc_html__( 'No image selected', 'ogify' ) . '</span>';
		}

		echo '</div>';
		echo '<div class="ogify-media-actions">';
		printf(
			'<button type="button" class="button" data-ogify-media-select data-title="%1$s" data-button="%2$s" data-alt="%3$s" aria-describedby="%5$s">%4$s</button>',
			esc_attr__( 'Choose default author photo', 'ogify' ),
			esc_attr__( 'Use this image', 'ogify' ),
			esc_attr__( 'Selected author photo', 'ogify' ),
			esc_html__( 'Choose Image', 'ogify' ),
			esc_attr( $description_id )
		);
		printf(
			'<button type="button" class="button" data-ogify-media-remove%1$s>%2$s</button>',
			$attachment_id ? '' : ' hidden',
			esc_html__( 'Remove', 'ogify' )
		);
		echo '</div>';
		echo '</div>';
		echo '<p id="' . esc_attr( $description_id ) . '" class="description">' . esc_html__( 'Used only when an author has no profile photo and no Gravatar.', 'ogify' ) . '</p>';
		$active_message = sprintf(
			/* translators: %s: avatar source currently active for the logged-in user. */
			esc_html__( 'Active for you right now: %s', 'ogify' ),
			'<strong>' . esc_html( self::avatar_source_label( $active_source ) ) . '</strong>'
		);
		echo '<p class="description ogify-active-source">' . wp_kses( $active_message, array( 'strong' => array() ) ) . '</p>';
	}

	/**
	 * Get the human label for an avatar source.
	 *
	 * @param string $source Avatar source.
	 * @return string
	 */
	private static function avatar_source_label( string $source ): string {
		$labels = array(
			'profile'  => __( 'Your OGify profile photo', 'ogify' ),
			'gravatar' => __( 'Your Gravatar', 'ogify' ),
			'default'  => __( 'This site default', 'ogify' ),
			'none'     => __( 'No photo', 'ogify' ),
		);

		return isset( $labels[ $source ] ) ? $labels[ $source ] : $labels['none'];
	}
}
