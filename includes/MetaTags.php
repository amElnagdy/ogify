<?php namespace OGify; if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Front-end Open Graph and Twitter meta tags.
 *
 * @package OGify
 */

final class MetaTags {
	/**
	 * Card renderer instance.
	 *
	 * @var CardImage
	 */
	private $card_image;

	/**
	 * Store the card renderer.
	 *
	 * @param CardImage $card_image Card renderer.
	 */
	public function __construct( CardImage $card_image ) {
		$this->card_image = $card_image;
	}

	/**
	 * Register front-end output hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', array( $this, 'render' ), 5 );
		add_filter( 'wpseo_opengraph_image', array( $this, 'filter_image' ) );
		add_filter( 'wpseo_twitter_image', array( $this, 'filter_image' ) );
		add_filter( 'rank_math/opengraph/facebook/og_image', array( $this, 'filter_image' ) );
		add_filter( 'rank_math/opengraph/twitter/twitter_image', array( $this, 'filter_image' ) );
	}

	/**
	 * Output the standalone meta tag set.
	 *
	 * @return void
	 */
	public function render(): void {
		$post_id = $this->get_current_public_post_id();
		if ( ! $post_id ) {
			return;
		}

		$post        = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$title       = get_the_title( $post );
		$description = $this->get_description( $post );
		$url         = get_permalink( $post );
		$settings    = Settings::get();
		$site_name   = ! empty( $settings['site_name_text'] ) ? $settings['site_name_text'] : get_bloginfo( 'name' );
		$image_url   = $this->card_image->get_image_url( $post_id );

		$this->render_property( 'og:type', 'article' );
		$this->render_property( 'og:title', $title );
		$this->render_property( 'og:description', $description );
		$this->render_property( 'og:url', $url, true );
		$this->render_property( 'og:site_name', $site_name );

		if ( '' !== $image_url ) {
			$this->render_property( 'og:image', $image_url, true );
			$this->render_property( 'og:image:secure_url', $image_url, true );
			$this->render_property( 'og:image:width', '1200' );
			$this->render_property( 'og:image:height', '630' );
			$this->render_property( 'og:image:type', 'image/png' );
			$this->render_property( 'og:image:alt', $title );
		}

		$this->render_property( 'article:published_time', get_post_time( 'c', false, $post, true ) );
		$this->render_property( 'article:modified_time', get_post_modified_time( 'c', false, $post, true ) );
		$this->render_name( 'twitter:card', 'summary_large_image' );
		$this->render_name( 'twitter:title', $title );
		$this->render_name( 'twitter:description', $description );

		if ( '' !== $image_url ) {
			$this->render_name( 'twitter:image', $image_url, true );
			$this->render_name( 'twitter:image:alt', $title );
		}
	}

	/**
	 * Return the OGify image URL for SEO plugin image filters.
	 *
	 * @param mixed $image Existing SEO plugin image value.
	 * @return mixed
	 */
	public function filter_image( $image ) {
		$post_id = $this->get_current_public_post_id();
		if ( ! $post_id ) {
			return $image;
		}

		$image_url = $this->card_image->get_image_url( $post_id );

		return '' !== $image_url ? $image_url : $image;
	}

	/**
	 * Get the current post ID when OGify should output.
	 *
	 * @return int
	 */
	private function get_current_public_post_id(): int {
		$settings = Settings::get();
		if ( empty( $settings['enabled'] ) || ! Plugin::has_gd() || ! is_singular() ) {
			return 0;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		$post_type  = get_post_type( $post );
		$post_types = isset( $settings['post_types'] ) ? (array) $settings['post_types'] : array();
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return 0;
		}

		if ( ! in_array( $post_type, $post_types, true ) ) {
			return 0;
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || empty( $post_type_object->public ) || ! is_post_publicly_viewable( $post ) ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * Build a clean description from excerpt, content, then title.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_description( \WP_Post $post ): string {
		$description = $this->clean_description( (string) $post->post_excerpt );

		if ( '' === $description ) {
			$description = $this->clean_description( (string) $post->post_content );
		}

		if ( '' === $description ) {
			$description = get_the_title( $post );
		}

		return $this->trim_description( $description );
	}

	/**
	 * Remove markup, shortcodes, entities, and noisy spacing.
	 *
	 * @param string $source Raw description source.
	 * @return string
	 */
	private function clean_description( string $source ): string {
		$text = wp_strip_all_tags( strip_shortcodes( $source ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( is_string( $text ) ? $text : '' );
	}

	/**
	 * Cap descriptions near 200 characters without cutting the last word.
	 *
	 * @param string $description Clean description.
	 * @return string
	 */
	private function trim_description( string $description ): string {
		if ( strlen( $description ) <= 200 ) {
			return $description;
		}

		$description = wp_html_excerpt( $description, 200, '' );
		$space       = strrpos( $description, ' ' );

		return trim( false === $space ? $description : substr( $description, 0, $space ) );
	}

	/**
	 * Render an Open Graph property tag.
	 *
	 * @param string $property Meta property.
	 * @param string $content Meta content.
	 * @param bool   $is_url Whether the content is a URL.
	 * @return void
	 */
	private function render_property( string $property, string $content, bool $is_url = false ): void {
		printf(
			'<meta property="%1$s" content="%2$s">' . "\n",
			esc_attr( $property ),
			$is_url ? esc_url( $content ) : esc_attr( $content )
		);
	}

	/**
	 * Render a named meta tag.
	 *
	 * @param string $name Meta name.
	 * @param string $content Meta content.
	 * @param bool   $is_url Whether the content is a URL.
	 * @return void
	 */
	private function render_name( string $name, string $content, bool $is_url = false ): void {
		printf(
			'<meta name="%1$s" content="%2$s">' . "\n",
			esc_attr( $name ),
			$is_url ? esc_url( $content ) : esc_attr( $content )
		);
	}
}
