<?php
/**
 * Php Snippets Type
 * Declaration for custom post type of Php code snippets
 *
 * @package Woody_Code_Snippets
 * @link    http://codex.wordpress.org/Post_Types
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_SnippetsType
 *
 * Handles registration and configuration of the snippets custom post type.
 */
class WINP_SnippetsType {

	/**
	 * Post type name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Plural title.
	 *
	 * @var string
	 */
	private $plural_title;

	/**
	 * Singular title.
	 *
	 * @var string
	 */
	private $singular_title;

	/**
	 * Menu icon URL.
	 *
	 * @var string
	 */
	private $menu_icon;

	/**
	 * Post type capabilities.
	 *
	 * @var string[]
	 */
	private $capabilities = [ 'administrator' ];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name           = WINP_SNIPPETS_POST_TYPE;
		$this->plural_title   = __( 'Woody snippets', 'insert-php' );
		$this->singular_title = __( 'Woody snippets', 'insert-php' );
		$this->menu_icon      = WINP_PLUGIN_URL . '/admin/assets/img/menu-icon-4.png';

		// Initialize view table.
		new WINP_SnippetsViewTable();

		// Register hooks.
		add_action( 'init', [ $this, 'register' ] );
		add_action( 'admin_head', [ $this, 'print_menu_styles' ] );
		add_action( 'admin_head', [ $this, 'print_menu_icon_styles' ] );
		add_filter( 'post_updated_messages', [ $this, 'updated_messages' ] );
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register() {
		$labels = $this->get_labels();
		$args   = $this->get_post_type_args( $labels );

		register_post_type( $this->name, $args );
	}

	/**
	 * Get post type labels.
	 *
	 * @return string[] Post type labels.
	 */
	private function get_labels() {
		$labels = [
			'name'               => $this->plural_title,
			'singular_name'      => $this->singular_title,
			'all_items'          => __( 'Snippets', 'insert-php' ),
			'add_new'            => __( '+ Add snippet', 'insert-php' ),
			'add_new_item'       => __( 'Add New Snippet', 'insert-php' ),
			'edit'               => __( 'Edit', 'insert-php' ),
			'edit_item'          => __( 'Edit snippet', 'insert-php' ),
			'new_item'           => __( 'New snippet', 'insert-php' ),
			'view'               => __( 'View Snippet', 'insert-php' ),
			'view_item'          => __( 'View snippet', 'insert-php' ),
			'search_items'       => __( 'Search snippets', 'insert-php' ),
			'not_found'          => __( 'Snippet not found', 'insert-php' ),
			'not_found_in_trash' => __( 'Snippet not found in trash', 'insert-php' ),
			'parent'             => __( 'Parent snippet', 'insert-php' ),
		];

		return apply_filters( 'wbcr_inp_items_lables', $labels );
	}

	/**
	 * Get post type registration arguments.
	 *
	 * @param string[] $labels Post type labels.
	 * @return array<string, mixed> Post type args.
	 */
	private function get_post_type_args( $labels ) {
		$snippet_type = WINP_Helper::get_snippet_type();
		$supports     = [ 'title', 'revisions' ];

		// Add editor support for text and ad snippets.
		if ( WINP_SNIPPET_TYPE_TEXT === $snippet_type || WINP_SNIPPET_TYPE_AD === $snippet_type ) {
			$supports[] = 'editor';
		}

		$supports     = apply_filters( 'wbcr_inp_items_supports', $supports );
		$can_export   = WINP_Plugin::app()->get_api_object()->is_key();
		$menu_icon    = $this->get_menu_icon_url();
		$capabilities = $this->get_capabilities();

		$args = [
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => null,
			'menu_icon'           => $menu_icon,
			'capability_type'     => $this->name,
			'capabilities'        => $capabilities,
			'hierarchical'        => false,
			'supports'            => $supports,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => true,
			'show_in_nav_menus'   => false,
			'can_export'          => $can_export,
		];

		return $args;
	}

	/**
	 * Get menu icon URL.
	 *
	 * @return string Menu icon URL.
	 */
	private function get_menu_icon_url() {
		return $this->menu_icon;
	}

	/**
	 * Get custom capabilities map.
	 *
	 * @return array<string, string> Capabilities map.
	 */
	private function get_capabilities() {
		return [
			'edit_post'          => 'edit_' . $this->name,
			'read_post'          => 'read_' . $this->name,
			'delete_post'        => 'delete_' . $this->name,
			'delete_posts'       => 'delete_' . $this->name . 's',
			'edit_posts'         => 'edit_' . $this->name . 's',
			'edit_others_posts'  => 'edit_others_' . $this->name . 's',
			'publish_posts'      => 'publish_' . $this->name . 's',
			'read_private_posts' => 'read_private_' . $this->name . 's',
			'create_posts'       => 'edit_' . $this->name . 's',
		];
	}

	/**
	 * Print menu styles for Woody snippets.
	 *
	 * @return void
	 */
	public function print_menu_styles() {
		?>
		<!-- Woody Code Snippets -->
		<style>
			#menu-posts-wbcr-snippets .wp-menu-open .wp-menu-name {
				background: #242525;
			}
		</style>
		<!-- /Woody Code Snippets -->
		<?php
	}

	/**
	 * Print menu icon styles.
	 *
	 * @return void
	 */
	public function print_menu_icon_styles() {
		if ( empty( $this->menu_icon ) ) {
			return;
		}

		$icon_url   = $this->menu_icon;
		$icon_url32 = str_replace( '.png', '-32.png', $icon_url );

		?>
		<style type="text/css" media="screen">
			#menu-posts-<?php echo esc_attr( $this->name ); ?> .wp-menu-image {
				background: url('<?php echo esc_url( $icon_url ); ?>') no-repeat 10px -30px !important;
				overflow: hidden;
			}

			#menu-posts-<?php echo esc_attr( $this->name ); ?> .wp-menu-image:before {
				content: "" !important;
			}

			#menu-posts-<?php echo esc_attr( $this->name ); ?>:hover .wp-menu-image,
			#menu-posts-<?php echo esc_attr( $this->name ); ?>.wp-has-current-submenu .wp-menu-image {
				background-position: 10px 2px !important;
			}

			#icon-edit.icon32-posts-<?php echo esc_attr( $this->name ); ?> {
				background: url('<?php echo esc_url( $icon_url32 ); ?>') no-repeat;
			}
		</style>
		<?php
	}

	/**
	 * Customize post update messages.
	 *
	 * @param array<string, array<int, string|false>> $messages Existing messages.
	 * @return array<string, array<int, string|false>> Modified messages.
	 */
	public function updated_messages( $messages ) {
		global $post;

		if ( ! $post || $post->post_type !== $this->name ) {
			return $messages;
		}

		$singular = $this->singular_title;

		$messages[ $this->name ] = [
			0  => '', // Unused. Messages start at index 1.
			1  => $singular . ' ' . __( 'updated.', 'insert-php' ),
			2  => __( 'Custom field updated successfully.', 'insert-php' ),
			3  => __( 'Custom field deleted.', 'insert-php' ),
			4  => $singular . ' ' . __( 'updated.', 'insert-php' ),
			5  => isset( $_GET['revision'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				? $singular . ' ' . sprintf(
					/* translators: %s: revision title */
					__( 'restored to revision from %s', 'insert-php' ),
					wp_post_revision_title( (int) $_GET['revision'], false ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
				: false,
			6  => $singular . ' ' . __( 'created.', 'insert-php' ),
			7  => $singular . ' ' . __( 'saved.', 'insert-php' ),
			8  => $singular . ' ' . __( 'submitted.', 'insert-php' ),
			9  => $singular . ' ' . sprintf(
				/* translators: %s: scheduled date */
				__( 'scheduled for: %s', 'insert-php' ),
				'<strong>' . date_i18n( __( 'M j, Y @ G:i', 'insert-php' ), strtotime( $post->post_date ) ) . '</strong>'
			),
			10 => $singular . ' ' . __( 'draft updated.', 'insert-php' ),
		];

		return $messages;
	}

	/**
	 * Add capabilities to administrator role.
	 * Called on plugin activation.
	 *
	 * @return void
	 */
	public function add_capabilities() {
		if ( empty( $this->capabilities ) ) {
			return;
		}

		foreach ( $this->capabilities as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			$role->add_cap( 'edit_' . $this->name );
			$role->add_cap( 'read_' . $this->name );
			$role->add_cap( 'delete_' . $this->name );
			$role->add_cap( 'edit_' . $this->name . 's' );
			$role->add_cap( 'edit_others_' . $this->name . 's' );
			$role->add_cap( 'publish_' . $this->name . 's' );
			$role->add_cap( 'read_private_' . $this->name . 's' );
		}
	}

	/**
	 * Remove capabilities from all roles.
	 * Called on plugin deactivation.
	 *
	 * @return void
	 */
	public function remove_capabilities() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return;
		}

		$all_roles = $wp_roles->roles;

		foreach ( $all_roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			$role->remove_cap( 'edit_' . $this->name );
			$role->remove_cap( 'read_' . $this->name );
			$role->remove_cap( 'delete_' . $this->name );
			$role->remove_cap( 'edit_' . $this->name . 's' );
			$role->remove_cap( 'edit_others_' . $this->name . 's' );
			$role->remove_cap( 'publish_' . $this->name . 's' );
			$role->remove_cap( 'read_private_' . $this->name . 's' );
		}
	}
}