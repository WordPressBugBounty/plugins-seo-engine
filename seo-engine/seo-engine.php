<?php
/*
Plugin Name: SEO Engine
Plugin URI: https://meowapps.com
Description: SEO Engine is built for the AI-driven future. Create quality content, and we handle the SEO. Simple, neat, and hassle-free!
Version: 0.3.4
Author: Jordy Meow
Author URI: https://jordymeow.com
Text Domain: seo-engine

Dual licensed under the MIT and GPL licenses:
http://www.opensource.org/licenses/mit-license.php
http://www.gnu.org/licenses/gpl.html
*/

define( 'SEOENGINE_VERSION', '0.3.4' );
define( 'SEOENGINE_PREFIX', 'mwseo' );
define( 'SEOENGINE_DOMAIN', 'seo-engine' );
define( 'SEOENGINE_ENTRY', __FILE__ );
define( 'SEOENGINE_PATH', dirname( __FILE__ ) );
define( 'SEOENGINE_URL', plugin_dir_url( __FILE__ ) );

require_once( 'classes/init.php' );

?>
