<?php

if ( class_exists( 'MeowPro_MWSEO_Core' ) && class_exists( 'Meow_MWSEO_Core' ) ) {
	function seo_engine_thanks_admin_notices() {
		echo '<div class="error"><p>' . __( 'Thanks for installing the Pro version of SEO Engine :) However, the free version is still enabled. Please disable or uninstall it.', 'media-cleaner' ) . '</p></div>';
	}
 
	add_action( 'admin_notices', 'seo_engine_thanks_admin_notices' );
	return;
}

spl_autoload_register(function ( $class ) {
  try {
    $file = null;
    if ( strpos( $class, 'Meow_MWSEO_Modules_' ) !== false ) {
      $file = MWSEO_PATH . '/classes/modules/' . str_replace( 'meow_mwseo_modules_', '', strtolower( $class ) ) . '.php';
    }
    else if ( strpos( $class, 'Meow_MWSEO_' ) !== false ) {
      $file = MWSEO_PATH . '/classes/' . str_replace( 'meow_mwseo_', '', strtolower( $class ) ) . '.php';
    }
    else if ( strpos( $class, 'MeowKit_MWSEO_' ) !== false ) {
      $file = MWSEO_PATH . '/common/' . str_replace( 'meowkit_mwseo_', '', strtolower( $class ) ) . '.php';
    }
    else if ( strpos( $class, 'MeowKitPro_MWSEO_' ) !== false ) {
      $file = MWSEO_PATH . '/common/premium/' . str_replace( 'meowkitpro_mwseo_', '', strtolower( $class ) ) . '.php';
    }
    else if ( strpos( $class, 'MeowPro_MWSEO_Ranks' ) !== false ) {
      $file = MWSEO_PATH . '/premium/ranks/' . str_replace( 'meowpro_mwseo_ranks_', '', strtolower( $class ) ) . '.php';
    }
    else if ( strpos( $class, 'MeowPro_MWSEO_' ) !== false ) {
      $file = MWSEO_PATH . '/premium/' . str_replace( 'meowpro_mwseo_', '', strtolower( $class ) ) . '.php';
    }
    if ( $file ) {
      if ( !file_exists( $file ) ) {
        return;
      }
      require( $file );
    }
  }
  catch ( Exception $e ) {
    error_log( 'SEO Engine: ' . $e->getMessage() );
  }
});

require_once( MWSEO_PATH . '/classes/api.php');
require_once( MWSEO_PATH . '/common/helpers.php');


global $SeoEngineCore, $mwseo_readability, $mwseo_score;
$SeoEngineCore = new Meow_MWSEO_Core();
$mwseo_readability = new Meow_MWSEO_Modules_Readability();
$mwseo_score = new Meow_MWSEO_Score( $SeoEngineCore );

?>