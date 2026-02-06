<?php
/**
 * Admin boot
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Инициализации метабоксов и страницы "о плагине".
 *
 * Этот хук реализует условную логику, при которой пользователь переодически будет
 * видет страницу "О плагине", а конкретно при активации и обновлении плагина.
 */
add_action(
	'admin_init',
	function () {
		require_once WINP_PLUGIN_DIR . '/admin/metaboxes/snippet-metabox.php';
	} 
);

/**
 * Enqueue scripts
 */
function wbcr_inp_enqueue_scripts() {
	global $pagenow;

	$screen = get_current_screen();

	if ( ( 'post-new.php' == $pagenow || 'post.php' == $pagenow ) && WINP_SNIPPETS_POST_TYPE == $screen->post_type ) {
		wp_enqueue_script(
			'wbcr-inp-admin-scripts',
			WINP_PLUGIN_URL . '/admin/assets/js/scripts.js',
			[
				'jquery',
				'jquery-ui-tooltip',
			],
			WINP_PLUGIN_VERSION
		);
	}
}

/**
 * Asset scripts for the tinymce editor
 *
 * @param string $hook
 */
function wbcr_inp_enqueue_tinymce_assets( $hook ) {
	$pages = [
		'post.php',
		'post-new.php',
		'widgets.php',
	];

	if ( ! in_array( $hook, $pages ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_script( 'wbcr-inp-tinymce-button-widget', WINP_PLUGIN_URL . '/admin/assets/js/tinymce4.4.js', [ 'jquery' ], WINP_PLUGIN_VERSION, true );
}

add_action( 'admin_enqueue_scripts', 'wbcr_inp_enqueue_tinymce_assets' );
add_action( 'admin_enqueue_scripts', 'wbcr_inp_enqueue_scripts' );

/**
 * Adds js variable required for shortcodes.
 *
 * @since 1.1.0
 * @see   before_wp_tiny_mce
 */
function wbcr_inp_tinymce_data( $hook ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	// styles for the plugin shorcodes
	$shortcode_icon  = WINP_PLUGIN_URL . '/admin/assets/img/shortcode-icon5.png';
	$shortcode_title = __( 'Woody Code Snippets', 'insert-php' );

	$result                  = WINP_Helper::get_shortcode_data( true );
	$shortcode_snippets_json = json_encode( $result );
	?>
	<!-- <?php echo esc_html__( 'Woody Code Snippets', 'insert-php' ); ?> for tinymce -->
	<style>
		i.wbcr-inp-shortcode-icon {
			background: url("<?php echo $shortcode_icon; ?>") center no-repeat;
		}
	</style>
	<script>
		var wbcr_inp_tinymce_snippets_button_title = '<?php echo $shortcode_title; ?>';
		var wbcr_inp_post_tinymce_nonce = '<?php echo wp_create_nonce( 'wbcr_inp_tinymce_post_nonce' ); ?>';
		var wbcr_inp_shortcode_snippets = <?php echo $shortcode_snippets_json; ?>;
	</script>
	<!-- /end <?php echo esc_html__( 'Woody Code Snippets', 'insert-php' ); ?> for tinymce -->
	<?php
}

// Defer hook registration until init to prevent early translation loading (WP 6.7+)
add_action(
	'init',
	function () {
		add_action( 'admin_print_scripts-post.php', 'wbcr_inp_tinymce_data' );
		add_action( 'admin_print_scripts-post-new.php', 'wbcr_inp_tinymce_data' );
		add_action( 'admin_print_scripts-widgets.php', 'wbcr_inp_tinymce_data' );
	} 
);

/**
 * Deactivate snippet on trashed
 *
 * @param $post_id
 *
 * @since 2.0.6
 */
function wbcr_inp_trash_post( $post_id ) {
	$post_type = get_post_type( $post_id );
	if ( $post_type == WINP_SNIPPETS_POST_TYPE ) {
		WINP_Helper::updateMetaOption( $post_id, 'snippet_activate', 0 );
	}
}

add_action( 'wp_trash_post', 'wbcr_inp_trash_post' );

/**
 * Removes the default 'new item' from the admin menu to add own page 'new item' later.
 *
 * @param $menu
 *
 * @return mixed
 * @see menu_order
 */
function wbcr_inp_remove_new_item( $menu ) {
	global $submenu;

	if ( ! isset( $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ] ) ) {
		return $menu;
	}
	unset( $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ][10] );

	return $menu;
}

add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'admin_menu', 'wbcr_inp_remove_new_item', 1 );

/**
 * Reorder submenu items to place '+ Add Snippet' as second item
 *
 * @param array<int|string, mixed> $menu Menu items.
 *
 * @return array<int|string, mixed>
 */
function wbcr_inp_reorder_submenu_items( $menu ) {
	global $submenu;

	if ( ! isset( $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ] ) ) {
		return $menu;
	}

	$snippet_submenu = $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ];
	$new_item_page   = null;
	$new_item_key    = null;

	foreach ( $snippet_submenu as $key => $item ) {
		if ( strpos( $item[2], 'new-item-' ) !== false ) {
			$new_item_page = $item;
			$new_item_key  = $key;
			break;
		}
	}

	if ( null !== $new_item_page ) {
		unset( $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ][ $new_item_key ] );
		$submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ][6] = $new_item_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		ksort( $submenu[ 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ] );
	}

	return $menu;
}

add_filter( 'admin_menu', 'wbcr_inp_reorder_submenu_items', 999 );

/**
 * If the user tried to get access to the default 'new item',
 * redirects forcibly to our page 'new item'.
 *
 * @see current_screen
 */
function wbcr_inp_redirect_to_new_item() {
	$screen = get_current_screen();

	if ( empty( $screen ) ) {
		return;
	}
	if ( 'add' !== $screen->action || 'post' !== $screen->base || WINP_SNIPPETS_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$winp_item = WINP_HTTP::get( 'winp_item', null );
	if ( ! is_null( $winp_item ) ) {
		return;
	}

	$url = admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE . '&page=winp-new-item' );

	wp_safe_redirect( $url );

	exit;
}

add_action( 'current_screen', 'wbcr_inp_redirect_to_new_item' );
