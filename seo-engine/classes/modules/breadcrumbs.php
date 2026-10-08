<?php

class Meow_MWSEO_Modules_Breadcrumbs
{
	const PRIMARY_CATEGORY_META = '_mwseo_primary_category';

	private $core = null;

	public function __construct( $core )
	{
		$this->core = $core;

		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_shortcode( 'seo_engine_breadcrumbs', array( $this, 'shortcode' ) );

		if ( !is_admin() && $this->core->get_option( 'auto_schema_enabled', false ) ) {
			add_action( 'wp_head', array( $this, 'inject_schema' ), 6 );
		}
	}

	#region Primary Category

	public function register_meta()
	{
		foreach ( $this->get_category_post_types() as $post_type ) {
			register_post_meta( $post_type, self::PRIMARY_CATEGORY_META, array(
				'type' => 'integer',
				'single' => true,
				'default' => 0,
				'show_in_rest' => true,
				'sanitize_callback' => 'absint',
				'auth_callback' => function( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			) );
		}
	}

	private function get_category_post_types()
	{
		return array_values( array_filter( get_post_types( array( 'show_in_rest' => true ) ), function( $post_type ) {
			return is_object_in_taxonomy( $post_type, 'category' );
		} ) );
	}

	public function enqueue_editor_assets()
	{
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( !$screen || !in_array( $screen->post_type, $this->get_category_post_types(), true ) ) {
			return;
		}
		$file = MWSEO_PATH . '/app/primary-category.js';
		wp_enqueue_script( 'mwseo-primary-category', MWSEO_URL . 'app/primary-category.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			file_exists( $file ) ? filemtime( $file ) : MWSEO_VERSION, true );
	}

	/**
	 * The category that represents the post in breadcrumbs. Our own choice first, then
	 * the one set in Yoast or Rank Math (so switching keeps it), then the first category.
	 * A stored choice only counts while the post is still in that category.
	 */
	public function get_primary_category( $post )
	{
		$post = get_post( $post );
		if ( !$post || !is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			return null;
		}
		$categories = get_the_category( $post->ID );
		if ( empty( $categories ) ) {
			return null;
		}
		$assigned = wp_list_pluck( $categories, 'term_id' );
		$primary = null;
		foreach ( array( self::PRIMARY_CATEGORY_META, '_yoast_wpseo_primary_category', 'rank_math_primary_category' ) as $meta_key ) {
			$term_id = (int) get_post_meta( $post->ID, $meta_key, true );
			if ( $term_id && in_array( $term_id, $assigned, true ) ) {
				$primary = get_term( $term_id, 'category' );
				break;
			}
		}
		if ( !$primary || is_wp_error( $primary ) ) {
			$primary = $categories[0];
		}
		return apply_filters( 'mwseo_primary_category', $primary, $post );
	}

	#endregion

	#region Trail

	/**
	 * The breadcrumb trail for the current request, as a list of [ 'name', 'url' ].
	 */
	public function get_trail()
	{
		$trail = array( array( 'name' => __( 'Home', 'seo-engine' ), 'url' => home_url( '/' ) ) );

		if ( is_front_page() ) {
			return apply_filters( 'mwseo_breadcrumbs', array(), null );
		}

		$object = get_queried_object();

		if ( is_singular() && $object instanceof WP_Post ) {
			if ( is_post_type_hierarchical( $object->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $object ) ) as $ancestor_id ) {
					$trail[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
				}
			}
			else {
				$primary = $this->get_primary_category( $object );
				if ( $primary ) {
					$trail = array_merge( $trail, $this->get_term_items( $primary ) );
				}
			}
			$trail[] = array( 'name' => get_the_title( $object ), 'url' => get_permalink( $object ) );
		}
		else if ( ( is_category() || is_tag() || is_tax() ) && $object instanceof WP_Term ) {
			$trail = array_merge( $trail, $this->get_term_items( $object ) );
		}
		else if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			if ( $page_for_posts ) {
				$trail[] = array( 'name' => get_the_title( $page_for_posts ), 'url' => get_permalink( $page_for_posts ) );
			}
		}
		else if ( is_search() ) {
			$trail[] = array( 'name' => sprintf( __( 'Search: %s', 'seo-engine' ), get_search_query() ), 'url' => '' );
		}
		else if ( is_404() ) {
			$trail[] = array( 'name' => __( 'Page not found', 'seo-engine' ), 'url' => '' );
		}
		else if ( is_archive() ) {
			$trail[] = array( 'name' => wp_strip_all_tags( get_the_archive_title() ), 'url' => '' );
		}

		$trail = count( $trail ) > 1 ? $trail : array();
		return apply_filters( 'mwseo_breadcrumbs', $trail, $object );
	}

	private function get_term_items( $term )
	{
		$items = array();
		$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		foreach ( array_merge( $ancestors, array( $term->term_id ) ) as $term_id ) {
			$item = get_term( $term_id, $term->taxonomy );
			if ( !$item || is_wp_error( $item ) ) {
				continue;
			}
			$link = get_term_link( $item );
			$items[] = array( 'name' => $item->name, 'url' => is_wp_error( $link ) ? '' : $link );
		}
		return $items;
	}

	#endregion

	#region Output

	public function inject_schema()
	{
		if ( !$this->core->should_render_frontend_meta() ) {
			return;
		}
		$trail = $this->get_trail();
		if ( empty( $trail ) ) {
			return;
		}
		$elements = array();
		foreach ( array_values( $trail ) as $index => $item ) {
			$element = array(
				'@type' => 'ListItem',
				'position' => $index + 1,
				'name' => wp_strip_all_tags( $item['name'] ),
			);
			if ( !empty( $item['url'] ) ) {
				$element['item'] = $item['url'];
			}
			$elements[] = $element;
		}
		$schema = apply_filters( 'mwseo_schema_breadcrumbs', array(
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $elements,
		) );
		echo '<script type="application/ld+json" class="mwseo-schema-breadcrumbs">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo "\n" . '</script>' . "\n";
	}

	public function shortcode( $atts )
	{
		$atts = shortcode_atts( array( 'separator' => '›' ), $atts, 'seo_engine_breadcrumbs' );
		$trail = $this->get_trail();
		if ( empty( $trail ) ) {
			return '';
		}
		$last = count( $trail ) - 1;
		$parts = array();
		foreach ( array_values( $trail ) as $index => $item ) {
			$name = esc_html( wp_strip_all_tags( $item['name'] ) );
			if ( $index === $last || empty( $item['url'] ) ) {
				$parts[] = '<span class="mwseo-breadcrumbs-current" aria-current="page">' . $name . '</span>';
			}
			else {
				$parts[] = '<a href="' . esc_url( $item['url'] ) . '">' . $name . '</a>';
			}
		}
		$separator = ' <span class="mwseo-breadcrumbs-separator" aria-hidden="true">' . esc_html( $atts['separator'] ) . '</span> ';
		return '<nav class="mwseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'seo-engine' ) . '">' . implode( $separator, $parts ) . '</nav>';
	}

	#endregion
}
