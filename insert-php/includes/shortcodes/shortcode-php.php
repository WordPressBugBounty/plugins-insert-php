<?php
/**
 * Php Shortcode
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_SnippetShortcodePhp extends WINP_SnippetShortcode {

	public $shortcode_name = 'wbcr_php_snippet';

	/**
	 * Content render
	 *
	 * @param array  $attr    Shortcode attributes.
	 * @param string $content Enclosed shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return mixed Rendered snippet output, if any.
	 */
	public function html( $attr, $content, $tag ) {
		$id = $this->get_snippet_id( $attr, WINP_SNIPPET_TYPE_PHP );

		if ( ! $id ) {
			if ( current_user_can( 'manage_options' ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				/* translators: %s: Shortcode tag name */
				echo '<span style="color:red">' . sprintf( esc_html__( '[%s]: PHP snippets error (not passed the snippet ID)', 'insert-php' ), esc_html( $tag ) ) . '</span>';
			}

			return;
		}

		$snippet      = get_post( $id );
		$snippet_meta = get_post_meta( $id, '' );

		if ( ! $snippet || empty( $snippet_meta ) ) {
			return;
		}

		$attr = $this->filter_attributes( $attr, $id );

		// Let users pass arbitrary variables, through shortcode attributes.
		// @since 2.0.5
		extract( $attr, EXTR_SKIP );

		$is_activate     = $this->get_snippet_activate( $snippet_meta );
		$snippet_scope   = $this->get_snippet_scope( $snippet_meta );
		$snippet_content = $this->get_snippet_content( $snippet, $snippet_meta, $id );

		if ( ! $is_activate || empty( $snippet_content ) || $snippet_scope != 'shortcode' || WINP_Helper::is_safe_mode() ) {
			return;
		}

		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
			if ( is_user_logged_in() && WINP_Plugin::app()->current_user_car() ) {
				echo esc_html__( 'This Woody snippet cannot run because unfiltered HTML insertion is disabled.', 'insert-php' );
			}

			return;
		}

		// Track shortcode execution.
		WINP_Plugin::app()->get_execute_object()->track_shortcode_snippet( $id );

		// Initialize error handler.
		WINP_Error_Handler::init();

		// Set current snippet context for error handler.
		WINP_Error_Handler::set_current_snippet( $id, $snippet->post_title, $snippet_content );

		$buffer_level = ob_get_level();
		ob_start();

		try {
			eval( $snippet_content );

			// Preserve output from buffers opened by the snippet itself.
			while ( ob_get_level() > $buffer_level + 1 ) {
				ob_end_flush();
			}

			$snippet_output = ob_get_clean();
			return false !== $snippet_output ? $snippet_output : '';
		} catch ( Throwable $exception ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			return WINP_Error_Handler::handle_exception( $exception );
		} finally {
			// Clear snippet context after execution.
			WINP_Error_Handler::clear_current_snippet();
		}
	}
}
