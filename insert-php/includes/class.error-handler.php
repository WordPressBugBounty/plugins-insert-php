<?php
/**
 * Error handler for PHP snippets.
 *
 * @package WP_Plugin_Insert_PHP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Error_Handler class
 */
class WINP_Error_Handler {

	/**
	 * Holds the current snippet context for error handling.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $current_snippet = null;

	/**
	 * Tracks whether the error handler has been initialized.
	 *
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Initialize error handler.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		add_filter( 'wp_php_error_message', [ __CLASS__, 'customize_error_message' ], 9, 1 );
		self::$initialized = true;
	}

	/**
	 * Set the current snippet context for error handling.
	 *
	 * @param int    $snippet_id Snippet ID.
	 * @param string $title Snippet title.
	 * @param string $content Optional snippet content. Accepted for backward compatibility and ignored.
	 *
	 * @return void
	 */
	public static function set_current_snippet( $snippet_id, $title, $content = null ) {
		self::$current_snippet = [
			'id'      => $snippet_id,
			'title'   => $title,
			'content' => $content,
		];
	}

	/**
	 * Clear the current snippet context after execution.
	 *
	 * @return void
	 */
	public static function clear_current_snippet() {
		self::$current_snippet = null;
	}

	/**
	 * Customize the error message displayed.
	 *
	 * @param string $message Original error message.
	 * @return string Modified error message.
	 */
	public static function customize_error_message( $message ) {
		$error = error_get_last();

		if ( ! $error || ! self::$current_snippet ) {
			return $message;
		}

		if ( ! self::is_eval_error( $error['file'] ) ) {
			return $message;
		}

		$snippet_title = self::$current_snippet['title'];
		$error_message = self::strip_stack_trace( $error['message'] );
		$error_line    = (int) $error['line'];

		// Build the custom error display.
		$custom_message = self::build_error_display( $snippet_title, $error_message, $error_line );

		return $message . $custom_message;
	}

	/**
	 * Check if error file indicates eval'd code.
	 * 
	 * @param string $file Error file path.
	 * @return bool True if error is from eval'd code.
	 */
	private static function is_eval_error( $file ) {
		return strpos( $file, "eval()'d code" ) !== false || 
				strpos( $file, 'eval()\'d code' ) !== false;
	}

	/**
	 * Strip stack trace from error message.
	 * 
	 * @param string $error_message Raw error message that may contain stack trace.
	 * @return string Clean error message without stack trace.
	 */
	private static function strip_stack_trace( $error_message ) {
		// Remove file path and eval() references.
		$error_message = preg_replace( '/\s+in\s+(?:\/|[A-Z]:[\/\\\\]).*?eval\(\)\'d code:\d+/i', '', $error_message );

		// Remove generic "in /path/to/file" references.
		if ( $error_message ) {
			$error_message = preg_replace( '/\s+in\s+(?:\/[^\s]+|[A-Z]:[\/\\\\][^\s]+)/i', '', $error_message );
		}
		
		$patterns = [
			'/\s*Stack trace:.*/is',
			'/\s*#\d+\s+.*/is',
			'/\s*thrown in .*/is',
		];

		foreach ( $patterns as $pattern ) {
			if ( $error_message ) {
				$error_message = preg_replace( $pattern, '', $error_message );
			}
		}

		if ( $error_message ) {
			$error_message = trim( $error_message );
		}

		return $error_message ? $error_message : '';
	}

	/**
	 * Build the custom error display HTML.
	 *
	 * @param string $snippet_title Snippet title.
	 * @param string $error_message Error message.
	 * @param int    $error_line Line number where error occurred.
	 * @return string|false HTML for error display.
	 */
	private static function build_error_display( $snippet_title, $error_message, $error_line ) {
		$title = $snippet_title
						? sprintf(
							// translators: %s is snippet title.
							esc_html__( 'Snippet: %s', 'insert-php' ),
							$snippet_title 
						)
						: '';

		$line_info = $error_line
						? sprintf(
							// translators: %d is line number.
							esc_html__( 'Line %d', 'insert-php' ),
							$error_line 
						)
						: '';

		$full_error_text = sprintf(
			"%s\n%s: %s",
			$title,
			$line_info,
			$error_message
		);

		ob_start();
		?>
		<style>
			.winp-error-container {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-left: 4px solid #d63638;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				margin: 20px 0;
				padding: 20px;
			}
			.winp-error-title {
				color: #d63638;
				font-size: 18px;
				font-weight: 600;
				margin: 0 0 15px 0;
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.winp-error-icon {
				width: 24px;
				height: 24px;
			}
			.winp-error-details {
				position: relative;
				background: #f6f7f7;
				border: 1px solid #dcdcde;
				border-radius: 4px;
				padding: 15px;
				margin: 15px 0;
			}
			.winp-error-row {
				margin: 10px 0;
				line-height: 1.6;
			}
			.winp-error-label {
				font-weight: 600;
				color: #1d2327;
				display: inline-block;
				min-width: 80px;
			}
			.winp-error-value {
				color: #2c3338;
				font-family: Consolas, Monaco, monospace;
			}
			.winp-error-message {
				color: #d63638;
				font-weight: 500;
				flex: 1;
			}
			.winp-copy-icon {
				position: absolute;
				top: 15px;
				right: 15px;
				flex-shrink: 0;
				cursor: pointer;
				padding: 4px;
				border-radius: 3px;
				transition: background 0.2s;
				display: inline-flex;
				align-items: center;
				justify-content: center;
			}
			.winp-copy-icon:hover {
				background: #e0e0e0;
			}
			.winp-copy-icon svg {
				width: 18px;
				height: 18px;
			}
			.winp-copy-icon.copied {
				background: #d5f5d5;
			}
			.winp-error-line {
				color: #d63638;
				font-weight: 600;
				font-size: 16px;
			}
		</style>

		<div class="winp-error-container">
			<h2 class="winp-error-title">
				<svg class="winp-error-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<circle cx="12" cy="12" r="10" stroke="#d63638" stroke-width="2"/>
					<path d="M12 8v4m0 4h.01" stroke="#d63638" stroke-width="2" stroke-linecap="round"/>
				</svg>
				<?php esc_html_e( 'PHP Snippet Error', 'insert-php' ); ?>
			</h2>

			<div class="winp-error-details">
				<div class="winp-error-row">
					<span class="winp-error-label"><?php esc_html_e( 'Snippet:', 'insert-php' ); ?></span>
					<span class="winp-error-value">
						<?php echo esc_html( $snippet_title ); ?>
					</span>
				</div>

				<?php if ( $error_line ) : ?>
					<div class="winp-error-row">
						<span class="winp-error-label"><?php esc_html_e( 'Line:', 'insert-php' ); ?></span>
						<span class="winp-error-line"><?php echo esc_html( (string) $error_line ); ?></span>
					</div>
				<?php endif; ?>

				<div class="winp-error-row">
					<span class="winp-error-label"><?php esc_html_e( 'Error:', 'insert-php' ); ?></span>
					<span class="winp-error-value winp-error-message"><?php echo esc_html( $error_message ); ?></span>
				</div>
				<span class="winp-copy-icon" onclick="copyErrorToClipboard()" title="<?php esc_attr_e( 'Copy error details', 'insert-php' ); ?>" id="winp-copy-icon">
					<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="9" y="9" width="13" height="13" rx="2" stroke="#2c3338" stroke-width="2"/>
						<path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke="#2c3338" stroke-width="2"/>
					</svg>
				</span>
			</div>
		</div>

		<script>
			function copyErrorToClipboard() {
				const errorText = <?php echo wp_json_encode( $full_error_text ); ?>;
				const icon = document.getElementById('winp-copy-icon');
				
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(errorText).then(function() {
						showCopySuccess(icon);
					}).catch(function(err) {
						fallbackCopy(errorText, icon);
					});
				} else {
					fallbackCopy(errorText, icon);
				}
			}

			function fallbackCopy(text, icon) {
				const textarea = document.createElement('textarea');
				textarea.value = text;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild(textarea);
				textarea.select();
				try {
					document.execCommand('copy');
					showCopySuccess(icon);
				} catch (err) {
					alert(<?php echo wp_json_encode( __( 'Failed to copy. Please copy manually.', 'insert-php' ) ); ?>);
				}
				document.body.removeChild(textarea);
			}

			function showCopySuccess(icon) {
				icon.classList.add('copied');
				const originalTitle = icon.getAttribute('title');
				icon.setAttribute('title', <?php echo wp_json_encode( __( 'Copied!', 'insert-php' ) ); ?>);
				
				// Change icon to checkmark
				icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6L9 17l-5-5" stroke="#007017" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
				
				setTimeout(function() {
					icon.classList.remove('copied');
					icon.setAttribute('title', originalTitle);
					// Restore clipboard icon
					icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="9" width="13" height="13" rx="2" stroke="#2c3338" stroke-width="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke="#2c3338" stroke-width="2"/></svg>';
				}, 2000);
			}
		</script>
		<?php
		return ob_get_clean();
	}
}
