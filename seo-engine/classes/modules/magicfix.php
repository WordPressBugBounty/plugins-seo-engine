<?php

class Meow_MWSEO_Modules_MagicFix
{
  private $post = null;
  private $core = null;
  private $meta_key_seo_title = null;
  private $meta_key_seo_excerpt = null;

  // Constructor to initialize the class
  function __construct( $post, $core, 
		$meta_key_seo_title = '_kiss_seo_title',
		$meta_key_seo_excerpt = '_kiss_seo_excerpt' )
  {
    $this->post = $post;
    $this->core = $core;
    $this->meta_key_seo_title = $meta_key_seo_title;
    $this->meta_key_seo_excerpt = $meta_key_seo_excerpt;
  }

  // Fix missing title using suggestions
  function magic_fix_title_missing()
  {
    try {
      $new_title = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'title' );
      $new_title = str_replace( '"', '', $new_title );
      return [ 'solution' => 'New Title:', 'value' => $new_title ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the post title
  function magic_fix_title_missing_post_update( $new_title )
  {
    try {
      $this->post->post_title = $new_title;
      wp_update_post( $this->post );
      return [ 'solution' => 'The Title was updated.', 'value' => $new_title ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix missing SEO title using suggestions
  function magic_fix_title_seo()
  {
    try {
      $new_title = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'seo_title' );
      $new_title = str_replace( '"', '', $new_title );
      return [ 'solution' => 'New SEO Title:', 'value' => $new_title ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the SEO title
  function magic_fix_title_seo_post_update( $new_title )
  {
    try {
      update_post_meta( $this->post->ID, $this->meta_key_seo_title, $new_title );
      return [ 'solution' => 'The SEO Title was updated.', 'value' => $new_title ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix missing excerpt using suggestions
  function magic_fix_excerpt_missing()
  {
    try {
      $new_excerpt = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'excerpt' );
      $new_excerpt = str_replace( '"', '', $new_excerpt );
      return [ 'solution' => 'New Excerpt:', 'value' => $new_excerpt ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the post excerpt
  function magic_fix_excerpt_missing_post_update( $new_excerpt )
  {
    try {
      $this->post->post_excerpt = $new_excerpt;
      wp_update_post( $this->post );
      return [ 'solution' => 'The Excerpt was updated.', 'value' => $new_excerpt ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix SEO excerpt length using suggestions
  function magic_fix_excerpt_seo_length()
  {
    try {
      $new_excerpt = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'seo_excerpt' );
      $new_excerpt = str_replace( '"', '', $new_excerpt );
      return [ 'solution' => 'New SEO Excerpt:', 'value' => $new_excerpt ];
    }
    catch ( Exception $e ) {
      $trace = $e->getTrace();
      $firstClass = isset( $trace[0]['class'] ) ? $trace[0]['class'] : null;
      $this->core->log( '❌ Generating Excerpt: ' . $firstClass );
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the SEO excerpt
  function magic_fix_excerpt_seo_length_post_update( $new_excerpt )
  {
    try {
      update_post_meta( $this->post->ID, $this->meta_key_seo_excerpt, $new_excerpt );
      return [ 'solution' => 'The SEO Excerpt was updated.', 'value' => $new_excerpt ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix slug length using suggestions
  function magic_fix_slug_length()
  {
    try {
      $new_slug = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'slug' );
      $new_slug = str_replace( '"', '', $new_slug );
      return [ 'solution' => 'New Slug:', 'value' => $new_slug ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the post slug
  function magic_fix_slug_length_post_update( $new_slug )
  {
    try {
      $this->post->post_name = $new_slug;
      wp_update_post( $this->post );
      return [ 'solution' => 'The Slug was updated.', 'value' => $new_slug ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix missing alt text for images
  function magic_fix_images_missing_alt_text( $images )
  {
    try {
      $missing_images = [];

      if ( empty( $images ) ) {
        return [ 'solution' => '❌ No images with missing alt text were found.', 'value' => '' ];
      }

      global $mfrh_rest;
      foreach ( $images as $index => $image ) {
        $attachment_id = attachment_url_to_postid( $image );

        if ( $attachment_id == 0 ) {
          continue;
        }

        $alt_attchment = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( !empty( $alt_attchment ) ) {
          $missing_images[ $index ]['attachment_alt'] = $alt_attchment;
        }

        if ( !empty( $mfrh_rest ) ) {
          $response = $mfrh_rest->rest_ai_suggest( [
            'mediaId' => $attachment_id,
            'type' => 'alternative text',
          ] );

          if ( is_wp_error( $response ) ) {
            $this->core->log( '❌ Generating: ' . $response->get_error_message() );
            continue;
          }

          $data = $response->get_data();

          if ( !$data['success'] ) {
            throw new Exception( $data['message'] );
          }

          $new_alt = $data['data'];
          $missing_images[ $index ]['mfrh_alt'] = $new_alt;
        }

        $missing_images[ $index ]['media_id'] = $attachment_id;
        $missing_images[ $index ]['img_src'] = $image;
      }

      return [ 'solution' => 'Missing Alt Text:', 'value' => $missing_images ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the alt text for images
  function magic_fix_images_missing_alt_text_post_update( $images )
  {
    try {
      $new_alt_texts = [];

      if ( empty( $images ) ) {
        return [ 'solution' => '❌ No images with missing alt text were found.', 'value' => '' ];
      }

      foreach ( $images as $image ) {
        if ( array_key_exists( 'is_cancelled', $image ) && $image['is_cancelled'] ) {
          continue;
        }

        $new_alt = $image['mfrh_alt'];
        update_post_meta( $image['media_id'], '_wp_attachment_image_alt', $new_alt );
        $new_alt_texts[ $image['media_id'] ] = $new_alt;
      }

      return [ 'solution' => 'Alt Text was updated:', 'value' => $new_alt_texts ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Fix missing links by generating a new paragraph
  function magic_fix_links_missing()
  {
    try {
      $new_paragraph = Meow_MWSEO_Modules_Suggestions::prompt( $this->post, 'magic_fix_links_missing' );
      return [ 'solution' => 'A new paragraph was added:', 'value' => $new_paragraph ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }

  // Update the post with a new paragraph
  function magic_fix_links_missing_update_post( $new_paragraph )
  {
    try {
      $new_content = $this->post->post_content . "\n\n" . $new_paragraph;
      $this->post->post_content = $new_content;
      wp_update_post( $this->post );
      return [ 'solution' => 'A new paragraph was added:', 'value' => $new_paragraph ];
    }
    catch ( Exception $e ) {
      return [ 'solution' => 'ERROR', 'value' => $e->getMessage() ];
    }
  }
}
