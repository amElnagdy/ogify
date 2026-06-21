<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Remove OGify data.
 *
 * @package OGify
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ogify_settings' );

$ogify_upload_dir = wp_upload_dir();
if ( empty( $ogify_upload_dir['error'] ) && ! empty( $ogify_upload_dir['basedir'] ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;

	$ogify_cache_dir = trailingslashit( $ogify_upload_dir['basedir'] ) . 'ogify';
	if ( is_dir( $ogify_cache_dir ) && WP_Filesystem() ) {
		$wp_filesystem->delete( $ogify_cache_dir, true );
	}
}

delete_metadata( 'user', 0, 'ogify_author_photo', '', true );
