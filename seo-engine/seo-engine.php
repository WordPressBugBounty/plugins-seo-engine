<?php
/*
Plugin Name: SEO Engine
Plugin URI: https://meowapps.com
Description: Made it through the SEO plugin wasteland? You've earned a coffee ☺️ Quietly powerful AI SEO that actually works. No bloat, just results. Enjoy! 💕
Version: 0.6.1
Author: Jordy Meow
Author URI: https://jordymeow.com
Text Domain: seo-engine

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html
*/

define( 'MWSEO_VERSION', '0.6.1' );
define( 'MWSEO_PREFIX', 'mwseo' );
define( 'MWSEO_DOMAIN', 'seo-engine' );
define( 'MWSEO_ENTRY', __FILE__ );
define( 'MWSEO_PATH', dirname( __FILE__ ) );
define( 'MWSEO_URL', plugin_dir_url( __FILE__ ) );
define( 'MWSEO_PRO', true );
define( 'MWSEO_ITEM_ID', 23019127 );

require_once( 'classes/init.php' );

?>
