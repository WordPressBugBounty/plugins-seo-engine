<?php

class Meow_MWSEO_Modules_GoogleAnalytics
{
	private $core = null;

	private $property_ids  = array();
	private $property_id   = null;
	private $client_secret = null;
	private $client_id     = null;

	private $use_cache = false;
	private $disabled_tracking = false;
	private $measurement_ids = array();
	private $main_measurement_id = null;
	private $other_measurement_ids = array();

	private $current_access_token = null;
	private $current_refresh_token = null;
	private $current_expires_at = null;

  	const TOKEN_URL = 'https://www.googleapis.com/oauth2/v4/token';
	const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
	const SCOPE_URL = 'https://www.googleapis.com/auth/analytics.readonly';

	private $base_url     = 'https://analyticsdata.googleapis.com/v1beta/';
	private $realtime_url = 'https://analyticsdata.googleapis.com/v1beta/';

	const TRANSIENT_TOKEN_INFO = 'mwseo_google_analytics_token_info';
	const TRANSIENT_REPORT_PREFIX = 'mwseo_google_analytics_report_';

	public function __construct( $core )
	{
		$this->core = $core;
		$this->init();

		$this->handle_authentication();
		$this->tracking();
	}

	#region Authentication

	public function get_redirect_url() {
		return admin_url( 'admin.php?page=mwseo_settings&nekoTab=settings' );
	}

	

  	/**
  	 * Initialize the module and load settings.
  	 */

	public function init()
	{
		$this->use_cache    = $this->core->get_option( 'google_analytics_cache', false );
		$this->property_ids = $this->core->get_option( 'google_analytics_property_ids', array() );
		$this->property_id  = $this->core->get_option( 'google_analytics_property_id' );

		if ( empty( $this->property_id ) && !empty( $this->property_ids ) ) {
			$this->property_id = $this->property_ids[0];
		}

		$this->client_secret = $this->core->get_option( 'google_analytics_client_secret', '' );
		$this->client_id     = $this->core->get_option( 'google_analytics_client_id', '' );

		if ( empty( $this->client_secret ) || empty( $this->client_id ) ) {
			$this->core->log( "⚠️ Google Analytics not configured. Please set your Client ID and Client Secret in the settings." );
			return;
		}

		$token_info = get_transient( self::TRANSIENT_TOKEN_INFO );

		if ( $token_info && isset( $token_info['access_token'] ) ) {
			$this->current_access_token = $token_info['access_token'];
		}
		if ( $token_info && isset( $token_info['refresh_token'] ) ) {
			$this->current_refresh_token = $token_info['refresh_token'];
		}
		if ( $token_info && isset( $token_info['expires_at'] ) ) {
			$this->current_expires_at = $token_info['expires_at'];
		}
  	
	}

	public function is_authenticated() {

		if ( empty( $this->client_secret ) || empty( $this->client_id ) ) {
			return false;
		}

		if ( empty( $this->property_id ) || ! in_array( $this->property_id, $this->property_ids ) ) {
			$this->core->log( "⚠️ Google Analytics property not set or invalid." );
			return false;
		}

		if ( ! $this->current_access_token || ! $this->current_refresh_token || ! $this->current_expires_at ) {
			return false;
		}

		if ( time() >= $this->current_expires_at ) {
			if ( ! $this->get_refresh_token() ) {
				return false;
			}
		}

		return true;
  	}

	public function unlink() {
		$this->core->log( "🔗 Unlinking Google Analytics." );

		delete_transient( self::TRANSIENT_TOKEN_INFO );

		$this->current_access_token = null;
		$this->current_refresh_token = null;
		$this->current_expires_at = null;

		return true;
  	}

  	/**
  	 * Handle the authentication process when the user is redirected back from Google.
  	 */

	public function handle_authentication() {

		if ( ! isset( $_GET['code'] ) ) {
			return false;
		}

		$code = sanitize_text_field( $_GET['code'] );

		if ( empty( $code ) ) {
			return false;
		}

		if ( $this->get_access_token( $code ) ) {
			$this->core->log( "✅ Successfully authenticated with Google Analytics." );

			return true;
		} else {
			$this->core->log( "⚠️ Failed to authenticate with Google Analytics." );
			return false;
		}
  	}

	public function get_auth_url() {

		$params = array( 
			'response_type' => 'code',
			'client_id' => $this->client_id,
			'redirect_uri' => $this->get_redirect_url(),
			'scope' => self::SCOPE_URL,
			'access_type' => 'offline',
			'prompt' => 'consent'
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
  	}

	private function get_access_token( $code ) {

		if( $this->is_authenticated() ) {
			$this->core->log( "✅ Already authenticated with Google Analytics." );
			return true;
		}

		$options = array( 
			'body' => array(
				'code' => $code,
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri' => $this->get_redirect_url(),
				'grant_type' => 'authorization_code'
			)
		);

		$result = wp_remote_post( self::TOKEN_URL, $options );
		$json = json_decode( $result['body'] );

		if ( isset( $json->error ) ) {
			$this->core->log( "⚠️ Error retrieving access token: " . $json->error );
			return false;
		}

		$expires_in    = isset( $json->expires_in )    ? $json->expires_in    : 3600;
		$access_token  = isset( $json->access_token )  ? $json->access_token  : null;
		$refresh_token = isset( $json->refresh_token ) ? $json->refresh_token : null;

		$token_info = array(
			'expires_in' => $expires_in,
			'expires_at' => time() + $expires_in,
			'access_token' => $access_token,
			'refresh_token' => $refresh_token
		);

		set_transient( self::TRANSIENT_TOKEN_INFO, $token_info );

		return true;
	}

	  public function get_refresh_token() {

		$options = array( 
			'body' => array(
				'client_id' => $this->client_id,
				'client_secret' => $this->client_secret,
				'refresh_token' => $this->current_refresh_token,
				'grant_type' => 'refresh_token'
			)
		);

		$result = wp_remote_post( self::TOKEN_URL, $options );

		if ( is_wp_error( $result ) ) {
			$this->core->log( "⚠️ Error refreshing access token: " . $result->get_error_message() );
			return false;
		}

		$json = json_decode( $result['body'] );
		if ( isset( $json->error ) ) {
			$this->core->log( "⚠️ Error refreshing access token: " . $json->error );
			return false;
		}
		
		$expires_in    = isset( $json->expires_in )    ? $json->expires_in    : 3600;
		$access_token  = isset( $json->access_token )  ? $json->access_token  : null;

		$token_info = array(
			'expires_in' => $expires_in,
			'expires_at' => time() + $expires_in,
			'access_token' => $access_token,
			'refresh_token' => $this->current_refresh_token
		);

		set_transient( self::TRANSIENT_TOKEN_INFO, $token_info );

		return true;
  	}  

	#endregion

	#region Tracking

	public function tracking() {

		if ( is_admin() ) { return; }

		$this->disabled_tracking = $this->core->get_option( 'google_analytics_tracking_disabled', false );
		$this->measurement_ids   = $this->core->get_option( 'google_analytics_tracking_ids', array() );

		if ( $this->disabled_tracking || empty( $this->measurement_ids ) ) {
			return;
		}

		$this->main_measurement_id   = $this->measurement_ids[0];
		$this->other_measurement_ids = array_slice( $this->measurement_ids, 1 );

		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_tracking_scripts' ) );
	}

	public function wp_enqueue_tracking_scripts() {

		// Don't track logged-in users
		$is_logged_in = is_user_logged_in();
		if ( $is_logged_in ) {
			$track_logged_users = $this->core->get_option( 'google_analytics_track_logged_users', false );
			if ( !$track_logged_users ) { return; }

			// Don't track editors and admins
			$is_power_user = current_user_can( 'editor' ) || current_user_can( 'administrator' );
			if ( $is_power_user ) {
				$track_power_users = $this->core->get_option( 'google_analytics_track_power_users', false );
				if ( !$track_power_users ) { return; }
			}
		}

		add_filter( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 2 );

		// Enqueue the Google Analytics script
		wp_register_script( 'mwseo-analytics-ga', 'https://www.googletagmanager.com/gtag/js?id=' . $this->main_measurement_id, array(), null, true );
		wp_enqueue_script( 'mwseo-analytics-ga' );

		// Add the inline script with the tracking code
		wp_add_inline_script( 'mwseo-analytics-ga', $this->build_js(), 'after' );
	}

	function build_js( )
	{
		$main_measurement_id   = $this->main_measurement_id;
		$other_measurement_ids = $this->other_measurement_ids;

		$extra_ids = json_encode( $other_measurement_ids );

		return "
		var extra_ids = {$extra_ids};
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());
		gtag('config', '{$main_measurement_id}');
		for (var i = 0; i < extra_ids.length; i++) {
			gtag('config', extra_ids[i]);
		}
		";
	}

	function script_loader_tag($tag, $handle)
	{
		if ($handle === 'mwseo-analytics-ga') {
		$tag = str_replace('<script src=', '<script async src=', $tag);
		return $tag;
		}
		return $tag;
	}

	#endregion

	#region Analytics


	public function get_analytics_data( $args = array() )
	{

		if ( !$this->is_authenticated() ) {
			return array();
		}

		$defaults = array(
			'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date' => date( 'Y-m-d' ),
			'group_by' => 'day',
			'limit' => 100
		);

		$args = wp_parse_args( $args, $defaults );

		// Check if there is cached version of the report
		if ( $this->use_cache ) {

			$prefix = self::TRANSIENT_REPORT_PREFIX . 'analytics_data_' . $this->property_id . '_';
			$transient_key =  $prefix . md5( serialize( $args ) );

			$cached_report = get_transient( $transient_key );
			if ( $cached_report !== false ) {
				$this->core->log( "✅ Using cached Google Analytics Data for args: " . json_encode( $args ) );
				return $cached_report;
			}

		}


		try {
			// Use actual Google Analytics Data API
			$report = $this->get_analytics_data_from_api( $args );

			if ( !empty( $report ) && $this->use_cache ) {
				// Cache the report
				set_transient( $transient_key, $report, 12 * HOUR_IN_SECONDS );
				$this->core->log( "✅ Cached Google Analytics report for args: " . json_encode( $args ) );
			} 

			return $report;

		} catch ( Exception $e ) {
			$this->core->log( "❌ Google Analytics API Error: " . $e->getMessage() );
			return array();
		}
	}

	public function get_analytics_summary( $start_date = null, $end_date = null )
	{
		if ( !$this->is_authenticated() ) {
			return array();
		}

		if ( !$start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}
		if ( !$end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		$args = array(
			'start_date' => $start_date,
			'end_date' => $end_date
		);

		// Check if there is cached version of the report
		if ( $this->use_cache ) {
			$prefix = self::TRANSIENT_REPORT_PREFIX . 'analytics_summary_' . $this->property_id . '_';
			$transient_key =  $prefix . md5( serialize( $args ) );
			
			$cached_report = get_transient( $transient_key );
			if ( $cached_report !== false ) {
				$this->core->log( "✅ Using cached Google Analytics Summary report for args: " . json_encode( $args ) );
				return $cached_report;
			}
		}

		try {
			// Use actual Google Analytics Data API
			$result = $this->get_analytics_summary_from_api( $start_date, $end_date );

			if ( !empty( $result ) && $this->use_cache ) {
				// Cache the report
				set_transient( $transient_key, $result, 12 * HOUR_IN_SECONDS );
				$this->core->log( "✅ Cached Google Analytics Summary report for args: " . json_encode( $args ) );
			}

			return $result;

		} catch ( Exception $e ) {
			$this->core->log( "❌ Google Analytics API Error: " . $e->getMessage() );
			return array();
		}
	}

	public function get_top_posts( $args = array() )
	{
		if ( !$this->is_authenticated() ) {
			return array();
		}

		$defaults = array(
			'start_date' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'end_date' => date( 'Y-m-d' ),

			// ! We override the limits in the API call. We should have setting later if needed.
		);

		$args = wp_parse_args( $args, $defaults );

		// Check if there is cached version of the report
		if ( $this->use_cache ) {
			$prefix = self::TRANSIENT_REPORT_PREFIX . 'top_posts_' . $this->property_id . '_';
			$transient_key =  $prefix . md5( serialize( $args ) );
			
			$cached_report = get_transient( $transient_key );
			if ( $cached_report !== false ) {
				$this->core->log( "✅ Using cached Google Top Posts report for args: " . json_encode( $args ) );
				return $cached_report;
			}
		}

		try {
			// Use actual Google Analytics Data API
			$result = $this->get_top_posts_from_api( $args );

			if ( !empty( $result ) && $this->use_cache ) {
				// Cache the report
				set_transient( $transient_key, $result, 12 * HOUR_IN_SECONDS );
				$this->core->log( "✅ Cached Google Top Posts report for args: " . json_encode( $args ) );
			}

			return $result;

		} catch ( Exception $e ) {
			$this->core->log( "❌ Google Analytics API Error: " . $e->getMessage() );
			return array();
		}
	}

	// Google Analytics Data API methods
	private function get_analytics_data_from_api( $args )
	{
		$date_ranges = array(
			array(
				'startDate' => $args['start_date'],
				'endDate' => $args['end_date']
			)
		);

		$dimensions = array(
			array( 'name' => 'date' ),
			array( 'name' => 'hostName' )
		);

		$metrics = array(
			array( 'name' => 'sessions' ),
			array( 'name' => 'totalUsers' ),
			array( 'name' => 'screenPageViews' ),
			array( 'name' => 'bounceRate' )
		);

		$request_data = array(
			'dateRanges' => $date_ranges,
			'dimensions' => $dimensions,
			'metrics' => $metrics,
			'orderBys' => array(
				array(
					'dimension' => array( 'dimensionName' => 'date' ),
					'desc' => false
				)
			)
		);

		$response = $this->make_google_analytics_request( 'runReport', $request_data );
		
		if ( ! $response ) {
			return array();
		}

		return $this->transform_analytics_data( $response, $args );
	}

	private function get_analytics_summary_from_api( $start_date, $end_date )
	{
		$date_ranges = array(
			array(
				'startDate' => $start_date,
				'endDate' => $end_date
			)
		);

		$dimensions = array(
			array( 'name' => 'hostName' )
		);

		$metrics = array(
			array( 'name' => 'sessions' ),
			array( 'name' => 'totalUsers' ),
			array( 'name' => 'screenPageViews' ),
			array( 'name' => 'bounceRate' ),
			array( 'name' => 'averageSessionDuration' ),
			array( 'name' => 'screenPageViewsPerSession' )
		);

		$request_data = array(
			'dateRanges' => $date_ranges,
			'dimensions' => $dimensions,
			'metrics' => $metrics
		);

		$response = $this->make_google_analytics_request( 'runReport', $request_data );
		
		if ( ! $response ) {
			return array();
		}

		return $this->transform_analytics_summary( $response );
	}

	private function get_top_posts_from_api( $args )
	{
		$date_ranges = array(
			array(
				'startDate' => $args['start_date'],
				'endDate' => $args['end_date']
			)
		);

		$dimensions = array(
			array( 'name' => 'pagePath' ),
			array( 'name' => 'pageTitle' ),
			array( 'name' => 'country' ),
			array( 'name' => 'hostName' )
		);

		$metrics = array(
			array( 'name' => 'sessions' ),
			array( 'name' => 'totalUsers' ),
			array( 'name' => 'screenPageViews' ),
			array( 'name' => 'averageSessionDuration' )
		);

		$request_data = array(
			'dateRanges' => $date_ranges,
			'dimensions' => $dimensions,
			'metrics' => $metrics,
			'orderBys' => array(
				array(
					'metric' => array( 'metricName' => 'sessions' ),
					'desc' => true
				)
			),

			// We should display 10 posts, but if we sort them by country, we might need more to have like 10 per country
			'limit' => 50 
			//'limit' => $args['limit']
		);

		$response = $this->make_google_analytics_request( 'runReport', $request_data );
		
		if ( ! $response ) {
			return array();
		}

		return $this->transform_top_posts_data( $response );
	}

	private function make_google_analytics_request( $endpoint, $data )
	{
		// Get access token
		$access_token = $this->current_access_token;
		if ( ! $access_token ) {
			$this->core->log( "❌ Failed to obtain access token" );
			return false;
		}

		$url = $this->base_url . 'properties/' . $this->property_id . ':' . $endpoint;

		$args = array(
			'body' => json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->core->log( "❌ Google Analytics API Request Error: " . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$this->core->log( "❌ Google Analytics API HTTP Error: " . $response_code . " - " . $response_body );
			return false;
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->core->log( "❌ Google Analytics API JSON Decode Error: " . json_last_error_msg() );
			return false;
		}

		return $decoded_response;
	}

	private function transform_analytics_data( $response, $args ) {
		$data = array();
		$daily_totals = array();

		if ( ! isset( $response['rows'] ) ) {
			return $data;
		}

		// First, gather data for individual hosts and simultaneously calculate daily totals.
		foreach ( $response['rows'] as $row ) {
			
			$date_raw = $row['dimensionValues'][0]['value'];
			$host = isset( $row['dimensionValues'][1]['value'] ) ? $row['dimensionValues'][1]['value'] : '';

			// The date is in YYYYMMDD format, convert it to YYYY-MM-DD
			$year  = substr( $date_raw, 0, 4 );
			$month = substr( $date_raw, 4, 2 );
			$day   = substr( $date_raw, 6, 2 );
			$date = $year . '-' . $month . '-' . $day;

			$sessions    = isset( $row['metricValues'][0]['value'] ) ? (int) $row['metricValues'][0]['value'] : 0;
			$users       = isset( $row['metricValues'][1]['value'] ) ? (int) $row['metricValues'][1]['value'] : 0;
			$pageviews   = isset( $row['metricValues'][2]['value'] ) ? (int) $row['metricValues'][2]['value'] : 0;
			$bounce_rate = isset( $row['metricValues'][3]['value'] ) ? (float) $row['metricValues'][3]['value'] * 100 : 0;

			// Add the specific host data
			$data[] = array(
				'date'            => $date,
				'visits'          => $sessions,
				'unique_visitors' => $users,
				'pageviews'       => $pageviews,
				'bounce_rate'     => round( $bounce_rate, 2 ),
				'host'            => $host
			);

			// Initialize daily total record if it doesn't exist
			if ( ! isset( $daily_totals[ $date ] ) ) {
				$daily_totals[ $date ] = array(
					'visits'          => 0,
					'unique_visitors' => 0,
					'pageviews'       => 0,
					'bounce_rate_sum' => 0,
					'count'           => 0,
				);
			}

			// Aggregate data for the 'all' host summary
			$daily_totals[ $date ]['visits']          += $sessions;
			$daily_totals[ $date ]['unique_visitors'] += $users;
			$daily_totals[ $date ]['pageviews']       += $pageviews;
			$daily_totals[ $date ]['bounce_rate_sum'] += $bounce_rate;
			$daily_totals[ $date ]['count']++;
		}

		// Now, create the 'all' host entries from the aggregated daily totals
		foreach ( $daily_totals as $date => $totals ) {
			$avg_bounce_rate = ( $totals['count'] > 0 ) ? $totals['bounce_rate_sum'] / $totals['count'] : 0;

			$data[] = array(
				'date'            => $date,
				'visits'          => $totals['visits'],
				'unique_visitors' => $totals['unique_visitors'],
				'pageviews'       => $totals['pageviews'],
				'bounce_rate'     => round( $avg_bounce_rate, 2 ),
				'host'            => 'all'
			);
		}
		
		$group_by = $args['group_by'];

		if ( $group_by === 'day' ) {
			// No grouping needed, data is already prepared
		} elseif ( $group_by === 'month' || $group_by === 'year' ) {
			$data = array_reduce( $data, function( $carry, $item ) use ( $group_by ) {
				$key = ( $group_by === 'month' ) ? date( 'Y-m', strtotime( $item['date'] ) ) : date( 'Y', strtotime( $item['date'] ) );
				
				// We need to group by both the time key and the host
				$group_key = $key . '_' . $item['host'];

				if ( ! isset( $carry[ $group_key ] ) ) {
					$carry[ $group_key ] = array(
						'date'            => $item['date'], // Keep one date for display
						'visits'          => 0,
						'unique_visitors' => 0,
						'pageviews'       => 0,
						'bounce_rate_sum' => 0,
						'count'           => 0,
						'host'            => $item['host'],
					);
				}

				// Sum the values for the period
				$carry[ $group_key ]['visits']          += $item['visits'];
				$carry[ $group_key ]['unique_visitors'] += $item['unique_visitors'];
				$carry[ $group_key ]['pageviews']       += $item['pageviews'];
				$carry[ $group_key ]['bounce_rate_sum'] += $item['bounce_rate'];
				$carry[ $group_key ]['count']++;

				return $carry;
			}, array() );

			// Calculate average bounce rate for each group and clean up the array
			$data = array_map(function( $item ) {
				$item['bounce_rate'] = ( $item['count'] > 0 ) ? round( $item['bounce_rate_sum'] / $item['count'], 2 ) : 0;
				unset( $item['bounce_rate_sum'], $item['count'] );
				return $item;
			}, $data);

			$data = array_values( $data );
		}

		return $data;
	}

	private function transform_analytics_summary( $response )
	{
		if ( ! isset( $response['rows'][0] ) ) {
			return array();
		}

		$rows_count = count( $response['rows'] );
		$data = array();
		$all = array(
			'total_visits'    => 0,
			'unique_visitors' => 0,
			'pageviews'       => 0,
			'bounce_rate'     => 0,
			'avg_session_duration' => 0,
			'pages_per_session'    => 0,

			'host' => 'all'
		);

		foreach ( $response['rows'] as $row ) {

			$host                 = isset( $row['dimensionValues'][0]['value'] ) ? $row['dimensionValues'][0]['value'] : '';

			$sessions             = isset( $row['metricValues'][0]['value'] )    ? (int) $row['metricValues'][0]['value'] : 0;
			$users                = isset( $row['metricValues'][1]['value'] )    ? (int) $row['metricValues'][1]['value'] : 0;
			$pageviews            = isset( $row['metricValues'][2]['value'] )    ? (int) $row['metricValues'][2]['value'] : 0;
			$bounce_rate          = isset( $row['metricValues'][3]['value'] )    ? (float) $row['metricValues'][3]['value'] * 100 : 0;
			$avg_session_duration = isset( $row['metricValues'][4]['value'] )    ? (float) $row['metricValues'][4]['value'] : 0;
			$pages_per_session    = isset( $row['metricValues'][5]['value'] )    ? (float) $row['metricValues'][5]['value'] : 0;
			
			$values = array(
				'total_visits' => $sessions,
				'unique_visitors' => $users,
				'pageviews' => $pageviews,
				'bounce_rate' => round( $bounce_rate, 2 ),
				'avg_session_duration' => round( $avg_session_duration ),
				'pages_per_session' => round( $pages_per_session, 2 ),
				'host' => $host
			);

			$data[] = $values;

			$all['total_visits'] += $sessions;
			$all['unique_visitors'] += $users;
			$all['pageviews'] += $pageviews;

			// We need to average the bounce rate and session duration
			$all['bounce_rate'] += $bounce_rate;
			$all['avg_session_duration'] += $avg_session_duration;
			$all['pages_per_session'] += $pages_per_session;

		}

		// Averae the cumulative values
		if ( $rows_count > 0 ) {
			$all['bounce_rate']          = round( $all['bounce_rate'] / $rows_count, 2 );
			$all['avg_session_duration'] = round( $all['avg_session_duration'] / $rows_count );
			$all['pages_per_session']    = round( $all['pages_per_session'] / $rows_count, 2 );
		}

		// We add the overall summary
		$data[] = $all;

		return $data;
	}

	private function transform_top_posts_data( $response )
	{
		global $wpdb;
		$data = array();

		if ( ! isset( $response['rows'] ) ) {
			return $data;
		}

		foreach ( $response['rows'] as $row ) {
			
			// Dimensions
			$page_path  = $row['dimensionValues'][0]['value'];
			$page_title = $row['dimensionValues'][1]['value'];
			$country    = isset( $row['dimensionValues'][2]['value'] ) ? $row['dimensionValues'][2]['value'] : '';
			$host 	    = isset( $row['dimensionValues'][3]['value'] ) ? $row['dimensionValues'][3]['value'] : '';

			// Metrics 
			$sessions   = isset( $row['metricValues'][0]['value'] ) ? (int) $row['metricValues'][0]['value'] : 0;
			$users      = isset( $row['metricValues'][1]['value'] ) ? (int) $row['metricValues'][1]['value'] : 0;
			$pageviews  = isset( $row['metricValues'][2]['value'] ) ? (int) $row['metricValues'][2]['value'] : 0;
			$avg_session_duration = isset( $row['metricValues'][3]['value'] ) ? (float) $row['metricValues'][3]['value'] : 0;

			// Try to match with WordPress posts
			$post_link = $host ? 'https://' . $host . $page_path : '';
			$post_id   = url_to_postid( $post_link ) ?: 0;
			$post_type = get_post( $post_id ) ? get_post_type( $post_id ) : 'page';

			$data[] = array(
				'ID'               => $post_id,
				'post_title'       => $page_title,
				'post_type'        => $post_type,
				'visits'           => $sessions,
				'unique_visitors'  => $users,
				'pageviews'        => $pageviews,
				'avg_time_on_page' => round( $avg_session_duration ),
				'page_path'        => $page_path,
				'post_link'        => $post_link ?? home_url( $page_path ),
				'country'          => $country,
				'host'             => $host
			);
		}

		return $data;
	}

	// Realtime Analytics method
	public function get_realtime_data()
	{
		if ( !$this->is_authenticated() ) {
			return array();
		}

		// Check if there is cached version of the report
		if ( $this->use_cache ) {
			$transient_key = self::TRANSIENT_REPORT_PREFIX . 'realtime_data' . $this->property_id;
			$cached_report = get_transient( $transient_key );
			if ( $cached_report !== false ) {
				$this->core->log( "✅ Using cached Google Realtime report" );
				return $cached_report;
			}
		}

		try {
			$metrics = array(
				array( 'name' => 'activeUsers' )
			);

			$request_data = array(
				'metrics' => $metrics
			);

			$response = $this->make_realtime_request( $request_data );
			
			if ( ! $response ) {
				return array();
			}

			$active_users = 0;
			if ( isset( $response['rows'][0]['metricValues'][0]['value'] ) ) {
				$active_users = (int) $response['rows'][0]['metricValues'][0]['value'];
			}

			$result = array(
				'active_users' => $active_users
			);

			// Cache
			if ( ! empty( $result ) && $this->use_cache ) {
				set_transient( $transient_key, $result, 5 * MINUTE_IN_SECONDS );
				$this->core->log( "✅ Cached Google Realtime report" );
			}

			return $result;

		} catch ( Exception $e ) {
			$this->core->log( "❌ Google Analytics Realtime API Error: " . $e->getMessage() );
			return array();
		}
	}

	private function make_realtime_request( $data )
	{
		// Get access token
		$access_token = $this->current_access_token;
		if ( ! $access_token ) {
			$this->core->log( "❌ Failed to obtain access token for realtime request" );
			return false;
		}

		$url = $this->realtime_url . 'properties/' . $this->property_id . ':runRealtimeReport';

		$args = array(
			'body' => json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 30
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->core->log( "❌ Google Analytics Realtime API Request Error: " . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$this->core->log( "❌ Google Analytics Realtime API HTTP Error: " . $response_code . " - " . $response_body );
			return false;
		}

		$decoded_response = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->core->log( "❌ Google Analytics Realtime API JSON Decode Error: " . json_last_error_msg() );
			return false;
		}

		return $decoded_response;
	}


	#endregion

}
