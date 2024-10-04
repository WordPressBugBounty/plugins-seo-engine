<?php

class Meow_MWSEO_Core
{
	public $admin = null;
	public $is_rest = false;
	public $site_url = null;

	public $meta_key_seo_title = '_kiss_seo_title';
	public $meta_key_seo_excerpt = '_kiss_seo_excerpt';
	public $meta_key_skip = '_kiss_seo_ignore';

	private $option_name = 'seo_kiss_options';

	public function __construct() {
		$this->site_url = get_site_url();
		$this->is_rest = MeowCommon_Helpers::is_rest();

		add_action( 'plugins_loaded', array( $this, 'init' ) );

		$analyze_on_update = get_option( 'seo_kiss_options' )[ 'seo_engine_analyze_on_update' ] ?? false;
		if ( $analyze_on_update ) {
			add_action( 'save_post', array( $this, 'analyze_on_update' ), 10, 3 );
		}
	}

	function init() {
		// Part of the core, settings and stuff
		$this->admin = new Meow_MWSEO_Admin( $this );

		// Only for REST
		if ( $this->is_rest ) {
			new Meow_MWSEO_Rest( $this, $this->admin );
		}

		// Dashboard
		$use_mwseo_sitemap = $this->get_option( 'seo_engine_sitemap', false );
		if ( $use_mwseo_sitemap ) {
			add_filter(
				'init',
				function() {
					$provider = new Meow_MWSEO_Sitemap( $this );
					wp_register_sitemap_provider( SEOENGINE_DOMAIN, $provider );
				}
			);

		}

		// Website
		//add_filter( 'wp_title', [ $this, 'render_title' ], 10, 2 );
		add_filter( 'pre_get_document_title', [ $this, 'render_title' ], 99, 1 );
		add_action( 'wp_head', [ $this, 'render_description' ], 99, 0 );
		add_action( 'init', [ $this, 'reject_gptbot_user_agent' ], 99, 0 );


		// Woocommerce
		if ( $this->get_option( 'seo_engine_woocommerce_assistant', false ) ) {
			add_action( 'add_meta_boxes', array( $this, 'add_wc_meta_boxes' ) );
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
			// make sure the path is legal (within the uploads directory with the SEOENGINE_PREFIX and log extension)
			if ( strpos( $path, $uploads_dir_path ) !== 0 || strpos( $path, SEOENGINE_PREFIX ) === false || substr( $path, -4 ) !== '.log' ) {
				$path = null;
			} else {
				return $path;
			}
		}

		if ( !$path ) {
			$path = $uploads_dir_path . SEOENGINE_PREFIX . "_" . $this->random_ascii_chars() . ".log";
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
		$enabled = $this->get_option( 'seo_engine_logs', false );
		if ( !$enabled ) { return; }

		$log_file_path = $this->get_logs_path();
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


	function reject_gptbot_user_agent() {
		$disallow_gpt_bot = get_option( 'seo_kiss_options' )[ 'seo_engine_disallow_gpt_bot' ] ?? false;
		if ( $disallow_gpt_bot && isset($_SERVER[ 'HTTP_USER_AGENT' ]) && strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'GPTBot' ) !== false ) {
			$this->log( '🤖 GPTBot has been detected and blocked.');
			header( 'HTTP/1.1 403 Forbidden' );
			exit( $this->get_funny_rejection_message() );
		}
	}

	function get_post_types() {
		global $wpdb;
		return $wpdb->get_col( "SELECT DISTINCT post_type FROM $wpdb->posts" );
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

	function check_title_duplicates($title, $id) {
		$duplicate_hashes = get_option( 'seo_engine_title_hashes' );
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
					update_option( 'seo_engine_title_hashes', $duplicate_hashes );
					return false;
				}
				
				return true;
			}
		}
		else {
			$duplicate_hashes[ $title_hash ] = $id;
			update_option( 'seo_engine_title_hashes', $duplicate_hashes );
			return false;
		}
	}

	function analyze_on_update( $post_id, $post, $update ) {
		$this->calculate_seo_score( $post );
	}


	// Decides whether or not the post is SEO-friendly or not.
	function calculate_seo_score( $post ) {
		if ( get_post_meta( $post->ID, '_seo_engine_status', true ) === 'skip' ) {
			$this->log( "🚫 Post (#{$post->ID}) was skipped." );
			return [ 'status' => 'skip', 'message' => 'Skipped.' ];
		}

		$number_of_tests = 0;
		$errors = [
			'messages' => array(),
			'codes' => array(),
		];
		$info_messages = [];
		$this->log( "🔍 Analyzing post (#{$post->ID})... " );

		// Check if title is unique
		$this->log( "🔠 Checking if the title is unique." );
		$number_of_tests++;
		$has_title = !empty( $post->post_title );

		if ( !$has_title ){
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  'The post does not have a title.' ],
				'codes' => [ ...$errors['codes'] ,  'title_missing' ],
			];
		}
		else if ( $this->check_title_duplicates( $post->post_title, $post->ID ) ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  'The title is not unique.' ],
				'codes' => [ ...$errors['codes'] ,  'title_not_unique' ],
			];
		}

		// Title for SEO should be between 10 and 70 chars.
		$this->log( "🔠 Checking if the title is between 10 and 70 characters." );
		$number_of_tests++;
		$seo_title = $this->build_title( $post );
		$seo_title_length = strlen( $seo_title );
		if ( $seo_title_length < 10 || $seo_title_length > 80 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "Title (for SEO) should be between 10 and 70 characters (80 max). Currently %s characters.", $seo_title_length ) ],
				'codes' => [ ...$errors['codes'] ,  'title_seo_length' ],
			];
		}


		// Excerpt should exist
		$this->log( "🔠 Checking if the excerpt exists." );
		$number_of_tests++;
		if ( empty( $post->post_excerpt ) ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  'Excerpt is missing.' ],
				'codes' => [ ...$errors['codes'] ,  'excerpt_missing' ],
			];
		}

		// Excerpt for SEO should be between 10 and 160 chars.
		$this->log( "🔠 Checking if the excerpt is between 10 and 160 characters." );
		$number_of_tests++;
		$seo_excerpt = $this->build_excerpt( $post );
		$seo_excerpt_length = strlen( $seo_excerpt );
		if ( $seo_excerpt_length < 10 || $seo_excerpt_length > 160 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "Excerpt (for SEO) should be between 10 and 160 characters (180 max). Currently %s characters.", $seo_excerpt_length ) ],
				'codes' => [ ...$errors['codes'] ,  'excerpt_seo_length' ],
			];
		}

		// Slug must be less than 64 characters.
		$this->log( "🔠 Checking if the slug is less than 64 characters." );
		$number_of_tests++;
		if ( strlen( $post->post_name ) > 64 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "The slug should be less than 64 characters. Currently %s characters.", strlen( $post->post_name ) ) ],
				'codes' => [ ...$errors['codes'] ,  'slug_length' ],
			];
		}

		// Slug must be less than 6 words.
		$this->log( "🔠 Checking if the slug is less than 6 words." );
		$number_of_tests++;
		$words = explode( '-', $post->post_name );
		if ( count( $words ) > 6 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "The slug should be less than 5 words. Currently %s words.", count( $words ) ) ],
				'codes' => [ ...$errors['codes'] ,  'slug_words' ],
			];
		}


		// Check if the post is long enough.
		$this->log( "🔠 Checking if the post is long enough." );
		$number_of_tests++;
		if ( str_word_count( $post->post_content ) < 300 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "The post is too short. It should be at least 300 words. Currently %s words.", str_word_count( $post->post_content ) ) ],
				'codes' => [ ...$errors['codes'] ,  'post_too_short' ],
			];
		}

		// Check if all images have alt text. check_images_alt_text
		$this->log( "🔠 Checking if all images have alt text." );
		$number_of_tests++;
		$alt_count = count( $this->check_images_alt_text( $post->post_content ) );
		if( $alt_count != 0  ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  sprintf( "You have %s image%s without alt text.", $alt_count, ( $alt_count >= 2 ? 's' : '' ) ) ],
				'codes' => [ ...$errors['codes'] ,  'images_missing_alt_text' ],
			];
		}

		// Check if the post includes a few internal and external links.
		$this->log( "🔠 Checking if the post includes a few internal and external links." );
		$number_of_tests++;
		$links = $this->check_links( $post->post_content );
		if ( $links[ 'total' ] == 0 ) {
			$this->log( "🟢 Matched issue. " );
			$errors = [
				'messages' => [ ...$errors['messages'] ,  'The post should include a few internal and external links.' ],
				'codes' => [ ...$errors['codes'] ,  'links_missing' ],
			];
		}
		else {
			$info_messages[] = sprintf( "The post includes %s internal and %s external links.", $links[ 'internal' ], $links[ 'external' ] );
		}

		// Check the readability score.
		$this->log( "🔠 Checking the readability score." );
		$number_of_tests++;
		$readability_module = new Meow_MWSEO_Modules_Readability();
		$readability = $readability_module->calculate_readability( $post->post_content );
		if ( $readability != 0 ) {

			$readability_message = sprintf( "The post's readability score is %s%%, it's %s", $readability[ 'flesch_kincaid' ], $readability[ 'grade' ] );
			$readability_treshold = get_option( 'seo_kiss_options' )[ 'seo_engine_readability_treshold' ] ?? 50;

			if( $readability[ 'flesch_kincaid' ] < $readability_treshold ){
				$this->log( "🟢 Matched issue. " );
				$errors = [
					'messages' => [ ...$errors['messages'] ,  $readability_message . ' Aim for a score above ' . $readability_treshold . '%.' ],
					'codes' => [ ...$errors['codes'] ,  'readability_score' ],
				];
			}
			else {
				$info_messages[] = $readability_message;
			}

		}

		// Many more to go...
		if( !empty( $errors['messages'] ) ) {
			$score = round( ( ( $number_of_tests - count( $errors['messages'] ) ) / $number_of_tests ) * 100 );
			$error_messages = implode( "\n\n• ", $errors['messages'] );
			
			$this->log( "🔴 Post (#{$post->ID}) is not SEO-friendly. " );
			$result = ['score' => $score ,'status' => 'error', 'message' => '• ' . $error_messages, 'codes' => $errors['codes'] ];
		}
		else {
			$info_messages = implode( "\n\n• ", $info_messages );
			$this->log( "🟢 Post (#{$post->ID}) is SEO-friendly. " );
			$result = ['score' => 100 ,'status' => 'ok', 'message' => 'The post is SEO-friendly. 😻 ' . $info_messages, 'codes' => [] ];
		}

		update_post_meta( $post->ID, '_seo_engine_score', $result['score'] );
		update_post_meta( $post->ID, '_seo_engine_status', $result['status'] );
		update_post_meta( $post->ID, '_seo_engine_message', $result['message'] );
		update_post_meta( $post->ID, '_seo_engine_codes', $result['codes'] );

		return $result;

	}

	function get_all_posts_with_seo_score() {

		// Both are set to true, to see the bigger picture. If user prefers to see a custom view, we can add options for them later.
		$show_unscored_posts = true; // get_option( 'seo_kiss_options' )[ 'seo_engine_show_unscored_chart' ] ?? false;
		$show_any_post_type = true;  // get_option( 'seo_kiss_options' )[ 'seo_engine_show_any_post_type' ] ?? false;

		$meta_query = $show_unscored_posts ? [] : [ 'key' => '_seo_engine_score', 'compare' => 'EXISTS' ];
		$default_post_type = get_option( 'seo_kiss_options' )[ 'seo_engine_default_post_type' ] ?? 'post';
		$post_type = $show_any_post_type ? 'any' : $default_post_type;

		$args = array(
			'post_type' => $post_type,
			'posts_per_page' => -1,
			'meta_query' => array(
				$meta_query,
			),
		);

		$posts = get_posts( $args );

		$posts_with_seo_score = array();
		
		foreach ( $posts as $post ) {
			$seo_score = get_post_meta( $post->ID, '_seo_engine_score', true );
			$seo_status = get_post_meta( $post->ID, '_seo_engine_status', true );


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

		if ( $score >= 0 && $score <= 20 ) {
			return 'Poor SEO';
		}
		else if ( $score >= 21 && $score <= 40 ) {
			return 'Normal SEO';
		}
		else if ( $score >= 41 && $score <= 60 ) {
			return 'Good SEO';
		}
		else if ( $score >= 61 && $score <= 80 ) {
			return 'Great SEO';
		}
		else if ( $score >= 81 && $score <= 100 ) {
			return 'Excellent SEO';
		}
		else {
			return 'Unknown';
		}
	}

	function get_seo_engine_post_meta( $post ) {

		$post_id = $post->ID;

		$score = get_post_meta( $post_id, '_seo_engine_score', true );
		$status = get_post_meta( $post_id, '_seo_engine_status', true );
		$message = get_post_meta( $post_id, '_seo_engine_message', true );
		$codes = get_post_meta( $post_id, '_seo_engine_codes', true );

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
		echo '<meta name="description" content="' . esc_html( $excerpt ) . '" />';
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

	function check_images_alt_text( $content ) {
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


	function magic_fix( $post, $errors, $update_post = false){
		//For the error codes refer to this notion page : https://www.notion.so/meowarts/Error-codes-table-220cc0999fdd4e56943a6957de4be854
		$magic_fixes = [];
		$start_time = microtime( true );

		global $mwai;
		if (is_null( $mwai ) || !isset( $mwai ) ) {
			$this->log( '⚠️ Missing AI Engine.');
			return false;
		}

		$magic_fix_module = new Meow_MWSEO_Modules_MagicFix( $post, $this );
		
		$fix_to_ignore = get_option( 'seo_kiss_options' )[ 'seo_engine_select_magic_fix' ] ?? [ ];
		$errors = array_diff( $errors, $fix_to_ignore );

		foreach ( $errors as $error_key => $error) {
			$error_switch = $update_post ? $error_key : $error;
			$elapsed_time = microtime( true ) - $start_time;
			$this->log( '🔍 Elapsed time : ' . sprintf('%0.2f', $elapsed_time) . ' seconds. Now processing : ' . $error_switch );
			switch ( $error_switch ) {
				case 'title_missing':
					$magic_fixes['title_missing'] =  $update_post ? $magic_fix_module->magic_fix_title_missing_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_title_missing(  );
					break;
				case 'title_not_unique':
					$magic_fixes['title_not_unique'] =  $update_post ? $magic_fix_module->magic_fix_title_missing_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_title_missing(  );
					break;
				case 'title_seo_length':
					$magic_fixes['title_seo_length'] =  $update_post ? $magic_fix_module->magic_fix_title_seo_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_title_seo(  );
					break;
				case 'excerpt_missing':
					$magic_fixes['excerpt_missing'] =  $update_post ? $magic_fix_module->magic_fix_excerpt_missing_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_excerpt_missing(  );
					break;
				case 'excerpt_seo_length':
					$magic_fixes['excerpt_seo_length'] =  $update_post ? $magic_fix_module->magic_fix_excerpt_seo_length_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_excerpt_seo_length(  );
					break;
				case 'slug_length':
				case 'slug_words':
					$magic_fixes['slug_words'] = $update_post ? $magic_fix_module->magic_fix_slug_length_post_update( $error[ 'value' ] ) : $magic_fix_module->magic_fix_slug_length(  );
					break;
				case 'images_missing_alt_text':

					if( !$update_post ) {
						$images = $this->check_images_alt_text( $post->post_content );
						$this->log( '🔍 Images missing alt text : ' . count( $images ) );
					}
					
					$magic_fixes['images_missing_alt_text'] =  $update_post ?
						$magic_fix_module->magic_fix_images_missing_alt_text_post_update( $error[ 'value' ] )
					:
						$magic_fix_module->magic_fix_images_missing_alt_text( $images );

					break;
				case 'links_missing':
					$magic_fixes['links_missing'] = $update_post ? $magic_fix_module->magic_fix_links_missing_update_post( $error[ 'value' ] ) : $magic_fix_module->magic_fix_links_missing(  );
					break;
				default:
					break;
			}
		}

		return $magic_fixes;
	}

	/**
	 *
	 * Roles & Access Rights
	 *
	 */
	function can_access_settings() {
		return apply_filters( 'seo_engine_allow_setup', current_user_can( 'manage_options' ) );
	}

	function can_access_features() {
		return apply_filters( 'seo_engine_allow_usage', current_user_can( 'administrator' ) );
	}

	#region Options

	function get_option( $option, $default = null ) {
		$options = $this->get_all_options();
		return $options[$option] ?? $default;
	}

	function sanitized_options(){
		$options = $this->get_all_options();
		
		$options['seo_engine_post_types'] = $this->make_post_type_list( $this->get_post_types() );

		if ( $options['seo_engine_language'] == 'auto' || empty( $options['seo_engine_language'] ) ) {
			$options['seo_engine_language'] = $this->get_language_name( get_locale() ) ?? 'English';
		}

		if ( empty( $options['seo_engine_readability_treshold'] ) ) {
			$options['seo_engine_readability_treshold'] = 50;
		}

		// AI Engine
		global $mwai;

		if( is_null( $mwai ) || !isset( $mwai ) ) {
			$options['seo_engine_ai_engine_status'] = false;
			$options['seo_engine_ai_engine_message'] = 'AI Engine is not available.';
		}
		else {

			try{
				$options['seo_engine_ai_engine_status'] = true;
				$options['seo_engine_ai_engine_message'] = $mwai->checkStatus();
			}
			catch ( Exception $e ) {
				$options['seo_engine_ai_engine_status'] = false;
				$options['seo_engine_ai_engine_message'] = $e->getMessage();
			}
		}

		// AI Features
		if ( !$options['seo_engine_ai_engine_status'] ) {
			// Disable all AI related features
			$options['seo_engine_ai_magic_fix'] = false;
			$options['seo_engine_ai_auto_correct'] = false;
			$options['seo_engine_ai_magic_wand'] = false;
			$options['seo_engine_ai_web_scraping'] = false;
			$options['seo_engine_ai_keywords'] = false;
			$options['seo_engine_woocommerce_assistant'] = false;
		}

		// SE Ranking


		// Sitemap
		if ( $options['seo_engine_disable_wp_sitemap'] ) {

		}

		// (HIDE) Temporary disable the Search Engine related features
		$options['seo_engine_google_ranking'] = false;
		$options['seo_engine_ai_web_scraping'] = false;

		$options = $this->update_options( $options );

		return $options;
	}

	function list_options() {

		return array(
			//POST TYPES
			'seo_engine_default_post_type' => 'post',
			'seo_engine_posts_limit' => 10,
			'seo_engine_post_types' => $this->make_post_type_list( $this->get_post_types() ),
			'seo_engine_select_post_types' => ['post'],

			//PREFERENCES
			'seo_engine_readability_treshold' => 50,
			//get_locale to full language name
			'seo_engine_language' => $this->get_language_name( get_locale() ) ?? 'English',
			'seo_engine_disallow_gpt_bot' => false,
			'seo_engine_analyze_on_update' => false,
			'seo_engine_analyze_buffer' => 10,
			'seo_engine_analyze_buffer_interval' => 0,
			'seo_engine_logs' => false,

			//SITEMAP
			'seo_engine_sitemap' => false,
			'seo_engine_disable_wp_sitemap' => false,
			'seo_engine_sitemap_exclude_users_provider' => false,
			'seo_engine_sitemap_exclude_posts_provider' => false,
			'seo_engine_sitemap_exclude_taxonomies_provider' => false,
			'seo_engine_sitemap_excluded_post_types' => [],
			'seo_engine_sitemap_excluded_taxonomies' => [],
			'seo_engine_sitemap_excluded_post_ids' => [],


			//SNS
			'seo_engine_social_networks' => false,

			//RANKING
			'seo_engine_google_ranking' => false,
			'seo_engine_google_api_key' => '',
			'seo_engine_google_programmable_search_engine' => '',
			'seo_engine_google_interval_hours' => 24,
			'seo_engine_google_search_depth' => 3,
			'seo_engine_google_track_points' => 60,

			//AI PARAMETERS
			'seo_engine_ai_engine_status' => false,
			'seo_engine_ai_engine_message' => '',
			'seo_engine_ai_magic_fix' => false,
			'seo_engine_ai_auto_correct' => false,
			'seo_engine_select_magic_fix' => [
				'readability_score',
			],

			'seo_engine_ai_magic_wand' => false,
			'seo_engine_ai_web_scraping' => false,
			'seo_engine_ai_keywords' => false,
		);
	}

	function get_all_options() {
		$options = get_option( $this->option_name, null );

		// TODO: Delete this after July 2024
		if ( is_array( $options ) ) {
			$options = array_combine( array_map( function( $key ) {
				return str_replace( 'seo_kiss_', 'seo_engine_', $key );
			}, array_keys( $options ) ), $options );
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

		$prompt  = "Here is the product: {PRODUCT}\n\nBased on the product, write a description of this product (between 120 and 240 words), a short description (between 20-49 words), a SEO-friendly title, and tags (separated by commas). Write it in {LANGUAGE}.";
		$prompt  = apply_filters( 'seo_engine_woocommerce_prompt', $prompt );
		$prompt .= "Use this keys: description, short_description, seo_title, tags.";

		$prompt = str_replace( '{PRODUCT}',  $params['product'],  $prompt );
		$prompt = str_replace( '{LANGUAGE}', $params['language'], $prompt );

		$response = $mwai->simpleJsonQuery( $prompt );;
		
		return $response;
	}

	#endregion

	#region Utilities

	function add_wc_meta_boxes()
	{
		if (get_post_type() !== 'product') {
			return;
		}

		add_meta_box(
			'mwseo-metadata',
			__('SEO Engine', SEOENGINE_PREFIX),
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
}

?>