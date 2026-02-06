<?php
/**
 * Settings Page Class
 * 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Settings Class
 */
class WINP_Settings {
	
	/**
	 * Singleton instance
	 *
	 * @var WINP_Settings|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
	}

	/**
	 * Register dashboard page
	 */
	public function register_settings_page(): void {
		$page_hook_suffix = add_submenu_page(
			'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE,
			__( 'Settings', 'insert-php' ),
			__( 'Settings', 'insert-php' ),
			'manage_options',
			'winp-settings',
			[ $this, 'render_settings_page' ],
			4
		);

		add_action( "admin_print_scripts-$page_hook_suffix", [ $this, 'enqueue_options_assets' ] );
	}

	/**
	 * Render dashboard page content
	 */
	public function render_settings_page(): void {
		echo '<div id="woody-settings"></div>';
	}

	/**
	 * Load assets for option page.
	 */
	public function enqueue_options_assets(): void {
		$asset_file = include WINP_PLUGIN_DIR . '/admin/assets/dashboard/build/index.asset.php';

		wp_enqueue_style(
			'winp-dashboard-styles',
			WINP_PLUGIN_URL . '/admin/assets/dashboard/build/style-index.css',
			[],
			$asset_file['version']
		);

		wp_enqueue_script(
			'winp-dashboard-scripts',
			WINP_PLUGIN_URL . '/admin/assets/dashboard/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'winp-dashboard-scripts', 'insert-php' );

		wp_localize_script(
			'winp-dashboard-scripts',
			'winpObjects',
			[
				'api'           => 'woody/v1',
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'winp_settings_nonce' ),
				'assetsUrl'     => WINP_PLUGIN_URL . '/admin/assets/',
				'sections'      => $this->get_sections(),
				'settings'      => self::get_settings(),
				'links'         => [
					'upgrade'           => tsdk_utmify( WINP_UPGRADE, 'settings_page', 'upgrade_link' ),
					'docs'              => WINP_DOCS,
					'support'           => WINP_SUPPORT,
					'orgSupport'        => WINP_ORG_SUPPORT,
					'websiteDesign'     => tsdk_utmify( 'https://themeisle.com/wordpress-website-design/', 'expert_help_card_woody_snippets', 'website_design' ),
					'speedOptimization' => tsdk_utmify( 'https://themeisle.com/wordpress-speed-optimization/', 'expert_help_card_woody_snippets', 'speed_optimization' ),
					'seoFoundation'     => tsdk_utmify( 'https://themeisle.com/wordpress-seo-foundation/', 'expert_help_card_woody_snippets', 'seo_foundation' ),
					'siteMaintenance'   => tsdk_utmify( 'https://themeisle.com/wordpress-maintenance/', 'expert_help_card_woody_snippets', 'site_maintenance' ),
					'hackedSiteRepair'  => tsdk_utmify( 'https://themeisle.com/wordpress-hacked-site-repair/', 'expert_help_card_woody_snippets', 'hacked_site_repair' ),
				],
				'isProActive'   => WINP_Plugin::app()->premium->is_pro_active(),
				'license'       => [
					'key'    => apply_filters( 'product_woody_license_key', 'free' ),
					'status' => apply_filters( 'product_woody_license_status', false ),
				],
				'maxUploadSize' => size_format( apply_filters( 'import_upload_size_limit', wp_max_upload_size() ) ),
				'snippetData'   => $this->get_snippet_data(),
				'isRTL'         => is_rtl(),
			]
		);

		do_action( 'themeisle_internal_page', WINP_PLUGIN_SLUG, 'settings' );
	}

	/**
	 * Get settings sections
	 * 
	 * @return array<array<string, string>>
	 */
	public function get_sections() {
		$sections = [
			[
				'id'    => 'general',
				'title' => __( 'General', 'insert-php' ),
			],
			[
				'id'    => 'code_editor',
				'title' => __( 'Code Editor', 'insert-php' ),
			],
		];

		$sections = apply_filters( 'wbcr/inp/settings/sections', $sections );

		$sections[] = [
			'id'    => 'import',
			'title' => __( 'Import', 'insert-php' ),
		];

		$sections[] = [
			'id'    => 'export',
			'title' => __( 'Export', 'insert-php' ),
		];

		return $sections;
	}

	/**
	 * Get settings options
	 * 
	 * @return array<array<string, mixed>>
	 */
	public static function get_settings() {
		$options = [
			[
				'title'   => __( 'Auto-activate New Snippets', 'insert-php' ),
				'hint'    => __( 'Automatically activate snippets when creating or updating them.', 'insert-php' ),
				'name'    => 'activate_by_default',
				'type'    => 'checkbox',
				'section' => 'general',
				'value'   => get_option( 'wbcr_inp_activate_by_default', true ),
				'default' => true,
			],
			[
				'title'   => __( 'Preserve Special Characters', 'insert-php' ),
				'hint'    => __( 'Preserve HTML entities without converting them.', 'insert-php' ),
				'name'    => 'keep_html_entities',
				'type'    => 'checkbox',
				'section' => 'general',
				'value'   => get_option( 'wbcr_inp_keep_html_entities' ),
				'default' => false,
			],
			[
				'title'   => __( 'Execute Shortcodes in Snippets', 'insert-php' ),
				'hint'    => __( 'Process shortcodes within snippets.', 'insert-php' ),
				'name'    => 'execute_shortcode',
				'type'    => 'checkbox',
				'section' => 'general',
				'value'   => get_option( 'wbcr_inp_execute_shortcode' ),
				'default' => false,
			],
			[
				'title'   => __( 'Enable Error Email Notifications', 'insert-php' ),
				'hint'    => __( 'Email notifications for snippet errors.', 'insert-php' ),
				'name'    => 'error_email_enabled',
				'type'    => 'checkbox',
				'section' => 'general',
				'value'   => get_option( 'wbcr_inp_error_email_enabled' ),
				'default' => false,
			],
			[
				'title'       => __( 'Error Notification Email', 'insert-php' ),
				'hint'        => __( 'Email for error notifications. Defaults to admin email.', 'insert-php' ),
				'name'        => 'error_email_address',
				'type'        => 'email',
				'section'     => 'general',
				'value'       => get_option( 'wbcr_inp_error_email_address', get_option( 'admin_email' ) ),
				'default'     => get_option( 'admin_email' ),
				'placeholder' => get_option( 'admin_email' ),
				'visibility'  => [
					'field' => 'error_email_enabled',
					'value' => true,
					'type'  => 'equals',
				],
			],
			[
				'title'   => __( 'Delete All Data on Uninstall', 'insert-php' ),
				'hint'    => __( 'Delete all data when uninstalling.', 'insert-php' ),
				'name'    => 'complete_uninstall',
				'type'    => 'checkbox',
				'section' => 'general',
				'value'   => get_option( 'wbcr_inp_complete_uninstall' ),
				'default' => false,
			],
			[
				'title'   => __( 'Code Style', 'insert-php' ),
				'hint'    => __( 'Choose a color theme for the code editor.', 'insert-php' ),
				'name'    => 'code_editor_theme',
				'type'    => 'dropdown',
				'section' => 'code_editor',
				'data'    => self::get_available_themes(),
				'value'   => get_option( 'wbcr_inp_code_editor_theme', 'default' ),
				'default' => 'default',
			],
			[
				'title'   => __( 'Indent With Tabs', 'insert-php' ),
				'hint'    => __( 'Use tabs instead of spaces for indentation.', 'insert-php' ),
				'name'    => 'code_editor_indent_with_tabs',
				'type'    => 'checkbox',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_indent_with_tabs' ),
				'default' => false,
			],
			[
				'title'   => __( 'Tab Size', 'insert-php' ),
				'hint'    => __( 'Number of spaces per Tab key press.', 'insert-php' ),
				'name'    => 'code_editor_tab_size',
				'type'    => 'integer',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_tab_size', 4 ),
				'default' => 4,
			],
			[
				'title'   => __( 'Indent Unit', 'insert-php' ),
				'hint'    => __( 'Define the number of spaces used for each indentation level in code blocks.', 'insert-php' ),
				'name'    => 'code_editor_indent_unit',
				'type'    => 'integer',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_indent_unit', 4 ),
				'default' => 4,
			],
			[
				'title'   => __( 'Wrap Lines', 'insert-php' ),
				'hint'    => __( 'Wrap long lines to fit the editor width.', 'insert-php' ),
				'name'    => 'code_editor_wrap_lines',
				'type'    => 'checkbox',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_wrap_lines', 1 ),
				'default' => true,
			],
			[
				'title'   => __( 'Line Numbers', 'insert-php' ),
				'hint'    => __( 'Show line numbers in the editor.', 'insert-php' ),
				'name'    => 'code_editor_line_numbers',
				'type'    => 'checkbox',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_line_numbers', 1 ),
				'default' => true,
			],
			[
				'title'   => __( 'Auto Close Brackets', 'insert-php' ),
				'hint'    => __( 'Auto-close brackets and quotes while typing.', 'insert-php' ),
				'name'    => 'code_editor_auto_close_brackets',
				'type'    => 'checkbox',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_auto_close_brackets', 1 ),
				'default' => true,
			],
			[
				'title'   => __( 'Highlight Selection Matches', 'insert-php' ),
				'hint'    => __( 'Highlight all matches of selected text.', 'insert-php' ),
				'name'    => 'code_editor_highlight_selection_matches',
				'type'    => 'checkbox',
				'section' => 'code_editor',
				'value'   => get_option( 'wbcr_inp_code_editor_highlight_selection_matches' ),
				'default' => false,
			],
		];

		$options = apply_filters( 'wbcr/inp/settings/options', $options );

		return $options;
	}

	/**
	 * Retrieve a list of the available CodeMirror themes
	 *
	 * @return array<array<string, mixed>> the available themes.
	 */
	public static function get_available_themes() {
		$themes = null;

		$themes      = [];
		$themes_dir  = WINP_PLUGIN_DIR . '/admin/assets/css/cmthemes/';
		$theme_files = glob( $themes_dir . '*.css' );

		if ( is_array( $theme_files ) ) {
			foreach ( $theme_files as $theme ) {
				$theme    = str_replace( $themes_dir, '', $theme );
				$theme    = str_replace( '.css', '', $theme );
				$themes[] = [
					'label' => $theme,
					'value' => $theme,
				];
			}
		}

		array_unshift(
			$themes,
			[
				'label' => 'default',
				'value' => 'default',
			] 
		);

		return $themes;
	}

	/**
	 * Get snippet data
	 *
	 * @return array<string, array<array<string, mixed>>>
	 */
	private function get_snippet_data() {
		$results = [];
		$types   = [];
		$tags    = [];

		$snippets = get_posts(
			[
				'post_type'   => WINP_SNIPPETS_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => - 1,
			] 
		);

		if ( ! empty( $snippets ) ) {
			foreach ( (array) $snippets as $snippet ) {
				$snippet_type = WINP_Helper::get_snippet_type( $snippet->ID );
				if ( ! isset( $types[ $snippet_type ] ) ) {
					$types[ $snippet_type ] = 1;
				} else {
					++$types[ $snippet_type ];
				}

				$terms = wp_get_post_terms( $snippet->ID, WINP_SNIPPETS_TAXONOMY );

				if ( ! empty( $terms ) ) {
					foreach ( (array) $terms as $snippet_tag ) {
						if ( ! isset( $tags[ $snippet_tag->slug ] ) ) {
							$tags[ $snippet_tag->slug ] = 1;
						} else {
							++$tags[ $snippet_tag->slug ];
						}
					}
				}
			}

			foreach ( $types as $snippet_type => $count ) {
				$results['types'][] = [
					'value' => $snippet_type,
					'label' => $snippet_type,
					'count' => $count,
				];
			}

			foreach ( $tags as $tag => $count ) {
				$results['tags'][] = [
					'value' => $tag,
					'label' => $tag,
					'count' => $count,
				];
			}
		}

		return $results;
	}

	/**
	 * Prevent cloning of the instance
	 * 
	 * @throws \Exception An exception when trying to clone the instance.
	 */
	private function __clone() {
		throw new \Exception( 'Cannot clone singleton' );
	}

	/**
	 * Prevent unserialization of the instance
	 * 
	 * @throws \Exception An exception when trying to unserialize the instance.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Get singleton instance
	 *
	 * @return WINP_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
