<?php
/**
 * Admin-specific notice handlers
 *
 * Registers built-in admin notices using the WINP_Notices system.
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_Admin_Notices
 */
class WINP_Admin_Notices {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_notices' ] );
		add_action( 'admin_init', [ $this, 'register_pending_error_notices' ] );
	}

	/**
	 * Register pending error notices from frontend fatal errors
	 * 
	 * @return void
	 */
	public function register_pending_error_notices() {
		$pending_notices = get_option( 'winp_pending_error_notices', [] );
		
		if ( ! is_array( $pending_notices ) || empty( $pending_notices ) ) {
			return;
		}

		// Get dismissed notices.
		$dismissed_notices = get_option( 'winp_dismissed_notices', [] );
		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = [];
		}

		// Register each pending notice that hasn't been dismissed.
		$updated = false;
		foreach ( $pending_notices as $notice_id => $notice_data ) {
			// Check if this notice was dismissed (full ID with prefix).
			$full_notice_id = 'winp_notice_' . $notice_id;
			
			// Check if dismissed and not expired.
			$is_dismissed = false;
			if ( isset( $dismissed_notices[ $full_notice_id ] ) ) {
				$expires      = (int) $dismissed_notices[ $full_notice_id ];
				$is_dismissed = 0 === $expires || $expires > time();
			}
			
			if ( $is_dismissed ) {
				// Notice was dismissed, remove it from pending list.
				unset( $pending_notices[ $notice_id ] );
				$updated = true;
			} else {
				// Not dismissed yet, register it.
				WINP_Notices::add_notice(
					$notice_id,
					$notice_data['message'],
					$notice_data['type'],
					true,
					0
				);
			}
		}

		// Update option if any notices were removed.
		if ( $updated ) {
			if ( empty( $pending_notices ) ) {
				delete_option( 'winp_pending_error_notices' );
			} else {
				update_option( 'winp_pending_error_notices', $pending_notices, false );
			}
		}
	}

	/**
	 * Register built-in admin notices
	 * 
	 * @return void
	 */
	public function register_notices() {
		// Security warning for PHP snippets.
		WINP_Notices::add_notice(
			'warning_security_notice',
			$this->get_warning_notice(),
			'warning',
			true,
			0,
			[
				'post',
				'post-new',
				'edit',
			]
		);

		// Safe mode notice.
		WINP_Notices::add_notice( 
			'safe_mode', 
			$this->get_safe_mode_notice(), 
			'warning', 
			false 
		);

		// Snippet error notice.
		WINP_Notices::add_notice(
			'result_error',
			$this->get_throw_php_error_notice(),
			'error',
			false,
			0,
			[
				'post',
				'post-new',
				'edit',
			] 
		);
	}

	/**
	 * Security warning for PHP snippet editing
	 *
	 * @return string|null
	 */
	private function get_warning_notice() {
		$current_screen = get_current_screen();

		if ( ! $current_screen || 'post' !== $current_screen->base || ( WINP_SNIPPETS_POST_TYPE !== $current_screen->post_type ) ) {
			return null;
		}

		$snippet_type = WINP_Helper::get_snippet_type();

		if ( WINP_SNIPPET_TYPE_PHP !== $snippet_type ) {
			return null;
		}

		$notice = '<b>' . __( 'Woody Code Snippets', 'insert-php' ) . '</b>: ' . __( 'Custom PHP snippets run on your site. Test thoroughly before activating. Consider using Safe Mode to preview changes.', 'insert-php' );
		return '<p>' . $notice . '</p>';
	}

	/**
	 * Error notification after saving snippet
	 *
	 * @return string|null
	 */
	private function get_throw_php_error_notice() {
		$save_snippet_result = WINP_HTTP::get( 'wbcr_inp_save_snippet_result' );
		$post_id             = WINP_HTTP::get( 'post' );

		if ( ! empty( $save_snippet_result ) && 'code-error' == $save_snippet_result ) {
			$post_id = ! empty( $post_id ) ? intval( $post_id ) : null;

			if ( $post_id ) {
				$error = WINP_Plugin::app()->get_execute_object()->getSnippetError( $post_id );

				if ( is_array( $error ) ) {
					// translators: 1: line number, 2: error message.
					return sprintf( '<p>%s</p><p><strong>%s</strong></p>', sprintf( __( 'The snippet has been deactivated due to an error on line %d:', 'insert-php' ), $error['line'] ), $error['message'] );
				}
			}
		}

		return null;
	}

	/**
	 * Safe mode reminder notification
	 *
	 * @return string|null
	 */
	private function get_safe_mode_notice() {
		if ( ! WINP_Helper::is_safe_mode() ) {
			return null;
		}

		$disable_safe_mode_url = esc_url( add_query_arg( [ 'wbcr-php-snippets-disable-safe-mode' => 1 ] ) );

		$notice  = __( 'Woody Code Snippets', 'insert-php' ) . ': ' . __( 'Safe Mode is active. All snippets are temporarily disabled. You can re-enable them after fixing any issues.', 'insert-php' );
		$notice .= ' <a href="' . $disable_safe_mode_url . '" class="button button-default">' . __( 'Disable Safe Mode', 'insert-php' ) . '</a>';

		return '<p>' . $notice . '</p>';
	}
}
