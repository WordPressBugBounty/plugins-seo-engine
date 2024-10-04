<?php
class Meow_MWSEO_Admin extends MeowCommon_Admin {

	public $core;

	public function __construct( $core ) {
		$this->core = $core;
		
		parent::__construct( SEOENGINE_PREFIX, SEOENGINE_ENTRY, SEOENGINE_DOMAIN, class_exists( 'MeowPro_MWSEO_Core' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'app_menu' ) );

			// Load the scripts only if they are needed by the current screen
			$page = isset( $_GET["page"] ) ? sanitize_text_field( $_GET["page"] ) : null;
			$post = isset( $_GET["post"] ) ? intval( $_GET["post"] ) : null;
			$post_type = isset( $_GET["post_type"] ) ? sanitize_text_field( $_GET["post_type"] ) : null;

			$is_seo_engine_screen = in_array( $page, [ SEOENGINE_PREFIX . '_settings', 'seo_engine_dashboard' ] );
			$is_meowapps_dashboard = $page === 'meowapps-main-menu';

			$is_wc_product = get_post_type( $post ) === 'product';
			$is_wc_new_product = $post_type === 'product';

			$is_wc_assistant_enabled = $this->core->get_option( 'seo_engine_woocommerce_assistant', false );

			if ( $is_meowapps_dashboard || $is_seo_engine_screen ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			}

			if ( $is_wc_assistant_enabled && ( $is_wc_product || $is_wc_new_product ) ) {
				error_log('is_wc_product && $is_wc_assistant_enabled');
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			}
		}

		add_action( 'wp_head', array( $this, 'seo_engine_headers' ) );
	}

	function seo_engine_headers( $headers ) {
		if( !$this->core->get_option( 'seo_engine_social_networks', false ) ) { return $headers;}

		// use open graph tags for social networks, we should use the featured image, title and excerpt
		if ( is_single() || is_page() ) {

			$featured_image = apply_filters( 'seo_engine_social_networks_featured_image', get_the_post_thumbnail_url(), get_the_ID() );
			$featured_image_alt = get_post_meta( get_post_thumbnail_id(), '_wp_attachment_image_alt', true );
			$excerpt = get_the_excerpt();
			$title = get_the_title();
			$site_name = get_bloginfo('name');
			$site_url = get_bloginfo('url');
			$site_domain_name = parse_url($site_url, PHP_URL_HOST);
			$site_description = get_bloginfo('description');
			$site_icon = get_site_icon_url();

			$site_twitter = $this->core->get_option('seo_engine_social_networks_twitter', null);
			if ( !empty( $site_twitter ) ) {
				$site_twitter = $site_twitter[0] === '@' ? $site_twitter : '@' . $site_twitter;
			}

			$site_facebook_app_id = $this->core->get_option('seo_engine_social_networks_facebook_app_id', null);
			
			#region General Open Graph tags
			
			echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">';
			echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">';
			echo '<meta property="og:title" content="' . esc_attr($title) . '">';
			echo '<meta property="og:description" content="' . esc_attr($excerpt) . '">';
			echo '<meta property="og:image" content="' . esc_url($featured_image) . '">';
			echo '<meta property="og:image:alt" content="' . esc_attr($featured_image_alt) . '">';
			echo '<meta property="og:type" content="article">';
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
		
		return $headers;
	}

	function admin_enqueue_scripts() {

		// Load the scripts
		$physical_file = SEOENGINE_PATH . '/app/index.js';
		$cache_buster = file_exists( $physical_file ) ? filemtime( $physical_file ) : SEOENGINE_VERSION;
		wp_register_script( 'seo_engine_seo-vendor', SEOENGINE_URL . 'app/vendor.js',
			['wp-element', 'wp-i18n'], $cache_buster
		);
		wp_register_script( 'seo_engine_seo', SEOENGINE_URL . 'app/index.js',
			['seo_engine_seo-vendor', 'wp-i18n'], $cache_buster
		);
		wp_set_script_translations( 'seo_engine_seo', 'seo-engine' );
		wp_enqueue_script('seo_engine_seo' );

		// Localize and options
		wp_localize_script( 'seo_engine_seo', 'seo_engine_seo', [
			'api_url' => rest_url( 'seo-engine/v1' ),
			'rest_url' => rest_url(),
			'plugin_url' => SEOENGINE_URL,
			'prefix' => SEOENGINE_PREFIX,
			'domain' => SEOENGINE_DOMAIN,
			'is_pro' => class_exists( 'MeowPro_MWSEO_Core' ),
			'is_registered' => !!$this->is_registered(),
			'rest_nonce' => wp_create_nonce( 'wp_rest' ),
			'fabicon_url' => get_site_icon_url(),
			'site_name' => get_bloginfo('name'),
			'options' => $this->core->sanitized_options(),
		] );
	}

	function is_registered() {
		return apply_filters( SEOENGINE_PREFIX . '_meowapps_is_registered', false, SEOENGINE_PREFIX );
	}

	function app_menu() {
		add_submenu_page( 'meowapps-main-menu', 'SEO Engine', 'SEO Engine', 'manage_options',
			SEOENGINE_PREFIX . '_settings', array( $this, 'admin_settings' ) );
	}

	function admin_settings() {
		echo '<div id="' . SEOENGINE_PREFIX . '-admin-settings"></div>';
	}

	
}

?>