=== OGify – Dynamic Open Graph Image Generator ===
Contributors: nagdy
Tags: open graph, og image, social share image, twitter card, social media
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate dynamic Open Graph and Twitter card images for WordPress posts with GD and bundled fonts.

== Description ==

OGify generates 1200x630 PNG social share images for public posts and outputs matching Open Graph and Twitter Card meta tags.

The plugin renders with WordPress, GD, and bundled Inter fonts. It stores generated images in uploads/ogify, refreshes them when post content or card settings change, and keeps settings in the WordPress options table.

Settings include enabled post types, author photo/name visibility, reading time visibility, site name text, background colors, text color, accent color, reading speed, and a default author photo.

== Installation ==

1. Upload the ogify folder to /wp-content/plugins/.
2. Activate OGify in the WordPress Plugins screen.
3. Open Settings > OGify and choose the card content and design options.
4. Optionally set each user's OGify share photo from their profile screen.

== Frequently Asked Questions ==

= Does OGify need a build step or external image service? =

No. OGify uses PHP, WordPress APIs, GD/FreeType, and bundled fonts.

= Where are generated images stored? =

Generated PNG files are stored in uploads/ogify.

= What happens if GD or FreeType is unavailable? =

Image generation is skipped and the plugin shows an admin notice instead of causing a fatal error.

== Changelog ==

= 1.0.0 =
* Initial release with generated Open Graph images, Twitter Card meta tags, settings, author photos, cached PNG output, settings preview, uninstall cleanup, and WordPress.org packaging.
