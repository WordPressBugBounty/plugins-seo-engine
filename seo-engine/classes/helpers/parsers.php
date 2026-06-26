<?php

class Meow_MWSEO_Helpers_Parsers
{
    private $core = null;

    

    public function __construct( $core )
    {
        $this->core = $core;

        $parsers = $this->core->get_option( 'parsers', [] );
        if( empty( $parsers ) ) return;


        // Divi
        if ( $parsers['Divi 5'] ?? false )
        {
            require_once( MWSEO_PATH . '/parsers/divi.php' );
        }   
    }

    public function refresh_parsers( $options ) {
        //* Refresh the parsers every time we get the options
        $options['parsers'] = [
            'Divi 5'          => defined( 'ET_BUILDER_5_DIR' ),
        ];

        return $options;
    }

    /**
     * @param array    $blocks    Result of parse_blocks().
     * @param string   $prefix    Block-name prefix for this builder (e.g. "divi/"). Only blocks whose
     *                            name starts with it go through the extractor; everything else falls
     *                            back to its rendered innerHTML.
     * @param callable $extractor function( array $block ): ?string the block's content.
     * @return string
     */
    public static function read_blocks( $blocks, $prefix, callable $extractor )
    {
        if ( !is_array( $blocks ) ) {
            return '';
        }

        $parts = [];

        foreach ( $blocks as $block ) {
            if ( !is_array( $block ) ) {
                continue;
            }

            $name = $block['blockName'] ?? null;

            // Only this builder's blocks go through the extractor; anything else
            // (core blocks, other builders) falls back to its rendered innerHTML.
            if ( $name !== null && strpos( $name, $prefix ) === 0 ) {
                $extracted = call_user_func( $extractor, $block );
                if ( is_string( $extracted ) && trim( $extracted ) !== '' ) {
                    $parts[] = $extracted;
                }
            } else if ( isset( $block['innerHTML'] ) && trim( $block['innerHTML'] ) !== '' ) {
                // Not this builder's block, use the default "innerHTML" if available
                $parts[] = $block['innerHTML'];
            }

            // Recurse into nested blocks
            if ( !empty( $block['innerBlocks'] ) ) {
                $parts[] = self::read_blocks( $block['innerBlocks'], $prefix, $extractor );
            }
        }

        $parts = array_filter( $parts, function ( $part ) {
            return is_string( $part ) && trim( $part ) !== '';
        } );

        return implode( "\n", $parts );
    }


}