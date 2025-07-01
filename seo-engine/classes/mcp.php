<?php

class Meow_MWSEO_MCP {
  private $core;
  private $api;
  
  public function __construct( $core ) {
    $this->core = $core;
    
    // Initialize everything on 'init' to ensure options are loaded
    add_action( 'init', array( $this, 'init' ), 20 );
  }
  
  public function init() {
    global $mwseo, $mwai;
    $this->api = $mwseo;
    
    // Only register MCP if enabled AND AI Engine is available
    if ( $this->core->get_option( 'mcp_support', false ) && isset( $mwai ) ) {
      // Register MCP tools
      add_filter( 'mwai_mcp_tools', array( $this, 'register_tools' ) );
      
      // Handle MCP tool execution
      add_filter( 'mwai_mcp_callback', array( $this, 'handle_tool_execution' ), 10, 4 );
    }
  }
  
  public function register_tools( $tools ) {
    // IMPORTANT: When defining inputSchema with no properties, do NOT include an empty 
    // 'properties' => [] array. This can cause MCP parsers to fail silently. 
    // Either omit the properties key entirely or ensure at least one property exists.
    
    // SEO Title Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_title',
      'description' => 'Get the SEO title (meta title) for a specific post or page. This is the title that appears in search engine results and browser tabs.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID. You can get this from other tools or by searching for posts.'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_set_seo_title',
      'description' => 'Set or update the SEO title (meta title) for a post or page. This title appears in search results and should be 50-60 characters for optimal display.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to update'
          ],
          'title' => [
            'type' => 'string',
            'description' => 'The SEO title to set. Recommended length: 50-60 characters. This will appear in search engine results.'
          ]
        ],
        'required' => ['post_id', 'title']
      ]
    ];

    // SEO Excerpt Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_excerpt',
      'description' => 'Get the SEO meta description for a post',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The post ID'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_set_seo_excerpt',
      'description' => 'Set the SEO meta description for a post',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The post ID'
          ],
          'excerpt' => [
            'type' => 'string',
            'description' => 'The meta description text'
          ]
        ],
        'required' => ['post_id', 'excerpt']
      ]
    ];

    // SEO Score Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_score',
      'description' => 'Get the current SEO score and metadata for a post. Returns score from 0 to 100, grade from A to F, and detailed SEO analysis including title, description, keywords, and readability metrics.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to analyze'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_do_seo_scan',
      'description' => 'Perform a comprehensive SEO analysis on a post. This calculates the SEO score, analyzes content quality, checks meta tags, evaluates readability, and provides actionable recommendations.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to scan and analyze'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_scored_posts',
      'description' => 'Get a list of all posts that have been analyzed with their SEO scores. Useful for identifying posts that need SEO improvements.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];

    // Insights
    $tools[] = [
      'name' => 'mwseo_get_insights',
      'description' => 'Analyze website speed, performance, and health using Google PageSpeed Insights. Get comprehensive metrics including performance score, accessibility rating, best practices compliance, SEO technical audit, Core Web Vitals (LCP, FID, CLS), loading times, and actionable recommendations for improvements. This is a complete website health check tool.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to get insights for'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    // Robots.txt Operations
    $tools[] = [
      'name' => 'mwseo_get_robots_txt',
      'description' => 'Get the current robots.txt content. This file controls how search engine crawlers access your website.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_set_robots_txt',
      'description' => 'Update the robots.txt content to control search engine crawler access. Be careful as incorrect rules can block search engines from indexing your site.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'content' => [
            'type' => 'string',
            'description' => 'The complete robots.txt content. Should follow robots.txt syntax with User-agent and Disallow/Allow directives.'
          ]
        ],
        'required' => ['content']
      ]
    ];
    
    // Google Analytics Operations
    $tools[] = [
      'name' => 'mwseo_get_analytics_monthly_summary',
      'description' => 'Get Google Analytics summary for the current month including visitors, pageviews, sessions, bounce rate, and average session duration.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_analytics_range_summary',
      'description' => 'Get Google Analytics summary for a custom date range. Returns traffic metrics, user behavior, and engagement statistics.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'start_date' => [
            'type' => 'string',
            'description' => 'Start date in YYYY-MM-DD format'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'End date in YYYY-MM-DD format'
          ]
        ],
        'required' => ['start_date', 'end_date']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_analytics_monthly_top_posts',
      'description' => 'Get the most visited posts/pages for the current month from Google Analytics. Shows pageviews, unique visitors, and engagement metrics.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_analytics_range_top_posts',
      'description' => 'Get top posts from Google Analytics for a date range',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'start_date' => [
            'type' => 'string',
            'description' => 'Start date (YYYY-MM-DD)'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'End date (YYYY-MM-DD)'
          ]
        ],
        'required' => ['start_date', 'end_date']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_analytics_top_posts_by_country',
      'description' => 'Get top performing posts filtered by visitor country. Useful for understanding regional content performance and targeting.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'country' => [
            'type' => 'string',
            'description' => 'ISO country code like US, GB or FR. Use all to see all countries'
          ]
        ]
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_analytics_top_countries',
      'description' => 'Get a list of countries where your visitors come from, sorted by traffic volume. Helps identify your main audience locations.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    // Utility Tools
    $tools[] = [
      'name' => 'mwseo_get_post_by_slug',
      'description' => 'Find a post or page by its URL slug. Returns the post ID and basic information needed for other SEO operations.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'slug' => [
            'type' => 'string',
            'description' => 'The URL slug of the post or page'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'The WordPress post type to search in. Common values: "post" (blog posts), "page" (static pages)',
            'default' => 'post'
          ]
        ],
        'required' => ['slug']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_bulk_seo_scan',
      'description' => 'Perform SEO analysis on multiple posts at once. Efficient for auditing content sections or entire websites.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_ids' => [
            'type' => 'array',
            'description' => 'Array of WordPress post IDs to analyze',
            'items' => [
              'type' => 'integer'
            ]
          ]
        ],
        'required' => ['post_ids']
      ]
    ];
    
    // Advanced SEO Tools
    $tools[] = [
      'name' => 'mwseo_get_posts_by_score_range',
      'description' => 'Find posts within a specific SEO score range. Useful for identifying posts that need improvement or are performing well.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'min_score' => [
            'type' => 'integer',
            'description' => 'Minimum SEO score (0-100)',
            'minimum' => 0,
            'maximum' => 100
          ],
          'max_score' => [
            'type' => 'integer',
            'description' => 'Maximum SEO score (0-100)',
            'minimum' => 0,
            'maximum' => 100
          ]
        ],
        'required' => ['min_score', 'max_score']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_posts_missing_seo',
      'description' => 'Find posts that are missing SEO titles or descriptions. Essential for identifying content gaps in your SEO strategy.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type such as post or page. Leave empty for all types.',
            'default' => ''
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of results to return',
            'default' => 50
          ]
        ]
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_search_posts',
      'description' => 'Search for posts by title or content to find specific content for SEO optimization.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'search_term' => [
            'type' => 'string',
            'description' => 'The term to search for in post titles and content'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type such as post or page',
            'default' => ''
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of results',
            'default' => 20
          ]
        ],
        'required' => ['search_term']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_recent_posts',
      'description' => 'Get recently published posts for SEO review. Helps ensure new content is properly optimized from the start.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Number of days to look back',
            'default' => 7
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type',
            'default' => 'post'
          ]
        ]
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_generate_sitemap_preview',
      'description' => 'Generate a preview of what would be included in the XML sitemap. Useful for understanding site structure and SEO coverage.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by specific post type, or leave empty for all',
            'default' => ''
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of URLs to preview',
            'default' => 100
          ]
        ]
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_check_duplicate_titles',
      'description' => 'Find posts with duplicate or similar SEO titles. Duplicate titles can confuse search engines and hurt rankings.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_seo_statistics',
      'description' => 'Get overall SEO statistics for the website including average scores, completion rates, and improvement opportunities.',
      'category' => 'SEO Engine',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    return $tools;
  }
  
  public function handle_tool_execution( $result, $tool, $args, $id ) {
    // Only handle our tools
    if ( strpos( $tool, 'mwseo_' ) !== 0 ) {
      return $result;
    }
    
    // Ensure API is initialized
    if ( !$this->api ) {
      return [ 'success' => false, 'error' => 'SEO Engine API not initialized' ];
    }
    
    try {
      switch ( $tool ) {
        // SEO Title Operations
        case 'mwseo_get_seo_title':
          return $this->api->get_seo_title( $args['post_id'] );
          
        case 'mwseo_set_seo_title':
          return $this->api->set_seo_title( $args['post_id'], $args['title'] );
          
        // SEO Excerpt Operations
        case 'mwseo_get_seo_excerpt':
          return $this->api->get_seo_excerpt( $args['post_id'] );
          
        case 'mwseo_set_seo_excerpt':
          return $this->api->set_seo_excerpt( $args['post_id'], $args['excerpt'] );
          
        // SEO Score Operations
        case 'mwseo_get_seo_score':
          return $this->api->get_seo_score( $args['post_id'] );
          
        case 'mwseo_do_seo_scan':
          return $this->api->do_seo_scan( $args['post_id'] );
          
        case 'mwseo_get_scored_posts':
          return $this->api->get_scored_posts();
          
        // Insights
        case 'mwseo_get_insights':
          return $this->api->get_insights( $args['post_id'] );
          
        // Robots.txt Operations
        case 'mwseo_get_robots_txt':
          $content = $this->api->get_robots_txt();
          return [ 'success' => true, 'data' => [ 'content' => $content ] ];
          
        case 'mwseo_set_robots_txt':
          $result = $this->api->set_robots_txt( $args['content'] );
          return [ 'success' => true, 'data' => $result ];
          
        // Google Analytics Operations
        case 'mwseo_get_analytics_monthly_summary':
          return $this->api->get_google_analytics_monthly_summary();
          
        case 'mwseo_get_analytics_range_summary':
          return $this->api->get_google_analytics_range_summary( 
            $args['start_date'], 
            $args['end_date'] 
          );
          
        case 'mwseo_get_analytics_monthly_top_posts':
          return $this->api->get_google_analytics_monthly_top_posts();
          
        case 'mwseo_get_analytics_range_top_posts':
          return $this->api->get_google_analytics_range_top_posts( 
            $args['start_date'], 
            $args['end_date'] 
          );
          
        case 'mwseo_get_analytics_top_posts_by_country':
          return $this->api->get_google_analytics_monthly_top_posts_by_country( 
            $args['country'] ?? null 
          );
          
        case 'mwseo_get_analytics_top_countries':
          return $this->api->get_google_analytics_top_countries();
          
        // Utility Tools
        case 'mwseo_get_post_by_slug':
          $post = get_page_by_path( 
            $args['slug'], 
            OBJECT, 
            $args['post_type'] ?? 'post' 
          );
          if ( $post ) {
            return [ 
              'success' => true, 
              'data' => [ 
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type
              ] 
            ];
          }
          return [ 'success' => false, 'error' => 'Post not found' ];
          
        case 'mwseo_bulk_seo_scan':
          $results = [];
          foreach ( $args['post_ids'] as $post_id ) {
            $results[$post_id] = $this->api->do_seo_scan( $post_id );
          }
          return [ 'success' => true, 'data' => $results ];
          
        // Advanced SEO Tools
        case 'mwseo_get_posts_by_score_range':
          $posts = $this->api->get_scored_posts();
          if ( !$posts['success'] ) {
            return $posts;
          }
          
          $filtered = array_filter( $posts['data'], function( $post ) use ( $args ) {
            $score = $post['score'] ?? 0;
            return $score >= $args['min_score'] && $score <= $args['max_score'];
          });
          
          return [ 'success' => true, 'data' => array_values( $filtered ) ];
          
        case 'mwseo_get_posts_missing_seo':
          $query_args = [
            'post_type' => !empty($args['post_type']) ? $args['post_type'] : ['post', 'page'],
            'posts_per_page' => $args['limit'] ?? 50,
            'meta_query' => [
              'relation' => 'OR',
              [
                'key' => $this->core->meta_key_seo_title,
                'compare' => 'NOT EXISTS'
              ],
              [
                'key' => $this->core->meta_key_seo_excerpt,
                'compare' => 'NOT EXISTS'
              ]
            ]
          ];
          
          $posts = get_posts( $query_args );
          $results = [];
          
          foreach ( $posts as $post ) {
            $results[] = [
              'post_id' => $post->ID,
              'post_title' => $post->post_title,
              'post_type' => $post->post_type,
              'permalink' => get_permalink( $post->ID ),
              'missing_title' => !get_post_meta( $post->ID, $this->core->meta_key_seo_title, true ),
              'missing_excerpt' => !get_post_meta( $post->ID, $this->core->meta_key_seo_excerpt, true )
            ];
          }
          
          return [ 'success' => true, 'data' => $results ];
          
        case 'mwseo_search_posts':
          $query_args = [
            's' => $args['search_term'],
            'post_type' => !empty($args['post_type']) ? $args['post_type'] : ['post', 'page'],
            'posts_per_page' => $args['limit'] ?? 20
          ];
          
          $posts = get_posts( $query_args );
          $results = [];
          
          foreach ( $posts as $post ) {
            $results[] = [
              'post_id' => $post->ID,
              'post_title' => $post->post_title,
              'post_type' => $post->post_type,
              'permalink' => get_permalink( $post->ID ),
              'seo_score' => get_post_meta( $post->ID, '_mwseo_score', true ) ?: null
            ];
          }
          
          return [ 'success' => true, 'data' => $results ];
          
        case 'mwseo_get_recent_posts':
          $date_query = [
            [
              'after' => $args['days'] . ' days ago',
              'inclusive' => true
            ]
          ];
          
          $query_args = [
            'post_type' => $args['post_type'] ?? 'post',
            'posts_per_page' => -1,
            'date_query' => $date_query,
            'orderby' => 'date',
            'order' => 'DESC'
          ];
          
          $posts = get_posts( $query_args );
          $results = [];
          
          foreach ( $posts as $post ) {
            $results[] = [
              'post_id' => $post->ID,
              'post_title' => $post->post_title,
              'post_date' => $post->post_date,
              'permalink' => get_permalink( $post->ID ),
              'seo_score' => get_post_meta( $post->ID, '_mwseo_score', true ) ?: null,
              'has_seo_title' => (bool) get_post_meta( $post->ID, $this->core->meta_key_seo_title, true ),
              'has_seo_excerpt' => (bool) get_post_meta( $post->ID, $this->core->meta_key_seo_excerpt, true )
            ];
          }
          
          return [ 'success' => true, 'data' => $results ];
          
        case 'mwseo_generate_sitemap_preview':
          $query_args = [
            'post_type' => !empty($args['post_type']) ? $args['post_type'] : ['post', 'page'],
            'posts_per_page' => $args['limit'] ?? 100,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
          ];
          
          $posts = get_posts( $query_args );
          $urls = [];
          
          foreach ( $posts as $post ) {
            $urls[] = [
              'loc' => get_permalink( $post->ID ),
              'lastmod' => get_post_modified_time( 'c', false, $post ),
              'post_title' => $post->post_title,
              'post_type' => $post->post_type
            ];
          }
          
          return [ 'success' => true, 'data' => $urls ];
          
        case 'mwseo_check_duplicate_titles':
          $all_titles = [];
          $duplicates = [];
          
          $posts = get_posts( [
            'post_type' => ['post', 'page'],
            'posts_per_page' => -1,
            'post_status' => 'publish'
          ] );
          
          foreach ( $posts as $post ) {
            $seo_title = get_post_meta( $post->ID, $this->core->meta_key_seo_title, true );
            if ( $seo_title ) {
              if ( isset( $all_titles[$seo_title] ) ) {
                if ( !isset( $duplicates[$seo_title] ) ) {
                  $duplicates[$seo_title] = [ $all_titles[$seo_title] ];
                }
                $duplicates[$seo_title][] = [
                  'post_id' => $post->ID,
                  'post_title' => $post->post_title,
                  'permalink' => get_permalink( $post->ID )
                ];
              } else {
                $all_titles[$seo_title] = [
                  'post_id' => $post->ID,
                  'post_title' => $post->post_title,
                  'permalink' => get_permalink( $post->ID )
                ];
              }
            }
          }
          
          return [ 'success' => true, 'data' => $duplicates ];
          
        case 'mwseo_get_seo_statistics':
          $stats = [
            'total_posts' => 0,
            'posts_with_seo_title' => 0,
            'posts_with_seo_excerpt' => 0,
            'posts_with_score' => 0,
            'average_score' => 0,
            'score_distribution' => [
              'A' => 0,
              'B' => 0,
              'C' => 0,
              'D' => 0,
              'F' => 0
            ]
          ];
          
          $posts = get_posts( [
            'post_type' => ['post', 'page'],
            'posts_per_page' => -1,
            'post_status' => 'publish'
          ] );
          
          $total_score = 0;
          $scored_posts = 0;
          
          foreach ( $posts as $post ) {
            $stats['total_posts']++;
            
            if ( get_post_meta( $post->ID, $this->core->meta_key_seo_title, true ) ) {
              $stats['posts_with_seo_title']++;
            }
            
            if ( get_post_meta( $post->ID, $this->core->meta_key_seo_excerpt, true ) ) {
              $stats['posts_with_seo_excerpt']++;
            }
            
            $score = get_post_meta( $post->ID, '_mwseo_score', true );
            if ( $score ) {
              $stats['posts_with_score']++;
              $total_score += (int) $score;
              $scored_posts++;
              
              // Determine grade
              if ( $score >= 90 ) $stats['score_distribution']['A']++;
              elseif ( $score >= 80 ) $stats['score_distribution']['B']++;
              elseif ( $score >= 70 ) $stats['score_distribution']['C']++;
              elseif ( $score >= 60 ) $stats['score_distribution']['D']++;
              else $stats['score_distribution']['F']++;
            }
          }
          
          if ( $scored_posts > 0 ) {
            $stats['average_score'] = round( $total_score / $scored_posts, 1 );
          }
          
          $stats['seo_title_coverage'] = round( ( $stats['posts_with_seo_title'] / $stats['total_posts'] ) * 100, 1 );
          $stats['seo_excerpt_coverage'] = round( ( $stats['posts_with_seo_excerpt'] / $stats['total_posts'] ) * 100, 1 );
          
          return [ 'success' => true, 'data' => $stats ];
      }
    }
    catch ( Exception $e ) {
      return [ 'success' => false, 'error' => $e->getMessage() ];
    }
    
    return $result;
  }
}