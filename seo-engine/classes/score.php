<?php

class Meow_MWSEO_Score {
	private $core;
	private $options;
	private $readability;
	
	// Score factor options
	private $check_title_exists;
	private $check_title_unique;
	private $check_title_length;
	private $check_excerpt_exists;
	private $check_excerpt_length;
	private $check_slug_length;
	private $check_slug_words;
	private $check_content_length;
	private $check_images_alt;
	private $check_links;
	private $check_readability;
	
	// Thresholds
	private $min_content_words = 300;
	private $max_slug_length = 64;
	private $max_slug_words = 6;
	private $title_min_length = 10;
	private $title_max_length = 70;
	private $title_absolute_max = 80;
	private $excerpt_min_length = 10;
	private $excerpt_max_length = 160;
	private $excerpt_absolute_max = 180;
	private $readability_threshold = 50;

	public function __construct( $core ) {
		$this->core = $core;
		$this->options = $core->get_all_options();
		
		// Initialize score factors from options
		$this->check_title_exists = $this->get_option( 'score_check_title_exists', true );
		$this->check_title_unique = $this->get_option( 'score_check_title_unique', true );
		$this->check_title_length = $this->get_option( 'score_check_title_length', true );
		$this->check_excerpt_exists = $this->get_option( 'score_check_excerpt_exists', true );
		$this->check_excerpt_length = $this->get_option( 'score_check_excerpt_length', true );
		$this->check_slug_length = $this->get_option( 'score_check_slug_length', true );
		$this->check_slug_words = $this->get_option( 'score_check_slug_words', true );
		$this->check_content_length = $this->get_option( 'score_check_content_length', true );
		$this->check_images_alt = $this->get_option( 'score_check_images_alt', true );
		$this->check_links = $this->get_option( 'score_check_links', true );
		$this->check_readability = $this->get_option( 'score_check_readability', true );
		
		// Initialize readability module if needed
		if ( $this->check_readability ) {
			global $mwseo_readability;
			$this->readability = $mwseo_readability;
		}
		
		// Get configurable thresholds
		$this->readability_threshold = $this->get_option( 'readability_treshold', 50 );
	}
	
	private function get_option( $key, $default = null ) {
		return isset( $this->options[$key] ) ? $this->options[$key] : $default;
	}
	
	/**
	 * Main function to calculate SEO score
	 */
	public function calculate( $post ) {
		$errors = [ 'messages' => [] ];
		$number_of_tests = 0;
		
		// Run each check if enabled
		if ( $this->check_title_exists ) {
			$number_of_tests++;
			$this->check_title_existence( $post, $errors );
		}
		
		if ( $this->check_title_unique ) {
			$number_of_tests++;
			$this->check_title_uniqueness( $post, $errors );
		}
		
		if ( $this->check_title_length ) {
			$number_of_tests++;
			$this->check_seo_title_length( $post, $errors );
		}
		
		if ( $this->check_excerpt_exists ) {
			$number_of_tests++;
			$this->check_excerpt_existence( $post, $errors );
		}
		
		if ( $this->check_excerpt_length ) {
			$number_of_tests++;
			$this->check_seo_excerpt_length( $post, $errors );
		}
		
		if ( $this->check_slug_length ) {
			$number_of_tests++;
			$this->check_slug_length_limit( $post, $errors );
		}
		
		if ( $this->check_slug_words ) {
			$number_of_tests++;
			$this->check_slug_word_count( $post, $errors );
		}
		
		if ( $this->check_content_length ) {
			$number_of_tests++;
			$this->check_content_word_count( $post, $errors );
		}
		
		if ( $this->check_images_alt ) {
			$number_of_tests++;
			$this->check_images_alt_text( $post, $errors );
		}
		
		if ( $this->check_links ) {
			$number_of_tests++;
			$this->check_post_links( $post, $errors );
		}
		
		if ( $this->check_readability ) {
			$number_of_tests++;
			$this->check_readability_score( $post, $errors );
		}
		
		// Calculate final score
		if ( $number_of_tests == 0 ) {
			return [
				'score' => 0,
				'errors' => $errors,
				'status' => 'skip',
				'message' => __( 'No SEO checks enabled.', 'seo-engine' )
			];
		}
		
		$score = round( ( ( $number_of_tests - count( $errors['messages'] ) ) / $number_of_tests ) * 100 );
		$status = count( $errors['messages'] ) === 0 ? 'ok' : 'error';
		
		// Get score category
		$category = $this->get_seo_category( $score );
		$emoji = $this->get_score_emoji( $score );
		$message = sprintf( __( '%s %s (%d%%)', 'seo-engine' ), $emoji, $category, $score );
		
		if ( count( $errors['messages'] ) > 0 ) {
			$message .= "\n\n" . implode( "\n", $errors['messages'] );
		}
		
		return [
			'score' => $score,
			'errors' => $errors,
			'status' => $status,
			'message' => $message,
			'category' => $category,
			'total_tests' => $number_of_tests,
			'failed_tests' => count( $errors['messages'] )
		];
	}
	
	/**
	 * Check if post has a title
	 */
	private function check_title_existence( $post, &$errors ) {
		if ( empty( $post->post_title ) ) {
			$errors['messages'][] = __( 'Title is missing', 'seo-engine' );
			$errors['codes'][] = 'title_missing';
		}
	}
	
	/**
	 * Check if title is unique
	 */
	private function check_title_uniqueness( $post, &$errors ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_title = %s AND ID != %d AND post_status = 'publish'",
			$post->post_title, $post->ID
		) );
		
		if ( $count > 0 ) {
			$errors['messages'][] = __( 'Title is not unique', 'seo-engine' );
			$errors['codes'][] = 'title_not_unique';
		}
	}
	
	/**
	 * Check SEO title length
	 */
	private function check_seo_title_length( $post, &$errors ) {
		$seo_title = get_post_meta( $post->ID, '_seo_title', true );
		$title = !empty( $seo_title ) ? $seo_title : $post->post_title;
		$length = strlen( $title );
		
		if ( $length < $this->title_min_length || $length > $this->title_max_length ) {
			$errors['messages'][] = sprintf( 
				__( 'Title length (%d) is not optimal (should be %d-%d)', 'seo-engine' ),
				$length, $this->title_min_length, $this->title_max_length
			);
			$errors['codes'][] = 'title_seo_length';
		} elseif ( $length > $this->title_absolute_max ) {
			$errors['messages'][] = sprintf(
				__( 'Title is too long (%d chars, max %d)', 'seo-engine' ),
				$length, $this->title_absolute_max
			);
			$errors['codes'][] = 'title_seo_length';
		}
	}
	
	/**
	 * Check if post has an excerpt
	 */
	private function check_excerpt_existence( $post, &$errors ) {
		$seo_excerpt = get_post_meta( $post->ID, '_seo_excerpt', true );
		if ( empty( $post->post_excerpt ) && empty( $seo_excerpt ) ) {
			$errors['messages'][] = __( 'Excerpt is missing', 'seo-engine' );
			$errors['codes'][] = 'excerpt_missing';
		}
	}
	
	/**
	 * Check SEO excerpt length
	 */
	private function check_seo_excerpt_length( $post, &$errors ) {
		$seo_excerpt = get_post_meta( $post->ID, '_seo_excerpt', true );
		$excerpt = !empty( $seo_excerpt ) ? $seo_excerpt : $post->post_excerpt;
		
		if ( !empty( $excerpt ) ) {
			$length = strlen( $excerpt );
			if ( $length < $this->excerpt_min_length || $length > $this->excerpt_max_length ) {
				$errors['messages'][] = sprintf(
					__( 'Excerpt length (%d) is not optimal (should be %d-%d)', 'seo-engine' ),
					$length, $this->excerpt_min_length, $this->excerpt_max_length
				);
				$errors['codes'][] = 'excerpt_seo_length';
			} elseif ( $length > $this->excerpt_absolute_max ) {
				$errors['messages'][] = sprintf(
					__( 'Excerpt is too long (%d chars, max %d)', 'seo-engine' ),
					$length, $this->excerpt_absolute_max
				);
				$errors['codes'][] = 'excerpt_seo_length';
			}
		}
	}
	
	/**
	 * Check slug length
	 */
	private function check_slug_length_limit( $post, &$errors ) {
		if ( strlen( $post->post_name ) > $this->max_slug_length ) {
			$errors['messages'][] = sprintf(
				__( 'Slug is too long (%d chars, max %d)', 'seo-engine' ),
				strlen( $post->post_name ), $this->max_slug_length
			);
			$errors['codes'][] = 'slug_length';
		}
	}
	
	/**
	 * Check slug word count
	 */
	private function check_slug_word_count( $post, &$errors ) {
		$word_count = count( explode( '-', $post->post_name ) );
		if ( $word_count > $this->max_slug_words ) {
			$errors['messages'][] = sprintf(
				__( 'Slug has too many words (%d, max %d)', 'seo-engine' ),
				$word_count, $this->max_slug_words
			);
			$errors['codes'][] = 'slug_words';
		}
	}
	
	/**
	 * Check content word count
	 */
	private function check_content_word_count( $post, &$errors ) {

		$check_live_content = $this->core->get_option( 'check_live_content', false );
		$content = $check_live_content ? $this->core->get_live_content( $post ) : wp_strip_all_tags( $post->post_content );

		$word_count = str_word_count( $content );
		
		if ( $word_count < $this->min_content_words ) {
			$errors['messages'][] = sprintf(
				__( 'Content is too short (%d words, min %d)', 'seo-engine' ),
				$word_count, $this->min_content_words
			);
			$errors['codes'][] = 'post_too_short';
		}
	}
	
	/**
	 * Check images alt text
	 */
	private function check_images_alt_text( $post, &$errors ) {
		$check_live_content = $this->core->get_option( 'check_live_content', false );
		$content = $check_live_content ? $this->core->get_live_content( $post, false ) : $post->post_content;

		$images = [];
		preg_match_all( '/<img[^>]+>/i', $content, $images );

		if ( !empty( $images[0] ) ) {
			$missing_alt = 0;
			foreach ( $images[0] as $image ) {
				if ( !preg_match( '/alt=[\'"]([^\'"]*)[\'"]/', $image ) ) {
					$missing_alt++;
				}
			}
			
			if ( $missing_alt > 0 ) {
				$errors['messages'][] = sprintf(
					__( '%d image(s) missing alt text', 'seo-engine' ),
					$missing_alt
				);
				$errors['codes'][] = 'images_missing_alt_text';
			}
		}
	}
	
	/**
	 * Check for internal and external links
	 */
	private function check_post_links( $post, &$errors ) {

		$check_live_content = $this->core->get_option( 'check_live_content', false );
		$content = $check_live_content ? $this->core->get_live_content( $post, false ) :  $post->post_content;

	
		$has_internal = false;
		$has_external = false;
		
		// Check for any links
		if ( preg_match_all( '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches ) ) {
			$site_url = get_site_url();
			foreach ( $matches[1] as $link ) {
				if ( strpos( $link, $site_url ) !== false || strpos( $link, '/' ) === 0 ) {
					$has_internal = true;
				} elseif ( strpos( $link, 'http' ) === 0 ) {
					$has_external = true;
				}
			}
		}
		
		if ( !$has_internal || !$has_external ) {
			$missing = [];
			if ( !$has_internal ) $missing[] = __( 'internal', 'seo-engine' );
			if ( !$has_external ) $missing[] = __( 'external', 'seo-engine' );
			
			$errors['messages'][] = sprintf(
				__( 'Missing %s links', 'seo-engine' ),
				implode( ' and ', $missing )
			);

			$errors['codes'][] = 'links_missing';
			if( !$has_internal ) {
				$errors['codes'][] = 'links_missing_internal';
			}

			if( !$has_external ) {
				$errors['codes'][] = 'links_missing_external';
			}
		}
	}
	
	/**
	 * Check readability score
	 */
	private function check_readability_score( $post, &$errors ) {
		if ( !$this->readability ) {
			return;
		}
		
		$check_live_content = $this->core->get_option( 'check_live_content', false );
		$content = $check_live_content ? $this->core->get_live_content( $post ) : wp_strip_all_tags( $post->post_content );

		$readability_data = $this->readability->calculate_readability( $content );
		$score = $readability_data['flesch_kincaid'] ?? false;
		
		if ( $score !== false && $score < $this->readability_threshold ) {
			$errors['messages'][] = sprintf(
				__( 'Readability score too low (%d%%, min %d%%)', 'seo-engine' ),
				$score, $this->readability_threshold
			);
			$errors['codes'][] = 'readability_score';
		}
	}
	
	/**
	 * Get SEO category based on score
	 */
	public function get_seo_category( $score ) {
		if ( $score >= 81 ) {
			return __( 'Excellent SEO', 'seo-engine' );
		} elseif ( $score >= 61 ) {
			return __( 'Great SEO', 'seo-engine' );
		} elseif ( $score >= 41 ) {
			return __( 'Good SEO', 'seo-engine' );
		} elseif ( $score >= 21 ) {
			return __( 'Normal SEO', 'seo-engine' );
		} else {
			return __( 'Poor SEO', 'seo-engine' );
		}
	}
	
	/**
	 * Get emoji based on score
	 */
	public function get_score_emoji( $score ) {
		if ( $score >= 81 ) {
			return '😻';
		} elseif ( $score >= 61 ) {
			return '😺';
		} elseif ( $score >= 41 ) {
			return '😸';
		} elseif ( $score >= 21 ) {
			return '🐱';
		} else {
			return '😿';
		}
	}
	
	/**
	 * Get all score factors with descriptions
	 */
	public function get_score_factors() {
		return [
			'score_check_title_exists' => [
				'label' => __( 'Title Existence', 'seo-engine' ),
				'description' => __( 'Check if the post has a title', 'seo-engine' ),
				'enabled' => $this->check_title_exists
			],
			'score_check_title_unique' => [
				'label' => __( 'Title Uniqueness', 'seo-engine' ),
				'description' => __( 'Ensure the title is unique across the site', 'seo-engine' ),
				'enabled' => $this->check_title_unique
			],
			'score_check_title_length' => [
				'label' => __( 'Title Length', 'seo-engine' ),
				'description' => sprintf( __( 'Check if title is between %d-%d characters', 'seo-engine' ), $this->title_min_length, $this->title_max_length ),
				'enabled' => $this->check_title_length
			],
			'score_check_excerpt_exists' => [
				'label' => __( 'Excerpt Existence', 'seo-engine' ),
				'description' => __( 'Check if the post has an excerpt', 'seo-engine' ),
				'enabled' => $this->check_excerpt_exists
			],
			'score_check_excerpt_length' => [
				'label' => __( 'Excerpt Length', 'seo-engine' ),
				'description' => sprintf( __( 'Check if excerpt is between %d-%d characters', 'seo-engine' ), $this->excerpt_min_length, $this->excerpt_max_length ),
				'enabled' => $this->check_excerpt_length
			],
			'score_check_slug_length' => [
				'label' => __( 'Slug Length', 'seo-engine' ),
				'description' => sprintf( __( 'Ensure slug is less than %d characters', 'seo-engine' ), $this->max_slug_length ),
				'enabled' => $this->check_slug_length
			],
			'score_check_slug_words' => [
				'label' => __( 'Slug Word Count', 'seo-engine' ),
				'description' => sprintf( __( 'Check if slug has less than %d words', 'seo-engine' ), $this->max_slug_words ),
				'enabled' => $this->check_slug_words
			],
			'score_check_content_length' => [
				'label' => __( 'Content Length', 'seo-engine' ),
				'description' => sprintf( __( 'Ensure content has at least %d words', 'seo-engine' ), $this->min_content_words ),
				'enabled' => $this->check_content_length
			],
			'score_check_images_alt' => [
				'label' => __( 'Image Alt Text', 'seo-engine' ),
				'description' => __( 'Check if all images have alt text', 'seo-engine' ),
				'enabled' => $this->check_images_alt
			],
			'score_check_links' => [
				'label' => __( 'Internal & External Links', 'seo-engine' ),
				'description' => __( 'Check for both internal and external links', 'seo-engine' ),
				'enabled' => $this->check_links
			],
			'score_check_readability' => [
				'label' => __( 'Readability Score', 'seo-engine' ),
				'description' => sprintf( __( 'Check if readability score is above %d%%', 'seo-engine' ), $this->readability_threshold ),
				'enabled' => $this->check_readability
			]
		];
	}
}