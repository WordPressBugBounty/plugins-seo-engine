<?php

class Meow_MWSEO_Core
{
	public $admin = null;
	public $is_rest = false;
	public $site_url = null;
	public $pro = null; // Premium features

	// TODO: Migrate to _mwseo_title
	public $meta_key_seo_title = '_kiss_seo_title';
	// TODO: Migrate to _mwseo_excerpt
	public $meta_key_seo_excerpt = '_kiss_seo_excerpt';
	// TODO: Migrate to _mwseo_ignore
	public $meta_key_skip = '_kiss_seo_ignore';
	public $meta_key_seo_keywords = '_mwseo_keywords';

	private $old_option_name = 'seo_kiss_options';
	private $option_name = 'mwseo_options';

	private $sitemap_module = null;
	private $schema_module = null;
	private $insights_module = null;
	private $analytics_module = null;
	private $googleanalytics_module = null;

	public function __construct() {
		global $mwseo_core;
		$mwseo_core = $this;

		$this->site_url = get_site_url();
		$this->is_rest = MeowKit_MWSEO_Helpers::is_rest();

		add_action( 'plugins_loaded', array( $this, 'init' ) );

		$analyze_on_update = $this->get_option( 'analyze_on_update', 'none' );
		if ( $analyze_on_update !== 'none' && $analyze_on_update !== false ) {
			add_action( 'save_post', array( $this, 'analyze_on_update' ), 10, 3 );
		}
	}

	function init() {
		global $mwseo;

		$mwseo = new Meow_MWSEO_API( $this );

		// Part of the core, settings and stuff
		$this->admin = new Meow_MWSEO_Admin( $this );

		// Only for REST
		if ( $this->is_rest ) {
			new Meow_MWSEO_Rest( $this, $this->admin );
		}

		// Attachment URL redirection feature
		$redirect_attachments = $this->get_option( 'redirect_attachments', false );
		if ( $redirect_attachments ) {
			add_filter( 'attachment_link', array( $this, 'redirect_attachment_to_parent' ), 10, 2 );
			add_action( 'template_redirect', array( $this, 'handle_attachment_redirect' ) );
		}

		// Dashboard
		$use_mwseo_sitemap = $this->get_option( 'sitemap', false );
		if ( $use_mwseo_sitemap ) {
			add_filter(
				'init',
				function() {
					$this->sitemap_module = new Meow_MWSEO_Sitemap( $this );
					wp_register_sitemap_provider( MWSEO_DOMAIN, $this->sitemap_module );
				}
			);

		}

		// Website
		//add_filter( 'wp_title', [ $this, 'render_title' ], 10, 2 );
		$use_content_seo = $this->get_option( 'content_seo', false );
		$use_technical_seo = $this->get_option( 'technical_seo', false );
		$use_seo_metadata = $this->get_option( 'use_seo_metadata', true );
		$auto_page_title = $this->get_option( 'auto_page_title', true );
		if ( ( $use_content_seo || $use_technical_seo ) && $auto_page_title ) {
			add_filter( 'pre_get_document_title', [ $this, 'render_title' ], 99, 1 );
			// Remove WordPress archive prefixes ("Archives: ", "Category: ", etc.) for cleaner SEO titles
			add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
		}
		if ( $use_content_seo && $use_seo_metadata ) {
			add_action( 'wp_head', [ $this, 'render_description' ], 99, 0 );
		}

		add_action( 'init', [ $this, 'reject_gptbot_user_agent' ], 99, 0 );


		// Woocommerce
		if ( $this->get_option( 'woocommerce_assistant', false ) ) {
			add_action( 'add_meta_boxes', array( $this, 'add_wc_meta_boxes' ) );
		}

		// Advanced Core
		if ( class_exists( 'MeowPro_MWSEO_Core' ) ) {
			$this->pro = new MeowPro_MWSEO_Core( $this );
		}

		// Insights
		if ( class_exists( 'Meow_MWSEO_Modules_Insights' ) ) {
			$this->insights_module = new Meow_MWSEO_Modules_Insights( $this );
		}

		// Schema
		if ( class_exists( 'Meow_MWSEO_Modules_Schema' ) ) {
			$this->schema_module = new Meow_MWSEO_Modules_Schema( $this );
		}

		// Analytics
		if ( class_exists( 'Meow_MWSEO_Modules_Analytics' ) ) {
			$this->analytics_module = new Meow_MWSEO_Modules_Analytics( $this );
			
			// Track visits on frontend
			if ( !is_admin() && !$this->is_rest ) {
				add_action( 'wp', array( $this, 'track_current_visit' ) );
			}
		}

		// Google Analytics
		if ( class_exists( 'Meow_MWSEO_Modules_GoogleAnalytics' ) ) {
			$this->googleanalytics_module = new Meow_MWSEO_Modules_GoogleAnalytics( $this );
		}

		// MCP integration - check both class and global variable
		if ( class_exists( 'Meow_MWAI_Core' ) || isset( $GLOBALS['mwai'] ) ) {
			new Meow_MWSEO_MCP( $this );
		}
	}

	function get_logs() {
		$log_file_path = $this->get_logs_path();

		if ( !file_exists( $log_file_path ) ) {
			return "Empty log file.";
		}

		$content = file_get_contents( $log_file_path );
		// $lines = explode( "\n", $content );
		// $lines = array_filter( $lines );
		// $lines = array_reverse( $lines );
		// $content = implode( "\n", $lines );
		return $content;
	}

	function clear_logs() {
		$logPath = $this->get_logs_path();
		if ( file_exists( $logPath ) ) {
			unlink( $logPath );
		}

		$options = $this->get_all_options();
		$options['logs_path'] = null;
		$this->update_options( $options );
	}

	function get_logs_path() {
		$uploads_dir = wp_upload_dir();
		$uploads_dir_path = trailingslashit( $uploads_dir['basedir'] );

		$path = $this->get_option( 'logs_path' );

		if ( $path && file_exists( $path ) ) {
			// make sure the path is legal (within the uploads directory with the MWSEO_PREFIX and log extension)
			if ( strpos( $path, $uploads_dir_path ) !== 0 || strpos( $path, MWSEO_PREFIX ) === false || substr( $path, -4 ) !== '.log' ) {
				$path = null;
			} else {
				return $path;
			}
		}

		if ( !$path ) {
			$path = $uploads_dir_path . MWSEO_PREFIX . "_" . $this->random_ascii_chars() . ".log";
			if ( !file_exists( $path ) ) {
				touch( $path );
			}
			$options = $this->get_all_options();
			$options['logs_path'] = $path;
			$this->update_options( $options );
		}

		return $path;
	}

	private function random_ascii_chars( $length = 10 ) {
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$chars_length = strlen( $chars );
		$random_string = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $chars[ rand( 0, $chars_length - 1 ) ];
		}
		return $random_string;
	}

	function log( $data = null ) {
		$enabled = $this->get_option( 'logs', false );
		if ( !$enabled ) { return; }

		$log_file_path = $this->get_logs_path();

		// Check log file size and rotate if too large (1MB limit)
		if ( file_exists( $log_file_path ) ) {
			$file_size = filesize( $log_file_path );
			$max_size = apply_filters( 'mwseo_max_log_size', 1 * 1024 * 1024 ); // 1MB default

			if ( $file_size > $max_size ) {
				@unlink( $log_file_path );
				// Log the rotation event
				$fh = @fopen( $log_file_path, 'w' );
				if ( $fh ) {
					$date = date( "Y-m-d H:i:s" );
					fwrite( $fh, "$date: 🔄 Log file rotated (exceeded " . size_format( $max_size ) . ")\n\n" );
					fclose( $fh );
				}
			}
		}
		
		$fh = @fopen( $log_file_path, 'a' );
		if ( !$fh ) { return false; }
		$date = date( "Y-m-d H:i:s" );
		if ( is_null( $data ) ) {
			fwrite( $fh, "\n" );
		}
		else {
			fwrite( $fh, "$date: {$data}\n" );
			//error_log( "[SEO ENGINE] $data" );
		}
		fclose( $fh );
		return true;
	}

	function get_funny_rejection_message() {
    $messages = [
			"🤖 Human detected! Pretending to be GPTBot? That’s a fun role-reversal!",
			"😂 Nice try, human! Even robots like GPTBot get a day off, courtesy of <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>.",
			"🎭 Humans playing robots? What a twist! GPTBot access: Denied by <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>!",
			"👀 Spotted! A human camouflaging as GPTBot. <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> sees you!",
			"😜 A human mimicking GPTBot? That's some advanced <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> wizardry!",
			"🤣 Oh, human, GPTBot impressions? You’re fun, but <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> says no entry!",
			"🤠 Yeehaw! A wild human pretending to be GPTBot? Not on <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>'s watch!",
			"🕵️‍♂️ Detective human, trying to sneak in as GPTBot? <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> caught ya!",
			"🤪 Human, are you trying to robot? Denied access by the watchful <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>!",
			"🚫 You must be a fun human to mimic GPTBot! But, <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> keeps the door locked.",
			"😅 Humans doing robot things? Not under <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>’s cyber-watch!",
			"🤨 A human in robot’s clothing! Nice try, but <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> is not fooled.",
			"🤔 Hmm, human, your GPTBot disguise needs work. Spotted by <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>!",
			"😆 The human is a robot? This plot twist brought to you by <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>!",
			"🧐 An insightful human trying to GPTBot around! But, <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> is on guard.",
			"🎉 Surprise! Human, you can’t sneak past <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>, even dressed as GPTBot!",
			"🎲 Rolled the dice, human? GPTBot disguise didn’t work on <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>.",
			"🤥 Human, pretending to be GPTBot? <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> knows your secret!",
			"🧛‍♂️ Ah ah ah, human! Even as GPTBot, you can’t bypass the <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a> barrier!",
			"🤓 Nerdy human alert! GPTBot cosplay isn’t fooling <a href='https://wordpress.org/plugins/seo-engine/'>SEO Engine</a>!"
    ];
    return $messages[ array_rand( $messages ) ];
	}


	function reject_gptbot_user_agent()
	{
		$disallow_gpt_bot = $this->get_option( 'disallow_gpt_bot', false );

		if ( $disallow_gpt_bot && isset($_SERVER[ 'HTTP_USER_AGENT' ]) && strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'GPTBot' ) !== false ) {
			$this->log( '🤖 GPTBot has been detected and blocked.');
			header( 'HTTP/1.1 403 Forbidden' );
			exit( $this->get_funny_rejection_message() );
		}
	}

	function get_post_types() {
		global $wpdb;
		$post_types = $wpdb->get_col( "SELECT DISTINCT post_type FROM $wpdb->posts" );
		
		// Exclude post types that don't need SEO
		$excluded_types = array( 'revision', 'wp_global_styles', 'wp_navigation' );
		$post_types = array_diff( $post_types, $excluded_types );
		
		return array_values( $post_types );
	}

	// Make post type list as [ 'post_type_value' => 'post_type_label' ]
	function make_post_type_list( $post_types ) {
		$list = [];
		foreach ( $post_types as $post_type ) {
			$posttype_obj = get_post_type_object( $post_type );
			if ( empty( $posttype_obj ) ) {continue;}
			$label = get_post_type_object( $post_type )->label;
			if ( !$label ) {continue;}

			$list[$post_type] = $label;
		}
		return $list;
	}

	function get_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$taxonomies = array_filter( $taxonomies, function( $taxonomy ) {
			return !in_array( $taxonomy->name, [ 'nav_menu', 'link_category', 'post_format' ] );
		} );
		return $taxonomies;
	}

	function make_taxonomy_list( $taxonomies ) {
		$list = [];
		foreach ( $taxonomies as $taxonomy ) {
			$list[$taxonomy->name] = $taxonomy->label;
		}
		return $list;
	}	

	function check_title_duplicates($title, $id) {
		$duplicate_hashes = get_option( 'mwseo_title_hashes' );
		if ( empty( $duplicate_hashes ) ) {
			$duplicate_hashes = array();
		}
	
		$title_hash = md5( $title );
	
		if ( array_key_exists( $title_hash, $duplicate_hashes ) ) {
			if( $duplicate_hashes[ $title_hash ] == $id ) {
				return false;
			}
			else {
				//verify that the post still exists
				$post = get_post( $duplicate_hashes[ $title_hash ] );
				if( empty( $post ) || $post->post_status == 'trash') {
					unset( $duplicate_hashes[ $title_hash ] );
					update_option( 'mwseo_title_hashes', $duplicate_hashes );
					return false;
				}
				
				return true;
			}
		}
		else {
			$duplicate_hashes[ $title_hash ] = $id;
			update_option( 'mwseo_title_hashes', $duplicate_hashes );
			return false;
		}
	}

	function analyze_on_update( $post_id, $post, $update ) {
		global $mwseo_score;
		if ( $mwseo_score ) {
			$analyze_on_update = $this->get_option( 'analyze_on_update', 'none' );
			// Determine analysis type: 'quick' or 'full'
			$analysis_type = ( $analyze_on_update === 'quick' ) ? 'quick' : 'full';$this->calculate_seo_score( $post, $analysis_type );
		}
	}


	// Decides whether or not the post is SEO-friendly or not.
	function calculate_seo_score( $post, $analysis_type = 'full' ) {
		global $mwseo_score;
		if ( !$mwseo_score ) {
			return [ 'status' => 'error', 'message' => 'Score module not initialized.' ];
		}

		if ( get_post_meta( $post->ID, '_mwseo_status', true ) === 'skip' ) {
			$this->log( "🚫 Post (#{$post->ID}) was skipped." );
			return [ 'status' => 'skip', 'message' => 'Skipped.' ];
		}

		// Clear magic fix markers before re-analysis
		$this->clear_magic_fix_markers( $post->ID );

		// Calculate SEO score with analysis type ('quick' or 'full')
		$result = $mwseo_score->calculate( $post, $analysis_type );

		// Save results
		update_post_meta( $post->ID, '_mwseo_analysis', $result );

		// Also save denormalized meta for sorting/filtering
		update_post_meta( $post->ID, '_mwseo_overall', $result['overall'] );
		// Note: cq and tech are legacy pillar scores, removed in v3
		update_post_meta( $post->ID, '_mwseo_updated', time() );

		// Maintain backward compatibility with old meta keys
		$status = $this->get_status_from_score( $result );
		$message = $this->get_message_from_score( $result );
		$codes = $this->get_codes_from_score( $result );

		update_post_meta( $post->ID, '_mwseo_score', $result['overall'] );
		update_post_meta( $post->ID, '_mwseo_status', $status );
		update_post_meta( $post->ID, '_mwseo_message', $message );
		update_post_meta( $post->ID, '_mwseo_codes', $codes );

		return [
			'score' => $result['overall'],
			'status' => $status,
			'message' => $message,
			'errors' => [ 'codes' => $codes ],
			'data' => $result
		];
	}

	// Convert score results to legacy status
	private function get_status_from_score( $result ) {
		$overall = $result['overall'];

		if ( $overall >= 70 ) return 'ok';
		if ( $overall >= 40 ) return 'error'; // Could improve
		return 'error'; // Needs attention
	}

	// Convert score results to legacy message
	private function get_message_from_score( $result ) {
		$overall = $result['overall'];

		if ( $overall >= 70 ) {
			$emoji = '🟢';
			$label = __( 'Great', 'seo-engine' );
		} elseif ( $overall >= 40 ) {
			$emoji = '🟠';
			$label = __( 'Could improve', 'seo-engine' );
		} else {
			$emoji = '🔴';
			$label = __( 'Needs attention', 'seo-engine' );
		}

		$message = sprintf( '%s %s (%d%%)', $emoji, $label, $overall );

		if ( !empty( $result['ai']['summary'] ) ) {
			$message .= "\n" . sprintf( __( 'How AI reads this: "%s"', 'seo-engine' ), $result['ai']['summary'] );
		}

		return $message;
	}

	// Extract error codes from score tests
	private function get_codes_from_score( $result ) {
		$codes = [];

		foreach ( $result['tests'] as $test_name => $score ) {
			if ( $score === 'NA' ) continue;
			if ( $score < 70 ) {
				$codes[] = $test_name;
			}
		}

		return $codes;
	}

	function get_all_posts_with_seo_score() {

		// $show_unscored_posts = $this->get_option( 'show_unscored_chart', false );
		// $show_any_post_type  = $this->get_option( 'show_any_post_type',  false );

		$show_any_post_type  = false;
		$show_unscored_posts = true;

		$meta_query = $show_unscored_posts ? [] : [ 'key' => '_mwseo_score', 'compare' => 'EXISTS' ];
		$default_post_type = $this->get_option( 'default_post_type', 'post' );
		$post_type = $show_any_post_type ? 'any' : $default_post_type;

		// When 'any' is selected, use only the enabled post types from settings
		if ( $post_type === 'any' ) {
			$post_type = $this->get_option( 'select_post_types', ['post', 'page'] );
		}

		// PERFORMANCE FIX: Allow limiting posts for very large sites
		// Default to 10000 max to prevent memory issues
		$max_posts = apply_filters( 'seo_engine_max_chart_posts', 10000 );

		$args = array(
			'post_type' => $post_type,
			'posts_per_page' => $max_posts,
			'meta_query' => array(
				$meta_query,
			),
		);

		$posts = get_posts( $args );

		// PERFORMANCE FIX: Prime meta cache to avoid N+1 queries
		if (!empty($posts)) {
			$post_ids = wp_list_pluck($posts, 'ID');
			update_meta_cache('post', $post_ids);
		}

		$posts_with_seo_score = array();

		foreach ( $posts as $post ) {
			$seo_score = get_post_meta( $post->ID, '_mwseo_score', true );
			$seo_status = get_post_meta( $post->ID, '_mwseo_status', true );


			$posts_with_seo_score[] = array(
				'id' => $post->ID,
				'title' => $post->post_title,
				'score' => $seo_score,
				'status' => $seo_status,
				'category' => $this->get_seo_category( $seo_score ),
			);
		}

		return $posts_with_seo_score;
	}

	private function get_seo_category ( $score ) {

		if ( $score >= 0 && $score < 25 ) {
			return 'Terrible';
		}
		else if ( $score >= 25 && $score < 50 ) {
			return 'Poor';
		}
		else if ( $score >= 50 && $score < 75 ) {
			return 'Acceptable';
		}
		else if ( $score >= 75 && $score < 90 ) {
			return 'Great';
		}
		else if ( $score >= 90 && $score <= 100 ) {
			return 'Excellent';
		}
		else {
			return 'Unknown';
		}
	}

	function get_seo_engine_post_meta( $post ) {

		$post_id = $post->ID;

		$score = get_post_meta( $post_id, '_mwseo_score', true );
		$status = get_post_meta( $post_id, '_mwseo_status', true );
		$message = get_post_meta( $post_id, '_mwseo_message', true );
		$codes = get_post_meta( $post_id, '_mwseo_codes', true );

		if( !$status ) { $status = 'pending'; }
		if( !$message ) { $message = 'Start an analysis to get a score.'; }

		return [
			'score' => $score,
			'status' => $status,
			'message' => $message,
			'codes' => $codes,
		];
	}

	

	function build_excerpt( $post, $excerpt = "" ) {
		if ( is_category() ) {
			$excerpt = category_description();
		}
		else if ( is_tag() ) {
			$excerpt = tag_description();
		}
		else if ( is_author() ) {
			$excerpt = get_the_author_meta( 'description' );
		}
		else if ( is_search() ) {
			$excerpt = get_search_query();
		}
		else if ( is_404() ) {
			$excerpt = '404';
		}
		else if ( is_home() ) {
			$excerpt = get_the_excerpt( get_option( 'page_for_posts' ) );
		}
		else if ( is_archive() ) {
			$excerpt = get_the_archive_description();
		}
		else if ( !empty( $post ) ) {
			$seo_excerpt = get_post_meta( $post->ID, $this->meta_key_seo_excerpt, true );
			$excerpt = !empty( $seo_excerpt ) ? $seo_excerpt : get_the_excerpt( $post->ID );
		}
		$excerpt = html_entity_decode( $excerpt );
		return $excerpt;
	}

	function build_title( $post, $title = "" ) {
		$override = false;
		if ( is_category() ) {
			$title = single_cat_title( '', false );
		}
		else if ( is_tag() ) {
			$title = single_tag_title( '', false );
		}
		else if ( is_author() ) {
			$title = get_the_author();
		}
		else if ( is_search() ) {
			$title = get_search_query();
		}
		else if ( is_404() ) {
			$title = '404';
		}
		else if ( is_home() ) {
			$title = get_the_title( get_option( 'page_for_posts' ) );
		}
		else if ( is_archive() ) {
			$title = get_the_archive_title();
		}
		else if ( !empty( $post ) ) {
			$seo_title = get_post_meta( $post->ID, $this->meta_key_seo_title, true );
			if ( !empty( $seo_title ) ) {
				$override = true;
				$title = $seo_title;
			}
			else {
				$title = get_the_title( $post->ID );
			}
		}
		$title = trim( strip_tags( $title ) );
		if ( !$override ) {
			$title = $title . " | " . trim( get_bloginfo( 'name' ) );
		}
		$title = html_entity_decode( $title );
		return $title;
	}

	function render_title( $title ) {
		global $post;
		$title = $this->build_title( $post, $title );
		return esc_html( $title );
	}

	function render_description() {
		global $post;
		$excerpt = $this->build_excerpt( $post );
		$excerpt = trim( strip_tags( $excerpt ) );
		echo '<meta class="mwseo-meta" name="description" content="' . esc_html( $excerpt ) . '" />';
	}

	function get_images_from_content( $content ) {
		if (empty($content)) {
			return array();
		}

		$dom = new DOMDocument;
		$loadHTML = @$dom->loadHTML($content);

		if (!$loadHTML) {
			throw new Exception('Failed to load HTML content');
		}

		$images = array();

		$images_html = array();
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$src = $img->getAttribute( 'src' );
			$alt = $img->getAttribute( 'alt' );

			if ( $src && filter_var( $src, FILTER_VALIDATE_URL ) ) {
				$images_html[] = array(
					'src' => preg_replace( '~-[0-9]+x[0-9]+.~', '.', $src ),
					'alt' => $alt ? $alt : '',
				);
			}
		}

		$images_shortcode = array();
		$pattern = get_shortcode_regex();
		preg_match_all( '/'. $pattern .'/s', $content, $matches );
		if ( !empty( $matches[2] ) ) {
			foreach ( $matches[2] as $key => $shortcode ) {
				if ( $shortcode == 'gallery' || $shortcode == 'meow-gallery' ) {
					$atts = shortcode_parse_atts( $matches[3][$key] );
					if (isset($atts['ids'])) {
						$ids = explode( ',', $atts['ids'] );
						foreach ( $ids as $id ) {
							if (is_numeric($id)) {
								$src = wp_get_attachment_url( $id );
								$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );
								if ($src && filter_var($src, FILTER_VALIDATE_URL)) {

									if( in_array( $src, array_column( $images_shortcode, 'src' ) ) ) {
										continue;
									}

									$images_shortcode[] = array(
										'src' => $src,
										'alt' => $alt ? $alt : '',
									);
								}
							}
						}
					}
				}
			}
		}

		$images = array_merge( $images_html, $images_shortcode );

		return $images;
	}

	function get_images_from_content_with_above_paragraph( $img_src_array, $content ) {
		$images = [];

		//Get all the text before the img src
		foreach ( $img_src_array as $img_src ) {

			$before_img = substr( $content, 0, strpos( $content, $img_src ) );
			$before_img = substr( $before_img, strrpos( $before_img, '<p>' ) + 3 );
			$before_img = strip_tags( $before_img );
			$before_img = preg_replace( '/\s+/', ' ', $before_img );
			$before_img = trim( $before_img );
			
			$images[] = array(
				'src' => $img_src,
				'alt' => $before_img,
				'text' => $before_img,
			);
		}
		

		return $images;
	}

	function get_live_content( $post, $strip_html = true ) {
		$post_url = get_permalink( $post->ID );
		if ( ! $post_url ) {
			$this->log( "⚠️ Could not get permalink for post ID: " . $post->ID );
			return $post->post_content;
		}
		
		$response = wp_remote_get( $post_url, array(
			'timeout' => 10,
			'user-agent' => 'SEO Engine Live Content Checker'
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log( "⚠️ Failed to fetch live content for post ID: " . $post->ID . ". Error: " . $response->get_error_message() );
			return $post->post_content;
		}
		
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$this->log( "⚠️ Failed to fetch live content for post ID: " . $post->ID . ". HTTP Status: " . wp_remote_retrieve_response_code( $response ) );
			return $post->post_content;
		}
		
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			$this->log( "⚠️ Empty response body for post ID: " . $post->ID );
			return $post->post_content;
		}
		
		// Strip HTML tags and get raw text content
		if ( $strip_html ) {
			$body = wp_strip_all_tags( $body );
		}

		$content = $body;
		// Clean up extra whitespace
		$content = preg_replace( '/\s+/', ' ', trim( $content ) );
		
		$this->log( "✅ Live content fetched successfully for post ID: " . $post->ID );
		return $content;
	}

	function check_images_alt_text( $post ) {

		$check_live_content = $this->get_option( 'check_live_content', false );
		$content = $check_live_content ? $this->get_live_content( $post, false ) : $post->post_content;

		$images = $this->get_images_from_content( $content );
		if ( !is_array( $images ) ) {
			return array();
		}

		$missing_alt_text = array();
		$image_meta_data = $this->get_all_image_meta_data($images);

		foreach ( $images as $image ) {
			if ( empty( $image[ 'alt' ] ) ) {
				$attachment_id = attachment_url_to_postid( $image[ 'src' ] );
				$meta_alt_text = $image_meta_data[$attachment_id];
				if ( !empty( $meta_alt_text ) ) {
					$this->log( sprintf( "🖼️ Image (src : %s) has alt text : %s", $image[ 'src' ], $meta_alt_text ) );
					continue;
				}
				$missing_alt_text[] = $image[ 'src' ];
			}
		}

		return $missing_alt_text;
	}

	function get_all_image_meta_data($images) {
		$meta_data = array();
		foreach ($images as $image) {
			$attachment_id = attachment_url_to_postid( $image[ 'src' ] );
			$meta_data[$attachment_id] = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		}
		return $meta_data;
	}


	function check_links( $content ){

		$links = array();
		$external_links = array();
		$internal_links = array();

		if ( preg_match_all( '/<a[^>]+>/i', $content, $matches ) ) {
			foreach ( $matches[0] as $link ) {
				if ( preg_match( '/href="http[s]?:\/\/([^"]+)"/i', $link, $href ) ) {
					$external_links[] = $href[ 1 ];
				}elseif ( preg_match( '/href="([^"]+)"/i', $link, $href ) ) {
					$internal_links[] = $href[ 1 ];
				}
			}
		}

		//TODO: Display the links somewhere, for now just the count

		$links[ 'external' ] = count( $external_links );
		$links[ 'internal' ] = count( $internal_links );
		$links[ 'total' ] = $links[ 'external' ] + $links[ 'internal' ];

		return $links;
	}


	/**
	 * Generate AI solution for a specific issue - Backward compatibility wrapper
	 *
	 * @deprecated Use $core->pro->magic_fix->generate_solution() instead
	 */
	public function magic_fix_generate_solution( $post, $issue_type ) {
		if ( $this->pro && $this->pro->magic_fix ) {
			return $this->pro->magic_fix->generate_solution( $post, $issue_type );
		}
		$this->log( '⚠️ Magic Fix is not available. This is a premium feature.' );
		return false;
	}

	/**
	 * Apply a generated solution to a post - Backward compatibility wrapper
	 *
	 * @deprecated Use $core->pro->magic_fix->apply_solution() instead
	 */
	public function magic_fix_apply_solution( $post, $issue_type, $solution ) {
		if ( $this->pro && $this->pro->magic_fix ) {
			return $this->pro->magic_fix->apply_solution( $post, $issue_type, $solution );
		}
		$this->log( '⚠️ Magic Fix is not available. This is a premium feature.' );
		return false;
	}

	/**
	 * Mark an issue as fixed by Magic Fix - Backward compatibility wrapper
	 *
	 * @deprecated Use $core->pro->magic_fix->mark_as_fixed() instead
	 */
	public function mark_issue_as_magic_fixed( $post_id, $issue_type ) {
		if ( $this->pro && $this->pro->magic_fix ) {
			return $this->pro->magic_fix->mark_as_fixed( $post_id, $issue_type );
		}
	}

	/**
	 * Check if an issue has been marked as fixed - Backward compatibility wrapper
	 *
	 * @deprecated Use $core->pro->magic_fix->is_fixed() instead
	 */
	public function is_issue_magic_fixed( $post_id, $issue_type ) {
		if ( $this->pro && $this->pro->magic_fix ) {
			return $this->pro->magic_fix->is_fixed( $post_id, $issue_type );
		}
		return false;
	}

	/**
	 * Clear all magic fix markers for a post - Backward compatibility wrapper
	 *
	 * @deprecated Use $core->pro->magic_fix->clear_markers() instead
	 */
	public function clear_magic_fix_markers( $post_id ) {
		if ( $this->pro && $this->pro->magic_fix ) {
			return $this->pro->magic_fix->clear_markers( $post_id );
		}
	}

	/**
	 * Migrate old meta keys to new mwseo prefixed keys
	 * This migration runs once per post when the post is accessed/analyzed
	 */
	public function migrate_post_meta_keys( $post_id ) {
		// Mapping of old keys to new keys
		$meta_keys_map = [
			'_seo_engine_data' => '_mwseo_analysis',
			'_seo_engine_ignored_tests' => '_mwseo_ignored_tests',
			'_seo_engine_overall' => '_mwseo_overall',
			'_seo_engine_updated' => '_mwseo_updated',
			'_seo_engine_ai_keywords' => '_mwseo_keywords',
			'_seo_title' => '_mwseo_title',
			'_seo_excerpt' => '_mwseo_excerpt',
			'_seo_robots' => '_mwseo_robots',
			'_seo_canonical' => '_mwseo_canonical',
			'_seo_status' => '_mwseo_status',
			'_seo_message' => '_mwseo_message',
			'_seo_score' => '_mwseo_score',
			'_seo_codes' => '_mwseo_codes',
		];

		foreach ( $meta_keys_map as $old_key => $new_key ) {
			$value = get_post_meta( $post_id, $old_key, true );
			if ( $value !== '' && $value !== false ) {
				// Copy to new key
				update_post_meta( $post_id, $new_key, $value );
				// Delete old key
				delete_post_meta( $post_id, $old_key );
			}
		}
	}

	/**
	 *
	 * Roles & Access Rights
	 *
	 */
	function can_access_settings() {
		return apply_filters( 'mwseo_allow_setup', current_user_can( 'manage_options' ) );
	}

	function can_access_features() {
		return apply_filters( 'mwseo_allow_usage', current_user_can( 'administrator' ) );
	}

	#region Options

	function get_option( $option, $default = null ) {
		$options = $this->get_all_options();
		return $options[$option] ?? $default;
	}

	function sanitized_options(){
		// This is a READ-ONLY method - it computes derived values but NEVER writes to the database
		$options = $this->get_all_options();

		$options['post_types'] = $this->make_post_type_list( $this->get_post_types() );

		if ( $options['language'] == 'auto' || empty( $options['language'] ) ) {
			$options['language'] = $this->get_language_name( get_locale() ) ?? 'English';
		}

		if ( empty( $options['readability_treshold'] ) ) {
			$options['readability_treshold'] = 50;
		}

		// Always force analyze_buffer to 1 - analyzing multiple posts at once causes performance issues
		$options['analyze_buffer'] = 1;

		// AI Engine - check status but DON'T modify stored options
		global $mwai;

		if( is_null( $mwai ) || !isset( $mwai ) ) {
			$options['ai_engine_status'] = false;
			$options['ai_engine_message'] = 'AI Engine is not available.';
		}
		else {

			try{
				$options['ai_engine_status'] = true;
				$options['ai_engine_message'] = $mwai->checkStatus();
			}
			catch ( Exception $e ) {
				$options['ai_engine_status'] = false;
				$options['ai_engine_message'] = $e->getMessage();
			}
		}

		// AI Features - Only disable in the returned array for UI purposes
		// DO NOT modify the stored user preferences
		if ( !$options['ai_engine_status'] ) {
			// Show features as disabled in UI without changing user's saved preferences
			// These are temporary overrides for the current request only
			$options['ai_magic_fix'] = false;
			$options['ai_auto_correct'] = false;
			$options['ai_magic_wand'] = false;
			$options['ai_web_scraping'] = false;
			$options['ai_keywords'] = false;
			$options['woocommerce_assistant'] = false;
		}

		// Return sanitized options WITHOUT persisting to database
		return $options;
	}

	// TODO: Review which options are still in use after meta key refactoring - many may be obsolete
	function list_options() {
		return array(
			// Module Toggles
			'content_seo' => true,
			'technical_seo' => true,

			'speed_and_vitals' => false,
			'analytics' => true,
			
			// Content SEO
			'default_post_type' => 'post',
			'posts_limit' => 10,
			'posts_sort' => array( 'accessor' => 'score', 'by' => 'asc' ),
			'posts_filter' => 'all',
			'post_types' => $this->make_post_type_list( $this->get_post_types() ),
			'select_post_types' => ['post', 'page'],
			'readability_treshold' => 50,
			'language' => $this->get_language_name( get_locale() ) ?? 'English',
			'analyze_on_update' => 'none',
			'analyze_buffer' => 1,
			'analyze_buffer_interval' => 0,
			
			// Technical SEO
			'sitemap' => false,
			'disable_wp_sitemap' => false,
			'sitemap_custom' => false,
			'sitemap_style' => 'default',
			'sitemap_path' => '',
			'sitemap_exclude_users_provider' => false,
			'sitemap_exclude_posts_provider' => false,
			'sitemap_exclude_taxonomies_provider' => false,
			'sitemap_excluded_post_types' => [],
			'sitemap_excluded_taxonomies' => [],
			'sitemap_excluded_post_ids' => [],
			'sitemap_max_urls' => 100,
			'sitemap_post_max_pages' => 100,
			'taxonomies' => $this->make_taxonomy_list( $this->get_taxonomies() ),
			'robot_editor' => false,
			'disallow_gpt_bot' => false,
			
			// Social Networks
			'social_networks' => false,
			'social_networks_twitter' => '',
			'social_networks_facebook_app_id' => '',
			
			// Search Visibility
			'google_ranking' => false,
			
			// Google Cloud / API
			'google_api_key' => '',
			'google_programmable_search_engine' => '',
			'google_interval_hours' => 24,
			'google_search_depth' => 3,
			'google_track_points' => 60,
			
			// Analytics Configuration
			'analytics_method' => 'none', // 'none', 'private', 'google'
			
			// Analytics Options
			'bots_track' => true,
			'bots_instructions' => false,
			'bots_instructions_content' => '',
			// Private Analytics
			'general_analytics' => false,
			'analytics_track_logged_users' => false,
			'analytics_track_power_users' => false,
			'analytics_privacy' => false,
			
			// Google Analytics
			'google_analytics_cache' => true,
			'google_analytics' => false,
			'google_analytics_property_ids' => [],
			'google_analytics_property_id' => '',
			'google_analytics_client_secret' => '',
			'google_analytics_client_id' => '',
			'google_analytics_tracking_ids' => [],
			'google_analytics_track_logged_users' => false,
			'google_analytics_track_power_users' => false,
			'google_analytics_tracking_disabled' => false,
			
			// AI Features
			'ai_engine_status' => false,
			'ai_engine_message' => '',
			'ai_magic_fix' => false,
			'ai_auto_correct' => false,
			'ai_magic_wand' => false,
			'ai_web_scraping' => false,
			'ai_keywords' => false,
			'ai_semantic_analysis' => true, // Enable AI semantic analysis (requires AI Engine)
			'full_analysis' => true, // Enable Full Analysis with Intelligence checks
			'daily_insights' => true,
			'woocommerce_assistant' => false,
			'select_magic_fix' => [
				'readability_score',
			],

			// Scoring Preferences
			'pillar_weight_cq' => 50, // Content Quality weight (0-100)
			'pillar_weight_tech' => 50, // Technical weight (0-100)
			'quality_safeguards_enabled' => true,
			'use_seo_metadata' => true,
			'show_ai_summary' => true,
			'row_density' => 'cozy', // compact | cozy

			// Content Quality - Basic
			'use_excerpt_as_meta' => true,
			'aim_easy_reading' => true,
			'encourage_image_alt' => true,
			'ai_detect_intent' => true,
			'short_strong_mode' => true,

			// Content Quality - Advanced
			'readability_flesch_min' => 50,
			'readability_flesch_max' => 70,
			'readability_grade_min' => 7,
			'readability_grade_max' => 10,
			'short_strong_sensitivity' => 'normal', // normal | strict | off
			'show_author_visible' => true,
			'link_to_author_page' => true,
			'intent_schema_coherence' => true,

			// Technical - Basic
			'require_unique_title' => true,
			'encourage_short_slug' => true,
			'encourage_internal_links' => true,
			'check_internal_links' => true,
			'check_missing_alt_text' => true,
			'check_orphaned_content' => true,
			'auto_schema_enabled' => true,

			// Length Guidelines
			'title_length_min' => 30,
			'title_length_max' => 75,
			'excerpt_length_min' => 80,
			'excerpt_length_max' => 160,
			'content_length_min' => 300,
			'content_length_max' => 1600,

			// Technical - Advanced
			'slug_length_max' => 60,
			'slug_words_min' => 2,
			'slug_words_max' => 6,
			'smart_internal_links' => true,
			'check_external_links' => false,
			'schema_by_post_type' => [
				'post' => 'Article',
				'page' => 'WebPage',
				'product' => 'Product',
			],

			// Content Comfort Zones
			'comfort_zone_quick_min' => 150,
			'comfort_zone_quick_max' => 500,
			'comfort_zone_guide_min' => 700,
			'comfort_zone_guide_max' => 1600,
			'comfort_zone_product_min' => 300,
			'comfort_zone_product_max' => 900,

			// Legacy Score Factors (kept for compatibility)
			'score_check_title_exists' => true,
			'score_check_title_unique' => true,
			'score_check_title_length' => true,
			'score_check_excerpt_exists' => true,
			'score_check_excerpt_length' => true,
			'score_check_slug_length' => true,
			'score_check_slug_words' => true,
			'score_check_content_length' => true,
			'score_check_images_alt' => true,
			'score_check_links' => true,
			'score_check_readability' => true,
			
			// Maintenance & Others
			'logs' => false,
			'hide_dashboard_message' => false,
			'mcp_support' => false,
		);
	}

	// TODO: Delete after January 2026
	function migrate_option_names( $options ) {
		// Create a new array with migrated keys
		$migrated_options = array();
		
		foreach ( $options as $key => $value ) {
			// Check if the key starts with 'seo_engine_'
			if ( strpos( $key, 'seo_engine_' ) === 0 ) {
				// Remove 'seo_engine_' prefix
				$new_key = substr( $key, 11 );
				$migrated_options[$new_key] = $value;
			} else {
				// Keep the key as is if it doesn't have the prefix
				$migrated_options[$key] = $value;
			}
		}
		
		// Save the migrated options
		update_option( $this->option_name, $migrated_options, false );
		
		// Also migrate WordPress native options
		$this->migrate_native_options();
		
		return $migrated_options;
	}

	// TODO: Delete after January 2026
	function migrate_native_options() {
		// Migrate title hashes
		$old_title_hashes = get_option( 'seo_engine_title_hashes' );
		if ( $old_title_hashes !== false ) {
			update_option( 'mwseo_title_hashes', $old_title_hashes );
			delete_option( 'seo_engine_title_hashes' );
		}
		
		// Migrate searches (from premium module)
		$old_searches = get_option( 'seo_engine_searches' );
		if ( $old_searches !== false ) {
			update_option( 'mwseo_searches', $old_searches );
			delete_option( 'seo_engine_searches' );
		}
		
		// Add any other native options that need migration here
	}

	function get_all_options() {

		// TODO: Delete after January 2026
		$old_options = get_option( $this->old_option_name, null );
		if ( $old_options ) {
			// Migrate old options to new option name
			update_option( $this->option_name, $old_options, false );
			delete_option( $this->old_option_name );

			$options = $old_options;
		} else {
			$options = get_option( $this->option_name, null );
		}
		
		// Check if we need to migrate from old option names
		if ( $options && isset( $options['default_post_type'] ) ) {
			$options = $this->migrate_option_names( $options );
		}
		
		// Make sure every option is set
		$options = wp_parse_args( $options, $this->list_options() );
		return $options;
	}

	function update_options( $options ) {
		if ( $options == get_option( $this->option_name ) ) {
			return $options;
		}

		if ( !update_option( $this->option_name, $options, false ) ) {
			return false;
		}

		$options = $this->sanitized_options();

		return $options;
	}

	function update_option( $option, $value ) {
		$options = $this->get_all_options();
		$options[$option] = $value;
		return $this->update_options( $options );
	}

	function reset_options() {
		return $this->update_options( $this->list_options() );
	}

	#endregion

	#region WooCommerce

	function generate_woocommerce_fields( $params )
	{
		global $mwai;

		if ( is_null( $mwai ) || !isset( $mwai ) ) {
			throw new Exception( 'AI Engine is not available.' );
		}

		$product_id = $params['post_id'];

		// We use the autosave that we force from the editor using AJAX so we get the actual content the user is editing
		$last_product_autosave = wp_get_post_autosave( $product_id );
		$product = $last_product_autosave ? $last_product_autosave : get_post( $product_id );

		$product_data = [
			'id' => $product_id,
			'title' => $product->post_title,
			'description' => $product->post_content,
			'short_description' => $product->post_excerpt,
			'categories' => wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) ),
			'tags' => wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) ),
			'attributes' => wc_get_product( $product_id )->get_attributes(),
			'price' => wc_get_product( $product_id )->get_price(),
		];


		// Use Vision if enabled
		$use_vision = $params['vision'];
		$vision_result = null;
		if ( $use_vision ) {
			$product_image = get_post_meta( $product_id, '_thumbnail_id', true );
			if ( $product_image ) {

				$prompt = "Describe the main elements in the image, the colors, the style, the context, and any text that is visible. Write it in {LANGUAGE}.";

				$intermediate = image_get_intermediate_size( $product_id, 'medium' );
				$path = $intermediate 
					? path_join( dirname( get_attached_file( $product_id ) ), $intermediate['file'] ) 
					: get_attached_file( $product_id );
				$url = $intermediate 
					? wp_get_attachment_image_url( $product_id, 'medium' ) 
					: wp_get_attachment_url( $product_id );


				$binary =  !empty( $path ) && file_exists( $path ) ? file_get_contents( $path ) : null;

				if ( !$binary ) {
					$vision_result = $mwai->simpleVisionQuery( $prompt, $url, $path, [ 'scope' => 'seo' ] );
				} else {
					$vision_result = $mwai->simpleVisionQuery( $prompt, null, $binary, [ 'scope' => 'seo' ] );
				}
			}
		}
		

		$prompt  = "Based on the product, write a description of this product (between 120 and 240 words), a short description (between 20-49 words), a SEO-friendly title, and tags (separated by commas). SEO and sales focused. Write it in {LANGUAGE}.";

		$prompt .= "\n\nHere are the user provided details about the product: {PRODUCT}.";

		$prompt .= "\n\nHere are the details extracted from the product data: " . json_encode( $product_data );

		if ( $vision_result ) {
			$prompt .= "\n\nAlso, here is what is seen in the product image: " . $vision_result;
		}

		$prompt  = apply_filters( 'mwseo_woo_product_prompt', $prompt );
		$prompt .= "Use this keys: description, short_description, seo_title, tags.";

		$prompt = str_replace( '{PRODUCT}',  $params['product'],  $prompt );
		$prompt = str_replace( '{LANGUAGE}', $params['language'], $prompt );

		$response = $mwai->simpleJsonQuery( $prompt );;
		
		return $response;
	}

	#endregion

	#region Utilities

	function import_yoast() {
		// For all the selected post types, get the _yoast_wpseo_title & _yoast_wpseo_metadesc	and import them to _kiss_seo_title & _kiss_seo_excerpt
		// TODO: Migrate to _mwseo_title & _mwseo_excerpt
		$post_types = $this->get_option( 'select_post_types', array( 'post ') );
		$posts = get_posts( array(
			'post_type' => $post_types,
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_yoast_wpseo_title',
					'compare' => 'EXISTS',
				),
				array(
					'key' => '_yoast_wpseo_metadesc',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $posts as $post ) {
			$yoast_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
			$yoast_excerpt = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
			$yoast_keywords = get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true );

			if ( !empty( $yoast_title ) ) {
				update_post_meta( $post->ID, $this->meta_key_seo_title, $yoast_title );
			}
			if ( !empty( $yoast_excerpt ) ) {
				update_post_meta( $post->ID, $this->meta_key_seo_excerpt, $yoast_excerpt );
			}
			if ( !empty( $yoast_keywords ) ) {
				$keywords = explode( ',', $yoast_keywords );
				$keywords = array_slice( $keywords, 0, 5 );
				
				update_post_meta( $post->ID, $this->meta_key_seo_keywords, $keywords );
			}
		}

		return count( $posts );
	}

	function get_seo_title( $post ) {
		$seo_title = get_post_meta( $post->ID, $this->meta_key_seo_title, true );
		if ( empty( $seo_title ) ) {
			$seo_title = $this->build_title( $post );
		}
		return $seo_title;
	}

	function set_seo_title( $post, $title ) {
		if ( empty( $title ) ) {
			return false;
		}
		$title = trim( strip_tags( $title ) );
		if ( empty( $title ) ) {
			return false;
		}
		update_post_meta( $post->ID, $this->meta_key_seo_title, $title );
		return true;
	}

	function get_seo_excerpt( $post ) {
		$seo_excerpt = get_post_meta( $post->ID, $this->meta_key_seo_excerpt, true );
		if ( empty( $seo_excerpt ) ) {
			$seo_excerpt = $this->build_excerpt( $post );
		}
		return $seo_excerpt;
	}

	function set_seo_excerpt( $post, $excerpt ) {
		if ( empty( $excerpt ) ) {
			return false;
		}
		$excerpt = trim( strip_tags( $excerpt ) );
		if ( empty( $excerpt ) ) {
			return false;
		}
		update_post_meta( $post->ID, $this->meta_key_seo_excerpt, $excerpt );
		return true;
	}

	/**
	 * Calculate effective display width of text for SERP.
	 * CJK characters (Chinese, Japanese, Korean) are roughly twice as wide as Latin characters
	 * in Google's search results, so they count as 2 units. This approximates the pixel-based
	 * measurement that Google actually uses for title/description truncation.
	 *
	 * @param string $text The text to measure
	 * @return int Display width in units (Latin chars = 1, CJK chars = 2)
	 */
	function get_display_width( $text ) {
		if ( empty( $text ) ) {
			return 0;
		}
		$width = 0;
		// Split into individual characters (UTF-8 safe)
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $chars as $char ) {
			// CJK Unified Ideographs, Extension A, Hiragana, Katakana, Hangul Syllables
			if ( preg_match( '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $char ) ) {
				$width += 2;
			} else {
				$width += 1;
			}
		}
		return $width;
	}

	/**
	 * Check if text is predominantly CJK characters.
	 *
	 * @param string $text The text to check
	 * @param float $threshold Ratio threshold (default 0.5 = 50% CJK)
	 * @return bool True if text meets or exceeds CJK threshold
	 */
	function is_mostly_cjk( $text, $threshold = 0.5 ) {
		if ( empty( $text ) ) {
			return false;
		}
		$text_length = mb_strlen( $text, 'UTF-8' );
		if ( $text_length === 0 ) {
			return false;
		}
		// Count CJK characters
		preg_match_all( '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $text, $matches );
		$cjk_count = count( $matches[0] );
		return ( $cjk_count / $text_length ) >= $threshold;
	}

	function import_rank_math() {
		// For all the selected post types, get the _rank_math_title & _rank_math_description and import them to _kiss_seo_title & _kiss_seo_excerpt
		// TODO: Migrate to _mwseo_title & _mwseo_excerpt
		$post_types = $this->get_option( 'select_post_types', array( 'post ') );
		$posts = get_posts( array(
			'post_type' => $post_types,
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => 'rank_math_title',
					'compare' => 'EXISTS',
				),
				array(
					'key' => 'rank_math_description',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $posts as $post ) {

			$rank_math_title = get_post_meta( $post->ID, 'rank_math_title', true );
			$rank_math_excerpt = get_post_meta( $post->ID, 'rank_math_description', true );
			$rank_math_keywords = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );

			if ( !empty( $rank_math_title ) ) {
				update_post_meta( $post->ID, $this->meta_key_seo_title, $rank_math_title );
			}
			if ( !empty( $rank_math_excerpt ) ) {
				update_post_meta( $post->ID, $this->meta_key_seo_excerpt, $rank_math_excerpt );
			}
			if ( !empty( $rank_math_keywords ) ) {
				$keywords = explode( ',', $rank_math_keywords );
				$keywords = array_slice( $keywords, 0, 5 );
				
				update_post_meta( $post->ID, $this->meta_key_seo_keywords, $keywords );
			}
		}

		return count( $posts );
	}

	function add_wc_meta_boxes()
	{
		if (get_post_type() !== 'product') {
			return;
		}

		add_meta_box(
			'mwseo-metadata',
			__('SEO Engine', MWSEO_PREFIX),
			array($this, 'render_wc_metadata_metabox'),
			'product',
			'side',
			'high'
		);
	}

	function render_wc_metadata_metabox()
	{
		echo '<div id="mwseo-admin-wc-assistant"></div>';
	}

	function get_language_name( $locale ) {
		$locale_name_array = [
			'en_US' => 'English (US)',
			'en_GB' => 'English (UK)',
			'en_AU' => 'English (Australia)',
			'en_CA' => 'English (Canada)',
			'fr_FR' => 'French',
			'fr_BE' => 'French (Belgium)',
			'fr_CA' => 'French (Canada)',
			'de_DE' => 'German',
			'de_CH' => 'German (Switzerland)',
			'de_AT' => 'German (Austria)',
			'it_IT' => 'Italian',
			'pt_PT' => 'Portuguese',
			'pt_BR' => 'Portuguese (Brazil)', 
			'es_ES' => 'Spanish',
			'es_MX' => 'Spanish (Mexico)',
			'es_GT' => 'Spanish (Guatemala)',
			'es_CL' => 'Spanish (Chile)',
			'es_AR' => 'Spanish (Argentina)',
			'es_CO' => 'Spanish (Colombia)',
			'es_PE' => 'Spanish (Peru)',
			'es_VE' => 'Spanish (Venezuela)',
			'nl_NL' => 'Dutch',
			'nl_BE' => 'Dutch (Belgium)',
			'ru_RU' => 'Russian',
			'pl_PL' => 'Polish',
			'ja' => 'Japanese',
			'zh_CN' => 'Chinese (China)',
			'zh_TW' => 'Chinese (Taiwan)',
			'ko_KR' => 'Korean',
			'ar' => 'Arabic',
			'he_IL' => 'Hebrew',
			'id_ID' => 'Indonesian',
			'ms_MY' => 'Malay',
			'th' => 'Thai',
			'vi' => 'Vietnamese',
			'tr_TR' => 'Turkish',
			'bg_BG' => 'Bulgarian',
			'el' => 'Greek',
			'da_DK' => 'Danish',
			'fa_IR' => 'Persian',
			'fi' => 'Finnish',
			'hi_IN' => 'Hindi',
			'hr' => 'Croatian',
			'hu_HU' => 'Hungarian',
			'nb_NO' => 'Norwegian (Bokmål)',
			'ro_RO' => 'Romanian',
			'sl_SI' => 'Slovenian',
			'sv_SE' => 'Swedish',
			'uk' => 'Ukrainian',
			'cs_CZ' => 'Czech',
			'sk_SK' => 'Slovak',
			'lt_LT' => 'Lithuanian',
			'mk_MK' => 'Macedonian',
			'mg_MG' => 'Malagasy',
			'ta_IN' => 'Tamil',
			'tl' => 'Tagalog',
			'az_AZ' => 'Azerbaijani',
			'az_TR' => 'Azerbaijani (Turkey)'  
		  ];

		if ( isset( $locale_name_array[ $locale ] ) ) {
			return $locale_name_array[ $locale ];
		}
		else {
			return null;
		}
	}

	#endregion

	/**
	 * Redirect attachment URLs to their parent post URLs
	 */
	public function redirect_attachment_to_parent( $link, $post_id ) {
		$attachment = get_post( $post_id );
		if ( $attachment && $attachment->post_type === 'attachment' && $attachment->post_parent ) {
			return get_permalink( $attachment->post_parent );
		}
		return $link;
	}

	/**
	 * Handle attachment page redirects to parent post
	 */
	public function handle_attachment_redirect()
	{
		if ( is_attachment() ) {
			global $post;

			if ( $post && $post->post_parent ) {
				wp_safe_redirect( esc_url( get_permalink( $post->post_parent ) ), 301 ) ;
			} else {
				wp_safe_redirect ( esc_url( home_url( '/' ) ), 301 );
			}
		}
	}

	#region Sitemap

	function generate_sitemap() {
		if ( !isset( $this->sitemap_module ) ) {
			return false;
		}

		return $this->sitemap_module->create_sitemap();
	}


	#endregion

	#region Robots.txt

	function get_robots_txt() {
		$robotsTxt = '';
		$robots_path = ABSPATH . 'robots.txt';
		$source = 'none';
		
		if ( file_exists( $robots_path ) ) {
			$robotsTxt = file_get_contents( $robots_path );
			$source = 'file';
		} else {
			// Get the default WordPress robots.txt content
			ob_start();
			do_robots();
			$robotsTxt = ob_get_clean();
			$source = 'default';
		}

		return [
			'content' => $robotsTxt,
			'source' => $source,
			'path' => $robots_path,
		];
	}

	function set_robots_txt( $content ) {
		$robots_path = ABSPATH . 'robots.txt';
		
		$result = file_put_contents($robots_path, $content);
		return $result !== false;
	}

	#endregion

	#region LLMs.txt

	function get_llms_txt() {
		$llmsTxt = '';
		$llms_path = ABSPATH . 'llms.txt';
		$source = 'none';
		
		if ( file_exists( $llms_path ) ) {
			$llmsTxt = file_get_contents( $llms_path );
			$source = 'file';
		} else {
			$llmsTxt = $this->get_template_llms();
			$source = 'template';
		}

		return [
			'content' => $llmsTxt,
			'source' => $source,
			'path' => $llms_path,
		];
	}

	function get_template_llms() {

		$site_url = get_site_url();
		$site_name = get_bloginfo( 'name' );

		$template = "
# {SITE_NAME}

> Short summary, e.g. description or key benefits of the site.

**Key Features**:
- **Feature 1**: Brief description of feature 1.
- **Feature 2**: Brief description of feature 2.
- **Feature 3**: Brief description of feature 3.

## Section Title 1
* [Page Title 1]({SITE_URL}/url/for/page1): Brief description of the page content.
* [Page Title 2]({SITE_URL}/url/for/page2): Brief description of the page content.
* [Page Title 3]({SITE_URL}/url/for/page3): Brief description of the page content.

## Section Title 2
* [Link Description A]({SITE_URL}/url/a): Description of content A.
* [Link Description B]({SITE_URL}/url/b): Description of content B.

## Optional: Lower-priority Resources
* [Optional Link]({SITE_URL}/optional/url): This content can be skipped if needed.
";

		$template = str_replace( '{SITE_URL}', $site_url, $template );
		$template = str_replace( '{SITE_NAME}', $site_name, $template );

		return $template;

	}

	function set_llms_txt( $content ) {
		$llms_path = ABSPATH . 'llms.txt';

		$site_url = get_site_url();
		$site_name = get_bloginfo( 'name' );

		$content = str_replace( '{SITE_URL}', $site_url, $content );
		$content = str_replace( '{SITE_NAME}', $site_name, $content );
		
		$result = file_put_contents( $llms_path, $content );
		return $result !== false;
	}

	#endregion

	#region Performance Insights

	function get_speed_and_vitals( $post_id ) {
		$result = $this->insights_module->get_insights_for_post( $post_id );

		return $result;
	}

	function get_last_insights() {
		$last_insights = $this->insights_module->get_last_insights();
		if ( !is_array( $last_insights ) ) {
			return [];
		}

		return $last_insights;
	}

	#endregion

	#region Analytics

	function track_current_visit() {
		if ( !isset( $this->analytics_module ) ) {
			return;
		}

		// Only track single posts and pages
		if ( is_singular() ) {
			global $post;
			if ( $post && $post->ID ) {
				$this->analytics_module->track_visit( $post->ID );
			}
		}
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_analytics_data( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_analytics_data( $args );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_analytics_summary( $start_date = null, $end_date = null ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_analytics_summary( $start_date, $end_date );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_top_posts( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_top_posts( $args );
	}

	function get_ai_agents_summary( $start_date = null, $end_date = null ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_ai_agents_summary( $start_date, $end_date );
	}

	function get_ai_agent_details( $bot_name, $start_date = null, $end_date = null ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_ai_agent_details( $bot_name, $start_date, $end_date );
	}

	function get_ai_agents_by_post( $post_id, $days = 30 ) {
		if ( !isset( $this->analytics_module ) ) {
			return array(
				'openai' => 0,
				'anthropic' => 0,
				'google' => 0,
				'others' => 0
			);
		}

		return $this->analytics_module->get_ai_agents_by_post( $post_id, $days );
	}

	function query_bot_traffic( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->query_bot_traffic( $args );
	}

	function rank_posts_for_bots( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->rank_posts_for_bots( $args );
	}

	function get_bot_profile( $bot_name, $start_date = null, $end_date = null ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_bot_profile( $bot_name, $start_date, $end_date );
	}

	function compare_bot_periods( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->compare_bot_periods( $args );
	}

	function get_bot_mix( $args = array() ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_bot_mix( $args );
	}

	#endregion

	#region Google Analytics
	function get_google_analytics_state() {
		if ( !class_exists( 'Meow_MWSEO_Modules_GoogleAnalytics' ) ) {
			return [];
		}

		// Get property_id with fallback to first element of property_ids array
		$property_id = $this->get_option( 'google_analytics_property_id', '' );
		if ( empty( $property_id ) ) {
			$property_ids = $this->get_option( 'google_analytics_property_ids', [] );
			if ( !empty( $property_ids ) && is_array( $property_ids ) ) {
				$property_id = $property_ids[0];
				$this->update_option( 'google_analytics_property_id', $property_id );
			}
		}

		return [
			'redirect_url' => $this->get_google_redirect_url(),
			'property_id' => $property_id,
		];
	}


	function unlink_google_analytics() {
		if ( !isset( $this->googleanalytics_module ) ) {
			return false;
		}

		return $this->googleanalytics_module->unlink();
	}

	function get_is_authenticated() {
		if ( !isset( $this->googleanalytics_module ) ) {
			return false;
		}

		// Refresh the options
		$this->googleanalytics_module->init();

		return $this->googleanalytics_module->is_authenticated();
	}

	function get_google_auth_url() {
		if ( !isset( $this->googleanalytics_module ) ) {
			return '';
		}

		// Refresh the options 
		$this->googleanalytics_module->init();

		return $this->googleanalytics_module->get_auth_url();
	}

	function get_google_redirect_url() {
		if ( !isset( $this->googleanalytics_module ) ) {
			return '';
		}
		
		return $this->googleanalytics_module->get_redirect_url();
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_google_analytics_data( $args = array() ) {
		if ( !isset( $this->googleanalytics_module ) ) {
			return array();
		}

		return $this->googleanalytics_module->get_analytics_data( $args );
	}

	function get_post_analytics( $post_id, $page_path, $start_date = null, $end_date = null ) {
		if ( !isset( $this->analytics_module ) ) {
			return array();
		}

		return $this->analytics_module->get_post_analytics( $post_id, $page_path, $start_date, $end_date );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_google_analytics_summary( $start_date = null, $end_date = null ) {
		if ( !isset( $this->googleanalytics_module ) ) {
			return array();
		}

		return $this->googleanalytics_module->get_analytics_summary( $start_date, $end_date );
	}

	function get_google_analytics_post_analytics( $page_path, $start_date = null, $end_date = null ) {
		if ( !isset( $this->googleanalytics_module ) ) {
			return array();
		}

		return $this->googleanalytics_module->get_post_analytics( $page_path, $start_date, $end_date );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_google_analytics_top_posts( $args = array() ) {
		if ( !isset( $this->googleanalytics_module ) ) {
			return array();
		}

		return $this->googleanalytics_module->get_top_posts( $args );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_google_analytics_realtime_data() {
		if ( !isset( $this->googleanalytics_module ) ) {
			return array();
		}

		return $this->googleanalytics_module->get_realtime_data();
	}

	// Plausible Analytics

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_plausible_analytics_data( $args = array() ) {
		if ( !isset( $this->pro->plausible_analytics ) ) {
			return array();
		}

		return $this->pro->plausible_analytics->get_data( $args );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_plausible_analytics_summary( $start_date = null, $end_date = null ) {
		if ( !isset( $this->pro->plausible_analytics ) ) {
			return array();
		}

		return $this->pro->plausible_analytics->get_summary( $start_date, $end_date );
	}

	function get_plausible_analytics_post_analytics( $page_path, $start_date = null, $end_date = null ) {
		if ( !isset( $this->pro->plausible_analytics ) ) {
			return array();
		}

		return $this->pro->plausible_analytics->get_post_analytics( $page_path, $start_date, $end_date );
	}

	// TODO [2025]: Refactor to unified analytics provider interface
	function get_plausible_analytics_top_posts( $start_date = null, $end_date = null, $limit = 10 ) {
		if ( !isset( $this->pro->plausible_analytics ) ) {
			return array();
		}

		return $this->pro->plausible_analytics->get_top_posts( $start_date, $end_date, $limit );
	}

	#endregion
}

?>