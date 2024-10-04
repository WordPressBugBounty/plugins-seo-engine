<?php

class Meow_MWSEO_Sitemap extends WP_Sitemaps_Provider
{
  private $core = null;

  public function __construct( $core )
  {
    $this->core = $core;
    $this->init();
  }

  function init(  )
  {
    $disabled = $this->core->get_option( 'seo_engine_disable_wp_sitemap', false );
    if ( $disabled ) {
      add_filter( 'wp_sitemaps_enabled', '__return_false' );
      return;
    }

    // Excluding Providers
    $excluded_providers = [
      $this->core->get_option( 'seo_engine_sitemap_exclude_users_provider', false ) ? 'users' : null,
      $this->core->get_option( 'seo_engine_sitemap_exclude_posts_provider', false ) ? 'posts' : null,
      $this->core->get_option( 'seo_engine_sitemap_exclude_taxonomies_provider', false ) ? 'taxonomies' : null,
    ];

    add_filter( 
      'wp_sitemaps_add_provider',
      function ( $provider, $name ) use ( $excluded_providers ) {
        if ( in_array( $name, $excluded_providers ) ) {
          return false;
        }

        return $provider;
      },
      10,
      2
     );

    //Exclude Post Types
    $excluded_post_types = $this->core->get_option( 'seo_engine_sitemap_excluded_post_types', [] );
    add_filter( 
      'wp_sitemaps_post_types',
      function ( $post_types ) use ( $excluded_post_types ) {
        return array_values( array_diff( $post_types, $excluded_post_types ) );
      }
     );

    //Exclude Taxonomies
    $excluded_taxonomies = $this->core->get_option( 'seo_engine_sitemap_excluded_taxonomies', [] );
    add_filter( 
      'wp_sitemaps_taxonomies',
      function ( $taxonomies ) use ( $excluded_taxonomies ) {
        return array_values( array_diff( $taxonomies, $excluded_taxonomies ) );
      }
     );


    //Exclude specific posts
    $excluded_posts = $this->core->get_option( 'seo_engine_sitemap_excluded_post_ids', [] );
    try {
      $excluded_posts = array_map( 'intval', $excluded_posts );
    } catch ( Exception $e ) {
      $excluded_posts = [];
      $this->core->log( '❌ ( Sitemap ) Error parsing excluded post ids.' );
    }
    add_filter( 
      'wp_sitemaps_posts_query_args',
      function ( $args, $post_type ) use ( $excluded_posts ) {
        if ( $post_type !== 'post' ) {
          return $args;
        }
        $args['post__not_in'] = isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(  );
        $args['post__not_in'] = array_merge( $args['post__not_in'], $excluded_posts );

        return $args;
      },
      10,
      2
     );
  }

  public function get_url_list( $page, $post_type = null )
  {
    $urls = parent::get_url_list( $page, $post_type );
    return $urls;
  }

  public function get_max_num_pages( $object_subtype = '' )
  {
    $max_pages = parent::get_max_num_pages( $object_subtype );
    return $max_pages;
  }
}
