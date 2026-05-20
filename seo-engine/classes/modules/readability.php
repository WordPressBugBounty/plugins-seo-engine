<?php

/**
 * Content Clarity scorer.
 *
 * The class name "Readability" is kept for backward compatibility — externally
 * this is now "Content Clarity" everywhere user-facing. The score measures
 * human-skimmability rather than Flesch Reading Ease (which Google
 * de-emphasised). Per Google's May 2026 AI optimization guide, there is
 * explicitly no need to chunk content for AI — so we measure structure for
 * human readers, not for AI parsers.
 *
 * Three signals rolled up into a single 0-100 score:
 *   - structure: 33 pts — presence and hierarchy of headings
 *   - lists:     33 pts — bullet/numbered lists (genuinely help skim-reading)
 *   - clarity:   34 pts — sentence-level readability
 *
 * Forgiving by design: a normal post with H2s and decent sentences scores 75+.
 * Low scores are reserved for genuinely problematic content.
 */
class Meow_MWSEO_Modules_Readability
{
	/**
	 * Detect if text is mostly CJK (Chinese, Japanese, Korean).
	 * CJK content needs different thresholds — each character carries more meaning
	 * than a Latin character, so paragraphs and sentences are naturally shorter.
	 */
	public function is_mostly_cjk( $text, $threshold = 0.5 ) {
		if ( empty( $text ) ) return false;
		$len = mb_strlen( $text, 'UTF-8' );
		if ( $len === 0 ) return false;
		preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text, $matches );
		return ( count( $matches[0] ) / $len ) >= $threshold;
	}

	/**
	 * Score a post's content for clarity and human-skimmability.
	 *
	 * @param string $content_html HTML content (post_content as stored).
	 * @return array { score, breakdown, suggestions }
	 *   - score:       int 0-100
	 *   - breakdown:   per-signal scores (structure 0-33, lists 0-33, clarity 0-34)
	 *   - suggestions: concrete, encouraging strings the agent / Magic Wand can act on
	 */
	public function calculate_readability( $content_html ) {
		$content_html = (string) $content_html;

		// Empty content: score 0, one suggestion.
		if ( trim( strip_tags( $content_html ) ) === '' ) {
			return [
				'score' => 0,
				'breakdown' => [ 'structure' => 0, 'lists' => 0, 'clarity' => 0 ],
				'suggestions' => [ 'This post has no readable content yet.' ],
			];
		}

		$headings   = $this->extract_headings( $content_html );
		$has_list   = $this->has_list( $content_html );
		$plain      = $this->to_plain_text( $content_html );
		$is_cjk     = $this->is_mostly_cjk( $plain );
		$word_count = $this->count_words( $plain, $is_cjk );

		$struct   = $this->score_structure( $headings, $word_count );
		$lists    = $this->score_lists( $has_list, $word_count );
		$clarity  = $this->score_clarity( $plain, $is_cjk );

		$total = (int) round( $struct['score'] + $lists['score'] + $clarity['score'] );
		// Clamp defensively.
		$total = max( 0, min( 100, $total ) );

		$suggestions = array_merge(
			$struct['suggestions'], $lists['suggestions'], $clarity['suggestions']
		);

		// If the post is in great shape, surface one encouraging line instead of an empty list.
		if ( empty( $suggestions ) && $total >= 80 ) {
			$suggestions[] = 'Your content is clear, well-structured, and easy to read.';
		}

		return [
			'score' => $total,
			'breakdown' => [
				'structure' => (int) round( $struct['score'] ),
				'lists'     => (int) round( $lists['score'] ),
				'clarity'   => (int) round( $clarity['score'] ),
			],
			'suggestions' => $suggestions,
		];
	}

	#region Signal scorers

	/**
	 * Structure: does the post have headings, and are they organized?
	 * - Any H2? base +16.
	 * - Multiple H2s on posts >600 words? +8.
	 * - Heading density ~ one per 300 words? +9.
	 */
	private function score_structure( $headings, $word_count ) {
		$h2s = $headings['h2'];
		$all = $headings['count_all'];

		// Very short posts (< 200 words) don't need headings — give them a free pass.
		if ( $word_count > 0 && $word_count < 200 ) {
			return [ 'score' => 33, 'suggestions' => [] ];
		}

		$score = 0;
		$suggestions = [];

		if ( $h2s >= 1 ) {
			$score += 16;
			if ( $word_count > 600 && $h2s >= 2 ) {
				$score += 8;
			} else if ( $word_count > 600 && $h2s === 1 ) {
				$suggestions[] = 'Your post is long enough to benefit from a second H2 section.';
			}

			// Density: roughly one heading per 300 words is great.
			if ( $all > 0 && $word_count > 0 ) {
				$density = $word_count / $all;
				if ( $density >= 150 && $density <= 450 ) {
					$score += 9;
				} else if ( $density > 450 ) {
					$suggestions[] = 'Adding one or two more headings would make this easier to skim.';
				}
			}
		} else {
			$suggestions[] = 'Add at least one H2 section heading. Readers rely on them to navigate long content.';
		}

		return [ 'score' => min( 33, $score ), 'suggestions' => $suggestions ];
	}

	/**
	 * Lists: bullet or numbered lists genuinely help skim-reading.
	 * Short posts get a free pass — not everything needs a list.
	 */
	private function score_lists( $has_list, $word_count ) {
		// Posts under 300 words: lists optional.
		if ( $word_count > 0 && $word_count < 300 ) {
			return [ 'score' => 33, 'suggestions' => [] ];
		}
		if ( $has_list ) {
			return [ 'score' => 33, 'suggestions' => [] ];
		}
		return [
			'score' => 16,
			'suggestions' => [ 'A short bulleted summary of your key points would make this post much easier to skim.' ],
		];
	}

	/**
	 * Clarity: how readable are the sentences?
	 * - Latin: avg 14-22 words = ideal; 10-30 = fine.
	 * - CJK: avg 25-60 chars = ideal.
	 * - Bonus for share of short, readable sentences (< 28 words).
	 */
	private function score_clarity( $plain, $is_cjk ) {
		$sentences = preg_split( '/(?<=[.!?。！？])\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_filter( array_map( 'trim', $sentences ) );
		if ( empty( $sentences ) ) {
			return [ 'score' => 0, 'suggestions' => [] ];
		}

		if ( $is_cjk ) {
			$lens = array_map( function( $s ) { return mb_strlen( $s, 'UTF-8' ); }, $sentences );
			$avg = array_sum( $lens ) / count( $lens );
			if ( $avg >= 25 && $avg <= 60 ) return [ 'score' => 34, 'suggestions' => [] ];
			if ( $avg > 60 ) {
				return [ 'score' => 18, 'suggestions' => [ 'Sentences are long. Try shorter, punchier statements where possible.' ] ];
			}
			return [ 'score' => 26, 'suggestions' => [] ];
		}

		$word_counts = array_map( function( $s ) { return str_word_count( $s ); }, $sentences );
		$avg = array_sum( $word_counts ) / count( $word_counts );
		$readable_ratio = count( array_filter( $word_counts, function( $n ) { return $n > 0 && $n < 28; } ) ) / count( $word_counts );

		$score = 0;
		$suggestions = [];

		if ( $avg >= 14 && $avg <= 22 ) {
			$score = 27;
		} else if ( $avg >= 10 && $avg <= 28 ) {
			$score = 22;
		} else if ( $avg > 28 ) {
			$score = 11;
			$suggestions[] = sprintf(
				'Sentences average %d words. Splitting where you use "and" or commas makes them easier to read.',
				(int) round( $avg )
			);
		} else {
			$score = 16;
		}

		// Readability bonus: up to +7
		$score += round( $readable_ratio * 7 );

		return [ 'score' => min( 34, $score ), 'suggestions' => $suggestions ];
	}

	#endregion

	#region HTML / text helpers

	private function extract_headings( $html ) {
		preg_match_all( '/<(h[1-6])\b[^>]*>(.*?)<\/\1>/is', $html, $m );
		$h2 = 0; $count_all = 0;
		foreach ( $m[1] ?? [] as $i => $tag ) {
			$tag = strtolower( $tag );
			$text = trim( strip_tags( $m[2][ $i ] ?? '' ) );
			if ( $text === '' ) continue;
			$count_all++;
			if ( $tag === 'h2' ) $h2++;
		}
		return [ 'h2' => $h2, 'count_all' => $count_all ];
	}

	private function has_list( $html ) {
		return (bool) preg_match( '/<(ul|ol)\b[^>]*>.*?<li\b[^>]*>.+?<\/li>.*?<\/\1>/is', $html );
	}

	private function to_plain_text( $html ) {
		$s = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		$s = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $s );
		$s = strip_tags( $s );
		$s = preg_replace( '/\[.*?\]/', '', $s ); // strip shortcodes
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	private function count_words( $plain, $is_cjk ) {
		if ( $is_cjk ) {
			// Each CJK character ≈ one "word-unit". Generous rounding.
			return (int) max( 1, floor( mb_strlen( $plain, 'UTF-8' ) * 0.6 ) );
		}
		return str_word_count( $plain );
	}

	#endregion
}
