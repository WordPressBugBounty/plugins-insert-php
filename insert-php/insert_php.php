<?php
/**
 * Plugin Name: Woody Code Snippets
 * Plugin URI: https://woodysnippet.com/
 * Description: Executes PHP code, uses conditional logic to insert ads, text, media content and external service's code. Ensures no content duplication.
 * Author: Themeisle
 * Version: 2.7.2
 * WordPress Available:  yes
 * Requires License:    no
 * Text Domain: insert-php
 * Domain Path: /languages/
 * Author URI: https://themeisle.com
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Woody Code Snippets requires PHP 7.4 or higher. Please upgrade your PHP version.', 'insert-php' ); ?></p>
			</div>
			<?php
		}
	);

	return;
}

global $wbcr_inp_safe_mode;

$wbcr_inp_safe_mode = false;

// Set the constant that the plugin is activated.
define( 'WINP_PLUGIN_ACTIVE', true );

define( 'WINP_PLUGIN_VERSION', '2.7.2' );

// Root directory of the plugin.
define( 'WINP_PLUGIN_DIR', __DIR__ );

define( 'WINP_PLUGIN_FILE', __FILE__ );

// Absolute url of the root directory of the plugin.
define( 'WINP_PLUGIN_URL', plugins_url( '', __FILE__ ) );

// Relative url of the plugin.
define( 'WINP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Plugin slug.
define( 'WINP_PLUGIN_SLUG', basename( dirname( WINP_PLUGIN_FILE ) ) );

define( 'WINP_PLUGIN_NAMESPACE', str_replace( '-', '_', strtolower( trim( WINP_PLUGIN_SLUG ) ) ) );

// The type of posts used for snippets types.
define( 'WINP_SNIPPETS_POST_TYPE', 'wbcr-snippets' );

// The taxonomy used for snippets types.
define( 'WINP_SNIPPETS_TAXONOMY', 'wbcr-snippet-tags' );

// The snippets types.
define( 'WINP_SNIPPET_TYPE_PHP', 'php' );
define( 'WINP_SNIPPET_TYPE_TEXT', 'text' );
define( 'WINP_SNIPPET_TYPE_UNIVERSAL', 'universal' );
define( 'WINP_SNIPPET_TYPE_CSS', 'css' );
define( 'WINP_SNIPPET_TYPE_JS', 'js' );
define( 'WINP_SNIPPET_TYPE_HTML', 'html' );
define( 'WINP_SNIPPET_TYPE_AD', 'advert' );

// We need to update these.
define( 'WINP_UPGRADE', 'https://woodysnippet.com/upgrade' );
define( 'WINP_DOCS', 'https://docs.themeisle.com/category/2429-woody-installation-and-setup' );
define( 'WINP_ORG_SUPPORT', 'https://wordpress.org/support/plugin/insert-php/' );
define( 'WINP_SUPPORT', 'https://themeisle.com/contact/' );

// Load text domain for translations.
add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain( 'insert-php', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	} 
);

// START: Related to premium version compatibility check for below 1.3.
add_action(
	'plugins_loaded',
	function () {
		$premium_plugin_file = 'woody-ad-snippets-premium/woody-ad-snippets-premium.php';
		$premium_plugin_path = WP_PLUGIN_DIR . '/' . $premium_plugin_file;

		if ( ! file_exists( $premium_plugin_path ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $premium_plugin_file ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$premium_data = get_plugin_data( $premium_plugin_path );

		if ( version_compare( $premium_data['Version'], '1.3.0', '<' ) ) {
			deactivate_plugins( $premium_plugin_file );
			set_transient( 'winp_premium_version_incompatible', true, WEEK_IN_SECONDS );
		}
	},
	5
);

add_action(
	'admin_init',
	function () {
		if ( isset( $_GET['winp_dismiss_premium_notice'] ) && check_admin_referer( 'winp_dismiss_premium_notice' ) ) {
			delete_transient( 'winp_premium_version_incompatible' );
			wp_safe_redirect( remove_query_arg( 'winp_dismiss_premium_notice' ) );
			exit;
		}
	}
);

add_action(
	'admin_notices',
	function () {
		if ( get_transient( 'winp_premium_version_incompatible' ) ) {
			$dismiss_url = wp_nonce_url(
				add_query_arg( 'winp_dismiss_premium_notice', '1' ),
				'winp_dismiss_premium_notice'
			);
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'Woody Code Snippets Premium has been deactivated.', 'insert-php' ); ?></strong>
				</p>
				<p>
					<?php
					printf(
						// translators: %s Premium version number.
						esc_html__( 'The installed premium version is not compatible with the current version of Woody Code Snippets. Please update the premium plugin to version %s or higher.', 'insert-php' ),
						'<strong>1.3.0</strong>'
					);
					?>
				</p>
				<p>
					<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Dismiss this notice', 'insert-php' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}
);
// END: Related to premium version compatibility check for below 1.3.

require_once WINP_PLUGIN_DIR . '/includes/class.insertion.locations.php';
require_once WINP_PLUGIN_DIR . '/includes/class.http.php';
require_once WINP_PLUGIN_DIR . '/includes/class.helpers.php';
require_once WINP_PLUGIN_DIR . '/includes/class.plugin.php';

/**
 * Adds a hint and button to the fatal error message.
 *
 * Since WordPress 5.2, we have access to a special mode for catching PHP errors.
 * If a user makes a syntax error while editing a snippet, instead of a white screen
 * (if PHP errors are disabled on the server), they will see a message from WordPress
 * generated by the WP_Fatal_Error_Handler class.
 *
 * We decided to add a button to this message to switch to safe mode.
 */
add_filter(
	'wp_php_error_message',
	function ( $message ) {
		$safe_mode_url     = admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE . '&wbcr-php-snippets-safe-mode' );
		$safe_mode_button  = '<div style="margin:20px 0;padding:20px; background:#ffe8e8;">' . __( 'If you see this message after saving the snippet to the Woody Code Snippets plugin, please enable safe mode in the Woody plugin. Safe mode will allow you to continue working in the admin panel of your site and change the snippet in which you made a php error.', 'insert-php' ) . '</div>';
		$safe_mode_button .= '<a href="' . $safe_mode_url . '" class="button">' . __( 'Enable Safe Mode', 'insert-php' ) . '</a>';

		return $message . $safe_mode_button;
	}
);

/**
 * Enables/Disable safe mode, in which the php code will not be executed.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( isset( $_GET['wbcr-php-snippets-safe-mode'] ) ) {
			WINP_Helper::enable_safe_mode();
			wp_safe_redirect( esc_url( remove_query_arg( [ 'wbcr-php-snippets-safe-mode' ] ) ) );
			die();
		}

		if ( isset( $_GET['wbcr-php-snippets-disable-safe-mode'] ) ) {
			WINP_Helper::disable_safe_mode();
			wp_safe_redirect( esc_url( remove_query_arg( [ 'wbcr-php-snippets-disable-safe-mode' ] ) ) );
			die();
		}
	},
	- 1
);

/**
 * Register product to SDK
 *
 * @param array<string> $products Registered products.
 * @return array<string>
 */
function winp_sdk_register_products( $products ) {
	$products[] = WINP_PLUGIN_FILE;

	return $products;
}

/**
 * About page metadata
 *
 * @return array<string, mixed>
 */
function winp_sdk_about_page() {
	return [
		'location'         => 'edit.php?post_type=wbcr-snippets',
		'logo'             => WINP_PLUGIN_URL . '/admin/assets/img/icon-256x256.png',
		'review_link'      => false,
		'has_upgrade_menu' => false,
	];
}

/**
 * Register compatibility using SDK.
 *
 * @param array<string, array<string, string>> $compatibilities All compatibilities.
 *
 * @return array<string, array<string, string>> Registered compatibility.
 */
function winp_sdk_register_compatibility( $compatibilities ) {
	$compatibilities['WoodyPro'] = [
		'basefile' => defined( 'WASP_PLUGIN_FILE' ) ? WASP_PLUGIN_FILE : '',
		'required' => '1.3.0',
	];

	return $compatibilities;
}

add_filter( 'themeisle_sdk_products', 'winp_sdk_register_products' );
add_filter( WINP_PLUGIN_NAMESPACE . '_about_us_metadata', 'winp_sdk_about_page' );
add_filter( 'themeisle_sdk_compatibilities/' . basename( WINP_PLUGIN_DIR ), 'winp_sdk_register_compatibility' );

// Register activation/deactivation hooks.
register_activation_hook(
	WINP_PLUGIN_FILE,
	function () {
		global $winp_snippets_locations;
		if ( ! isset( $winp_snippets_locations ) ) {
			$winp_snippets_locations = new WINP_Insertion_Locations();
		}
	
		// Instantiate plugin for activation.
		$plugin = new WINP_Plugin();
		$plugin->activation_hook();
	} 
);

register_deactivation_hook(
	WINP_PLUGIN_FILE,
	function () {
		global $winp_snippets_locations;
		if ( ! isset( $winp_snippets_locations ) ) {
			$winp_snippets_locations = new WINP_Insertion_Locations();
		}
	
		// Instantiate plugin for deactivation.
		$plugin = new WINP_Plugin();
		$plugin->deactivation_hook();
	} 
);

// Initialize on 'init' hook with priority 0 to avoid early translation loading (WP 6.7+)
add_action(
	'init',
	function () {
		global $winp_snippets_locations;
		$winp_snippets_locations = new WINP_Insertion_Locations();

		try {
			require_once WINP_PLUGIN_DIR . '/vendor/autoload.php';
			new WINP_Plugin();
		} catch ( Exception $exception ) {
			// Plugin wasn't initialized due to an error
			define( 'WINP_PLUGIN_THROW_ERROR', true );

			$wbcr_plugin_error_func = function () use ( $exception ) {
				$error = sprintf( 'The %s plugin has stopped. <b>Error:</b> %s Code: %s', 'Woody Ad Snippets', $exception->getMessage(), $exception->getCode() );
				echo '<div class="notice notice-error"><p>' . $error . '</p></div>';
			};

			add_action( 'admin_notices', $wbcr_plugin_error_func );
			add_action( 'network_admin_notices', $wbcr_plugin_error_func );
		}
	},
	0 
);
