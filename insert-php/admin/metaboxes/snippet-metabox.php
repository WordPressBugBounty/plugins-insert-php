<?php
/**
 * Cloud Sync Metabox
 * 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud Sync Metabox
 */
class WINP_Snippet_MetaBox {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action(
			'add_meta_boxes',
			function () {
				add_meta_box(
					'winp-cloud-sync',
					__( 'Sync to Cloud Library', 'insert-php' ),
					[ $this, 'render_cloud_sync' ],
					WINP_SNIPPETS_POST_TYPE,
					'side',
					'default'
				);

				add_meta_box(
					'winp-base-options',
					__( 'General Options', 'insert-php' ),
					[ $this, 'render_base_options' ],
					WINP_SNIPPETS_POST_TYPE,
					'normal',
					'default'
				);

				$snippet_type = WINP_Helper::get_snippet_type();

				if ( WINP_SNIPPET_TYPE_PHP !== $snippet_type ) {
					add_meta_box(
						'winp-snippet-conditions',
						__( 'Display Conditions', 'insert-php' ),
						[ $this, 'render_snippet_conditions' ],
						WINP_SNIPPETS_POST_TYPE,
						'normal',
						'default'
					);
				}

				add_meta_box(
					'winp-code-revisions',
					__( 'Code Revisions', 'insert-php' ),
					[ $this, 'render_code_revisions' ],
					WINP_SNIPPETS_POST_TYPE,
					'normal',
					'default'
				);
			}
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'deregister_default_editor_resourses' ] );

		add_action( 'admin_head', [ $this, 'remove_media_button' ] );
		add_filter( 'wp_default_editor', [ $this, 'set_default_editor' ] );
		add_action( 'admin_footer-post.php', [ $this, 'print_code_editor_scripts' ], 99 );
		add_action( 'admin_footer-post-new.php', [ $this, 'print_code_editor_scripts' ], 99 );
		add_action( 'edit_form_after_editor', [ $this, 'php_editor_markup' ], 10, 1 );

		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
		add_action( 'edit_form_top', [ $this, 'edit_form_top' ] );
		add_action( 'post_submitbox_misc_actions', [ $this, 'post_submitbox_show_shortcode' ] );
		add_action( 'edit_form_after_title', [ $this, 'keep_html_entities' ] );

		add_filter( 'pre_post_content', [ $this, 'stop_post_filters' ] );
		add_filter( 'content_save_pre', [ $this, 'init_post_filters' ], 9999 );

		add_action( 'save_post', [ $this, 'on_saving_snippet' ], 11 );
		add_action( 'save_post', [ $this, 'after_saving_snippet' ], 15 );
	}

	/**
	 * Enqueue modal assets
	 * 
	 * @param string $hook Hook suffix.
	 * 
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || WINP_SNIPPETS_POST_TYPE !== $screen->post_type ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$asset_file = include WINP_PLUGIN_DIR . '/admin/assets/metabox/build/index.asset.php';

		wp_enqueue_style(
			'winp-metabox-styles',
			WINP_PLUGIN_URL . '/admin/assets/metabox/build/style-index.css',
			[],
			$asset_file['version']
		);

		wp_enqueue_script(
			'winp-metabox-scripts',
			WINP_PLUGIN_URL . '/admin/assets/metabox/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'winp-metabox-scripts', 'insert-php' );

		$api_object = WINP_Plugin::app()->get_api_object();
		$is_changed = $api_object->is_key() ? $api_object->is_changed( $post_id ) === true : false;

		// Get revisions data.
		$revisions_data = $this->get_revisions_data( $post_id );

		// Get snippet filters for conditions.
		$snippet_filters = get_post_meta( $post_id, 'wbcr_inp_snippet_filters', true );
		$changed_filters = get_post_meta( $post_id, 'wbcr_inp_changed_filters', true );

		// Get grouped filter params for conditions dropdown.
		$grouped_filter_params = $this->get_grouped_filter_params();

		// Get location options for base-options dropdown.
		$location_options = $this->get_location_options();

		// Get WPML languages if available.
		$wpml_languages = [];
		if ( defined( 'WPML_PLUGIN_FILE' ) ) {
			$wpml = apply_filters( 'wpml_active_languages', null, null );
			if ( is_array( $wpml ) ) {
				foreach ( $wpml as $item ) {
					$wpml_languages[] = [
						'code' => $item['code'],
						'name' => $item['native_name'],
						'flag' => $item['country_flag_url'],
					];
				}
			}
		}

		wp_localize_script(
			'winp-metabox-scripts',
			'winpMetaboxData',
			[
				'snippetId'            => $post_id,
				'isPremium'            => $api_object->is_key(),
				'isChanged'            => $is_changed,
				'upgradeUrl'           => tsdk_utmify( WINP_UPGRADE, 'cloud_sync_metabox', 'upgrade_button' ),
				'revisions'            => $revisions_data['revisions'],
				'deleteNonce'          => wp_create_nonce( 'winp_rev_delete' ),
				'restoreNonces'        => $revisions_data['restoreNonces'],
				'snippetFilters'       => ! empty( $snippet_filters ) ? $snippet_filters : [],
				'changedFilters'       => ! empty( $changed_filters ) ? intval( $changed_filters ) : 0,
				'groupedFiltersParams' => $grouped_filter_params,
				'locationOptions'      => $location_options,
				'wpmlLanguages'        => $wpml_languages,
			]
		);

		// Enqueue code editor styles.
		wp_enqueue_style( 'winp-ccm', WINP_PLUGIN_URL . '/admin/assets/dist/css/ccm.min.css', [], WINP_PLUGIN_VERSION );
		wp_enqueue_style( 'winp-code-editor-style', WINP_PLUGIN_URL . '/admin/assets/css/code-editor-style.css', [], WINP_PLUGIN_VERSION );
		wp_enqueue_style( 'winp-snippet-edit', WINP_PLUGIN_URL . '/admin/assets/css/snippet-edit.css', [], WINP_PLUGIN_VERSION );

		$code_editor_theme = get_option( 'wbcr_inp_code_editor_theme' );

		if ( ! empty( $code_editor_theme ) && 'default' !== $code_editor_theme ) {
			wp_enqueue_style( 'winp-codemirror-theme', WINP_PLUGIN_URL . '/admin/assets/css/cmthemes/' . $code_editor_theme . '.css', [], WINP_PLUGIN_VERSION );
		}

		global $post;

		if ( empty( $post ) || WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}

		$snippet_type = WINP_Helper::get_snippet_type();

		// Enqueue snippet validation script.
		wp_enqueue_script( 'winp-snippet-validation', WINP_PLUGIN_URL . '/admin/assets/js/snippet-validation.js', [ 'jquery' ], WINP_PLUGIN_VERSION, true );
		wp_localize_script(
			'winp-snippet-validation',
			'wbcrInpValidation',
			[
				'nonce'          => wp_create_nonce( 'winp_validate_snippet_' . $post->ID ),
				'postType'       => WINP_SNIPPETS_POST_TYPE,
				'validatingText' => __( 'Validating code...', 'insert-php' ),
				'errorTitle'     => __( 'Code Validation Error', 'insert-php' ),
				'errorText'      => __( 'The code snippet contains errors. Please fix them before publishing.', 'insert-php' ),
			]
		);

		if ( WINP_SNIPPET_TYPE_TEXT === $snippet_type || WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			return;
		}

		$code_editor_mode = 'application/x-httpd-php';
		if ( WINP_SNIPPET_TYPE_PHP === $snippet_type ) {
			$code_editor_mode = 'text/x-php';

			wp_enqueue_script( 'phpparser', WINP_PLUGIN_URL . '/admin/assets/js/codemirror/php-parser.js', [ 'wp-codemirror' ], WINP_PLUGIN_VERSION, true );
			wp_enqueue_script( 'phplint', WINP_PLUGIN_URL . '/admin/assets/js/codemirror/php-lint.js', [ 'wp-codemirror', 'phpparser' ], WINP_PLUGIN_VERSION, true );
		} elseif ( WINP_SNIPPET_TYPE_CSS === $snippet_type ) {
			$code_editor_mode = 'text/css';

			wp_enqueue_script( 'csslint' );
		} elseif ( WINP_SNIPPET_TYPE_JS === $snippet_type ) {
			$code_editor_mode = 'application/javascript';

			wp_enqueue_script( 'esprima' );
			wp_enqueue_script( 'jshint' );
			wp_enqueue_script( 'jsonlint' );
		} elseif ( WINP_SNIPPET_TYPE_HTML === $snippet_type ) {
			$code_editor_mode = 'text/html';

			wp_enqueue_script( 'htmlhint' );
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				wp_enqueue_script( 'htmlhint-kses' );
			}
		}

		$code_editor_theme = get_option( 'wbcr_inp_code_editor_theme' );

		$settings = [
			'type'       => $code_editor_mode,
			'codemirror' => [
				'mode'              => [
					'name'      => $code_editor_mode,
					'startOpen' => WINP_SNIPPET_TYPE_PHP !== $snippet_type,
				],
				'theme'             => ! empty( $code_editor_theme ) && 'default' !== $code_editor_theme ? $code_editor_theme : 'default',
				'matchBrackets'     => true,
				'styleActiveLine'   => true,
				'continueComments'  => true,
				'autoCloseTags'     => true,
				'inputStyle'        => 'contenteditable',
				'direction'         => 'ltr',
				'lint'              => true,
				'gutters'           => [ 'CodeMirror-lint-markers' ],
				'matchTags'         => [
					'bothTags' => true,
				],
				'extraKeys'         => [
					'Ctrl-Space' => 'autocomplete',
					'Ctrl-/'     => 'toggleComment',
					'Cmd-/'      => 'toggleComment',
					'Alt-F'      => 'findPersistent',
					'Ctrl-F'     => 'findPersistent',
					'Cmd-F'      => 'findPersistent',
				],
				'indentWithTabs'    => get_option( 'wbcr_inp_code_editor_indent_with_tabs', true ),
				'tabSize'           => (int) get_option( 'wbcr_inp_code_editor_tab_size', 4 ),
				'indentUnit'        => (int) get_option( 'wbcr_inp_code_editor_indent_size', 4 ),
				'lineNumbers'       => get_option( 'wbcr_inp_code_editor_line_numbers', true ),
				'lineWrapping'      => get_option( 'wbcr_inp_code_editor_wrap_lines', true ),
				'autoCloseBrackets' => get_option( 'wbcr_inp_code_editor_auto_close_brackets', true ),
			],
		];

		if ( get_option( 'wbcr_inp_code_editor_highlight_selection_matches', true ) ) {
			$settings['codemirror']['highlightSelectionMatches'] = [
				'showToken' => true,
				'style'     => 'winp-matchhighlight',
			];
		} else {
			$settings['codemirror']['highlightSelectionMatches'] = false;
		}

		wp_enqueue_code_editor( $settings );

		wp_add_inline_script(
			'wp-codemirror',
			'window.CodeMirror = wp.CodeMirror;'
		);
	}

	/**
	 * Render metabox content
	 * 
	 * @return void
	 */
	public function render_cloud_sync() {
		?>
		<div id="winp-cloud-sync-root"></div>
		<?php
	}

	/**
	 * Render Base Options metabox content
	 * 
	 * @return void
	 */
	public function render_base_options() {
		global $post;
		$snippet_type        = WINP_Helper::get_snippet_type();
		$snippet_scope       = get_post_meta( $post->ID, 'wbcr_inp_snippet_scope', true );
		$custom_name         = get_post_meta( $post->ID, 'wbcr_inp_snippet_custom_name', true );
		$snippet_location    = get_post_meta( $post->ID, 'wbcr_inp_snippet_location', true );
		$snippet_p_number    = get_post_meta( $post->ID, 'wbcr_inp_snippet_p_number', true );
		$snippet_linking     = get_post_meta( $post->ID, 'wbcr_inp_snippet_linking', true );
		$snippet_description = get_post_meta( $post->ID, 'wbcr_inp_snippet_description', true );
		$snippet_tags        = get_post_meta( $post->ID, 'wbcr_inp_snippet_tags', true );
		$snippet_wpml_lang   = get_post_meta( $post->ID, 'wbcr_inp_snippet_wpml_lang', true );
		?>
		<input id="winp_snippet_scope" name="wbcr_inp_snippet_scope" value="<?php echo esc_attr( $snippet_scope ? $snippet_scope : 'shortcode' ); ?>" type="hidden"/>
		<input id="winp_snippet_custom_name" name="wbcr_inp_snippet_custom_name" value="<?php echo esc_attr( $custom_name ); ?>" type="hidden"/>
		<?php if ( WINP_SNIPPET_TYPE_PHP !== $snippet_type ) : ?>
			<input id="winp_snippet_location" name="wbcr_inp_snippet_location" value="<?php echo esc_attr( $snippet_location ? $snippet_location : 'header' ); ?>" type="hidden"/>
			<input id="winp_snippet_p_number" name="wbcr_inp_snippet_p_number" value="<?php echo esc_attr( $snippet_p_number ? $snippet_p_number : '0' ); ?>" type="hidden"/>
		<?php endif; ?>
		<?php if ( WINP_SNIPPET_TYPE_CSS === $snippet_type || WINP_SNIPPET_TYPE_JS === $snippet_type ) : ?>
			<input id="winp_snippet_linking" name="wbcr_inp_snippet_linking" value="<?php echo esc_attr( $snippet_linking ? $snippet_linking : 'external' ); ?>" type="hidden"/>
		<?php endif; ?>
		<input id="winp_snippet_description" name="wbcr_inp_snippet_description" value="<?php echo esc_attr( $snippet_description ); ?>" type="hidden"/>
		<?php if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type ) : ?>
			<input id="winp_snippet_tags" name="wbcr_inp_snippet_tags" value="<?php echo esc_attr( $snippet_tags ); ?>" type="hidden"/>
		<?php endif; ?>
		<?php if ( defined( 'WPML_PLUGIN_FILE' ) ) : ?>
			<input id="winp_snippet_wpml_lang" name="wbcr_inp_snippet_wpml_lang" value="<?php echo esc_attr( $snippet_wpml_lang ); ?>" type="hidden"/>
		<?php endif; ?>
		<div id="winp-base-options-root"></div>
		<?php
	}

	/**
	 * Render Code Revisions metabox content
	 * 
	 * @return void
	 */
	public function render_code_revisions() {
		?>
		<div id="winp-code-revisions-root"></div>
		<?php
	}

	/**
	 * Render Snippet Conditions metabox content
	 * 
	 * @param WP_Post $post Current post object.
	 * 
	 * @return void
	 */
	public function render_snippet_conditions( $post ) {
		?>
		<?php $changed_filters = get_post_meta( $post->ID, 'wbcr_inp_changed_filters', true ); ?>
		<input id="winp_changed_filters" name="wbcr_inp_changed_filters" value="<?php echo empty( $changed_filters ) ? 0 : 1; ?>" type="hidden"/>
		<?php $snippet_filters = get_post_meta( $post->ID, 'wbcr_inp_snippet_filters', true ); ?>
		<input id="winp_visibility_filters" name="wbcr_inp_snippet_filters"
				value='<?php echo ! empty( $snippet_filters ) ? wp_json_encode( $snippet_filters ) : '[]'; ?>'
				type="hidden"/>
		<?php wp_nonce_field( 'wbcr_inp_snippet_' . $post->ID . '_conditions_metabox', 'wbcr_inp_snippet_conditions_metabox_nonce' ); ?>
		<div id="winp-snippet-conditions-root"></div>
		<?php
	}

	/**
	 * Get revisions data for the snippet
	 * 
	 * @param int $post_id Post ID.
	 * 
	 * @return array{revisions: array<int, array{id: int, date: string, author: string, canDelete: bool, canRestore: bool}>, restoreNonces: array<int, string>} Revisions data with nonces.
	 */
	private function get_revisions_data( $post_id ) {
		if ( ! $post_id ) {
			return [
				'revisions'     => [],
				'restoreNonces' => [],
			];
		}

		$revisions = wp_get_post_revisions(
			$post_id,
			[
				'numberposts' => 10,
				'order'       => 'DESC',
				'orderby'     => 'date ID',
			]
		);

		if ( empty( $revisions ) ) {
			return [
				'revisions'     => [],
				'restoreNonces' => [],
			];
		}

		$snippet = get_post( $post_id );
		if ( ! $snippet instanceof WP_Post ) {
			return [
				'revisions'     => [],
				'restoreNonces' => [],
			];
		}

		$user_id               = get_current_user_id();
		$user_can_edit         = current_user_can( 'edit_' . WINP_SNIPPETS_POST_TYPE . 's' );
		$user_can_edit_other   = current_user_can( 'edit_others_' . WINP_SNIPPETS_POST_TYPE . 's' );
		$user_can_delete       = current_user_can( 'delete_' . WINP_SNIPPETS_POST_TYPE );
		$user_can_delete_other = current_user_can( 'delete_others_' . WINP_SNIPPETS_POST_TYPE . 's' );

		$revisions_data = [];
		$restore_nonces = [];

		foreach ( $revisions as $revision ) {
			if ( ! $revision instanceof WP_Post ) {
				continue;
			}

			// Skip autosaves.
			if ( wp_is_post_autosave( $revision ) ) {
				continue;
			}

			$author      = get_userdata( (int) $revision->post_author );
			$can_delete  = ( (int) $revision->post_author === $user_id && $user_can_delete ) || ( (int) $revision->post_author !== $user_id && $user_can_delete_other );
			$can_restore = ( (int) $snippet->post_author === $user_id && $user_can_edit ) || ( (int) $snippet->post_author !== $user_id && $user_can_edit_other );

			$revisions_data[] = [
				'id'         => $revision->ID,
				'date'       => date_i18n( 'M j, Y @ H:i', strtotime( $revision->post_modified ) ),
				'author'     => $author ? $author->display_name : __( 'Unknown', 'insert-php' ),
				'canDelete'  => $can_delete,
				'canRestore' => $can_restore,
			];

			// Generate restore nonce for each revision.
			$restore_nonces[ $revision->ID ] = wp_create_nonce( 'winp_rev_' . $revision->ID . '_restore' );
		}

		return [
			'revisions'     => $revisions_data,
			'restoreNonces' => $restore_nonces,
		];
	}

	/**
	 * Disable post filtering. Snippets code cannot be filtered, otherwise it will cause errors.
	 *
	 * @param mixed $value The content value.
	 *
	 * @return mixed
	 */
	public function stop_post_filters( $value ) {
		global $wbcr__has_kses, $wbcr__has_targeted_link_rel_filters;

		$screen = get_current_screen();
		if ( empty( $screen ) || WINP_SNIPPETS_POST_TYPE !== $screen->post_type ) {
			return $value;
		}

		$snippet_type = WINP_Helper::get_snippet_type();

		if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type ) {
			// Prevent content filters from corrupting JSON in post_content.
			$wbcr__has_kses = ( false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' ) );
			if ( $wbcr__has_kses ) {
				kses_remove_filters();
			}
			$wbcr__has_targeted_link_rel_filters = ( false !== has_filter( 'content_save_pre', 'wp_targeted_link_rel' ) );
			if ( $wbcr__has_targeted_link_rel_filters ) {
				if ( function_exists( 'wp_remove_targeted_link_rel_filters' ) ) {
					// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_remove_targeted_link_rel_filtersFound -- Fallback provided for WP 6.7+
					wp_remove_targeted_link_rel_filters();
				} else {
					remove_filter( 'content_save_pre', 'wp_targeted_link_rel' );
				}
			}
		}

		return $value;
	}

	/**
	 * Enable post filtering.
	 *
	 * @param mixed $value The content value.
	 *
	 * @return mixed
	 */
	public function init_post_filters( $value ) {
		global $wbcr__has_kses, $wbcr__has_targeted_link_rel_filters;

		$screen = get_current_screen();
		if ( empty( $screen ) || WINP_SNIPPETS_POST_TYPE !== $screen->post_type ) {
			return $value;
		}

		if ( $wbcr__has_kses ) {
			kses_init_filters();
		}

		if ( $wbcr__has_targeted_link_rel_filters ) {
			if ( function_exists( 'wp_init_targeted_link_rel_filters' ) ) {
				// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_init_targeted_link_rel_filtersFound -- Fallback provided for WP 6.7+
				wp_init_targeted_link_rel_filters();
			} else {
				add_filter( 'content_save_pre', 'wp_targeted_link_rel' );
			}
		}

		unset( $wbcr__has_kses );
		unset( $wbcr__has_targeted_link_rel_filters );

		return $value;
	}

	/**
	 * Add the codemirror editor in the `post` screen
	 *
	 * @param WP_Post|null $post Current post object.
	 *
	 * @return void
	 */
	public function keep_html_entities( $post ) {
		$current_screen = get_current_screen();

		if ( ! $post || ! $current_screen || ( WINP_SNIPPETS_POST_TYPE !== $current_screen->post_type ) ) {
			return;
		}

		if ( get_option( 'wbcr_inp_keep_html_entities' ) && strstr( $post->post_content, '&' ) ) {

			// First the ampresands.
			$post->post_content = str_replace( '&amp', htmlentities( '&amp' ), $post->post_content );

			// Then the rest of the entities.
			$html_flags = defined( 'ENT_HTML5' ) ? ENT_QUOTES | ENT_HTML5 : ENT_QUOTES;
			$entities   = get_html_translation_table( HTML_ENTITIES, $html_flags );

			unset( $entities[ array_search( '&amp;', $entities ) ] );

			// phpcs:ignore WordPressVIPMinimum.Security.StaticStrreplace.StaticStrreplace -- Used for HTML entity handling, not shell execution
			$regular_expression = str_replace( ';', '', '/(' . implode( '|', $entities ) . ')/i' );

			preg_match_all( $regular_expression, $post->post_content, $matches );

			if ( isset( $matches[0] ) && count( $matches[0] ) > 0 ) {
				foreach ( $matches[0] as $_entity ) {
					$post->post_content = str_replace( $_entity, htmlentities( $_entity ), $post->post_content );
				}
			}
		}
	}

	/**
	 * Remove media button
	 *
	 * @return void
	 */
	public function remove_media_button() {
		global $post;

		if ( empty( $post ) || WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}

		$snippet_type = WINP_Helper::get_snippet_type();
		if ( WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			return;
		}

		remove_action( 'media_buttons', 'media_buttons' );
	}

	/**
	 * Set default editor based on snippet type
	 *
	 * @param string $type Editor type.
	 *
	 * @return string
	 */
	public function set_default_editor( $type ) {
		global $post;

		if ( empty( $post ) || WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return $type;
		}

		$snippet_type = WINP_Helper::get_snippet_type();
		if ( WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			return 'html';
		}

		return $type;
	}

	/**
	 * Deregister other CodeMirror styles
	 *
	 * @return void
	 */
	public function deregister_default_editor_resourses() {
		global $post;

		if ( empty( $post ) || WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}

		/* Remove other CodeMirror styles */
		wp_deregister_style( 'codemirror' );
	}

	/**
	 * Print Code Editor scripts
	 * 
	 * @return void
	 */
	public function print_code_editor_scripts() {
		global $post;

		if ( empty( $post ) || WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}

		$snippet_type = WINP_Helper::get_snippet_type();
		if ( WINP_SNIPPET_TYPE_TEXT === $snippet_type || WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			return;
		}
		?>
		<script>
			/* Loads CodeMirror on the snippet editor */
			(function () {
				var editor = wp.codeEditor.initialize( document.getElementById( 'post_content' ), wp.codeEditor.defaultSettings.codemirror );

				if ( editor && editor.codemirror ) {
					var existingKeys = editor.codemirror.getOption( 'extraKeys' ) || {};
					var newKeys = {};

					for ( var key in existingKeys ) {
						if ( existingKeys.hasOwnProperty( key ) ) {
							newKeys[key] = existingKeys[key];
						}
					}

					// Add new key binding
					newKeys['Ctrl-S'] = function( cm ) {
						document.getElementById( 'publish' ).click();
					};

					newKeys['Cmd-S'] = function( cm ) {
						document.getElementById( 'publish' ).click();
					};

					editor.codemirror.setOption( 'extraKeys', newKeys );
				}
			})();

			jQuery(document).ready(function ($) {
				$('.wp-editor-tabs').remove();
			});
		</script>
		<?php
	}

	/**
	 * Markup PHP snippet editor.
	 *
	 * @param WP_Post $post Post Object.
	 *
	 * @return void
	 */
	public function php_editor_markup( $post ) {

		if ( WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}

		$snippet_type = WINP_Helper::get_snippet_type();
		if ( WINP_SNIPPET_TYPE_TEXT === $snippet_type || WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			return;
		}

		wp_nonce_field( basename( __FILE__ ), WINP_SNIPPETS_POST_TYPE );

		$snippet_code = WINP_Helper::get_snippet_code( $post );

		?>
		<div class="wp-editor-container winp-editor-container">
			<textarea id="post_content" name="post_content" class="wp-editor-area winp-php-content"><?php echo esc_html( $snippet_code ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Adds one or more classes to the body tag in the dashboard.
	 *
	 * @param string $classes Current body classes.
	 *
	 * @return string Altered body classes.
	 */
	public function admin_body_class( $classes ) {
		global $post;

		if ( ! empty( $post ) && WINP_SNIPPETS_POST_TYPE === $post->post_type ) {
			$snippet_type = WINP_Helper::get_snippet_type();

			$new_classes = 'wbcr-inp-snippet-type-' . esc_attr( $snippet_type );

			if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type ) {
				$new_classes .= ' winp-snippet-enabled';
			}

			return ' ' . $new_classes . ' ' . $classes;
		}

		return $classes;
	}

	/**
	 * Add hidden tag to edit post form
	 * Set post title for snippet post with status auto-draft
	 *
	 * @param WP_Post|null $current_post Current post object.
	 *
	 * @return void
	 */
	public function edit_form_top( $current_post ) {
		if ( ! $current_post || WINP_SNIPPETS_POST_TYPE !== $current_post->post_type ) {
			return;
		}

		$snippet_type = WINP_HTTP::get( 'winp_item', WINP_SNIPPET_TYPE_PHP, 'sanitize_key' );
		$snippet_type = WINP_Helper::getMetaOption( $current_post->ID, 'snippet_type', $snippet_type );

		echo '<input type="hidden" id="wbcr_inp_snippet_type" name="wbcr_inp_snippet_type" value="' . esc_attr( $snippet_type ) . '">';

		if ( 'auto-draft' === $current_post->post_status && WINP_Helper::getMetaOption( $current_post->ID, 'snippet_draft', false ) ) {
			global $post;

			$retrieved_post = get_post( $current_post->ID );
			if ( $retrieved_post ) {
				$post->post_title = $retrieved_post->post_title;
			}
		}
	}

	/**
	 * Show shortcode in the publish box
	 *
	 * @param WP_Post|null $post Current post object.
	 *
	 * @return void
	 */
	public function post_submitbox_show_shortcode( $post ) {
		if ( ! $post || ( WINP_SNIPPETS_POST_TYPE !== $post->post_type ) ) {
			return;
		}

		if ( WINP_Helper::getMetaOption( $post->ID, 'snippet_draft', false ) ) {
			return;
		}

		$snippet_scope = WINP_Helper::getMetaOption( $post->ID, 'snippet_scope' );
		$value         = '';
		$shortcode     = '';

		if ( 'shortcode' === $snippet_scope ) {
			$shortcode = WINP_Helper::get_where_use_text( $post );
		} else {
			$value     = WINP_Helper::get_where_use_text( $post );
			$shortcode = WINP_Helper::get_shortcode_text( $post );
		}
		echo "<div class='wbcr_inp_shortcode_input_container'><label for='wbcr_inp_shortcode_input'>" . esc_html__( 'Shortcode:', 'insert-php' ) . '</label>';
		echo "<div style='position: relative; display: inline-flex; align-items: center; width: 100%;'>";
		echo "<span class='dashicons dashicons-clipboard' style='position: absolute; top:10px; left: 8px; pointer-events: none; color: #2271b1;'></span>";
		echo "<input type='text' name='wbcr_inp_shortcode_input' class='wbcr_inp_shortcode_input' value='" . esc_attr( $shortcode ) . "' readonly='readonly' style='cursor: pointer; padding-left: 35px; width: 100%;'>";
		echo '</div></div>';
		echo "<div class='wbcr_inp_whereuse_input_container'><label for='wbcr_inp_whereuse_input'>" . esc_html__( 'Location:', 'insert-php' ) . '</label>';
		echo "<input type='text' name='wbcr_inp_whereuse_input' class='wbcr_inp_whereuse_input' value='" . esc_attr( $value ) . "' readonly='readonly'>";
		echo '</div>';
	}

	/**
	 * Get grouped filter parameters for conditions
	 * 
	 * @return array<array<string, mixed>> Grouped filter parameters
	 */
	private function get_grouped_filter_params() {
		$grouped_filter_params = [
			[
				'id'    => 'user',
				'title' => __( 'User', 'insert-php' ),
				'items' => [
					[
						'id'          => 'user-role',
						'title'       => __( 'Role', 'insert-php' ),
						'type'        => 'select',
						'values'      => [
							'type'   => 'ajax',
							'action' => 'wbcr_inp_ajax_get_user_roles',
						],
						'description' => __( 'A role of the user who views your website. The role "guest" is applied to unregistered users.', 'insert-php' ),
					],
					[
						'id'          => 'user-registered',
						'title'       => __( 'Registration Date', 'insert-php' ),
						'type'        => 'date',
						'description' => __( 'The date when the user who views your website was registered. For unregistered users this date always equals to 1 Jan 1970.', 'insert-php' ),
					],
					[
						'id'          => 'user-cookie-name',
						'title'       => __( 'Cookie Name', 'insert-php' ),
						'type'        => 'text',
						'onlyEquals'  => true,
						'description' => __( 'Determines whether the user\'s browser has a cookie with a given name.', 'insert-php' ),
					],
				],
			],
			[
				'id'    => 'location',
				'title' => __( 'Location', 'insert-php' ),
				'items' => [
					[
						'id'          => 'location-page',
						'title'       => __( 'Page URL', 'insert-php' ),
						'type'        => 'text',
						'description' => __( 'The URL of the current page where a user who views your website is located.', 'insert-php' ),
					],
					[
						'id'          => 'location-referrer',
						'title'       => __( 'Current Referrer', 'insert-php' ),
						'type'        => 'text',
						'description' => __( 'A referrer URL that brought the user to the current page.', 'insert-php' ),
					],
					[
						'id'          => 'location-post-type',
						'title'       => __( 'Post type', 'insert-php' ),
						'type'        => 'select',
						'values'      => [
							'type'   => 'ajax',
							'action' => 'wbcr_inp_ajax_get_post_types',
						],
						'description' => __( 'A post type of the current page.', 'insert-php' ),
					],
					[
						'id'          => 'location-taxonomy',
						'title'       => __( 'Category/Tag Archive Page', 'insert-php' ),
						'type'        => 'select',
						'values'      => [
							'type'   => 'ajax',
							'action' => 'wbcr_inp_ajax_get_taxonomies',
						],
						'description' => __( 'A taxonomy archive page.', 'insert-php' ),
					],
					[
						'id'          => 'page-taxonomy',
						'title'       => __( 'Categories or Tags assigned to this page', 'insert-php' ),
						'type'        => 'select',
						'values'      => [
							'type'   => 'ajax',
							'action' => 'wbcr_inp_ajax_get_taxonomies',
						],
						'description' => __( 'A taxonomy of the current page.', 'insert-php' ),
					],
					[
						'id'          => 'location-some-page',
						'title'       => __( 'Page', 'insert-php' ),
						'type'        => 'select',
						'values'      => [
							'type'   => 'ajax',
							'action' => 'wbcr_inp_ajax_get_page_list',
						],
						'description' => __( 'Select from a list of specific pages.', 'insert-php' ),
					],
				],
			],
			[
				'id'    => 'technology',
				'title' => __( 'Technology', 'insert-php' ) . ' (PRO)',
				'items' => [
					[
						'id'          => 'technology-addblocker',
						'title'       => __( 'Addblocker', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses an ad blocker on the website.', 'insert-php' ),
					],
					[
						'id'          => 'technology-browser',
						'title'       => __( 'Browser', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses the selected browser.', 'insert-php' ),
					],
					[
						'id'          => 'technology-use-cookie',
						'title'       => __( 'Use cookie', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses cookies on the website.', 'insert-php' ),
					],
					[
						'id'          => 'technology-use-javascript',
						'title'       => __( 'Use JavaScript', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses JavaScript on the website.', 'insert-php' ),
					],
					[
						'id'          => 'technology-operating-system',
						'title'       => __( 'Operating system', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses the selected OS.', 'insert-php' ),
					],
					[
						'id'          => 'technology-device-type',
						'title'       => __( 'Device type', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Determines whether the user uses the selected device type.', 'insert-php' ),
					],
				],
			],
			[
				'id'    => 'auditory',
				'title' => __( 'Auditory', 'insert-php' ) . ' (PRO)',
				'items' => [
					[
						'id'          => 'auditory-country',
						'title'       => __( 'User country', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Geolocation', 'insert-php' ),
					],
					[
						'id'          => 'auditory-viewing',
						'title'       => __( 'Viewing depth', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Pages viewed in current session', 'insert-php' ),
					],
					[
						'id'          => 'auditory-attendance',
						'title'       => __( 'By Date', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Time period when the user visited', 'insert-php' ),
					],
					[
						'id'          => 'auditory-visits',
						'title'       => __( 'Total number of visits', 'insert-php' ),
						'type'        => 'disabled',
						'description' => __( 'Total visits by this user', 'insert-php' ),
					],
				],
			],
		];

		return apply_filters( 'wbcr/inp/visibility/filter_params', $grouped_filter_params );
	}

	/**
	 * Get location options for base-options dropdown.
	 *
	 * @return array<int, array{title: string, items: array<int, array{value: string, label: string, disabled: bool}>}>
	 */
	private function get_location_options() {
		/**
		 * Insertion Locations instance.
		 * 
		 * @var WINP_Insertion_Locations
		 */
		$winp_snippets_locations = new WINP_Insertion_Locations();

		$snippet_type = WINP_Helper::get_snippet_type();

		// Build location groups.
		$location_groups = [];

		// Add Everywhere group (not for TEXT/AD types).
		if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type ) {
			$everywhere_items = $winp_snippets_locations->getInsertionForOptions( 'everywhere' );
			if ( ! empty( $everywhere_items ) ) {
				$location_groups[] = [
					'title' => __( 'Everywhere', 'insert-php' ),
					'items' => $this->format_location_items( $everywhere_items ),
				];
			}
		}

		// Add Posts group.
		$posts_items = $winp_snippets_locations->getInsertionForOptions( 'posts' );
		if ( ! empty( $posts_items ) ) {
			$location_groups[] = [
				'title' => __( 'Posts, Pages, Custom post types', 'insert-php' ),
				'items' => $this->format_location_items( $posts_items ),
			];
		}

		// Add Pages group.
		$pages_items = $winp_snippets_locations->getInsertionForOptions( 'pages' );
		if ( ! empty( $pages_items ) ) {
			$location_groups[] = [
				'title' => __( 'Categories, Archives, Tags, Taxonomies', 'insert-php' ),
				'items' => $this->format_location_items( $pages_items ),
			];
		}

		// Add WooCommerce group if available.
		$woocommerce_items = $winp_snippets_locations->getInsertionForOptions( 'woocommerce' );
		if ( ! empty( $woocommerce_items ) ) {
			$location_groups[] = [
				'title' => __( 'WooCommerce Locations', 'insert-php' ),
				'items' => $this->format_location_items( $woocommerce_items, ! defined( 'WINP_PLUGIN_ACTIVE' ) || ! class_exists( 'WooCommerce' ) ),
			];
		}

		// Add Custom group if available.
		$custom_items = $winp_snippets_locations->getInsertionForOptions( 'custom' );
		if ( ! empty( $custom_items ) ) {
			$location_groups[] = [
				'title' => __( 'Custom Action Hook', 'insert-php' ),
				'items' => $this->format_location_items( $custom_items ),
			];
		}

		return $location_groups;
	}

	/**
	 * Format location items for dropdown
	 *
	 * @param array<int, array{0: string, 1: string, 2: string, 3: bool}> $items    Location items - array of [$key, $label, $hint, $requiresLocationNumber].
	 * @param bool                                                        $disabled Whether items should be disabled.
	 *
	 * @return array<int, array{value: string, label: string, disabled: bool, requiresLocationNumber: bool}>
	 */
	private function format_location_items( $items, $disabled = false ) {
		$formatted = [];

		foreach ( $items as $item ) {
			$formatted[] = [
				'value'                  => $item[0],
				'label'                  => $item[1],
				'requiresLocationNumber' => $item[3],
				'disabled'               => $disabled,
			];
		}

		return $formatted;
	}

	/**
	 * On saving snippet - save meta options before factory form saves
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function on_saving_snippet( $post_id ) {
		// Only process snippet post type.
		if ( get_post_type( $post_id ) !== WINP_SNIPPETS_POST_TYPE ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if user has permissions to save.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save any meta during revision restoration.
		// During restore, WordPress doesn't send form data, so we should let wp_restore_post_revision handle it.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- We're only reading GET to detect restore, not processing data
		$is_restoring = isset( $_GET['action'] ) && 'restore' === $_GET['action'] && isset( $_GET['revision'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		
		if ( $is_restoring ) {
			return;
		}

		// Skip if this is an auto-draft (initial post creation).
		$post_status = get_post_status( $post_id );
		if ( 'auto-draft' === $post_status ) {
			// For auto-draft, save snippet type from URL parameter if available.
			$url_snippet_type = WINP_HTTP::get( 'winp_item', '', 'sanitize_key' );
			if ( ! empty( $url_snippet_type ) ) {
				WINP_Helper::updateMetaOption( $post_id, 'snippet_type', $url_snippet_type );
			}
			return;
		}

		// Skip if no form data is present (e.g., when trashing/untrashing).
		// Check if the snippet type field exists in POST - if not, we're not in a form submission context.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- We're only checking if field exists to detect form submission, not processing the value
		if ( ! isset( $_POST['wbcr_inp_snippet_type'] ) ) {
			return;
		}

		$location = WINP_HTTP::post( 'wbcr_inp_snippet_location', 'header', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_location', $location );

		$p_number = WINP_HTTP::post( 'wbcr_inp_snippet_p_number', '0', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_p_number', $p_number );

		$scope = WINP_HTTP::post( 'wbcr_inp_snippet_scope', 'shortcode', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_scope', $scope );

		$type = WINP_HTTP::post( 'wbcr_inp_snippet_type', WINP_SNIPPET_TYPE_PHP, true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_type', $type );

		$linking = WINP_HTTP::post( 'wbcr_inp_snippet_linking', '', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_linking', $linking );

		$description = WINP_HTTP::post( 'wbcr_inp_snippet_description', '', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_description', $description );

		$tags = WINP_HTTP::post( 'wbcr_inp_snippet_tags', '', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_tags', $tags );

		$wpml_lang = WINP_HTTP::post( 'wbcr_inp_snippet_wpml_lang', '', true );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_wpml_lang', $wpml_lang );

		$priority = WINP_Helper::getMetaOption( $post_id, 'snippet_priority', WINP_Helper::get_next_snippet_priority() );
		WINP_Helper::updateMetaOption( $post_id, 'snippet_priority', $priority );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified via wp_nonce_field in metabox
		$filters_raw = isset( $_POST['wbcr_inp_snippet_filters'] ) ? sanitize_text_field( wp_unslash( $_POST['wbcr_inp_snippet_filters'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$filters = ! empty( $filters_raw ) ? json_decode( stripslashes( $filters_raw ) ) : '';
		WINP_Helper::updateMetaOption( $post_id, 'snippet_filters', $filters );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified via wp_nonce_field in metabox
		$changed_filters = isset( $_POST['wbcr_inp_changed_filters'] ) ? intval( $_POST['wbcr_inp_changed_filters'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		WINP_Helper::updateMetaOption( $post_id, 'changed_filters', $changed_filters );

		do_action( 'wbcr/inp/base_option/on_saving_form', $post_id );
	}

	/**
	 * After saving snippet - validate code and activate if needed
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function after_saving_snippet( $post_id ) {
		// Only process snippet post type.
		if ( get_post_type( $post_id ) !== WINP_SNIPPETS_POST_TYPE ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if user has permissions to save.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Skip validation and activation for auto-drafts.
		$post_status = get_post_status( $post_id );
		if ( 'auto-draft' === $post_status ) {
			return;
		}

		$is_default_activate = get_option( 'wbcr_inp_activate_by_default', true );
		$snippet_scope       = WINP_HTTP::post( 'wbcr_inp_snippet_scope', null, true );
		$snippet_type        = WINP_Helper::get_snippet_type( $post_id );
		$post_content        = get_post_field( 'post_content', $post_id );

		if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type ) {
			$snippet_content = ! empty( $post_content ) ? WINP_Plugin::app()->get_execute_object()->prepareCode( $post_content, $post_id ) : '';
		} else {
			$snippet_content = $post_content;
		}

		WINP_Helper::updateMetaOption( $post_id, 'snippet_activate', false );

		$validate = true;

		if ( 'evrywhere' === $snippet_scope || 'auto' === $snippet_scope ) {
			if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type && WINP_SNIPPET_TYPE_CSS !== $snippet_type && WINP_SNIPPET_TYPE_JS !== $snippet_type && WINP_SNIPPET_TYPE_HTML !== $snippet_type ) {
				$validate = $this->validate_code( $snippet_content, $snippet_type );
			} else {
				$validate = true;
			}
		}

		if ( $validate ) {
			// Activate snippet if default activation is enabled and user has permission.
			if ( $is_default_activate && WINP_Plugin::app()->current_user_car() ) {
				WINP_Helper::updateMetaOption( $post_id, 'snippet_activate', true );
			}
		} else {
			// Validation failed - redirect with error.
			if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
				define( 'WP_SANDBOX_SCRAPING', true );
			}
			/* Display message if a parse error occurred */
			wp_safe_redirect(
				add_query_arg(
					[
						'action'                       => 'edit',
						'post'                         => $post_id,
						'wbcr_inp_save_snippet_result' => 'code-error',
					],
					admin_url( 'post.php' ) 
				) 
			);

			exit;
		}
	}

	/**
	 * Validate the snippet code before saving to database
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 *
	 * @return bool true if code is valid, false if it contains errors.
	 */
	private function validate_code( $snippet_code, $snippet_type ) {
		$snippet_code = stripslashes( $snippet_code );

		if ( empty( $snippet_code ) ) {
			return true;
		}

		// Set custom error handler to catch warnings and notices.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			function ( $errno, $errstr, $errfile, $errline ) {
				// Don't let warnings/notices pass through.
				if ( strpos( $errfile, "eval()'d code" ) !== false ) {
					// Convert warnings/notices to exceptions so we can catch them.
					throw new ErrorException( $errstr, 0, $errno, $errfile, $errline ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception constructor parameters, not output. Message is escaped when displayed.
				}
				return false; // Let PHP handle non-eval errors normally.
			}
		);

		// Start output buffering to catch any output from the code.
		ob_start();

		try {
			$result = WINP_SNIPPET_TYPE_UNIVERSAL === $snippet_type ? eval( '?> ' . $snippet_code . ' <?php ' ) : eval( $snippet_code );

			// elimination of errors 500 in eval() functions, with the directive display_errors = off;.
			header( 'HTTP/1.0 200 OK' );

			// Discard any output (echo/print statements are normal for snippets).
			ob_end_clean();
			restore_error_handler();

			do_action( 'wbcr_inp_after_execute_snippet', get_the_ID(), $snippet_code, $result );

			return false !== $result;
		} catch ( ParseError $e ) {
			ob_end_clean();
			restore_error_handler();
			$this->display_validation_error( $e->getLine(), $e->getMessage() );
			return false;
		} catch ( Throwable $e ) {
			ob_end_clean();
			restore_error_handler();
			$line = strpos( $e->getFile(), "eval()'d code" ) !== false ? $e->getLine() : 0;
			$this->display_validation_error( $line, $e->getMessage() );
			return false;
		}
	}

	/**
	 * Display validation error and exit
	 *
	 * @param int    $line Line number where error occurred.
	 * @param string $message Error message.
	 *
	 * @return void
	 */
	private function display_validation_error( $line, $message ) {
		$title = __( 'Code Error Detected', 'insert-php' );
		
		if ( $line > 0 ) {
			// translators: %d is the line number.
			$error_text = sprintf( __( 'This code snippet contains a fatal error on line %d:', 'insert-php' ), $line );
		} else {
			$error_text = __( 'This code snippet contains a fatal error:', 'insert-php' );
		}
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				html {
					background: #f1f1f1;
				}
				body {
					background: #fff;
					border: 1px solid #ccd0d4;
					color: #444;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
					margin: 2em auto;
					padding: 1em 2em;
					max-width: 700px;
					-webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
				}
				h1 {
					border-bottom: 1px solid #dadada;
					clear: both;
					color: #666;
					font-size: 24px;
					margin: 30px 0 0 0;
					padding: 0;
					padding-bottom: 7px;
				}
				#error-page {
					margin-top: 50px;
				}
				#error-page p {
					font-size: 14px;
					line-height: 1.5;
					margin: 25px 0 20px;
				}
				#error-page code {
					font-family: Consolas, Monaco, monospace;
					background: #f5f5f5;
					padding: 2px 4px;
					border: 1px solid #ddd;
					border-radius: 3px;
				}
				.error-message {
					background: #fff3cd;
					border-left: 4px solid #d63638;
					padding: 12px;
					margin: 20px 0;
					font-family: Consolas, Monaco, monospace;
					font-size: 13px;
					line-height: 1.6;
					color: #721c24;
				}
				a {
					color: #0073aa;
					text-decoration: none;
				}
				a:hover,
				a:active {
					color: #006799;
				}
				a:focus {
					color: #124964;
					-webkit-box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8);
					box-shadow: 0 0 0 1px #5b9dd9, 0 0 2px 1px rgba(30, 140, 190, 0.8);
					outline: none;
				}
				.button {
					background: #f6f7f7;
					border: 1px solid #0073aa;
					color: #0073aa;
					display: inline-block;
					text-decoration: none;
					font-size: 13px;
					line-height: 2;
					height: 28px;
					margin: 0;
					padding: 0 10px 1px;
					cursor: pointer;
					border-radius: 3px;
					white-space: nowrap;
					box-sizing: border-box;
				}
				.button:hover {
					background: #f0f0f1;
					border-color: #006799;
					color: #006799;
				}
			</style>
		</head>
		<body id="error-page">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $error_text ); ?></p>
			<div class="error-message"><?php echo esc_html( $message ); ?></div>
			<p><?php esc_html_e( 'Your previous snippet version is safe. Your site is unaffected.', 'insert-php' ); ?></p>
			<p>
				<?php esc_html_e( 'Click your browser\'s back button to return and fix the error.', 'insert-php' ); ?>
				<?php esc_html_e( 'You can also close this page to discard your changes.', 'insert-php' ); ?>
			</p>
			<p>
				<a href="javascript:history.back()" class="button">&larr; <?php esc_html_e( 'Return to Editor', 'insert-php' ); ?></a>
			</p>
		</body>
		</html>
		<?php
		exit;
	}
}

new WINP_Snippet_MetaBox();
