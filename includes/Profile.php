<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * User profile author photo field.
 *
 * @package OGify
 */

final class Profile {
	const PHOTO_META = 'ogify_author_photo';

	/**
	 * Register profile hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile_field' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile_field' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue media picker assets for user profile screens.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'ogify-admin', OGIFY_URL . 'assets/admin.css', array(), OGIFY_VERSION );
		wp_enqueue_script( 'ogify-admin', OGIFY_URL . 'assets/admin.js', array( 'jquery' ), OGIFY_VERSION, true );
	}

	/**
	 * Render the author photo picker.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function render_profile_field( \WP_User $user ): void {
		$attachment_id = self::photo_attachment_id( (int) $user->ID );
		$image         = $attachment_id ? wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => esc_attr__( 'OGify share photo preview', 'ogify' ) ) ) : '';

		echo '<table class="form-table" role="presentation"><tr>';
		echo '<th><label for="ogify-author-photo-select">' . esc_html__( 'OGify share photo', 'ogify' ) . '</label></th>';
		echo '<td>';
		echo '<div class="ogify-media-field" data-ogify-media-field>';
		printf(
			'<input type="hidden" name="%1$s" value="%2$d" data-ogify-media-id>',
			esc_attr( self::PHOTO_META ),
			esc_attr( $attachment_id )
		);
		echo '<div class="ogify-media-preview" data-ogify-media-preview>';

		if ( $image ) {
			echo wp_kses_post( $image );
		}

		echo '</div>';
		echo '<div class="ogify-media-actions">';
		printf(
			'<button id="ogify-author-photo-select" type="button" class="button" data-ogify-media-select data-title="%1$s" data-button="%2$s" data-alt="%3$s">%4$s</button>',
			esc_attr__( 'Choose OGify share photo', 'ogify' ),
			esc_attr__( 'Use this image', 'ogify' ),
			esc_attr__( 'OGify share photo preview', 'ogify' ),
			esc_html__( 'Choose Image', 'ogify' )
		);
		printf(
			'<button type="button" class="button" data-ogify-media-remove%1$s>%2$s</button>',
			esc_attr( $attachment_id ? '' : ' hidden' ),
			esc_html__( 'Remove', 'ogify' )
		);
		echo '</div>';
		echo '</div>';
		echo '</td>';
		echo '</tr></table>';
	}

	/**
	 * Save the author photo attachment id.
	 *
	 * @param int $user_id User id being saved.
	 * @return void
	 */
	public static function save_profile_field( int $user_id ): void {
		$nonce = ( isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'update-user_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$attachment_id = isset( $_POST[ self::PHOTO_META ] ) ? absint( wp_unslash( $_POST[ self::PHOTO_META ] ) ) : 0;

		if ( $attachment_id ) {
			update_user_meta( $user_id, self::PHOTO_META, $attachment_id );
			return;
		}

		delete_user_meta( $user_id, self::PHOTO_META );
	}

	/**
	 * Get the saved author photo attachment id.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public static function photo_attachment_id( int $user_id ): int {
		return absint( get_user_meta( $user_id, self::PHOTO_META, true ) );
	}
}
