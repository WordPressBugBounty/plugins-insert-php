<?php
/**
 * Text Shortcode
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_SnippetShortcodeText extends WINP_SnippetShortcode {

	public $shortcode_name = 'wbcr_text_snippet';

	/**
	 * Content render
	 *
	 * @param array  $attr
	 * @param string $content
	 * @param string $tag
	 */
	public function html( $attr, $content, $tag ) {
		$id = $this->get_snippet_id( $attr, WINP_SNIPPET_TYPE_TEXT );

		if ( ! $id ) {
			/* translators: %s: Shortcode tag name */
			echo '<span style="color:red">' . sprintf( esc_html__( '[%s]: Text snippets error (not passed the snippet ID)', 'insert-php' ), esc_html( $tag ) ) . '</span>';

			return;
		}

		$snippet      = get_post( $id );
		$snippet_meta = get_post_meta( $id, '' );

		if ( ! $snippet || empty( $snippet_meta ) ) {
			return;
		}

		$is_activate   = $this->get_snippet_activate( $snippet_meta );
		$snippet_scope = $this->get_snippet_scope( $snippet_meta );
		$is_condition  = WINP_Plugin::app()->get_execute_object()->checkCondition( $id );

		if ( ! $is_activate || $snippet_scope != 'shortcode' || ! $is_condition ) {
			return;
		}

		// Track shortcode execution.
		WINP_Plugin::app()->get_execute_object()->track_shortcode_snippet( $id );

		$post_content = $snippet->post_content;
		if ( get_option( 'wbcr_inp_execute_shortcode' ) ) {
			$post_content = do_shortcode( $post_content );
		}

		/**
		 * Shortcode content filter
		 *
		 * @since 2.4.4
		 */
		$post_content = apply_filters( 'wbcr/inp/snippet/shortcode_text/post_content', $post_content, $id );

		echo str_replace( '{{SNIPPET_CONTENT}}', $content, $post_content );
	}
}
