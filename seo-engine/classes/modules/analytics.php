<?php

class Meow_MWSEO_Modules_Analytics
{
	private $core = null;
	private $table_name = null;
	private $use_privacy = false;

	public function __construct( $core )
	{
		$this->core = $core;
		global $wpdb;
		$this->table_name = $wpdb->prefix . MWSEO_PREFIX .'_analytics';
		$this->init();
	}

	public function init()
	{
		$this->use_privacy = $this->core->get_option( 'analytics_privacy', false );
		$this->create_analytics_table();
	}

	private function create_analytics_table()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $this->table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			post_id bigint(20) NOT NULL,
			visit_date datetime DEFAULT CURRENT_TIMESTAMP,
			user_ip varchar(45) NOT NULL,
			user_agent text,
			referer text,
			is_logged_in tinyint(1) DEFAULT 0,
			user_id bigint(20) DEFAULT NULL,
			country varchar(2) DEFAULT NULL,
			session_id varchar(100) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY visit_date (visit_date),
			KEY user_ip (user_ip),
			KEY is_logged_in (is_logged_in)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function track_visit( $post_id )
	{
		// Check if analytics tracking is enabled
		if ( !$this->core->get_option( 'general_analytics', false ) ) {
			return false;
		}

		// Don't track logged-in users
		if ( is_user_logged_in() ) {
			$track_logged_users = $this->core->get_option( 'analytics_track_logged_users', false );
			if ( !$track_logged_users ) {
				return false;
			}

			// Don't track editors and admins
			$is_power_user = current_user_can( 'editor' ) || current_user_can( 'administrator' );
			if ( $is_power_user ) {
				$track_power_users = $this->core->get_option( 'analytics_track_power_users', false );
				if ( !$track_power_users ) {
					return false;
				}
			}
		}

		// Don't track bots
		if ( $this->is_bot() ) {
			return false;
		}

		global $wpdb;

		$user_ip = $this->get_user_ip();
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$referer = $_SERVER['HTTP_REFERER'] ?? '';
		$is_logged_in = is_user_logged_in() ? 1 : 0;
		$user_id = $this->get_current_user_id();
		$session_id = $this->get_session_id();

		// Check for duplicate visit (same IP, same post, within last hour)
		$recent_visit = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $this->table_name 
			WHERE post_id = %d 
			AND user_ip = %s 
			AND visit_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
			LIMIT 1",
			$post_id,
			$user_ip
		) );

		if ( $recent_visit ) {
			return false; // Don't track duplicate visits
		}

		$data = array(
				'post_id' => $post_id,
				'user_ip' => $user_ip,
				'user_agent' => $user_agent,
				'referer' => $referer,
				'is_logged_in' => $is_logged_in,
				'user_id' => $user_id > 0 ? $user_id : null,
				'session_id' => $session_id,
				'visit_date' => current_time( 'mysql' )
		);

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$this->core->log( "📈 Visit tracked for post ID: $post_id" );
		}

		return $result !== false;
	}

	private function get_current_user_id()
	{
		$user_id = get_current_user_id();
		if ( $this->use_privacy && $user_id > 0 ) {
			// Hash the user ID for privacy
			$hash = hash( 'sha256', $user_id, true ); // binary output
			$user_id = substr( rtrim( strtr( base64_encode( $hash ), '+/', '-_'), '=' ), 0, 12 );
		}

		return $user_id;
	}

	private function get_user_ip()
	{
		$ip = '127.0.0.1';
		$headers = [
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];
	

		foreach ( $headers as $header ) {
			if ( array_key_exists( $header, $_SERVER ) && !empty( $_SERVER[ $header ] && $_SERVER[ $header ] != '::1' ) ) {
				$address_chain = explode( ',', wp_unslash( $_SERVER [ $header ] ) );
				$ip = filter_var( trim( $address_chain[ 0 ] ), FILTER_VALIDATE_IP );
				break;
			}
		}
		
		$ip = filter_var( apply_filters( 'mwseo_get_ip_address', $ip ), FILTER_VALIDATE_IP );

		if ( $this->use_privacy ) {
			$hash = hash( 'sha256', $ip, true ); // binary output
			$ip = substr( rtrim( strtr( base64_encode( $hash ), '+/', '-_'), '=' ), 0, 12 );
		}		

		return $ip;
	}

	private function get_session_id()
	{
		if ( session_status() == PHP_SESSION_NONE ) {
			session_start();
		}
		return session_id();
	}

	private function is_bot()
	{
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$bots = array(
			'bot', 'crawler', 'spider', 'scraper', 'facebook', 'whatsapp',
			'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
			'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot'
		);

		foreach ( $bots as $bot ) {
			if ( stripos( $user_agent, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}

	public function get_analytics_data( $args = array() )
	{
		global $wpdb;

		$defaults = array(
			'post_id' => null,
			'start_date' => null,
			'end_date' => null,
			'group_by' => 'day', // day, month, year
			'limit' => 100
		);

		$args = wp_parse_args( $args, $defaults );

		// So we can construct the WHERE clause dynamically
		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( $args['post_id'] ) {
			$where_conditions[] = 'post_id = %d';
			$where_values[] = $args['post_id'];
		}

		if ( $args['start_date'] ) {
			$where_conditions[] = 'visit_date >= %s';
			$where_values[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where_conditions[] = 'visit_date <= %s';
			$where_values[] = $args['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );

		switch ( $args['group_by'] ) {
			case 'month':
				$date_format = '%%Y-%%m';
				$group_by = 'DATE_FORMAT(visit_date, "%%Y-%%m")';
				break;
			case 'year':
				$date_format = '%%Y';
				$group_by = 'DATE_FORMAT(visit_date, "%%Y")';
				break;
			default:
				$date_format = '%%Y-%%m-%%d';
				$group_by = 'DATE_FORMAT(visit_date, "%%Y-%%m-%%d")';
		}

		$sql = "SELECT 
			DATE_FORMAT(visit_date, \"$date_format\") as period,
			COUNT(*) as visits,
			COUNT(DISTINCT user_ip) as unique_visitors,
			COUNT(DISTINCT post_id) as unique_posts,
			SUM(is_logged_in) as logged_in_visits
			FROM $this->table_name 
			WHERE $where_clause 
			GROUP BY $group_by 
			ORDER BY period DESC 
			LIMIT %d";

		$where_values[] = $args['limit'];


		$query = $wpdb->prepare( $sql, ...$where_values );
		$result = $wpdb->get_results( $query, ARRAY_A );

		return $result;
	}

	public function get_top_posts( $args = array() )
	{
		global $wpdb;

		$defaults = array(
			'start_date' => null,
			'end_date' => null,
			'limit' => 10
		);

		$args = wp_parse_args( $args, $defaults );

		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( $args['start_date'] ) {
			$where_conditions[] = 'a.visit_date >= %s';
			$where_values[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where_conditions[] = 'a.visit_date <= %s';
			$where_values[] = $args['end_date'] . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$sql = "SELECT 
			a.post_id,
			p.post_title,
			p.post_type,
			p.guid as post_url,
			COUNT(*) as visits,
			COUNT(DISTINCT a.user_ip) as unique_visitors
			FROM $this->table_name a
			LEFT JOIN {$wpdb->posts} p ON a.post_id = p.ID
			WHERE $where_clause 
			GROUP BY a.post_id 
			ORDER BY visits DESC 
			LIMIT %d";

		$where_values[] = $args['limit'];

		$query = $wpdb->prepare( $sql, ...$where_values );
		$res = $wpdb->get_results( $query, ARRAY_A );

		return $res;
	}

	public function get_analytics_summary( $start_date = null, $end_date = null )
	{
		global $wpdb;

		$where_conditions = array( '1=1' );
		$where_values = array();

		if ( $start_date ) {
			$where_conditions[] = 'visit_date >= %s';
			$where_values[] = $start_date;
		}

		if ( $end_date ) {
			$where_conditions[] = 'visit_date <= %s';
			$where_values[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where_conditions );

		$sql = "SELECT 
			COUNT(*) as total_visits,
			COUNT(DISTINCT user_ip) as unique_visitors,
			COUNT(DISTINCT post_id) as unique_posts,
			SUM(is_logged_in) as logged_in_visits,
			AVG(CASE WHEN is_logged_in = 0 THEN 1 ELSE 0 END) * 100 as bounce_rate
			FROM $this->table_name 
			WHERE $where_clause";

		if ( !empty( $where_values ) ) {
			$query = $wpdb->prepare( $sql, ...$where_values );
		} else {
			$query = $sql;
		}

		return $wpdb->get_row( $query, ARRAY_A );
	}
}