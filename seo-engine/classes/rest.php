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
			register_rest_route( $this->namespace, '/ai_magic_fix', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_magic_fix' )
			) );
			register_rest_route( $this->namespace, '/ai_magic_fix_update_post', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_magic_fix_update_post' )
			) );
			register_rest_route( $this->namespace, '/ai_magic_fix_new_suggestion', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_ai_magic_fix_new_suggestion' )
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

	function rest_scored_posts( ) {
		$scored_posts = $this->core->get_all_posts_with_seo_score();

		return new WP_REST_Response( [
			'success' => true,
			'data' => $scored_posts,
		], 200);
	}

	function rest_posts($request) {
		$post_type = $this->core->get_option('default_post_type', 'post');
	
		$params = $request->get_json_params();
	
		$sort = $params['sort'];
		$page = $params['page'];
		$limit = $params['limit'];
		$offset = ($page - 1) * $limit;
		
		$search = isset($params['search']) ? $params['search'] : null;
		$filter = isset($params['filterBy']) ? $params['filterBy'] : null;
		$filter = $filter == 'all' ? null : $filter;
		
		// Get language filter
		$language = isset($params['language']) ? $params['language'] : null;
		if (empty($language) || $language === 'all') {
			$language = $this->core->get_option('default_language', 'all');
		}
	
		$total_counts = [
			'pending' => 0,
			'issue' => 0,
			'major_issue' => 0,
			'skip' => 0,
			'ok' => 0,
			'all' => 0,
		];
	
		$args = [
			'post_type' => $post_type,
			'posts_per_page' => -1, // Get all posts
			'orderby' => 'meta_value_num', //$sort['accessor'],
			'order' => $sort['by'] == 'desc' ? 'DESC' : 'ASC',
			'nopaging' => true,
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => '_seo_engine_score',
					'compare' => 'EXISTS', // This will find posts that have the meta key
				],
				[
					'key' => '_seo_engine_score',
					'compare' => 'NOT EXISTS', // This will find posts that do not have the meta key
				],
			],
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
	
		if ($search) {
			$args['s'] = $search; // Search in post title and content
		}
	
		$query = new WP_Query($args);
		$total_counts['all'] = $query->found_posts; // Get the total count
		$posts = $query->posts; // Get all posts
	
		$data = [];
		foreach ($posts as $post) {

			$status = get_post_meta($post->ID, '_seo_engine_status', true);
			if ( $status == 'error' ) {
				$score = get_post_meta($post->ID, '_seo_engine_score', true);
				$status = $score < 50 ? 'major_issue' : 'issue';
			}

			if ( empty($status) ) {
				$status = 'pending';
			}

			$total_counts[$status]++;
	
			if ($filter && $status != $filter) {
				continue;
			}
	
			$data[] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'excerpt' => $post->post_excerpt,
				'slug' => $post->post_name,
				'permalink' => get_permalink($post->ID),
				'status' => $this->core->get_seo_engine_post_meta($post),
				'publish_date' => $post->post_date,
				'featured_image' => get_the_post_thumbnail_url($post->ID, 'full'),
				'seo_title' => get_post_meta($post->ID, $this->core->meta_key_seo_title, true),
				'seo_excerpt' => get_post_meta($post->ID, $this->core->meta_key_seo_excerpt, true),
				'rendered_title' => $this->core->build_title($post),
				'rendered_excerpt' => $this->core->build_excerpt($post),
				'post_type' => $post->post_type,
				'language' => function_exists('pll_get_post_language') ? pll_get_post_language($post->ID, 'slug') : null,
			];
		}
	
		wp_reset_postdata();

		$paginated_data = array_slice($data, $offset, $limit); // Apply offset and limit
	
		return new WP_REST_Response([
			'success' => true,
			'posts' => $paginated_data,
			'total' => $total_counts,
		], 200);
	}

	function rest_get_all_ids() {
		$post_type = $this->core->get_option( 'default_post_type', 'post' );
		$language  = $this->core->get_option( 'default_language', 'all' );
	
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

		$results = [];
		$success = true;
		$message = 'OK';

		foreach ($post_ids as $post_id) {
			$post = get_post( $post_id );
			if ( !$post ) {
				$success = false;
				$message = 'Post not found for ID: ' . $post_id;
				break;
			}

			$score = $this->core->calculate_seo_score( $post );
			$results[$post_id] = $score;
		}

		return new WP_REST_Response( [
			'success' => $success,
			'message' => $message,
			'data' => [
				'results' => $results,
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
		$this->update_or_delete_post_meta( $post_id, '_seo_engine_ai_keywords', $ai_keywords );


		// Update the post metadata.
		$seo_title = $params['seo_title'] ?? null;
		$seo_excerpt = $params['seo_excerpt'] ?? null;
		if ( $seo_title !== null ) {
			$this->update_or_delete_post_meta( $post_id, $this->core->meta_key_seo_title, $seo_title );
		}
		if ( $seo_excerpt !== null ) {
			$this->update_or_delete_post_meta( $post_id, $this->core->meta_key_seo_excerpt, $seo_excerpt );
		}

		return new WP_REST_Response( [
			'success' => true,
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

		$this->update_or_delete_post_meta( $post_id, '_seo_engine_status', $skip ? 'skip' : 'pending' );
		$this->update_or_delete_post_meta( $post_id, '_seo_engine_message', $skip ? 'This post has been skipped. No SEO score.' : null );
		$this->update_or_delete_post_meta( $post_id, '_seo_engine_score', null );

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

	function rest_ai_magic_fix_new_suggestion( $request ){
		try{
			$params = $request->get_json_params();
			$post = get_post( $params[ 'id' ] );
			$field = $params[ 'field' ];
	
	
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}
	
			$ai_suggestion = Meow_MWSEO_Modules_Suggestions::prompt( $post, $field  );
	
			if ( empty( $ai_suggestion ) || is_null( $ai_suggestion ) ) {
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


	function rest_ai_magic_fix_update_post( $request ){
		try{
			$params = $request->get_json_params();
			$post = get_post( $params[ 'id' ] );
	
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}
			
			$fix_result = $this->core->magic_fix( $post, $params[ 'fixes' ], true );

			if ( $fix_result === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Missing AI Engine.',
				], 200 );
			}

			// re-analyze the post after the fixes
			$score = $this->core->calculate_seo_score( $post );
	
			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => [
					'id_received' => $params[ 'id' ],
					'fixes_result' => $fix_result,
				]
			], 200 );
	
		}
		catch( Exception $e)
		{
			$this->core->log('❌ ' . $e->getMessage());
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	function rest_ai_magic_fix( $request ){
		try{
			$params = $request->get_json_params();
			$post = get_post( $params[ 'id' ] );
	
			if ( !$post ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Post not found.',
				], 404 );
			}

			$fix_result = $this->core->magic_fix( $post, $params[ 'codes' ] );
			if ( $fix_result === false ) {
				return new WP_REST_Response([
					'success' => false,
					'message' => 'Missing AI Engine.',
				], 200 );
			}

			return new WP_REST_Response([
				'success' => true,
				'message' => 'OK',
				'data' => [
					'id_received' => $params[ 'id' ],
					'codes_received' => $params[ 'codes' ],
					'fix_result' => $fix_result,
				]
			], 200 );

		}
		catch( Exception $e)
		{
			$this->core->log('❌ ' . $e->getMessage());
			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
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
	
			$keywords = get_post_meta( $post->ID, '_seo_engine_ai_keywords', true );
	
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

			$prompt  = $params['prompt'] ?? 'Generate a robots.txt file for a WordPress website.';
			$content = $params['content'] ?? '';

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

			$robots_txt = $mwai->simpleTextQuery( $instructions, ['scope' => 'seo-engine'] );
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

	#region Sitemap

	function rest_sitemap_generate() {
		try {
			$path = $this->core->generate_sitemap();
			return new WP_REST_Response( [ 
				'success' => true, 
				'message' => 'Sitemap generated successfully at ' . $path,
				'data' => [ 'path' => $path ]
			], 200 );
		} catch ( Exception $e ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
		}
	}

	#endregion

	#region Analytics

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

	function rest_get_google_analytics_summary( $request ) {
		try {
			$params = $request->get_json_params();
			
			$start_date = isset( $params['start_date'] ) ? $params['start_date'] : null;
			$end_date = isset( $params['end_date'] ) ? $params['end_date'] : null;

			$data = $this->core->get_google_analytics_summary( $start_date, $end_date );

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
				'data' => $data
			], 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage()
			], 500 );
		}
	}

	function rest_get_google_analytics_realtime( $request ) {
		try {
			$data = $this->core->get_google_analytics_realtime_data();

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

	#endregion
}
