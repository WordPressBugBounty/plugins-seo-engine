<?php

/**
 * Content Clarity scorer.
 *
 * The class name "Readability" is kept for backward compatibility — externally
 * this is now "Content Clarity" everywhere user-facing. The score measures
 * AI-extractability and human-skimmability rather than Flesch Reading Ease
 * (which Google de-emphasised and AI bots ignore).
 *
 * Four signals, 25 points each, rolled up into a single 0-100 score:
 *   - chunkability: paragraphs sized to be quoted
 *   - structure:    presence and hierarchy of headings
 *   - lists:        bullet/numbered lists (AI cites these heavily)
 *   - clarity:      sentence-level extractability
 *
 * Forgiving by design: a normal post with H2s and decent paragraphs scores 75+.
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
	 * Score a post's content for clarity and AI-extractability.
	 *
	 * @param string $content_html HTML content (post_content as stored).
	 * @return array { score, breakdown, suggestions }
	 *   - score:       int 0-100
	 *   - breakdown:   per-signal scores 0-25
	 *   - suggestions: concrete, encouraging strings the agent / Magic Wand can act on
	 */
	public function calculate_readability( $content_html ) {
		$content_html = (string) $content_html;

		// Empty content: score 0, one suggestion.
		if ( trim( strip_tags( $content_html ) ) === '' ) {
			return [
				'score' => 0,
				'breakdown' => [ 'chunkability' => 0, 'structure' => 0, 'lists' => 0, 'clarity' => 0 ],
				'suggestions' => [ 'This post has no readable content yet.' ],
			];
		}

		$paragraphs = $this->extract_paragraphs( $content_html );
		$headings   = $this->extract_headings( $content_html );
		$has_list   = $this->has_list( $content_html );
		$plain      = $this->to_plain_text( $content_html );
		$is_cjk     = $this->is_mostly_cjk( $plain );
		$word_count = $this->count_words( $plain, $is_cjk );

		$chunk    = $this->score_chunkability( $paragraphs, $is_cjk );
		$struct   = $this->score_structure( $headings, $word_count );
		$lists    = $this->score_lists( $has_list, $word_count );
		$clarity  = $this->score_clarity( $plain, $is_cjk );

		$total = (int) round( $chunk['score'] + $struct['score'] + $lists['score'] + $clarity['score'] );
		// Clamp defensively.
		$total = max( 0, min( 100, $total ) );

		$suggestions = array_merge(
			$chunk['suggestions'], $struct['suggestions'],
			$lists['suggestions'], $clarity['suggestions']
		);

		// If the post is in great shape, surface one encouraging line instead of an empty list.
		if ( empty( $suggestions ) && $total >= 80 ) {
			$suggestions[] = 'Your content is clean and easy for both humans and AI bots to quote.';
		}

		return [
			'score' => $total,
			'breakdown' => [
				'chunkability' => (int) round( $chunk['score'] ),
				'structure'    => (int) round( $struct['score'] ),
				'lists'        => (int) round( $lists['score'] ),
				'clarity'      => (int) round( $clarity['score'] ),
			],
			'suggestions' => $suggestions,
		];
	}

	#region Signal scorers

	/**
	 * Chunkability: are paragraphs sized to be quoted?
	 * Sweet spot: 50-400 chars (Latin) / 25-200 chars (CJK).
	 * Mega-paragraphs (>800 / >400) hurt the score hard.
	 */
	private function score_chunkability( $paragraphs, $is_cjk ) {
		if ( empty( $paragraphs ) ) {
			return [ 'score' => 0, 'suggestions' => [ 'Wrap your content in paragraphs so it can be skimmed and quoted.' ] ];
		}

		$lo  = $is_cjk ? 25  : 50;
		$hi  = $is_cjk ? 200 : 400;
		$mega = $is_cjk ? 400 : 800;

		$good = 0; $mega_count = 0; $mega_sample = null;
		foreach ( $paragraphs as $p ) {
			$len = $is_cjk ? mb_strlen( $p, 'UTF-8' ) : strlen( $p );
			if ( $len >= $lo && $len <= $hi ) {
				$good++;
			} else if ( $len > $mega ) {
				$mega_count++;
				if ( $mega_sample === null ) {
					$mega_sample = mb_substr( $p, 0, 60, 'UTF-8' );
				}
			}
		}

		$ratio = $good / count( $paragraphs );
		// 25 points scaled by good-paragraph ratio, minus mega penalty
		$score = 25 * $ratio - min( 10, $mega_count * 4 );
		$score = max( 0, min( 25, $score ) );

		$suggestions = [];
		if ( $mega_count > 0 ) {
			$suggestions[] = sprintf(
				$mega_count === 1
					? 'One very long paragraph ("%s…"). Splitting where the topic changes makes it 10× more quotable.'
					: '%d very long paragraphs (the first starts "%s…"). Splitting them at topic changes makes the post 10× more quotable.',
				$mega_count === 1 ? $mega_sample : $mega_count,
				$mega_count === 1 ? null : $mega_sample
			);
			// Re-format if it was the 2+ case (sprintf above isn't quite right with conditional args)
			if ( $mega_count > 1 ) {
				$suggestions[ count($suggestions) - 1 ] = sprintf(
					'%d very long paragraphs (the first starts "%s…"). Splitting them at topic changes makes the post 10× more quotable.',
					$mega_count, $mega_sample
				);
			}
		}
		return [ 'score' => $score, 'suggestions' => $suggestions ];
	}

	/**
	 * Structure: does the post have headings, and are they organized?
	 * - Any H2? base +12.
	 * - Multiple H2s on posts >600 words? +6.
	 * - Heading density ~ one per 300 words? +7.
	 */
	private function score_structure( $headings, $word_count ) {
		$h2s = $headings['h2'];
		$all = $headings['count_all'];

		// Very short posts (< 200 words) don't need headings — give them a free pass.
		if ( $word_count > 0 && $word_count < 200 ) {
			return [ 'score' => 25, 'suggestions' => [] ];
		}

		$score = 0;
		$suggestions = [];

		if ( $h2s >= 1 ) {
			$score += 12;
			if ( $word_count > 600 && $h2s >= 2 ) {
				$score += 6;
			} else if ( $word_count > 600 && $h2s === 1 ) {
				$suggestions[] = 'Your post is long enough to benefit from a second H2 section.';
			}

			// Density: roughly one heading per 300 words is great.
			if ( $all > 0 && $word_count > 0 ) {
				$density = $word_count / $all;
				if ( $density >= 150 && $density <= 450 ) {
					$score += 7;
				} else if ( $density > 450 ) {
					$suggestions[] = 'Adding one or two more headings would make this easier to skim.';
				}
			}
		} else {
			$suggestions[] = 'Add at least one H2 section heading. AI bots and readers both rely on them to navigate.';
		}

		return [ 'score' => min( 25, $score ), 'suggestions' => $suggestions ];
	}

	/**
	 * Lists: AI citation rates spike when key facts are in bullet or numbered lists.
	 * Short posts get a free pass — not everything needs a list.
	 */
	private function score_lists( $has_list, $word_count ) {
		// Posts under 300 words: lists optional.
		if ( $word_count > 0 && $word_count < 300 ) {
			return [ 'score' => 25, 'suggestions' => [] ];
		}
		if ( $has_list ) {
			return [ 'score' => 25, 'suggestions' => [] ];
		}
		return [
			'score' => 12,
			'suggestions' => [ 'A short bulleted summary of your key points would make this post much more citable by AI bots.' ],
		];
	}

	/**
	 * Clarity: sentences extractable / quotable?
	 * - Latin: avg 14-22 words = ideal; 10-30 = fine.
	 * - CJK: avg 25-60 chars = ideal.
	 * - Bonus for share of "short, quotable" sentences (< 28 words).
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
			if ( $avg >= 25 && $avg <= 60 ) return [ 'score' => 25, 'suggestions' => [] ];
			if ( $avg > 60 ) {
				return [ 'score' => 14, 'suggestions' => [ 'Sentences are long. Try shorter, punchier statements where possible.' ] ];
			}
			return [ 'score' => 20, 'suggestions' => [] ];
		}

		$word_counts = array_map( function( $s ) { return str_word_count( $s ); }, $sentences );
		$avg = array_sum( $word_counts ) / count( $word_counts );
		$quotable_ratio = count( array_filter( $word_counts, function( $n ) { return $n > 0 && $n < 28; } ) ) / count( $word_counts );

		$score = 0;
		$suggestions = [];

		if ( $avg >= 14 && $avg <= 22 ) {
			$score = 20;
		} else if ( $avg >= 10 && $avg <= 28 ) {
			$score = 16;
		} else if ( $avg > 28 ) {
			$score = 8;
			$suggestions[] = sprintf(
				'Sentences average %d words. Splitting where you use "and" or commas makes them more quotable.',
				(int) round( $avg )
			);
		} else {
			$score = 12;
		}

		// Quotable bonus: up to +5
		$score += round( $quotable_ratio * 5 );

		return [ 'score' => min( 25, $score ), 'suggestions' => $suggestions ];
	}

	#endregion

	#region HTML / text helpers

	private function extract_paragraphs( $html ) {
		// Match <p>...</p> blocks.
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $m );
		$ps = array_map( function( $s ) { return trim( strip_tags( $s ) ); }, $m[1] ?? [] );
		$ps = array_values( array_filter( $ps, function( $s ) { return $s !== ''; } ) );

		// Fallback: if no <p> tags at all (Gutenberg block content may not wrap), split by blank lines.
		if ( empty( $ps ) ) {
			$plain = trim( preg_replace( '/\s+/', ' ', strip_tags( $html ) ) );
			$ps = array_values( array_filter( array_map( 'trim', preg_split( '/\n\s*\n+/', $plain ) ) ) );
		}
		return $ps;
	}

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
