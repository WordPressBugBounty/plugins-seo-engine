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
    $disabled = $this->core->get_option( 'disable_wp_sitemap', false );
    $custom = $this->core->get_option( 'sitemap_custom', false );
    if ( $disabled ) {
      add_filter( 'wp_sitemaps_enabled', '__return_false' );

      // Make our own sitemap if the WP sitemap is disabled
      if ( $custom ) {
        // Generate the sitemap on a new post save
        add_action( 'save_post', array( $this, 'create_sitemap_callback' ), 10, 3 );

        // Generate the sitemap on post deletion
        add_action( 'delete_post', array( $this, 'create_sitemap_callback' ), 10, 3 );



        add_filter( 'robots_txt', array( $this, 'add_sitemap_to_robots' ), 10, 1 );
      }


      return;
    }

    // Excluding Providers
    $excluded_providers = [
      $this->core->get_option( 'sitemap_exclude_users_provider', false ) ? 'users' : null,
      $this->core->get_option( 'sitemap_exclude_posts_provider', false ) ? 'posts' : null,
      $this->core->get_option( 'sitemap_exclude_taxonomies_provider', false ) ? 'taxonomies' : null,
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

    // Exclude Post Types
    $excluded_post_types = $this->core->get_option('sitemap_excluded_post_types', []);
    add_filter(
        'wp_sitemaps_post_types',
        function ($post_types) use ($excluded_post_types) {

            foreach ($excluded_post_types as $excluded_post_type) {
                if (isset($post_types[$excluded_post_type])) {
                    unset($post_types[$excluded_post_type]);
                }
            }

            return $post_types;
        },
        10,
        1
    );

    //Exclude Taxonomies
    $excluded_taxonomies = $this->core->get_option( 'sitemap_excluded_taxonomies', [] );
    add_filter( 
      'wp_sitemaps_taxonomies',
      function ( $taxonomies ) use ( $excluded_taxonomies ) {

        foreach( $excluded_taxonomies as $excluded_taxonomy ) {
          if ( isset( $taxonomies[$excluded_taxonomy] ) ) {
            unset( $taxonomies[$excluded_taxonomy] );
          }
        }

        return $taxonomies;
      }
     );


    //Exclude specific posts
    $excluded_posts = $this->core->get_option( 'sitemap_excluded_post_ids', [] );
    try {
      $excluded_posts = array_map( 'intval', $excluded_posts );
    } catch ( Exception $e ) {
      $excluded_posts = [];
      $this->core->log( '❌ ( Sitemap ) Error parsing excluded post ids.' );
    }
    add_filter( 
      'wp_sitemaps_posts_query_args',
      function ( $args, $post_type ) use ( $excluded_posts ) {
        // if ( $post_type !== 'post' ) {
        //   return $args;
        // }
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
    $urls = [];
    $posts = get_posts( [
      'post_type' => $post_type,
      'posts_per_page' => $this->core->get_option( 'sitemap_max_urls', 100 ),
      'paged' => $page,
      'fields' => 'ids',
    ] );

    foreach ( $posts as $post_id ) {
      $urls[] = get_permalink( $post_id );
    }

    return $urls;
  }

  public function get_max_num_pages( $object_subtype = '' )
  {
    $post_type = $object_subtype;
    $args = [
      'post_type' => $post_type,
      'posts_per_page' => $this->core->get_option( 'sitemap_post_max_pages', 100 ),
      'fields' => 'ids',
    ];

    $query = new WP_Query( $args );
    return $query->max_num_pages;
  }

  public function add_sitemap_to_robots( $output )
  {
    // Remove existing Sitemap line
    $output = preg_replace("/Sitemap: .*/", "", $output);

    // Append new Sitemap line with correct WordPress URL
    $output .= "Sitemap: " . $this->core->get_option( 'sitemap_path' ) . "\n";
    return $output;
  }


  public function create_sitemap_callback( $post_id, $post )
  {
    if ( wp_is_post_revision( $post_id ) || $post->post_status != 'publish' ) {
      return;
    }
    $this->core->log( "🗺️ Sitemap was generated automatically ( Post ID: {$post_id}, Post Title: {$post->post_title} )" );
    $this->create_sitemap();
  }

  private $style = null;
  public function create_sitemap() {
    // Using the same settings as the WP core sitemap
    $excluded_post_types = $this->core->get_option( 'sitemap_excluded_post_types', [] );
    $excluded_posts      = $this->core->get_option( 'sitemap_excluded_post_ids', [] );
    $excluded_taxonomies = $this->core->get_option( 'sitemap_excluded_taxonomies', [] );
    
    $sitemap_style       = $this->core->get_option( 'sitemap_style', 'default' );
    $sitemap_style       = $sitemap_style == 'default' ? '' : '-' . $sitemap_style;
    $this->style         = $sitemap_style;

    // Convert excluded posts to integers (and log errors if any)
    try {
        $excluded_posts = array_map( 'intval', $excluded_posts );
    } catch ( Exception $e ) {
        $excluded_posts = [];
        $this->core->log( '❌ (Sitemap) Error parsing excluded post ids.' );
    }

    // * We want the same structure as the WP default sitemap ( With sub-sitemaps )
    // We’ll store each sub-sitemap file path + URL here
    $sitemaps = []; 

    // Remove the ones excluded by the user in the plugin options
    $public_post_types = get_post_types( [ 'public' => true ], 'names' );
    foreach ( $excluded_post_types as $excluded_type ) {
        if ( isset( $public_post_types[ $excluded_type ] ) ) {
            unset( $public_post_types[ $excluded_type ] );
        } elseif ( ( $key = array_search( $excluded_type, $public_post_types ) ) !== false ) {
            unset( $public_post_types[ $key ] );
        }
    }

    // Then create sub-sitemap for each post type
    foreach ( $public_post_types as $post_type ) {
        $sub_sitemap_data = $this->create_sitemap_for_post_type( $post_type, $excluded_posts, $excluded_taxonomies );
        // If successfully created, add it to index
        if ( $sub_sitemap_data && ! empty( $sub_sitemap_data['filename'] ) ) {
            $sitemaps[] = [
                'loc' => site_url( '/' . $sub_sitemap_data['filename'] ),
            ];
        }
    }

    // We do the same for taxonomies
    $public_taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
    foreach ( $excluded_taxonomies as $excluded_tax ) {
        if ( isset( $public_taxonomies[ $excluded_tax ] ) ) {
            unset( $public_taxonomies[ $excluded_tax ] );
        }
    }

    // Create sub-sitemap for each taxonomy
    foreach ( $public_taxonomies as $tax_name ) {
        $sub_sitemap_data = $this->create_sitemap_for_taxonomy( $tax_name );
        if ( $sub_sitemap_data && ! empty( $sub_sitemap_data['filename'] ) ) {
            $sitemaps[] = [
                'loc' => site_url( '/' . $sub_sitemap_data['filename'] ),
            ];
        }
    }

    // We do the same for users
    $exclude_users_provider = $this->core->get_option( 'sitemap_exclude_users_provider', false );
    if ( ! $exclude_users_provider ) {
        $sub_sitemap_data = $this->create_sitemap_for_users();
        if ( $sub_sitemap_data && ! empty( $sub_sitemap_data['filename'] ) ) {
            $sitemaps[] = [
                'loc' => site_url( '/' . $sub_sitemap_data['filename'] ),
            ];
        }
    }

    // * Now we create the main sitemap index file
    $index_xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $index_xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
    $index_xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ( $sitemaps as $item ) {
        $index_xml .= "  <sitemap>\n";
        $index_xml .= "    <loc>" . esc_url( $item['loc'] ) . "</loc>\n";
        $index_xml .= "  </sitemap>\n";
    }

    $index_xml .= '</sitemapindex>' . "\n";

    // TODO: Maybe we should have an option to choose the filename
    $filename = 'wp-sitemap.xml';
    $filepath = ABSPATH . $filename;;

    file_put_contents( $filepath, $index_xml );
    $realpath = realpath( $filepath );

    $this->core->update_option( 'sitemap_path', $realpath );

    return $realpath;
}

private function create_sitemap_for_post_type( $post_type, $excluded_posts = [], $excluded_taxonomies = [] ) {
  $tax_query = [];
  if ( ! empty( $excluded_taxonomies ) ) {
      $tax_query['relation'] = 'AND';
      foreach ( $excluded_taxonomies as $taxonomy ) {
          if ( taxonomy_exists( $taxonomy ) ) {
              $all_term_ids = get_terms([
                  'taxonomy'   => $taxonomy,
                  'fields'     => 'ids',
                  'hide_empty' => false,
              ]);
              if ( ! empty( $all_term_ids ) && ! is_wp_error( $all_term_ids ) ) {
                  $tax_query[] = [
                      'taxonomy' => $taxonomy,
                      'field'    => 'term_id',
                      'terms'    => $all_term_ids,
                      'operator' => 'NOT IN',
                  ];
              }
          }
      }
  }

  $posts = get_posts([
      'post_type'      => $post_type,
      'post__not_in'   => $excluded_posts,
      'tax_query'      => $tax_query,
      'orderby'        => 'modified',
      'order'          => 'DESC',
      'posts_per_page' =>  $this->core->get_option( 'sitemap_max_urls', 100 ),
  ]);

  // If no posts found, maybe skip generating a file
  if ( empty( $posts ) ) {
      return false;
  }

  // Build sub-sitemap XML
  $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
  $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach ( $posts as $post ) {
      setup_postdata( $post );
      $postdate = explode( " ", $post->post_modified );
      $xml .= "  <url>\n";
      $xml .= "    <loc>" . esc_url( get_permalink( $post->ID ) ) . "</loc>\n";
      $xml .= "    <lastmod>" . esc_html( $postdate[0] ) . "</lastmod>\n";
      $xml .= "    <changefreq>daily</changefreq>\n";
      $xml .= "    <priority>1.0</priority>\n";
      $xml .= "  </url>\n";
  }

  $xml .= "</urlset>\n";

  // Generate a filename: e.g. wp-sitemap-posts-{$post_type}-1.xml
  $filename = "wp-sitemap-posts-{$post_type}-1.xml";
  $filepath = ABSPATH . $filename;
  file_put_contents( $filepath, $xml );

  wp_reset_postdata();

  return [
      'filename' => $filename,
      'filepath' => realpath( $filepath ),
  ];
}

private function create_sitemap_for_taxonomy( $taxonomy ) {
  // Get all public terms in the taxonomy
  $terms = get_terms([
      'taxonomy'   => $taxonomy,
      'hide_empty' => false,
  ]);

  if ( empty( $terms ) || is_wp_error( $terms ) ) {
      return false;
  }

  $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
  $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach ( $terms as $term ) {
      // The link to a term archive page
      $term_link = get_term_link( $term );
      if ( is_wp_error( $term_link ) ) {
          continue;
      }

      $xml .= "  <url>\n";
      $xml .= "    <loc>" . esc_url( $term_link ) . "</loc>\n";
      $xml .= "    <changefreq>daily</changefreq>\n";
      $xml .= "    <priority>0.8</priority>\n";
      $xml .= "  </url>\n";
  }

  $xml .= "</urlset>\n";

  $filename = "wp-sitemap-taxonomies-{$taxonomy}-1.xml";
  $filepath = ABSPATH . $filename;
  file_put_contents( $filepath, $xml );

  return [
      'filename' => $filename,
      'filepath' => realpath( $filepath ),
  ];
}

private function create_sitemap_for_users() {
  // TODO: maybe filter out non-authors or specific roles with an option?
  // WordPress seems like it only includes users who have authored posts
  $users = get_users([
      'who' => 'authors',
  ]);

  if ( empty( $users ) ) {
      return false;
  }

  $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
  $xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
  $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

  foreach ( $users as $user ) {
      // Author archive link
      $author_link = get_author_posts_url( $user->ID );
      $xml .= "  <url>\n";
      $xml .= "    <loc>" . esc_url( $author_link ) . "</loc>\n";
      $xml .= "    <changefreq>daily</changefreq>\n";
      $xml .= "    <priority>0.5</priority>\n";
      $xml .= "  </url>\n";
  }

  $xml .= "</urlset>\n";

  $filename = "wp-sitemap-users-1.xml";
  $filepath = ABSPATH . $filename;
  file_put_contents( $filepath, $xml );

  return [
      'filename' => $filename,
      'filepath' => realpath( $filepath ),
  ];
}

}
?>