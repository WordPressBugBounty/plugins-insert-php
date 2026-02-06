<?php
/**
 * PHP snippets plugin base
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WINP_Plugin' ) ) {

	class WINP_Plugin {

		/**
		 * Custom license provider (overrides parent's premium property)
		 *
		 * @var WINP_License
		 */
		public $premium;

		/**
		 * @var WINP_Plugin
		 */
		private static $app;

		/**
		 * Snippets custom post type instance.
		 *
		 * @var WINP_SnippetsType
		 */
		private $snippets_type;

		/**
		 * @throws Exception
		 */
		public function __construct() {
			self::$app = $this;

			// Initialize custom license provider.
			require_once WINP_PLUGIN_DIR . '/includes/class.license.php';
			$this->premium = new WINP_License();

			require_once WINP_PLUGIN_DIR . '/includes/class.rest.php';
			new WINP_Rest();

			require_once WINP_PLUGIN_DIR . '/admin/pages/class.settings.php';
			require_once WINP_PLUGIN_DIR . '/admin/pages/class.new-item.php';
			require_once WINP_PLUGIN_DIR . '/admin/pages/class.snippet-library.php';

			WINP_Settings::get_instance();
			WINP_NewItem::get_instance();
			WINP_SnippetLibrary::get_instance();

			$this->load_global();

			if ( is_admin() ) {

				if ( WINP_Helper::doing_ajax() ) {
					require WINP_PLUGIN_DIR . '/admin/ajax/ajax.php';
					require WINP_PLUGIN_DIR . '/admin/ajax/snippet-library.php';
				}

				$this->load_backend();
			}
			add_action(
				'init',
				function () {
					if ( WINP_Plugin::app()->premium->is_active() ) {
						update_option( WINP_PLUGIN_NAMESPACE . '_logger_flag', 'yes' );
					}
				} 
			);

			add_filter( WINP_PLUGIN_NAMESPACE . '_logger_data', [ $this, 'get_logger_data' ] );
		}

		/**
		 * Get plugin instance
		 * 
		 * @return WINP_Plugin
		 */
		public static function app() {
			return self::$app;
		}

		/**
		 * Survey data.
		 * 
		 * @return array<string, mixed>
		 */
		public function get_survey_data() {
			$install_time       = get_option( WINP_PLUGIN_NAMESPACE . '_install', time() );
			$days_since_install = round( ( time() - $install_time ) / DAY_IN_SECONDS );
			$total_snippets     = wp_count_posts( WINP_SNIPPETS_POST_TYPE );
			$total_snippets     = isset( $total_snippets->publish ) ? $total_snippets->publish : 0;

			$data = [
				'environmentId' => 'cmiooosih4vm0ad01eihjub64',
				'attributes'    => [
					'free_version'        => WINP_PLUGIN_VERSION,
					'pro_version'         => defined( 'WASP_PLUGIN_VERSION' ) ? WASP_PLUGIN_VERSION : '',
					'install_days_number' => $days_since_install,
					'license_status'      => self::app()->premium->is_active(),
					'total_snippets'      => $total_snippets,
				],
			];

			if ( self::app()->premium->is_active() ) {
				$data['attributes']['license_key'] = apply_filters( 'themeisle_sdk_secret_masking', self::app()->premium->get_key() );
			}

			return $data;
		}

		/**
		 * @return bool
		 */
		public function current_user_car() {
			return current_user_can( 'manage_options' );
		}

		/**
		 * Get Execute_Snippet object
		 *
		 * @return WINP_Execute_Snippet
		 */
		public function get_execute_object() {
			require_once WINP_PLUGIN_DIR . '/includes/class.execute.snippet.php';

			return WINP_Execute_Snippet::app();
		}

		/**
		 * Get WINP_Api object
		 *
		 * @return WINP_Api
		 */
		public function get_api_object() {
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.request.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.api.php';

			return new WINP_Api();
		}

		/**
		 * Get WINP_Common_Snippet object
		 *
		 * @return WINP_Common_Snippet
		 */
		public function get_common_object() {
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.common.snippet.php';

			return new WINP_Common_Snippet();
		}

		/**
		 * Plugin activation hook.
		 * Creates demo snippets on first activation and sets up capabilities.
		 *
		 * @return void
		 */
		public function activation_hook() {
			// Add custom capabilities to administrator role.
			$this->snippets_type->add_capabilities();
			
			// Create demo snippets with examples of use.
			if ( ! get_option( 'wbcr_inp_demo_snippets_created' ) ) {
				WINP_Helper::create_demo_snippets();
			}
			
			WINP_Helper::flush_page_cache();
		}

		/**
		 * Plugin deactivation hook.
		 * Removes custom capabilities.
		 *
		 * @return void
		 */
		public function deactivation_hook() {
			// Remove custom capabilities from administrator role.
			$this->snippets_type->remove_capabilities();
		}

		/**
		 * Register custom post types and taxonomies.
		 *
		 * @throws \Exception Exception.
		 * @since   2.2.0
		 */
		private function register_types() {
			require_once WINP_PLUGIN_DIR . '/admin/types/snippets-post-types.php';
			$this->snippets_type = new WINP_SnippetsType();

			require_once WINP_PLUGIN_DIR . '/admin/types/snippets-taxonomy.php';
			new WINP_SnippetsTaxonomy();
		}

		/**
		 * Register shortcodes.
		 *
		 * @since  2.2.0
		 */
		private function register_shortcodes() {
			$action = WINP_HTTP::get( 'action', '' );
			if ( ! ( 'edit' == $action && is_admin() ) ) {
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcodes.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-php.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-text.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-universal.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-css.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-js.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-html.php';
				require_once WINP_PLUGIN_DIR . '/includes/shortcodes/shortcode-ad.php';

				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodePhp', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeText', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeUniversal', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeCss', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeJs', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeHtml', $this );
				WINP_Helper::register_shortcode( 'WINP_SnippetShortcodeAdvert', $this );
			}
		}

		/**
		 * Initialization and require files for backend and frontend.
		 *
		 * @since  2.2.0
		 */
		private function load_global() {
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.gutenberg.snippet.php';
			require_once WINP_PLUGIN_DIR . '/includes/class.admin-bar.php';

			new WINP_Gutenberg_Snippet();
			WINP_Admin_Bar::instance();

			$this->get_execute_object()->register_hooks();
			$this->register_shortcodes();
		}

		/**
		 * Initialization and require files for backend.
		 *
		 * @throws \Exception
		 * @since  2.2.0
		 */
		private function load_backend() {
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.snippets.viewtable.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.filter.snippet.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.actions.snippet.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.notices.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.admin.notices.php';
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.request.php';
			require_once WINP_PLUGIN_DIR . '/admin/boot.php';

			$this->get_common_object()->register_hooks();
			$this->register_types();

			new WINP_Filter_List();
			new WINP_Actions_Snippet();
			WINP_Notices::instance();
			new WINP_Admin_Notices();

			if ( ! defined( 'E2E_TESTING' ) ) {
				add_filter(
					'themeisle-sdk/survey/' . WINP_PLUGIN_SLUG,
					function ( $data, $page_slug ) {
						if ( empty( $page_slug ) ) {
							return $data;
						}

						return $this->get_survey_data();
					},
					10,
					2
				);
			}
		}

		/**
		 * Метод проверяет активацию премиум плагина и наличие действующего лицензионного ключа
		 *
		 * @return bool
		 */
		public function is_premium() {
			return $this->premium->is_active();
		}

		/**
		 * Logger data.
		 * 
		 * @return array<string, mixed>
		 */
		public function get_logger_data() {
			$settings = WINP_Settings::get_instance()->get_settings();

			// Each settings has a lot of keys, we only want name and value items. So map them.
			$settings = array_map(
				function ( $setting ) {
					return [
						'name'  => isset( $setting['name'] ) ? $setting['name'] : '',
						'value' => isset( $setting['value'] ) ? $setting['value'] : '',
					];
				},
				$settings
			);

			$total_snippets = wp_count_posts( WINP_SNIPPETS_POST_TYPE );
			$total_snippets = isset( $total_snippets->publish ) ? $total_snippets->publish : 0;

			// Count snippets by type.
			$snippet_types = [ 'php', 'css', 'js', 'text', 'html', 'advert', 'universal' ];
			$types_count   = [];
			
			foreach ( $snippet_types as $type ) {
				$count = get_posts(
					[
						'post_type'      => WINP_SNIPPETS_POST_TYPE,
						'post_status'    => 'publish',
						'meta_key'       => 'wbcr_inp_snippet_type',
						'meta_value'     => $type,
						'posts_per_page' => -1,
						'fields'         => 'ids',
					]
				);
				
				if ( ! empty( $count ) ) {
					$types_count[ $type ] = count( $count );
				}
			}

			return [
				'settings' => $settings,
				'stats'    => [
					'total_snippets' => $total_snippets,
					'types'          => $types_count,
				],
			];
		}
	}
}
