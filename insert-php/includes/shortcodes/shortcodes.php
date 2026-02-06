<?php
/**
 * A base shortcode for all snippets
 *
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base shortcode class for all snippet shortcodes
 */
class WINP_SnippetShortcode {

	/**
	 * Plugin instance
	 *
	 * @var WINP_Plugin
	 */
	public $plugin;

	/**
	 * Shortcode name(s)
	 *
	 * @var string|array<string>
	 */
	public $shortcode_name = 'wbcr_php_snippet';

	/**
	 * Includes assets in header
	 *
	 * @var bool
	 */
	public $assets_in_header = true;

	/**
	 * Constructor
	 *
	 * @param WINP_Plugin $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Ensure shortcode_name is an array.
		if ( ! is_array( $this->shortcode_name ) ) {
			$this->shortcode_name = [ $this->shortcode_name ];
		}

		// Register shortcode(s) with WordPress.
		foreach ( $this->shortcode_name as $name ) {
			if ( ! empty( $name ) ) {
				add_shortcode( $name, [ $this, 'render' ] );
			}
		}

		// Enqueue assets in header if needed.
		if ( $this->assets_in_header ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueue assets if needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Override in child classes if needed.
	}

	/**
	 * Shortcode render callback.
	 *
	 * @param array<string, mixed> $attr    Shortcode attributes.
	 * @param string|null          $content Shortcode content.
	 * @param string               $tag     Shortcode tag.
	 *
	 * @return string
	 */
	public function render( $attr, $content, $tag ) {
		ob_start();
		$this->html( $attr, $content ?? '', $tag );
		$html = ob_get_clean();
		return false !== $html ? $html : '';
	}

	/**
	 * Filter attributes
	 *
	 * @param array<string, mixed> $attr    Shortcode attributes.
	 * @param int                  $post_id Post ID.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_attributes( $attr, $post_id ) {
		if ( ! empty( $attr ) ) {
			$available_tags = WINP_Helper::getMetaOption( $post_id, 'snippet_tags', null );

			if ( ! empty( $available_tags ) ) {
				$available_tags = explode( ',', $available_tags );
				$available_tags = array_map( 'trim', $available_tags );
			}

			foreach ( $attr as $name => $value ) {
				$is_allow_attr = in_array( $name, [ 'id', 'title' ] );
				$validate_name = preg_match( '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/', $name );

				if ( ! $is_allow_attr && ( ( ! empty( $available_tags ) && ! in_array( $name, $available_tags ) ) || ! $validate_name ) ) {
					unset( $attr[ $name ] );
				} else {
					// issue PCS-1
					// before sending the value to the shortcode, using encodeURIComponent(val).replace(/\./g, ‘%2E’); fixes the issue. Will the next update stop this from working?
					$value = urldecode( $value );

					// Remove script tag
					$value = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $value );

					// Remove any attribute starting with "on" or xmlns
					$value = preg_replace( '#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $value );

					// Remove javascript: and vbscript: protocols
					$value = preg_replace( '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $value );
					$value = preg_replace( '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $value );
					$value = preg_replace( '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $value );

					// Filter value
					if ( version_compare( phpversion(), '7.3.0', '>=' ) ) {
						$filter = FILTER_SANITIZE_ADD_SLASHES;
					} else {
						$filter = FILTER_SANITIZE_MAGIC_QUOTES;
					}
					$value         = filter_var( $value, FILTER_SANITIZE_SPECIAL_CHARS );
					$attr[ $name ] = filter_var( $value, $filter );
				}
			}
		}

		return $attr;
	}

	/**
	 * Get snippet id
	 *
	 * @param array<string, mixed> $attr Shortcode attributes.
	 * @param string               $type Snippet type.
	 *
	 * @return int|null
	 */
	public function get_snippet_id( $attr, $type ) {
		$id = isset( $attr['id'] ) ? (int) $attr['id'] : null;

		$snippet_type = null;

		// Only resolve snippet type when a valid (truthy) ID is provided to avoid
		// unnecessary request parsing or database lookups for invalid IDs.
		if ( $id ) {
			$snippet_type = WINP_Helper::get_snippet_type( $id );

			// Security: Reject if get_snippet_type() returned false (invalid post type)
			// or if the snippet type doesn't match the expected type.
			if ( false === $snippet_type || $snippet_type !== $type ) {
				$id = 0;
			}
		}

		return $id;
	}

	/**
	 * Get snippet activate
	 *
	 * @param array<string, mixed> $snippet_meta Snippet metadata.
	 *
	 * @return bool
	 */
	public function get_snippet_activate( $snippet_meta ) {
		// WPML Compatibility.
		if ( defined( 'WPML_PLUGIN_FILE' ) ) {
			$wpml_langs = isset( $snippet_meta['wbcr_inp_snippet_wpml_lang'][0] ) ? $snippet_meta['wbcr_inp_snippet_wpml_lang'][0] : '';
			if ( $wpml_langs !== '' && defined( 'ICL_LANGUAGE_CODE' ) ) {
				if ( ! in_array( ICL_LANGUAGE_CODE, explode( ',', $wpml_langs ) ) ) {
					return false;
				}
			}
		}

		return isset( $snippet_meta['wbcr_inp_snippet_activate'] ) && $snippet_meta['wbcr_inp_snippet_activate'][0];
	}

	/**
	 * Get snippet scope
	 *
	 * @param array<string, mixed> $snippet_meta Snippet metadata.
	 *
	 * @return string|null
	 */
	public function get_snippet_scope( $snippet_meta ) {
		return isset( $snippet_meta['wbcr_inp_snippet_scope'] ) ? $snippet_meta['wbcr_inp_snippet_scope'][0] : null;
	}

	/**
	 * Get snippet content
	 *
	 * @param WP_Post              $snippet      Snippet post object.
	 * @param array<string, mixed> $snippet_meta Snippet metadata.
	 * @param int                  $id           Snippet ID.
	 *
	 * @return string|null
	 */
	public function get_snippet_content( $snippet, $snippet_meta, $id ) {
		$snippet_code = WINP_Helper::get_snippet_code( $snippet );

		if ( get_option( 'wbcr_inp_execute_shortcode' ) ) {
			$snippet_code = do_shortcode( $snippet_code );
		}

		return WINP_Plugin::app()->get_execute_object()->prepareCode( $snippet_code, $id );
	}

	/**
	 * Content render
	 *
	 * @param array<string, mixed> $attr    Shortcode attributes.
	 * @param string               $content Shortcode content.
	 * @param string               $tag     Shortcode tag.
	 *
	 * @return void
	 */
	public function html( $attr, $content, $tag ) {
	}
}
