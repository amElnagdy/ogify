<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * GD card renderer and cache.
 *
 * @package OGify
 */

final class CardImage {
	const WIDTH       = 1200;
	const HEIGHT      = 630;
	const PADDING     = 80;
	const TITLE_WIDTH = 1040;
	const AVATAR_SIZE = 96;

	/**
	 * Ensure a cached card exists and return its public URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_image_url( int $post_id ): string {
		try {
			if ( ! $this->can_render() ) {
				return '';
			}

			$data = $this->resolve_inputs( $post_id );
			if ( ! $data ) {
				return '';
			}

			if ( file_exists( $data['path'] ) ) {
				return $data['url'];
			}

			if ( ! $this->render_to_file( $data ) ) {
				return '';
			}

			$this->cleanup_stale_images( $data['dir'], $post_id, $data['path'] );

			return $data['url'];
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/**
	 * Resolve WordPress data before passing it to GD.
	 *
	 * @param int $post_id Post ID.
	 * @return array|false
	 */
	private function resolve_inputs( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$settings        = Settings::get();
		$author_id       = (int) $post->post_author;
		$author          = get_userdata( $author_id );
		$author_name     = $author && ! empty( $author->display_name ) ? (string) $author->display_name : get_bloginfo( 'name' );
		$reading_minutes = ReadingTime::minutes( $post_id );
		$avatar          = $this->resolve_avatar( $author_id, $settings );
		$hash            = md5(
			serialize(
				array(
					'post_title'             => (string) $post->post_title,
					'reading_minutes'        => $reading_minutes,
					'author_display_name'    => $author_name,
					'author_photo_id_or_url' => $avatar['id_or_url'],
					'author_photo_mtime'     => $avatar['mtime'],
					'settings'               => $settings,
				)
			)
		);
		$upload_dir      = wp_upload_dir();

		if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) || ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$cache_dir = trailingslashit( $upload_dir['basedir'] ) . 'ogify';
		if ( ! wp_mkdir_p( $cache_dir ) || ! wp_is_writable( $cache_dir ) ) {
			return false;
		}

		$filename = $post_id . '-' . $hash . '.png';

		return array(
			'post_id'         => $post_id,
			'title'           => $this->sanitize_drawn_text( (string) $post->post_title ),
			'reading_label'   => $this->uppercase( ReadingTime::label( $post_id ) ),
			'author_name'     => $this->sanitize_drawn_text( $author_name ),
			'site_name'       => $this->resolve_site_name( $settings ),
			'avatar'          => $avatar,
			'settings'        => $settings,
			'dir'             => $cache_dir,
			'path'            => trailingslashit( $cache_dir ) . $filename,
			'url'             => rtrim( $upload_dir['baseurl'], '/' ) . '/ogify/' . $filename,
			'bold_font'       => OGIFY_PATH . 'assets/fonts/Inter-Bold.ttf',
			'regular_font'    => OGIFY_PATH . 'assets/fonts/Inter-Regular.ttf',
		);
	}

	/**
	 * Render the resolved card data to a PNG file.
	 *
	 * @param array $data Resolved render data.
	 * @return bool
	 */
	private function render_to_file( array $data ): bool {
		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( ! $image ) {
			return false;
		}

		imagealphablending( $image, true );
		imagesavealpha( $image, true );
		$this->draw_background( $image, $data['settings'] );

		if ( ! empty( $data['settings']['show_reading_time'] ) && ! $this->draw_badge( $image, $data ) ) {
			imagedestroy( $image );
			return false;
		}

		if ( ! $this->draw_title( $image, $data ) ) {
			imagedestroy( $image );
			return false;
		}

		if ( ! $this->draw_author_chip( $image, $data ) ) {
			imagedestroy( $image );
			return false;
		}

		// Write to a uniquely-named temp first; imagepng() honors the umask (world-readable),
		// unlike tempnam() which forces 0600 and would leave the served card unreadable.
		$tmp_file = $data['path'] . '.' . wp_generate_password( 12, false ) . '.tmp';
		$written  = imagepng( $image, $tmp_file, 6 );
		imagedestroy( $image );

		if ( ! $written ) {
			wp_delete_file( $tmp_file );
			return false;
		}

		// Publish atomically so a crawler hitting a freshly-published post never reads a
		// half-written PNG. WP_Filesystem::move() renames in place on the direct transport.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;

		if ( ! WP_Filesystem() || ! $wp_filesystem->move( $tmp_file, $data['path'], true ) ) {
			wp_delete_file( $tmp_file );
			return false;
		}

		return true;
	}

	/**
	 * Check GD/FreeType and bundled font availability.
	 *
	 * @return bool
	 */
	private function can_render(): bool {
		foreach ( array( 'imagecreatetruecolor', 'imagesavealpha', 'imagepng', 'imagettfbbox', 'imagettftext', 'imagecreatefromstring', 'imagecopyresampled', 'getimagesizefromstring' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return false;
			}
		}

		return is_readable( OGIFY_PATH . 'assets/fonts/Inter-Bold.ttf' ) && is_readable( OGIFY_PATH . 'assets/fonts/Inter-Regular.ttf' );
	}

	/**
	 * Draw the configured background.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param array             $settings Settings.
	 * @return void
	 */
	private function draw_background( $image, array $settings ): void {
		if ( 'gradient' !== $settings['bg_type'] ) {
			$color = $this->allocate_hex_color( $image, $settings['bg_color'] );
			imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $color );
			return;
		}

		$from = $this->hex_to_rgb( $settings['bg_gradient_from'] );
		$to   = $this->hex_to_rgb( $settings['bg_gradient_to'] );

		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$ratio = $y / ( self::HEIGHT - 1 );
			$rgb   = array(
				'r' => (int) round( $from['r'] + ( ( $to['r'] - $from['r'] ) * $ratio ) ),
				'g' => (int) round( $from['g'] + ( ( $to['g'] - $from['g'] ) * $ratio ) ),
				'b' => (int) round( $from['b'] + ( ( $to['b'] - $from['b'] ) * $ratio ) ),
			);

			imageline( $image, 0, $y, self::WIDTH, $y, $this->allocate_rgb_color( $image, $rgb ) );
		}
	}

	/**
	 * Draw the reading-time badge.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param array             $data Resolved render data.
	 * @return bool
	 */
	private function draw_badge( $image, array $data ): bool {
		$font       = $data['bold_font'];
		$text       = $data['reading_label'];
		$font_size  = 24;
		$text_width = $this->text_width( $text, $font, $font_size );
		$x          = self::PADDING;
		$y          = 70;
		$height     = 46;
		$width      = $text_width + 44;
		$bg         = $this->allocate_hex_color( $image, $data['settings']['accent_color'] );
		$text_rgb   = $this->badge_text_rgb( $data['settings']['accent_color'] );
		$text_color = $this->allocate_rgb_color( $image, $text_rgb );
		$bbox       = $this->text_bbox( $text, $font, $font_size );
		$baseline   = (int) round( $y + ( ( $height - $this->bbox_height( $bbox ) ) / 2 ) - $this->bbox_min_y( $bbox ) );

		$this->draw_pill( $image, $x, $y, $width, $height, $bg );

		return $this->draw_text( $image, $text, $font_size, $x + 22, $baseline, $text_color, $font );
	}

	/**
	 * Draw the title block and return whether GD accepted the text.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param array             $data Resolved render data.
	 * @return bool
	 */
	private function draw_title( $image, array $data ): bool {
		$font        = $data['bold_font'];
		$title       = $data['title'];
		$text_color  = $this->allocate_hex_color( $image, $data['settings']['text_color'] );
		$shadow      = $this->allocate_rgb_color( $image, array( 'r' => 0, 'g' => 0, 'b' => 0 ), 62 );
		$font_size   = 64;
		$title_top   = ! empty( $data['settings']['show_reading_time'] ) ? 176 : 120;
		$line_height = 76;

		foreach ( array( 64, 60, 56 ) as $size ) {
			list( $lines, $total_height ) = $this->measure_title_block( $title, $font, $size, self::TITLE_WIDTH, 3 );
			$font_size                    = $size;
			$line_height                  = (int) round( $size * 1.18 );

			if ( ! $this->has_ellipsis( $lines ) || 56 === $size ) {
				break;
			}
		}

		$needs_shadow = $this->needs_title_shadow( $data['settings'] );
		foreach ( $lines as $index => $line ) {
			$baseline = $title_top + $font_size + ( $index * $line_height );

			if ( $needs_shadow && ! $this->draw_text( $image, $line, $font_size, self::PADDING + 1, $baseline + 1, $shadow, $font ) ) {
				return false;
			}

			if ( ! $this->draw_text( $image, $line, $font_size, self::PADDING, $baseline, $text_color, $font ) ) {
				return false;
			}
		}

		$data['title_height'] = $total_height;

		return true;
	}

	/**
	 * Draw the author row.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param array             $data Resolved render data.
	 * @return bool
	 */
	private function draw_author_chip( $image, array $data ): bool {
		$settings  = $data['settings'];
		$show_name = ! empty( $settings['show_author_name'] ) && '' !== $data['author_name'];
		$show_site = ! empty( $settings['show_site_name'] ) && '' !== $data['site_name'];
		$show_photo = ! empty( $settings['show_author_photo'] ) && '' !== $data['avatar']['bytes'];

		if ( ! $show_name && ! $show_site && ! $show_photo ) {
			return true;
		}

		$title_top    = ! empty( $settings['show_reading_time'] ) ? 176 : 120;
		$title_layout = $this->measure_title_block( $data['title'], $data['bold_font'], 56, self::TITLE_WIDTH, 3 );
		$y            = max( 454, $title_top + $title_layout[1] + 54 );
		$x            = self::PADDING;
		$text_x       = $x;

		if ( $show_photo ) {
			$avatar = $this->create_circular_avatar( $data['avatar']['bytes'], self::AVATAR_SIZE );
			if ( $avatar ) {
				imagecopy( $image, $avatar, $x, $y, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE );
				imagedestroy( $avatar );
				$text_x += 122;
			}
		}

		$text_color = $this->allocate_hex_color( $image, $settings['text_color'] );
		$muted      = $this->allocate_hex_color( $image, $settings['text_color'], 46 );

		if ( $show_name && ! $this->draw_text( $image, $data['author_name'], 32, $text_x, $y + 39, $text_color, $data['bold_font'] ) ) {
			return false;
		}

		if ( $show_site ) {
			$baseline = $show_name ? $y + 77 : $y + 52;
			if ( ! $this->draw_text( $image, $data['site_name'], 26, $text_x, $baseline, $muted, $data['regular_font'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Word-wrap and measure a title block.
	 *
	 * @param string $text Text to wrap.
	 * @param string $font Font path.
	 * @param int    $font_size Font size.
	 * @param int    $max_width Max pixel width.
	 * @param int    $max_lines Max line count.
	 * @return array
	 */
	private function measure_title_block( string $text, string $font, int $font_size, int $max_width, int $max_lines ): array {
		$normalized = preg_replace( '/\s+/u', ' ', $text );
		$text       = trim( is_string( $normalized ) ? $normalized : $text );
		if ( '' === $text ) {
			return array( array(), 0 );
		}

		$units = $this->wrap_units( $text, $font, $font_size, $max_width );
		$lines = array();
		$line  = '';

		foreach ( $units as $unit ) {
			$candidate = '' === $line ? $unit['text'] : $line . $unit['sep'] . $unit['text'];
			if ( '' === $line || $this->text_width( $candidate, $font, $font_size ) <= $max_width ) {
				$line = $candidate;
				continue;
			}

			$lines[] = $line;
			$line    = $unit['text'];
		}

		if ( '' !== $line ) {
			$lines[] = $line;
		}

		if ( count( $lines ) > $max_lines ) {
			$lines = array_slice( $lines, 0, $max_lines );
			$lines[ $max_lines - 1 ] = $this->ellipsize_line( $lines[ $max_lines - 1 ], $font, $font_size, $max_width );
		}

		$line_height  = (int) round( $font_size * 1.18 );
		$total_height = count( $lines ) * $line_height;

		return array( $lines, $total_height );
	}

	/**
	 * Convert text into wrap units without splitting multibyte characters.
	 *
	 * @param string $text Text.
	 * @param string $font Font path.
	 * @param int    $font_size Font size.
	 * @param int    $max_width Max pixel width.
	 * @return array
	 */
	private function wrap_units( string $text, string $font, int $font_size, int $max_width ): array {
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			$words = array_filter( explode( ' ', $text ) );
		}

		$units = array();
		foreach ( $words as $word_index => $word ) {
			$sep = 0 === $word_index ? '' : ' ';

			if ( $this->text_width( $word, $font, $font_size ) <= $max_width ) {
				$units[] = array( 'text' => $word, 'sep' => $sep );
				continue;
			}

			$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $chars ) ) {
				$chars = str_split( $word );
			}

			foreach ( $chars as $char_index => $char ) {
				$units[] = array(
					'text' => $char,
					'sep'  => 0 === $char_index ? $sep : '',
				);
			}
		}

		return $units;
	}

	/**
	 * Fit an ellipsis on a capped line.
	 *
	 * @param string $line Line text.
	 * @param string $font Font path.
	 * @param int    $font_size Font size.
	 * @param int    $max_width Max pixel width.
	 * @return string
	 */
	private function ellipsize_line( string $line, string $font, int $font_size, int $max_width ): string {
		$ellipsis = html_entity_decode( '&hellip;', ENT_QUOTES, 'UTF-8' );
		$line     = trim( $line );

		if ( $this->text_width( $line . $ellipsis, $font, $font_size ) <= $max_width ) {
			return $line . $ellipsis;
		}

		$parts = preg_match( '/\s/u', $line ) ? preg_split( '/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY ) : preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			$parts = str_split( $line );
		}

		while ( ! empty( $parts ) ) {
			array_pop( $parts );
			$candidate = ( preg_match( '/\s/u', $line ) ? implode( ' ', $parts ) : implode( '', $parts ) ) . $ellipsis;

			if ( $this->text_width( $candidate, $font, $font_size ) <= $max_width ) {
				return $candidate;
			}
		}

		return $ellipsis;
	}

	/**
	 * Resolve the first usable avatar source.
	 *
	 * @param int   $author_id Author user ID.
	 * @param array $settings Settings.
	 * @return array
	 */
	private function resolve_avatar( int $author_id, array $settings ): array {
		$empty = array(
			'id_or_url' => '',
			'mtime'     => 0,
			'bytes'     => '',
		);

		if ( empty( $settings['show_author_photo'] ) ) {
			return $empty;
		}

		$photo_id = Profile::photo_attachment_id( $author_id );
		$source   = $this->attachment_avatar( $photo_id );
		if ( $source ) {
			return $source;
		}

		$avatar_url = get_avatar_url( $author_id, array( 'size' => 256 ) );
		if ( is_string( $avatar_url ) && '' !== $avatar_url ) {
			$source = $this->remote_avatar( $avatar_url );
			if ( $source ) {
				return $source;
			}
		}

		$source = $this->attachment_avatar( absint( $settings['default_author_photo'] ) );

		return $source ? $source : $empty;
	}

	/**
	 * Load an attachment avatar.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false
	 */
	private function attachment_avatar( int $attachment_id ) {
		if ( ! $attachment_id ) {
			return false;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_readable( $path ) || ! getimagesize( $path ) ) {
			return false;
		}

		$bytes = file_get_contents( $path );
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return false;
		}

		return array(
			'id_or_url' => 'attachment:' . $attachment_id,
			'mtime'     => filemtime( $path ) ? filemtime( $path ) : 0,
			'bytes'     => $bytes,
		);
	}

	/**
	 * Fetch and validate a remote avatar.
	 *
	 * @param string $url Avatar URL.
	 * @return array|false
	 */
	private function remote_avatar( string $url ) {
		$response = wp_remote_get( $url, array( 'timeout' => 3 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( is_array( $content_type ) ) {
			$content_type = reset( $content_type );
		}

		if ( is_string( $content_type ) && '' !== $content_type && false === strpos( strtolower( $content_type ), 'image/' ) ) {
			return false;
		}

		$bytes = wp_remote_retrieve_body( $response );
		if ( ! is_string( $bytes ) || '' === $bytes || ! getimagesizefromstring( $bytes ) ) {
			return false;
		}

		return array(
			'id_or_url' => $url,
			'mtime'     => 0,
			'bytes'     => $bytes,
		);
	}

	/**
	 * Create a supersampled circular avatar.
	 *
	 * @param string $bytes Image bytes.
	 * @param int    $size Target size.
	 * @return resource|\GdImage|false
	 */
	private function create_circular_avatar( string $bytes, int $size ) {
		$source = imagecreatefromstring( $bytes );
		if ( ! $source ) {
			return false;
		}

		$source_width  = imagesx( $source );
		$source_height = imagesy( $source );
		$crop_size     = min( $source_width, $source_height );
		$crop_x        = (int) floor( ( $source_width - $crop_size ) / 2 );
		$crop_y        = (int) floor( ( $source_height - $crop_size ) / 2 );
		$super_size    = min( 384, $size * 4 );
		$large         = imagecreatetruecolor( $super_size, $super_size );
		$avatar        = imagecreatetruecolor( $size, $size );

		if ( ! $large || ! $avatar ) {
			imagedestroy( $source );
			return false;
		}

		imagealphablending( $large, false );
		imagesavealpha( $large, true );
		imagefilledrectangle( $large, 0, 0, $super_size, $super_size, $this->allocate_rgb_color( $large, array( 'r' => 0, 'g' => 0, 'b' => 0 ), 127 ) );
		imagecopyresampled( $large, $source, 0, 0, $crop_x, $crop_y, $super_size, $super_size, $crop_size, $crop_size );

		$radius = $super_size / 2;
		$limit  = $radius * $radius;
		$clear  = $this->allocate_rgb_color( $large, array( 'r' => 0, 'g' => 0, 'b' => 0 ), 127 );

		for ( $y = 0; $y < $super_size; $y++ ) {
			for ( $x = 0; $x < $super_size; $x++ ) {
				$dx = $x + 0.5 - $radius;
				$dy = $y + 0.5 - $radius;

				if ( ( $dx * $dx ) + ( $dy * $dy ) > $limit ) {
					imagesetpixel( $large, $x, $y, $clear );
				}
			}
		}

		imagealphablending( $avatar, false );
		imagesavealpha( $avatar, true );
		imagecopyresampled( $avatar, $large, 0, 0, 0, 0, $size, $size, $super_size, $super_size );
		imagedestroy( $source );
		imagedestroy( $large );

		return $avatar;
	}

	/**
	 * Sanitize text before it is drawn into a PNG.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function sanitize_drawn_text( string $text ): string {
		$charset = get_bloginfo( 'charset' );
		$charset = $charset ? $charset : 'UTF-8';
		$text    = wp_strip_all_tags( strip_shortcodes( $text ) );
		$text    = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, $charset );
		$text    = preg_replace( '/\s+/u', ' ', $text );

		return trim( is_string( $text ) ? $text : '' );
	}

	/**
	 * Resolve the displayed site name.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	private function resolve_site_name( array $settings ): string {
		$name = isset( $settings['site_name_text'] ) ? $this->sanitize_drawn_text( (string) $settings['site_name_text'] ) : '';
		if ( '' !== $name ) {
			return $name;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}

	/**
	 * Remove older cached images for the same post.
	 *
	 * @param string $dir Cache directory.
	 * @param int    $post_id Post ID.
	 * @param string $keep_path Current image path.
	 * @return void
	 */
	private function cleanup_stale_images( string $dir, int $post_id, string $keep_path ): void {
		$files = glob( trailingslashit( $dir ) . $post_id . '-*.png' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( $file !== $keep_path ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Draw a rounded pill.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param int               $x X position.
	 * @param int               $y Y position.
	 * @param int               $width Width.
	 * @param int               $height Height.
	 * @param int               $color Color.
	 * @return void
	 */
	private function draw_pill( $image, int $x, int $y, int $width, int $height, int $color ): void {
		$radius = (int) floor( $height / 2 );
		imagefilledrectangle( $image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color );
		imagefilledellipse( $image, $x + $radius, $y + $radius, $height, $height, $color );
		imagefilledellipse( $image, $x + $width - $radius, $y + $radius, $height, $height, $color );
	}

	/**
	 * Draw one TTF text run.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param string            $text Text.
	 * @param int               $font_size Font size.
	 * @param int               $x X position.
	 * @param int               $y Baseline position.
	 * @param int               $color Color.
	 * @param string            $font Font path.
	 * @return bool
	 */
	private function draw_text( $image, string $text, int $font_size, int $x, int $y, int $color, string $font ): bool {
		return false !== imagettftext( $image, $font_size, 0, $x, $y, $color, $font, $text );
	}

	/**
	 * Decide if the title needs a contrast shadow.
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	private function needs_title_shadow( array $settings ): bool {
		$text = $this->hex_to_rgb( $settings['text_color'] );
		$bgs  = 'gradient' === $settings['bg_type'] ? array( $settings['bg_gradient_from'], $settings['bg_gradient_to'] ) : array( $settings['bg_color'] );

		foreach ( $bgs as $bg ) {
			if ( $this->contrast_ratio( $text, $this->hex_to_rgb( $bg ) ) < 3 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Pick black or white badge text from accent luminance.
	 *
	 * @param string $hex Accent hex color.
	 * @return array
	 */
	private function badge_text_rgb( string $hex ): array {
		$rgb = $this->hex_to_rgb( $hex );
		$yiq = ( ( $rgb['r'] * 299 ) + ( $rgb['g'] * 587 ) + ( $rgb['b'] * 114 ) ) / 1000;

		return $yiq > 128 ? array( 'r' => 0, 'g' => 0, 'b' => 0 ) : array( 'r' => 255, 'g' => 255, 'b' => 255 );
	}

	/**
	 * Calculate WCAG contrast ratio.
	 *
	 * @param array $a First RGB color.
	 * @param array $b Second RGB color.
	 * @return float
	 */
	private function contrast_ratio( array $a, array $b ): float {
		$l1 = $this->relative_luminance( $a );
		$l2 = $this->relative_luminance( $b );

		if ( $l1 < $l2 ) {
			$tmp = $l1;
			$l1  = $l2;
			$l2  = $tmp;
		}

		return ( $l1 + 0.05 ) / ( $l2 + 0.05 );
	}

	/**
	 * Calculate relative luminance.
	 *
	 * @param array $rgb RGB color.
	 * @return float
	 */
	private function relative_luminance( array $rgb ): float {
		$channels = array();
		foreach ( array( 'r', 'g', 'b' ) as $key ) {
			$value = $rgb[ $key ] / 255;
			$channels[ $key ] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}

		return ( 0.2126 * $channels['r'] ) + ( 0.7152 * $channels['g'] ) + ( 0.0722 * $channels['b'] );
	}

	/**
	 * Allocate a color from hex.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param string            $hex Hex color.
	 * @param int               $alpha GD alpha value.
	 * @return int
	 */
	private function allocate_hex_color( $image, string $hex, int $alpha = 0 ): int {
		return $this->allocate_rgb_color( $image, $this->hex_to_rgb( $hex ), $alpha );
	}

	/**
	 * Allocate an RGB color.
	 *
	 * @param resource|\GdImage $image Image resource.
	 * @param array             $rgb RGB color.
	 * @param int               $alpha GD alpha value.
	 * @return int
	 */
	private function allocate_rgb_color( $image, array $rgb, int $alpha = 0 ): int {
		$color = imagecolorallocatealpha( $image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha );

		return false === $color ? 0 : $color;
	}

	/**
	 * Parse a hex color.
	 *
	 * @param string $hex Hex color.
	 * @return array
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( trim( $hex ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 'r' => 0, 'g' => 0, 'b' => 0 );
		}

		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Measure text width.
	 *
	 * @param string $text Text.
	 * @param string $font Font path.
	 * @param int    $font_size Font size.
	 * @return int
	 */
	private function text_width( string $text, string $font, int $font_size ): int {
		return $this->bbox_width( $this->text_bbox( $text, $font, $font_size ) );
	}

	/**
	 * Return a safe TTF bounding box.
	 *
	 * @param string $text Text.
	 * @param string $font Font path.
	 * @param int    $font_size Font size.
	 * @return array
	 */
	private function text_bbox( string $text, string $font, int $font_size ): array {
		$bbox = imagettfbbox( $font_size, 0, $font, $text );

		return is_array( $bbox ) ? $bbox : array( 0, 0, 0, 0, 0, 0, 0, 0 );
	}

	/**
	 * Calculate bounding-box width.
	 *
	 * @param array $bbox TTF bounding box.
	 * @return int
	 */
	private function bbox_width( array $bbox ): int {
		$xs = array( $bbox[0], $bbox[2], $bbox[4], $bbox[6] );

		return (int) ( max( $xs ) - min( $xs ) );
	}

	/**
	 * Calculate bounding-box height.
	 *
	 * @param array $bbox TTF bounding box.
	 * @return int
	 */
	private function bbox_height( array $bbox ): int {
		$ys = array( $bbox[1], $bbox[3], $bbox[5], $bbox[7] );

		return (int) ( max( $ys ) - min( $ys ) );
	}

	/**
	 * Get bounding-box minimum Y.
	 *
	 * @param array $bbox TTF bounding box.
	 * @return int
	 */
	private function bbox_min_y( array $bbox ): int {
		return (int) min( array( $bbox[1], $bbox[3], $bbox[5], $bbox[7] ) );
	}

	/**
	 * Detect if wrapped lines were capped.
	 *
	 * @param array $lines Lines.
	 * @return bool
	 */
	private function has_ellipsis( array $lines ): bool {
		$ellipsis = html_entity_decode( '&hellip;', ENT_QUOTES, 'UTF-8' );

		foreach ( $lines as $line ) {
			if ( false !== strpos( $line, $ellipsis ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Uppercase a UTF-8 label when mbstring is available.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function uppercase( string $text ): string {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
	}
}
