<?php

// TODO [2025]: Refactor to unified analytics provider interface
class Meow_MWSEO_Modules_MatomoAnalytics
{
	private $core = null;
	private $api_key = null;
	private $server_url = null;
	private $site_id = null;
	private $use_cache = false;
	private $disabled_tracking = false;

	const TRANSIENT_REPORT_PREFIX = 'mwseo_matomo_analytics_report_';

	// Matomo returns every page of a report unless capped, and the default row carries a fat
	// goals/segment payload: an uncapped 30-day page-day matrix measures in megabytes, which
	// these reports have to survive on 128M hosts. So every call trims columns and either
	// scopes to known paths or caps the row count.
	const MAX_ROWS_DAILY = 400;
	const MAX_ROWS_TOTALS = 1000;

	// Longest path list we'll turn into a filter regex before falling back to a capped report.
	const MAX_SCOPED_PATHS = 200;

	const COLUMNS_BASIC = 'label,nb_visits,nb_hits,url';
	const COLUMNS_FULL = 'label,nb_visits,nb_hits,url,bounce_rate,avg_time_on_page';

	public function __construct( $core )
	{
		$this->core = $core;
		$this->init();
		$this->tracking();
	}

	/**
	 * Initialize the module and load settings.
	 */
	public function init()
	{
		$this->api_key = $this->core->get_option( 'matomo_api_key', '' );
		$this->server_url = $this->core->get_option( 'matomo_server_url', '' );
		$this->site_id = $this->core->get_option( 'matomo_site_id', '' );
		$this->use_cache = $this->core->get_option( 'analytics_cache', false );

		// Accept both "https://analytics.example.com" and ".../index.php" as the server URL.
		$this->server_url = rtrim( $this->server_url, '/' );
		$this->server_url = preg_replace( '#/index\.php$#', '', $this->server_url );
	}

	/**
	 * Initialize tracking script injection.
	 */
	public function tracking() {

		if ( is_admin() ) { return; }

		$this->disabled_tracking = $this->core->get_option( 'matomo_analytics_tracking_disabled', false );

		if ( $this->disabled_tracking || empty( $this->site_id ) || empty( $this->server_url ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_tracking_scripts' ) );
	}

	public function wp_enqueue_tracking_scripts() {

		// Don't track logged-in users
		if ( is_user_logged_in() ) {
			$track_logged_users = $this->core->get_option( 'matomo_track_logged_users', false );
			if ( !$track_logged_users ) { return; }

			// Don't track editors and admins
			$is_power_user = current_user_can( 'editor' ) || current_user_can( 'administrator' );
			if ( $is_power_user ) {
				$track_power_users = $this->core->get_option( 'matomo_track_power_users', false );
				if ( !$track_power_users ) { return; }
			}
		}

		wp_register_script( 'mwseo-analytics-matomo', $this->server_url . '/matomo.js', array(), null, true );
		wp_enqueue_script( 'mwseo-analytics-matomo' );

		// matomo.js reads the _paq queue, so it has to exist before the script runs.
		$bootstrap = sprintf(
			"var _paq = window._paq = window._paq || [];\n" .
			"_paq.push(['trackPageView']);\n" .
			"_paq.push(['enableLinkTracking']);\n" .
			"_paq.push(['setTrackerUrl', %s]);\n" .
			"_paq.push(['setSiteId', %s]);",
			wp_json_encode( $this->server_url . '/matomo.php' ),
			wp_json_encode( (string) $this->site_id )
		);
		wp_add_inline_script( 'mwseo-analytics-matomo', $bootstrap, 'before' );
	}

	/**
	 * Check if Matomo Analytics is configured.
	 */
	public function is_configured() {
		return ! empty( $this->api_key ) && ! empty( $this->site_id ) && ! empty( $this->server_url );
	}

	/**
	 * Make a Reporting API request to Matomo.
	 */
	private function make_request( $method, $params = array() ) {
		if ( ! $this->is_configured() ) {
			throw new Exception( 'Matomo Analytics is not configured.' );
		}

		$params = array_merge( array(
			'module' => 'API',
			'format' => 'JSON',
			'method' => $method,
			'idSite' => $this->site_id,
		), $params );

		// Matomo 4+ rejects token_auth in the query string, it has to be POSTed.
		$params['token_auth'] = $this->api_key;

		$response = wp_remote_post( $this->server_url . '/index.php', array(
			'body' => $params,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Matomo API request failed: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			throw new Exception( 'Matomo API returned status code: ' . $code );
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Failed to parse Matomo API response.' );
		}

		// Matomo answers errors with HTTP 200 and a JSON body instead of a status code.
		if ( isset( $data['result'] ) && $data['result'] === 'error' ) {
			$message = isset( $data['message'] ) ? $data['message'] : 'Unknown error.';
			throw new Exception( 'Matomo API error: ' . $message );
		}

		return $data;
	}

	/**
	 * Get analytics data for a time series.
	 */
	public function get_data( $args = array() ) {
		$start_date = isset( $args['start_date'] ) && $args['start_date'] ? $args['start_date'] : date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = isset( $args['end_date'] ) && $args['end_date'] ? $args['end_date'] : date( 'Y-m-d' );
		$period = isset( $args['group_by'] ) ? $this->convert_group_by_to_period( $args['group_by'] ) : 'day';

		$cache_key = self::TRANSIENT_REPORT_PREFIX . md5( $start_date . $end_date . $period );

		if ( $this->use_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			// With a date range and a sub-range period, Matomo returns one entry per period,
			// keyed by the period's date.
			$data = $this->make_request( 'VisitsSummary.get', array(
				'period' => $period,
				'date' => $start_date . ',' . $end_date,
			) );

			$transformed = array();

			foreach ( (array) $data as $date => $row ) {
				if ( !is_array( $row ) ) { continue; }

				$transformed[] = array(
					'period' => $date,
					'visits' => isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0,
					'unique_visitors' => $this->unique_visitors( $row ),
					'unique_posts' => 0, // Not tracked by Matomo
					'bounce_rate' => $this->parse_percentage( isset( $row['bounce_rate'] ) ? $row['bounce_rate'] : 0 ),
					'visit_duration' => isset( $row['avg_time_on_site'] ) ? (int) $row['avg_time_on_site'] : 0,
				);
			}

			if ( $this->use_cache ) {
				set_transient( $cache_key, $transformed, 12 * HOUR_IN_SECONDS );
			}

			return $transformed;

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get analytics summary.
	 */
	public function get_summary( $start_date = null, $end_date = null ) {
		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		$cache_key = self::TRANSIENT_REPORT_PREFIX . 'summary_' . md5( $start_date . $end_date );

		if ( $this->use_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$data = $this->make_request( 'VisitsSummary.get', array(
				'period' => 'range',
				'date' => $start_date . ',' . $end_date,
			) );

			$summary = array(
				'total_visits' => isset( $data['nb_visits'] ) ? (int) $data['nb_visits'] : 0,
				'unique_visitors' => $this->unique_visitors( $data ),
				'unique_posts' => 0, // Matomo doesn't provide this
				'logged_in_visits' => 0, // Matomo doesn't track this
				'bounce_rate' => $this->parse_percentage( isset( $data['bounce_rate'] ) ? $data['bounce_rate'] : 0 ),
				'pageviews' => isset( $data['nb_actions'] ) ? (int) $data['nb_actions'] : 0,
				'views_per_visit' => isset( $data['nb_actions_per_visit'] ) ? (float) $data['nb_actions_per_visit'] : 0,
				'visit_duration' => isset( $data['avg_time_on_site'] ) ? (int) $data['avg_time_on_site'] : 0,
			);

			if ( $this->use_cache ) {
				set_transient( $cache_key, $summary, 12 * HOUR_IN_SECONDS );
			}

			return $summary;

		} catch ( Exception $e ) {
			return array(
				'total_visits' => 0,
				'unique_visitors' => 0,
				'unique_posts' => 0,
				'logged_in_visits' => 0,
				'bounce_rate' => 0,
				'pageviews' => 0,
				'views_per_visit' => 0,
				'visit_duration' => 0,
			);
		}
	}

	/**
	 * Get the stats of a single page.
	 */
	public function get_post_analytics( $page_path, $start_date = null, $end_date = null ) {
		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		try {
			// Both slash variants: Matomo stores the path as it was requested.
			$no_slash = rtrim( $page_path, '/' );
			if ( $no_slash === '' ) { $no_slash = '/'; }
			$variants = $no_slash === '/' ? array( '/' ) : array( $no_slash, $no_slash . '/' );

			$rows = $this->request_page_urls( 'range', $start_date . ',' . $end_date, array(
				'columns' => self::COLUMNS_FULL,
				'paths' => $variants,
			) );
			$wanted = $this->normalize_path( $page_path );

			foreach ( $rows as $row ) {
				if ( $this->normalize_path( $this->row_path( $row ) ) !== $wanted ) { continue; }

				return array(
					'visits' => isset( $row['nb_hits'] ) ? (int) $row['nb_hits'] : 0,
					'unique_visitors' => isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0,
					'pageviews' => isset( $row['nb_hits'] ) ? (int) $row['nb_hits'] : 0,
					'bounce_rate' => $this->parse_percentage( isset( $row['bounce_rate'] ) ? $row['bounce_rate'] : 0 ),
					'avg_time_on_page' => isset( $row['avg_time_on_page'] ) ? (int) $row['avg_time_on_page'] : 0,
					'page_path' => $page_path
				);
			}

			return array();
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Get top posts/pages.
	 */
	public function get_top_posts( $args = array() ) {
		$defaults = array( 'start_date' => null, 'end_date' => null, 'limit' => 10 );
		$args = wp_parse_args( $args, $defaults );

		$start_date = $args['start_date'] ? $args['start_date'] : date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = $args['end_date'] ? $args['end_date'] : date( 'Y-m-d' );
		$limit = (int) $args['limit'];

		$cache_key = self::TRANSIENT_REPORT_PREFIX . 'top_posts_' . md5( $start_date . $end_date . $limit );

		if ( $this->use_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$rows = $this->request_page_urls( 'range', $start_date . ',' . $end_date, array(
				'limit' => $limit,
				'columns' => self::COLUMNS_FULL,
			) );

			$top_posts = array();

			foreach ( $rows as $row ) {
				$page_path = $this->row_path( $row );
				if ( $page_path === '' ) { continue; }

				$post_id = url_to_postid( home_url( $page_path ) );

				$post_title = 'Untitled';
				$post_url = home_url( $page_path );
				$post_type = 'page';

				if ( $post_id > 0 ) {
					$post = get_post( $post_id );
					if ( $post ) {
						$post_title = $post->post_title;
						$post_url = get_permalink( $post_id );
						$post_type = $post->post_type;
					}
				}

				$top_posts[] = array(
					'post_id' => $post_id,
					'post_title' => $post_title,
					'post_url' => $post_url,
					'post_type' => $post_type,
					'path' => $page_path,
					'visits' => isset( $row['nb_hits'] ) ? (int) $row['nb_hits'] : 0,
					'unique_visitors' => isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0,
					'bounce_rate' => $this->parse_percentage( isset( $row['bounce_rate'] ) ? $row['bounce_rate'] : 0 ),
					'visit_duration' => isset( $row['avg_time_on_page'] ) ? (int) $row['avg_time_on_page'] : 0,
				);
			}

			if ( $this->use_cache ) {
				set_transient( $cache_key, $top_posts, 12 * HOUR_IN_SECONDS );
			}

			return $top_posts;

		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Per-day, per-page visitors, for the Content SEO sparklines.
	 * Returns [ [ 'date' => Y-m-d, 'host' => ..., 'path' => ..., 'visitors' => int ], ... ].
	 * Scoped to $paths when given: the unscoped page-day matrix is megabytes on a busy site.
	 */
	public function get_pages_daily( $start_date = null, $end_date = null, $paths = null ) {
		if ( ! $start_date ) { $start_date = date( 'Y-m-d', strtotime( '-30 days' ) ); }
		if ( ! $end_date ) { $end_date = date( 'Y-m-d' ); }

		try {
			// period=day over a range gives one bucket of rows per day, keyed by date.
			$data = $this->request_page_urls( 'day', $start_date . ',' . $end_date, array(
				'limit' => self::MAX_ROWS_DAILY,
				'columns' => self::COLUMNS_BASIC,
				'paths' => $paths,
			) );

			$out = array();

			foreach ( (array) $data as $date => $rows ) {
				if ( !is_array( $rows ) ) { continue; }
				foreach ( $rows as $row ) {
					if ( !is_array( $row ) ) { continue; }
					$path = $this->row_path( $row );
					if ( $path === '' ) { continue; }
					$out[] = array(
						'date' => $date,
						'host' => $this->row_host( $row ),
						'path' => $path,
						'visitors' => isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0,
					);
				}
			}

			return $out;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * Per-page visitor totals over the window (no date dimension, so one row per page).
	 * Returns [ [ 'host' => ..., 'path' => ..., 'visitors' => int ], ... ].
	 */
	public function get_pages_totals( $start_date = null, $end_date = null ) {
		if ( ! $start_date ) { $start_date = date( 'Y-m-d', strtotime( '-30 days' ) ); }
		if ( ! $end_date ) { $end_date = date( 'Y-m-d' ); }

		try {
			$rows = $this->request_page_urls( 'range', $start_date . ',' . $end_date, array(
				'limit' => self::MAX_ROWS_TOTALS,
				'columns' => self::COLUMNS_BASIC,
			) );

			$out = array();

			foreach ( $rows as $row ) {
				$path = $this->row_path( $row );
				if ( $path === '' ) { continue; }
				$out[] = array(
					'host' => $this->row_host( $row ),
					'path' => $path,
					'visitors' => isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0,
				);
			}

			return $out;
		} catch ( Exception $e ) {
			return array();
		}
	}

	/**
	 * One flat page-URL report. Flat mode returns every page as its own row instead of the
	 * folder tree Matomo uses by default. $args: limit, columns, paths.
	 */
	private function request_page_urls( $period, $date, $args = array() ) {
		$params = array(
			'period' => $period,
			'date' => $date,
			'flat' => 1,
			'filter_sort_column' => 'nb_visits',
			'filter_sort_order' => 'desc',
		);

		if ( !empty( $args['columns'] ) ) {
			$params['showColumns'] = $args['columns'];
		}

		// Scoping to the paths we actually care about is both lighter and more accurate than
		// capping, since a low-traffic page can fall outside the top rows on a given day.
		$regex = $this->build_label_filter( isset( $args['paths'] ) ? $args['paths'] : null );
		if ( $regex !== null ) {
			$params['filter_column'] = 'label';
			$params['filter_pattern'] = $regex;
			$params['filter_limit'] = -1;
		}
		else {
			$params['filter_limit'] = isset( $args['limit'] ) ? (int) $args['limit'] : self::MAX_ROWS_TOTALS;
		}

		$data = $this->make_request( 'Actions.getPageUrls', $params );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Anchored alternation over the given paths, matched against the flat row labels.
	 * Slashes are deliberately left unescaped: Matomo escapes them itself before building
	 * the regex, and pre-escaping them would produce a literal backslash instead.
	 */
	private function build_label_filter( $paths ) {
		if ( empty( $paths ) || !is_array( $paths ) ) { return null; }

		$paths = array_values( array_unique( array_filter( $paths ) ) );
		if ( empty( $paths ) || count( $paths ) > self::MAX_SCOPED_PATHS ) { return null; }

		$quoted = array();
		foreach ( $paths as $path ) {
			$quoted[] = preg_quote( $path );
		}

		return '^(?:' . implode( '|', $quoted ) . ')$';
	}

	/**
	 * The path of a page row. Matomo gives a full `url` on most rows, and the flat `label`
	 * (already a path) otherwise.
	 */
	private function row_path( $row ) {
		if ( !empty( $row['url'] ) ) {
			$path = parse_url( $row['url'], PHP_URL_PATH );
			if ( $path ) { return $path; }
		}
		if ( !empty( $row['label'] ) ) {
			return '/' . ltrim( $row['label'], '/' );
		}
		return '';
	}

	private function row_host( $row ) {
		if ( !empty( $row['url'] ) ) {
			$host = parse_url( $row['url'], PHP_URL_HOST );
			if ( $host ) { return $host; }
		}
		return '*';
	}

	private function normalize_path( $path ) {
		$path = (string) $path;
		$q = strpos( $path, '?' );
		if ( $q !== false ) { $path = substr( $path, 0, $q ); }
		return strtolower( '/' . trim( $path, '/' ) );
	}

	/**
	 * Matomo reports rates as strings like "45%".
	 */
	private function parse_percentage( $value ) {
		if ( is_string( $value ) ) {
			return (float) rtrim( trim( $value ), '%' );
		}
		return (float) $value;
	}

	/**
	 * Unique visitors are not processed for `range` periods unless the Matomo admin enabled
	 * it, so fall back to visits rather than reporting zero.
	 */
	private function unique_visitors( $row ) {
		if ( isset( $row['nb_uniq_visitors'] ) ) {
			return (int) $row['nb_uniq_visitors'];
		}
		return isset( $row['nb_visits'] ) ? (int) $row['nb_visits'] : 0;
	}

	/**
	 * Convert our group_by format to Matomo's period format.
	 */
	private function convert_group_by_to_period( $group_by ) {
		$map = array(
			'day' => 'day',
			'week' => 'week',
			'month' => 'month',
			'year' => 'year',
		);

		return isset( $map[$group_by] ) ? $map[$group_by] : 'day';
	}

	/**
	 * Clear all cached reports.
	 */
	public function clear_cache() {
		global $wpdb;

		$pattern = '_transient_' . self::TRANSIENT_REPORT_PREFIX . '%';
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$pattern
		) );
	}
}
