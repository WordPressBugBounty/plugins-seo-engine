<?php

class Meow_MWSEO_Modules_Redirects
{
	private $core = null;
	private $redirects_table = null;
	private $log_404_table = null;
	private $home_path = null;
	private $deferred_hits = array(); // [rule_id => count] flushed on shutdown

	public function __construct( $core ) {
		$this->core = $core;
		global $wpdb;
		$this->redirects_table = $wpdb->prefix . MWSEO_PREFIX . '_redirects';
		$this->log_404_table   = $wpdb->prefix . MWSEO_PREFIX . '_404_log';
		$this->init();
	}

	public function init() {
		// Module is opt-in: bail entirely when disabled so we don't create tables
		// or schedule cron on sites that aren't using it.
		if ( !$this->core->get_option( 'redirects_enabled', false ) ) {
			// Clean up the cron if the module was previously enabled.
			$ts = wp_next_scheduled( 'mwseo_prune_404_log' );
			if ( $ts ) { wp_unschedule_event( $ts, 'mwseo_prune_404_log' ); }
			return;
		}

		$this->maybe_create_redirects_table();
		$this->maybe_create_404_log_table();

		add_action( 'template_redirect', array( $this, 'handle_redirect_match' ), 1 );

		if ( $this->core->get_option( 'redirects_track_404', true ) ) {
			add_action( 'template_redirect', array( $this, 'maybe_log_404' ), 99 );
		}

		if ( $this->core->get_option( 'redirects_auto_slug', false ) ) {
			add_action( 'post_updated', array( $this, 'maybe_auto_redirect_on_slug_change' ), 10, 3 );
		}

		// Daily cron to prune old 404 entries
		add_action( 'mwseo_prune_404_log', array( $this, 'prune_old_404s' ) );
		if ( !wp_next_scheduled( 'mwseo_prune_404_log' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'mwseo_prune_404_log' );
		}
	}

	#region DB Tables

	private function maybe_create_redirects_table() {
		global $wpdb;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$this->redirects_table'" ) === $this->redirects_table;
		if ( $table_exists ) { return; }

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->redirects_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			source_url varchar(500) NOT NULL,
			source_hash char(32) NOT NULL,
			match_type varchar(10) NOT NULL DEFAULT 'exact',
			target_url varchar(500) NOT NULL DEFAULT '',
			status_code smallint(5) NOT NULL DEFAULT 301,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			hits int(10) unsigned NOT NULL DEFAULT 0,
			last_hit datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			notes varchar(255) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY source_hash (source_hash),
			KEY enabled (enabled),
			KEY match_type (match_type)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private function maybe_create_404_log_table() {
		global $wpdb;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$this->log_404_table'" ) === $this->log_404_table;
		if ( $table_exists ) { return; }

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->log_404_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			url varchar(500) NOT NULL,
			url_hash char(32) NOT NULL,
			hits int(10) unsigned NOT NULL DEFAULT 1,
			first_hit datetime DEFAULT CURRENT_TIMESTAMP,
			last_hit datetime DEFAULT CURRENT_TIMESTAMP,
			last_referer varchar(500) DEFAULT NULL,
			last_user_agent varchar(255) DEFAULT NULL,
			ignored tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY url_hash (url_hash),
			KEY last_hit (last_hit),
			KEY ignored (ignored)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	#endregion

	#region Hot path: redirect match

	public function handle_redirect_match() {
		// Skip admin, REST, AJAX, login
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$path = $this->get_request_path();
		if ( empty( $path ) ) { return; }

		// Skip wp-admin / wp-login / similar system paths
		if ( preg_match( '#^/?(wp-admin|wp-login\.php|wp-cron\.php|xmlrpc\.php|wp-json)#', $path ) ) {
			return;
		}

		$normalized = $this->normalize_path( $path );
		$hash = md5( $normalized );

		// Try exact match (cached)
		$rule = wp_cache_get( $hash, 'mwseo_redirects' );
		if ( $rule === false ) {
			global $wpdb;
			$rule = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, target_url, status_code FROM $this->redirects_table
				 WHERE source_hash = %s AND match_type = 'exact' AND enabled = 1
				 LIMIT 1",
				$hash
			), ARRAY_A );
			// Cache result (positive or negative) for 5 minutes
			wp_cache_set( $hash, $rule ?: 'none', 'mwseo_redirects', 300 );
		}

		if ( is_array( $rule ) && !empty( $rule ) ) {
			$this->execute_redirect( $rule, $normalized );
			return;
		}

		// Skip regex scan for static assets — never redirected anyway
		if ( preg_match( '/\.(jpg|jpeg|png|gif|webp|svg|ico|css|js|map|woff2?|ttf|eot|mp4|mp3|pdf|zip|xml)$/i', $normalized ) ) {
			return;
		}

		// Regex rules (Pro only) — load all enabled regex rules from cache, scan in PHP
		$regex_rules = wp_cache_get( 'all_regex', 'mwseo_redirects' );
		if ( $regex_rules === false ) {
			global $wpdb;
			$regex_rules = $wpdb->get_results(
				"SELECT id, source_url, target_url, status_code FROM $this->redirects_table
				 WHERE match_type = 'regex' AND enabled = 1",
				ARRAY_A
			);
			wp_cache_set( 'all_regex', $regex_rules ?: array(), 'mwseo_redirects', 300 );
		}

		if ( !empty( $regex_rules ) && class_exists( 'MeowPro_MWSEO_Core' ) ) {
			foreach ( $regex_rules as $r ) {
				$pattern = '#' . str_replace( '#', '\\#', $r['source_url'] ) . '#';
				$result = @preg_match( $pattern, $normalized );
				if ( $result === 1 ) {
					$target = @preg_replace( $pattern, $r['target_url'], $normalized );
					$rule = array(
						'id' => $r['id'],
						'target_url' => $target,
						'status_code' => $r['status_code'],
					);
					$this->execute_redirect( $rule, $normalized );
					return;
				}
			}
		}
	}

	private function execute_redirect( $rule, $source_path ) {
		$target = $rule['target_url'];
		$status = intval( $rule['status_code'] );

		// 410 Gone — set status, do not redirect
		if ( $status === 410 ) {
			$this->queue_hit_increment( $rule['id'] );
			status_header( 410 );
			nocache_headers();
			return;
		}

		// Resolve relative target to absolute URL
		if ( $target && $target[0] === '/' ) {
			$target = home_url( $target );
		}
		elseif ( !preg_match( '#^https?://#i', $target ) ) {
			$target = home_url( '/' . ltrim( $target, '/' ) );
		}

		$this->queue_hit_increment( $rule['id'] );

		wp_redirect( $target, $status, 'SEO Engine' );
		exit;
	}

	private function queue_hit_increment( $rule_id ) {
		// Defer the UPDATE to shutdown so it doesn't block the redirect response
		if ( empty( $this->deferred_hits ) ) {
			add_action( 'shutdown', array( $this, 'flush_deferred_hits' ), 99 );
		}
		$rule_id = intval( $rule_id );
		if ( !isset( $this->deferred_hits[ $rule_id ] ) ) {
			$this->deferred_hits[ $rule_id ] = 0;
		}
		$this->deferred_hits[ $rule_id ]++;
	}

	public function flush_deferred_hits() {
		if ( empty( $this->deferred_hits ) ) { return; }
		global $wpdb;
		foreach ( $this->deferred_hits as $rule_id => $count ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $this->redirects_table SET hits = hits + %d, last_hit = NOW() WHERE id = %d",
				$count, $rule_id
			) );
		}
		$this->deferred_hits = array();
	}

	#endregion

	#region 404 logging

	public function maybe_log_404() {
		if ( !is_404() ) { return; }

		// Skip bots if configured
		if ( $this->core->get_option( 'redirects_skip_bots', true ) && $this->is_crawler() ) {
			return;
		}

		$path = $this->get_request_path();
		if ( empty( $path ) ) { return; }

		$normalized = $this->normalize_path( $path );

		// Skip media / asset extensions — broken images and files are noise
		// for SEO, and would otherwise dominate the 404 log.
		if ( $this->is_asset_path( $normalized ) ) {
			return;
		}

		// Skip excluded patterns
		$excludes = $this->get_404_exclude_patterns();
		foreach ( $excludes as $pattern ) {
			if ( $this->path_matches_wildcard( $normalized, $pattern ) ) {
				return;
			}
		}

		$hash = md5( $normalized );
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? mb_substr( $_SERVER['HTTP_REFERER'], 0, 500 ) : null;
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : null;

		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $this->log_404_table (url, url_hash, hits, first_hit, last_hit, last_referer, last_user_agent)
			 VALUES (%s, %s, 1, NOW(), NOW(), %s, %s)
			 ON DUPLICATE KEY UPDATE
			   hits = hits + 1,
			   last_hit = NOW(),
			   last_referer = VALUES(last_referer),
			   last_user_agent = VALUES(last_user_agent)",
			mb_substr( $normalized, 0, 500 ),
			$hash,
			$referer,
			$user_agent
		) );
	}

	private function get_404_exclude_patterns() {
		$raw = $this->core->get_option( 'redirects_404_exclude', "/wp-admin/*\n/feed*\n/xmlrpc.php" );
		if ( !is_string( $raw ) ) { return array(); }
		$lines = preg_split( "/\r\n|\n|\r/", $raw );
		$patterns = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) { $patterns[] = $line; }
		}
		return $patterns;
	}

	private function path_matches_wildcard( $path, $pattern ) {
		// Convert simple wildcard to regex
		$regex = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
		return (bool) @preg_match( $regex, $path );
	}

	private function is_asset_path( $path ) {
		// Strip a possible double-extension suffix (e.g. ".jpg.webp") and check
		// the final extension. Also catches common WP /wp-content/uploads/ patterns.
		$assets = 'jpe?g|png|gif|webp|avif|svg|ico|bmp|tiff?'  // images
			. '|css|js|mjs|map'                              // web assets
			. '|woff2?|ttf|otf|eot'                          // fonts
			. '|mp[34]|m4[av]|wav|ogg|webm|mov|avi|wmv|flv'  // media
			. '|pdf|zip|gz|rar|7z|tar'                       // documents/archives
			. '|xml|txt|csv|json';                           // data
		return (bool) @preg_match( '/\.(' . $assets . ')(\.[a-z0-9]{2,5})?$/i', $path );
	}

	private function is_crawler() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		if ( empty( $ua ) ) { return false; }
		$markers = array( 'bot', 'crawler', 'spider', 'scraper', 'slurp', 'baiduspider', 'yandex', 'facebookexternalhit', 'twitterbot', 'linkedinbot' );
		foreach ( $markers as $m ) {
			if ( stripos( $ua, $m ) !== false ) { return true; }
		}
		return false;
	}

	public function prune_old_404s() {
		$days = intval( $this->core->get_option( 'redirects_404_retention_days', 30 ) );
		if ( $days <= 0 ) { return; }
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $this->log_404_table WHERE last_hit < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}

	#endregion

	#region Slug auto-redirect

	public function maybe_auto_redirect_on_slug_change( $post_id, $post_after, $post_before ) {
		if ( $post_after->post_status !== 'publish' ) { return; }
		if ( $post_before->post_name === $post_after->post_name ) { return; }
		if ( empty( $post_before->post_name ) ) { return; }

		$post_type_obj = get_post_type_object( $post_after->post_type );
		if ( !$post_type_obj || empty( $post_type_obj->public ) ) { return; }

		// Build the old permalink by temporarily restoring the previous slug
		$old_url = $this->compute_old_permalink( $post_before, $post_after );
		$new_url = get_permalink( $post_after );

		if ( !$old_url || !$new_url || $old_url === $new_url ) { return; }

		$old_path = $this->normalize_path( wp_parse_url( $old_url, PHP_URL_PATH ) );
		$new_path = $this->normalize_path( wp_parse_url( $new_url, PHP_URL_PATH ) );

		if ( $old_path === $new_path ) { return; }

		$this->save_redirect( array(
			'source_url' => $old_path,
			'target_url' => $new_path,
			'match_type' => 'exact',
			'status_code' => 301,
			'enabled' => 1,
			'notes' => 'auto: slug change',
		), true );
	}

	private function compute_old_permalink( $post_before, $post_after ) {
		// Trick get_permalink into using the old slug by passing a cloned post object
		$clone = clone $post_after;
		$clone->post_name = $post_before->post_name;
		// If parent or post_status changed, try to keep the historical structure
		$clone->post_status = 'publish';
		return get_permalink( $clone );
	}

	#endregion

	#region Public API (used by REST handlers)

	public function get_request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) { return ''; }
		$uri = $_SERVER['REQUEST_URI'];
		$qpos = strpos( $uri, '?' );
		if ( $qpos !== false ) { $uri = substr( $uri, 0, $qpos ); }
		return $uri;
	}

	public function normalize_path( $path ) {
		if ( empty( $path ) ) { return '/'; }
		$path = '/' . ltrim( $path, '/' );
		// Strip trailing slash except for root
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}
		return $path;
	}

	public function list_redirects( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search' => '',
			'sort' => 'created_at',
			'order' => 'DESC',
			'page' => 1,
			'limit' => 50,
			'enabled' => null,
		);
		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( $args['search'] !== '' ) {
			$where[] = '(source_url LIKE %s OR target_url LIKE %s OR notes LIKE %s)';
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like; $values[] = $like; $values[] = $like;
		}
		if ( $args['enabled'] !== null ) {
			$where[] = 'enabled = %d';
			$values[] = (int) $args['enabled'];
		}

		$allowed_sort = array( 'created_at', 'updated_at', 'last_hit', 'hits', 'source_url', 'status_code' );
		$sort = in_array( $args['sort'], $allowed_sort, true ) ? $args['sort'] : 'created_at';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$page = max( 1, intval( $args['page'] ) );
		$limit = max( 1, min( 200, intval( $args['limit'] ) ) );
		$offset = ( $page - 1 ) * $limit;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $this->redirects_table WHERE $where_sql";
		$total = !empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) )
			: (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT * FROM $this->redirects_table WHERE $where_sql ORDER BY $sort $order LIMIT %d OFFSET %d";
		$values[] = $limit; $values[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A );

		return array(
			'total' => $total,
			'page' => $page,
			'limit' => $limit,
			'rows' => $rows ?: array(),
		);
	}

	public function list_404s( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'search' => '',
			'sort' => 'last_hit',
			'order' => 'DESC',
			'page' => 1,
			'limit' => 50,
			'include_ignored' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( !$args['include_ignored'] ) {
			$where[] = 'ignored = 0';
		}
		if ( $args['search'] !== '' ) {
			$where[] = '(url LIKE %s OR last_referer LIKE %s)';
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like; $values[] = $like;
		}

		$allowed_sort = array( 'last_hit', 'first_hit', 'hits', 'url' );
		$sort = in_array( $args['sort'], $allowed_sort, true ) ? $args['sort'] : 'last_hit';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$page = max( 1, intval( $args['page'] ) );
		$limit = max( 1, min( 200, intval( $args['limit'] ) ) );
		$offset = ( $page - 1 ) * $limit;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $this->log_404_table WHERE $where_sql";
		$total = !empty( $values )
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) )
			: (int) $wpdb->get_var( $count_sql );

		$sql = "SELECT * FROM $this->log_404_table WHERE $where_sql ORDER BY $sort $order LIMIT %d OFFSET %d";
		$values[] = $limit; $values[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A );

		return array(
			'total' => $total,
			'page' => $page,
			'limit' => $limit,
			'rows' => $rows ?: array(),
		);
	}

	/**
	 * Create or update a redirect rule.
	 * @param array $data Rule fields.
	 * @param bool  $skip_if_exists If true, do nothing when an exact rule for this source already exists.
	 * @return array|WP_Error Saved row or error.
	 */
	public function save_redirect( $data, $skip_if_exists = false ) {
		global $wpdb;

		$id = isset( $data['id'] ) ? intval( $data['id'] ) : 0;
		$source_url = isset( $data['source_url'] ) ? trim( $data['source_url'] ) : '';
		$target_url = isset( $data['target_url'] ) ? trim( $data['target_url'] ) : '';
		$match_type = isset( $data['match_type'] ) && $data['match_type'] === 'regex' ? 'regex' : 'exact';
		$status_code = isset( $data['status_code'] ) ? intval( $data['status_code'] ) : 301;
		$enabled = isset( $data['enabled'] ) ? (int) (bool) $data['enabled'] : 1;
		$notes = isset( $data['notes'] ) ? mb_substr( (string) $data['notes'], 0, 255 ) : null;

		if ( $source_url === '' ) {
			return new WP_Error( 'invalid_source', 'Source URL is required.' );
		}
		if ( $status_code !== 410 && $target_url === '' ) {
			return new WP_Error( 'invalid_target', 'Target URL is required (except for 410 Gone).' );
		}
		$allowed_status = array( 301, 302, 307, 308, 410 );
		if ( !in_array( $status_code, $allowed_status, true ) ) {
			$status_code = 301;
		}

		// Pro gate: regex requires Pro
		if ( $match_type === 'regex' && !class_exists( 'MeowPro_MWSEO_Core' ) ) {
			return new WP_Error( 'pro_required', 'Regex match type requires SEO Engine Pro.' );
		}

		// Validate regex compiles
		if ( $match_type === 'regex' ) {
			$test_pattern = '#' . str_replace( '#', '\\#', $source_url ) . '#';
			if ( @preg_match( $test_pattern, '' ) === false ) {
				return new WP_Error( 'invalid_regex', 'Invalid regular expression.' );
			}
		}

		// Normalize exact source to a path when given a full URL on same host
		if ( $match_type === 'exact' ) {
			$source_url = $this->normalize_source_for_storage( $source_url );
		}

		$source_hash = md5( $match_type === 'exact' ? $source_url : $source_url );

		if ( $skip_if_exists && $match_type === 'exact' ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $this->redirects_table WHERE source_hash = %s AND match_type = 'exact' LIMIT 1",
				$source_hash
			) );
			if ( $existing ) { return array( 'id' => (int) $existing, 'skipped' => true ); }
		}

		$row = array(
			'source_url' => mb_substr( $source_url, 0, 500 ),
			'source_hash' => $source_hash,
			'match_type' => $match_type,
			'target_url' => mb_substr( $target_url, 0, 500 ),
			'status_code' => $status_code,
			'enabled' => $enabled,
			'notes' => $notes,
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' );

		if ( $id > 0 ) {
			$wpdb->update( $this->redirects_table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$formats[] = '%s';
			$wpdb->insert( $this->redirects_table, $row, $formats );
			$id = (int) $wpdb->insert_id;
		}

		$this->flush_caches();

		return $this->get_redirect( $id );
	}

	public function import_from_rank_math() {
		global $wpdb;

		$rm_table = $wpdb->prefix . 'rank_math_redirections';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) === $rm_table;
		if ( !$table_exists ) {
			return 0;
		}

		// Make sure our own table exists before inserting (the module may be disabled).
		$this->maybe_create_redirects_table();

		$rows = $wpdb->get_results( "SELECT sources, url_to, header_code, status FROM $rm_table", ARRAY_A );
		if ( empty( $rows ) ) {
			return 0;
		}

		$imported = 0;
		foreach ( $rows as $row ) {
			$sources = maybe_unserialize( $row['sources'] );
			if ( !is_array( $sources ) ) {
				continue;
			}

			$target = isset( $row['url_to'] ) ? trim( $row['url_to'] ) : '';
			$status_code = isset( $row['header_code'] ) ? intval( $row['header_code'] ) : 301;
			$enabled = ( isset( $row['status'] ) && $row['status'] === 'active' ) ? 1 : 0;

			foreach ( $sources as $source ) {
				if ( empty( $source['pattern'] ) ) {
					continue;
				}

				$pattern = $source['pattern'];
				$comparison = isset( $source['comparison'] ) ? $source['comparison'] : 'exact';

				// Map Rank Math comparison types to SEO Engine's match_type (exact|regex).
				$match_type = 'exact';
				switch ( $comparison ) {
					case 'regex':
						$match_type = 'regex';
						break;
					case 'contains':
						$match_type = 'regex';
						$pattern = '.*' . preg_quote( $pattern, '#' ) . '.*';
						break;
					case 'start':
						$match_type = 'regex';
						$pattern = '^' . preg_quote( $pattern, '#' );
						break;
					case 'end':
						$match_type = 'regex';
						$pattern = preg_quote( $pattern, '#' ) . '$';
						break;
					case 'exact':
					default:
						$match_type = 'exact';
						break;
				}

				$result = $this->save_redirect( array(
					'source_url' => $pattern,
					'target_url' => $target,
					'match_type' => $match_type,
					'status_code' => $status_code,
					'enabled' => $enabled,
					'notes' => 'Imported from Rank Math',
				), true );

				// Count only rows we actually inserted (skip existing/errors like Pro-gated regex).
				if ( is_array( $result ) && empty( $result['skipped'] ) ) {
					$imported++;
				}
			}
		}

		if ( $imported > 0 ) {
			$this->core->log( "↪️ Imported {$imported} redirect(s) from Rank Math." );
		}

		return $imported;
	}

	private function normalize_source_for_storage( $source ) {
		// If a full URL was provided for the current site, strip down to the path
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$source_host = wp_parse_url( $source, PHP_URL_HOST );
		if ( $source_host && $site_host && strcasecmp( $source_host, $site_host ) === 0 ) {
			$path = wp_parse_url( $source, PHP_URL_PATH );
			return $this->normalize_path( $path );
		}
		// If it's a path, normalize
		if ( !$source_host ) {
			return $this->normalize_path( $source );
		}
		return $source;
	}

	public function get_redirect( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $this->redirects_table WHERE id = %d", intval( $id )
		), ARRAY_A );
		return $row ?: null;
	}

	public function delete_redirects( $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $ids );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) { return 0; }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$count = $wpdb->query( $wpdb->prepare(
			"DELETE FROM $this->redirects_table WHERE id IN ($placeholders)",
			...$ids
		) );
		$this->flush_caches();
		return (int) $count;
	}

	public function bulk_redirects( $action, $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $ids );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) { return 0; }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		switch ( $action ) {
			case 'enable':
				$count = $wpdb->query( $wpdb->prepare(
					"UPDATE $this->redirects_table SET enabled = 1 WHERE id IN ($placeholders)",
					...$ids
				) );
				break;
			case 'disable':
				$count = $wpdb->query( $wpdb->prepare(
					"UPDATE $this->redirects_table SET enabled = 0 WHERE id IN ($placeholders)",
					...$ids
				) );
				break;
			case 'delete':
				return $this->delete_redirects( $ids );
			default:
				return 0;
		}
		$this->flush_caches();
		return (int) $count;
	}

	public function convert_404( $id, $extra = array() ) {
		global $wpdb;
		$id = intval( $id );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $this->log_404_table WHERE id = %d", $id
		), ARRAY_A );
		if ( !$row ) { return new WP_Error( 'not_found', '404 entry not found.' ); }

		$data = array(
			'source_url' => $row['url'],
			'target_url' => isset( $extra['target_url'] ) ? $extra['target_url'] : '',
			'status_code' => isset( $extra['status_code'] ) ? intval( $extra['status_code'] ) : 301,
			'match_type' => 'exact',
			'enabled' => 1,
			'notes' => isset( $extra['notes'] ) ? $extra['notes'] : 'Created from 404 log',
		);

		$result = $this->save_redirect( $data );
		if ( is_wp_error( $result ) ) { return $result; }

		// Delete the 404 entry now that it's been converted
		$wpdb->delete( $this->log_404_table, array( 'id' => $id ), array( '%d' ) );

		return $result;
	}

	public function ignore_404( $ids, $ignored = true ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $ids );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) { return 0; }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare(
			"UPDATE $this->log_404_table SET ignored = %d WHERE id IN ($placeholders)",
			$ignored ? 1 : 0,
			...$ids
		) );
	}

	public function delete_404s( $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', (array) $ids );
		$ids = array_filter( $ids );
		if ( empty( $ids ) ) { return 0; }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM $this->log_404_table WHERE id IN ($placeholders)",
			...$ids
		) );
	}

	public function clear_404s( $older_than_days = 0 ) {
		global $wpdb;
		$days = intval( $older_than_days );
		if ( $days > 0 ) {
			return (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM $this->log_404_table WHERE last_hit < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			) );
		}
		return (int) $wpdb->query( "TRUNCATE TABLE $this->log_404_table" );
	}

	private function flush_caches() {
		// Object cache uses keys keyed by source_hash and a singleton 'all_regex' key.
		// Clear the regex cache; per-hash entries are fine to leave because TTL is 5min.
		wp_cache_delete( 'all_regex', 'mwseo_redirects' );
	}

	#endregion
}
