<?php

// IndexNow: instantly notifies Bing, Yandex, Naver, Seznam (and every IndexNow-compatible
// engine) when content is published or updated, so new pages don't sit waiting for a crawl.
// Google does not consume IndexNow; this complements the sitemap, it does not replace it.
// Spec: https://www.indexnow.org/documentation
class Meow_MWSEO_Modules_IndexNow
{
	private $core = null;

	public function __construct( $core ) {
		$this->core = $core;
		$this->init();
	}

	public function init() {
		// Rides the Technical SEO module: one switch, fully automatic, on by default.
		if ( !$this->core->get_option( 'technical_seo', true ) || !$this->core->get_option( 'indexnow', true ) ) {
			return;
		}
		add_action( 'init', array( $this, 'serve_key_file' ), 0 );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
	}

	// The key is self-generated once; engines verify ownership by fetching /{key}.txt.
	public function get_key() {
		$key = $this->core->get_option( 'indexnow_key', '' );
		if ( empty( $key ) ) {
			$key = strtolower( wp_generate_password( 32, false, false ) );
			$this->core->update_option( 'indexnow_key', $key );
		}
		return $key;
	}

	// Serve the verification file at the site root without touching rewrite rules
	// (works on every host and on multi-domain installs, where each domain serves it).
	public function serve_key_file() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) return;
		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		if ( substr( $path, -4 ) !== '.txt' || strlen( $path ) !== 36 ) return;
		$key = $this->get_key();
		if ( $path !== $key . '.txt' ) return;
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo $key;
		exit;
	}

	public function on_transition( $new_status, $old_status, $post ) {
		// Fires for first publish AND for updates of already-published posts.
		if ( $new_status !== 'publish' || !$post || wp_is_post_revision( $post ) ) return;

		$public_types = get_post_types( array( 'public' => true ) );
		unset( $public_types['attachment'] );
		if ( !in_array( $post->post_type, $public_types, true ) ) return;

		// No-index posts are excluded on purpose; don't ask engines to fetch them.
		$excluded = array_map( 'intval', (array) $this->core->get_option( 'sitemap_excluded_post_ids', [] ) );
		if ( in_array( (int) $post->ID, $excluded, true ) ) return;

		$url = get_permalink( $post );
		if ( !$url ) return;

		// Throttle: a burst of saves on the same post pings once every 10 minutes.
		$lock = 'mwseo_indexnow_' . $post->ID;
		if ( get_transient( $lock ) ) return;
		set_transient( $lock, 1, 10 * MINUTE_IN_SECONDS );

		$this->ping( array( $url ) );
	}

	// Submit URLs to api.indexnow.org (shared endpoint: one ping reaches every engine).
	// Multi-domain installs (Polylang) group by host, since key location must match the host.
	public function ping( $urls ) {
		$urls = array_values( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) );
		if ( empty( $urls ) ) return false;

		$key = $this->get_key();
		$by_host = array();
		foreach ( $urls as $url ) {
			$host = parse_url( $url, PHP_URL_HOST );
			if ( !$host ) continue;
			$by_host[ $host ][] = $url;
		}

		foreach ( $by_host as $host => $host_urls ) {
			$scheme = parse_url( $host_urls[0], PHP_URL_SCHEME ) ?: 'https';
			$body = array(
				'host' => $host,
				'key' => $key,
				'keyLocation' => $scheme . '://' . $host . '/' . $key . '.txt',
				'urlList' => array_slice( $host_urls, 0, 10000 ),
			);
			// Non-blocking: publishing must never wait on a third-party endpoint.
			wp_remote_post( 'https://api.indexnow.org/indexnow', array(
				'blocking' => false,
				'timeout' => 3,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body' => wp_json_encode( $body ),
			) );
			$this->core->log( '📣 IndexNow pinged ' . count( $host_urls ) . ' URL(s) on ' . $host );
		}
		return true;
	}
}
