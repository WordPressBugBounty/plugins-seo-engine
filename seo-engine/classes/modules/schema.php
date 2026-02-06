<?php

class Meow_MWSEO_Modules_Schema
{
	private $core = null;
	private $schema_enabled = false;

	public function __construct( $core )
	{
		$this->core = $core;
		$this->init();
	}

	public function init()
	{
		$this->schema_enabled = $this->core->get_option( 'auto_schema_enabled', true );

		if ( $this->schema_enabled ) {
			if ( !is_admin() ) {
				add_action( 'wp_head', array( $this, 'inject_schema_markup' ), 5 );
			}
			// Let the scoring system know we're generating schema
			add_filter( 'seo_engine_has_schema', array( $this, 'confirm_schema_present' ), 10, 2 );
		}
	}

	/**
	 * Confirm that schema is present for scoring system
	 */
	public function confirm_schema_present( $has_schema, $post )
	{
		// If schema module is enabled and we have a valid post, we're generating schema
		if ( $post && is_object( $post ) && isset( $post->ID ) ) {
			return true;
		}
		return $has_schema;
	}

	/**
	 * Inject JSON-LD schema markup into wp_head
	 */
	public function inject_schema_markup()
	{
		// Only run on singular posts
		if ( !is_singular() ) {
			return;
		}

		global $post;
		if ( !$post ) {
			return;
		}

		$schema = $this->generate_schema( $post );
		if ( $schema ) {
			echo '<script type="application/ld+json" class="mwseo-schema">' . "\n";
			echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			echo "\n" . '</script>' . "\n";
		}
	}

	/**
	 * Generate schema markup for a post
	 *
	 * @param WP_Post $post The post object
	 * @return array|null Schema array or null if not applicable
	 */
	private function generate_schema( $post )
	{
		// Get schema type based on post type
		$schema_by_post_type = $this->core->get_option( 'schema_by_post_type', array(
			'post' => 'Article',
			'page' => 'WebPage',
			'product' => 'Product',
		) );

		$post_type = get_post_type( $post );
		$schema_type = isset( $schema_by_post_type[ $post_type ] ) ? $schema_by_post_type[ $post_type ] : 'Article';

		// Generate appropriate schema based on type
		switch ( $schema_type ) {
			case 'Product':
				return $this->generate_product_schema( $post );
			case 'WebPage':
				return $this->generate_webpage_schema( $post );
			case 'Article':
			default:
				return $this->generate_article_schema( $post );
		}
	}

	/**
	 * Generate Article schema
	 */
	private function generate_article_schema( $post )
	{
		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Article',
		);

		// Headline (required)
		$schema['headline'] = get_the_title( $post );

		// Description
		$excerpt = $this->core->build_excerpt( $post );
		if ( !empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// DatePublished (required)
		$schema['datePublished'] = get_the_date( 'c', $post );

		// DateModified (required)
		$schema['dateModified'] = get_the_modified_date( 'c', $post );

		// Author (required)
		$author = get_the_author_meta( 'display_name', $post->post_author );
		if ( !empty( $author ) ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name' => $author,
				'url' => get_author_posts_url( $post->post_author )
			);
		}

		// Publisher (required for Article)
		$site_name = get_bloginfo( 'name' );
		$site_logo = get_site_icon_url();

		$publisher = array(
			'@type' => 'Organization',
			'name' => $site_name,
			'url' => home_url(),
		);

		if ( !empty( $site_logo ) ) {
			$publisher['logo'] = array(
				'@type' => 'ImageObject',
				'url' => $site_logo,
			);
		}

		$schema['publisher'] = $publisher;

		// Image (recommended)
		$featured_image_id = get_post_thumbnail_id( $post );
		if ( $featured_image_id ) {
			$image_url = get_the_post_thumbnail_url( $post, 'large' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// MainEntityOfPage
		$schema['mainEntityOfPage'] = array(
			'@type' => 'WebPage',
			'@id' => get_permalink( $post ),
		);

		return apply_filters( 'mwseo_schema_article', $schema, $post );
	}

	/**
	 * Generate WebPage schema
	 */
	private function generate_webpage_schema( $post )
	{
		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'WebPage',
		);

		// Name (title)
		$schema['name'] = get_the_title( $post );

		// Description
		$excerpt = $this->core->build_excerpt( $post );
		if ( !empty( $excerpt ) ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// URL
		$schema['url'] = get_permalink( $post );

		// DatePublished
		$schema['datePublished'] = get_the_date( 'c', $post );

		// DateModified
		$schema['dateModified'] = get_the_modified_date( 'c', $post );

		// Image
		$featured_image_id = get_post_thumbnail_id( $post );
		if ( $featured_image_id ) {
			$image_url = get_the_post_thumbnail_url( $post, 'large' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		return apply_filters( 'mwseo_schema_webpage', $schema, $post );
	}

	/**
	 * Generate Product schema (for WooCommerce or similar)
	 */
	private function generate_product_schema( $post )
	{
		// Check if WooCommerce is active and this is a product
		if ( !function_exists( 'wc_get_product' ) ) {
			return $this->generate_article_schema( $post );
		}

		$product = wc_get_product( $post->ID );
		if ( !$product ) {
			return $this->generate_article_schema( $post );
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Product',
		);

		// Name (required)
		$schema['name'] = $product->get_name();

		// Description
		$description = $product->get_short_description();
		if ( empty( $description ) ) {
			$description = $product->get_description();
		}
		if ( !empty( $description ) ) {
			$schema['description'] = wp_strip_all_tags( $description );
		}

		// Image
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// SKU
		$sku = $product->get_sku();
		if ( !empty( $sku ) ) {
			$schema['sku'] = $sku;
		}

		// Offers (required for Product)
		$schema['offers'] = array(
			'@type' => 'Offer',
			'url' => get_permalink( $post ),
			'priceCurrency' => get_woocommerce_currency(),
			'price' => $product->get_price(),
			'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
		);

		return apply_filters( 'mwseo_schema_product', $schema, $post, $product );
	}
}

?>
