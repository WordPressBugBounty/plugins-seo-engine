<?php

// Feeds the SEO block in AI Engine's Modules tab through its mwai_seo_stats filter.
// AI Engine renders whatever tiles arrive, generically. Rule of that block: never
// send an invented number; an unavailable tile carries a reason code (and optionally
// an action link) instead of a value.
class Meow_MWSEO_Modules_AIEngine
{
	private $core;

	public function __construct( $core ) {
		$this->core = $core;
		add_filter( 'mwai_seo_stats', array( $this, 'mwai_seo_stats' ) );
	}

	public function mwai_seo_stats( $stats ) {
		// Only offered when the robots.txt editor is actually enabled; AI Engine
		// falls back to a plain "View" link on the served /robots.txt otherwise.
		$robots_url = $this->core->get_option( 'robot_editor', false )
			? admin_url( 'admin.php?page=mwseo_settings&nekoTab=settings&section=technical' )
			: null;
		return array(
			'provider' => array(
				'name' => 'SEO Engine',
				'version' => MWSEO_VERSION,
				'admin_url' => admin_url( 'admin.php?page=mwseo_settings' ),
			),
			'robots_url' => $robots_url,
			'tiles' => array(
				'ai_bot_visits' => $this->tile_ai_bot_visits(),
				'ai_visibility' => $this->tile_ai_visibility(),
				'top_ai_bots' => $this->tile_top_ai_bots(),
			),
		);
	}

	private function tile( $label, $args = array() ) {
		return array_merge( array(
			'label' => $label,
			'available' => false,
			'value' => null,
			'period' => null,
			'reason' => null,
			'action' => null,
		), $args );
	}

	private function tile_ai_bot_visits() {
		$label = __( 'AI bot visits', 'seo-engine' );
		// Bot tracking only depends on bots_track: general_analytics gates the human
		// visit tracking, a separate table (see analytics.php init vs track_visit).
		$tracking = $this->core->get_option( 'bots_track', false );
		if ( !$tracking ) {
			// The Track toggle lives in the Traffic Analytics settings section, which is
			// only reachable when the Analytics module is on; otherwise land on Modules.
			$section = $this->core->get_option( 'analytics', false ) ? 'analytics' : 'modules';
			return $this->tile( $label, array(
				'reason' => 'tracking_off',
				'action' => array(
					'label' => __( 'Enable tracking', 'seo-engine' ),
					'url' => admin_url( 'admin.php?page=mwseo_settings&nekoTab=settings&section=' . $section ),
				),
			) );
		}
		// The analytics module is private on core; this public wrapper returns an
		// empty array when the module is unavailable, which lands on no_data below.
		$data = $this->core->query_bot_traffic( array(
			'bot_type' => 'ai',
			'start_date' => date( 'Y-m-d', strtotime( '-7 days' ) ),
		) );
		$visits = isset( $data['aggregates']['total_visits'] ) ? (int) $data['aggregates']['total_visits'] : 0;
		if ( $visits === 0 ) {
			return $this->tile( $label, array( 'reason' => 'no_data' ) );
		}
		return $this->tile( $label, array(
			'available' => true,
			'value' => $visits,
			'period' => __( '7 days', 'seo-engine' ),
		) );
	}

	private function tile_ai_visibility() {
		$label = __( 'AI visibility', 'seo-engine' );
		if ( !isset( $this->core->pro->ai_visibility ) ) {
			return $this->tile( $label, array( 'reason' => 'pro_required' ) );
		}
		$module = $this->core->pro->ai_visibility;
		if ( !$module->is_enabled() ) {
			// AI Engine has no dedicated copy for this code and falls back to its
			// generic empty state, which is what we want; the action link does the work.
			return $this->tile( $label, array(
				'reason' => 'disabled',
				'action' => array(
					'label' => __( 'Enable AI Visibility', 'seo-engine' ),
					'url' => admin_url( 'admin.php?page=mwseo_settings&nekoTab=settings&section=modules' ),
				),
			) );
		}
		$overview = $module->get_overview();
		if ( empty( $overview['brand_count'] ) ) {
			return $this->tile( $label, array(
				'reason' => 'no_brand',
				'action' => array(
					'label' => __( 'Set up your brand', 'seo-engine' ),
					'url' => admin_url( 'admin.php?page=mwseo_settings&nekoTab=ai-visibility' ),
				),
			) );
		}
		if ( !isset( $overview['global_score'] ) ) {
			return $this->tile( $label, array( 'reason' => 'no_data' ) );
		}
		return $this->tile( $label, array(
			'available' => true,
			'value' => (int) $overview['global_score'],
			'period' => __( 'score', 'seo-engine' ),
		) );
	}

	// A `list` tile: AI Engine renders the rows (label + value) instead of one big number.
	private function tile_top_ai_bots() {
		$label = __( 'Top AI bots', 'seo-engine' );
		if ( !$this->core->get_option( 'bots_track', false ) ) {
			$section = $this->core->get_option( 'analytics', false ) ? 'analytics' : 'modules';
			return $this->tile( $label, array(
				'reason' => 'tracking_off',
				'action' => array(
					'label' => __( 'Enable tracking', 'seo-engine' ),
					'url' => admin_url( 'admin.php?page=mwseo_settings&nekoTab=settings&section=' . $section ),
				),
			) );
		}
		$ai_bots = $this->core->get_bots_by_type( 'ai' );
		$summary = $this->core->get_ai_agents_summary( date( 'Y-m-d', strtotime( '-7 days' ) ) );
		$top = array();
		foreach ( (array) $summary as $row ) {
			// The summary covers every tracked crawler; keep only the AI ones here.
			if ( in_array( $row['bot_name'], $ai_bots, true ) ) {
				$top[] = array(
					'label' => $row['bot_name'],
					'value' => (int) $row['visit_count'],
				);
			}
			if ( count( $top ) >= 3 ) {
				break;
			}
		}
		if ( empty( $top ) ) {
			return $this->tile( $label, array( 'reason' => 'no_data' ) );
		}
		return $this->tile( $label, array(
			'available' => true,
			'list' => $top,
			'period' => __( '7 days', 'seo-engine' ),
		) );
	}
}
