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
        add_action( 'wp_after_insert_post', array( $this, 'create_sitemap_callback' ), 10, 3 );

        // Generate the sitemap on post deletion
        // add_action( 'delete_post', array( $this, 'create_sitemap_callback' ), 10, 3 );



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
    $posts = get_posts( $this->core->apply_language_filter( [
      'post_type' => $post_type,
      'posts_per_page' => $this->core->get_option( 'sitemap_max_urls', 100 ),
      'paged' => $page,
      'fields' => 'ids',
    ], 'all' ) );

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
    // A sitemap covers every language, and unlike get_url_list() below this is a
    // WP_Query, so Bogo would narrow the count down to the site locale.
    $args = $this->core->apply_language_filter( $args, 'all' );

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


  public function create_sitemap_callback( $post, $update, $before_post )
  {
    $post = is_numeric( $post ) ? get_post( $post ) : $post;
    if ( wp_is_post_revision( $post ) || $post->post_status != 'publish' ) {
      return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    if ( get_transient( 'mwseo_sitemap_generating' ) ) {
        return;
    }
    
    set_transient( 'mwseo_sitemap_generating', true, 5 );

    $this->core->log( "🗺️ Sitemap was generated automatically ( Post ID: {$post->ID}, Post Title: {$post->post_title} )" );

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
        $sub_sitemap_data = $this->create_sitemap_for_post_type( $post_type, $excluded_posts );
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

    if ( ! function_exists( 'get_home_path' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $home_path = get_home_path();

    if ( ! is_writable( $home_path ) && ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
        $home_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR;
    }

    $filepath = $home_path . $filename;
    $sitepath = site_url( '/' . $filename );

    if ( !empty( $index_xml ) ) {
        $content = $index_xml;
        
        $f = fopen( $filepath, 'w+' );
        fwrite( $f, $content );
        fclose( $f );
    }

    $realpath = realpath( $filepath );

    $this->core->update_option( 'sitemap_path', $sitepath );

    return [
        'filename' => $filename,
        'filepath' => $realpath,
        'url'      => $sitepath,
    ];
}

private function create_sitemap_for_post_type( $post_type, $excluded_posts = [] ) {

    $exlcude_posts_provider = $this->core->get_option( 'sitemap_exclude_posts_provider', false );
    if ( $exlcude_posts_provider ) return false;

    // A sitemap covers every language, whatever the request's current one is
    // (Polylang sets it to the saved post's language on post.php).
    $posts = get_posts( $this->core->apply_language_filter( [
        'post_type'      => $post_type,
        'post__not_in'   => $excluded_posts,
        'orderby'        => 'modified',
        'order'          => 'DESC',
        'posts_per_page' =>  $this->core->get_option( 'sitemap_max_urls', 100 ),
    ], 'all' ) );

    // If no posts found, maybe skip generating a file
    if ( empty( $posts ) ) {
        return false;
    }

    // Build sub-sitemap XML
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    foreach ( $posts as $post ) {
        setup_postdata( $post );
        $post_lastmod = $post->post_modified;
        $lastmod_iso8601 = mysql2date( 'c', $post_lastmod, false );

        $xml .= "  <url>\n";
        $xml .= "    <loc>" . esc_url( get_permalink( $post->ID ) ) . "</loc>\n";
        $xml .= "    <lastmod>" . esc_html( $lastmod_iso8601 ) . "</lastmod>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>1.0</priority>\n";
        $urls = [];
        foreach ( $this->get_translations( $post->ID, $post_type, false ) as $lang => $id ) {
            if ( get_post_status( $id ) === 'publish' && ! in_array( (int) $id, $excluded_posts, true ) ) {
                $urls[ $lang ] = get_permalink( $id );
            }
        }
        $xml .= $this->hreflang_links( $urls );
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

    $exclude_taxonomies_provider = $this->core->get_option( 'sitemap_exclude_taxonomies_provider', false );
    if ( $exclude_taxonomies_provider ) return false;

    // WPML narrows get_terms() to its current language; the sitemap needs them all.
    $wpml_lang = apply_filters( 'wpml_current_language', null );
    if ( $wpml_lang ) {
        do_action( 'wpml_switch_language', 'all' );
    }

    // Get all public terms in the taxonomy
    $terms = get_terms( $this->core->apply_language_filter( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ], 'all' ) );

    if ( $wpml_lang ) {
        do_action( 'wpml_switch_language', $wpml_lang );
    }

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return false;
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?xml-stylesheet type="text/xsl" href="' . MWSEO_URL . 'classes/sitemap-style' . $this->style  . '.xsl"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

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
        $urls = [];
        foreach ( $this->get_translations( $term->term_id, $taxonomy, true ) as $lang => $id ) {
            $link = get_term_link( (int) $id, $taxonomy );
            if ( ! is_wp_error( $link ) ) {
                $urls[ $lang ] = $link;
            }
        }
        $xml .= $this->hreflang_links( $urls );
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

// Translations of a post or term (itself included) as [ language slug => id ],
// from Polylang or WPML. Empty when neither is active.
private function get_translations( $id, $type, $is_term ) {
    if ( function_exists( 'pll_get_post_translations' ) ) {
        return $is_term ? pll_get_term_translations( $id ) : pll_get_post_translations( $id );
    }
    $ids = [];
    foreach ( array_keys( (array) apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] ) ) as $lang ) {
        $translated = apply_filters( 'wpml_object_id', $id, $type, false, $lang );
        if ( $translated ) {
            $ids[ $lang ] = $translated;
        }
    }
    return $ids;
}

// Translations as <xhtml:link> alternates, keyed by language slug.
// The list includes the URL itself, as Google requires.
private function hreflang_links( $urls ) {
    if ( count( $urls ) < 2 ) {
        return '';
    }
    $codes = function_exists( 'pll_languages_list' )
        ? array_combine( pll_languages_list(), pll_languages_list( [ 'fields' => 'w3c' ] ) )
        : array_column( (array) apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] ), 'tag', 'code' );
    $xml = '';
    foreach ( $urls as $lang => $url ) {
        $code = $codes[ $lang ] ?? $lang;
        $xml .= '    <xhtml:link rel="alternate" hreflang="' . esc_attr( $code ) . '" href="' . esc_url( $url ) . '" />' . "\n";
    }
    return $xml;
}

private function create_sitemap_for_users() {
    $exclude_users_provider = $this->core->get_option( 'sitemap_exclude_users_provider', false );
    if ( $exclude_users_provider ) return false;

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