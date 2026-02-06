<?php
/**
 * New Item Page Class
 * 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_NewItem Class
 */
class WINP_NewItem {
	
	/**
	 * Singleton instance
	 *
	 * @var WINP_NewItem|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_new_item_page' ] );
	}

	/**
	 * Register new item page
	 */
	public function register_new_item_page(): void {
		$page_hook_suffix = add_submenu_page(
			'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE,
			'+ ' . __( 'Add Snippet', 'insert-php' ),
			'+ ' . __( 'Add Snippet', 'insert-php' ),
			WINP_Helper::has_post_capabilities(),
			'winp-new-item',
			[ $this, 'render_new_item_page' ],
			1
		);

		add_action( "admin_print_scripts-$page_hook_suffix", [ $this, 'enqueue_new_item_assets' ] );
	}

	/**
	 * Render new item page content
	 */
	public function render_new_item_page(): void {
		echo '<div id="woody-new-item"></div>';
	}

	/**
	 * Load assets for new item page
	 */
	public function enqueue_new_item_assets(): void {
		$asset_file = include WINP_PLUGIN_DIR . '/admin/assets/new-item/build/index.asset.php';

		wp_enqueue_style(
			'winp-new-item-styles',
			WINP_PLUGIN_URL . '/admin/assets/new-item/build/style-index.css',
			[],
			$asset_file['version']
		);

		wp_enqueue_script(
			'winp-new-item-scripts',
			WINP_PLUGIN_URL . '/admin/assets/new-item/build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'winp-new-item-scripts', 'insert-php' );

		wp_localize_script(
			'winp-new-item-scripts',
			'winpNewItemObjects',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'winp_new_item_nonce' ),
				'snippetTypes' => $this->get_snippet_types(),
				'postType'     => WINP_SNIPPETS_POST_TYPE,
				'createUrl'    => admin_url( 'post-new.php?post_type=' . WINP_SNIPPETS_POST_TYPE ),
			]
		);

		do_action( 'themeisle_internal_page', WINP_PLUGIN_SLUG, 'new-item' );
	}

	/**
	 * Get available snippet types
	 *
	 * @return array<array<string, string>>
	 */
	private function get_snippet_types() {
		return [
			[
				'value'       => 'php',
				'label'       => __( 'PHP Snippet', 'insert-php' ),
				'description' => __( 'Execute PHP code on your site. Register functions, hooks, and global variables. Think of it as a virtual functions.php file.', 'insert-php' ),
				'icon'        => 'dashicons-editor-code',
			],
			[
				'value'       => 'text',
				'label'       => __( 'Text Snippet', 'insert-php' ),
				'description' => __( 'Insert formatted text content including quotes, paragraphs, tables, and media files. Supports shortcodes from other plugins.', 'insert-php' ),
				'icon'        => 'dashicons-text',
			],
			[
				'value'       => 'universal',
				'label'       => __( 'Universal Snippet', 'insert-php' ),
				'description' => __( 'Insert any combination of PHP, HTML, JavaScript, and CSS code. Perfect for ads, analytics, embeds, and complex scenarios.', 'insert-php' ),
				'icon'        => 'dashicons-admin-site',
			],
			[
				'value'       => 'css',
				'label'       => __( 'CSS Snippet', 'insert-php' ),
				'description' => __( 'Add custom CSS styles to your site. Modify appearance and layout without editing theme files.', 'insert-php' ),
				'icon'        => 'dashicons-art',
			],
			[
				'value'       => 'js',
				'label'       => __( 'JavaScript Snippet', 'insert-php' ),
				'description' => __( 'Add custom JavaScript to your site. Ideal for analytics, interactive features, and third-party integrations.', 'insert-php' ),
				'icon'        => 'dashicons-media-code',
			],
			[
				'value'       => 'html',
				'label'       => __( 'HTML Snippet', 'insert-php' ),
				'description' => __( 'Insert custom HTML markup anywhere on your site. Add structured content and layout elements.', 'insert-php' ),
				'icon'        => 'dashicons-editor-table',
			],
			[
				'value'       => 'ad',
				'label'       => __( 'Ad Snippet', 'insert-php' ),
				'description' => __( 'Insert advertisement code and banners. Manage ad placements with ease across your site.', 'insert-php' ),
				'icon'        => 'dashicons-megaphone',
			],
		];
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
	 * @return WINP_NewItem
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
