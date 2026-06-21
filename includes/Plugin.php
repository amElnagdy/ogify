<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Main plugin loader.
 *
 * @package OGify
 */

final class Plugin {
	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! self::has_gd() ) {
			add_action( 'admin_notices', array( $this, 'render_gd_notice' ) );
		}

		if ( is_admin() ) {
			require_once OGIFY_PATH . 'includes/Settings.php';
			require_once OGIFY_PATH . 'includes/Profile.php';
			Settings::register_hooks();
			Profile::register_hooks();
		}
	}

	/**
	 * Check whether the required GD and FreeType functions are available.
	 *
	 * @return bool
	 */
	public static function has_gd(): bool {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' );
	}

	/**
	 * Render the GD dependency notice.
	 *
	 * @return void
	 */
	public function render_gd_notice(): void {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'OGify needs the GD/FreeType extension. Image generation is disabled.', 'ogify' ) . '</p></div>';
	}
}
