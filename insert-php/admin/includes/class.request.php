<?php
/**
 * Woody Request class
 *
 * Contains methods for executing requests and processing responses.
 * Uses the WINP\JsonMapper\Mapper to convert the response to a convenient object.
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
use WpOrg\Requests\Requests;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Request class
 */
class WINP_Request {

	/**
	 * Base request URL.
	 */
	const WINP_REQUEST_URL = 'https://api.woodysnippet.com/v2/woody/';

	/**
	 * WINP_REQUEST constructor.
	 */
	public function __construct() {
		// Load GPL-compatible DTO classes.
		require_once WINP_PLUGIN_DIR . '/admin/includes/dto/class-dto-base.php';
		require_once WINP_PLUGIN_DIR . '/admin/includes/dto/class-type.php';
		require_once WINP_PLUGIN_DIR . '/admin/includes/dto/class-snippet.php';
	}

	/**
	 * Get license key
	 *
	 * @return string
	 */
	private function get_key() {
		return WINP_Plugin::app()->premium->get_key();
	}

	/**
	 * Get license plugin_id
	 *
	 * @return string
	 */
	private function get_plugin_id() {
		return WINP_Plugin::app()->premium->get_setting( 'plugin_id' );
	}

	/**
	 * Get base64 token string
	 *
	 * @return string
	 */
	private function get_token() {
		return base64_encode( $this->get_key() );
	}

	/**
	 * Get headers
	 *
	 * @return array
	 */
	private function get_headers() {
		return [
			'Authorization' => 'Bearer ' . $this->get_token(),
			'PluginId'      => $this->get_plugin_id(),
			'Version'       => 'v2', // That tells server that the user is using SDK, not Freemius.
		];
	}

	/**
	 * Check is key data available
	 *
	 * @return bool
	 */
	public function is_key() {
		return WINP_Plugin::app()->premium->is_active() && $this->get_key();
	}

	/**
	 * Make POST request with authorization headers and return response
	 *
	 * @param string $point
	 * @param array  $args
	 *
	 * @return array|bool|WP_Error
	 */
	public function post( $point, $args = [] ) {
		if ( ! $this->is_key() ) {
			return false;
		}

		$args['headers'] = $this->get_headers();

		return wp_remote_post( self::WINP_REQUEST_URL . $point, $args );
	}

	/**
	 * Make GET request with authorization headers and return response
	 *
	 * @param string $point
	 * @param array  $args
	 *
	 * @return array|bool|WP_Error
	 */
	public function get( $point, $args = [] ) {
		// Allow common endpoints without authentication.
		$is_common_endpoint = strpos( $point, 'common' ) === 0;
		
		if ( ! $is_common_endpoint && ! $this->is_key() ) {
			return false;
		}

		// Add headers if user has valid license (even for common endpoints).
		if ( $this->is_key() ) {
			$args['headers'] = $this->get_headers();
		}

		return wp_remote_get( self::WINP_REQUEST_URL . $point, $args );
	}

	/**
	 * Make PUT request with authorization headers and return response
	 *
	 * @param string $point
	 * @param array  $args
	 *
	 * @return array|bool|WP_Error
	 */
	public function put( $point, $args = [] ) {
		if ( ! $this->is_key() ) {
			return false;
		}

		$args['method']  = Requests::PUT;
		$args['headers'] = $this->get_headers();

		return wp_remote_request( self::WINP_REQUEST_URL . $point, $args );
	}

	/**
	 * Check response
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	public function check_response( $response ) {
		if ( empty( $response ) || $response instanceof WP_Error ) {
			return false;
		}

		if ( ! isset( $response['body'] ) || empty( $response['body'] ) ) {
			return false;
		}

		if ( 200 != $response['response']['code'] && 201 != $response['response']['code'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Check body
	 *
	 * @param $body
	 *
	 * @return bool
	 */
	public function check_body( $body ) {
		if ( empty( $body ) ) {
			return false;
		}

		if ( ! is_array( $body ) && ! is_object( $body ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get response text error
	 *
	 * @param $response
	 *
	 * @return string
	 */
	public function get_response_error( $response ) {
		if ( empty( $response ) ) {
			return 'Empty response';
		}

		if ( $response instanceof WP_Error ) {
			return $response->get_error_message();
		}

		if ( is_array( $response ) ) {
			if ( ! isset( $response['body'] ) || empty( $response['body'] ) ) {
				return 'Empty body';
			}

			if ( 200 != $response['response']['code'] && 201 != $response['response']['code'] ) {
				return $response['response']['message'] . ' [Code: ' . $response['response']['code'] . ']';
			}
		}

		return 'Unknown error';
	}

	/**
	 * Get mapped object by name
	 *
	 * @param array<string, mixed>|bool|WP_Error $json         Response array from wp_remote_* functions.
	 * @param string                             $object_name  Class name (e.g., 'WINP_DTO_Snippet').
	 *
	 * @return object|bool Object instance or false on failure.
	 * @throws Exception If mapping fails.
	 */
	public function map_object( $json, $object_name ) {
		if ( ! $this->check_response( $json ) ) {
			return false;
		}

		if ( is_wp_error( $json ) || ! isset( $json['body'] ) ) {
			return false;
		}

		$body = json_decode( $json['body'], true );

		if ( ! $this->check_body( $body ) ) {
			return false;
		}

		try {
			if ( ! method_exists( $object_name, 'from_array' ) ) {
				throw new Exception( "Class {$object_name} does not have a from_array method" );
			}

			return $object_name::from_array( $body );
		} catch ( Exception $exception ) {
			return false;
		}
	}

	/**
	 * Get mapped objects by name
	 *
	 * @param array<string, mixed>|bool|WP_Error $json         Response array from wp_remote_* functions.
	 * @param string                             $object_name  Class name (e.g., 'WINP_DTO_Snippet').
	 *
	 * @return array<object>|bool Array of object instances or false on failure.
	 * @throws Exception If mapping fails.
	 */
	public function map_objects( $json, $object_name ) {
		if ( ! $this->check_response( $json ) ) {
			return false;
		}

		if ( is_wp_error( $json ) || ! isset( $json['body'] ) ) {
			return false;
		}

		$body = json_decode( $json['body'], true );

		if ( ! is_array( $body ) ) {
			return false;
		}

		try {
			if ( ! method_exists( $object_name, 'array_from_json' ) ) {
				throw new Exception( "Class {$object_name} does not have an array_from_json method" );
			}

			return $object_name::array_from_json( $body );
		} catch ( Exception $exception ) {
			return false;
		}
	}
}
