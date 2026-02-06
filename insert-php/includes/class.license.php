<?php
/**
 * License management class
 *
 * Handles premium license activation, deactivation, and validation.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_License
 *
 * Standalone license provider with dummy methods for initial implementation.
 * All methods return hardcoded values to be replaced with real API calls later.
 */
class WINP_License {

	/**
	 * Get the license data.
	 *
	 * @return bool|\stdClass
	 */
	public static function get_data() {
		if ( ! defined( 'WASP_PLUGIN_NAMESPACE' ) ) {
			return false;
		}

		return get_option( WASP_PLUGIN_NAMESPACE . '_license_data' );
	}

	/**
	 * Check if Pro is available
	 *
	 * @return  bool
	 */
	public function is_pro_active() {
		return defined( 'WASP_PLUGIN_VERSION' );
	}

	/**
	 * Get license key
	 *
	 * @return string License key
	 */
	public function get_key() {
		$license = self::get_data();

		if ( false === $license ) {
			return '';
		}

		if ( ! isset( $license->key ) ) {
			return '';
		}

		return $license->key;
	}

	/**
	 * Check if license is currently active and valid
	 *
	 * @return bool True if active
	 */
	public function is_active() {
		$status = self::get_data();

		if ( ! $status ) {
			return false;
		}

		if ( ! isset( $status->license ) ) {
			return false;
		}

		if ( 'valid' !== $status->license ) {
			return false;
		}

		return true;
	}

	/**
	 * Get a setting value
	 *
	 * @param string $key Setting key
	 *
	 * @return mixed Setting value or null
	 */
	public function get_setting( $key ) {
		$data = self::get_data();

		if ( false === $data ) {
			return null;
		}

		if ( 'plugin_id' === $key ) {
			return $data->download_id ?? null;
		}

		return null;
	}

	/**
	 * Toggle license.
	 * 
	 * @param string $action License action.
	 * @param string $key    License Key.
	 * 
	 * @return array<string, mixed>|\WP_Error Response.
	 */
	public function toggle_license( $action, $key ) {
		if ( 'deactivate' === $action ) {
			$key = apply_filters( 'product_woody_license_key', 'free' );
		}

		$response = apply_filters( 'themeisle_sdk_license_process_woody', $key, $action );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'success' => true,
			'message' => 'activate' === $action ? __( 'Activated', 'insert-php' ) : __( 'Deactivated', 'insert-php' ),
			'license' => [
				'key'    => apply_filters( 'product_woody_license_key', 'free' ),
				'status' => apply_filters( 'product_woody_license_status', false ),
			],
		];
	}
}
