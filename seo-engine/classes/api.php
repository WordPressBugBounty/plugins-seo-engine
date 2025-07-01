<?php

class Meow_MWSEO_API {

  public $core;

  public function __construct( $core ) {
    $this->core = $core;
  }

  #region SEO Settings

  public function get_seo_title( $post_id ) {
    if ( ! $post_id ) { return [ "success" => false, "message" => "Post ID is required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $seo_title = $this->core->get_seo_title( $post );
    return [ "success" => true, "data" => $seo_title ];
  }

  public function set_seo_title( $post_id, $title ) {
    if ( ! $post_id || ! $title ) { return [ "success" => false, "message" => "Post ID and title are required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $result = $this->core->set_seo_title( $post, $title );
    return [ "success" => true, "data" => $result ];
    }

  public function get_seo_excerpt( $post_id ) {
    if ( ! $post_id ) { return [ "success" => false, "message" => "Post ID is required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $seo_excerpt = $this->core->get_seo_excerpt( $post );
    return [ "success" => true, "data" => $seo_excerpt ];
    }

  public function set_seo_excerpt( $post_id, $excerpt ) {
    if ( ! $post_id || ! $excerpt ) { return [ "success" => false, "message" => "Post ID and excerpt are required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $result = $this->core->set_seo_excerpt( $post, $excerpt );
    return [ "success" => true, "data" => $result ];
    }

  #endregion

  #region SEO Score

  public function get_seo_score( $post_id ) {
    if ( ! $post_id ) { return [ "success" => false, "message" => "Post ID is required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $result = $this->core->get_seo_engine_post_meta( $post );
    return $result;
    }

  public function do_seo_scan( $post_id ) {
    if ( ! $post_id ) { return [ "success" => false, "message" => "Post ID is required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $result = $this->core->calculate_seo_score( $post );
    return $result;
    }

  public function get_scored_posts() {
    return $this->core->get_all_posts_with_seo_score();
    }

  #endregion

  #region Insights

  public function get_insights( $post_id ) {
    if ( ! $post_id ) { return [ "success" => false, "message" => "Post ID is required." ]; }

    $post = get_post( $post_id );
    if ( ! $post ) { return [ "success" => false, "message" => "Post not found." ]; }

    $insights = $this->core->get_speed_and_vitals( $post_id );
    if ( ! $insights ) {
      return [ "success" => false, "message" => "No insights available for this post." ];
    }

    return [
      "success" => true,
      "data" => $insights,
      "message" => "Insights retrieved successfully."
    ];
    }

  #endregion

  #region Robots.txt

  public function get_robots_txt() {
    $robots = $this->core->get_robots_txt();
    $content = $robots['content'] ?? '';

    return $content;
    }

  public function set_robots_txt( $content ) {
    $result = $this->core->set_robots_txt( $content );
    return $result;
  }

  #endregion

  #region Google Analytics

  public function get_google_analytics_monthly_summary( ) {
    $summary = $this->core->get_google_analytics_summary( );
    return [ "success" => !empty( $summary ), "data" => $summary ];
    }

  public function get_google_analytics_range_summary( $start_date, $end_date ) {
    if ( ! $start_date || ! $end_date ) {
      return [ "success" => false, "message" => "Start date and end date are required." ];
    }

    $summary = $this->core->get_google_analytics_summary( $start_date, $end_date );
    return [ "success" => !empty( $summary ), "data" => $summary ];
    }

  public function get_google_analytics_monthly_top_posts( ) {
    $top_posts = $this->core->get_google_analytics_top_posts( );

    return [ "success" => !empty( $top_posts ), "data" => $top_posts ];
    }

  public function get_google_analytics_range_top_posts( $start_date, $end_date ) {
    if ( ! $start_date || ! $end_date ) {
      return [ "success" => false, "message" => "Start date and end date are required." ];
    }

    $args = [
      'start_date' => $start_date,
      'end_date' => $end_date,
    ];

    $top_posts = $this->core->get_google_analytics_top_posts( $args );
    return [ "success" => !empty( $top_posts ), "data" => $top_posts ];
    }

  public function get_google_analytics_monthly_top_posts_by_country( $country = null ) {
    $top_posts = $this->core->get_google_analytics_top_posts( );

    if( !empty( $country ) && $country != 'all' ) {
      $top_posts = array_filter( $top_posts, function( $post ) use ( $country ) {
        return isset( $post['country'] ) && $post['country'] === $country;
      });
    }

    return [ "success" => !empty( $top_posts ), "data" => $top_posts ];
    }

  public function get_google_analytics_top_countries( ) {
    $top_posts = $this->core->get_google_analytics_top_posts( );
    
    $countries = [];
    foreach ( $top_posts as $post ) {
      if ( isset( $post['country'] ) && ! in_array( $post['country'], $countries ) ) {
        $countries[] = $post['country'];
      }
    }

    return [ "success" => !empty( $countries ), "data" => $countries ];
    }

  #endregion

}