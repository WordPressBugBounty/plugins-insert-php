<?php
/**
 * Admin notices system.
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_Notices
 */
class WINP_Notices {

	/**
	 * Class instance.
	 * 
	 * @var WINP_Notices
	 */
	private static $instance = null;

	/**
	 * Prefix.
	 * 
	 * @var string
	 */
	private static $prefix = 'winp_notice_';

	/**
	 * Notices storage.
	 * 
	 * @var array<int, array<string, mixed>> Notices data
	 */
	private static $notices = [];

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		add_action( 'admin_notices', [ $this, 'display_notices' ] );
		add_action( 'wp_ajax_winp_dismiss_notice', [ $this, 'ajax_dismiss_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Get singleton instance
	 *
	 * @return WINP_Notices
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Enqueue scripts for dismissible notices
	 * 
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue if there are notices to display.
		if ( empty( self::$notices ) ) {
			return;
		}
		
		// Add CSS for error notice styling.
		wp_add_inline_style(
			'common',
			'.winp-admin-notice { 
				padding: 12px 16px !important; 
			}
			.winp-admin-notice.is-dismissible {
				padding-right: 38px !important;
			}
			.winp-admin-notice p { margin: 0.5em 0; }
			.winp-admin-notice p:first-child { margin-top: 0; }
			.winp-admin-notice p:last-child { margin-bottom: 0; }
			.winp-error-notice-header { 
				margin-bottom: 10px; 
				font-size: 14px;
				line-height: 1.5;
			}
			.winp-snippet-edit-link {
				color: #2271b1;
				text-decoration: none;
				font-weight: 500;
				border-bottom: 1px solid transparent;
				transition: all 0.15s ease;
			}
			.winp-snippet-edit-link:hover {
				color: #135e96;
				border-bottom-color: #135e96;
			}
			.winp-snippet-edit-link:focus {
				box-shadow: 0 0 0 2px #2271b1;
				outline: 2px solid transparent;
				border-radius: 2px;
			}
			.winp-admin-notice details { 
				margin: 10px 0 4px; 
				border: 1px solid rgba(0, 0, 0, 0.15);
				border-radius: 4px;
				background: #fff;
			}
			.winp-admin-notice details summary { 
				cursor: pointer; 
				font-weight: 500;
				color: #2271b1;
				padding: 10px 12px;
				user-select: none;
				font-size: 13px;
				display: flex;
				align-items: center;
				transition: all 0.15s ease;
			}
			.winp-admin-notice details summary::-webkit-details-marker {
				display: none;
			}
			.winp-admin-notice details summary::before {
				content: "▶";
				display: inline-block;
				margin-right: 8px;
				font-size: 10px;
				transition: transform 0.2s ease;
			}
			.winp-admin-notice details[open] summary::before {
				transform: rotate(90deg);
			}
			.winp-admin-notice details summary:hover { 
				color: #135e96;
				background: rgba(0, 0, 0, 0.03);
			}
			.winp-admin-notice details[open] summary { 
				border-bottom: 1px solid rgba(0, 0, 0, 0.1);
				background: rgba(0, 0, 0, 0.02);
			}
			.winp-error-details {
				padding: 14px;
			}
			.winp-error-message,
			.winp-error-location {
				margin-bottom: 14px;
			}
			.winp-error-location {
				margin-bottom: 0;
			}
			.winp-error-message strong,
			.winp-error-location strong {
				display: block;
				margin-bottom: 6px;
				font-size: 11px;
				text-transform: uppercase;
				color: #646970;
				font-weight: 600;
				letter-spacing: 0.5px;
			}
			.winp-error-message code {
				display: block;
				padding: 10px 12px;
				background: #f6f7f7;
				border-left: 3px solid #d63638;
				font-size: 13px;
				line-height: 1.6;
				color: #d63638;
				word-break: break-word;
				font-family: Consolas, Monaco, monospace;
			}
			.winp-error-location {
				font-size: 13px;
				color: #2c3338;
				line-height: 1.5;
			}
			.winp-line-number {
				color: #646970;
				font-style: italic;
			}
			.winp-error-notice-footer {
				margin-top: 12px;
				padding-top: 12px;
				border-top: 1px solid rgba(0, 0, 0, 0.1);
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
			}
			.winp-dismiss-help {
				margin: 0 !important;
				font-size: 13px;
				color: #646970;
				flex: 1;
			}
			.winp-manual-dismiss-btn {
				background: #f0f0f1;
				border-color: #2271b1;
				color: #2271b1;
				flex-shrink: 0;
				font-weight: 500;
			}
			.winp-manual-dismiss-btn:hover {
				background: #2271b1;
				border-color: #2271b1;
				color: #fff;
			}'
		);
		
		wp_add_inline_script(
			'common',
			'(function($) {
				// Handle X button dismiss
				$(document).on("click", ".winp-admin-notice.is-dismissible .notice-dismiss", function(e) {
					var notice = $(this).closest(".winp-admin-notice");
					var noticeId = notice.data("notice-id");
					var nonce = notice.data("nonce");
					
					if (noticeId && nonce && typeof ajaxurl !== "undefined") {
						$.post(ajaxurl, {
							action: "winp_dismiss_notice",
							notice_id: noticeId,
							nonce: nonce
						});
					}
				});
				
				// Handle manual dismiss button
				$(document).on("click", ".winp-manual-dismiss-btn", function(e) {
					e.preventDefault();
					var notice = $(this).closest(".winp-admin-notice");
					var noticeId = notice.data("notice-id");
					var nonce = notice.data("nonce");
					
					if (noticeId && nonce && typeof ajaxurl !== "undefined") {
						$(this).prop("disabled", true).text("Dismissing...");
						
						$.post(ajaxurl, {
							action: "winp_dismiss_notice",
							notice_id: noticeId,
							nonce: nonce
						}).done(function() {
							notice.fadeOut(300, function() {
								$(this).remove();
							});
						}).fail(function() {
							alert("Failed to dismiss notice. Please try again.");
							$(this).prop("disabled", false).text("Dismiss Notice");
						});
					}
				});
			})(jQuery);'
		);
	}

	/**
	 * Display admin notices
	 * 
	 * @return void
	 */
	public function display_notices() {
		if ( ! WINP_Plugin::app()->current_user_car() ) {
			return;
		}

		$current_screen = get_current_screen();

		// Display all registered notices.
		foreach ( self::$notices as $notice ) {
			// Check if notice should be shown on current screen.
			if ( ! empty( $notice['where'] ) && $current_screen ) {
				if ( ! in_array( $current_screen->base, $notice['where'] ) ) {
					continue;
				}
			}

			// Check if notice is dismissed.
			if ( $notice['dismissible'] && $this->is_notice_dismissed( $notice['id'] ) ) {
				continue;
			}

			$this->render_notice( $notice );
		}
	}

	/**
	 * Render a single notice
	 *
	 * @param array<string, mixed> $notice Notice data.
	 * 
	 * @return void
	 */
	private function render_notice( $notice ) {
		$type         = $notice['type'];
		$dismissible  = $notice['dismissible'] ? 'is-dismissible' : '';
		$notice_class = "notice notice-{$type} {$dismissible}";
		
		$data_attrs = '';
		if ( $notice['dismissible'] ) {
			$data_attrs = sprintf( 
				'data-notice-id="%s" data-nonce="%s"', 
				esc_attr( $notice['id'] ),
				wp_create_nonce( 'winp_dismiss_notice_' . $notice['id'] )
			);
		}

		printf(
			'<div class="%s winp-admin-notice" %s>%s</div>',
			esc_attr( $notice_class ),
			$data_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$notice['text'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Check if notice is dismissed
	 *
	 * @param string $notice_id Notice ID.
	 * 
	 * @return bool
	 */
	private function is_notice_dismissed( $notice_id ) {
		$dismissed_notices = get_option( 'winp_dismissed_notices', [] );
		
		if ( ! is_array( $dismissed_notices ) || ! isset( $dismissed_notices[ $notice_id ] ) ) {
			return false;
		}
		
		$expires = (int) $dismissed_notices[ $notice_id ];
		
		// If expires is 0, dismissed forever.
		// If expires is set and still in future, still dismissed.
		return 0 === $expires || $expires > time();
	}

	/**
	 * Handle AJAX dismiss request
	 * 
	 * @return void
	 */
	public function ajax_dismiss_notice() {
		$notice_id = isset( $_POST['notice_id'] ) ? sanitize_key( $_POST['notice_id'] ) : '';
		$nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! $notice_id || ! wp_verify_nonce( $nonce, 'winp_dismiss_notice_' . $notice_id ) ) {
			wp_send_json_error();
		}

		if ( ! WINP_Plugin::app()->current_user_car() ) {
			wp_send_json_error();
		}

		// Find the notice to get its expiration time.
		$expire_time = 0; // Default: dismiss forever.
		foreach ( self::$notices as $notice ) {
			if ( $notice['id'] === $notice_id ) {
				$expire_time = $notice['dismiss_expires'] > 0 ? time() + $notice['dismiss_expires'] : 0;
				break;
			}
		}
		
		// Get existing dismissed notices.
		$dismissed_notices = get_option( 'winp_dismissed_notices', [] );
		if ( ! is_array( $dismissed_notices ) ) {
			$dismissed_notices = [];
		}
		
		// Clean up expired dismissals.
		foreach ( $dismissed_notices as $id => $expires ) {
			if ( 0 !== $expires && $expires < time() ) {
				unset( $dismissed_notices[ $id ] );
			}
		}
		
		// Limit size to prevent excessive growth (keep last 10 dismissals).
		if ( count( $dismissed_notices ) > 10 ) {
			// Sort by expiration time (oldest first).
			asort( $dismissed_notices );
			// Remove oldest entries beyond limit.
			$dismissed_notices = array_slice( $dismissed_notices, -10, null, true );
		}
		
		// Add this dismissal.
		$dismissed_notices[ $notice_id ] = $expire_time;
		
		// Update option.
		update_option( 'winp_dismissed_notices', $dismissed_notices, false );

		wp_send_json_success();
	}

	/**
	 * Add notice to be displayed
	 *
	 * @param string        $id              ID of the notice.
	 * @param string|null   $message         Message of the notice.
	 * @param string        $type            Type of the notice. Possible values: 'error', 'warning', 'success', 'info'.
	 * @param bool          $dismissible     Whether the notice can be dismissed.
	 * @param int           $dismiss_expires Time in seconds, after which the notice will be shown again. 0 - forever.
	 * @param array<string> $where           Where to show the notice. Screen base values: 'post', 'post-new', 'edit', etc.
	 * 
	 * @return void
	 */
	public static function add_notice( $id, $message, $type = 'warning', $dismissible = true, $dismiss_expires = 0, $where = [] ) {
		if ( is_null( $message ) || empty( $message ) ) {
			return;
		}

		// Prevent duplicate notices.
		foreach ( self::$notices as $notice ) {
			if ( $notice['id'] === self::$prefix . $id ) {
				return;
			}
		}

		self::$notices[] = [
			'id'              => self::$prefix . $id,
			'type'            => $type,
			'dismissible'     => (bool) $dismissible,
			'dismiss_expires' => (int) $dismiss_expires,
			'where'           => $where,
			'text'            => $message,
		];
	}
}
