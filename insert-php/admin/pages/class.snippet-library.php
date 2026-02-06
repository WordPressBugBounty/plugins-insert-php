<?php
/**
 * Snippet Library Page Class
 * 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_SnippetLibrary Class
 */
class WINP_SnippetLibrary {
	
	/**
	 * Singleton instance
	 *
	 * @var WINP_SnippetLibrary|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_snippet_library_page' ] );
		
		require_once WINP_PLUGIN_DIR . '/admin/includes/class.snippets.table.php';
	}

	/**
	 * Register snippet library page
	 */
	public function register_snippet_library_page(): void {
		$page_hook_suffix = add_submenu_page(
			'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE,
			__( 'Snippets Library', 'insert-php' ),
			__( 'Snippets Library', 'insert-php' ),
			WINP_Helper::has_post_capabilities(),
			'snippet-library',
			[ $this, 'render_snippet_library_page' ]
		);

		add_action( "admin_print_scripts-$page_hook_suffix", [ $this, 'enqueue_snippet_library_assets' ] );
	}

	/**
	 * Load assets for snippet library page
	 */
	public function enqueue_snippet_library_assets(): void {
		wp_enqueue_style( 'winp-snippets-table', WINP_PLUGIN_URL . '/admin/assets/css/snippets-table.css', [], WINP_PLUGIN_VERSION );
		wp_enqueue_script( 'winp-snippet-library', WINP_PLUGIN_URL . '/admin/assets/js/snippet-library.js', [ 'jquery' ], WINP_PLUGIN_VERSION, true );
		wp_localize_script(
			'winp-snippet-library',
			'winp_snippet_library',
			[
				'is_import'     => __( 'Import snippet?', 'insert-php' ),
				'is_delete'     => __( 'Delete snippet?', 'insert-php' ),
				'import_failed' => __( 'Import failed. Please check your file format and try again.', 'insert-php' ),
				'delete_failed' => __( 'Delete failed. Please refresh the page and try again.', 'insert-php' ),
			]
		);
	}

	/**
	 * Render snippet library page content
	 */
	public function render_snippet_library_page(): void {
		$my_snippets_tab = true;
		$library_tab     = false;
		$my_snippets_url = esc_url_raw( remove_query_arg( [ 'tab' ] ) );
		$library_url     = esc_url_raw( add_query_arg( 'tab', 'library', $my_snippets_url ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking tab parameter for UI display
		if ( isset( $_GET['tab'] ) && 'library' === $_GET['tab'] ) {
			$my_snippets_tab = false;
			$library_tab     = true;
		}

		do_action( 'themeisle_internal_page', WINP_PLUGIN_SLUG, 'snippet-library' );
		?>
		<div class="wrap">
			<div class="winp-snippet-library">
				<h3><?php esc_html_e( 'Snippets Library', 'insert-php' ); ?></h3>

				<div class="nav-tab-wrapper">
					<a href="<?php echo esc_url( $my_snippets_url ); ?>" class="nav-tab<?php echo $my_snippets_tab ? ' nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'My Snippets', 'insert-php' ); ?>
					</a>
					<a href="<?php echo esc_url( $library_url ); ?>" class="nav-tab<?php echo $library_tab ? ' nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Snippets Library', 'insert-php' ); ?>
					</a>
				</div>

				<?php if ( $library_tab ) : ?>
					<div id="tab1">
						<?php $this->render_html( true ); ?>
					</div>
				<?php else : ?>
					<div id="tab2">
						<?php $this->render_html( false ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render html part with snippets list
	 *
	 * @param bool $common Whether to show common snippets (library) or user snippets.
	 */
	private function render_html( bool $common ): void {
		$snippet_list_table = new WINP_Snippet_Library_Table();

		$is_pro = WINP_Plugin::app()->get_api_object()->is_key();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking tab parameter for display logic
		$is_library_tab = isset( $_GET['tab'] ) && 'library' === $_GET['tab'];
		
		if ( $is_pro || $is_library_tab ) :
			?>
			<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
			<form id="winp-snippet-library" method="get">
				<!-- For plugins, we also need to ensure that the form posts back to our current page -->
				<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '' ); ?>"/>
				<!-- Now we can render the completed list table -->
				<?php
				$snippet_list_table->prepare_items( $common );
				$snippet_list_table->display();
				?>
			</form>

			<?php wp_nonce_field( 'winp-snippet-library', 'winp-snippet-library-nonce' ); ?>
		<?php else : ?>
			<div class="winp-upsell-container">
				<div class="winp-upsell-card">
					<div class="winp-upsell-icon">
						<span class="dashicons dashicons-category"></span>
					</div>
					<div class="winp-upsell-title">
						<?php esc_html_e( 'My Templates', 'insert-php' ); ?>
					</div>
					<p class="winp-upsell-badge">
						<?php esc_html_e( 'Pro feature', 'insert-php' ); ?>
					</p>
					<p class="winp-upsell-description">
						<?php esc_html_e( 'Snippets saved as templates sync automatically across all your sites—no import/export needed.', 'insert-php' ); ?>
					</p>
					<a href="<?php echo esc_url( tsdk_utmify( WINP_UPGRADE, 'snippet_library', 'my_templates_upsell' ) ); ?>" class="button button-primary button-large winp-upsell-button" target="_blank">
						<?php esc_html_e( 'Upgrade to Pro', 'insert-php' ); ?>
					</a>
				</div>
			</div>
			<?php
		endif;
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
	 * @return WINP_SnippetLibrary
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
