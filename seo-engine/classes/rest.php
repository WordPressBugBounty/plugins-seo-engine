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
			// LOGS
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

			// SETTINGS
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
			register_rest_route( $this->namespace, '/update_skip_option', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_update_skip_option' )
			) );
			register_rest_route( $this->namespace, '/get_ai_keywords', array(
				'methods' => 'POST',
				'permission_callback' => array( $this->core, 'can_access_settings' ),
				'callback' => array( $this, 'rest_get_ai_keywords' )
			) );

			// Google Ranking
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

			// WooCommerce
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


			// AI Engine
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
		$post_type = get_option('seo_kiss_options', null)['seo_engine_default_post_type'] ?? null;
	
		$params = $request->get_json_params();
	
		$sort = $params['sort'];
		$page = $params['page'];
		$limit = $params['limit'];
		$offset = ($page - 1) * $limit;
		
		$search = isset($params['search']) ? $params['search'] : null;
		$filter = isset($params['filterBy']) ? $params['filterBy'] : null;
		$filter = $filter == 'all' ? null : $filter;
	
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
		$post_type = get_option('seo_kiss_options', null)['seo_engine_default_post_type'] ?? 'post';
	
		$args = [
			'post_type' => $post_type,
			'posts_per_page' => -1, // Get all posts
			'fields' => 'ids',
		];
	
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
		$featured_image = apply_filters( 'seo_engine_social_networks_featured_image', $featured_image, $post->ID );

		

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
}
