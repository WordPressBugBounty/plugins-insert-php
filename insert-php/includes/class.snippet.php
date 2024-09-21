<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_Snippet {

	protected $id;

	protected $type;

	public function __construct(WP_Post $snippet) {
		$this->id = $snippet->ID;

		//todo: to refactor
		$this->type = WINP_Helper::get_snippet_type( $this->id );

	}

	public static function get_snippet( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $post_id ) {
			$snippet = get_post( $post_id );

			if($snippet) {
				return new self($snippet);
			}
		}

		return null;
	}

	public function is_allowed() {
		// If the user has prohibited the insertion of unfiltered HTML,
		// we prohibit the execution of snippets.
		if ( ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML )
		     && ! in_array( $this->type, [
				WINP_SNIPPET_TYPE_TEXT,
				WINP_SNIPPET_TYPE_AD,
				WINP_SNIPPET_TYPE_CSS
			] ) ) {
			return false;
		}

		return true;
	}

	public function get_meta($key) {
		return get_post_meta( $this->id, WINP_Plugin::app()->getPrefix() . $key, true );
	}

	public function get_scope() {
		return $this->get_meta('snippet_scope');

	}

	public function get_content() {
		return $this->get_meta('snippet_code');
	}

	public function is_active() {
		// WPML Compatibility
		if ( defined( 'WPML_PLUGIN_FILE' ) ) {
			$wpml_langs = $this->get_meta('snippet_wpml_lang');

			if ( $wpml_langs !== '' && defined( 'ICL_LANGUAGE_CODE' ) ) {
				if ( ! in_array( ICL_LANGUAGE_CODE, explode( ',', $wpml_langs ) ) ) {
					return false;
				}
			}
		}

		//todo: протестировать
		return $this->get_meta('snippet_activate');
	}
}