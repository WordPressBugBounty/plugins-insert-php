<?php
/**
 * Factory Taxonomies
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>
 * @since         1.0.0
 * @package       core
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'FACTORY_T_000_LOADED' ) ) {
	return;
}

define( 'FACTORY_TAXONOMIES_335_VERSION', '3.3.5' );

define( 'FACTORY_TAXONOMIES_335_LOADED', true );

define( 'FACTORY_TAXONOMIES_335_DIR', dirname( __FILE__ ) );
define( 'FACTORY_TAXONOMIES_335_URL', plugins_url( '', __FILE__ ) );

load_plugin_textdomain( 'factory_taxonomies_335', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );

// sets version of admin interface
if ( is_admin() ) {
	if ( ! defined( 'FACTORY_FLAT_ADMIN' ) ) {
		define( 'FACTORY_FLAT_ADMIN', true );
	}
}

#comp merge
require( FACTORY_TAXONOMIES_335_DIR . '/taxonomy.class.php' );
require( FACTORY_TAXONOMIES_335_DIR . '/taxonomy.php' );
#endcomp