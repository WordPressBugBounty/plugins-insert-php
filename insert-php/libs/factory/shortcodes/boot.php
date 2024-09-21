<?php
/**
 * Factory Shortcodes
 *
 * @author        Alex Kovalev <alex.kovalevv@gmail.com>
 * @since         1.0.0
 * @package       factory-shortcodes
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

if ( defined( 'FACTORY_SHORTCODES_335_LOADED' ) ) {
	return;
}

define( 'FACTORY_SHORTCODES_335_VERSION', '3.3.5' );

define( 'FACTORY_SHORTCODES_335_LOADED', true );

define( 'FACTORY_SHORTCODES_335_DIR', dirname( __FILE__ ) );

#comp merge
require( FACTORY_SHORTCODES_335_DIR . '/shortcodes.php' );
require( FACTORY_SHORTCODES_335_DIR . '/shortcode.class.php' );
#endcomp
