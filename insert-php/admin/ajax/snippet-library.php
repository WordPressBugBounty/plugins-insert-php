<?php
/**
 * Ajax requests handler
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get snippet library table content
 */
function wbcr_inp_ajax_get_snippet_library() {
	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	check_ajax_referer( 'winp-snippet-library', 'winp_nonce' );
	?>
	<div class="wrap">
		<form id="winp-snippet-library-list" method="get">
			<input type="hidden" name="page" value="<?php echo WINP_HTTP::request( 'page', 1, true ); ?>"/>
			<input type="hidden" name="order" value="<?php echo WINP_HTTP::request( 'order', 'asc', true ); ?>"/>
			<input type="hidden" name="orderby" value="<?php echo WINP_HTTP::request( 'orderby', 'title', true ); ?>"/>
			<div id="winp-snippet-library-table" style="">
				<p><?php _e( 'Loading snippets...', 'insert-php' ); ?></p>
				<?php
				wp_nonce_field( 'winp-ajax-custom-list-nonce', 'winp_ajax_custom_list_nonce' );
				?>
			</div>
		</form>
	</div>
	<?php
	wp_die();
}

add_action( 'wp_ajax_winp_get_snippet_library', 'wbcr_inp_ajax_get_snippet_library' );

/**
 * Snippet create from library
 */
function wbcr_inp_ajax_snippet_create() {
	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	check_ajax_referer( 'winp-ajax-custom-list-nonce', 'winp_ajax_custom_list_nonce' );

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, true );
	$post_id    = WINP_HTTP::post( 'post_id', 0, true );
	$common     = WINP_HTTP::post( 'common', 0 );
	$result     = WINP_Plugin::app()->get_api_object()->create_from_library( $snippet_id, $post_id, $common );

	echo( $result );
	exit();
}

add_action( 'wp_ajax_winp_snippet_create', 'wbcr_inp_ajax_snippet_create' );

/**
 * Snippet delete from library
 */
function wbcr_inp_ajax_snippet_delete() {
	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, true );

	check_ajax_referer( 'winp-ajax-snippet-delete-' . $snippet_id, 'winp_ajax_snippet_delete_nonce' );

	$result = WINP_Plugin::app()->get_api_object()->delete_snippet( $snippet_id );

	echo( $result );
	exit();
}

add_action( 'wp_ajax_winp_snippet_delete', 'wbcr_inp_ajax_snippet_delete' );

/**
 * Action wp_ajax for fetching the first time table structure
 */
function wbcr_inp_ajax_sts_display_callback() {
	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	check_ajax_referer( 'winp-ajax-custom-list-nonce', 'winp_ajax_custom_list_nonce' );

	require_once WINP_PLUGIN_DIR . '/admin/includes/class.snippets.table.php';

	// Create an instance of our package class...
	$snippet_list_table = new WINP_Snippet_Library_Table( true );
	// Fetch, prepare, sort, and filter our data...
	$snippet_list_table->prepare_items();
	ob_start();
	$snippet_list_table->display();
	$display = ob_get_clean();
	die(
		json_encode(
			[
				'display' => $display,
			] 
		) 
	);
}

add_action( 'wp_ajax_winp_sts_display', 'wbcr_inp_ajax_sts_display_callback' );


