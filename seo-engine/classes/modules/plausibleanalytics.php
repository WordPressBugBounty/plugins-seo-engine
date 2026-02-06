<?php

// TODO [2025]: Refactor to unified analytics provider interface
class Meow_MWSEO_Modules_PlausibleAnalytics
{
	private $core = null;
	private $api_key = null;
	private $server_url = null;
	private $site_id = null;
	private $use_cache = false;

	const TRANSIENT_REPORT_PREFIX = 'mwseo_plausible_analytics_report_';

	public function __construct( $core )
	{
		$this->core = $core;
		$this->init();
	}

	/**
	 * Initialize the module and load settings.
	 */
	public function init()
	{
		$this->api_key = $this->core->get_option( 'plausible_api_key', '' );
		$this->server_url = $this->core->get_option( 'plausible_server_url', 'https://plausible.io' );
		$this->site_id = $this->core->get_option( 'plausible_site_id', '' );
		$this->use_cache = $this->core->get_option( 'plausible_analytics_cache', false );

		// Remove trailing slash from server URL
		$this->server_url = rtrim( $this->server_url, '/' );

		if ( empty( $this->api_key ) || empty( $this->site_id ) ) {
			return;
		}
	}

	/**
	 * Check if Plausible Analytics is configured.
	 */
	public function is_configured() {
		return ! empty( $this->api_key ) && ! empty( $this->site_id ) && ! empty( $this->server_url );
	}

	/**
	 * Make an API request to Plausible.
	 */
	private function make_request( $endpoint, $params = array() ) {
		if ( ! $this->is_configured() ) {
			throw new Exception( 'Plausible Analytics is not configured.' );
		}

		$url = $this->server_url . '/api/v1/' . ltrim( $endpoint, '/' );

		// Add site_id to params
		$params['site_id'] = $this->site_id;

		// Build query string
		$url .= '?' . http_build_query( $params );

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			throw new Exception( 'Plausible API request failed: ' . $error_message );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			throw new Exception( 'Plausible API returned status code: ' . $code );
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Failed to parse Plausible API response.' );
		}

		return $data;
	}

	/**
	 * Get analytics data for a time series.
	 */
	// TODO [2025]: Refactor to unified analytics provider interface
	public function get_data( $args = array() ) {
		$start_date = isset( $args['start_date'] ) ? $args['start_date'] : date( 'Y-m-d', strtotime( '-30 days' ) );
		$end_date = isset( $args['end_date'] ) ? $args['end_date'] : date( 'Y-m-d' );
		$period = isset( $args['group_by'] ) ? $this->convert_group_by_to_period( $args['group_by'] ) : 'day';

		$cache_key = self::TRANSIENT_REPORT_PREFIX . md5( $start_date . $end_date . $period );

		if ( $this->use_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$params = array(
				'period' => 'custom',
				'date' => $start_date . ',' . $end_date,
				'metrics' => 'visitors,pageviews,bounce_rate,visit_duration',
			);

			$data = $this->make_request( 'stats/timeseries', $params );

			// Transform Plausible format to our format
			$results = isset( $data['results'] ) ? $data['results'] : array();

			$transformed = $this->transform_timeseries_data( $results, $start_date, $end_date );

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
	// TODO [2025]: Refactor to unified analytics provider interface
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
			$params = array(
				'period' => 'custom',
				'date' => $start_date . ',' . $end_date,
				'metrics' => 'visitors,visits,pageviews,views_per_visit,bounce_rate,visit_duration',
			);

			$data = $this->make_request( 'stats/aggregate', $params );

			// Map Plausible data to expected format
			$summary = array(
				'total_visits' => isset( $data['results']['visits']['value'] ) ? $data['results']['visits']['value'] : 0,
				'unique_visitors' => isset( $data['results']['visitors']['value'] ) ? $data['results']['visitors']['value'] : 0,
				'unique_posts' => 0, // Plausible doesn't provide this
				'logged_in_visits' => 0, // Plausible doesn't track this
				'bounce_rate' => isset( $data['results']['bounce_rate']['value'] ) ? $data['results']['bounce_rate']['value'] : 0,
				// Additional Plausible-specific metrics
				'pageviews' => isset( $data['results']['pageviews']['value'] ) ? $data['results']['pageviews']['value'] : 0,
				'views_per_visit' => isset( $data['results']['views_per_visit']['value'] ) ? $data['results']['views_per_visit']['value'] : 0,
				'visit_duration' => isset( $data['results']['visit_duration']['value'] ) ? $data['results']['visit_duration']['value'] : 0,
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
	 * Get top posts/pages.
	 */
	// TODO [2025]: Refactor to unified analytics provider interface
	public function get_post_analytics( $page_path, $start_date = null, $end_date = null ) {
		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		try {
			$params = array(
				'period' => 'custom',
				'date' => $start_date . ',' . $end_date,
				'metrics' => 'visitors,pageviews,bounce_rate,visit_duration',
				'filters' => 'event:page==' . $page_path,
			);

			$data = $this->make_request( 'stats/aggregate', $params );

			if ( empty( $data['results'] ) ) {
				return array();
			}

			$results = $data['results'];
			return array(
				'visits' => isset( $results['pageviews']['value'] ) ? (int) $results['pageviews']['value'] : 0,
				'unique_visitors' => isset( $results['visitors']['value'] ) ? (int) $results['visitors']['value'] : 0,
				'pageviews' => isset( $results['pageviews']['value'] ) ? (int) $results['pageviews']['value'] : 0,
				'bounce_rate' => isset( $results['bounce_rate']['value'] ) ? (float) $results['bounce_rate']['value'] : 0,
				'avg_time_on_page' => isset( $results['visit_duration']['value'] ) ? (int) $results['visit_duration']['value'] : 0,
				'page_path' => $page_path
			);
		} catch ( Exception $e ) {
			return array();
		}
	}

	public function get_top_posts( $start_date = null, $end_date = null, $limit = 10 ) {
		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		$cache_key = self::TRANSIENT_REPORT_PREFIX . 'top_posts_' . md5( $start_date . $end_date . $limit );

		if ( $this->use_cache ) {
			$cached_data = get_transient( $cache_key );
			if ( $cached_data !== false ) {
				return $cached_data;
			}
		}

		try {
			$params = array(
				'period' => 'custom',
				'date' => $start_date . ',' . $end_date,
				'property' => 'event:page',
				'metrics' => 'visitors,pageviews,bounce_rate,visit_duration',
				'limit' => $limit,
			);

			$data = $this->make_request( 'stats/breakdown', $params );

			$top_posts = array();

			if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
				foreach ( $data['results'] as $result ) {
					$page_path = isset( $result['page'] ) ? $result['page'] : '';

					// Try to get post ID from path
					$post_id = url_to_postid( home_url( $page_path ) );

					// Get post data if post_id is valid
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
						'visits' => isset( $result['pageviews'] ) ? $result['pageviews'] : 0, // Map pageviews to visits
						'unique_visitors' => isset( $result['visitors'] ) ? $result['visitors'] : 0, // Map visitors to unique_visitors
						'bounce_rate' => isset( $result['bounce_rate'] ) ? $result['bounce_rate'] : 0,
						'visit_duration' => isset( $result['visit_duration'] ) ? $result['visit_duration'] : 0,
					);
				}
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
	 * Transform Plausible timeseries data to our format.
	 */
	private function transform_timeseries_data( $results, $start_date, $end_date ) {
		if ( empty( $results ) ) {
			return array();
		}

		$transformed = array();

		foreach ( $results as $result ) {
			$date = isset( $result['date'] ) ? $result['date'] : '';

			$transformed[] = array(
				'period' => $date, // Chart expects 'period' not 'date'
				'visits' => isset( $result['pageviews'] ) ? $result['pageviews'] : 0, // Map pageviews to visits
				'unique_visitors' => isset( $result['visitors'] ) ? $result['visitors'] : 0, // Map visitors to unique_visitors
				'unique_posts' => 0, // Not tracked by Plausible
				'bounce_rate' => isset( $result['bounce_rate'] ) ? $result['bounce_rate'] : 0,
				'visit_duration' => isset( $result['visit_duration'] ) ? $result['visit_duration'] : 0,
			);
		}

		return $transformed;
	}

	/**
	 * Convert our group_by format to Plausible's period format.
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
