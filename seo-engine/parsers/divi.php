<?php

add_filter( 'mwseo_post_content', 'mwseo_parser_divi' , 10, 3 );

function mwseo_parser_divi( $content, $post, $should_strip_html ) {

    if ( empty( $post ) || empty( $post->post_content ) || !function_exists( 'parse_blocks' ) ) {
        return $content;
    }

    // Only handle Divi 5 block-based content; leave everything else untouched.
    if ( strpos( $post->post_content, 'wp:divi/' ) === false ) {
        return $content;
    }

    $blocks = parse_blocks( $post->post_content );
    $html = trim( Meow_MWSEO_Helpers_Parsers::read_blocks( $blocks, 'divi/', 'mwseo_divi_extract_block' ) );

    if ( $html === '' ) return $content;
    
    return $should_strip_html ? wp_strip_all_tags( $html ) : $html;
}

//* This is the core logic "extractor" that will be called for each divi/* block
function mwseo_divi_extract_block( $block ) {
    if ( empty( $block['attrs'] ) ) return '';
    
    $found = [];
    //recursive content fetching
    mwseo_divi_collect_inner_content( $block['attrs'], $found );

    return implode( "\n", $found );
}

function mwseo_divi_collect_inner_content( $attrs, &$out ) {
    if ( !is_array( $attrs ) ) {
        return;
    }

    foreach ( $attrs as $key => $val ) {
        if ( $key === 'innerContent' && is_array( $val ) ) {
            $value = null;

            if ( isset( $val['desktop']['value'] ) && is_string( $val['desktop']['value'] ) ) {
                $value = $val['desktop']['value'];
            } else {
                // Fallback: first breakpoint that carries a string value.
                foreach ( $val as $breakpoint ) {
                    if ( isset( $breakpoint['value'] ) && is_string( $breakpoint['value'] ) ) {
                        $value = $breakpoint['value'];
                        break;
                    }
                }
            }

            if ( is_string( $value ) && trim( $value ) !== '' ) {
                $out[] = $value;
            }
        } else if ( is_array( $val ) ) {
            mwseo_divi_collect_inner_content( $val, $out );
        }
    }
}