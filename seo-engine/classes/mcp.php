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

  /**
   * Evaluate the effective SEO quality of a post.
   * Returns info about both custom and auto-generated SEO, plus any issues detected.
   * This helps distinguish between "no custom SEO" vs "actually problematic SEO".
   */
  private function evaluate_effective_seo( $post ) {
    $effective_title = $this->core->get_seo_title( $post );
    $effective_desc = $this->core->get_seo_excerpt( $post );

    $has_custom_title = (bool) get_post_meta( $post->ID, $this->core->meta_key_seo_title, true );
    $has_custom_desc = (bool) get_post_meta( $post->ID, $this->core->meta_key_seo_excerpt, true );

    // Use display width instead of character count (CJK chars count as 2)
    $title_width = $this->core->get_display_width( $effective_title );
    $desc_width = $this->core->get_display_width( $effective_desc );

    // Evaluate title quality (optimal: 30-75 display units)
    $title_issues = [];
    if ( $title_width < 30 ) {
      $title_issues[] = 'too_short';
    }
    if ( $title_width > 75 ) {
      $title_issues[] = 'too_long';
    }

    // Evaluate description quality (optimal: 80-160 display units)
    $desc_issues = [];
    if ( empty( $effective_desc ) || $desc_width < 80 ) {
      $desc_issues[] = 'too_short';
    }
    if ( $desc_width > 160 ) {
      $desc_issues[] = 'too_long';
    }

    return [
      'has_custom_title' => $has_custom_title,
      'has_custom_description' => $has_custom_desc,
      'effective_title' => $effective_title,
      'effective_description' => $effective_desc,
      'title_display_width' => $title_width,
      'description_display_width' => $desc_width,
      'title_issues' => $title_issues,
      'description_issues' => $desc_issues,
      'needs_attention' => !empty( $title_issues ) || !empty( $desc_issues )
    ];
  }

  public function register_tools( $tools ) {
    // IMPORTANT: When defining inputSchema with no properties, do NOT include an empty 
    // 'properties' => [] array. This can cause MCP parsers to fail silently. 
    // Either omit the properties key entirely or ensure at least one property exists.
    
    // SEO Title Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_title',
      'description' => 'Get the SEO meta title for a post. This is the title that appears in search engine results (SERPs) and browser tabs, not the WordPress post title. Returns the custom SEO title if set, otherwise returns the default generated title. Use this to see what title search engines will display.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID. You can get this from mwseo_search_posts, mwseo_get_post_by_slug, or other discovery tools.'
          ]
        ],
        'required' => ['post_id']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_set_seo_title',
      'description' => 'Set a custom SEO meta title for a post. This overrides the WordPress post title for search engines. Optimal length is 50-60 characters to avoid truncation in search results. The title should be compelling and include target keywords near the beginning.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to update'
          ],
          'title' => [
            'type' => 'string',
            'description' => 'The SEO title to set. Should be 50-60 characters for optimal display in search results. Include important keywords at the start.'
          ]
        ],
        'required' => ['post_id', 'title']
      ]
    ];

    // SEO Excerpt Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_excerpt',
      'description' => 'Get the SEO meta description for a post. This is the description snippet that appears under the title in search engine results. Returns the custom meta description if set, otherwise returns the default excerpt. This is crucial for click-through rates from search results.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID'
          ]
        ],
        'required' => ['post_id']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_set_seo_excerpt',
      'description' => 'Set the SEO meta description for a post. This text appears in search results under the title and should be 80-160 characters. Write it like an elevator pitch - compelling, clear, and including target keywords naturally. This directly impacts click-through rate from search results.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to update'
          ],
          'excerpt' => [
            'type' => 'string',
            'description' => 'The meta description text. Should be 80-160 characters, compelling, and include target keywords naturally.'
          ]
        ],
        'required' => ['post_id', 'excerpt']
      ]
    ];

    // SEO Score Operations
    $tools[] = [
      'name' => 'mwseo_get_seo_score',
      'description' => 'Get the complete SEO analysis for a post including score (0-100), status, detailed test results, and all issues found. Returns the full analysis object with scores for individual tests like title_exists, excerpt_length, readability_score, etc. Each test returns a score or "NA" if not applicable. Use this to understand exactly what SEO issues a post has.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
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
      'description' => 'Run a fresh comprehensive SEO analysis on a post. This re-calculates the SEO score by analyzing content quality, meta tags, readability, image alt text, internal/external links, and more. Use this after making changes to a post to see updated scores. Returns the complete analysis with individual test scores and overall rating.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to scan. This will perform a fresh analysis and update stored scores.'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    $tools[] = [
      'name' => 'mwseo_get_scored_posts',
      'description' => 'Get a list of all posts that have been analyzed with their SEO scores. Useful for identifying posts that need SEO improvements. Supports filtering by post type and status to narrow down results.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type (e.g., "post", "page", "product"). Leave empty for all types.'
          ],
          'status' => [
            'type' => 'string',
            'description' => 'Filter by status: "ok" (good SEO), "error" (needs improvement), "skip" (skipped posts), or leave empty for all.'
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of results to return',
            'default' => 100
          ]
        ]
      ]
    ];

    // Insights
    $tools[] = [
      'name' => 'mwseo_get_insights',
      'description' => 'Get Google PageSpeed Insights data for a specific post URL. Returns performance metrics, Core Web Vitals (LCP, FID, CLS), accessibility score, best practices compliance, and SEO technical audit. This analyzes actual page load performance from Google\'s perspective. Note: This makes a live API call to Google and may take a few seconds.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to analyze. The post must be published and accessible publicly.'
          ]
        ],
        'required' => ['post_id']
      ]
    ];
    
    // Robots.txt Operations
    $tools[] = [
      'name' => 'mwseo_get_robots_txt',
      'description' => 'Get the current robots.txt file content from the website root. This file tells search engine crawlers which pages they can and cannot access. Returns the actual file content if it exists, otherwise returns the WordPress default robots.txt rules.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_set_robots_txt',
      'description' => 'Update the robots.txt file in the website root. Use this to control which search engine crawlers can access which parts of your site. IMPORTANT: Be very careful - incorrect rules can accidentally block search engines from indexing your entire site. Always include "User-agent: *" and "Sitemap:" directives.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'content' => [
            'type' => 'string',
            'description' => 'The complete robots.txt content. Must follow robots.txt syntax with User-agent and Disallow/Allow directives. Include sitemap URL.'
          ]
        ],
        'required' => ['content']
      ]
    ];
    
    // Analytics Operations (Source-Agnostic)
    $tools[] = [
      'name' => 'mwseo_get_analytics_data',
      'description' => 'Get analytics data from the currently configured source (Google Analytics, Plausible Analytics, or Private Analytics). Specify metric="summary" for traffic overview (visitors, pageviews, sessions, bounce rate) or metric="top_posts" for most visited content. Supports date range filtering and country filtering. Defaults to current month if dates omitted. Examples: (1) Get current month summary: metric="summary". (2) Get January top posts: metric="top_posts", start_date="2024-01-01", end_date="2024-01-31". (3) Get US traffic only: metric="top_posts", country="US". Respects the Display Source setting in the dashboard.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'metric' => [
            'type' => 'string',
            'description' => 'Type of data to fetch: "summary" (traffic overview) or "top_posts" (most visited content)',
            'enum' => ['summary', 'top_posts']
          ],
          'start_date' => [
            'type' => 'string',
            'description' => 'Optional: Start date in YYYY-MM-DD format. Omit for current month.'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'Optional: End date in YYYY-MM-DD format. Omit for current month.'
          ],
          'country' => [
            'type' => 'string',
            'description' => 'Optional: Filter by ISO country code ("US", "GB", "FR", etc.) or "all" for all countries. Only applies to top_posts metric and only works if the analytics source provides country data (Google Analytics, Plausible Analytics).'
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Optional: Maximum posts to return when metric="top_posts". Default 20.',
            'default' => 20
          ]
        ],
        'required' => ['metric']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_post_analytics',
      'description' => 'Get analytics data for a specific post or page. Returns visits, unique visitors, pageviews, and other metrics for the given post. Useful for answering questions like "how many visits does this page get?" or "what is the traffic for this article?". Supports date range filtering. Defaults to current month if dates omitted. Respects the Display Source setting in the dashboard.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to get analytics for.'
          ],
          'start_date' => [
            'type' => 'string',
            'description' => 'Optional: Start date in YYYY-MM-DD format. Omit for current month.'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'Optional: End date in YYYY-MM-DD format. Omit for current month.'
          ]
        ],
        'required' => ['post_id']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_analytics_top_countries',
      'description' => 'Get a ranked list of countries where your website visitors come from, sorted by traffic volume. Returns country codes and visitor counts aggregated from top posts data. Helps identify your main audience locations for targeted content strategy and localization decisions. Note: Country data requires Google Analytics or Plausible Analytics; will return an error if using Private Analytics.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];
    
    // Utility Tools
    $tools[] = [
      'name' => 'mwseo_get_post_by_slug',
      'description' => 'Look up a post by its URL slug to get the WordPress post ID. The slug is the URL-friendly part of the post URL (e.g., "my-awesome-post" from example.com/my-awesome-post). Returns post ID, title, and type. Use this when you know the URL but need the post ID for other operations.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'slug' => [
            'type' => 'string',
            'description' => 'The URL slug of the post (the part after the domain in the URL, without slashes)'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'The WordPress post type to search in. Use "post" for blog posts, "page" for pages, "product" for WooCommerce products.',
            'default' => 'post'
          ]
        ],
        'required' => ['slug']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_bulk_seo_scan',
      'description' => 'Refresh SEO scores for multiple posts at once using QUICK scans (fast baseline checks; existing AI results are preserved). This is the right tool after content fixes, when issue counts have gone stale. Each call processes at most 20 posts; extra IDs come back in "skipped" so you can chunk follow-up calls. For a full AI re-analysis of a single post, use mwseo_do_seo_scan instead.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_ids' => [
            'type' => 'array',
            'description' => 'Array of WordPress post IDs to scan, max 20 per call (extras are returned in "skipped" for the next call). Example: [123, 456, 789]',
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
      'description' => 'Find all posts with SEO scores within a specific range. Useful for targeted optimization - find posts scoring 40-69 that need improvement, or 70+ that are doing well. Scores: 0-39=Poor, 40-69=Needs Work, 70+=Good. Returns post IDs, titles, and current scores.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'min_score' => [
            'type' => 'integer',
            'description' => 'Minimum SEO score (0-100). For example, use 0 to find worst posts, or 40 to find mediocre posts.',
            'minimum' => 0,
            'maximum' => 100
          ],
          'max_score' => [
            'type' => 'integer',
            'description' => 'Maximum SEO score (0-100). For example, use 39 for poor posts, or 100 for all posts above minimum.',
            'minimum' => 0,
            'maximum' => 100
          ]
        ],
        'required' => ['min_score', 'max_score']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_posts_missing_seo',
      'description' => 'Find posts without CUSTOM SEO titles or descriptions. Note: Posts without custom SEO use auto-generated values from the WordPress title/excerpt, which are often perfectly adequate. For posts where the effective SEO actually has problems (too short, too long), use mwseo_get_posts_needing_seo instead.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type: "post", "page", "product", etc. Leave empty to search all post types.',
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
      'name' => 'mwseo_get_posts_needing_seo',
      'description' => 'Find posts where the EFFECTIVE SEO (custom or auto-generated) has actual problems. Uses display width (CJK characters count as 2) to approximate Google SERP pixel limits. Flags titles outside 30-75 width and descriptions outside 80-160 width. More actionable than mwseo_get_posts_missing_seo because it finds posts that genuinely need attention, not just posts without custom SEO.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type: "post", "page", "product", etc. Leave empty to search all post types.',
            'default' => ''
          ],
          'issue_type' => [
            'type' => 'string',
            'description' => 'Filter by issue type: "title" for title issues only, "description" for description issues only, "any" for either (default).',
            'default' => 'any'
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
      'description' => 'Search posts by title or content keywords. Use this to find specific posts when you don\'t know the post ID. Returns matching posts with their IDs, titles, permalinks, and SEO scores if available. Useful for finding posts about specific topics for optimization.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'search_term' => [
            'type' => 'string',
            'description' => 'The keyword or phrase to search for in post titles and content'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type like "post", "page", etc. Leave empty to search all types.',
            'default' => ''
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of results to return',
            'default' => 20
          ]
        ],
        'required' => ['search_term']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_recent_posts',
      'description' => 'Get recently published posts from the last N days. Perfect for auditing new content to ensure it starts with good SEO. Returns posts with their SEO scores and whether they have SEO titles/descriptions set. Use this to catch SEO issues early on new content.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Number of days to look back. Default is 7 (last week).',
            'default' => 7
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by post type like "post" or "page"',
            'default' => 'post'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_generate_sitemap_preview',
      'description' => 'Preview what URLs would be included in the XML sitemap without generating the actual file. Shows post URLs, last modified dates, and post types. Useful for understanding what content search engines will discover through the sitemap.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Filter by specific post type like "post" or "page", or leave empty to preview all types',
            'default' => ''
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of URLs to include in preview',
            'default' => 100
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_check_duplicate_titles',
      'description' => 'Find posts with identical SEO titles. Duplicate titles confuse search engines about which page to rank for a query, hurting SEO for both pages. Returns groups of posts sharing the same title. Fix these by making each title unique and descriptive.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_seo_statistics',
      'description' => 'Get comprehensive SEO statistics for the entire website. Returns total posts, average SEO score, score distribution (A/B/C/D/F grades), custom SEO rates, AND counts of posts with actual SEO issues (title/description too short or too long). The "posts_needing_attention" count shows posts that genuinely need work, while "custom_title_rate" just shows customization rate (low rate is fine if auto-generated titles are good).',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object'
      ]
    ];

    // AI Keywords
    $tools[] = [
      'name' => 'mwseo_get_ai_keywords',
      'description' => 'Get the AI-extracted keywords for a post. These keywords help SEO Engine optimize the content analysis and scoring. They are NOT WordPress tags or categories, but semantic keywords that guide the SEO optimization process.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID'
          ]
        ],
        'required' => ['post_id']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_set_ai_keywords',
      'description' => 'Set AI keywords for a post to guide SEO Engine optimization. These keywords help the plugin understand what topics and concepts are important in the content, enabling better SEO analysis and recommendations. They are internal to SEO Engine and not WordPress tags.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID'
          ],
          'keywords' => [
            'type' => 'array',
            'description' => 'Array of keyword strings (e.g., ["machine learning", "artificial intelligence", "neural networks"]). Usually 3-5 keywords work best.',
            'items' => [
              'type' => 'string'
            ]
          ]
        ],
        'required' => ['post_id', 'keywords']
      ]
    ];

    // Bot Analytics - Advanced Tools
    $tools[] = [
      'name' => 'mwseo_query_bot_traffic',
      'description' => 'Flexible query tool for AI bot traffic with timeline analysis and rollups. Returns both aggregate statistics and time-series data to answer questions like "Show me ClaudeBot activity on my pricing page this month, grouped by day" or "What\'s the overall bot traffic trend?". This single tool covers most bot traffic analysis needs including trends, top pages, and specific post tracking.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'start_date' => [
            'type' => 'string',
            'description' => 'Start date in YYYY-MM-DD format. Defaults to 30 days ago if omitted. Example: "2025-10-01"'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'End date in YYYY-MM-DD format. Defaults to today if omitted. Example: "2025-10-23"'
          ],
          'post_id' => [
            'type' => 'integer',
            'description' => 'Optional: Filter by specific post ID to see bot traffic for a single post. Omit to see site-wide traffic.'
          ],
          'bot_name' => [
            'type' => 'string',
            'description' => 'Optional: Filter by specific bot name (e.g., "GPTBot", "ClaudeBot", "Google-Extended", "PerplexityBot"). Omit to see all bots. Case-sensitive exact match.'
          ],
          'bot_type' => [
            'type' => 'string',
            'description' => 'Optional: Filter by bot category. "ai" = AI assistants, trainers and answer engines (GPTBot, ClaudeBot, PerplexityBot, Google-Extended...); "search" = classic search-index crawlers (Googlebot family, bingbot); "all" = no filter. One call replaces summing per-bot queries by hand.',
            'enum' => ['ai', 'search', 'all']
          ],
          'group_by' => [
            'type' => 'string',
            'description' => 'Optional: Time grouping for trend analysis. Options: "hour" (hourly breakdown), "day" (daily breakdown - most common), "week" (weekly aggregates), "month" (monthly aggregates). Omit for aggregates only without timeline.',
            'enum' => ['hour', 'day', 'week', 'month']
          ],
          'metric' => [
            'type' => 'string',
            'description' => 'Metric to track in time-series. Options: "visits" (count of bot visits - default), "unique_posts" (number of different posts visited per period). Only relevant when group_by is specified.',
            'default' => 'visits',
            'enum' => ['visits', 'unique_posts']
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_rank_posts_for_bots',
      'description' => 'Rank posts by bot visit frequency to find most or least visited content. Perfect for discovering which content attracts AI crawlers (most visited) or which published posts are being ignored (least visited). Supports filtering by bot type, post type, and minimum visit thresholds.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'order' => [
            'type' => 'string',
            'description' => 'Ranking order: "most" returns highest-traffic posts first (popular content), "least" returns lowest-traffic posts first (neglected content). Default is "most".',
            'default' => 'most',
            'enum' => ['most', 'least']
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of posts to return. Default is 20, useful for quick overviews. Set higher (e.g., 50-100) for comprehensive audits.',
            'default' => 20
          ],
          'min_visits' => [
            'type' => 'integer',
            'description' => 'Minimum visit threshold. Only return posts with at least this many bot visits. Default 0 shows all posts. Use 1+ when order="least" to exclude completely unvisited posts.',
            'default' => 0
          ],
          'bot_name' => [
            'type' => 'string',
            'description' => 'Optional: Filter by specific bot (e.g., "ClaudeBot") to see which posts that bot prefers. Omit to consider all bots.'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Optional: Filter by WordPress post type (e.g., "post", "page", "product"). Useful for analyzing specific content types. Omit to include all post types.'
          ],
          'days' => [
            'type' => 'integer',
            'description' => 'Number of days to look back from today. Default 30 (last month). Use 7 for weekly trends, 90 for quarterly analysis, etc.',
            'default' => 30
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_bot_profile',
      'description' => 'Comprehensive deep-dive analysis for a specific AI bot. Returns headline statistics, top visited posts, daily visit cadence, and anomaly detection (spikes vs prior period). Use this to understand a bot\'s behavior patterns, crawl frequency, content preferences, and detect unusual activity.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'bot_name' => [
            'type' => 'string',
            'description' => 'Name of the bot to analyze (e.g., "GPTBot", "ClaudeBot", "Google-Extended"). Must match exactly. This is the primary identifier for the bot whose profile you want to see.'
          ],
          'start_date' => [
            'type' => 'string',
            'description' => 'Analysis period start date in YYYY-MM-DD format. Defaults to 30 days ago. The tool automatically compares against an equal prior period for anomaly detection.'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'Analysis period end date in YYYY-MM-DD format. Defaults to today. Combined with start_date to define the analysis window.'
          ]
        ],
        'required' => ['bot_name']
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_compare_bot_periods',
      'description' => 'Compare bot traffic between two time periods to identify trends, growth, or decline. Returns percent changes, trend indicators (increasing/decreasing/stable), and highlights posts with the biggest traffic shifts. Useful for measuring impact of content changes, SEO improvements, or seasonal patterns in bot activity.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'period1_start' => [
            'type' => 'string',
            'description' => 'Period 1 start date in YYYY-MM-DD format. Default is 60 days ago. This is your baseline/comparison period (e.g., "last month").'
          ],
          'period1_end' => [
            'type' => 'string',
            'description' => 'Period 1 end date in YYYY-MM-DD format. Default is 31 days ago. Should be before period2 for meaningful comparison.'
          ],
          'period2_start' => [
            'type' => 'string',
            'description' => 'Period 2 start date in YYYY-MM-DD format. Default is 30 days ago. This is your current/recent period (e.g., "this month").'
          ],
          'period2_end' => [
            'type' => 'string',
            'description' => 'Period 2 end date in YYYY-MM-DD format. Default is today. Marks the end of the period you\'re analyzing.'
          ],
          'bot_name' => [
            'type' => 'string',
            'description' => 'Optional: Filter comparison to a specific bot (e.g., "ClaudeBot"). Omit to compare all bot traffic across periods.'
          ],
          'post_id' => [
            'type' => 'integer',
            'description' => 'Optional: Filter comparison to a specific post ID. Useful for tracking "did bot traffic to this post increase after I updated it?". Omit for site-wide comparison.'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_bot_mix',
      'description' => 'Analyze the distribution of bot traffic across different AI crawlers with percentage breakdowns. Also detects new bots that appeared during the period (weren\'t present in prior 30 days). Useful for understanding your bot audience composition, identifying dominant crawlers, and spotting emerging AI platforms indexing your content.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'start_date' => [
            'type' => 'string',
            'description' => 'Analysis period start date in YYYY-MM-DD format. Defaults to 30 days ago. Defines the window for calculating distribution percentages.'
          ],
          'end_date' => [
            'type' => 'string',
            'description' => 'Analysis period end date in YYYY-MM-DD format. Defaults to today.'
          ],
          'post_type' => [
            'type' => 'string',
            'description' => 'Optional: Segment distribution by post type (e.g., "post", "page", "product"). Useful for questions like "Which bots prefer my product pages vs blog posts?". Omit for site-wide mix.'
          ]
        ]
      ]
    ];

    // Magic Fix / Issues
    $tools[] = [
      'name' => 'mwseo_get_issues',
      'description' => 'Get SEO issues. With post_id: detailed per-post breakdown (which tests failed, scores, severity). Without post_id: site-wide aggregate showing the most common failing tests across all scanned posts (which problems to fix first).',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'Optional. The WordPress post ID for a per-post breakdown. Omit for a site-wide aggregate.'
          ],
          'sample_size' => [
            'type' => 'integer',
            'description' => 'Site-wide mode only. Maximum number of scanned posts to aggregate over. Default 5000.',
            'default' => 5000
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_suggest_seo_title',
      'description' => 'Generate AI-written SEO title candidates for a post, typically used after mwseo_gsc_quick_wins surfaces a CTR opportunity. Returns 3 candidate titles (configurable). The agent picks one and applies it via mwseo_set_seo_title. Optionally pass target_query to steer the candidates toward a specific search query the page already ranks for. Requires AI Engine.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The post ID to suggest titles for.'
          ],
          'target_query' => [
            'type' => 'string',
            'description' => 'Optional. A search query the page should rank for. Candidates will naturally include this phrasing.'
          ],
          'count' => [
            'type' => 'integer',
            'description' => 'Number of candidate titles to return. Default 3, max 10.',
            'default' => 3
          ]
        ],
        'required' => [ 'post_id' ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_suggest_seo_excerpt',
      'description' => 'Generate AI-written meta description candidates for a post. The single biggest fixable issue bucket on most sites is missing or mis-sized meta descriptions; this pairs with mwseo_set_seo_excerpt to fix them in bulk (suggest, pick, set, then re-scan in batches). Returns 3 candidates (configurable). Optionally pass target_query to steer toward a query the page ranks for. Requires AI Engine.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The post ID to suggest meta descriptions for.'
          ],
          'target_query' => [
            'type' => 'string',
            'description' => 'Optional. A search query the page should rank for. Candidates will naturally include this phrasing.'
          ],
          'count' => [
            'type' => 'integer',
            'description' => 'Number of candidates to return. Default 3, max 10.',
            'default' => 3
          ]
        ],
        'required' => [ 'post_id' ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_get_orphan_pages',
      'description' => 'Find published posts that have zero inbound internal links (no other post on the site links to them). Substantive orphans are the highest-value target for adding internal links. Filters keep the list actionable on large sites; defaults focus on substantive posts (>=300 words). Up to 2000 candidates scanned per call.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_type' => [
            'type' => 'string',
            'description' => 'Post type to scan. Defaults to "post".',
            'default' => 'post'
          ],
          'lang' => [
            'type' => 'string',
            'description' => 'Optional. Polylang language slug (e.g. "en", "fr", "ja"). Only effective if Polylang is active.'
          ],
          'created_after' => [
            'type' => 'string',
            'description' => 'Optional. ISO date (YYYY-MM-DD). Only consider posts created on or after this date. Useful to skip very old content.'
          ],
          'min_word_count' => [
            'type' => 'integer',
            'description' => 'Skip posts below this word count. Default 300 (substantive posts only). CJK content is estimated via character count.',
            'default' => 300
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of orphans to return, sorted by word count desc. Default 50, max 500.',
            'default' => 50
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_suggest_internal_links',
      'description' => 'Get AI-ranked internal-link candidate posts (no placement suggestions). FAST: extracts keywords, gathers candidates, AI-ranks the top matches, returns each with a context excerpt so you can judge relevance, usually 5 to 15 seconds total. To generate concrete placement options (search/replace snippets) for one of these candidates, call mwseo_generate_internal_link_placements afterwards with the source + chosen target. Accepts an existing post_id OR a draft_content payload. Requires Pro and AI Engine.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'WordPress post ID of an existing post. Either this OR draft_content must be provided.'
          ],
          'draft_content' => [
            'type' => 'object',
            'description' => 'Draft payload for unsaved content (use during drafting before publish). Either this OR post_id must be provided.',
            'properties' => [
              'title' => [ 'type' => 'string', 'description' => 'Draft title' ],
              'content' => [ 'type' => 'string', 'description' => 'Draft body (HTML or plain text)' ],
              'post_type' => [ 'type' => 'string', 'description' => 'Optional. Defaults to "post".', 'default' => 'post' ]
            ],
            'required' => [ 'title', 'content' ]
          ],
          'max_candidates' => [
            'type' => 'integer',
            'description' => 'Maximum candidates returned. Default 10, max 10.',
            'default' => 10
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_generate_internal_link_placements',
      'description' => 'Given a source post (or draft) and ONE target post, generate AI placement suggestions: each suggestion is a {search, replace, reason} triple ready to apply, using up to 4 strategies (link existing text → add in parenthesis → add new sentence → add at end). Usually 5-10 seconds. Call this after mwseo_suggest_internal_links to drill into the most promising candidate. Requires Pro and AI Engine.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'Source post ID. Either this OR draft_content must be provided.'
          ],
          'draft_content' => [
            'type' => 'object',
            'description' => 'Draft source payload. Either this OR post_id must be provided.',
            'properties' => [
              'title' => [ 'type' => 'string' ],
              'content' => [ 'type' => 'string' ],
              'post_type' => [ 'type' => 'string', 'default' => 'post' ]
            ],
            'required' => [ 'title', 'content' ]
          ],
          'target_post_id' => [
            'type' => 'integer',
            'description' => 'Target post ID (from a candidate returned by mwseo_suggest_internal_links).'
          ]
        ],
        'required' => [ 'target_post_id' ]
      ]
    ];

    // Google Search Console (Pro)
    $tools[] = [
      'name' => 'mwseo_gsc_status',
      'description' => 'Check Google Search Console connection status. Returns whether GSC is connected, the selected property, the list of available properties (verified sites the connected Google account owns), and the OAuth URL if not connected. ALWAYS call this first before other gsc_* tools. Requires Pro.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [ 'type' => 'object' ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_quick_wins',
      'description' => 'THE high-leverage SEO tool: returns categorized, actionable opportunities with concrete payoff estimates and one-button-away next steps. Four categories: 🎯 Close to first page (posts ranking #5 to #15 with strong impressions: small lift, big traffic gain), 💡 Title & meta opportunities (high impressions but low CTR: the title or description isn\'t compelling clicks), 🤖 AI Overview suspects (top-5 queries with severely depressed CTR — likely cannibalized by Google\'s AI Overviews; Ahrefs measures ~60% CTR drop, Pew ~47%), 💎 Hidden gems (great CTR but few impressions: these posts convert when seen). Each opportunity includes the recommended action, the next MCP tool to run, and an estimated click lift. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Lookback window in days. Default 28, max 90.',
            'default' => 28
          ],
          'min_impressions' => [
            'type' => 'integer',
            'description' => 'Ignore queries with fewer impressions than this in the period (noise filter). Default 50.',
            'default' => 50
          ],
          'limit_per_category' => [
            'type' => 'integer',
            'description' => 'Maximum opportunities returned per category. Default 5, max 50.',
            'default' => 5
          ],
          'property' => [
            'type' => 'string',
            'description' => 'Optional. Override the active Search Console property (use the siteUrl from mwseo_gsc_status). Use this for multi-domain Polylang/WPML setups to query a specific language site.'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_ai_overview_suspects',
      'description' => 'Surfaces queries where this site ranks in the top 5 but clicks-through is severely below the position curve (under 35% of expected) — the signature pattern of Google\'s AI Overview cannibalizing the click. Independent studies measure AI Overviews dropping CTR 47% (Pew) to 60% (Ahrefs) on impacted queries. There is no direct fix that "beats" AI Overviews; the play is to be the source they cite, and to diversify traffic. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Lookback window in days. Default 28, max 90.',
            'default' => 28
          ],
          'min_impressions' => [
            'type' => 'integer',
            'description' => 'Ignore queries with fewer impressions than this in the period (noise filter). Default 50.',
            'default' => 50
          ],
          'limit_per_category' => [
            'type' => 'integer',
            'description' => 'Maximum suspects returned. Default 5, max 50.',
            'default' => 5
          ],
          'property' => [
            'type' => 'string',
            'description' => 'Optional. Override the active Search Console property (use the siteUrl from mwseo_gsc_status).'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_site_pulse',
      'description' => 'Site-level Search Console pulse: returns total clicks, impressions, average position, and CTR for the property over the period, plus period-over-period deltas. The "is the site growing?" answer in one call. Same trend shape as mwseo_gsc_post_pulse but at the site level. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Period length in days. The prior comparison period is the same length immediately before. Default 28.',
            'default' => 28
          ],
          'property' => [
            'type' => 'string',
            'description' => 'Optional. Override the active property (siteUrl). For multi-domain setups.'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_weekly_digest',
      'description' => 'One-call snapshot of a property: site totals + period-over-period trends, the top opportunity in each Quick Wins category, the pages that moved the most in clicks, and search queries that entered the data this period. Designed as the entry point for a weekly /pulse workflow — composes site_pulse + quick_wins + movers + new-query analysis into a single call. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [
            'type' => 'integer',
            'description' => 'Period length in days. The prior comparison period is the same length immediately before. Default 7 (one week).',
            'default' => 7
          ],
          'property' => [
            'type' => 'string',
            'description' => 'Optional. Override the active property (siteUrl). For multi-domain setups.'
          ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_post_pulse',
      'description' => 'Per-post Search Console pulse: returns a one-line human headline ("This post has X impressions/month, ranking #N on average, clicks up Y%"), top queries the post ranks for, and trend comparisons (clicks/impressions/position) vs. the previous period. Designed for the "what\'s happening with my post" moment. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to inspect.'
          ],
          'days' => [
            'type' => 'integer',
            'description' => 'Current period length in days (the prior comparison period is the same length immediately before). Default 28.',
            'default' => 28
          ],
          'property' => [
            'type' => 'string',
            'description' => 'Optional. Override the active Search Console property (siteUrl). For multi-domain setups.'
          ],
          'debug' => [
            'type' => 'boolean',
            'description' => 'Optional. If true, include a _debug block in the response (code version + actual date windows queried). For verifying deployments and diagnosing empty-period issues.',
            'default' => false
          ]
        ],
        'required' => [ 'post_id' ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_top_queries',
      'description' => 'Top search queries from Google Search Console, optionally scoped to a single page URL or country. Returns query, clicks, impressions, CTR, and average position. Use this for free-form exploration; use mwseo_gsc_quick_wins for actionable opportunities. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [ 'type' => 'integer', 'description' => 'Lookback window in days. Default 28.', 'default' => 28 ],
          'limit' => [ 'type' => 'integer', 'description' => 'Max queries to return. Default 25, max 1000.', 'default' => 25 ],
          'page_url' => [ 'type' => 'string', 'description' => 'Optional. Full URL of a single page to scope the queries to.' ],
          'country' => [ 'type' => 'string', 'description' => 'Optional. ISO 3-letter country code (e.g. "usa", "fra", "jpn").' ],
          'property' => [ 'type' => 'string', 'description' => 'Optional. Override the active Search Console property (siteUrl). For multi-domain setups.' ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_top_pages',
      'description' => 'Top pages by clicks from Google Search Console. Returns each page URL with clicks, impressions, CTR, average position, and the matched WP post_id/title where the URL resolves to a known post. Requires Pro and a connected GSC property.',
      'category' => 'SEO Engine',
      'accessLevel' => 'read',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'days' => [ 'type' => 'integer', 'description' => 'Lookback window in days. Default 28.', 'default' => 28 ],
          'limit' => [ 'type' => 'integer', 'description' => 'Max pages to return. Default 25, max 1000.', 'default' => 25 ],
          'property' => [ 'type' => 'string', 'description' => 'Optional. Override the active Search Console property (siteUrl). For multi-domain setups.' ]
        ]
      ]
    ];

    $tools[] = [
      'name' => 'mwseo_gsc_set_property',
      'description' => 'Set the active Google Search Console property to query. Use mwseo_gsc_status first to see available properties. The property is a verified site URL like "https://example.com/" or "sc-domain:example.com". Requires Pro and GSC connected.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'property' => [ 'type' => 'string', 'description' => 'The siteUrl from mwseo_gsc_status available_properties.' ]
        ],
        'required' => [ 'property' ]
      ]
    ];

    // Post Management
    $tools[] = [
      'name' => 'mwseo_skip_post',
      'description' => 'Mark a post to skip SEO analysis. Use this for posts that should not be analyzed (e.g., drafts, private pages, or content you do not want indexed). The post will be excluded from SEO scoring and reports.',
      'category' => 'SEO Engine',
      'accessLevel' => 'write',
      'inputSchema' => [
        'type' => 'object',
        'properties' => [
          'post_id' => [
            'type' => 'integer',
            'description' => 'The WordPress post ID to skip'
          ],
          'skip' => [
            'type' => 'boolean',
            'description' => 'True to skip the post, false to un-skip it',
            'default' => true
          ]
        ],
        'required' => ['post_id']
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
          $post = get_post( $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found' ];
          }

          // Get basic score info
          $score_data = $this->api->get_seo_score( $args['post_id'] );

          // Get full analysis data
          $analysis = get_post_meta( $post->ID, '_mwseo_analysis', true );

          if ( $analysis && isset( $analysis['tests'] ) ) {
            $score_data['analysis'] = $analysis;
          }

          return [ 'success' => true, 'data' => $score_data ];
          
        case 'mwseo_do_seo_scan':
          return $this->api->do_seo_scan( $args['post_id'] );
          
        case 'mwseo_get_scored_posts':
          // Note: get_scored_posts() returns a bare array, not a success wrapper
          $posts = $this->api->get_scored_posts();

          if ( !is_array( $posts ) ) {
            return [ 'success' => false, 'error' => 'Failed to retrieve scored posts' ];
          }

          // Apply filters if provided
          if ( isset( $args['post_type'] ) && !empty( $args['post_type'] ) ) {
            $posts = array_filter( $posts, function( $post ) use ( $args ) {
              $post_obj = get_post( $post['id'] );
              return $post_obj && $post_obj->post_type === $args['post_type'];
            });
          }

          if ( isset( $args['status'] ) && !empty( $args['status'] ) ) {
            $posts = array_filter( $posts, function( $post ) use ( $args ) {
              return isset( $post['status'] ) && $post['status'] === $args['status'];
            });
          }

          // Apply limit
          $limit = $args['limit'] ?? 100;
          $posts = array_slice( $posts, 0, $limit );

          return [ 'success' => true, 'data' => array_values( $posts ) ];
          
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

        // Analytics Operations (Source-Agnostic)
        case 'mwseo_get_analytics_data':
          // metric is required - validate it exists
          if ( empty( $args['metric'] ) ) {
            return [ 'success' => false, 'error' => 'metric parameter is required. Must be "summary" or "top_posts".' ];
          }

          $metric = $args['metric'];
          $start_date = $args['start_date'] ?? null;
          $end_date = $args['end_date'] ?? null;
          $country = $args['country'] ?? null;
          $limit = $args['limit'] ?? 20;

          if ( $metric === 'summary' ) {
            // Use source-agnostic method (respects Display Source setting)
            $data = $this->core->get_analytics_summary( $start_date, $end_date );
            return [ 'success' => !empty( $data ), 'data' => $data ];
          }
          elseif ( $metric === 'top_posts' ) {
            // Use source-agnostic method (respects Display Source setting)
            $query_args = [
              'start_date' => $start_date,
              'end_date' => $end_date,
              'limit' => $limit
            ];

            $top_posts = $this->core->get_top_posts( $query_args );

            // Filter by country if specified (only works with Google Analytics data)
            if ( !empty( $country ) && $country !== 'all' && is_array( $top_posts ) ) {
              $top_posts = array_filter( $top_posts, function( $post ) use ( $country ) {
                return isset( $post['country'] ) && $post['country'] === $country;
              });
              $top_posts = array_values( $top_posts );
            }

            return [ 'success' => !empty( $top_posts ), 'data' => $top_posts ];
          }
          else {
            return [ 'success' => false, 'error' => 'Invalid metric. Must be "summary" or "top_posts".' ];
          }

        case 'mwseo_get_post_analytics':
          if ( empty( $args['post_id'] ) ) {
            return [ 'success' => false, 'error' => 'post_id parameter is required.' ];
          }

          $post_id = (int) $args['post_id'];
          $post = get_post( $post_id );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found with ID ' . $post_id . '.' ];
          }

          $permalink = get_permalink( $post_id );
          $page_path = wp_parse_url( $permalink, PHP_URL_PATH ) ?: '/';
          $start_date = $args['start_date'] ?? null;
          $end_date = $args['end_date'] ?? null;

          $data = $this->core->get_post_analytics( $post_id, $page_path, $start_date, $end_date );

          if ( empty( $data ) ) {
            return [
              'success' => true,
              'data' => [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_url' => $permalink,
                'message' => 'No analytics data found for this post in the selected date range.'
              ]
            ];
          }

          $data['post_id'] = $post_id;
          $data['post_title'] = $post->post_title;
          $data['post_url'] = $permalink;

          return [ 'success' => true, 'data' => $data ];

        case 'mwseo_get_analytics_top_countries':
          // Use source-agnostic method (respects Display Source setting)
          $top_posts = $this->core->get_top_posts( [] );

          if ( empty( $top_posts ) || !is_array( $top_posts ) ) {
            return [ 'success' => false, 'data' => [] ];
          }

          // Aggregate visitor counts by country (if country data is available)
          $country_stats = [];
          foreach ( $top_posts as $post ) {
            if ( isset( $post['country'] ) ) {
              $country = $post['country'];
              // Use unique_visitors if available, fallback to visits, then to 1
              $visitors = isset( $post['unique_visitors'] ) ? (int) $post['unique_visitors'] :
                         (isset( $post['visits'] ) ? (int) $post['visits'] : 1);

              if ( !isset( $country_stats[$country] ) ) {
                $country_stats[$country] = [
                  'country' => $country,
                  'visitors' => 0
                ];
              }
              $country_stats[$country]['visitors'] += $visitors;
            }
          }

          // If no country data found (e.g., Private Analytics), return error
          if ( empty( $country_stats ) ) {
            return [
              'success' => false,
              'error' => 'Country data not available with current analytics source. This feature requires Google Analytics or Plausible Analytics.',
              'data' => []
            ];
          }

          // Sort by visitor count descending
          usort( $country_stats, function( $a, $b ) {
            return $b['visitors'] - $a['visitors'];
          });

          return [ 'success' => true, 'data' => array_values( $country_stats ) ];
          
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
          
        case 'mwseo_bulk_seo_scan': {
          // Bulk runs QUICK scans: sub-second each, they refresh the failing-test counts after
          // content fixes (the actual bulk use case) and preserve existing AI results. Full AI
          // analysis takes seconds per post and times out in bulk, so it stays per-post via
          // mwseo_do_seo_scan. The batch is capped and the rest handed back explicitly instead
          // of timing out halfway with no explanation.
          $requested = array_values( array_map( 'intval', (array) $args['post_ids'] ) );
          $batch = array_slice( $requested, 0, 20 );
          $skipped = array_slice( $requested, 20 );
          $results = [];
          foreach ( $batch as $post_id ) {
            $post = get_post( $post_id );
            $results[$post_id] = $post
              ? $this->core->calculate_seo_score( $post, 'quick' )
              : [ 'success' => false, 'message' => 'Post not found.' ];
          }
          $response = [ 'success' => true, 'mode' => 'quick', 'data' => $results ];
          if ( !empty( $skipped ) ) {
            $response['skipped'] = $skipped;
            $response['note'] = 'Only 20 posts are scanned per call to stay within HTTP timeouts. Call again with the skipped IDs. For a full AI re-analysis of one post, use mwseo_do_seo_scan.';
          }
          return $response;
        }
          
        // Advanced SEO Tools
        case 'mwseo_get_posts_by_score_range':
          // Note: get_scored_posts() returns a bare array, not a success wrapper
          $posts = $this->api->get_scored_posts();

          if ( !is_array( $posts ) ) {
            return [ 'success' => false, 'error' => 'Failed to retrieve scored posts' ];
          }

          $filtered = array_filter( $posts, function( $post ) use ( $args ) {
            $score = $post['score'] ?? 0;
            return $score >= $args['min_score'] && $score <= $args['max_score'];
          });

          return [ 'success' => true, 'data' => array_values( $filtered ) ];
          
        case 'mwseo_get_posts_missing_seo':
          // TODO: meta_key_seo_title and meta_key_seo_excerpt should migrate to _mwseo_title and _mwseo_excerpt
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

        case 'mwseo_get_posts_needing_seo':
          // Find posts where the EFFECTIVE SEO (custom or auto-generated) has actual problems
          $post_type = !empty( $args['post_type'] ) ? $args['post_type'] : ['post', 'page'];
          $issue_type = $args['issue_type'] ?? 'any';
          $limit = $args['limit'] ?? 50;

          $posts = get_posts( [
            'post_type' => $post_type,
            'posts_per_page' => -1, // Get all, then filter
            'post_status' => 'publish'
          ] );

          $results = [];
          foreach ( $posts as $post ) {
            $seo_eval = $this->evaluate_effective_seo( $post );

            // Filter by issue type
            $has_relevant_issue = false;
            if ( $issue_type === 'title' && !empty( $seo_eval['title_issues'] ) ) {
              $has_relevant_issue = true;
            } elseif ( $issue_type === 'description' && !empty( $seo_eval['description_issues'] ) ) {
              $has_relevant_issue = true;
            } elseif ( $issue_type === 'any' && $seo_eval['needs_attention'] ) {
              $has_relevant_issue = true;
            }

            if ( $has_relevant_issue ) {
              $results[] = [
                'post_id' => $post->ID,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'permalink' => get_permalink( $post->ID ),
                'effective_title' => $seo_eval['effective_title'],
                'effective_description' => $seo_eval['effective_description'],
                'title_display_width' => $seo_eval['title_display_width'],
                'description_display_width' => $seo_eval['description_display_width'],
                'title_issues' => $seo_eval['title_issues'],
                'description_issues' => $seo_eval['description_issues'],
                'has_custom_title' => $seo_eval['has_custom_title'],
                'has_custom_description' => $seo_eval['has_custom_description']
              ];

              if ( count( $results ) >= $limit ) {
                break;
              }
            }
          }

          return [ 'success' => true, 'data' => $results ];

        case 'mwseo_search_posts':
          // Empty search_term silently returned recent posts via get_posts('s'=>'').
          // Validate input and run a direct LIKE query for predictable matching.
          $search_term = isset( $args['search_term'] ) ? trim( (string) $args['search_term'] ) : '';
          if ( $search_term === '' ) {
            return [ 'success' => false, 'error' => 'search_term is required and cannot be empty' ];
          }

          $limit = isset( $args['limit'] ) ? max( 1, min( 100, (int) $args['limit'] ) ) : 20;
          $post_types = !empty( $args['post_type'] ) ? (array) $args['post_type'] : [ 'post', 'page' ];

          global $wpdb;
          $like = '%' . $wpdb->esc_like( $search_term ) . '%';
          $type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

          $sql = "SELECT ID FROM {$wpdb->posts}
            WHERE post_status = 'publish'
              AND post_type IN ($type_placeholders)
              AND ( post_title LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s )
            ORDER BY
              CASE WHEN post_title LIKE %s THEN 0 ELSE 1 END,
              post_date DESC
            LIMIT %d";

          $params = array_merge( $post_types, [ $like, $like, $like, $like, $limit ] );
          $post_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

          $results = [];
          foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( !$post ) continue;
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
            // Custom SEO counts (for backward compatibility)
            'posts_with_seo_title' => 0,
            'posts_with_seo_excerpt' => 0,
            // Scoring stats
            'posts_with_score' => 0,
            'average_score' => 0,
            'score_distribution' => [
              'A' => 0,
              'B' => 0,
              'C' => 0,
              'D' => 0,
              'F' => 0
            ],
            // NEW: Actual issue counts (these are what matter for prioritization)
            'posts_with_title_issues' => 0,
            'posts_with_description_issues' => 0,
            'posts_needing_attention' => 0
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

            // Evaluate effective SEO quality (custom or auto-generated)
            $seo_eval = $this->evaluate_effective_seo( $post );
            if ( !empty( $seo_eval['title_issues'] ) ) {
              $stats['posts_with_title_issues']++;
            }
            if ( !empty( $seo_eval['description_issues'] ) ) {
              $stats['posts_with_description_issues']++;
            }
            if ( $seo_eval['needs_attention'] ) {
              $stats['posts_needing_attention']++;
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

          // Rates (renamed from "coverage" for clarity - low rate is fine if auto-generated SEO is good)
          $stats['custom_title_rate'] = round( ( $stats['posts_with_seo_title'] / $stats['total_posts'] ) * 100, 1 );
          $stats['custom_excerpt_rate'] = round( ( $stats['posts_with_seo_excerpt'] / $stats['total_posts'] ) * 100, 1 );
          // Keep old names for backward compatibility
          $stats['seo_title_coverage'] = $stats['custom_title_rate'];
          $stats['seo_excerpt_coverage'] = $stats['custom_excerpt_rate'];

          return [ 'success' => true, 'data' => $stats ];

        // AI Keywords
        case 'mwseo_get_ai_keywords':
          $post = get_post( $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found' ];
          }

          $keywords = get_post_meta( $post->ID, '_mwseo_keywords', true );
          return [
            'success' => true,
            'data' => [
              'post_id' => $post->ID,
              'keywords' => $keywords ?: []
            ]
          ];

        case 'mwseo_set_ai_keywords':
          $post = get_post( $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found' ];
          }

          $keywords = $args['keywords'];
          if ( !is_array( $keywords ) ) {
            return [ 'success' => false, 'error' => 'Keywords must be an array' ];
          }

          // Limit to 10 keywords max
          $keywords = array_slice( $keywords, 0, 10 );

          update_post_meta( $post->ID, '_mwseo_keywords', $keywords );
          return [
            'success' => true,
            'data' => [
              'post_id' => $post->ID,
              'keywords' => $keywords
            ],
            'message' => 'AI keywords updated successfully'
          ];

        // Bot Analytics - Advanced Tools
        case 'mwseo_query_bot_traffic':
          $query_args = array(
            'start_date' => $args['start_date'] ?? null,
            'end_date' => $args['end_date'] ?? null,
            'post_id' => $args['post_id'] ?? null,
            'bot_name' => $args['bot_name'] ?? null,
            'bot_type' => $args['bot_type'] ?? null,
            'group_by' => $args['group_by'] ?? null,
            'metric' => $args['metric'] ?? 'visits'
          );

          $result = $this->core->query_bot_traffic( $query_args );
          return [ 'success' => true, 'data' => $result ];

        case 'mwseo_rank_posts_for_bots':
          $rank_args = array(
            'order' => $args['order'] ?? 'most',
            'limit' => $args['limit'] ?? 20,
            'min_visits' => $args['min_visits'] ?? 0,
            'bot_name' => $args['bot_name'] ?? null,
            'post_type' => $args['post_type'] ?? null,
            'days' => $args['days'] ?? 30
          );

          $result = $this->core->rank_posts_for_bots( $rank_args );
          return [ 'success' => true, 'data' => $result ];

        case 'mwseo_bot_profile':
          if ( empty( $args['bot_name'] ) ) {
            return [ 'success' => false, 'error' => 'bot_name is required' ];
          }

          $result = $this->core->get_bot_profile(
            $args['bot_name'],
            $args['start_date'] ?? null,
            $args['end_date'] ?? null
          );

          return [ 'success' => true, 'data' => $result ];

        case 'mwseo_compare_bot_periods':
          $compare_args = array(
            'period1_start' => $args['period1_start'] ?? null,
            'period1_end' => $args['period1_end'] ?? null,
            'period2_start' => $args['period2_start'] ?? null,
            'period2_end' => $args['period2_end'] ?? null,
            'bot_name' => $args['bot_name'] ?? null,
            'post_id' => $args['post_id'] ?? null
          );

          $result = $this->core->compare_bot_periods( $compare_args );
          return [ 'success' => true, 'data' => $result ];

        case 'mwseo_bot_mix':
          $mix_args = array(
            'start_date' => $args['start_date'] ?? null,
            'end_date' => $args['end_date'] ?? null,
            'post_type' => $args['post_type'] ?? null
          );

          $result = $this->core->get_bot_mix( $mix_args );
          return [ 'success' => true, 'data' => $result ];

        // Magic Fix / Issues
        case 'mwseo_get_issues':
          // Per-post mode
          if ( !empty( $args['post_id'] ) ) {
            $post = get_post( $args['post_id'] );
            if ( !$post ) {
              return [ 'success' => false, 'error' => 'Post not found' ];
            }

            $analysis = get_post_meta( $post->ID, '_mwseo_analysis', true );
            $codes = get_post_meta( $post->ID, '_mwseo_codes', true );

            if ( !$analysis || !isset( $analysis['tests'] ) ) {
              return [
                'success' => false,
                'error' => 'No analysis found for this post. Run mwseo_do_seo_scan first.'
              ];
            }

            $issues = [];
            foreach ( $analysis['tests'] as $test_name => $score ) {
              if ( $score === 'NA' ) continue;
              if ( $score < 70 ) {
                $issues[] = [
                  'test' => $test_name,
                  'score' => $score,
                  'severity' => $score < 40 ? 'high' : 'medium'
                ];
              }
            }

            return [
              'success' => true,
              'data' => [
                'post_id' => $post->ID,
                'overall_score' => $analysis['overall'] ?? 0,
                'issues' => $issues,
                'codes' => $codes ?: []
              ]
            ];
          }

          // Site-wide aggregate mode — delegate to the shared aggregator so the
          // MCP tool and the /aggregate_issues REST endpoint never drift apart.
          global $mwseo_score;
          if ( !$mwseo_score ) {
            return [ 'success' => false, 'error' => 'Score module not initialized.' ];
          }

          $data = $mwseo_score->aggregate_issues( [
            'sample_size' => isset( $args['sample_size'] ) ? (int) $args['sample_size'] : 5000,
            'post_type'   => !empty( $args['post_type'] ) ? (array) $args['post_type'] : null,
          ] );

          return [ 'success' => true, 'data' => $data ];

        case 'mwseo_get_orphan_pages':
          $post_type = !empty( $args['post_type'] ) ? (array) $args['post_type'] : [ 'post' ];
          $lang = isset( $args['lang'] ) ? (string) $args['lang'] : '';
          $created_after = isset( $args['created_after'] ) ? (string) $args['created_after'] : '';
          $min_word_count = isset( $args['min_word_count'] ) ? max( 0, (int) $args['min_word_count'] ) : 300;
          $limit = isset( $args['limit'] ) ? max( 1, min( 500, (int) $args['limit'] ) ) : 50;
          $scan_limit = 2000;

          $query_args = [
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $scan_limit,
            'orderby' => 'date',
            'order' => 'DESC',
          ];
          if ( $created_after !== '' ) {
            $query_args['date_query'] = [ [ 'after' => $created_after, 'inclusive' => true ] ];
          }
          if ( $lang !== '' && function_exists( 'pll_get_post_language' ) ) {
            $query_args['lang'] = $lang;
          }

          $candidates = get_posts( $query_args );
          $orphans = [];
          $scanned = 0;

          global $wpdb;
          foreach ( $candidates as $candidate ) {
            $scanned++;

            $stripped = strip_tags( $candidate->post_content );
            $word_count = str_word_count( $stripped );
            if ( $word_count === 0 && mb_strlen( $stripped ) > 0 ) {
              // CJK / non-Latin fallback: rough proxy via character count.
              $word_count = (int) ( mb_strlen( $stripped ) / 2 );
            }
            if ( $word_count < $min_word_count ) continue;

            // Approximate inbound count via slug substring match. Over-counts
            // mentions vs. real links, which means we may miss some orphans
            // (false negatives) — the safer error direction than false positives.
            if ( empty( $candidate->post_name ) ) continue;
            $like_slug = '%' . $wpdb->esc_like( '/' . $candidate->post_name . '/' ) . '%';
            $inbound = (int) $wpdb->get_var( $wpdb->prepare(
              "SELECT COUNT(*) FROM {$wpdb->posts}
                WHERE post_status = 'publish' AND ID != %d AND post_content LIKE %s",
              $candidate->ID, $like_slug
            ) );

            if ( $inbound > 0 ) continue;

            $orphans[] = [
              'post_id' => $candidate->ID,
              'post_title' => $candidate->post_title,
              'permalink' => get_permalink( $candidate ),
              'post_type' => $candidate->post_type,
              'post_date' => $candidate->post_date,
              'word_count' => $word_count,
            ];
          }

          usort( $orphans, function( $a, $b ) { return $b['word_count'] - $a['word_count']; } );
          $orphans = array_slice( $orphans, 0, $limit );

          return [
            'success' => true,
            'data' => [
              'scanned' => $scanned,
              'scan_limit' => $scan_limit,
              'truncated' => $scanned >= $scan_limit,
              'orphans' => $orphans,
            ]
          ];

        case 'mwseo_suggest_internal_links':
          if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
            return [ 'success' => false, 'error' => 'Magic Fix is not available (Pro required).' ];
          }

          // Resolve to a post-like object: either a real WP_Post or a pseudo-post for drafts.
          $is_draft = false;
          if ( !empty( $args['post_id'] ) ) {
            $post = get_post( $args['post_id'] );
            if ( !$post ) {
              return [ 'success' => false, 'error' => 'Post not found' ];
            }
          } else if ( !empty( $args['draft_content'] ) && is_array( $args['draft_content'] ) ) {
            $draft = $args['draft_content'];
            if ( empty( $draft['title'] ) || empty( $draft['content'] ) ) {
              return [ 'success' => false, 'error' => 'draft_content requires both title and content.' ];
            }
            // Pseudo-post: ID=0 makes step2 skip category/tag lookups and fall through to
            // pure keyword search, which is the right behavior for an unpublished draft.
            $post = new stdClass();
            $post->ID = 0;
            $post->post_title = (string) $draft['title'];
            $post->post_content = (string) $draft['content'];
            $post->post_excerpt = '';
            $post->post_type = !empty( $draft['post_type'] ) ? (string) $draft['post_type'] : 'post';
            $is_draft = true;
          } else {
            return [ 'success' => false, 'error' => 'Either post_id or draft_content is required.' ];
          }

          $max_candidates = isset( $args['max_candidates'] ) ? max( 1, min( 10, (int) $args['max_candidates'] ) ) : 10;
          $magic = $this->core->pro->magic_fix;

          // Steps 1-3 only. Step 4 (placement generation) is sequential AI calls
          // that easily blow past MCP timeouts; call mwseo_generate_internal_link_placements
          // per chosen target instead.
          $keywords = $magic->internal_links_step1( $post );
          $candidates = $magic->internal_links_step2( $post, $keywords );
          $selected_ids = $magic->internal_links_step3( $post, $candidates );

          $suggestions = [];
          $processed = 0;
          foreach ( $selected_ids as $target_id ) {
            if ( $processed >= $max_candidates ) break;

            $target_post = get_post( (int) $target_id );
            if ( !$target_post ) continue;

            $context = $magic->extract_candidate_context( $target_post, $keywords );

            $suggestions[] = [
              'post_id' => $target_post->ID,
              'post_title' => $target_post->post_title,
              'post_url' => get_permalink( $target_post ),
              'context_excerpt' => $context,
            ];
            $processed++;
          }

          return [
            'success' => true,
            'data' => [
              'mode' => $is_draft ? 'draft' : 'post',
              'keywords' => $keywords,
              'candidates_considered' => count( $candidates ),
              'suggestions' => $suggestions,
              'next_step' => 'For each promising candidate, call mwseo_generate_internal_link_placements with target_post_id=<candidate post_id> to get placement suggestions.'
            ]
          ];

        case 'mwseo_suggest_seo_title': {
          if ( empty( $args['post_id'] ) ) {
            return [ 'success' => false, 'error' => 'post_id is required.' ];
          }
          $post = get_post( (int) $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found.' ];
          }
          global $mwai;
          if ( !$mwai ) {
            return [ 'success' => false, 'error' => 'AI Engine is not available.' ];
          }

          $count = isset( $args['count'] ) ? max( 1, min( 10, (int) $args['count'] ) ) : 3;
          $target_query = !empty( $args['target_query'] ) ? trim( (string) $args['target_query'] ) : '';
          $current_title = get_post_meta( $post->ID, $this->core->meta_key_seo_title, true ) ?: $post->post_title;
          $language = $this->core->get_post_language_name( $post->ID );
          $excerpt = wp_trim_words( strip_tags( $post->post_content ), 60 );

          $query_line = $target_query !== ''
            ? sprintf( 'The page should rank for: "%s". Include this phrasing naturally where it fits.', $target_query )
            : '';

          $prompt = sprintf(
            "Write %d distinct, compelling SEO title candidates for the post below. Titles must:\n" .
            "- Be 30 to 70 characters\n" .
            "- Be specific and click-worthy (no clickbait, no all-caps)\n" .
            "- Avoid emoji and trailing punctuation\n" .
            "%s\n" .
            "\nCurrent title: %s\nPost content sample: %s\n\nReturn ONLY a JSON array of strings, nothing else.",
            $count, $query_line, $current_title, $excerpt
          );
          $prompt = sprintf( '<instructions>Reply ONLY with a JSON array of %d title strings in %s. No other text, no markdown fences.</instructions> <prompt>%s</prompt>', $count, $language, $prompt );

          $raw = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo' ] );
          $raw = trim( (string) $raw );
          $raw = preg_replace( '/^```(json)?\s*/m', '', $raw );
          $raw = preg_replace( '/```\s*$/m', '', $raw );
          $candidates = json_decode( trim( $raw ), true );
          if ( !is_array( $candidates ) ) {
            return [ 'success' => false, 'error' => 'AI returned an unparseable response.', '_raw' => substr( $raw, 0, 200 ) ];
          }
          $candidates = array_values( array_filter( array_map( 'trim', array_map( 'strval', $candidates ) ) ) );
          $candidates = array_slice( $candidates, 0, $count );

          return [
            'success' => true,
            'data' => [
              'post_id' => $post->ID,
              'current_title' => $current_title,
              'candidates' => $candidates,
              'next_step' => sprintf( 'Pick a candidate and apply it with mwseo_set_seo_title post_id=%d title="<chosen>".', $post->ID )
            ]
          ];
        }

        case 'mwseo_suggest_seo_excerpt': {
          if ( empty( $args['post_id'] ) ) {
            return [ 'success' => false, 'error' => 'post_id is required.' ];
          }
          $post = get_post( (int) $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found.' ];
          }
          global $mwai;
          if ( !$mwai ) {
            return [ 'success' => false, 'error' => 'AI Engine is not available.' ];
          }

          $count = isset( $args['count'] ) ? max( 1, min( 10, (int) $args['count'] ) ) : 3;
          $target_query = !empty( $args['target_query'] ) ? trim( (string) $args['target_query'] ) : '';
          $current_excerpt = get_post_meta( $post->ID, $this->core->meta_key_seo_excerpt, true ) ?: $post->post_excerpt;
          $language = $this->core->get_post_language_name( $post->ID );
          $content_sample = wp_trim_words( strip_tags( $post->post_content ), 120 );

          $query_line = $target_query !== ''
            ? sprintf( 'The page should rank for: "%s". Include this phrasing naturally where it fits.', $target_query )
            : '';

          $prompt = sprintf(
            "Write %d distinct meta description candidates for the post below. Each must:\n" .
            "- Be 120 to 155 characters\n" .
            "- Summarize the page's actual value and invite the click (no clickbait, no all-caps)\n" .
            "- Avoid emoji and quotation marks\n" .
            "%s\n" .
            "\nPost title: %s\nCurrent meta description: %s\nPost content sample: %s\n\nReturn ONLY a JSON array of strings, nothing else.",
            $count, $query_line, $post->post_title, ( $current_excerpt ?: '(none)' ), $content_sample
          );
          $prompt = sprintf( '<instructions>Reply ONLY with a JSON array of %d meta description strings in %s. No other text, no markdown fences.</instructions> <prompt>%s</prompt>', $count, $language, $prompt );

          $raw = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo' ] );
          $raw = trim( (string) $raw );
          $raw = preg_replace( '/^```(json)?\s*/m', '', $raw );
          $raw = preg_replace( '/```\s*$/m', '', $raw );
          $candidates = json_decode( trim( $raw ), true );
          if ( !is_array( $candidates ) ) {
            return [ 'success' => false, 'error' => 'AI returned an unparseable response.', '_raw' => substr( $raw, 0, 200 ) ];
          }
          $candidates = array_values( array_filter( array_map( 'trim', array_map( 'strval', $candidates ) ) ) );
          $candidates = array_slice( $candidates, 0, $count );

          return [
            'success' => true,
            'data' => [
              'post_id' => $post->ID,
              'current_excerpt' => $current_excerpt,
              'candidates' => $candidates,
              'next_step' => sprintf( 'Pick a candidate and apply it with mwseo_set_seo_excerpt post_id=%d excerpt="<chosen>".', $post->ID )
            ]
          ];
        }

        case 'mwseo_generate_internal_link_placements':
          if ( !$this->core->pro || !$this->core->pro->magic_fix ) {
            return [ 'success' => false, 'error' => 'Magic Fix is not available (Pro required).' ];
          }
          if ( empty( $args['target_post_id'] ) ) {
            return [ 'success' => false, 'error' => 'target_post_id is required.' ];
          }
          $target_post = get_post( (int) $args['target_post_id'] );
          if ( !$target_post ) {
            return [ 'success' => false, 'error' => 'Target post not found.' ];
          }

          // Resolve source: real post or draft pseudo-post
          if ( !empty( $args['post_id'] ) ) {
            $source = get_post( $args['post_id'] );
            if ( !$source ) {
              return [ 'success' => false, 'error' => 'Source post not found.' ];
            }
          } else if ( !empty( $args['draft_content'] ) && is_array( $args['draft_content'] ) ) {
            $draft = $args['draft_content'];
            if ( empty( $draft['title'] ) || empty( $draft['content'] ) ) {
              return [ 'success' => false, 'error' => 'draft_content requires both title and content.' ];
            }
            $source = new stdClass();
            $source->ID = 0;
            $source->post_title = (string) $draft['title'];
            $source->post_content = (string) $draft['content'];
            $source->post_excerpt = '';
            $source->post_type = !empty( $draft['post_type'] ) ? (string) $draft['post_type'] : 'post';
          } else {
            return [ 'success' => false, 'error' => 'Either post_id or draft_content is required.' ];
          }

          $result = $this->core->pro->magic_fix->internal_links_step4( $source, $target_post );
          return [
            'success' => true,
            'data' => is_array( $result ) ? $result : [
              'post_id' => $target_post->ID,
              'post_title' => $target_post->post_title,
              'post_url' => get_permalink( $target_post ),
              'options' => [],
              'note' => 'No natural placement found.'
            ]
          ];

        // Google Search Console (Pro)
        case 'mwseo_gsc_status': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          $client_id = $this->core->get_option( 'google_analytics_client_id', '' );
          $client_secret = $this->core->get_option( 'google_analytics_client_secret', '' );

          if ( empty( $client_id ) || empty( $client_secret ) ) {
            return [
              'success' => true,
              'connected' => false,
              'summary' => '⚠️ Google OAuth credentials are not configured. Set google_analytics_client_id and google_analytics_client_secret in Settings. Those credentials are shared with Google Search Console.',
              'next_step' => 'Open Settings → Analytics → set Client ID and Client Secret from your Google Cloud Console OAuth client.'
            ];
          }

          if ( !$gsc->is_authenticated() ) {
            return [
              'success' => true,
              'connected' => false,
              'summary' => '⚠️ Not yet connected to Google Search Console. Open the authorization URL below in a browser, sign in, and authorize.',
              'auth_url' => $gsc->get_auth_url(),
              'next_step' => 'Open auth_url in a browser, authorize, then call mwseo_gsc_status again.'
            ];
          }

          $properties = $gsc->list_properties();
          $current_property = $gsc->get_property();

          if ( empty( $current_property ) ) {
            $suggestion = !empty( $properties ) ? $properties[0]['site_url'] : null;
            $summary = $suggestion
              ? sprintf( '🟡 Connected, but no property selected yet. Suggested: %s. Call mwseo_gsc_set_property to choose.', $suggestion )
              : '🟡 Connected, but no verified properties were returned by Google. Make sure your Google account owns at least one verified Search Console property.';
            return [
              'success' => true,
              'connected' => true,
              'property' => null,
              'available_properties' => $properties ?: [],
              'summary' => $summary,
              'next_step' => $suggestion ? "Call mwseo_gsc_set_property with property=\"$suggestion\"." : null
            ];
          }

          return [
            'success' => true,
            'connected' => true,
            'property' => $current_property,
            'available_properties' => $properties ?: [],
            'summary' => sprintf( '✅ Connected to %s. Run mwseo_gsc_quick_wins for ranked opportunities, mwseo_gsc_top_pages or mwseo_gsc_top_queries for exploration, or mwseo_gsc_post_pulse on any post_id.', $current_property )
          ];
        }

        case 'mwseo_gsc_set_property': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          if ( empty( $args['property'] ) ) {
            return [ 'success' => false, 'error' => 'property is required.' ];
          }
          $this->core->pro->search_console->set_property( (string) $args['property'] );
          return [
            'success' => true,
            'property' => (string) $args['property'],
            'summary' => sprintf( '✅ Active property set to %s.', $args['property'] )
          ];
        }

        case 'mwseo_gsc_quick_wins': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return $gsc->get_quick_wins( $args );
        }

        case 'mwseo_gsc_ai_overview_suspects': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return $gsc->get_ai_overview_suspects( $args );
        }

        case 'mwseo_gsc_site_pulse': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return [ 'success' => true, 'data' => $gsc->get_summary( $args ) ];
        }

        case 'mwseo_gsc_weekly_digest': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return $gsc->get_weekly_digest( $args );
        }

        case 'mwseo_gsc_post_pulse': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          if ( empty( $args['post_id'] ) ) {
            return [ 'success' => false, 'error' => 'post_id is required.' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          $days = isset( $args['days'] ) ? max( 7, min( 90, (int) $args['days'] ) ) : 28;
          $property = !empty( $args['property'] ) ? (string) $args['property'] : null;
          $debug = !empty( $args['debug'] );
          $result = $gsc->get_post_pulse( (int) $args['post_id'], $days, $property, $debug );
          if ( $result === false ) {
            return [ 'success' => false, 'error' => $gsc->get_last_error() ?: 'Unknown error' ];
          }
          return [ 'success' => true, 'data' => $result ];
        }

        case 'mwseo_gsc_top_queries': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return [ 'success' => true, 'data' => $gsc->get_top_queries( $args ) ];
        }

        case 'mwseo_gsc_top_pages': {
          if ( !$this->core->pro || !$this->core->pro->search_console ) {
            return [ 'success' => false, 'error' => 'Search Console module is not available (Pro required).' ];
          }
          $gsc = $this->core->pro->search_console;
          if ( !$gsc->is_authenticated() || ( !$gsc->get_property() && empty( $args['property'] ) ) ) {
            return [ 'success' => false, 'error' => 'Not connected to Google Search Console, or no property set. Run mwseo_gsc_status first, or pass a property argument.' ];
          }
          return [ 'success' => true, 'data' => $gsc->get_top_pages( $args ) ];
        }

        // Post Management
        case 'mwseo_skip_post':
          $post = get_post( $args['post_id'] );
          if ( !$post ) {
            return [ 'success' => false, 'error' => 'Post not found' ];
          }

          $skip = $args['skip'] ?? true;

          if ( $skip ) {
            update_post_meta( $post->ID, '_mwseo_status', 'skip' );
            $message = 'Post marked to skip SEO analysis';
          } else {
            delete_post_meta( $post->ID, '_mwseo_status', 'skip' );
            $message = 'Post unmarked from skip list';
          }

          return [
            'success' => true,
            'data' => [
              'post_id' => $post->ID,
              'skipped' => $skip
            ],
            'message' => $message
          ];
      }
    }
    catch ( Exception $e ) {
      return [ 'success' => false, 'error' => $e->getMessage() ];
    }

    return $result;
  }
}