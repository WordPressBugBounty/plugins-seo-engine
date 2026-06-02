<?php

/**
 * SEO Engine Pro - Score
 * Two-pillar scoring system: Content Quality + Technical
 * Implements normalized 0-100 scoring with context-aware targets
 */
class Meow_MWSEO_Score {
	private $core;
	private $options;
	private $ai_enabled = false;

	// Default configuration
	private $defaults = [
		'bands' => ['good' => [70, 100], 'warn' => [40, 69], 'bad' => [0, 39]],
		// Penalty points: Start with 100 points, each failed check deducts points
		// Total possible penalties FAR EXCEED 100, so multiple issues drive score to 0
		// Higher penalty = more critical issue
		'penalties' => [
			// CRITICAL Intelligence Checks (AI-powered quality)
			'semantic_alignment' => 25,  // Title must match content meaning
			'grammar_typos' => 20,        // Clean writing is essential
			'authenticity_originality' => 20, // Original, not AI-like content

			// CRITICAL Basics Checks (rule-based technical)
			'title_exists' => 40,         // Absolutely required
			'title_unique_sitewide' => 20, // Duplicate titles are terrible
			'schema_integrity' => 18,     // Schema present + required fields complete
			'internal_links' => 20,       // Essential for site structure
			'not_orphaned' => 15,         // Must be linked from other pages
			'structure_quality' => 15,    // Heading hierarchy, paragraph length, scannability
			'topic_completeness' => 15,   // AI analyzes if key subtopics are covered

			// VERY IMPORTANT Checks
			'alt_coverage' => 15,         // Accessibility & SEO
			'js_rendered_content' => 15,  // Main content must be in HTML, not JS-injected
			'intent_fit' => 12,           // Content length must fit purpose
			'readability_score' => 12,    // Content Clarity: structure + lists + sentence clarity
			'excerpt_exists' => 10,       // Meta description needed
			'featured_image' => 10,       // Visual presence matters
			'personality_engagement' => 10, // Human voice and personal touch

			// IMPORTANT Checks
			'excerpt_length' => 8,        // Proper meta description length
			'title_length' => 8,          // Title must fit in search results
			'content_depth' => 8,         // Adequate content needed (post-type based)

			// MODERATE Checks
			'slug_structure' => 5,        // Length + word count combined
			'external_link_present' => 5, // Has at least one external link
			'meta_robots_tag' => 5,       // No accidental noindex/nofollow
			'author_visible' => 3,
		],
		'comfort_zones' => [
			'quick' => [150, 500],
			'guide' => [700, 1600],
			'product' => [300, 900],
			'local' => [300, 900],
		],
		'safeguards' => [
			'require_title' => true,
			'require_unique_title' => true,
			'prevent_unintended_noindex' => true,
			'require_schema_when_expected' => true,
			'min_semantic_alignment' => 0.35
		],
		'quality_safeguards_enabled' => true,
	];

	public function __construct( $core ) {
		$this->core = $core;
		$this->options = $core->get_all_options();

		// Note: Don't check for $mwai here - it's created during plugins_loaded hook
		// We'll check at runtime in analyze_ai()
		$this->ai_enabled = true; // Will be validated at runtime

		// Merge user options with defaults
		$this->defaults = apply_filters( 'seo_engine_v2_defaults', $this->defaults );
	}

	/**
	 * Main calculation function - analyzes and scores a post
	 * @param object $post WordPress post object
	 * @param string $analysis_type 'quick' for basic checks only, 'full' for AI analysis, 'baseline' for technical only
	 */
	public function calculate( $post, $analysis_type = 'full' ) {
		// Collect content signals
		$analysis = $this->analyze_post( $post );

		// AI stage (only run for 'full' analysis and if AI is enabled)
		if ( $analysis_type === 'full' && $this->ai_enabled ) {
			$analysis['ai'] = $this->analyze_ai( $post, $analysis );
		} else if ( $analysis_type === 'baseline' ) {
			// For baseline (tech-step), always use default AI data - don't preserve old AI scores
			// This ensures AI steps will properly trigger penalty animations when they run
			$analysis['ai'] = $this->get_default_ai_data();
		} else {
			if ( $analysis_type === 'full' ) {
				$analysis['ai'] = $this->get_default_ai_data();
			} else {
				// Preserve existing AI data from previous Full Analysis
				$existing_data = get_post_meta( $post->ID, '_mwseo_analysis', true );
				if ( $existing_data && isset( $existing_data['ai'] ) ) {
					$analysis['ai'] = $existing_data['ai'];
				} else {
					$analysis['ai'] = $this->get_default_ai_data();
				}
			}
		}

		// Compute test scores
		$tests = $this->score_tests( $post, $analysis );

		// Detect flags
		$flags = $this->detect_flags( $post, $tests );

		// Calculate overall score using penalty system
		$overall = $this->calculate_score_from_penalties( $tests, $flags );

		// Build result
		$result = [
			'overall' => $overall,
			'tests' => $tests,
			'ai' => $analysis['ai'],
			'flags' => $flags,
			'penalties' => $this->calculate_applied_penalties( $tests ),
			'max_penalties' => $this->defaults['penalties'], // Max penalty for each test
			'word_count' => $analysis['word_count'],
			'title_length' => $analysis['title_length'],
			'excerpt_length' => $analysis['excerpt_length'],
			'version' => 3,  // Bumped version for new scoring system
			'timestamp' => time(),
			'cache_hit' => isset( $analysis['ai']['_cache_hit'] ) ? true : false
		];

		// Add AI feedback fields to top level for easy frontend access
		if ( isset( $analysis['ai'] ) ) {
			if ( isset( $analysis['ai']['grammar_feedback'] ) ) {
				$result['grammar_feedback'] = $analysis['ai']['grammar_feedback'];
			}
			if ( isset( $analysis['ai']['topic_feedback'] ) ) {
				$result['topic_feedback'] = $analysis['ai']['topic_feedback'];
			}
			if ( isset( $analysis['ai']['readability_feedback'] ) ) {
				$result['readability_feedback'] = $analysis['ai']['readability_feedback'];
			}
			if ( isset( $analysis['ai']['authenticity_feedback'] ) ) {
				$result['authenticity_feedback'] = $analysis['ai']['authenticity_feedback'];
			}
			if ( isset( $analysis['ai']['personality_feedback'] ) ) {
				$result['personality_feedback'] = $analysis['ai']['personality_feedback'];
			}
		}

		return $result;
	}

	/**
	 * Analyze post content and extract signals
	 */
	private function analyze_post( $post ) {
		$check_live_content = $this->get_option( 'check_live_content', false );

		$seo_title = get_post_meta( $post->ID, '_mwseo_title', true );
		$title = !empty( $seo_title ) ? $seo_title : $post->post_title;
		$excerpt = get_post_meta( $post->ID, '_mwseo_excerpt', true ) ?: $post->post_excerpt;

		// Calculate full page title length (as it appears in search results)
		if ( !empty( $seo_title ) ) {
			$full_page_title = $seo_title;
		} else {
			$site_name = trim( get_bloginfo( 'name' ) );
			$full_page_title = $post->post_title . " | " . $site_name;
		}

		$analysis = [
			'title' => $post->post_title,
			'slug' => $post->post_name,
			'excerpt' => $excerpt,
			'content' => $check_live_content ? $this->core->get_live_content( $post ) : wp_strip_all_tags( $post->post_content ),
			'content_html' => $check_live_content ? $this->core->get_live_content( $post, false ) : $post->post_content,
			'word_count' => 0,
			'title_length' => mb_strlen( $full_page_title ),
			'excerpt_length' => mb_strlen( $excerpt ),
			'images' => [],
			'links' => ['internal' => [], 'external' => []],
		];

		// Word count with CJK fallback
		$word_count = str_word_count( $analysis['content'] );

		// CJK override: If content is mostly CJK and word count is near zero, estimate from character length
		if ( $word_count < 10 && $this->core->is_mostly_cjk( $analysis['content'], 0.9 ) ) {
			// Estimate word count: each CJK character ≈ 0.6 words (conservative multiplier)
			$char_length = mb_strlen( $analysis['content'], 'UTF-8' );
			$word_count = max( 1, floor( $char_length * 0.6 ) );
		}

		$analysis['word_count'] = $word_count;

		// Extract images
		preg_match_all( '/<img[^>]+>/i', $analysis['content_html'], $images );
		if ( !empty( $images[0] ) ) {
			foreach ( $images[0] as $img ) {
				$has_alt = preg_match( '/alt=[\'"]([^\'"]*)[\'"]/', $img, $alt_match );
				$analysis['images'][] = [
					'tag' => $img,
					'alt' => $has_alt ? $alt_match[1] : '',
					'has_alt' => $has_alt && !empty( $alt_match[1] )
				];
			}
		}

		// Extract links
		if ( preg_match_all( '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $analysis['content_html'], $matches ) ) {
			// Use the post's permalink to derive the site domain, so that
			// multilingual setups (e.g. Polylang with separate domains) correctly
			// detect internal links based on the post's own language domain.
			$permalink = get_permalink( $post->ID );
			$parsed = parse_url( $permalink );
			$site_host = isset( $parsed['host'] ) ? $parsed['host'] : parse_url( get_site_url(), PHP_URL_HOST );

			foreach ( $matches[1] as $link ) {
				$link_trimmed = trim( $link );

				// Skip empty or anchor links
				if ( empty( $link_trimmed ) || $link_trimmed === '#' || strpos( $link_trimmed, '#' ) === 0 ) {
					continue;
				}

				// Internal: relative links (/, ./, ../), or same domain as the post
				$is_relative = ( strpos( $link_trimmed, '/' ) === 0 && strpos( $link_trimmed, '//' ) !== 0 )
					|| strpos( $link_trimmed, './' ) === 0
					|| strpos( $link_trimmed, '../' ) === 0;
				$link_host = parse_url( $link_trimmed, PHP_URL_HOST );
				$is_same_domain = $link_host && $link_host === $site_host;

				if ( $is_relative || $is_same_domain ) {
					$analysis['links']['internal'][] = $link_trimmed;
				} elseif ( preg_match( '#^https?://#', $link_trimmed ) ) {
					$analysis['links']['external'][] = $link_trimmed;
				}
			}
		}

		return $analysis;
	}

	/**
	 * AI analysis - semantic alignment, intent, summary, entities
	 */
	private function analyze_ai( $post, $analysis ) {
		global $mwai;

		$ai_data = [
			'summary' => '',
			'confidence' => 0.0,
			'intent' => 'unknown',
			'entities' => [],
			'semantic_alignment' => 0.0,
		];

		if ( !$mwai ) {
			return $ai_data;
		}

		if ( !method_exists( $mwai, 'simpleTextQuery' ) ) {
			return $ai_data;
		}

		// PERFORMANCE FIX: Cache AI analysis based on content hash
		// Generate cache key based on full raw content + enabled checks
		// Using full content ensures any change (even adding a link) invalidates cache
		$cache_key_data = [
			'title' => $analysis['title'],
			'content' => $analysis['content'], // Full content for accurate change detection
			'content_html' => $analysis['content_html'], // Include HTML to detect link changes
			'checks' => [
				'grammar' => $this->core->get_option( 'check_grammar_typos', false ),
				'authenticity' => $this->core->get_option( 'check_authenticity_originality', false ),
				'personality' => $this->core->get_option( 'check_personality_engagement', false ),
				'structure' => $this->core->get_option( 'check_structure_quality', false ),
				'readability' => $this->core->get_option( 'check_readability_score', false ),
				'topic' => $this->core->get_option( 'check_topic_completeness', false ),
			],
		];
		$content_hash = md5( json_encode( $cache_key_data ) );
		$cache_key = 'seo_engine_ai_' . $post->ID . '_' . $content_hash;

		// Try to get cached results (7 day expiration)
		$cached_ai_data = get_transient( $cache_key );
		if ( $cached_ai_data !== false && is_array( $cached_ai_data ) ) {
			$cached_ai_data['_cache_hit'] = true;
			return $cached_ai_data;
		}

		try {
			// Generate summary and detect intent
			$summary_prompt = "Analyze this content and provide:\n1. A one-sentence summary (max 90 chars)\n2. Content intent (choose one: QuickAnswer, Guide, HowTo, Product, Local, News, or General)\n3. Key entities/topics (max 5)\n\nTitle: {$analysis['title']}\nContent: " . $this->truncate_at_sentence( $analysis['content'], 1000 ) . "\n\nRespond in JSON format: {\"summary\": \"...\", \"intent\": \"...\", \"entities\": [...], \"confidence\": 0.0-1.0}";

			$response = $mwai->simpleTextQuery( $summary_prompt );

			// Remove markdown code blocks if present
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			// Try to parse JSON from response
			$summary_result = json_decode( $response, true );

			if ( $summary_result && is_array( $summary_result ) ) {
				$ai_data['summary'] = $summary_result['summary'] ?? '';
				$ai_data['intent'] = $summary_result['intent'] ?? 'General';
				$ai_data['entities'] = $summary_result['entities'] ?? [];
				$ai_data['confidence'] = floatval( $summary_result['confidence'] ?? 0.5 );
			}

			// Calculate semantic alignment (title/excerpt vs content)
			$ai_data['semantic_alignment'] = $this->calculate_semantic_alignment( $post, $analysis );

			// Calculate recommended content length based on intent
			$ai_data['recommended_length'] = $this->calculate_recommended_length( $ai_data['intent'], $analysis['word_count'] );

			// Check grammar and typos if enabled
			$check_grammar = $this->core->get_option( 'check_grammar_typos', false );
			if ( $check_grammar ) {
				$grammar_result = $this->analyze_grammar( $analysis );
				$ai_data['grammar_score'] = $grammar_result['score'];
				$ai_data['grammar_feedback'] = $grammar_result['feedback'];
			}

			// Check authenticity & originality if enabled
			$check_authenticity = $this->core->get_option( 'check_authenticity_originality', false );
			if ( $check_authenticity ) {
				$authenticity_result = $this->analyze_authenticity_originality( $analysis );
				$ai_data['authenticity_score'] = $authenticity_result['score'];
				$ai_data['authenticity_feedback'] = $authenticity_result['feedback'];
			}

			// Check personality & engagement if enabled
			$check_personality = $this->core->get_option( 'check_personality_engagement', false );
			if ( $check_personality ) {
				$personality_result = $this->analyze_personality_engagement( $analysis );
				$ai_data['personality_score'] = $personality_result['score'];
				$ai_data['personality_feedback'] = $personality_result['feedback'];
			}

			// Check structure quality if enabled
			$check_structure = $this->core->get_option( 'check_structure_quality', false );
			if ( $check_structure ) {
				$ai_data['structure_score'] = $this->analyze_structure_quality( $analysis );
			}

			// Check readability score if enabled
			$check_readability = $this->core->get_option( 'check_readability_score', false );
			if ( $check_readability ) {
				$readability_result = $this->analyze_readability( $analysis );
				$ai_data['readability_score'] = $readability_result['score'];
				$ai_data['readability_feedback'] = $readability_result['feedback'];
			}

			// Check topic completeness if enabled
			$check_topic_completeness = $this->core->get_option( 'check_topic_completeness', false );
			if ( $check_topic_completeness ) {
				$topic_result = $this->analyze_topic_completeness( $analysis );
				$ai_data['topic_completeness'] = $topic_result['score'];
				$ai_data['topic_feedback'] = $topic_result['feedback'];
			}

		} catch ( Exception $e ) {
			error_log( 'SEO Engine AI Analysis Error: ' . $e->getMessage() );
		}

		// Cache the AI analysis results for 7 days (604800 seconds)
		// Allow filtering the cache duration
		$cache_duration = apply_filters( 'seo_engine_ai_cache_duration', 7 * DAY_IN_SECONDS );
		set_transient( $cache_key, $ai_data, $cache_duration );

		return $ai_data;
	}

	/**
	 * Calculate semantic alignment between title/meta and content
	 * Uses AI to determine if title accurately represents content
	 */
	private function calculate_semantic_alignment( $post, $analysis ) {
		global $mwai;

		try {
			$prompt = "Rate how well this title matches the main topic and content (0.0 to 1.0):\n\nTitle: {$analysis['title']}\n\nContent preview: " . $this->truncate_at_sentence( $analysis['content'], 1000 ) . "\n\nRate from 0.0 (completely unrelated) to 1.0 (perfectly aligned). Be generous - if the title accurately describes what the content is about, give 0.9 or higher. Only give low scores if the title is misleading or about a different topic. Respond with ONLY a number.";

			$result = $mwai->simpleFastTextQuery( $prompt );
			$score = floatval( trim( $result ) );


			// Ensure it's between 0 and 1
			return max( 0.0, min( 1.0, $score ) );

		} catch ( Exception $e ) {
			// Fallback: simple keyword overlap
			return $this->simple_semantic_alignment( $analysis );
		}
	}

	/**
	 * Simple fallback semantic alignment (keyword overlap)
	 */
	private function simple_semantic_alignment( $analysis ) {
		$title_words = array_filter( explode( ' ', strtolower( $analysis['title'] ) ), function( $w ) {
			return strlen( $w ) > 3; // Only words longer than 3 chars
		});

		$content_lower = strtolower( $analysis['content'] );
		$matches = 0;

		foreach ( $title_words as $word ) {
			if ( strpos( $content_lower, $word ) !== false ) {
				$matches++;
			}
		}

		return count( $title_words ) > 0 ? $matches / count( $title_words ) : 0.5;
	}

	/**
	 * Format AI feedback: clean up and limit length (keep markdown)
	 */
	private function format_ai_feedback( $text ) {
		if ( empty( $text ) ) {
			return '';
		}

		if ( is_array( $text ) ) {
			$text = implode( ' ', $text );
		}

		// Ensure $text is a string before processing
		$text = (string) $text;

		// Remove markdown headers (###, ##, #)
		$text = preg_replace( '/^#{1,6}\s+/m', '', $text );

		// Remove bullet points/list markers (-, *, •) but keep the text
		$text = preg_replace( '/^[\-\*•]\s+/m', '', $text );

		// Remove numbered list markers (1., 2., etc.)
		$text = preg_replace( '/^\d+[\.)]\s+/m', '', $text );

		// Clean up excessive whitespace
		$text = preg_replace( '/\s+/', ' ', $text );

		// Limit to approximately 150 characters to ensure complete sentences
		if ( strlen( $text ) > 150 ) {
			$text = substr( $text, 0, 150 );
			// Try to cut at last period
			$last_period = strrpos( $text, '.' );
			if ( $last_period !== false && $last_period > 80 ) {
				$text = substr( $text, 0, $last_period + 1 );
			} else {
				// Try to cut at last comma
				$last_comma = strrpos( $text, ',' );
				if ( $last_comma !== false && $last_comma > 80 ) {
					$text = substr( $text, 0, $last_comma ) . '...';
				} else {
					$text .= '...';
				}
			}
		}

		return trim( $text );
	}

	/**
	 * Truncate content at a sentence boundary to avoid feeding incomplete sentences to AI.
	 */
	private function truncate_at_sentence( $text, $max_chars = 1500 ) {
		if ( mb_strlen( $text ) <= $max_chars ) {
			return $text;
		}
		$truncated = mb_substr( $text, 0, $max_chars );
		// Find last sentence-ending punctuation (.!?。）)
		$last = max(
			mb_strrpos( $truncated, '.' ) ?: 0,
			mb_strrpos( $truncated, '!' ) ?: 0,
			mb_strrpos( $truncated, '?' ) ?: 0,
			mb_strrpos( $truncated, '。' ) ?: 0
		);
		// Only cut at sentence boundary if we keep at least 60% of the text
		if ( $last > $max_chars * 0.6 ) {
			return mb_substr( $truncated, 0, $last + 1 );
		}
		return $truncated;
	}

	/**
	 * Analyze grammar and typos in content using AI
	 */
	private function analyze_grammar( $analysis ) {
		global $mwai;

		if ( !$mwai ) {
			return ['score' => 'NA', 'feedback' => ''];
		}

		try {
			// Sample content for analysis, truncated at sentence boundary
			$content_sample = $this->truncate_at_sentence( $analysis['content'], 1500 );

			if ( empty( $content_sample ) ) {
				return ['score' => 100, 'feedback' => '']; // No content to analyze
			}

			$prompt = "Grammar check. Score 0-100. Only flag clear mistakes: misspellings, broken syntax, wrong conjugations, missing words. Ignore stylistic choices, hyphenation preferences, word choice opinions, and formatting. The text may be truncated — do NOT flag the last sentence as incomplete. If < 80, list 2 issues max (e.g., 'Typo: teh→the'). Very brief.\n\nText:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";

			$response = $mwai->simpleFastTextQuery( $prompt );
			$response = trim( $response );

			// Remove markdown code blocks if present
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			$result = json_decode( $response, true );

			if ( $result && is_array( $result ) && isset( $result['score'] ) ) {
				$score = intval( $result['score'] );
				$feedback = $this->format_ai_feedback( $result['feedback'] ?? '' );

				if ( $score < 0 || $score > 100 ) {
					return ['score' => 'NA', 'feedback' => ''];
				}

				return ['score' => $score, 'feedback' => $feedback];
			}

			return ['score' => 'NA', 'feedback' => ''];

		} catch ( Exception $e ) {
			return ['score' => 'NA', 'feedback' => ''];
		}
	}

	/**
	 * Analyze authenticity & originality using AI.
	 *
	 * Google's May 2026 AI Optimization Guide is explicit: "non-commodity"
	 * content with a "unique point of view that stands out" is what wins.
	 * The contrast Google gives is "7 Tips for First-Time Homebuyers"
	 * (commodity, recycled) vs. "Why We Waived the Inspection & Saved Money"
	 * (first-hand experience). This prompt scores on that exact axis, with
	 * AI-template phrasing as a secondary penalty.
	 */
	private function analyze_authenticity_originality( $analysis ) {
		global $mwai;

		if ( !$mwai ) {
			return ['score' => 'NA', 'feedback' => ''];
		}

		try {
			$content_sample = $this->truncate_at_sentence( $analysis['content'], 1500 );

			if ( empty( $content_sample ) ) {
				return ['score' => 100, 'feedback' => ''];
			}

			$prompt = "Score this post 0-100 on the commodity ↔ first-hand axis defined by Google's AI Optimization Guide:\n"
				. "- 100: unique point of view, first-hand experience, specific details, real anecdotes (e.g. 'Why We Waived the Inspection & Saved Money')\n"
				. "- 50: solid general advice, but mostly things anyone could write from research (e.g. '7 Tips for First-Time Homebuyers')\n"
				. "- 0: recycled commodity content, full of AI-template phrases like 'In today's fast-paced world' or 'It's important to note that'\n"
				. "Creative, literary, or poetic writing counts as first-hand. Be honest, not generous — generic content is the norm and should score in the 40-60 range.\n"
				. "If < 70, give one sentence of feedback naming the specific weakness (commodity framing, missing personal angle, AI-template phrases, etc).\n\n"
				. "Text:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";

			$response = $mwai->simpleFastTextQuery( $prompt );
			$response = trim( $response );

			// Remove markdown code blocks if present
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			$result = json_decode( $response, true );

			if ( $result && is_array( $result ) && isset( $result['score'] ) ) {
				$score = intval( $result['score'] );
				$feedback = $this->format_ai_feedback( $result['feedback'] ?? '' );

				if ( $score < 0 || $score > 100 ) {
					return ['score' => 'NA', 'feedback' => ''];
				}

				return ['score' => $score, 'feedback' => $feedback];
			}

			return ['score' => 'NA', 'feedback' => ''];

		} catch ( Exception $e ) {
			return ['score' => 'NA', 'feedback' => ''];
		}
	}

	/**
	 * Analyze personality & engagement using AI
	 * Checks for human voice, personal touch, emotional connection
	 */
	private function analyze_personality_engagement( $analysis ) {
		global $mwai;

		if ( !$mwai ) {
			return ['score' => 'NA', 'feedback' => ''];
		}

		try {
			$content_sample = $this->truncate_at_sentence( $analysis['content'], 1500 );

			if ( empty( $content_sample ) ) {
				return ['score' => 100, 'feedback' => ''];
			}

			$prompt = "Personality check. Score 0-100. If < 70, list 2 suggestions max. Very brief.\n\nText:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";

			$response = $mwai->simpleFastTextQuery( $prompt );
			$response = trim( $response );

			// Remove markdown code blocks if present
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			$result = json_decode( $response, true );

			if ( $result && is_array( $result ) && isset( $result['score'] ) ) {
				$score = intval( $result['score'] );
				$feedback = $this->format_ai_feedback( $result['feedback'] ?? '' );

				if ( $score < 0 || $score > 100 ) {
					return ['score' => 'NA', 'feedback' => ''];
				}

				return ['score' => $score, 'feedback' => $feedback];
			}

			return ['score' => 'NA', 'feedback' => ''];

		} catch ( Exception $e ) {
			return ['score' => 'NA', 'feedback' => ''];
		}
	}

	/**
	 * Analyze content structure quality using AI
	 * Checks for heading hierarchy, paragraph length, scannability
	 */
	private function analyze_structure_quality( $analysis ) {
		global $mwai;

		if ( !$mwai ) {
			return 'NA';
		}

		try {
			// Use HTML content to analyze structure
			$content_html = $analysis['content_html'];

			if ( empty( $content_html ) ) {
				return 100;
			}

			// Strip Gutenberg block comments (<!-- wp:xxx --> / <!-- /wp:xxx -->)
			// so the AI sees actual HTML structure, not editor noise.
			$clean_html = preg_replace( '/<!--\s*\/?wp:[^>]*-->\s*/', '', $content_html );
			$clean_html = trim( $clean_html );

			if ( empty( $clean_html ) ) {
				return 100;
			}

			$content_sample = mb_substr( $clean_html, 0, 3000, 'UTF-8' );

			$prompt = "Analyze this HTML content structure. Rate from 0-100. Only penalize real problems:\n\n- Wall of text: no paragraphs or headings at all\n- Extremely long paragraphs (300+ words without a break)\n- Completely missing subheadings in long content (1000+ words)\n\nDo NOT penalize:\n- Articles that use normal paragraph lengths (even 100-200 words)\n- Content without bullet lists (lists are not required)\n- Literary, editorial, or photo-essay writing styles\n- Content that simply has fewer headings if paragraphs are reasonable\n\nMost well-structured articles should score 80+. Only give below 60 for genuinely hard-to-read walls of text.\n\nHTML:\n{$content_sample}\n\nRespond with ONLY a number between 0 and 100.";

			$result = $mwai->simpleFastTextQuery( $prompt );
			$result = trim( $result );

			$score = intval( $result );

			if ( $score < 0 || $score > 100 ) {
				return 'NA';
			}

			return $score;

		} catch ( Exception $e ) {
			return 'NA';
		}
	}

	/**
	 * Score Content Clarity (human skimmability).
	 *
	 * Delegates to Meow_MWSEO_Modules_Readability. The class name there is kept
	 * for backwards compatibility (it used to be Flesch), but the actual scoring
	 * is now structure + lists + sentence clarity — written for human readers
	 * per Google's May 2026 AI guidance (which explicitly says no AI-specific
	 * chunking is needed). Optional AI feedback appended on low scores when
	 * AI Engine is available.
	 */
	private function analyze_readability( $analysis ) {
		global $mwseo_readability, $mwai;

		$content = $analysis['content'];
		if ( empty( $content ) ) {
			return [ 'score' => 100, 'feedback' => '' ];
		}

		// Defensive fallback if the global isn't wired (shouldn't happen in practice).
		if ( !$mwseo_readability ) {
			require_once dirname( __FILE__ ) . '/modules/readability.php';
			$mwseo_readability = new Meow_MWSEO_Modules_Readability();
		}

		$result = $mwseo_readability->calculate_readability( $content );
		$score = (int) ( $result['score'] ?? 0 );

		// Build feedback from the suggestions the module produced.
		$feedback = '';
		if ( !empty( $result['suggestions'] ) ) {
			$feedback = implode( ' ', array_slice( $result['suggestions'], 0, 2 ) );
		}

		// Layer AI elaboration on top only when the score is genuinely weak.
		// The module already explains "what to fix"; AI adds a sentence on "how".
		if ( $score < 60 && $mwai ) {
			try {
				$content_sample = mb_substr( $content, 0, 1500, 'UTF-8' );
				$baseline = $feedback ? "Specific issues found: {$feedback}" : '';
				$prompt = "Suggest one concrete way to improve this post's structure or sentence clarity for human readers. One sentence. {$baseline}\n\nText:\n{$content_sample}";
				$response = $mwai->simpleFastTextQuery( $prompt );
				$ai_line = trim( $response );
				if ( $ai_line !== '' ) {
					$feedback = $this->format_ai_feedback( trim( ( $feedback ? $feedback . ' ' : '' ) . $ai_line ) );
				}
			} catch ( Exception $e ) {
				// Fall back to module suggestions; non-fatal.
			}
		}

		return [
			'score' => $score,
			'feedback' => $feedback,
			'breakdown' => $result['breakdown'] ?? null,
		];
	}

	/**
	 * Analyze topic completeness using AI
	 * Checks if key subtopics and questions are covered
	 */
	private function analyze_topic_completeness( $analysis ) {
		global $mwai;

		if ( !$mwai ) {
			return ['score' => 'NA', 'feedback' => ''];
		}

		try {
			$title = $analysis['title'];
			$content_sample = $this->truncate_at_sentence( $analysis['content'], 2000 );

			if ( empty( $title ) || empty( $content_sample ) ) {
				return ['score' => 100, 'feedback' => ''];
			}

			$prompt = "Topic coverage for '{$title}'. Score 0-100. If < 80, list 2 missing topics max. Very brief.\n\nContent:\n{$content_sample}\n\nJSON: {\"score\": X, \"feedback\": \"...\"}";

			$response = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo' ] );
			$response = trim( $response );

			// Remove markdown code blocks if present
			$response = preg_replace( '/```json\s*/', '', $response );
			$response = preg_replace( '/```\s*$/', '', $response );
			$response = trim( $response );

			$result = json_decode( $response, true );

			if ( $result && is_array( $result ) && isset( $result['score'] ) ) {
				$score = intval( $result['score'] );
				$feedback = $this->format_ai_feedback( $result['feedback'] ?? '' );

				if ( $score < 0 || $score > 100 ) {
					return ['score' => 'NA', 'feedback' => ''];
				}

				return ['score' => $score, 'feedback' => $feedback];
			}

			return ['score' => 'NA', 'feedback' => ''];

		} catch ( Exception $e ) {
			return ['score' => 'NA', 'feedback' => ''];
		}
	}

	/**
	 * Default AI data when AI is disabled
	 */
	private function get_default_ai_data() {
		return [
			'summary' => '',
			'confidence' => 0.0,
			'intent' => 'unknown',
			'entities' => [],
			'semantic_alignment' => 0.0,
		];
	}

	/**
	 * Score all tests (0-100 or "NA")
	 */
	private function score_tests( $post, $analysis ) {
		$tests = [];

		// Run content_depth first (needed by intent_fit to avoid redundancy)
		$tests['content_depth'] = $this->test_content_depth( $post, $analysis );

		// CONTENT QUALITY TESTS
		$tests['excerpt_exists'] = $this->test_excerpt_exists( $analysis );
		$tests['excerpt_length'] = $this->test_excerpt_length( $analysis );
		$tests['alt_coverage'] = $this->test_alt_coverage( $analysis );
		$tests['semantic_alignment'] = $this->test_semantic_alignment( $analysis );
		$tests['intent_fit'] = $this->test_intent_fit( $analysis, $tests['content_depth'] );
		$tests['author_visible'] = $this->test_author_visible( $post );
		$tests['grammar_typos'] = $this->test_grammar_typos( $analysis );
		$tests['authenticity_originality'] = $this->test_authenticity_originality( $analysis );
		$tests['personality_engagement'] = $this->test_personality_engagement( $analysis );
		$tests['readability_score'] = $this->test_readability_score( $analysis );
		$tests['topic_completeness'] = $this->test_topic_completeness( $analysis );

		// TECHNICAL TESTS
		$tests['title_exists'] = $this->test_title_exists( $analysis );
		$tests['title_unique_sitewide'] = $this->test_title_unique( $post, $analysis );
		$tests['title_length'] = $this->test_title_length( $post, $analysis );
		$tests['slug_structure'] = $this->test_slug_structure( $analysis );
		$tests['internal_links'] = $this->test_internal_links( $analysis );
		$tests['external_link_present'] = $this->test_external_link_present( $analysis );
		$tests['not_orphaned'] = $this->test_not_orphaned( $post, $analysis );
		$tests['featured_image'] = $this->test_featured_image( $post );
		$tests['schema_integrity'] = $this->test_schema_integrity( $post, $analysis );
		// content_depth already calculated above
		$tests['structure_quality'] = $this->test_structure_quality( $analysis );
		$tests['meta_robots_tag'] = $this->test_meta_robots_tag( $post );
		$tests['js_rendered_content'] = $this->test_js_rendered_content( $post, $analysis );

		// Override ignored tests with perfect scores
		$ignored_tests = get_post_meta( $post->ID, '_mwseo_ignored_tests', true );
		if ( is_array( $ignored_tests ) && !empty( $ignored_tests ) ) {
			foreach ( $ignored_tests as $ignored_test ) {
				if ( isset( $tests[$ignored_test] ) ) {
					// Set to 100 (perfect score) to exclude from issues
					$tests[$ignored_test] = 100;
				}
			}
		}

		return $tests;
	}

	// ========================================
	// CONTENT QUALITY TEST IMPLEMENTATIONS
	// ========================================

	/**
	 * Helper: Score a value against a target range
	 * ±30% deviation = good (80), ±50% = warning (50), beyond = error (20)
	 */
	private function score_length_range( $actual, $min, $max ) {
		if ( $actual >= $min && $actual <= $max ) {
			return 100; // Perfect - within range
		}

		$range_size = $max - $min;

		// Below minimum
		if ( $actual < $min ) {
			$deviation = $min - $actual;
			$threshold_30 = $range_size * 0.3;
			$threshold_50 = $range_size * 0.5;

			if ( $deviation <= $threshold_30 ) {
				return 80; // Good - within 30%
			} elseif ( $deviation <= $threshold_50 ) {
				return 50; // Warning - within 50%
			} else {
				return 20; // Error - beyond 50%
			}
		}

		// Above maximum
		if ( $actual > $max ) {
			$deviation = $actual - $max;
			$threshold_30 = $range_size * 0.3;
			$threshold_50 = $range_size * 0.5;

			if ( $deviation <= $threshold_30 ) {
				return 80; // Good - within 30%
			} elseif ( $deviation <= $threshold_50 ) {
				return 50; // Warning - within 50%
			} else {
				return 20; // Error - beyond 50%
			}
		}

		return 50; // Fallback
	}

	private function test_excerpt_exists( $analysis ) {
		return !empty( $analysis['excerpt'] ) ? 100 : 0;
	}

	private function test_excerpt_length( $analysis ) {
		if ( empty( $analysis['excerpt'] ) ) return 'NA'; // Can't check length if excerpt doesn't exist

		// Use display width (CJK chars count as 2) for consistent SERP measurement
		$width = $this->core->get_display_width( $analysis['excerpt'] );

		// Perfect: 80-160 display units
		if ( $width >= 80 && $width <= 160 ) {
			return 100; // No penalty
		}

		// Partial penalty: 50-79 or 161-220 display units
		if ( ( $width >= 50 && $width < 80 ) || ( $width > 160 && $width <= 220 ) ) {
			return 60; // Partial -3 pts penalty (60% = -3.2 of max 8)
		}

		// Full penalty: <50 or >220 display units
		return 0; // Full -8 pts penalty
	}

	private function test_alt_coverage( $analysis ) {
		if( !$this->core->get_option( 'check_missing_alt_text', true ) ) {
			return 'NA';
		}

		if ( empty( $analysis['images'] ) || !is_array( $analysis['images'] ) ) {
			return 'NA';
		}

		$total_images = count( $analysis['images'] );

		if ( $total_images === 0 ) return 'NA';

		$with_alt = 0;
		foreach ( $analysis['images'] as $img ) {
			if ( $img['has_alt'] ) $with_alt++;
		}

		$coverage = $total_images > 0 ? ($with_alt / $total_images) * 100 : 0;

		// Soft floor: if some but not all have alt, minimum 60
		if ( $coverage > 0 && $coverage < 90 ) {
			return max( 60, $coverage );
		}

		return round( $coverage );
	}

	private function test_semantic_alignment( $analysis ) {
		if ( !$this->ai_enabled ) return 'NA';

		$alignment = $analysis['ai']['semantic_alignment'] ?? 0;

		// Return NA if no real AI data (default data has 0.0 alignment)
		if ( $alignment == 0 ) return 'NA';

		return round( $alignment * 100 );
	}

	private function test_intent_fit( $analysis, $content_depth_score = 100 ) {
		if ( !$this->ai_enabled ) return 'NA';

		// Skip if content_depth is already failing - no point checking AI intent fit
		// when the basic technical minimum isn't met
		if ( $content_depth_score !== 'NA' && $content_depth_score < 100 ) {
			return 'NA';
		}

		$intent = $analysis['ai']['intent'] ?? 'unknown';

		// Return NA if no real AI data (default data has 'unknown' intent)
		if ( $intent === 'unknown' ) return 'NA';

		$word_count = $analysis['word_count'];

		// Define ideal ranges for each intent type
		$ranges = [
			'QuickAnswer' => ['min' => 150, 'ideal_min' => 200, 'ideal_max' => 400, 'max' => 600],
			'News' => ['min' => 200, 'ideal_min' => 300, 'ideal_max' => 500, 'max' => 800],
			'Guide' => ['min' => 400, 'ideal_min' => 600, 'ideal_max' => 1200, 'max' => 2000],
			'HowTo' => ['min' => 400, 'ideal_min' => 600, 'ideal_max' => 1200, 'max' => 2000],
			'Product' => ['min' => 200, 'ideal_min' => 300, 'ideal_max' => 600, 'max' => 1000],
			'default' => ['min' => 300, 'ideal_min' => 400, 'ideal_max' => 800, 'max' => 1200]
		];

		$range = $ranges[$intent] ?? $ranges['default'];

		// Score based on where content falls in the range
		if ( $word_count >= $range['ideal_min'] && $word_count <= $range['ideal_max'] ) {
			return 100; // Perfect fit
		} else if ( $word_count >= $range['min'] && $word_count < $range['ideal_min'] ) {
			// A bit short, score proportionally (70-95)
			$ratio = ($word_count - $range['min']) / ($range['ideal_min'] - $range['min']);
			return max(70, round(70 + ($ratio * 25)));
		} else if ( $word_count > $range['ideal_max'] && $word_count <= $range['max'] ) {
			// A bit long, score proportionally (80-95)
			$ratio = ($range['max'] - $word_count) / ($range['max'] - $range['ideal_max']);
			return max(80, round(80 + ($ratio * 15)));
		} else if ( $word_count < $range['min'] ) {
			// Too short
			$ratio = $word_count / $range['min'];
			return max(40, round($ratio * 70));
		} else {
			// Too long but acceptable
			return 70;
		}
	}

	private function test_author_visible( $post ) {
		$author_id = $post->post_author;
		return $author_id > 0 ? 100 : 0;
	}

	private function test_grammar_typos( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_grammar_typos', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get grammar analysis from AI data
		if ( isset( $analysis['ai']['grammar_score'] ) ) {
			return $analysis['ai']['grammar_score'];
		}

		return 'NA';
	}

	private function test_authenticity_originality( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_authenticity_originality', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get authenticity analysis from AI data
		if ( isset( $analysis['ai']['authenticity_score'] ) ) {
			return $analysis['ai']['authenticity_score'];
		}

		return 'NA';
	}

	private function test_personality_engagement( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_personality_engagement', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get personality analysis from AI data
		if ( isset( $analysis['ai']['personality_score'] ) ) {
			return $analysis['ai']['personality_score'];
		}

		return 'NA';
	}

	private function test_structure_quality( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_structure_quality', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get structure quality analysis from AI data
		if ( isset( $analysis['ai']['structure_score'] ) ) {
			return $analysis['ai']['structure_score'];
		}

		return 'NA';
	}

	private function test_readability_score( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_readability_score', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get readability analysis from AI data
		if ( isset( $analysis['ai']['readability_score'] ) ) {
			return $analysis['ai']['readability_score'];
		}

		return 'NA';
	}

	private function test_topic_completeness( $analysis ) {
		// Check if this test is enabled
		$check_enabled = $this->core->get_option( 'check_topic_completeness', false );
		if ( !$check_enabled || !$this->ai_enabled ) {
			return 'NA';
		}

		// Get topic completeness analysis from AI data
		if ( isset( $analysis['ai']['topic_completeness'] ) ) {
			return $analysis['ai']['topic_completeness'];
		}

		return 'NA';
	}

	// ========================================
	// TECHNICAL TEST IMPLEMENTATIONS
	// ========================================

	private function test_title_exists( $analysis ) {
		return !empty( $analysis['title'] ) ? 100 : 0;
	}

	private function test_title_unique( $post, $analysis ) {
		global $wpdb;

		// Check if Polylang is active and get the post's language
		if ( function_exists( 'pll_get_post_language' ) ) {
			$post_language = pll_get_post_language( $post->ID, 'slug' );

			if ( $post_language ) {
				// Only check for duplicates within the same language
				$count = $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID)
					FROM $wpdb->posts p
					INNER JOIN $wpdb->term_relationships tr ON p.ID = tr.object_id
					INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					INNER JOIN $wpdb->terms t ON tt.term_id = t.term_id
					WHERE p.post_title = %s
					AND p.ID != %d
					AND p.post_status = 'publish'
					AND tt.taxonomy = 'language'
					AND t.slug = %s",
					$analysis['title'], $post->ID, $post_language
				) );

				return $count == 0 ? 100 : 0;
			}
		}

		// Fallback: check sitewide if no language plugin or post has no language
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_title = %s AND ID != %d AND post_status = 'publish'",
			$analysis['title'], $post->ID
		) );

		return $count == 0 ? 100 : 0;
	}

	private function test_title_length( $post, $analysis ) {
		$seo_title = get_post_meta( $post->ID, '_mwseo_title', true );

		// Build the full rendered page title (as it appears in search results)
		if ( !empty( $seo_title ) ) {
			// SEO Title is set - this is already the full page title
			$title = $seo_title;
		} else {
			// No override - construct the full page title with site name
			$site_name = trim( get_bloginfo( 'name' ) );
			$title = $analysis['title'] . " | " . $site_name;
		}

		// Use display width (CJK chars count as 2) for consistent SERP measurement
		$width = $this->core->get_display_width( $title );

		if ( $width === 0 ) return 'NA'; // Can't check length if title doesn't exist

		// Thresholds in display units (works for all languages)
		$min = $this->get_option( 'title_length_min', 30 );
		$max = $this->get_option( 'title_length_max', 75 );

		return $this->score_length_range( $width, $min, $max );
	}

	private function test_slug_structure( $analysis ) {
		$slug = $analysis['slug'];
		$len = mb_strlen( $slug );
		$words = explode( '-', $slug );
		$word_count = count( $words );

		// CJK override: For overwhelmingly CJK slugs, treat character count as word count
		if ( $this->core->is_mostly_cjk( $slug, 0.9 ) ) {
			// Skip word-count rule for CJK slugs, just check display width
			$width = $this->core->get_display_width( $slug );
			return $width <= 30 ? 100 : 60;
		}

		// Standard English path: 2–6 hyphenated words
		// Full penalty: length > 50 OR words < 2 OR words > 6
		if ( $len > 50 || $word_count < 2 || $word_count > 6 ) {
			// Partial penalty: length 41-50 OR words = 7
			if ( ($len >= 41 && $len <= 50) || $word_count === 7 ) {
				return 60; // Partial -2 pts penalty (40% of max)
			}
			return 0; // Full -5 pts penalty
		}

		// Perfect: length ≤ 40 AND words 2-6
		return 100; // No penalty
	}

	private function test_internal_links( $analysis ) {
		// Check if internal links checking is enabled
		if ( !$this->get_option( 'check_internal_links', true ) ) {
			return 'NA';
		}

		$actual = count( $analysis['links']['internal'] );
		$word_count = $analysis['word_count'];

		// For substantial posts (300+ words), require at least 1 internal link
		if ( $word_count >= 300 ) {
			return $actual >= 1 ? 100 : 30;
		}

		// For shorter posts, internal links are optional but recommended
		return $actual >= 1 ? 100 : 70;
	}

	private function test_external_link_present( $analysis ) {
		// Check if external links checking is enabled
		if ( !$this->get_option( 'check_external_links', false ) ) {
			return 'NA';
		}

		// Simple presence check - has at least one external link
		return count( $analysis['links']['external'] ) > 0 ? 100 : 0;
	}

	private function test_not_orphaned( $post, $analysis ) {
		// Check if orphaned content checking is enabled
		if ( !$this->get_option( 'check_orphaned_content', true ) ) {
			return 'NA';
		}

		// Check if post has incoming internal links from other pages
		$is_orphaned = $this->is_orphaned( $post );
		return $is_orphaned ? 0 : 100; // 0 if orphaned (full -15 pts penalty), 100 if linked
	}

	private function test_featured_image( $post ) {
		// Check if post has a featured image set
		$has_thumbnail = has_post_thumbnail( $post->ID );
		return $has_thumbnail ? 100 : 0;
	}

	private function test_schema_integrity( $post, $analysis ) {
		// Check for JSON-LD schema in content or via filters
		$content = get_post_field( 'post_content', $post->ID );

		// Check for JSON-LD in content
		$has_schema = strpos( $content, '"@type"' ) !== false || strpos( $content, 'schema.org' ) !== false;

		// Check if a plugin is adding schema
		if ( !$has_schema ) {
			$has_schema = apply_filters( 'seo_engine_has_schema', false, $post );
		}

		// No schema found — skip check if our own schema module is disabled
		if ( !$has_schema ) {
			$auto_schema = $this->get_option( 'auto_schema_enabled', true );
			if ( !$auto_schema ) {
				return 'NA';
			}
			return 0;
		}

		// Schema is present, now check for required fields
		// Required fields for Article/BlogPosting: headline, datePublished, author, image
		$required_fields = ['headline', 'datePublished', 'author', 'image'];
		$missing_fields = [];

		// Try to extract JSON-LD and check for required fields
		if ( preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $content, $matches) ) {
			$schema_json = json_decode( $matches[1], true );

			if ( $schema_json && isset( $schema_json['@type'] ) ) {
				// Check if it's an Article or BlogPosting
				$type = $schema_json['@type'];
				if ( $type === 'Article' || $type === 'BlogPosting' || $type === 'NewsArticle' ) {
					foreach ( $required_fields as $field ) {
						if ( !isset( $schema_json[$field] ) || empty( $schema_json[$field] ) ) {
							$missing_fields[] = $field;
						}
					}
				}
			}
		}

		// If we found missing required fields → partial -10 pts penalty (score ≈ 44)
		if ( !empty( $missing_fields ) ) {
			return 44; // Partial penalty
		}

		// Schema present and complete → no penalty
		return 100;
	}

	private function test_content_depth( $post, $analysis ) {
		$word_count = $analysis['word_count'];
		$post_type = $post->post_type;

		// More reasonable thresholds (configurable via filters)
		// Default: post ≥ 400 words; page ≥ 200; product ≥ 150
		$default_thresholds = [
			'post' => 400,
			'page' => 200,
			'product' => 150,
			'default' => 250,
		];

		// Allow filtering thresholds per post type
		$threshold = apply_filters(
			'seo_engine_content_depth_threshold',
			$default_thresholds[$post_type] ?? $default_thresholds['default'],
			$post_type,
			$post
		);

		// Calculate percentage of threshold met
		$percentage = $threshold > 0 ? ($word_count / $threshold) * 100 : 100;

		// Scoring (more lenient):
		// -8 pts (score = 0) if below 50% of threshold (very short)
		// -4 pts (score = 50) if 50-99% of threshold (could be longer)
		// 0 pts (score = 100) if ≥ threshold (meets expectation)

		if ( $percentage < 50 ) {
			return 0; // Full -8 pts penalty - very short
		} elseif ( $percentage < 100 ) {
			return 50; // Partial -4 pts penalty - could be longer
		}

		return 100; // No penalty, meets threshold
	}

	private function test_meta_robots_tag( $post ) {
		// Check for accidental noindex/nofollow directives in meta robots
		$robots = get_post_meta( $post->ID, '_mwseo_robots', true );

		// If no robots meta is set, that's good (defaults to index,follow)
		if ( empty( $robots ) ) {
			return 100;
		}

		// Check for problematic directives
		$has_noindex = strpos( $robots, 'noindex' ) !== false;
		$has_nofollow = strpos( $robots, 'nofollow' ) !== false;

		// Either directive is a problem for SEO
		if ( $has_noindex || $has_nofollow ) {
			return 0; // Full -5 pts penalty
		}

		return 100; // No penalty
	}

	/**
	 * Test whether the post's main content is present in the raw HTML response,
	 * or whether it only appears after JavaScript runs.
	 *
	 * Google's May 2026 AI Optimization Guide is explicit: JavaScript-rendered
	 * content "isn't blocked from crawlers" is acknowledged as more complex and
	 * a real risk — content that only renders client-side may not be indexed
	 * reliably. This check fetches the post's permalink with wp_remote_get and
	 * verifies that a sample of the post body actually appears in the raw HTML.
	 *
	 * Result is cached per-post for 24h via transient to avoid hammering the
	 * server on bulk scans.
	 *
	 * Returns 100 (pass), 0 (fail), or 'NA' (skipped — draft, password-protected,
	 * empty content, fetch failed, etc).
	 */
	private function test_js_rendered_content( $post, $analysis ) {
		// Only check published posts on a public URL.
		if ( $post->post_status !== 'publish' ) {
			return 'NA';
		}
		if ( !empty( $post->post_password ) ) {
			return 'NA';
		}

		$content = $analysis['content'] ?? '';
		// Strip shortcodes BEFORE stripping tags — otherwise shortcode tokens
		// (which expand to different HTML when rendered) would pollute the sample
		// and cause false positives on shortcode-heavy posts.
		$plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $content ) ) ) );
		if ( mb_strlen( $plain, 'UTF-8' ) < 80 ) {
			// Not enough text to build a reliable signature.
			return 'NA';
		}

		$cache_key = 'mwseo_js_render_' . $post->ID . '_' . md5( $post->post_modified . $post->post_content );
		$cached = get_transient( $cache_key );
		if ( $cached === 'pass' ) return 100;
		if ( $cached === 'fail' ) return 0;
		if ( $cached === 'na' ) return 'NA';

		$permalink = get_permalink( $post );
		if ( empty( $permalink ) ) {
			set_transient( $cache_key, 'na', DAY_IN_SECONDS );
			return 'NA';
		}

		$response = wp_remote_get( $permalink, [
			'timeout'   => 8,
			'sslverify' => false,
			'headers'   => [ 'User-Agent' => 'Mozilla/5.0 (compatible; SEOEngineBot/1.0; +https://meowapps.com/seo-engine)' ],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( $cache_key, 'na', HOUR_IN_SECONDS ); // Retry sooner if fetch failed.
			return 'NA';
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			set_transient( $cache_key, 'na', HOUR_IN_SECONDS );
			return 'NA';
		}

		// Strip the rendered HTML to plain text so we compare apples to apples
		// (Gutenberg / shortcodes / theme wrappers all get unwrapped).
		$rendered_plain = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );

		// Sample 3 non-overlapping chunks from the post body and check each is present.
		// A SPA site will have an empty <body> and none of these will appear.
		$sample_len = 40;
		$total = mb_strlen( $plain, 'UTF-8' );
		$samples = [
			mb_substr( $plain, 0, $sample_len, 'UTF-8' ),
			mb_substr( $plain, max( 0, intval( $total / 2 ) - intval( $sample_len / 2 ) ), $sample_len, 'UTF-8' ),
			mb_substr( $plain, max( 0, $total - $sample_len ), $sample_len, 'UTF-8' ),
		];

		$hits = 0;
		foreach ( $samples as $sample ) {
			$sample = trim( $sample );
			if ( $sample === '' ) continue;
			if ( mb_stripos( $rendered_plain, $sample, 0, 'UTF-8' ) !== false ) {
				$hits++;
			}
		}

		// Require at least 2 of 3 samples present — handles minor whitespace/HTML differences.
		$pass = ( $hits >= 2 );
		set_transient( $cache_key, $pass ? 'pass' : 'fail', DAY_IN_SECONDS );
		return $pass ? 100 : 0;
	}

	// ========================================
	// PILLAR CALCULATIONS
	// ========================================

	/**
	 * Calculate overall score using penalty-based system
	 * Start with 100 points, subtract penalties for each failing check
	 */
	private function calculate_score_from_penalties( $tests, $flags ) {
		$penalties = $this->defaults['penalties'];
		$total_penalty = 0;

		foreach ( $penalties as $test_name => $max_penalty ) {
			if ( !isset( $tests[$test_name] ) ) continue;

			$score = $tests[$test_name];

			// Skip NA tests (disabled or not applicable)
			if ( $score === 'NA' ) continue;

			// Calculate penalty based on test score
			// Score of 100 = no penalty (perfect)
			// Score of 0 = full penalty (complete failure)
			// Score of 50 = half penalty (partial pass)
			$penalty = $max_penalty * ( 100 - $score ) / 100;
			$total_penalty += $penalty;
		}

		// Apply quality safeguards - cap score if critical issues exist
		$final_score = 100 - $total_penalty;
		if ( $this->defaults['quality_safeguards_enabled'] && $this->has_critical_issues( $tests, $flags ) ) {
			$final_score = min( 50, $final_score );
		}

		// Clamp to 0-100 range (never show negative scores)
		return max( 0, min( 100, round( $final_score ) ) );
	}

	/**
	 * Calculate detailed penalty breakdown for debugging/display
	 */
	private function calculate_applied_penalties( $tests ) {
		$penalties = $this->defaults['penalties'];
		$applied = [];

		foreach ( $penalties as $test_name => $max_penalty ) {
			if ( !isset( $tests[$test_name] ) ) continue;

			$score = $tests[$test_name];

			// Skip NA tests
			if ( $score === 'NA' ) continue;

			$penalty = $max_penalty * ( 100 - $score ) / 100;

			// Only include if penalty was applied
			if ( $penalty > 0 ) {
				$applied[$test_name] = round( $penalty, 1 );
			}
		}

		return $applied;
	}

	/**
	 * Maps each issue type to a fix tier, used by the Bulk SEO experience.
	 *   ai_tier1 = cheap & safe AI fixes (the v1 bulk set)
	 *   ai_tier2 = heavier / costlier AI fixes (internal links, image generation)
	 *   anything not listed = 'manual' (detected, not auto-fixable in bulk yet)
	 * Keep ai_tier1 + ai_tier2 in sync with the implemented Magic Fix types.
	 */
	public function get_fix_tiers() {
		return [
			'excerpt_exists'        => 'ai_tier1',
			'excerpt_length'        => 'ai_tier1',
			'title_length'          => 'ai_tier1',
			'grammar_typos'         => 'ai_tier1',
			'alt_coverage'          => 'ai_tier1',
			'internal_links'        => 'ai_tier2',
			'featured_image'        => 'ai_tier2',
			'external_link_present' => 'ai_tier2',
		];
	}

	/**
	 * Site-wide aggregation of failing tests across already-analyzed posts.
	 * Shared by the MCP get_issues tool and the /aggregate_issues REST endpoint so
	 * both stay in sync. Reuses the applied penalties already stored per post
	 * (_mwseo_analysis['penalties']) so projected score lift needs no recompute.
	 *
	 * @param array $args { post_type[], status, language, sample_size }
	 * @return array { posts_scanned, posts_with_issues, average_score, sample_size, top_failing_tests[] }
	 */
	public function aggregate_issues( $args = [] ) {
		global $wpdb;

		$post_types  = !empty( $args['post_type'] ) ? (array) $args['post_type']
			: (array) $this->get_option( 'select_post_types', ['post', 'page'] );
		$status      = !empty( $args['status'] ) ? $args['status'] : 'any';
		$lang        = isset( $args['language'] ) ? (string) $args['language'] : '';
		$sample_size = isset( $args['sample_size'] ) ? max( 100, min( 50000, (int) $args['sample_size'] ) ) : 5000;

		$query_args = [
			'post_type'      => $post_types,
			'post_status'    => $status === 'any' ? ['publish', 'future', 'draft', 'pending', 'private'] : (array) $status,
			'posts_per_page' => $sample_size,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			// Only posts that have actually been analyzed.
			'meta_query'     => [ [ 'key' => '_mwseo_analysis', 'compare' => 'EXISTS' ] ],
		];
		if ( $lang !== '' && function_exists( 'pll_get_post_language' ) ) {
			$query_args['lang'] = $lang;
		}

		$ids = get_posts( $query_args );

		$tiers         = $this->get_fix_tiers();
		$max_penalties = $this->defaults['penalties'];
		$test_data     = [];
		$posts_scanned = 0;
		$posts_with_issues = 0;
		$score_sum     = 0;
		$last_analyzed = 0;
		$distribution  = [ 'excellent' => 0, 'great' => 0, 'fine' => 0, 'poor' => 0, 'weak' => 0 ];

		if ( !empty( $ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta}
				 WHERE meta_key = '_mwseo_analysis' AND post_id IN ($placeholders)",
				$ids
			) );

			foreach ( $rows as $row ) {
				$analysis = maybe_unserialize( $row->meta_value );
				if ( !is_array( $analysis ) || !isset( $analysis['tests'] ) ) continue;

				$posts_scanned++;
				if ( isset( $analysis['timestamp'] ) ) $last_analyzed = max( $last_analyzed, (int) $analysis['timestamp'] );
				$overall = isset( $analysis['overall'] ) ? (float) $analysis['overall'] : 0;
				$score_sum += $overall;
				if ( $overall >= 90 )      $distribution['excellent']++;
				else if ( $overall >= 75 ) $distribution['great']++;
				else if ( $overall >= 50 ) $distribution['fine']++;
				else if ( $overall >= 26 ) $distribution['poor']++;
				else                       $distribution['weak']++;
				$applied = ( isset( $analysis['penalties'] ) && is_array( $analysis['penalties'] ) ) ? $analysis['penalties'] : [];
				$had_issue = false;

				foreach ( $analysis['tests'] as $test_name => $score ) {
					if ( $score === 'NA' || !is_numeric( $score ) || $score >= 70 ) continue;
					$had_issue = true;
					$severity = $score < 40 ? 'high' : 'medium';

					if ( !isset( $test_data[ $test_name ] ) ) {
						$test_data[ $test_name ] = [
							'test'                => $test_name,
							'count'               => 0,
							'high'                => 0,
							'medium'              => 0,
							'sample_post_ids'     => [],
							'sum_applied_penalty' => 0.0,
							'tier'                => $tiers[ $test_name ] ?? 'manual',
						];
					}
					$test_data[ $test_name ]['count']++;
					$test_data[ $test_name ][ $severity ]++;
					if ( count( $test_data[ $test_name ]['sample_post_ids'] ) < 5 ) {
						$test_data[ $test_name ]['sample_post_ids'][] = (int) $row->post_id;
					}
					// Reuse stored applied penalty; fall back to the formula for older analyses.
					$pen = isset( $applied[ $test_name ] ) ? (float) $applied[ $test_name ]
						: ( ( $max_penalties[ $test_name ] ?? 0 ) * ( 100 - $score ) / 100 );
					$test_data[ $test_name ]['sum_applied_penalty'] += $pen;
				}

				if ( $had_issue ) $posts_with_issues++;
			}
		}

		foreach ( $test_data as &$t ) {
			$t['sum_applied_penalty'] = round( $t['sum_applied_penalty'], 1 );
			$t['projected_avg_lift']  = $posts_scanned > 0 ? round( $t['sum_applied_penalty'] / $posts_scanned, 1 ) : 0;
			$t['fixable']             = in_array( $t['tier'], ['ai_tier1', 'ai_tier2'], true );
		}
		unset( $t );

		usort( $test_data, function ( $a, $b ) { return $b['count'] - $a['count']; } );

		$total_issues = 0;
		foreach ( $test_data as $t ) { $total_issues += $t['count']; }

		// Total posts of these types (any non-trash status), so the UI can show "analyzed X of Y".
		$posts_total = 0;
		foreach ( $post_types as $pt ) {
			$counts = wp_count_posts( $pt );
			if ( $counts ) {
				$posts_total += (int) $counts->publish + (int) $counts->future + (int) $counts->draft
					+ (int) $counts->pending + (int) $counts->private;
			}
		}

		return [
			'posts_scanned'     => $posts_scanned,
			'posts_total'       => $posts_total,
			'posts_with_issues' => $posts_with_issues,
			'total_issues'      => $total_issues,
			'average_score'     => $posts_scanned > 0 ? round( $score_sum / $posts_scanned, 1 ) : 0,
			'last_analyzed'     => $last_analyzed ?: null,
			'distribution'      => $distribution,
			'sample_size'       => $sample_size,
			'top_failing_tests' => array_values( $test_data ),
		];
	}

	/**
	 * Returns the analyzed posts that are failing a specific test, with each post's
	 * applied penalty (= the per-post score it would recover). Used by the Bulk SEO
	 * "fix this issue everywhere" flow to build its work-list and review deltas.
	 *
	 * @param string $test The test/issue key (e.g. excerpt_length)
	 * @param array  $args { post_type[], status, language, limit }
	 * @return array { test, total, posts[] }
	 */
	public function get_posts_failing_test( $test, $args = [] ) {
		$post_types = !empty( $args['post_type'] ) ? (array) $args['post_type']
			: (array) $this->get_option( 'select_post_types', ['post', 'page'] );
		$status = !empty( $args['status'] ) ? $args['status'] : 'any';
		$lang   = isset( $args['language'] ) ? (string) $args['language'] : '';
		$limit  = isset( $args['limit'] ) ? max( 1, min( 2000, (int) $args['limit'] ) ) : 1000;

		$query_args = [
			'post_type'      => $post_types,
			'post_status'    => $status === 'any' ? ['publish', 'future', 'draft', 'pending', 'private'] : (array) $status,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'meta_query'     => [ [ 'key' => '_mwseo_analysis', 'compare' => 'EXISTS' ] ],
		];
		if ( $lang !== '' && function_exists( 'pll_get_post_language' ) ) {
			$query_args['lang'] = $lang;
		}

		$ids = get_posts( $query_args );
		$posts = [];

		if ( !empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $pid ) {
				$analysis = get_post_meta( $pid, '_mwseo_analysis', true );
				if ( !is_array( $analysis ) || !isset( $analysis['tests'][ $test ] ) ) continue;
				$score = $analysis['tests'][ $test ];
				if ( $score === 'NA' || !is_numeric( $score ) || $score >= 70 ) continue;

				$applied = isset( $analysis['penalties'][ $test ] ) ? (float) $analysis['penalties'][ $test ]
					: ( ( $this->defaults['penalties'][ $test ] ?? 0 ) * ( 100 - $score ) / 100 );
				$p = get_post( $pid );

				// Current value for the field this test targets, so the proposals table can
				// show "before → after" inline (title and meta-description fixes).
				$current = '';
				if ( in_array( $test, ['title_length', 'title_exists', 'title_unique_sitewide'], true ) ) {
					$seo_title = get_post_meta( $pid, '_mwseo_title', true );
					$current = ( $seo_title !== '' && $seo_title !== false ) ? $seo_title : ( $p ? $p->post_title : '' );
				} else if ( in_array( $test, ['excerpt_exists', 'excerpt_length'], true ) ) {
					$seo_ex = get_post_meta( $pid, '_mwseo_excerpt', true );
					$current = ( $seo_ex !== '' && $seo_ex !== false ) ? $seo_ex : ( $p ? $p->post_excerpt : '' );
				}

				$posts[] = [
					'id'         => (int) $pid,
					'title'      => $p ? $p->post_title : ( '#' . $pid ),
					'current'    => $current,
					'score'      => isset( $analysis['overall'] ) ? (int) $analysis['overall'] : null,
					'test_score' => (int) $score,
					'penalty'    => round( $applied, 1 ),
					'edit_url'   => get_edit_post_link( $pid, 'raw' ),
				];
			}
		}

		return [ 'test' => $test, 'total' => count( $posts ), 'posts' => $posts ];
	}

	// ========================================
	// HELPER FUNCTIONS
	// ========================================

	private function calculate_recommended_length( $intent, $current_word_count ) {
		// Define ideal ranges for each intent type
		$ranges = [
			'QuickAnswer' => ['ideal_min' => 200, 'ideal_max' => 400],
			'News' => ['ideal_min' => 300, 'ideal_max' => 500],
			'Guide' => ['ideal_min' => 600, 'ideal_max' => 1200],
			'HowTo' => ['ideal_min' => 600, 'ideal_max' => 1200],
			'Product' => ['ideal_min' => 300, 'ideal_max' => 600],
			'default' => ['ideal_min' => 400, 'ideal_max' => 800]
		];

		$range = $ranges[$intent] ?? $ranges['default'];

		// If content is already in ideal range, return null (no recommendation needed)
		if ( $current_word_count >= $range['ideal_min'] && $current_word_count <= $range['ideal_max'] ) {
			return null;
		}

		// Return the appropriate target
		if ( $current_word_count < $range['ideal_min'] ) {
			return $range['ideal_min']; // Too short, recommend minimum
		} else {
			return $range['ideal_max']; // Too long, recommend maximum
		}
	}

	private function is_short_and_strong_mode( $analysis ) {
		if ( !$this->ai_enabled ) return false;

		$intent = $analysis['ai']['intent'] ?? 'unknown';
		$word_count = $analysis['word_count'];
		$semantic_alignment = $analysis['ai']['semantic_alignment'] ?? 0;

		return (in_array( $intent, ['QuickAnswer', 'News'] ) || $word_count < 400)
			&& $semantic_alignment >= 0.65;
	}

	private function has_critical_issues( $tests, $flags ) {
		$safeguards = $this->defaults['safeguards'];

		// Missing or non-unique title (truly critical for SEO)
		if ( $safeguards['require_title'] && $tests['title_exists'] === 0 ) return true;
		if ( $safeguards['require_unique_title'] && $tests['title_unique_sitewide'] === 0 ) return true;

		// Unintended noindex (blocks search engines)
		if ( $safeguards['prevent_unintended_noindex'] && $flags['noindex'] ) return true;

		// Schema removed - it's important but not critical enough to cap scores at 50%

		// Poor semantic alignment (title doesn't match content)
		if ( $safeguards['min_semantic_alignment'] > 0 ) {
			$alignment = $tests['semantic_alignment'];
			if ( $alignment !== 'NA' && $alignment < ($safeguards['min_semantic_alignment'] * 100) ) {
				return true;
			}
		}

		return false;
	}

	private function detect_flags( $post, $tests ) {
		$flags = [
			'noindex' => $this->is_noindexed( $post ),
			'orphaned' => $this->get_option( 'check_orphaned_content', true ) ? $this->is_orphaned( $post ) : false,
			'missing_schema' => isset( $tests['schema_integrity'] ) && $tests['schema_integrity'] === 0,
			'redirected' => false, // TODO: implement redirect detection
			'canonical_set' => $this->has_canonical( $post ),
		];

		return $flags;
	}

	private function is_noindexed( $post ) {
		// Check if post has noindex meta
		$robots = get_post_meta( $post->ID, '_mwseo_robots', true );
		return strpos( $robots, 'noindex' ) !== false;
	}

	private function is_orphaned( $post ) {
		// Check if post has any incoming internal links
		// Simplified: check if post appears in any other post's content
		global $wpdb;

		$permalink = get_permalink( $post->ID );
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE ID != %d AND post_status = 'publish' AND post_content LIKE %s",
			$post->ID,
			'%' . $wpdb->esc_like( $permalink ) . '%'
		) );

		return $count == 0;
	}

	private function has_canonical( $post ) {
		$canonical = get_post_meta( $post->ID, '_mwseo_canonical', true );
		return !empty( $canonical );
	}

	private function get_option( $key, $default = null ) {
		return isset( $this->options[$key] ) ? $this->options[$key] : $default;
	}

	/**
	 * Get color band for a score
	 */
	public function get_score_band( $score ) {
		if ( $score >= 70 ) return 'good';
		if ( $score >= 40 ) return 'warn';
		return 'bad';
	}

	/**
	 * Get top fixes for a post based on test results
	 */
	public function get_top_fixes( $tests, $analysis ) {
		$fixes = [];

		// Calculate impact for each failed test
		foreach ( $tests as $test_name => $score ) {
			if ( $score === 'NA' ) continue;
			if ( $score >= 70 ) continue; // Only show fixes for low scores

			$impact = 100 - $score;
			$fixes[] = [
				'test' => $test_name,
				'score' => $score,
				'impact' => $impact,
				'action' => $this->get_fix_action( $test_name, $analysis )
			];
		}

		// Sort by impact (highest first)
		usort( $fixes, function( $a, $b ) {
			return $b['impact'] - $a['impact'];
		});

		// Return top 3
		return array_slice( $fixes, 0, 3 );
	}

	private function get_fix_action( $test_name, $analysis ) {
		$actions = [
			'title_length' => 'Shorten title to ~58 chars',
			'excerpt_exists' => 'Add meta description',
			'excerpt_length' => 'Adjust excerpt to 80-160 chars',
			'alt_coverage' => 'Add alt text to ' . count( array_filter( $analysis['images'], function($i) { return !$i['has_alt']; }) ) . ' images',
			'internal_links' => 'Add at least 1 internal link',
			'external_links' => 'Add external links',
			'slug_length' => 'Shorten URL slug',
			'slug_words' => 'Simplify URL slug',
			'content_depth' => 'Expand content',
			'schema_present' => 'Add schema markup',
			'title_exists' => 'Add a title',
			'title_unique_sitewide' => 'Make title unique',
			'featured_image' => 'Set a featured image',
			'grammar_typos' => 'Review and fix grammar/typos',
		];

		return $actions[$test_name] ?? 'Review ' . str_replace( '_', ' ', $test_name );
	}

	/**
	 * Get list of enabled AI steps for progressive analysis
	 * Returns array of step names that should be run
	 */
	public function get_enabled_ai_steps() {
		$steps = [];

		// Optional AI checks based on settings
		if ( $this->core->get_option( 'check_semantic_alignment', false ) ) {
			$steps[] = 'summary'; // Includes intent, entities, semantic alignment
		}
		if ( $this->core->get_option( 'check_grammar_typos', false ) ) {
			$steps[] = 'grammar';
		}
		if ( $this->core->get_option( 'check_authenticity_originality', false ) ) {
			$steps[] = 'authenticity';
		}
		if ( $this->core->get_option( 'check_personality_engagement', false ) ) {
			$steps[] = 'personality';
		}
		if ( $this->core->get_option( 'check_structure_quality', false ) ) {
			$steps[] = 'structure';
		}
		if ( $this->core->get_option( 'check_readability_score', false ) ) {
			$steps[] = 'readability';
		}
		if ( $this->core->get_option( 'check_topic_completeness', false ) ) {
			$steps[] = 'topic';
		}

		return $steps;
	}

	/**
	 * Run a single AI analysis step
	 * @param object $post WordPress post object
	 * @param string $step Step name (summary, grammar, authenticity, etc.)
	 * @return array|false Step result data or false on error
	 */
	public function run_ai_step( $post, $step ) {
		global $mwai;

		if ( !$mwai || !method_exists( $mwai, 'simpleTextQuery' ) ) {
			return false;
		}

		// Get current analysis data
		$analysis = $this->analyze_post( $post );

		try {
			switch ( $step ) {
				case 'summary':
					return $this->run_summary_step( $post, $analysis );

				case 'grammar':
					$result = $this->analyze_grammar( $analysis );
					return [
						'grammar_score' => $result['score'],
						'grammar_feedback' => $result['feedback']
					];

				case 'authenticity':
					$result = $this->analyze_authenticity_originality( $analysis );
					return [
						'authenticity_score' => $result['score'],
						'authenticity_feedback' => $result['feedback']
					];

				case 'personality':
					$result = $this->analyze_personality_engagement( $analysis );
					return [
						'personality_score' => $result['score'],
						'personality_feedback' => $result['feedback']
					];

				case 'structure':
					return [
						'structure_score' => $this->analyze_structure_quality( $analysis )
					];

				case 'readability':
					$result = $this->analyze_readability( $analysis );
					return [
						'readability_score' => $result['score'],
						'readability_feedback' => $result['feedback']
					];

				case 'topic':
					$result = $this->analyze_topic_completeness( $analysis );
					return [
						'topic_completeness' => $result['score'],
						'topic_feedback' => $result['feedback']
					];

				default:
					return false;
			}
		} catch ( Exception $e ) {
			error_log( 'SEO Engine AI Step Error (' . $step . '): ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Run the summary step (includes intent, entities, semantic alignment)
	 */
	private function run_summary_step( $post, $analysis ) {
		global $mwai;

		$summary_prompt = "Analyze this content and provide:\n1. A one-sentence summary (max 90 chars)\n2. Content intent (choose one: QuickAnswer, Guide, HowTo, Product, Local, News, or General)\n3. Key entities/topics (max 5)\n\nTitle: {$analysis['title']}\nContent: " . $this->truncate_at_sentence( $analysis['content'], 1000 ) . "\n\nRespond in JSON format: {\"summary\": \"...\", \"intent\": \"...\", \"entities\": [...], \"confidence\": 0.0-1.0}";

		$response = $mwai->simpleTextQuery( $summary_prompt, [ 'scope' => 'seo' ] );

		// Remove markdown code blocks if present
		$response = preg_replace( '/```json\s*/', '', $response );
		$response = preg_replace( '/```\s*$/', '', $response );
		$response = trim( $response );

		// Try to parse JSON from response
		$summary_result = json_decode( $response, true );

		$result = [
			'summary' => '',
			'intent' => 'General',
			'entities' => [],
			'confidence' => 0.5,
			'semantic_alignment' => 0.0,
			'recommended_length' => []
		];

		if ( $summary_result && is_array( $summary_result ) ) {
			$result['summary'] = $summary_result['summary'] ?? '';
			$result['intent'] = $summary_result['intent'] ?? 'General';
			$result['entities'] = $summary_result['entities'] ?? [];
			$result['confidence'] = floatval( $summary_result['confidence'] ?? 0.5 );
		}

		// Calculate semantic alignment
		$result['semantic_alignment'] = $this->calculate_semantic_alignment( $post, $analysis );

		// Calculate recommended content length based on intent
		$result['recommended_length'] = $this->calculate_recommended_length( $result['intent'], $analysis['word_count'] );

		return $result;
	}

	/**
	 * Merge AI step result into existing analysis data
	 * @param int $post_id Post ID
	 * @param array $step_data Step result from run_ai_step()
	 * @return bool Success
	 */
	public function merge_ai_step( $post_id, $step_data ) {
		$existing_data = get_post_meta( $post_id, '_mwseo_analysis', true );

		if ( !$existing_data || !isset( $existing_data['ai'] ) ) {
			return false;
		}

		// Merge the step data into AI section
		$existing_data['ai'] = array_merge( $existing_data['ai'], $step_data );

		// Update the post meta with merged AI data
		update_post_meta( $post_id, '_mwseo_analysis', $existing_data );

		// Recalculate overall score with new AI data
		// Use 'quick' mode which preserves existing AI data from post meta
		$post = get_post( $post_id );
		if ( $post ) {
			$result = $this->calculate( $post, 'quick' );
			update_post_meta( $post_id, '_mwseo_overall', $result['overall'] );
			update_post_meta( $post_id, '_mwseo_score', $result['overall'] );
			update_post_meta( $post_id, '_mwseo_analysis', $result );
		}

		return true;
	}
}
