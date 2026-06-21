<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Reading time helper.
 *
 * @package OGify
 */

final class ReadingTime {
	/**
	 * Calculate reading time in minutes for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function minutes( int $post_id ): int {
		$settings = get_option( 'ogify_settings', array() );
		$wpm      = 200;

		if ( is_array( $settings ) && ! empty( $settings['reading_wpm'] ) ) {
			$wpm = absint( $settings['reading_wpm'] );
		}

		if ( $wpm < 1 ) {
			$wpm = 200;
		}

		$content    = (string) get_post_field( 'post_content', $post_id );
		$text       = wp_strip_all_tags( strip_shortcodes( $content ) );
		$word_count = preg_match_all( '/\p{L}+/u', $text, $matches );

		if ( false === $word_count ) {
			$word_count = 0;
		}

		return (int) max( 1, ceil( $word_count / $wpm ) );
	}

	/**
	 * Get a translated reading time label for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function label( int $post_id ): string {
		$minutes = self::minutes( $post_id );

		/* translators: %d: reading time in minutes. */
		return sprintf( _n( '%d min read', '%d min read', $minutes, 'ogify' ), $minutes );
	}
}
