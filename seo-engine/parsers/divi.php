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
    if ( $block['blockName'] === 'divi/blog' ) return mwseo_divi_blog_links( $block['attrs'] ?? [] );
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

            // Desktop first, then the first breakpoint that carries a value.
            foreach ( array_merge( [ $val['desktop'] ?? null ], $val ) as $breakpoint ) {
                $value = mwseo_divi_value_to_html( $breakpoint['value'] ?? null );
                if ( $value !== null ) break;
            }

            if ( is_string( $value ) && trim( $value ) !== '' ) {
                $out[] = $value;
            }
        } else if ( is_array( $val ) ) {
            mwseo_divi_collect_inner_content( $val, $out );
        }
    }
}

//* innerContent is a string (text, HTML) or, for buttons/CTAs, an array with text + linkUrl.
function mwseo_divi_value_to_html( $value ) {
    if ( is_string( $value ) ) return $value;
    if ( is_array( $value ) && !empty( $value['linkUrl'] ) && is_string( $value['linkUrl'] ) ) {
        $text = is_string( $value['text'] ?? null ) ? $value['text'] : '';
        return '<a href="' . esc_url( $value['linkUrl'] ) . '">' . esc_html( $text ) . '</a>';
    }
    return null;
}

//* The Blog block lists posts on the frontend: render them as links so they count as internal links.
function mwseo_divi_blog_links( $attrs ) {
    $adv = $attrs['post']['advanced'] ?? [];
    $type = $adv['type']['desktop']['value'] ?? 'post';
    $number = (int)( $adv['number']['desktop']['value'] ?? 10 ); // Divi's default
    $categories = array_filter( (array)( $adv['categories']['desktop']['value'] ?? [] ), 'is_numeric' ); // "all"/"current" = no filter

    $args = [
        'post_type' => $type,
        'post_status' => 'publish',
        'posts_per_page' => $number > 0 ? $number : 10,
        'fields' => 'ids',
        'no_found_rows' => true,
    ];
    if ( !empty( $categories ) ) $args['category__in'] = array_map( 'intval', $categories );

    $links = [];
    foreach ( get_posts( $args ) as $id ) {
        $links[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>';
    }
    return implode( "\n", $links );
}
