<?php if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Plugin Name: OGify – Dynamic Open Graph Image Generator
 * Description: Generates dynamic Open Graph share images for posts.
 * Version: 1.0.0
 * Author: Nagdy
 * Author URI: https://nagdy.me
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: ogify
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package OGify
 */

define( 'OGIFY_VERSION', '1.0.0' );
define( 'OGIFY_FILE', __FILE__ );
define( 'OGIFY_PATH', plugin_dir_path( __FILE__ ) );
define( 'OGIFY_URL', plugin_dir_url( __FILE__ ) );

require_once OGIFY_PATH . 'includes/Plugin.php';
require_once OGIFY_PATH . 'includes/ReadingTime.php';

( new \OGify\Plugin() )->register();
