<?php
class Meow_MWSEO_Admin extends MeowKit_MWSEO_Admin {

	public $core;

	public function __construct( $core ) {
		$this->core = $core;
		
		parent::__construct( MWSEO_PREFIX, MWSEO_ENTRY, MWSEO_DOMAIN, class_exists( 'MeowPro_MWSEO_Core' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'app_menu' ) );
			// Move "Surgical SEO" to sit right under "All Posts" (the position arg is unreliable
			// once other plugins add their own items, so we reorder the submenu explicitly).
			add_action( 'admin_menu', array( $this, 'reorder_surgical_submenu' ), 999 );

			// Load the scripts only if they are needed by the current screen
			$page = isset( $_GET["page"] ) ? sanitize_text_field( $_GET["page"] ) : null;

			$is_seo_engine_screen = in_array( $page, [ MWSEO_PREFIX . '_settings', 'seo_engine_dashboard' ] );
			$is_meowapps_dashboard = $page === 'meowapps-main-menu';
			$is_surgical_screen = in_array( $page, [ 'mwseo_surgical_post', 'mwseo_surgical_page' ], true );

			if ( $is_meowapps_dashboard || $is_seo_engine_screen || $is_surgical_screen ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			}
		}

		add_action( 'wp_head', array( $this, 'seo_engine_headers' ) );
		add_action( 'wp_head', array( $this, 'seo_engine_sitemap_headers' ) );
	}

	function seo_engine_sitemap_headers() {

		$disabled = $this->core->get_option( 'disable_wp_sitemap', false );
   		$custom   = $this->core->get_option( 'sitemap_custom', false );

		if( $disabled && !$custom ) {
			return;
		}

		$excluded_posts = $this->core->get_option( 'sitemap_excluded_post_ids', [] );
		$excluded_posts = array_map( 'intval', $excluded_posts );
		$post_id = get_the_ID();

		if( !in_array( $post_id, $excluded_posts ) ) {
			return;
		}

		echo '<!-- SEO Engine: This post is excluded from the sitemap -->';
		echo '<meta name="robots" content="noindex, follow">';
	}

	function seo_engine_headers() {
		if( !$this->core->get_option( 'social_networks', false ) ) { return; }
		// Step back when another SEO plugin is handling the frontend meta tags.
		if( !$this->core->should_render_frontend_meta() ) { return; }

		// use open graph tags for social networks, we should use the featured image, title and excerpt
		if ( is_single() || is_page() ) {
			$featured_image = apply_filters( 'mwseo_featured_image', get_the_post_thumbnail_url(), get_the_ID() );
			$featured_image_alt = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
			$excerpt = get_the_excerpt();
			$title = get_the_title();
			$site_name = get_bloginfo('name');
			$site_url = get_bloginfo('url');
			$site_domain_name = parse_url($site_url, PHP_URL_HOST);
			$site_description = get_bloginfo('description');
			$site_icon = get_site_icon_url();

			$site_twitter = $this->core->get_option('social_networks_twitter', null);
			if ( !empty( $site_twitter ) ) {
				$site_twitter = $site_twitter[0] === '@' ? $site_twitter : '@' . $site_twitter;
			}

			$site_facebook_app_id = $this->core->get_option('social_networks_facebook_app_id', null);
			
			#region General Open Graph tags
			
			echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">';
			echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">';
			echo '<meta property="og:title" content="' . esc_attr($title) . '">';
			echo '<meta property="og:description" content="' . esc_attr($excerpt) . '">';
			echo '<meta property="og:image" content="' . esc_url($featured_image) . '">';
			echo '<meta property="og:image:alt" content="' . esc_attr($featured_image_alt) . '">';
			// Homepage (whether "Latest posts" or a static page in Settings → Reading) is
			// a "website" per OG spec; only single posts/pages get "article".
			$og_type = is_front_page() ? 'website' : 'article';
			echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">';
			echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">';
			echo '<meta property="og:locale:alternate" content="' . esc_attr(get_locale()) . '">';
			echo '<meta property="og:site" content="' . esc_url($site_url) . '">';
			echo '<meta property="og:site_description" content="' . esc_attr($site_description) . '">';
			echo '<meta property="og:site_icon" content="' . esc_url($site_icon) . '">';

			if ( !empty( $site_twitter ) ) {
				echo '<meta property="og:site_twitter" content="' . esc_attr($site_twitter) . '">';
			}

			#endregion

			#region Twitter Open Graph tags
			echo '<!-- Twitter Meta Tags -->';
			echo '<meta name="twitter:card" content="summary_large_image">';
			echo '<meta name="twitter:image" content="' . esc_url($featured_image) . '">';
			echo '<meta name="twitter:image:alt" content="' . esc_attr($featured_image_alt) . '">';

			echo '<meta name="twitter:title" content="' . esc_attr($title) . '">';
			echo '<meta name="twitter:description" content="' . esc_attr($excerpt) . '">';

			echo '<meta name="twitter:domain" content="' . esc_attr($site_domain_name) . '">';
			echo '<meta name="twitter:url" content="' . esc_url(get_permalink()) . '">';

			if ( !empty( $site_twitter ) ) {
				echo '<meta name="twitter:site" content="' . esc_attr($site_twitter) . '">';
				echo '<meta name="twitter:creator" content="' . esc_attr($site_twitter) . '">';
			};
			#endregion

			#region Facebook Open Graph tags
			if ( !empty( $site_facebook_app_id ) ) {
				echo '<!-- Facebook Meta Tags -->';
				echo '<meta property="fb:app_id" content="' . esc_attr($site_facebook_app_id) . '">';
			}
			#endregion

			echo '<!-- Open Graph Meta Tags Powered With Love By SEO Engine 😽 -->';
		}
	}

	function admin_enqueue_scripts() {

		// Load the scripts
		$physical_file = MWSEO_PATH . '/app/index.js';
		$cache_buster = file_exists( $physical_file ) ? filemtime( $physical_file ) : MWSEO_VERSION;
		wp_register_script( 'seo_engine_seo-vendor', MWSEO_URL . 'app/vendor.js',
			['wp-element', 'wp-i18n'], $cache_buster
		);
		wp_register_script( 'seo_engine_seo', MWSEO_URL . 'app/index.js',
			['seo_engine_seo-vendor', 'wp-i18n'], $cache_buster
		);
		wp_set_script_translations( 'seo_engine_seo', 'seo-engine' );
		wp_enqueue_script('seo_engine_seo' );

		// Localize and options
		$js = [
			'api_url' => rest_url( 'seo-engine/v1' ),
			'rest_url' => rest_url(),
			'plugin_url' => MWSEO_URL,
			'prefix' => MWSEO_PREFIX,
			'domain' => MWSEO_DOMAIN,
			'is_pro' => class_exists( 'MeowPro_MWSEO_Core' ),
			'is_registered' => !!$this->is_registered(),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'fabicon_url' => get_site_icon_url(),
			'site_name' => get_bloginfo('name'),
			'options' => $this->core->sanitized_options(),
			'google_analytics' => $this->core->get_google_analytics_state(),
			'google_search_console' => $this->core->get_google_search_console_state(),
			'blog_name' => trim( get_bloginfo( 'name' ) ),
			'active_seo_plugins' => [
				'yoast'      => class_exists( 'WPSEO_Frontend' ),
				'all_in_one' => class_exists( 'All_in_One_SEO_Pack' ),
				'rank_math'  => class_exists( 'RankMath' ),
				'seopress'   => class_exists( 'SEOPress' ),
			]
		];

		wp_localize_script( 'seo_engine_seo', 'mwseo', $js );
	}

	function is_registered() {
		return apply_filters( MWSEO_PREFIX . '_meowapps_is_registered', false, MWSEO_PREFIX );
	}

	function app_menu() {
		add_submenu_page( 'meowapps-main-menu', 'SEO Engine', 'SEO Engine', 'manage_options',
			MWSEO_PREFIX . '_settings', array( $this, 'admin_settings' ) );

		// Standalone "Surgical SEO" posts manager — shown alongside (never replacing) the
		// native post lists, only when the Content SEO module is enabled, per enabled post type.
		if ( $this->core->get_option( 'content_seo', false ) ) {
			$post_types = (array) $this->core->get_option( 'select_post_types', ['post', 'page'] );

			if ( in_array( 'post', $post_types, true ) ) {
				$label = __( 'All Posts (SEO)', 'seo-engine' );
				add_submenu_page( 'edit.php', $label, $label, 'manage_options',
					'mwseo_surgical_post', array( $this, 'render_surgical_posts' ) );
			}
			if ( in_array( 'page', $post_types, true ) ) {
				$label = __( 'All Pages (SEO)', 'seo-engine' );
				add_submenu_page( 'edit.php?post_type=page', $label, $label, 'manage_options',
					'mwseo_surgical_page', array( $this, 'render_surgical_page' ) );
			}
		}
	}

	// Reorders the Posts/Pages submenus so "Surgical SEO" appears right after "All Posts".
	function reorder_surgical_submenu() {
		global $submenu;
		$targets = [
			'edit.php'               => 'mwseo_surgical_post',
			'edit.php?post_type=page' => 'mwseo_surgical_page',
		];
		foreach ( $targets as $parent => $slug ) {
			if ( empty( $submenu[ $parent ] ) ) continue;
			$found = null;
			foreach ( $submenu[ $parent ] as $i => $item ) {
				if ( isset( $item[2] ) && $item[2] === $slug ) { $found = $i; break; }
			}
			if ( $found === null ) continue;
			$entry = $submenu[ $parent ][ $found ];
			unset( $submenu[ $parent ][ $found ] );
			$items = array_values( $submenu[ $parent ] );
			// Insert just after the first entry ("All Posts" / "All Pages").
			array_splice( $items, 1, 0, [ $entry ] );
			$submenu[ $parent ] = $items;
		}
	}

	function admin_settings() {
		echo '<div id="' . MWSEO_PREFIX . '-admin-settings"></div>';
	}

	function render_surgical_posts() {
		echo '<div id="mwseo-surgical-app" data-post-type="post"></div>';
	}

	function render_surgical_page() {
		echo '<div id="mwseo-surgical-app" data-post-type="page"></div>';
	}

	
}

?>