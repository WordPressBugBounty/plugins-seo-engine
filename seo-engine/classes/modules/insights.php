<?php

class Meow_MWSEO_Modules_Insights
{
  private $core = null;
  private $ready = false;
  private $key = null;
  private $timeout = 120; // Timeout in seconds

  private $url = "https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed";
  private $transient_name = 'mwseo_last_insights';

  // Constructor to initialize the class
  public function __construct( $core )
  {
    $this->core = $core;
    $this->init();
  }

  // Initialize Google Insights settings
  public function init()
  {
    if ( !$this->check_api_settings() ) {
      return;
    }

    $this->ready = true;
  }

  function check_api_settings()
  {
    $this->key = $this->core->get_option( 'google_api_key', '' );

    if ( empty( $this->key ) ) {
      return false;
    }

    return true;
  }

  function get_insights_for_post( $post_id )
  {
    if( $post_id === 'delete' ) {
      $cached = get_transient( $this->transient_name );
      if ( $cached !== false ) {
        delete_transient( $this->transient_name );
        $this->core->log( '✅ Google Insights cache cleared.' );
        return true;
      }
      $this->core->log( '⚠️ No Google Insights cache found to clear.' );
      return false;
    }
    
    $url = $post_id != 'main' ? get_permalink( $post_id ) : get_home_url();

    if ( empty( $url ) ) {
      return false;
    }

    return $this->get_insights( $url );
  }

  function get_insights( $url )
  {
    if ( !$this->ready ) {
      $this->core->log( '⚠️ Google Insights not ready.' );
      return false;
    }

    $categories = ['performance', 'accessibility', 'best-practices', 'seo'];
    $categoryQueryString = '';
    foreach ( $categories as $category ) {
        $categoryQueryString .= '&category=' . urlencode( $category );
    }

    $args = array(
      'url' => $url,
      //'url' => 'https://www.meowapps.com/', //! For testing purposes
      'key' => $this->key,
    );

    $queryString = http_build_query( $args );
    $fullQueryString = $queryString . $categoryQueryString;

    $response = wp_remote_get( $this->url . '?' . $fullQueryString, array(
        'timeout' => $this->timeout,
        'headers' => array(
            'referer' => home_url(),
        ),
    ) );

    if ( is_wp_error( $response ) ) {
      return false;
    }

    $result = json_decode( wp_remote_retrieve_body( $response ), true );
    set_transient( $this->transient_name, $result );

    return $result;
  }

  function get_last_insights()
  {
    $cached = get_transient( $this->transient_name );

    if ( $cached === false ) {
      return false;
    }

    return $cached;
  }

}