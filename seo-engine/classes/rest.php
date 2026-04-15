<?php

class Meow_MWSEO_Rest
{
	private $core = null;
	private $rank = null;
	private $namespace = 'seo-engine/v1';
	
	public function __construct( $core, $admin ) {
		if ( !current_user_can( 'administrator' ) ) {
			return;
		}
		$this->core = $core;
		if ( class_exists( 'MeowPro_MWSEO_Ranks_Core' ) ) {
			$this->rank = new MeowPro_MWSEO_Ranks_Core( $this->core );
		}
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * STANDARDIZED API RESPONSE HELPERS
	 * Ensures consistent response structure and appropriate HTTP codes
	 */

	/**
	 * Return successful response
	 * @param mixed $data Response data
	 * @param string $message Optional success message
	 * @param int $status HTTP status code (default 200)
	 */
	private function success_response( $data = null, $message = '', $status = 200 ) {
		$response = [ 'success' => true ];
		if ( $data !== null ) {
			$response['data'] = $data;
		}
		if ( !empty( $message ) ) {
			$response['message'] = $message;
		}
		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Return error response
	 * @param string $message Error message
	 * @param string $code Error code
	 * @param int $status HTTP status code (default 400)
	 * @param mixed $data Additional error data
	 */
	private function error_response( $message, $code = 'error', $status = 400, $data = null ) {
		$response = [
			'success' => false,
			'message' => $message,
			'code' => $code
		];
		if ( $data !== null ) {
			$response['data'] = $data;
		}
		return new WP_REST_Response( $response, $status );
	}

	function rest_api_init() {
		try {
			#region REST LOGS
			register_rest_route( $this->namespace, '/get_logs', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_get_logs' )
			) );
			register_rest_route( $this->namespace, '/clear_logs', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_clear_logs' )
			) );
			#endregion

			#region REST MAINTENANCE
			register_rest_route( $this->namespace, '/reset_data', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_reset_data' )
			) );
			#endregion
			
			#region REST  Robots.txt
			register_rest_route( $this->namespace, '/get_robots_txt', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_get_robots_txt' )
			) );
			register_rest_route( $this->namespace, '/update_robots_txt', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_update_robots_txt' )
			) );
			register_rest_route( $this->namespace, '/ai_generate_robots_txt', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_ai_generate_robots_txt' )
			) );
			
			#endregion

			#region REST LLMs.txt
			register_rest_route( $this->namespace, '/get_llms_txt', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_get_llms_txt' )
			) );
			register_rest_route( $this->namespace, '/update_llms_txt', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_update_llms_txt' )
			) );
			register_rest_route( $this->namespace, '/ai_generate_llms_txt', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_ai_generate_llms_txt' )
			) );
			
			#endregion

			#region REST POSTS

			register_rest_route( $this->namespace, '/fetch_posts', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_features' ),
				'callback' => array( $this, 'rest_fetch_posts' ),
				'args' => array(
					'search' => array( 'required' => false ),
					'offset' => array( 'required' => false, 'default' => 0 ),
					'limit' => array( 'required' => false, 'default' => 10 ),
				)
			) );

			register_rest_route( $this->namespace, '/post_types', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_post_types' ),
			) );
			register_rest_route( $this->namespace, '/categories', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_categories' ),
			) );
			register_rest_route( $this->namespace, '/category_seo', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_category_seo' ),
				'args' => array(
					'category_id' => array( 'required' => true ),
				)
			) );
			register_rest_route( $this->namespace, '/category_seo', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_update_category_seo' ),
			) );
			register_rest_route( $this->namespace, '/ai_generate_category_seo', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_generate_category_seo' ),
			) );
			register_rest_route( $this->namespace, '/posts', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_posts' ),
			) );
			register_rest_route( $this->namespace, '/scored_posts', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_scored_posts' ),
			) );
			register_rest_route( $this->namespace, '/update_post', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_update_post' )
			) );
			register_rest_route( $this->namespace, '/ignore_seo_issue', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ignore_seo_issue' )
			) );
			register_rest_route( $this->namespace, '/reset_ignored_issues', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_reset_ignored_issues' )
			) );
			register_rest_route( $this->namespace, '/one_or_last_post', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_one_or_last_post' )
			) );

			register_rest_route( $this->namespace, '/get_ai_keywords', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_ai_keywords' )
			) );
			
			register_rest_route( $this->namespace, '/get_score_factors', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_score_factors' )
			) );

			register_rest_route( $this->namespace, '/get_post_statuses', array(
				'methods' => 'POST',
				'args' => array(
					'type' => array( 'required' => true ),
				),
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_post_statuses' )
			) );

			#endregion

			#region REST SETTINGS
			register_rest_route( $this->namespace, '/settings/update', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_settings_update' )
			) );
			register_rest_route( $this->namespace, '/settings/list', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_settings_list' ),
			) );
			register_rest_route( $this->namespace, '/settings/reset', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_settings_reset' ),
			) );

			
			register_rest_route( $this->namespace, '/update_skip_option', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_update_skip_option' )
			) );
			
			register_rest_route( $this->namespace, '/import_data', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_import_data' )
			) );

			register_rest_route( $this->namespace, '/clear_ai_cache', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_clear_ai_cache' )
			) );

			#endregion

			#region REST Google Ranking
			register_rest_route( $this->namespace, '/fetch_searches', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_fetch_searches' )
			) );
			register_rest_route( $this->namespace, '/save_search', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_save_search' )
			) );
			register_rest_route( $this->namespace, '/delete_search', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_delete_search' )
			) );

			#endregion

			#region REST Performance Insights
			register_rest_route( $this->namespace, '/get_insights', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_insights' )
			) );

			register_rest_route( $this->namespace, '/get_last_insights', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_last_insights' )
			) );

			#endregion

			#region REST WooCommerce

			register_rest_route( $this->namespace, '/generate_fields', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_generate_fields' )
			) );

			// SEO
			register_rest_route( $this->namespace, '/start_analysis', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_start_analysis' )
			) );
			register_rest_route( $this->namespace, '/analysis/baseline', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_analysis_baseline' )
			) );
			register_rest_route( $this->namespace, '/analysis/ai-step', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_analysis_ai_step' )
			) );
			// NEW UNIFIED ANALYSIS API
			register_rest_route( $this->namespace, '/analysis/init', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_analysis_init' )
			) );
			register_rest_route( $this->namespace, '/analysis/tech-step', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_analysis_tech_step' )
			) );
			register_rest_route( $this->namespace, '/get_all_ids', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_all_ids' )
			) );
			
			#endregion

			#region REST AI Engine
			register_rest_route( $this->namespace, '/ai_suggestion', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_suggest' )
			) );
			register_rest_route( $this->namespace, '/ai_web_scraping', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_web_scraping' )
			) );
			register_rest_route( $this->namespace, '/magic_fix_generate', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_generate' )
			) );
			register_rest_route( $this->namespace, '/magic_fix_apply', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_apply' )
			) );

			// Internal Links Magic Fix - Multi-step endpoints
			register_rest_route( $this->namespace, '/magic_fix_internal_links_step1', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_internal_links_step1' )
			) );
			register_rest_route( $this->namespace, '/magic_fix_internal_links_step2', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_internal_links_step2' )
			) );
			register_rest_route( $this->namespace, '/magic_fix_internal_links_step3', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_internal_links_step3' )
			) );
			register_rest_route( $this->namespace, '/magic_fix_internal_links_step4', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_magic_fix_internal_links_step4' )
			) );

			register_rest_route( $this->namespace, '/generate_daily_insight', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_generate_daily_insight' )
			) );

			register_rest_route( $this->namespace, '/get_daily_insight', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_daily_insight' )
			) );

			#region REST Languages
			register_rest_route( $this->namespace, '/get_languages', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_languages' )
			) );

			#endregion

			#region REST Analytics
			register_rest_route( $this->namespace, '/analytics/data', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_analytics_data' )
			) );
			register_rest_route( $this->namespace, '/analytics/summary', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_analytics_summary' )
			) );
			register_rest_route( $this->namespace, '/analytics/top_posts', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_top_posts' )
			) );
			register_rest_route( $this->namespace, '/analytics/ai_agents_summary', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_ai_agents_summary' )
			) );
			register_rest_route( $this->namespace, '/analytics/ai_agent_details', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_ai_agent_details' )
			) );

			register_rest_route( $this->namespace, '/analytics/ai_agents_by_post', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_ai_agents_by_post' )
			) );

			#endregion

			#region REST Google Analytics
			
			register_rest_route( $this->namespace, '/google-analytics/check_auth', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_check_google_analytics_authenticated' )
			) );

			register_rest_route( $this->namespace, '/google-analytics/get_auth', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_google_analytics_auth' )
			) );
			register_rest_route( $this->namespace, '/google-analytics/unlink', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_unlink_google_analytics' )
			) );
			register_rest_route( $this->namespace, '/google-analytics/data', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_google_analytics_data' )
			) );
			register_rest_route( $this->namespace, '/google-analytics/summary', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_google_analytics_summary' )
			) );
			register_rest_route( $this->namespace, '/google-analytics/top_posts', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_google_analytics_top_posts' )
			) );
			register_rest_route( $this->namespace, '/google-analytics/realtime', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_google_analytics_realtime' )
			) );

			#endregion

			#region REST Plausible Analytics
			register_rest_route( $this->namespace, '/plausible-analytics/data', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_plausible_analytics_data' )
			) );
			register_rest_route( $this->namespace, '/plausible-analytics/summary', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_plausible_analytics_summary' )
			) );
			register_rest_route( $this->namespace, '/plausible-analytics/top_posts', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_plausible_analytics_top_posts' )
			) );

			#endregion


			#region REST Sitemap	
			register_rest_route( $this->namespace, '/sitemap/generate', array(
				'methods' => 'GET',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_sitemap_generate' )
			) );

			#endregion
		
		}
		catch (Exception $e) {
			var_dump($e);
		}
	}

	#region General
	function get_param( $request, $key, $default = null ) {
		$params = $request->get_json_params();
		return ( array_key_exists( $key, $params ) ) ? $params[$key] : $default;
	}
	#endregion

	#region Logs
	function rest_get_logs() {
		$logs = $this->core->get_logs();
		return new WP_REST_Response( [ 'success' => true, 'data' => $logs ], 200 );
	}

	function rest_clear_logs() {
		$this->core->clear_logs();
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	#endregion

	#region Update Options

	function rest_settings_list() {

		// Actually refresh dynamic options (related to Wordpress' settings).
		$this->core->sanitized_options();


		return new WP_REST_Response( [
			'success' => true,
			'options' => $this->core->get_all_options()
		], 200 );
	}

	function rest_settings_update( $request ) {
		try {
			$params = $request->get_json_params();
			$value = $params['options'];
			$options = $this->core->update_options( $value );
			$success = !!$options;
			$message = __( $success ? 'OK' : "Could not update options.", 'seo-engine' );
			return new WP_REST_Response([ 'success' => $success, 'message' => $message, 'options' => $options ], 200 );
		}
		catch ( Exception $e ) {
			$message = apply_filters( 'mwai_ai_exception', $e->getMessage() );
			return new WP_REST_Response([ 'success' => false, 'message' => $message ], 500 );
		}
	}

	function rest_settings_reset() {
		try {
			$options = $this->core->reset_options();
			$success = !!$options;
			$message = __( $success ? 'OK' : "Could not reset options.", 'seo-engine' );
			return new WP_REST_Response([ 'success' => $success, 'message' => $message, 'options' => $options ], 200 );
		}
		catch ( Exception $e ) {
			$message = apply_filters( 'mwai_ai_exception', $e->getMessage() );
			return new WP_REST_Response([ 'success' => false, 'message' => $message ], 500 );
		}
	}

	#endregion

	#region Posts
	function rest_post_types() {
		$data = $this->core->make_post_type_list( $this->core->get_post_types() );
		return new WP_REST_Response( [
			'success' => true,
			'data' => $data,
		], 200 );
	}

	function rest_categories() {
		$data = $this->core->make_category_list( $this->core->get_categories() );
		return new WP_REST_Response( [
			'success' => true,
			'data' => $data,
		], 200 );
	}

	function rest_get_category_seo( $request ) {
		$category_id = $request->get_param( 'category_id' );
		if ( empty( $category_id ) ) {
			return $this->error_response( 'Category ID is required.', 'missing_category_id' );
		}
		
		$category = get_term( $category_id );
		if ( !$category || is_wp_error( $category ) ) {
			return $this->error_response( 'Category not found.', 'category_not_found', 404 );
		}
		
		$seo_title = get_term_meta( $category_id, '_mwseo_title', true );
		$seo_description = get_term_meta( $category_id, '_mwseo_description', true );
		
		return $this->success_response( [
			'category_id' => $category_id,
			'name' => $category->name,
			'slug' => $category->slug,
			'description' => $category->description,
			'count' => $category->count,
			'seo_title' => $seo_title ?: '',
			'seo_description' => $seo_description ?: '',
		] );
	}

	function rest_update_category_seo( $request ) {
		$params = $request->get_json_params();
		$category_ids = isset( $params['category_ids'] ) ? array_map( 'intval', $params['category_ids'] ) : [];
		$seo_title = isset( $params['seo_title'] ) ? sanitize_text_field( $params['seo_title'] ) : '';
		$seo_description = isset( $params['seo_description'] ) ? sanitize_textarea_field( $params['seo_description'] ) : '';
		
		if ( empty( $category_ids ) ) {
			return $this->error_response( 'Category ID is required.', 'missing_category_id' );
		}

		foreach ( $category_ids as $category_id ) {
			$category = get_term( $category_id );
			if ( !$category || is_wp_error( $category ) ) {
				return $this->error_response( 'Category not found.', 'category_not_found', 404 );
			}
			
			update_term_meta( $category_id, '_mwseo_title', $seo_title );
			update_term_meta( $category_id, '_mwseo_description', $seo_description );
		}
		
		return $this->success_response( [
			'category_ids' => $category_ids,
			'seo_title' => $seo_title,
			'seo_description' => $seo_description,
		], __( 'Category SEO settings saved successfully.', 'seo-engine' ) );
	}

	function rest_ai_generate_category_seo( $request ) {
		try {
			$params = $request->get_json_params();
			$individual = isset( $params['individual'] ) ? boolval( $params['individual'] ) : false;
			$category_ids = isset( $params['category_ids'] ) ? array_map( 'intval', $params['category_ids'] ) : [];

			if ( empty( $category_ids ) ) {
				return $this->error_response( 'Category ID is required.', 'missing_category_id' );
			}

			global $mwai;
			if ( is_null( $mwai ) || !isset( $mwai ) ) {
				return $this->error_response( 'AI Engine is required for this feature.', 'missing_ai_engine' );
			}

			$site_name = get_bloginfo( 'name' );
			$site_description = get_bloginfo( 'description' );

			// Helper function to generate SEO for a single category
			$generate_for_category = function( $category_id ) use ( $mwai, $site_name, $site_description ) {
				$category = get_term( $category_id );
				if ( !$category || is_wp_error( $category ) ) {
					return [ 'error' => 'Category not found', 'category_id' => $category_id ];
				}

				$category_name = $category->name;
				$category_description = $category->description;
				$category_slug = $category->slug;
				$category_count = $category->count;

				// Get some posts from this category for context
				$posts = get_posts( [
					'category' => $category_id,
					'numberposts' => 5,
					'post_status' => 'publish',
				] );
				$post_titles = array_map( function( $post ) {
					return $post->post_title;
				}, $posts );

				$instructions = "Generate SEO metadata for a WordPress category page. Provide a compelling SEO title (max 60 characters) and SEO description (max 160 characters) that will appear in search engine results.\n\n";
				$instructions .= "Website: {$site_name}\n";
				$instructions .= "Website description: {$site_description}\n\n";
				$instructions .= "Category name: {$category_name}\n";
				$instructions .= "Category slug: {$category_slug}\n";
				$instructions .= "Category description: " . ( $category_description ?: 'No description' ) . "\n";
				$instructions .= "Number of posts: {$category_count}\n";
				if ( !empty( $post_titles ) ) {
					$instructions .= "Sample post titles: " . implode( ', ', $post_titles ) . "\n";
				}
				$instructions .= "\nRespond ONLY with valid JSON in this exact format (no markdown, no code blocks):\n";
				$instructions .= '{"seo_title": "Your SEO title here", "seo_description": "Your SEO description here"}';

				$ai_response = $mwai->simpleTextQuery( $instructions, [ 'scope' => 'seo' ] );
				if ( empty( $ai_response ) ) {
					return [ 'error' => 'AI failed to generate content', 'category_id' => $category_id ];
				}

				// Parse JSON response
				$ai_response = trim( $ai_response );
				// Remove potential markdown code blocks
				$ai_response = preg_replace( '/^```json\s*/i', '', $ai_response );
				$ai_response = preg_replace( '/```$/', '', $ai_response );
				$ai_response = trim( $ai_response );

				$parsed = json_decode( $ai_response, true );
				if ( json_last_error() !== JSON_ERROR_NONE || !isset( $parsed['seo_title'] ) || !isset( $parsed['seo_description'] ) ) {
					return [ 'error' => 'Failed to parse AI response', 'category_id' => $category_id ];
				}

				return [
					'category_id' => $category_id,
					'category_name' => $category_name,
					'seo_title' => sanitize_text_field( $parsed['seo_title'] ),
					'seo_description' => sanitize_textarea_field( $parsed['seo_description'] ),
				];
			};

			// If individual mode with multiple categories, generate for each and return all propositions
			if ( $individual && count( $category_ids ) > 1 ) {
				$propositions = [];
				foreach ( $category_ids as $category_id ) {
					$result = $generate_for_category( $category_id );
					$propositions[] = $result;
				}
				return $this->success_response( [
					'individual' => true,
					'propositions' => $propositions,
				] );
			}

			// Single category or non-individual mode: generate for first category only
			$category_id = $category_ids[0];
			$result = $generate_for_category( $category_id );

			if ( isset( $result['error'] ) ) {
				return $this->error_response( $result['error'], 'generation_error' );
			}

			return $this->success_response( [
				'individual' => false,
				'seo_title' => $result['seo_title'],
				'seo_description' => $result['seo_description'],
			] );

		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage(), 'exception' );
		}
	}

	function rest_scored_posts( ) {
		$scored_posts = $this->core->get_all_posts_with_seo_score();

		return new WP_REST_Response( [
			'success' => true,
			'data' => $scored_posts,
		], 200);
	}

	function rest_posts($request) {
		$post_type = $this->core->get_option('default_post_type', 'post');

		// When 'any' is selected, use only the enabled post types from settings
		if ( $post_type === 'any' ) {
			$post_type = $this->core->get_option( 'select_post_types', ['post', 'page'] );
		}

		$params = $request->get_json_params();

		$sort = $params['sort'];
		$page = $params['page'];
		$limit = $params['limit'];
		$offset = ($page - 1) * $limit;

		$search = isset($params['search']) ? $params['search'] : null;
		$filter = isset($params['filterBy']) ? $params['filterBy'] : null;
		$show_all = $filter == 'all';
		$filter = $show_all ? null : $filter;

		// Get language filter
		$language = isset($params['language']) ? $params['language'] : null;
		if (empty($language) || $language === 'all') {
			$language = $this->core->get_option('default_language', 'all');
		}

		// Get the status filter
		$status = $this->core->get_option('default_post_status', 'publish');

		$total_counts = [
			'pending' => 0,
			'issue' => 0,
			'skip' => 0,
			'ok' => 0,
			'all' => 0,
		];

		$args = [
			'post_type' => $post_type,
			'posts_per_page' => -1,
			'nopaging' => true,
			'post_status' => $status,
		];

		// Add language filter if Polylang is active and a specific language is selected
		if (function_exists('pll_get_post_language') && $language !== 'all') {
			$args['tax_query'] = [
				[
					'taxonomy' => 'language',
					'field'    => 'slug',
					'terms'    => $language,
				],
			];
		}

		if ($search) {
			// Search in post title/content AND also in meta fields
			$args['s'] = $search;
			$args['_kiss_meta_search'] = $search; // Custom flag for our filter
			
			// Add filter to extend search to meta fields
			add_filter('posts_where', function($where, $wp_query) use ($search) {
				global $wpdb;
				if ($wp_query->get('_kiss_meta_search')) {
					$search_term = '%' . $wpdb->esc_like($search) . '%';
					$where = preg_replace(
						"/\(\s*{$wpdb->posts}.post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
						"({$wpdb->posts}.post_title LIKE $1 OR {$wpdb->posts}.ID IN (
							SELECT post_id FROM {$wpdb->postmeta} 
							WHERE (meta_key = '_kiss_seo_excerpt' OR meta_key = '_kiss_seo_title') 
							AND meta_value LIKE '" . esc_sql($search_term) . "'
						))",
						$where
					);
				}
				return $where;
			}, 10, 2);
		}

		$query = new WP_Query($args);
		$posts = $query->posts;

		// PERFORMANCE FIX: Prime meta cache to avoid N+1 queries
		// Extract post IDs for cache priming
		$post_ids = wp_list_pluck($posts, 'ID');
		if (!empty($post_ids)) {
			// Prime post meta cache (all meta for these posts loaded in 1-2 queries)
			update_meta_cache('post', $post_ids);
			// Prime thumbnail cache
			update_postmeta_cache($post_ids);
		}

		$excluded_posts = $this->core->get_option( 'sitemap_excluded_post_ids', [] );
		$excluded_posts = array_map( 'intval', $excluded_posts );

		$data = [];
		foreach ($posts as $post) {
			// Migrate old meta keys to new ones (runs once per post)
			$this->core->migrate_post_meta_keys($post->ID);

			// Check if post is marked as skip
			$skip_status = get_post_meta($post->ID, '_mwseo_status', true);
			$is_skip = $skip_status === 'skip';

			// Get score data
			$score_data = get_post_meta($post->ID, '_mwseo_analysis', true);
			$has_score = !empty($score_data);

			// Determine status
			if ($is_skip) {
				$status = 'skip';
			} else if ($has_score && isset($score_data['overall'])) {
				$score = $score_data['overall'];
				$status = $score <= 50 ? 'issue' : 'ok';
			} else {
				$status = 'pending';
			}

			// Update counts
			$total_counts[$status]++;
			if ($status !== 'skip') {
				$total_counts['all']++;
			}

			// Apply filter (All excludes skip)
			if ($show_all && $status === 'skip') {
				continue;
			}
			if ($filter && $status !== $filter) {
				continue;
			}

			$ignored_tests = get_post_meta($post->ID, '_mwseo_ignored_tests', true);
			$magic_fixes_applied = get_post_meta($post->ID, '_mwseo_issues_fixed', true);
			// Get AI agents data for this post
			$ai_agents_data = $this->core->get_ai_agents_by_post($post->ID, 30);

			// TODO: meta_key_seo_title and meta_key_seo_excerpt should migrate to _mwseo_title and _mwseo_excerpt
			$data[] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'excerpt' => $post->post_excerpt,
				'slug' => $post->post_name,
				'permalink' => get_permalink($post->ID),
				'status' => $this->core->get_seo_engine_post_meta($post),
				'publish_date' => $post->post_date,
				'featured_image' => get_the_post_thumbnail_url($post->ID, 'medium'),
				'seo_title' => get_post_meta($post->ID, $this->core->meta_key_seo_title, true),
				'seo_excerpt' => get_post_meta($post->ID, $this->core->meta_key_seo_excerpt, true),
				'rendered_title' => $this->core->build_title($post),
				'rendered_excerpt' => $this->core->build_excerpt($post),
				'post_type' => $post->post_type,
				'language' => function_exists('pll_get_post_language') ? pll_get_post_language($post->ID, 'slug') : null,
				'score' => $has_score ? $score_data : null,
				'ignored_tests' => is_array($ignored_tests) ? $ignored_tests : [],
				'fixed' => is_array($magic_fixes_applied) ? $magic_fixes_applied : [],
				'ai_agents' => $ai_agents_data,
				'no_index' => in_array($post->ID, $excluded_posts),
			];
		}

		wp_reset_postdata();

		// Sort data based on sort parameters
		if (isset($sort['accessor']) && isset($sort['by'])) {
			$accessor = $sort['accessor'];
			$order = $sort['by'];

			usort($data, function($a, $b) use ($accessor, $order) {
				$value_a = null;
				$value_b = null;

				// Get values based on accessor
				if ($accessor === 'score') {
					$has_score_a = isset($a['score']['overall']);
					$has_score_b = isset($b['score']['overall']);

					// Posts without scores always go to the end
					if (!$has_score_a && !$has_score_b) return 0;
					if (!$has_score_a) return 1;
					if (!$has_score_b) return -1;

					$value_a = $a['score']['overall'];
					$value_b = $b['score']['overall'];
				} else if ($accessor === 'id') {
					$value_a = $a['id'];
					$value_b = $b['id'];
				} else if ($accessor === 'title') {
					$value_a = strtolower($a['title']);
					$value_b = strtolower($b['title']);
				}

				// Compare values
				if ($value_a === $value_b) {
					return 0;
				}

				if ($order === 'asc') {
					return ($value_a < $value_b) ? -1 : 1;
				} else {
					return ($value_a > $value_b) ? -1 : 1;
				}
			});
		}

		$paginated_data = array_slice($data, $offset, $limit);

		return new WP_REST_Response([
			'success' => true,
			'posts' => $paginated_data,
			'total' => $total_counts,
		], 200);
	}

	function rest_get_all_ids() {
		$post_type = $this->core->get_option( 'default_post_type', 'post' );
		$language  = $this->core->get_option( 'default_language', 'all' );

		// When 'any' is selected, use only the enabled post types from settings
		if ( $post_type === 'any' ) {
			$post_type = $this->core->get_option( 'select_post_types', ['post', 'page'] );
		}

		$args = [
			'post_type' => $post_type,
			'posts_per_page' => -1, // Get all posts
			'fields' => 'ids',
		];
		
		// Add language filter if Polylang is active and a specific language is selected
		if (function_exists('pll_get_post_language') && $language !== 'all') {
			// Set the language taxonomy query for Polylang
			$args['tax_query'] = [
				[
					'taxonomy' => 'language',
					'field'    => 'slug',
					'terms'    => $language,
				],
			];
		}
	
		$query = new WP_Query($args);
		$posts = $query->posts; // Get all posts
	
		wp_reset_postdata();
	
		return new WP_REST_Response([
			'success' => true,
			'ids' => $posts,
		], 200);
	}

	function rest_one_or_last_post( $request ) {

		$post_id = $this->get_param( $request, 'id', $this->core->get_option( 'preview_post_id', null ) );
		$this->core->update_option( 'preview_post_id', $post_id );

		$has_featured_image = $this->get_param( $request, 'has_featured_image', false );

		$post = get_post( $post_id );
		
		if ( !$post ) {
			
			$post_search = [
				'post_type' => 'post',
				'posts_per_page' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
			];

			if ( $has_featured_image ) {
				$post_search['meta_query'] = [
					[
						'key' => '_thumbnail_id',
					],
				];
			}

			$post = get_posts( $post_search )[0];

			if ( !$post && $has_featured_image ) {
				unset( $post_search['meta_query'] );
				$post = get_posts( $post_search )[0];
			}

		}

		$featured_image = get_the_post_thumbnail_url( $post->ID, 'full' );
		$featured_image = $featured_image ? $featured_image : "https://placehold.co/1200x630?text=No+Featured+Image";
		$featured_image = apply_filters( 'mwseo_sns_featured_image', $featured_image, $post->ID );

		

		return new WP_REST_Response( [
			'success' => true,
			'data' => [

				'title' => $post->post_title,
				'excerpt' => $post->post_excerpt,
				'featured' => $featured_image,
				'domain' => preg_replace( '/^https?:\/\/(www\.)?/', '', get_site_url() ),

			],
		], 200 );
	}

	function rest_start_analysis( $request ) {
		$params = $request->get_json_params();
		$post_ids = $params['ids'] ?? [$params['id']];
		$analysis_type = $params['type'] ?? 'full'; // 'quick' or 'full'

		$results = [];
		$updated_posts = [];
		$success = true;
		$message = 'OK';

		foreach ($post_ids as $post_id) {
			$post = get_post( $post_id );
			if ( !$post ) {
				$success = false;
				$message = 'Post not found for ID: ' . $post_id;
				break;
			}

			$score = $this->core->calculate_seo_score( $post, $analysis_type );
			$results[$post_id] = $score;

			// Build the complete post object (same structure as rest_posts)
			$score_data = get_post_meta($post_id, '_mwseo_analysis', true);
			$has_score = !empty($score_data);
			$skip_status = get_post_meta($post_id, '_mwseo_status', true);
			$ignored_tests = get_post_meta($post_id, '_mwseo_ignored_tests', true);
			$magic_fixes_applied = get_post_meta($post_id, '_mwseo_issues_fixed', true);
			$ai_agents_data = $this->core->get_ai_agents_by_post($post_id, 30);

			$updated_posts[$post_id] = [
				'id' => $post_id,
				'title' => $post->post_title,
				'excerpt' => $post->post_excerpt,
				'slug' => $post->post_name,
				'permalink' => get_permalink($post_id),
				'status' => $this->core->get_seo_engine_post_meta($post),
				'publish_date' => $post->post_date,
				'featured_image' => get_the_post_thumbnail_url($post_id, 'medium'),
				'seo_title' => get_post_meta($post_id, $this->core->meta_key_seo_title, true),
				'seo_excerpt' => get_post_meta($post_id, $this->core->meta_key_seo_excerpt, true),
				'rendered_title' => $this->core->build_title($post),
				'rendered_excerpt' => $this->core->build_excerpt($post),
				'post_type' => $post->post_type,
				'language' => function_exists('pll_get_post_language') ? pll_get_post_language($post_id, 'slug') : null,
				'score' => $has_score ? $score_data : null,
				'ignored_tests' => is_array($ignored_tests) ? $ignored_tests : [],
				'fixed' => is_array($magic_fixes_applied) ? $magic_fixes_applied : [],
				'ai_agents' => $ai_agents_data,
			];
		}

		return new WP_REST_Response( [
			'success' => $success,
			'message' => $message,
			'data' => [
				'results' => $results,
				'updated_posts' => $updated_posts,
			]
		], $success ? 200 : 404 );
	}

	function rest_update_post( $request ) {
		$params = $request->get_json_params();
		// Validation
		if ( !isset( $params['id'] ) || !isset( $params['title'] ) || !isset( $params['excerpt'] ) || !isset( $params['slug'] )) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Missing some parameters. Required: id, title, excerpt and slug.',
			], 200 );
		}

		// Update the post.
		$post_id = $params['id'];
		$post = [
			'ID' => $post_id,
			'post_title' => $params['title'],
			'post_excerpt' => $params['excerpt'],
			'post_name' => $params['slug'],
		];
		$result = wp_update_post( $post );
		if ( $result === 0 ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Failed to update the post.',
			], 200 );
		}

		// Update the AI keywords.
		$ai_keywords = $params['ai_keywords'] == '' ? null : explode(' ', $params['ai_keywords'] );
		$this->update_or_delete_post_meta( $post_id, '_mwseo_keywords', $ai_keywords );


		// Update the post metadata.
		// TODO: meta_key_seo_title and meta_key_seo_excerpt should migrate to _mwseo_title and _mwseo_excerpt
		$seo_title = $params['seo_title'] ?? null;
		$seo_excerpt = $params['seo_excerpt'] ?? null;
		if ( $seo_title !== null ) {
			$this->update_or_delete_post_meta( $post_id, $this->core->meta_key_seo_title, $seo_title );
		}
		if ( $seo_excerpt !== null ) {
			$this->update_or_delete_post_meta( $post_id, $this->core->meta_key_seo_excerpt, $seo_excerpt );
		}

		// Update the no index
		$no_index = isset( $params['no_index'] ) ? boolVal( $params['no_index'] ) : null;
		if ( $no_index !== null ) {

			$excluded_posts = $this->core->get_option( 'sitemap_excluded_post_ids', [] );
			$excluded_posts = array_map( 'intval', $excluded_posts );

			if ( $no_index ) {
				if ( !in_array( $post_id, $excluded_posts ) ) {
					$excluded_posts[] = $post_id;
				}
			} else {
				if ( in_array( $post_id, $excluded_posts ) ) {
					$excluded_posts = array_diff( $excluded_posts, [ $post_id ] );
				}
			}
			
			$this->core->update_option( 'sitemap_excluded_post_ids', $excluded_posts );
		}


		return new WP_REST_Response( [
			'success' => true,
		], 200 );
	}

	function rest_ignore_seo_issue( $request ) {
		$params = $request->get_json_params();

		// Validation
		if ( !isset( $params['id'] ) || !isset( $params['test'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Missing parameters. Required: id, test.',
			], 400 );
		}

		$post_id = $params['id'];
		$test_name = $params['test'];

		// Get current ignored tests
		$ignored_tests = get_post_meta( $post_id, '_mwseo_ignored_tests', true );
		if ( !is_array( $ignored_tests ) ) {
			$ignored_tests = [];
		}

		// Add test to ignored list if not already there
		if ( !in_array( $test_name, $ignored_tests ) ) {
			$ignored_tests[] = $test_name;
			update_post_meta( $post_id, '_mwseo_ignored_tests', $ignored_tests );
		}

		// Recalculate score with ignored tests
		$post = get_post( $post_id );
		if ( $post ) {
			// Get current analysis type from last run (default to 'quick' to preserve AI data)
			$score = $this->core->calculate_seo_score( $post, 'quick' );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => 'Issue ignored successfully.',
			'data' => [
				'ignored_tests' => $ignored_tests,
				'new_score' => $score ?? null
			]
		], 200 );
	}

	function rest_reset_ignored_issues( $request ) {
		$params = $request->get_json_params();

		// Validation
		if ( !isset( $params['id'] ) ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Missing parameter. Required: id.',
			], 400 );
		}

		$post_id = $params['id'];

		// Delete ignored tests meta
		delete_post_meta( $post_id, '_mwseo_ignored_tests' );

		// Recalculate score without ignored tests
		$post = get_post( $post_id );
		if ( $post ) {
			$score = $this->core->calculate_seo_score( $post, 'quick' );
		}

		return new WP_REST_Response( [
			'success' => true,
			'message' => 'Ignored issues reset successfully.',
			'data' => [
				'new_score' => $score ?? null
			]
		], 200 );
	}

	function rest_update_skip_option( $request ) {
		$params = $request->get_json_params();
		// Validation
		if ( !isset( $params['id'] ) || !isset( $params['skip'] )) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Missing some parameters. Required: id and skip.',
			], 200 );
		}

		$post_id = $params['id'];
		$skip = boolVal( $params['skip'] );

		$this->update_or_delete_post_meta( $post_id, '_mwseo_status', $skip ? 'skip' : 'pending' );
		$this->update_or_delete_post_meta( $post_id, '_mwseo_message', $skip ? 'This post has been skipped. No SEO score.' : null );
		$this->update_or_delete_post_meta( $post_id, '_mwseo_score', null );

		return new WP_REST_Response( [
			'success' => true,
		], 200 );
	}

	function update_or_delete_post_meta( $post_id, $meta_key, $meta_value ) {
		//add post meta if non-existent
		if ( !get_post_meta( $post_id, $meta_key ) ) {
			add_post_meta( $post_id, $meta_key, $meta_value );
			return;
		}

		if ( $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
		else {
			delete_post_meta( $post_id, $meta_key, $meta_value );
		}
	}

	function rest_fetch_posts( $request ) {
		try {
			$params = $request->get_json_params();
			$search = isset($params['search']) ? $params['search'] : '';
			$offset = isset($params['offset']) ? intval($params['offset']) : 0;
			$limit = isset($params['limit']) ? intval($params['limit']) : 10;

			global $wpdb;
			$searchPlaceholder = $search ? '%' . $search . '%' : '';
			$where_search_clause = $search ? $wpdb->prepare(
				"AND ( p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_name LIKE %s ) ",
				$searchPlaceholder,
				$searchPlaceholder,
				$searchPlaceholder
			) : '';

			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, p.post_date, p.post_status, u.display_name as author
					FROM $wpdb->posts p 
					LEFT JOIN $wpdb->users u ON p.post_author = u.ID
					WHERE p.post_type = 'post' 
					AND p.post_status IN ('publish', 'draft', 'private')
					$where_search_clause 
					ORDER BY p.post_date DESC 
					LIMIT %d, %d", 
					$offset, 
					$limit
				), 
				OBJECT
			);

			$posts_count = (int)$wpdb->get_var(
				"SELECT COUNT(*)
				FROM $wpdb->posts p 
				WHERE p.post_type = 'post' 
				AND p.post_status IN ('publish', 'draft', 'private')
				$where_search_clause"
			);

			$data = array_map(function($post) {
				return [
					'id' => $post->ID,
					'title' => $post->post_title,
					'date' => $post->post_date,
					'author' => $post->author,
					'status' => $post->post_status
				];
			}, $posts);

			return new WP_REST_Response([
				'success' => true,
				'data' => $data,
				'total' => $posts_count
			], 200);
		} catch (Exception $e) {
			return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
		}
	}
	#endregion

	#region Performance Insights

	function rest_get_last_insights( ) {
		$result = $this->core->get_last_insights( );

		if ( $result === false ) {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'Failed to retrieve last insights.',
			], 500 );
		}

		return new WP_REST_Response([
			'success' => true,
			'data' => $result
		], 200);
	}

	function rest_get_insights( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = isset( $params['post'] ) ? $params['post'] : null;

			if ( !$post_id ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post ID is required.',
				], 400 );
			}

			// Check if post exists
			$post = get_post( $post_id );
			if ( !$post && $post_id !== 'main' && $post_id !== 'delete' ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Invalid Post ID.',
				], 404 );
			}

			$result = $this->core->get_speed_and_vitals( $post_id );

			if ( $result === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Failed to retrieve insights.',
				], 500 );
			}

			return new WP_REST_Response([
				'success' => true,
				'data' => $result
			], 200);
		} catch (Exception $e) {
			$this->core->log( 'Error in rest_get_insights: ' . $e->getMessage() );
			return new WP_REST_Response(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
		}
	}


	#endregion

	#region Google Ranking
	function rest_fetch_searches(  ) {
		if ( is_null( $this->rank ) ) {
			throw new Exception( 'Google Ranking is not available.' );
		}

		$searches = $this->rank->get_updated_searches();
		return new WP_REST_Response([
			'success' => true,
			'message' => 'OK',
			'data' => $searches,
		], 200 );
	}

	function rest_delete_search( $request ) {
		try {
			if ( is_null( $this->rank ) ) {
				throw new Exception( 'Google Ranking is not available.' );
			}
		$params = $request->get_json_params();
		$searches = $this->rank->delete_search( $params['id'] );
		

		return new WP_REST_Response([
			'success' => true,
			'message' => 'OK',
			'data' => $searches,
		], 200 );

		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_save_search( $request ) {
		try {
			if ( is_null( $this->rank ) ) {
				throw new Exception( 'Google Ranking is not available.' );
			}
		$params = $request->get_json_params();
		
		$search = $this->rank->add_search( $params );
		
		return new WP_REST_Response([
			'success' => true,
			'message' => 'OK - Save New Search',
			'data' => $search,
		], 200 );

		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	#endregion

	#region WooCommerce

	function rest_generate_fields( $request ) {
		try {

			$params = $request->get_json_params();
			$meta = $this->core->generate_woocommerce_fields( $params );

			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => $meta,
			], 200 );
		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	#endregion

	function rest_ai_suggest( $request ) {
		try {

			$params = $request->get_json_params();
			$post = get_post( $params[ 'id' ] );
	
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}
			
			global $mwai;
			if (is_null( $mwai ) || !isset( $mwai ) ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Missing AI Engine.',
				], 500 );
			}else{
				$ai_suggestion = Meow_MWSEO_Modules_Suggestions::prompt( $post, $params[ 'field' ] );
			}

			if (empty($ai_suggestion) || is_null($ai_suggestion)) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'AI suggestion is invalid.',
				], 400 );
			}
	
			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => str_replace('"', '', $ai_suggestion),
			], 200 );
	
		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * Generate a solution for a specific SEO issue
	 */
	function rest_magic_fix_generate( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];
			$issue_type = $params['issue_type'];

			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}

			// Check if AI Engine is available
			global $mwai;
			if ( is_null( $mwai ) || !isset( $mwai ) ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'AI Engine is not available. Please make sure AI Engine plugin is installed and activated.',
				], 500 );
			}

			// Use premium Magic Fix class
			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Magic Fix is not available. This is a premium feature.',
				], 500 );
			}

			$result = $this->core->pro->magic_fix->generate_solution( $post, $issue_type );

			if ( $result === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Could not generate solution. Please try again.',
				], 200 );
			}

			return new WP_REST_Response([
				'success' => true,
				'data' => $result
			], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ ' . $e->getMessage());
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * Apply a solution to fix a specific SEO issue
	 */
	function rest_magic_fix_apply( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];
			$issue_type = $params['issue_type'];
			$solution = $params['solution'];

			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}

			// Use premium Magic Fix class
			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Magic Fix is not available. This is a premium feature.',
				], 500 );
			}

			$result = $this->core->pro->magic_fix->apply_solution( $post, $issue_type, $solution );

			if ( $result === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Could not apply solution.',
				], 200 );
			}

			// Check if result contains an error
			if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => $result['error'] ?? 'Could not apply solution.',
				], 200 );
			}

			// Don't recalculate score here - let user run analysis when ready
			// This preserves the _mwseo_issues_fixed markers

			return new WP_REST_Response([
				'success' => true,
				'message' => 'Solution applied successfully.',
				'data' => $result
			], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ ' . $e->getMessage());
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	/**
	 * Internal Links Step 1: Extract keywords from current post
	 */
	function rest_magic_fix_internal_links_step1( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];

			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Post not found.' ], 404 );
			}

			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Magic Fix is not available.' ], 500 );
			}

			// Call the magic_fix method for step 1
			$result = $this->core->pro->magic_fix->internal_links_step1( $post );

			return new WP_REST_Response([ 'success' => true, 'data' => $result ], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ Step 1 error: ' . $e->getMessage());
			return new WP_REST_Response([ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Internal Links Step 2: Build candidate list
	 */
	function rest_magic_fix_internal_links_step2( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];
			$keywords = $params['keywords'];

			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Post not found.' ], 404 );
			}

			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Magic Fix is not available.' ], 500 );
			}

			$result = $this->core->pro->magic_fix->internal_links_step2( $post, $keywords );

			return new WP_REST_Response([ 'success' => true, 'data' => $result ], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ Step 2 error: ' . $e->getMessage());
			return new WP_REST_Response([ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Internal Links Step 3: AI selects top posts
	 */
	function rest_magic_fix_internal_links_step3( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];
			$candidates = $params['candidates'];

			$post = get_post( $post_id );
			if ( !$post ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Post not found.' ], 404 );
			}

			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Magic Fix is not available.' ], 500 );
			}

			$result = $this->core->pro->magic_fix->internal_links_step3( $post, $candidates );

			return new WP_REST_Response([ 'success' => true, 'data' => $result ], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ Step 3 error: ' . $e->getMessage());
			return new WP_REST_Response([ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	/**
	 * Internal Links Step 4: Generate placements for a single post
	 */
	function rest_magic_fix_internal_links_step4( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'];
			$target_id = $params['target_id'];

			$post = get_post( $post_id );
			$target_post = get_post( $target_id );

			if ( !$post || !$target_post ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Post not found.' ], 404 );
			}

			if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
				return new WP_REST_Response([ 'success' => false, 'message' => 'Magic Fix is not available.' ], 500 );
			}

			$result = $this->core->pro->magic_fix->internal_links_step4( $post, $target_post );

			return new WP_REST_Response([ 'success' => true, 'data' => $result ], 200 );

		} catch( Exception $e ) {
			$this->core->log('❌ Step 4 error: ' . $e->getMessage());
			return new WP_REST_Response([ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	function rest_generate_daily_insight( $request ) {
		try {
			// Check if AI Engine is available
			if ( !class_exists( 'Meow_MWAI_Core' ) ) {
				return $this->error_response( 'AI Engine is not available.', 'ai_engine_unavailable', 503 );
			}

			global $mwai;

			// Get the 20 posts with worst scores
			$args = array(
				'post_type' => $this->core->get_option( 'select_post_types', ['post'] ),
				'posts_per_page' => 20,
				'orderby' => 'meta_value_num',
				'order' => 'ASC',
				'meta_key' => '_mwseo_score',
				'meta_query' => array(
					array(
						'key' => '_mwseo_score',
						'compare' => 'EXISTS'
					),
					array(
						'key' => '_mwseo_score',
						'value' => 0,
						'compare' => '>=',
						'type' => 'NUMERIC'
					)
				)
			);

			$posts = get_posts( $args );

			if ( empty( $posts ) ) {
				return $this->error_response( 'No analyzed posts found.', 'no_posts', 404 );
			}

			// Prime meta cache
			$post_ids = wp_list_pluck( $posts, 'ID' );
			update_meta_cache( 'post', $post_ids );

			// Build data for AI
			$posts_data = [];
			foreach ( $posts as $post ) {
				$score = get_post_meta( $post->ID, '_mwseo_score', true );
				$codes = get_post_meta( $post->ID, '_mwseo_codes', true );
				$edit_link = get_edit_post_link( $post->ID, 'raw' );

				// Format issue codes to be human-readable
				$issues = [];
				if ( is_array( $codes ) && !empty( $codes ) ) {
					foreach ( $codes as $code ) {
						// Convert snake_case to Title Case
						$issues[] = ucwords( str_replace( '_', ' ', $code ) );
					}
				}

				$posts_data[] = [
					'title' => $post->post_title,
					'score' => $score,
					'issues' => $issues,
					'edit_link' => $edit_link
				];
			}

			// Create AI prompt
			$prompt = "You are an SEO consultant. Below are the 20 posts with the lowest SEO scores from a website. Each post has a score (0-100), a list of SEO issues, and an edit link.\n\n";

			foreach ( $posts_data as $idx => $data ) {
				$prompt .= sprintf(
					"Post %d: \"%s\" (Score: %d)\n",
					$idx + 1,
					$data['title'],
					$data['score']
				);
				if ( !empty( $data['issues'] ) ) {
					$prompt .= "Issues: " . implode( ', ', $data['issues'] ) . "\n";
				}
				$prompt .= "Edit link: " . $data['edit_link'] . "\n\n";
			}

			$prompt .= "Based on these posts, write TWO short paragraphs:\n\n";
			$prompt .= "Paragraph 1: Start with a friendly greeting (Hi, Hello, Hey). Address the user directly (you, your). Identify the 2-3 main SEO issues affecting these posts and quick fixes. Be specific and actionable. Keep it under 3 sentences.\n\n";
			$prompt .= "Paragraph 2: Give 3 specific examples using short references. For each, create a markdown link using a brief topic/keyword from the title (2-4 words max), not the full title. Format: 'the posts about [topic](edit_link), [topic](edit_link), and [topic](edit_link)'. Keep it under 2 sentences.\n\n";
			$prompt .= "Use markdown for **bold** emphasis on issue names. Keep the tone friendly, personal, and encouraging. Address the user directly throughout. Total length: 5 sentences maximum across both paragraphs.";

			// Get AI response
			$insight_text = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo' ] );

			// Store insight with timestamp
			$this->core->update_option( 'daily_insight_text', $insight_text );
			$this->core->update_option( 'daily_insight_generated_at', current_time( 'mysql' ) );

			return $this->success_response( [
				'text' => $insight_text,
				'generated_at' => current_time( 'mysql' )
			] );

		} catch ( Exception $e ) {
			$this->core->log( '❌ Daily Insight error: ' . $e->getMessage() );
			return $this->error_response( $e->getMessage(), 'generation_failed', 500 );
		}
	}

	function rest_get_daily_insight( $request ) {
		$text = $this->core->get_option( 'daily_insight_text', null );
		$generated_at = $this->core->get_option( 'daily_insight_generated_at', null );

		if ( !$text ) {
			return $this->success_response( [
				'text' => null,
				'generated_at' => null
			] );
		}

		return $this->success_response( [
			'text' => $text,
			'generated_at' => $generated_at
		] );
	}

	function rest_ai_web_scraping( $request ) {

		try {
			if ( is_null( $this->rank ) ) {
				throw new Exception( 'Google Ranking is not available.' );
			}

			$params = $request->get_json_params();
			$value = $params[ 'search' ];

			// prepare the search parameters with default values
			$locale = get_locale();
			$search = [
				'q__search' => $value,
				'cr__country' => substr($locale, 3, 2),
				'hl__interface_language' => substr($locale, 0, 2),
				'gl__geolocation' => 'country' . substr($locale, 3, 2),
				'exactTerms__exact_terms'=> '',
				'excludeTerms__exclude_terms'=> '',
				'filter__filter' => '0',

				'd__depth' => 1,
			];
			$google = new MeowPro_MWSEO_Ranks_Google( $this->core );
			$result = $google->search( $search );

			if ( !$result ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'AI suggestion is invalid.',
				], 400 );
			}

			global $mwai;
			if( is_null( $mwai ) || !isset( $mwai ) ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Missing AI Engine.',
				], 500 );
			}

			$string_result = json_encode( $result );

			$prompt = "This are the top result for the search: " . $value . ". From them generate a title, an excerpt and a slug. Reverse engineer these result so the generated content is SEO optimized for this search. \n\n" . $string_result . "\n\n Use the following keys: title, excerpt, slug.";
			$suggestion = $mwai->simpleJsonQuery( $prompt );
	
			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => [
					'title' => $suggestion[ 'title' ], 
					'excerpt' => $suggestion[ 'excerpt' ],
					'slug' => $suggestion[ 'slug' ],
				]
			], 200 );
	
		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_import_data( $request ) {
		try {
			$params = $request->get_json_params();
			$plugin = $params[ 'plugin' ];

			switch ( $plugin ) {
				case 'rankmath':
					$import = $this->core->import_rank_math();
					break;
				case 'yoast':
					$import = $this->core->import_yoast();
					break;
				default:
					return new WP_REST_Response([
						'success' => true,
						'message' => 'Invalid plugin.',
					], 200 );
			}

			if ( $import ) {
				return new WP_REST_Response([
					'success' => true,
					'message' => "$import post(s) SEO data imported.",
					'data' => $import,
				], 200 );
			}
			else {
				return new WP_REST_Response([
					'success' => true,
					'message' => 'No posts found to import.',
				], 200 );
			}

		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_clear_ai_cache( $request ) {
		try {
			global $wpdb;

			// Get all posts with SEO analysis data
			$results = $wpdb->get_results(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE meta_key = '_mwseo_analysis'",
				ARRAY_A
			);

			$cleared_count = 0;

			foreach ( $results as $row ) {
				$post_id = $row['post_id'];
				$analysis = maybe_unserialize( $row['meta_value'] );

				// If analysis has AI data, remove it
				if ( is_array( $analysis ) && isset( $analysis['ai'] ) ) {
					unset( $analysis['ai'] );
					update_post_meta( $post_id, '_mwseo_analysis', $analysis );
					$cleared_count++;
				}
			}

			return $this->success_response(
				[ 'cleared_count' => $cleared_count ],
				$cleared_count > 0
					? "AI cache cleared for $cleared_count post(s)."
					: "No AI cache found to clear."
			);

		} catch ( Exception $e ) {
			$this->core->log( '❌ Clear AI cache error: ' . $e->getMessage() );
			return $this->error_response( $e->getMessage(), 'clear_cache_failed', 500 );
		}
	}

	function rest_get_ai_keywords( $request ){
		try{
			$params = $request->get_json_params();
			$post = get_post( $params[ 'id' ] );
	
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}
	
			$keywords = get_post_meta( $post->ID, '_mwseo_keywords', true );
	
			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => [
					'id_received' => $params[ 'id' ],
					'keywords' => $keywords == '' ? [] : $keywords,
				]
			], 200 );
	
		}
		catch( Exception $e)
		{
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_get_post_statuses( $request ) {
		$post_type = $request->get_param('type');

		if( empty($post_type) ) {
			return new WP_REST_Response([
				'success' => true,
				'data' => [],
			], 200 );
		}

		$type_to_domain = [
			'product' => 'woocommerce',
		];

		$statuses = get_post_stati( [] , 'objects' );

		$status_list = [];
		foreach ( $statuses as $status ) {
			$domain = $status->label_count["domain"] ?? 'core';

			if( $domain === 'core' || ( isset($type_to_domain[$post_type]) && $domain === $type_to_domain[$post_type] ) ) {
				$status_list[] = [
					'slug' => $status->name,
					'name' => $status->label,
				];
			}
		}

		return new WP_REST_Response([
			'success' => true,
			'data' => $status_list
		], 200 );
	}
	
	function rest_get_score_factors() {
		try {
			global $mwseo_score;
			
			if ( !$mwseo_score ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Score module not initialized.',
				], 500 );
			}
			
			$factors = $mwseo_score->get_score_factors();
			
			return new WP_REST_Response([
				'success' => true,
				'data' => $factors
			], 200 );
		}
		catch( Exception $e ) {
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_get_languages() {
		// Check if Polylang is active
		if ( function_exists( 'pll_languages_list' ) ) {
			$languages = [];
			$language_slugs = pll_languages_list();
			$language_names = pll_languages_list(['fields' => 'name']);
			
			foreach ( $language_slugs as $index => $slug ) {
				$languages[] = [
					'slug' => $slug,
					'name' => $language_names[$index]
				];
			}
			
			return new WP_REST_Response([
				'success' => true,
				'languages' => $languages,
			], 200 );
		} else {
			// Return empty array if Polylang is not active
			return new WP_REST_Response([
				'success' => true,
				'languages' => [],
			], 200 );
		}
	}

	#region Robots.txt
	function rest_get_robots_txt() {
		$robots = $this->core->get_robots_txt();

		$robotsTxt = $robots['content'] ?? '';
		$source    = $robots['source'] ?? 'default';

		
		return new WP_REST_Response( [ 'success' => true, 'data' => $robotsTxt, 'source' => $source ], 200 );
	}

	function rest_update_robots_txt( $request) {
		
		
		$params = $request->get_json_params();
		$content = $params['content'] ?? '';

		if ( empty( $content ) ) {
			return new WP_REST_Response( [ 
				'success' => false, 
				'message' => 'Content is empty. Please provide valid content.'
			], 400 );
		}

		// Validate the content (basic validation)
		if ( strlen( $content ) > 5000 ) {
			return new WP_REST_Response( [ 
				'success' => false, 
				'message' => 'Content is too long. Please limit it to 5000 characters.'
			], 400 );
		}
		
		$result = $this->core->set_robots_txt( $content );
		
		if ($result === false) {
			return new WP_REST_Response( [ 
				'success' => false, 
				'message' => 'Could not write to robots.txt file. Please check file permissions.'
			], 500 );
		}
		
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	function rest_ai_generate_robots_txt( $request ) {
		try {
			$params  = $request->get_json_params();

			$prompt  = empty( $params['prompt'] ) ? 'Generate a robots.txt file for a WordPress website.' : $params['prompt'];
			$content = $params['content'];

			if ( empty( $prompt ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Prompt is empty. Please provide a valid prompt.'
				], 400 );
			}

			global $mwai;
			if (is_null( $mwai ) || !isset( $mwai ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Missing AI Engine.'
				], 500 );
			}

			// Gather the necessary data for the prompt
			$site_url          = get_site_url();
			$site_name         = get_bloginfo( 'name' );
			$site_description  = get_bloginfo( 'description' );
			$site_language     = get_option( 'WPLANG' );
			$site_admin_email  = get_option( 'admin_email' );
			$site_post_types   = get_post_types( [ 'public' => true ], 'names' );
			$site_categories   = get_categories( [ 'hide_empty' => false ] );
			$site_taxonomies   = get_taxonomies( [ 'public' => true ], 'names' );
			$site_sitemap      = get_option( 'home' ) . '/sitemap.xml';
			$site_last_updated = date( 'Y-m-d H:i:s' );

			$site_data = [
				'site_url'          => $site_url,
				'site_name'         => $site_name,
				'site_description'  => $site_description,
				'site_language'     => $site_language,
				'site_admin_email'  => $site_admin_email,
				'site_post_types'   => implode( ', ', $site_post_types ),
				'site_categories'   => implode( ', ', wp_list_pluck( $site_categories, 'name' ) ),
				'site_taxonomies'   => implode( ', ', $site_taxonomies ),
				'site_sitemap'      => $site_sitemap,
				'site_last_updated' => $site_last_updated,
			];

			$site_data_json = json_encode( $site_data, JSON_PRETTY_PRINT );


			$instructions = "Generate a robots.txt file for a WordPress website. The content should be SEO optimized. Here are the details of the website:\n\n";
			$instructions .= "Website Data:\n";
			$instructions .= $site_data_json . "\n\n";
			$instructions .= "Here is the prompt from the user:\n";
			$instructions .= $prompt . "\n\n";
			$instructions .= "The current content of the robots.txt file is:\n";
			$instructions .= $content . "\n\n";
			$instructions .= "Please generate a robots.txt file based on the above information. Don't include any explanations, just provide the raw text of the robots.txt file. No quotes, no code blocks, just the text. The content should be SEO optimized and follow best practices for a WordPress website.\n\n";

			$robots_txt = $mwai->simpleTextQuery( $instructions, [ 'scope' => 'seo' ] );
			if ( empty( $robots_txt ) || is_null( $robots_txt ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'AI suggestion is invalid.'
				], 400 );
			}

			// Validate the generated robots.txt content
			if ( strlen( $robots_txt ) > 5000 ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Generated content is too long. Please limit it to 5000 characters.'
				], 400 );
			}

			// Send the generated robots.txt content back to the client
			return new WP_REST_Response( [ 
				'success' => true, 
				'message' => 'OK', 
				'data' => $robots_txt 
			], 200 );
			
		} catch (Exception $e) {
			return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	#endregion

	#region LLMs.txt
	function rest_get_llms_txt() {
		$llms = $this->core->get_llms_txt();

		$llmsTxt = $llms['content'] ?? '';
		$source  = $llms['source'] ?? 'default';

		
		return new WP_REST_Response( [ 'success' => true, 'data' => $llmsTxt, 'source' => $source ], 200 );
	}

	function rest_update_llms_txt( $request ) {
		$params  = $request->get_json_params();
		$content = $params['content'] ?? '';

		// Validate the content (basic validation)
		if ( strlen( $content ) > 50000 ) {
			return new WP_REST_Response( [ 
				'success' => false, 
				'message' => 'Content is too long. Please limit it to 50000 characters.'
			], 400 );
		}
		
		$result = $this->core->set_llms_txt( $content );
		
		if ( $result === false ) {
			return new WP_REST_Response( [ 
				'success' => false, 
				'message' => 'Could not write to llms.txt file. Please check file permissions.'
			], 500 );
		}
		
		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	function rest_ai_generate_llms_txt( $request ) {
		try {
			$params  = $request->get_json_params();

			$prompt  = $params['prompt'] ?? '';
			$content = $params['content'] ?? '';

			if ( empty( $prompt ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Prompt is empty. Please provide a valid prompt.'
				], 400 );
			}

			global $mwai;
			if ( is_null( $mwai ) || !isset( $mwai ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Missing AI Engine.'
				], 500 );
			}

			// Gather the necessary data for the prompt
			$site_url         = get_site_url();
			$site_name        = get_bloginfo( 'name' );
			$site_description = get_bloginfo( 'description' );
			$site_language    = get_option( 'WPLANG' );
			$site_post_types  = get_post_types( [ 'public' => true ], 'names' );
			$site_categories  = get_categories( [ 'hide_empty' => false ] );
			$site_taxonomies  = get_taxonomies( [ 'public' => true ], 'names' );

			$site_data = [
				'site_url'         => $site_url,
				'site_name'        => $site_name,
				'site_description' => $site_description,
				'site_language'    => $site_language,
				'site_post_types'  => implode( ', ', $site_post_types ),
				'site_categories'  => implode( ', ', wp_list_pluck( $site_categories, 'name' ) ),
				'site_taxonomies'  => implode( ', ', $site_taxonomies ),
				'site_sitemap'     => $this->core->get_option( 'sitemap_path' ),
			];

			$site_data_json = json_encode( $site_data, JSON_PRETTY_PRINT );

			$instructions  = "Generate an llms.txt file for a website. The llms.txt file is used to provide information to Large Language Models (LLMs) about your website content, structure, and how they should interact with it.\n\n";
			$instructions .= "Website Data:\n";
			$instructions .= $site_data_json . "\n\n";
			$instructions .= "Here is the prompt from the user:\n";
			$instructions .= $prompt . "\n\n";
			$instructions .= "The current content of the llms.txt file is:\n";
			$instructions .= $content . "\n\n";
			$instructions .= "Please generate an llms.txt file based on the above information. The llms.txt format typically includes:\n";
			$instructions .= "- A title line starting with # followed by the site name\n";
			$instructions .= "- A description block explaining what the site is about\n";
			$instructions .= "- Sections for different content types or pages\n";
			$instructions .= "- Links to important pages with descriptions\n";
			$instructions .= "- Any special instructions for LLMs\n\n";
			$instructions .= "Don't include any explanations, just provide the raw text of the llms.txt file. No quotes, no code blocks, just the text.\n\n";

			$llms_txt = $mwai->simpleTextQuery( $instructions, [ 'scope' => 'seo' ] );
			if ( empty( $llms_txt ) || is_null( $llms_txt ) ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'AI suggestion is invalid.'
				], 400 );
			}

			// Validate the generated llms.txt content
			if ( strlen( $llms_txt ) > 50000 ) {
				return new WP_REST_Response( [ 
					'success' => false, 
					'message' => 'Generated content is too long. Please limit it to 50000 characters.'
				], 400 );
			}

			// Send the generated llms.txt content back to the client
			return new WP_REST_Response( [ 
				'success' => true, 
				'message' => 'OK', 
				'data'    => $llms_txt 
			], 200 );
			
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	#endregion

	#region Sitemap

	function rest_sitemap_generate() {
		try {
			$res = $this->core->generate_sitemap();
			return new WP_REST_Response( [ 
				'success' => true, 
				'data' => $res
			], 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	#endregion

	#region Analytics

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_analytics_data( $request ) {
		try {
			$params = $request->get_json_params();
			$args = array(
				'post_id' => isset( $params['post_id'] ) ? intval( $params['post_id'] ) : null,
				'start_date' => isset( $params['start_date'] ) ? $params['start_date'] : null,
				'end_date' => isset( $params['end_date'] ) ? $params['end_date'] : null,
				'group_by' => isset( $params['group_by'] ) ? $params['group_by'] : 'day',
				'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 100
			);
			$data = $this->core->get_analytics_data( $args );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_analytics_summary( $request ) {
		try {
			$params = $request->get_json_params();
			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;
			$data = $this->core->get_analytics_summary( $start_date, $end_date );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_top_posts( $request ) {
		try {
			$params = $request->get_json_params();
			$args = array(
				'start_date' => isset( $params['start_date'] ) ? $params['start_date'] : null,
				'end_date' => isset( $params['end_date'] ) ? $params['end_date'] : null,
				'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 10
			);
			$data = $this->core->get_top_posts( $args );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_get_ai_agents_summary( $request ) {
		try {
			$params = $request->get_json_params();
			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;
			$data = $this->core->get_ai_agents_summary( $start_date, $end_date );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_get_ai_agent_details( $request ) {
		try {
			$params = $request->get_json_params();
			$bot_name = isset( $params['bot_name'] ) ? $params['bot_name'] : null;
			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;
			
			if ( !$bot_name ) {
				return new WP_REST_Response( [
					'success' => false,
					'message' => 'Bot name is required'
				], 400 );
			}

			$data = $this->core->get_ai_agent_details( $bot_name, $start_date, $end_date );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_get_ai_agents_by_post( $request ) {
		try {
			$params = $request->get_json_params();
			$post_id = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : null;
			$days = isset( $params['days'] ) ? intval( $params['days'] ) : 30;

			if ( !$post_id ) {
				return new WP_REST_Response( [
					'success' => false,
					'message' => 'Post ID is required'
				], 400 );
			}

			$data = $this->core->get_ai_agents_by_post( $post_id, $days );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		}
		catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_check_google_analytics_authenticated() {
		$is_authenticated = $this->core->get_is_authenticated();
		return new WP_REST_Response( [
			'success' => true,
			'is_authenticated' => $is_authenticated,
		], 200 );
	}

	function rest_get_google_analytics_auth() {
		$auth_url = $this->core->get_google_auth_url();
		if ( $auth_url ) {
			return new WP_REST_Response( [
				'success' => true,
				'auth_url' => $auth_url
			], 200 );
		}
		else {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Failed to get Google Analytics redirect URL.'
			], 500 );
		}
	}

	function rest_unlink_google_analytics() {
		$res = $this->core->unlink_google_analytics();
		if ( $res ) {
			return new WP_REST_Response( [
				'success' => true,
				'message' => 'Google Analytics unlinked successfully.'
			], 200 );
		}
		else {
			return new WP_REST_Response( [
				'success' => false,
				'message' => 'Failed to unlink Google Analytics.'
			], 500 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_google_analytics_data( $request ) {
		try {
			$params = $request->get_json_params();
			$args = array(
				'start_date' => isset( $params['start_date'] ) ? $params['start_date'] : null,
				'end_date' => isset( $params['end_date'] ) ? $params['end_date'] : null,
				'group_by' => isset( $params['group_by'] ) ? $params['group_by'] : 'day',
				'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 100
			);
			$data = $this->core->get_google_analytics_data( $args );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data,
				'has_error' => false,
				'error_message' => null
			], 200 );

		}
		catch ( Exception $e ) {
			// Return error in the same format as success, but with has_error flag
			return new WP_REST_Response( [
				'success' => true,
				'data' => array(),
				'has_error' => true,
				'error_message' => $e->getMessage()
			], 200 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_google_analytics_summary( $request ) {
		try {
			$params = $request->get_json_params();

			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;

			$data = $this->core->get_google_analytics_summary( $start_date, $end_date );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data,
				'has_error' => false,
				'error_message' => null
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => true,
				'data' => array(),
				'has_error' => true,
				'error_message' => $e->getMessage()
			], 200 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_google_analytics_top_posts( $request ) {
		try {
			$params = $request->get_json_params();

			$args = array(
				'start_date' => isset( $params['start_date'] ) ? $params['start_date'] : null,
				'end_date' => isset( $params['end_date'] ) ? $params['end_date'] : null,
				'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 10
			);

			$data = $this->core->get_google_analytics_top_posts( $args );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data,
				'has_error' => false,
				'error_message' => null
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => true,
				'data' => array(),
				'has_error' => true,
				'error_message' => $e->getMessage()
			], 200 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_google_analytics_realtime( $request ) {
		try {
			$data = $this->core->get_google_analytics_realtime_data();

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		} catch ( Exception $e ) {
			// Note: Realtime errors are not fatal, just return empty data
			// The main data/summary/top_posts will show the error
			return new WP_REST_Response( [
				'success' => true,
				'data' => array()
			], 200 );
		}
	}

	// Plausible Analytics

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_plausible_analytics_data( $request ) {
		try {
			$params = $request->get_json_params();
			$args = array(
				'start_date' => isset( $params['start_date'] ) ? $params['start_date'] : null,
				'end_date' => isset( $params['end_date'] ) ? $params['end_date'] : null,
				'group_by' => isset( $params['group_by'] ) ? $params['group_by'] : 'day',
				'limit' => isset( $params['limit'] ) ? intval( $params['limit'] ) : 100
			);
			$data = $this->core->get_plausible_analytics_data( $args );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_plausible_analytics_summary( $request ) {
		try {
			$params = $request->get_json_params();

			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;

			$data = $this->core->get_plausible_analytics_summary( $start_date, $end_date );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function rest_get_plausible_analytics_top_posts( $request ) {
		try {
			$params = $request->get_json_params();

			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;
			$limit = isset( $params['limit'] ) ? intval( $params['limit'] ) : 10;

			$data = $this->core->get_plausible_analytics_top_posts( $start_date, $end_date, $limit );

			return new WP_REST_Response( [
				'success' => true,
				'data' => $data
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_reset_data( $request ) {
		try {
			global $wpdb;
			$prefix = $wpdb->prefix;

			// Delete all SEO analysis data from post meta
			$wpdb->query( "DELETE FROM {$prefix}postmeta WHERE meta_key LIKE '_mwseo_%'" );

			// Delete analytics tables if they exist
			$analytics_table = $prefix . 'mwseo_analytics';
			$ai_agents_table = $prefix . 'mwseo_ai_agents';

			$wpdb->query( "TRUNCATE TABLE {$analytics_table}" );
			$wpdb->query( "TRUNCATE TABLE {$ai_agents_table}" );

			// Clear any cached data
			wp_cache_flush();

			return $this->success_response( null, 'All SEO Engine data has been reset successfully.' );

		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage(), 'reset_data_failed', 500 );
		}
	}

	/**
	 * Baseline analysis - runs technical checks only, returns AI steps to run
	 * POST /analysis/baseline
	 * Body: { post_id: 123 }
	 */
	function rest_analysis_baseline( $request ) {
		global $mwseo_score;

		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'] ?? null;

			if ( !$post_id ) {
				return $this->error_response( 'Missing post_id parameter', 'missing_post_id', 400 );
			}

			$post = get_post( $post_id );
			if ( !$post ) {
				return $this->error_response( 'Post not found', 'post_not_found', 404 );
			}

			if ( !$mwseo_score ) {
				return $this->error_response( 'Score module not initialized', 'score_module_error', 500 );
			}

			// Generate a session ID
			$session_id = wp_generate_uuid4();

			// Store session metadata
			update_post_meta( $post_id, '_mwseo_analysis_session', [
				'session_id' => $session_id,
				'started_at' => time(),
			] );

			// Run baseline analysis (technical checks only)
			$result = $this->core->calculate_seo_score( $post, 'baseline' );

			// Get list of AI steps to run
			$ai_steps = $mwseo_score->get_enabled_ai_steps();

			// Get the updated post data
			$score_data = get_post_meta( $post_id, '_mwseo_analysis', true );

			return new WP_REST_Response( [
				'success' => true,
				'session_id' => $session_id,
				'ai_steps' => $ai_steps,
				'result' => $result,
				'analysis' => $score_data,
			], 200 );

		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage(), 'baseline_analysis_failed', 500 );
		}
	}

	/**
	 * Run a single AI analysis step
	 * POST /analysis/ai-step
	 * Body: { post_id: 123, step: 'grammar', session_id: 'uuid' }
	 */
	function rest_analysis_ai_step( $request ) {
		global $mwseo_score;

		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'] ?? null;
			$step = $params['step'] ?? null;
			$session_id = $params['session_id'] ?? null;

			if ( !$post_id || !$step || !$session_id ) {
				return $this->error_response( 'Missing required parameters', 'missing_parameters', 400 );
			}

			// Check if session is still valid
			$session_meta = get_post_meta( $post_id, '_mwseo_analysis_session', true );
			if ( !$session_meta || $session_meta['session_id'] !== $session_id ) {
				return new WP_REST_Response( [
					'success' => false,
					'code' => 'stale_session',
					'message' => 'Session has been superseded by a newer analysis'
				], 200 ); // Return 200 so client can handle gracefully
			}

			$post = get_post( $post_id );
			if ( !$post ) {
				return $this->error_response( 'Post not found', 'post_not_found', 404 );
			}

			if ( !$mwseo_score ) {
				return $this->error_response( 'Score module not initialized', 'score_module_error', 500 );
			}

			// Run the AI step
			$step_result = $mwseo_score->run_ai_step( $post, $step );

			if ( $step_result === false ) {
				return new WP_REST_Response( [
					'success' => false,
					'code' => 'ai_error',
					'message' => 'AI step failed',
					'step' => $step
				], 200 ); // Return 200 so client can decide to retry or skip
			}

			// Merge the step result into the analysis
			$merged = $mwseo_score->merge_ai_step( $post_id, $step_result );

			if ( !$merged ) {
				return $this->error_response( 'Failed to merge AI step result', 'merge_failed', 500 );
			}

			// Get updated analysis data
			$analysis = get_post_meta( $post_id, '_mwseo_analysis', true );

			// Extract issues found in this AI step
			$step_issues = $this->extract_ai_step_issues( $step, $step_result, $analysis );

			// Map step name to penalty key
			$step_to_penalty_map = [
				'summary' => 'semantic_alignment',
				'grammar' => 'grammar_typos',
				'authenticity' => 'authenticity_originality',
				'personality' => 'personality_engagement',
				'structure' => 'structure_quality',
				'readability' => 'readability_score',
				'topic' => 'topic_completeness'
			];

			// Get penalties for this specific step
			$step_penalties = [];
			$penalty_key = $step_to_penalty_map[$step] ?? null;
			if ( $penalty_key && isset( $analysis['penalties'][$penalty_key] ) ) {
				$step_penalties[$penalty_key] = $analysis['penalties'][$penalty_key];
			}

			// Debug logging
			error_log( "SEO Engine - AI Step '{$step}' completed:" );
			error_log( "  Penalty key: {$penalty_key}" );
			error_log( "  Test value: " . ( isset( $analysis['tests'][$penalty_key] ) ? $analysis['tests'][$penalty_key] : 'NOT SET' ) );
			error_log( "  Penalty value: " . ( isset( $analysis['penalties'][$penalty_key] ) ? $analysis['penalties'][$penalty_key] : 'NOT SET' ) );
			error_log( "  Step penalties: " . json_encode( $step_penalties ) );
			error_log( "  AI data: " . json_encode( $analysis['ai'] ?? [] ) );

			// Return unified response format
			return $this->success_response( [
				'step' => $step,
				'completed' => true,
				'score' => $analysis['overall'] ?? 0,
				'step_issues' => $step_issues,
				'step_penalties' => $step_penalties,
				'analysis' => $analysis
			] );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'code' => 'ai_step_exception',
				'message' => $e->getMessage(),
				'step' => $step ?? 'unknown'
			], 200 ); // Return 200 so client can handle gracefully
		}
	}

	/**
	 * NEW UNIFIED ANALYSIS API
	 * Initialize analysis session and return metadata about what will be analyzed
	 * POST /analysis/init
	 * Body: { post_id: 123, mode: 'quick' | 'full' }
	 */
	function rest_analysis_init( $request ) {
		global $mwseo_score;

		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'] ?? null;
			$mode = $params['mode'] ?? 'quick'; // 'quick' or 'full'

			if ( !$post_id ) {
				return $this->error_response( 'Missing post_id parameter', 'missing_post_id', 400 );
			}

			$post = get_post( $post_id );
			if ( !$post ) {
				return $this->error_response( 'Post not found', 'post_not_found', 404 );
			}

			if ( !$mwseo_score ) {
				return $this->error_response( 'Score module not initialized', 'score_module_error', 500 );
			}

			// Generate a session ID
			$session_id = wp_generate_uuid4();

			// Store session metadata
			update_post_meta( $post_id, '_mwseo_analysis_session', [
				'session_id' => $session_id,
				'started_at' => time(),
				'mode' => $mode,
			] );

			// Get list of AI steps (if full mode)
			$ai_steps = [];
			if ( $mode === 'full' ) {
				$ai_steps = $mwseo_score->get_enabled_ai_steps();
			}

			// Return metadata about what will be analyzed
			return $this->success_response( [
				'session_id' => $session_id,
				'mode' => $mode,
				'steps' => [
					'tech' => [
						'enabled' => true,
						'name' => 'Technical Analysis'
					],
					'ai' => [
						'enabled' => $mode === 'full' && count( $ai_steps ) > 0,
						'steps' => $ai_steps
					]
				]
			] );

		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage(), 'init_failed', 500 );
		}
	}

	/**
	 * Run technical analysis step
	 * POST /analysis/tech-step
	 * Body: { post_id: 123, session_id: 'uuid' }
	 * Returns: Unified response with score, issues_found, penalties, analysis
	 */
	function rest_analysis_tech_step( $request ) {
		global $mwseo_score;

		try {
			$params = $request->get_json_params();
			$post_id = $params['post_id'] ?? null;
			$session_id = $params['session_id'] ?? null;

			if ( !$post_id || !$session_id ) {
				return $this->error_response( 'Missing required parameters', 'missing_parameters', 400 );
			}

			// Check if session is still valid
			$session_meta = get_post_meta( $post_id, '_mwseo_analysis_session', true );
			if ( !$session_meta || $session_meta['session_id'] !== $session_id ) {
				return $this->error_response( 'Session has been superseded by a newer analysis', 'stale_session', 409 );
			}

			$post = get_post( $post_id );
			if ( !$post ) {
				return $this->error_response( 'Post not found', 'post_not_found', 404 );
			}

			if ( !$mwseo_score ) {
				return $this->error_response( 'Score module not initialized', 'score_module_error', 500 );
			}

			// Run technical analysis (baseline)
			$result = $this->core->calculate_seo_score( $post, 'baseline' );

			// Get updated analysis data
			$analysis = get_post_meta( $post_id, '_mwseo_analysis', true );

			// Extract issues found in this technical step
			$step_issues = $this->extract_technical_issues( $analysis );

			return $this->success_response( [
				'step' => 'tech',
				'completed' => true,
				'score' => $analysis['overall'] ?? 0,
				'step_issues' => $step_issues,
				'step_penalties' => $analysis['penalties'] ?? [],
				'analysis' => $analysis
			] );

		} catch ( Exception $e ) {
			return $this->error_response( $e->getMessage(), 'tech_step_failed', 500 );
		}
	}

	/**
	 * Helper to extract technical issues from analysis
	 * Returns list of issues with test, title, description, penalty
	 */
	private function extract_technical_issues( $analysis ) {
		if ( !isset( $analysis['tests'] ) ) {
			return [];
		}

		$issues = [];
		$tests = $analysis['tests'];
		$penalties = $analysis['penalties'] ?? [];
		$max_penalties = $analysis['max_penalties'] ?? [];

		// Technical tests (non-AI)
		$technical_tests = [
			'title_exists', 'title_unique_sitewide', 'title_length', 'slug_structure',
			'excerpt_exists', 'excerpt_length', 'author_visible', 'content_depth',
			'not_orphaned', 'internal_links', 'external_link_present', 'alt_coverage',
			'schema_integrity', 'featured_image', 'meta_robots_tag'
		];

		foreach ( $technical_tests as $test_name ) {
			if ( isset( $tests[$test_name] ) && $tests[$test_name] === 'fail' ) {
				$issues[] = [
					'test' => $test_name,
					'penalty' => $penalties[$test_name] ?? 0,
					'max_penalty' => $max_penalties[$test_name] ?? 0
				];
			}
		}

		return $issues;
	}

	/**
	 * Helper to extract AI step issues from step result
	 */
	private function extract_ai_step_issues( $step, $step_result, $analysis ) {
		$issues = [];

		// Map step name to test/penalty key
		$step_to_test_map = [
			'summary' => 'semantic_alignment',
			'grammar' => 'grammar_typos',
			'authenticity' => 'authenticity_originality',
			'personality' => 'personality_engagement',
			'structure' => 'structure_quality',
			'readability' => 'readability_score',
			'topic' => 'topic_completeness'
		];

		$test_name = $step_to_test_map[$step] ?? null;
		if ( !$test_name ) {
			return $issues;
		}

		$tests = $analysis['tests'] ?? [];
		$penalties = $analysis['penalties'] ?? [];

		// Check if this AI step has a penalty (score < 100 or test failed)
		// AI tests return numeric scores (0-100), not 'fail'
		if ( isset( $tests[$test_name] ) ) {
			$test_value = $tests[$test_name];
			$has_penalty = ( is_numeric( $test_value ) && $test_value < 100 ) || $test_value === 'fail' || $test_value === 0;

			if ( $has_penalty && isset( $penalties[$test_name] ) && $penalties[$test_name] > 0 ) {
				$issues[] = [
					'test' => $test_name,
					'penalty' => $penalties[$test_name]
				];
			}
		}

		return $issues;
	}

	#endregion
}
