=== OGify – Dynamic Open Graph Image Generator ===
Contributors: nagdy
Tags: open graph, og image, social share image, twitter card, social media
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Generate dynamic Open Graph and Twitter card images for WordPress posts with GD and bundled fonts.

== Description ==

OGify generates 1200x630 PNG social share images for public posts and outputs matching Open Graph and Twitter Card meta tags, so every post gets a designed share card without a hand-made featured image.

Choose from three card templates: Classic (left-aligned with an author chip and an accent tag), Centered (symmetrical, with the avatar above a bottom byline), and Minimal (an oversized title with an accent bar and a single meta line). A bold "Midnight & Amber" palette ships as the default.

The plugin renders with WordPress, GD, and bundled Inter fonts. It stores generated images in uploads/ogify, refreshes them when post content or card settings change, and keeps settings in the WordPress options table.

Settings include the card template, author photo/name visibility, reading time visibility, site name text, a solid or gradient background, text and accent colors, reading speed, and a default author photo. Each user can set their own share photo from their profile screen.

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

== Screenshots ==

1. A generated Open Graph card using the Classic template.
2. The Centered template.
3. The Minimal template.
4. The OGify settings screen with a live card preview.

== Changelog ==

= 1.0.0 =
* Initial release.
* Dynamic 1200x630 Open Graph images with matching Open Graph and Twitter Card meta tags.
* Three card templates: Classic, Centered, and Minimal.
* "Midnight & Amber" default palette, with a solid or gradient background and configurable text and accent colors.
* Per-user author photos with Gravatar and site-default fallback, plus reading time and site name.
* Two-column settings screen with a live preview, and a Settings link on the Plugins row.
* Cached PNG output that refreshes on content or setting changes, and full uninstall cleanup.
