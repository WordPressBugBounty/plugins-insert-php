<?php
/**
 * Factory viewtable
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>
 * @since         1.0.0
 * @package       factory-viewtables
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// module provides function only for the admin area
if ( ! is_admin() ) {
	return;
}

if ( defined( 'FACTORY_VIEWTABLES_415_LOADED' ) ) {
	return;
}

define( 'FACTORY_VIEWTABLES_415_VERSION', '4.1.5' );
define( 'FACTORY_VIEWTABLES_415_LOADED', true );

define( 'FACTORY_VIEWTABLES_415_DIR', dirname( __FILE__ ) );
define( 'FACTORY_VIEWTABLES_415_URL', plugins_url( '', __FILE__ ) );

#comp merge
require( FACTORY_VIEWTABLES_415_DIR . '/viewtable.class.php' );
require( FACTORY_VIEWTABLES_415_DIR . '/includes/viewtable-columns.class.php' );
#endcomp