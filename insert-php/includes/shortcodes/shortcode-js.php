<?php
/**
 * Universal Shortcode
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_SnippetShortcodeJs extends WINP_SnippetShortcode {

	public $shortcode_name = 'wbcr_js_snippet';

	/**
	 * Content render
	 *
	 * @param array  $attr
	 * @param string $content
	 * @param string $tag
	 */
	public function html( $attr, $content, $tag ) {
		$id = $this->get_snippet_id( $attr, WINP_SNIPPET_TYPE_JS );

		if ( ! $id ) {
			/* translators: %s: Shortcode tag name */
			echo '<span style="color:red">' . sprintf( esc_html__( '[%s]: PHP snippets error (not passed the snippet ID)', 'insert-php' ), esc_html( $tag ) ) . '</span>';

			return;
		}

		$snippet      = get_post( $id );
		$snippet_meta = get_post_meta( $id, '' );

		if ( ! $snippet || empty( $snippet_meta ) ) {
			return;
		}

		$attrs = $this->filter_attributes( $attr, $id );

		// Let users pass arbitrary variables, through shortcode attributes.
		// @since 2.4.0
		$vars = '';
		foreach ( $attrs as $var => $value ) {
			$vars .= PHP_EOL . "var {$var} = \"{$value}\";";
		}


		$is_activate     = $this->get_snippet_activate( $snippet_meta );
		$snippet_content = $this->get_snippet_content( $snippet, $snippet_meta, $id );
		$snippet_scope   = $this->get_snippet_scope( $snippet_meta );
		$is_condition    = WINP_Plugin::app()->get_execute_object()->checkCondition( $id );

		if ( ! $is_activate || empty( $snippet_content ) || $snippet_scope != 'shortcode' || ! $is_condition ) {
			return;
		}

		// Track shortcode execution.
		WINP_Plugin::app()->get_execute_object()->track_shortcode_snippet( $id );

		echo "<script type='text/javascript'>{$vars}</script>"; // print attributes
		echo WINP_Execute_Snippet::getJsCssSnippetData( $id );
	}
}
