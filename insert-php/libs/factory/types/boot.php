<?php
/**
 * Factory Types
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>
 * @since         1.0.0
 * @package       factory-types
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'FACTORY_TYPES_415_LOADED' ) ) {
	return;
}

define( 'FACTORY_TYPES_415_VERSION', '4.1.5' );
define( 'FACTORY_TYPES_415_LOADED', true );

define( 'FACTORY_TYPES_415_DIR', dirname( __FILE__ ) );
define( 'FACTORY_TYPES_415_URL', plugins_url( '', __FILE__ ) );

load_plugin_textdomain( 'wbcr_factory_types_415', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );

// sets version of admin interface
if ( is_admin() ) {
	if ( ! defined( 'FACTORY_FLAT_ADMIN' ) ) {
		define( 'FACTORY_FLAT_ADMIN', true );
	}
}

#comp merge
require( FACTORY_TYPES_415_DIR . '/types.php' );
require( FACTORY_TYPES_415_DIR . '/type.class.php' );
require( FACTORY_TYPES_415_DIR . '/includes/type-menu.class.php' );
#endcomp