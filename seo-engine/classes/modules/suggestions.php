<?php

class Meow_MWSEO_Modules_Suggestions
{
    public static function prompt($post, $field, $customContent = null, $max_tokens = 500, $meta_key_seo_title = '_kiss_seo_title', $meta_key_seo_excerpt = '_kiss_seo_excerpt' ){
		$core = new Meow_MWSEO_Core();
		global $mwai;

		if (is_null( $mwai ) || !isset( $mwai ) ) {
			$core->log( "⚠️ Missing AI Engine." );
			return false;
		}

		$language    = $core->get_option( 'language', 'English' );
		$ai_keywords = $core->get_option( 'ai_keywords', false );

		$originalContent = $customContent?? Meow_MWSEO_Modules_Suggestions::get_post_sample_context( $post );
		$prompt = "";
		$max_tokens = $max_tokens;
		$expected_size = 0;

		

		switch ($field){
			//EDIT FIELDS
			case 'title_not_unique':
			case 'title_missing':
			case 'title':
				$prompt = sprintf( "Given the original title: \"%s\" and a sample from the post content : \"%s\", formulate a new, captivating, SEO-optimized title.", $post->post_title, $originalContent );
				$expected_size = 80;
				break;
			case 'excerpt_missing':
			case 'excerpt':
				$prompt = sprintf( "Given the original excerpt: \"%s\" and a sample from the post content : \"%s\", write a concise (2 sentences), engaging and SEO-friendly excerpt.", $post->post_excerpt, $originalContent );
				$expected_size = 200;
				break;
			case 'title_seo_length':
			case 'seo_title':
				$prompt = sprintf( "For the website \"%s\". Given the original SEO title: \"%s\" and a sample from the post content : \"%s\", create a new, SEO-optimized title that will attract more clicks. The format should be \"<short_title_keywords> | <site name>\". Ideally between 40 and 50 characters. It should be absolutely CONCISE and LESS than 70 characters.", get_bloginfo( 'name' ), get_post_meta( $post->ID, $meta_key_seo_title, true ), $originalContent );
				$expected_size = 60;
				break;
			case 'excerpt_seo_length':
			case 'seo_excerpt':
				$prompt = sprintf( "For the website \"%s\". Given the original SEO excerpt: \"%s\" and a sample from the post content : \"%s\", write a new, SEO-friendly excerpt that will entice readers to click. The most important keywords should be at the beginning of the excerpt. Ideally between 80 and 100 characters. It should be absolutely CONCISE and LESS than 120 characters.", get_bloginfo( 'name' ), get_post_meta( $post->ID, $meta_key_seo_excerpt, true ) , $originalContent );
				$expected_size = 100;
				break;
			case 'slug_words':
			case 'slug_length':
			case 'slug':
				$prompt = sprintf( "Given the original slug: \"%s\" and a sample from the post content : \"%s\". Create a new, SEO-friendly 4 words slug that is less than 64 chars in the format <keyword_1>-<keyword_2>-<keyword_3>-<keyword_4>", $post->post_name, $originalContent );
				$expected_size = 60;
				break;
			
			//MAGIC FIXES
			
			case 'magic_fix_images_missing_alt_text':
				$prompt = sprintf( "Given the original title: \"%s\" and paragraph above the image : \"%s\", create a new, SEO-friendly description that will add alt text to the image. (one sentence with 5 to 10 words max). New alt text could be :", $post->post_title, $originalContent );
				break;
			case 'links_missing_internal':

				// Get keywords from the post.
				$keywords = [];
				if ( $ai_keywords ) {
					$keywords = get_post_meta( $post->ID, '_seo_engine_ai_keywords', true );
				} 

				if ( empty( $keywords ) || !is_array( $keywords ) ) {
					$keywords_prompt = sprintf(" Givent the content of the post : \"%s\", identify the main keywords that are relevant to the post, they will be used to search the website for realted posts using WordPress search. Return a JSON array with the keywords, like this : {\"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"]}.", $originalContent );
					$keywords_json = $mwai->simpleJsonQuery( $keywords_prompt );
					$keywords = $keywords_json['keywords'] ?? [];
				}

				if ( empty( $keywords ) ) {
					$core->log( "⚠️ No keywords found for the post." );
					return false;
				}

				// Gather the posts that match the keywords.
				$posts = get_posts( [
					's' => implode( ' ', $keywords ),
					'post_type' => 'any',
					'post_status' => 'publish',
					'numberposts' => 5,
				] );

				if ( empty( $posts ) ) {
					$core->log( "⚠️ No posts found for the keywords." );
					return false;
				}
				
				$posts_list = [];
				foreach ( $posts as $related_post ) {
					// Skip the current post.
					if ( $related_post->ID === $post->ID ) {
						continue;
					}

					$posts_list[] = sprintf( "<a href=\"%s\" target=\"_blank\">%s</a> (Excerpt: \"%s\")", get_permalink( $related_post->ID ), $related_post->post_title, wp_trim_words( $related_post->post_content, 20 ) );
				}

				if ( empty( $posts_list ) ) {
					$core->log( "⚠️ No related posts found for the keywords." );
					return false;
				}

				// Create the prompt.
				$prompt = sprintf( "Given the original title: \"%s\" and a sample from the post content : \"%s\", create a new, SEO-friendly paragraph \"Check out our other articles\" that will add a few internal links to related posts on the website. The format should be natural, very human, something along \"We are also talking about [keyword] on %s\". Do NOT include links  that are not provided, only use the one provided here. The Excerpt is just for you to have context about these posts, do NOT include it in the paragraphe. Include the links using the <a> element as provided.", $post->post_title, $originalContent, implode( ', ', $posts_list ) );
				break;
	
			case 'links_missing_external':
				$prompt = sprintf( "Given the original title: \"%s\" and a sample from the post content : \"%s\", create a new, SEO-friendly paragraph \"You might be interested in\" that will add a few external embedded links to wikipedia articles that are related. The format should be natural, very human, something along \"Speaking of [keyword], you might be interested in  <a href=\"[https_link_to_article]\" target=\"_blank\">[wikipedia_article]</a>\". It should be natural, so it should not start with \"You might be interested in\". The wikipedia articles should be related to the post content, and the links should be embedded in the text and it should feel like a natural part of the current paragraph.", $post->post_title, $originalContent );
				break;
			
				//DEFAULT
			default:
				$core->log( "⚠️ Unknown field ($field), can't prompt AI." );
				return false;
		}

		if ( $ai_keywords ) {
			$keywords = get_post_meta( $post->ID, '_seo_engine_ai_keywords', true );
			if ( !empty( $keywords ) && is_array( $keywords ) ) {
				$prompt = sprintf( '%s. The user wants this keywords to be what guides your suggestion : %s.', $prompt, implode(', ', $keywords) );
			}
		}

		$prompt = sprintf( '<instructions>Reply only with the asked content, no other text or explanations, nothing else. Your reply should be in %s.</instructions> <prompt>%s</prompt>', $language, $prompt );

		$ai_suggestion = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo-engine'] );
		$ai_suggestion = Meow_MWSEO_Modules_Suggestions::verify_ai_suggestion( $ai_suggestion, $expected_size, $language );
		

		//sanitize ai suggestion if needed
		switch ($field){
			case 'slug_length':
			case 'slug_words':
			case 'slug':
				$ai_suggestion = sanitize_title( $ai_suggestion );
				break;
			
			case 'links_missing_internal':
			case 'links_missing_external':
				// Convert to block editor format.
				$ai_suggestion = sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", $ai_suggestion );
				break;
			case 'excerpt_seo_length':
			case 'seo_excerpt':
			case 'excerpt_missing':
			case 'excerpt':
				$ai_suggestion = preg_replace('/.*\n/', '', $ai_suggestion, 1);
			default:
				break;
		}

		return $ai_suggestion;
	}

	public static function verify_ai_suggestion( $ai_suggestion, $expected_size, $language ) {
		$core = new Meow_MWSEO_Core();
		$is_auto_correct_enabled = get_option( 'mwseo_options', null )[ 'ai_auto_correct' ] ?? false;

		if ( strlen( $ai_suggestion ) > $expected_size && $expected_size > 0 && $is_auto_correct_enabled) {
			$core->log( "⚠️ AI Suggestion too long." );

			$prompt = sprintf('In %s, simplistically paraphrase the following "%s" (currently a %d characters string) into a string with less than %d characters, and ensure that its essential concepts and format are retained. Only the reduced form is required. Shortened string is: ', $language, $ai_suggestion, strlen($ai_suggestion), $expected_size);
			
			global $mwai;
			$ai_suggestion = $mwai->simpleTextQuery( $prompt, [ 'scope' => 'seo-engine'] );
		}
		
		return $ai_suggestion;
	}

    public static function get_post_sample_context( $post ){
		$core = new Meow_MWSEO_Core();
		$check_live_content = $core->get_option( 'check_live_content', false );
		
		$content = '';
		
		// If check_live_content is enabled, try to fetch the live content
		if ( $check_live_content ) {
			$content = $core->get_live_content( $post );
		} else {
			$content = strip_tags( $post->post_content );
		}
		
		$words = str_word_count( $content, 1 );
		$words_count = count( $words );

		// If the post is less than 300 words, get the whole content.
		if ( $words_count < 300 ) {
			return $content;
		}

		// Get a total of 300 words from the post, 100 from the beginning, 100 from the middle and 100 from the end.
		$words_per_section = 200;
		$words_per_section = $words_per_section > $words_count ? $words_count : $words_per_section;
		$words_per_section = $words_per_section < 50 ? 50 : $words_per_section;

		$words_beginning = array_slice( $words, 0, $words_per_section );
		$words_middle = array_slice( $words, floor( $words_count / 2 ) - floor( $words_per_section / 2 ), $words_per_section );
		$words_end = array_slice( $words, $words_count - $words_per_section, $words_per_section );

		$context = sprintf(
			"Beginning of content : \"%s\". Middle of content : \"%s\". End of content : \"%s\".",
			implode( ' ', $words_beginning ),
			implode( ' ', $words_middle ),
			implode( ' ', $words_end )
		);

		return $context;
	}
}