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
 * Returns a list of available roles.
 */
function wbcr_inp_ajax_get_user_roles() {
	global $wp_roles;

	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, 'intval' );

	check_admin_referer( 'wbcr_inp_snippet_' . $snippet_id . '_conditions_metabox' );

	$roles = $wp_roles->roles;

	$values = [];
	foreach ( $roles as $role_id => $role ) {
		$values[] = [
			'value' => $role_id,
			'title' => $role['name'],
		];
	}

	$values[] = [
		'value' => 'guest',
		'title' => __( 'Guest', 'insert-php' ),
	];

	$result = [
		'values' => $values,
	];

	echo json_encode( $result );
	exit;
}

add_action( 'wp_ajax_wbcr_inp_ajax_get_user_roles', 'wbcr_inp_ajax_get_user_roles' );

/**
 * Returns a list of public post types.
 */
function wbcr_inp_ajax_get_post_types() {

	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, 'intval' );

	check_admin_referer( 'wbcr_inp_snippet_' . $snippet_id . '_conditions_metabox' );

	$values     = [];
	$post_types = get_post_types( [ 'public' => true ], 'objects' );
	if ( ! empty( $post_types ) ) {
		foreach ( $post_types as $key => $value ) {
			$values[] = [
				'value' => $key,
				'title' => $value->label,
			];
		}
	}

	$result = [
		'values' => $values,
	];

	echo json_encode( $result );
	exit;
}

add_action( 'wp_ajax_wbcr_inp_ajax_get_post_types', 'wbcr_inp_ajax_get_post_types' );

/**
 * Returns a list of public taxonomies.
 */
function wbcr_inp_ajax_get_taxonomies() {

	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, 'intval' );

	check_admin_referer( 'wbcr_inp_snippet_' . $snippet_id . '_conditions_metabox' );

	$values     = [];
	$categories = get_categories( [ 'hide_empty' => false ] );

	if ( ! empty( $categories ) ) {
		foreach ( $categories as $cat ) {
			$values[] = [
				'value' => $cat->term_id,
				'title' => $cat->name,
			];
		}
	}

	$result = [
		'values' => $values,
	];

	echo json_encode( $result );
	exit;
}

add_action( 'wp_ajax_wbcr_inp_ajax_get_taxonomies', 'wbcr_inp_ajax_get_taxonomies' );

/**
 * Returns a list of page list values
 */
function wbcr_inp_ajax_get_page_list() {

	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	$snippet_id = WINP_HTTP::post( 'snippet_id', 0, 'intval' );

	check_admin_referer( 'wbcr_inp_snippet_' . $snippet_id . '_conditions_metabox' );

	$is_woo   = WINP_Helper::is_woo_active();
	$woo_desc = $is_woo ? '' : __( '(not active)', 'insert-php' );

	$result = [
		'values' => [
			__( 'Basic', 'insert-php' )                   => [
				[
					'value' => 'base_web',
					'title' => __( 'Entire Website', 'insert-php' ),
				],
				[
					'value' => 'base_sing',
					'title' => __( 'All Single Posts & Pages', 'insert-php' ),
				],
				[
					'value' => 'base_arch',
					'title' => __( 'All Archive Pages', 'insert-php' ),
				],
			],
			__( 'Special Pages', 'insert-php' )           => [
				[
					'value' => 'spec_404',
					'title' => __( '404 Page', 'insert-php' ),
				],
				[
					'value' => 'spec_search',
					'title' => __( 'Search Page', 'insert-php' ),
				],
				[
					'value' => 'spec_blog',
					'title' => __( 'Blog/Posts Page', 'insert-php' ),
				],
				[
					'value' => 'spec_front',
					'title' => __( 'Front Page', 'insert-php' ),
				],
				[
					'value' => 'spec_date',
					'title' => __( 'Date Archive', 'insert-php' ),
				],
				[
					'value' => 'spec_auth',
					'title' => __( 'Author Archive', 'insert-php' ),
				],
			],
			__( 'Posts', 'insert-php' )                   => [
				[
					'value' => 'post_all',
					'title' => __( 'All Posts', 'insert-php' ),
				],
				[
					'value' => 'post_arch',
					'title' => __( 'All Posts Archive', 'insert-php' ),
				],
				[
					'value' => 'post_cat',
					'title' => __( 'All Categories Archive', 'insert-php' ),
				],
				[
					'value' => 'post_tag',
					'title' => __( 'All Tags Archive', 'insert-php' ),
				],
			],
			__( 'Pages', 'insert-php' )                   => [
				[
					'value' => 'page_all',
					'title' => __( 'All Pages', 'insert-php' ),
				],
				[
					'value' => 'page_arch',
					'title' => __( 'All Pages Archive', 'insert-php' ),
				],
			],
			__( 'WooCommerce', 'insert-php' ) . $woo_desc => [
				[
					'value'    => 'woo_product',
					'title'    => __( 'Product', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_cart',
					'title'    => __( 'Cart Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_checkout',
					'title'    => __( 'Checkout Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_checkout_pay',
					'title'    => __( 'Checkout Payment Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_arch',
					'title'    => __( 'All Products Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_cat',
					'title'    => __( 'Product Category Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
				[
					'value'    => 'woo_tag',
					'title'    => __( 'Product Tag Page', 'insert-php' ),
					'disabled' => ! $is_woo,
				],
			],
		],
	];

	echo json_encode( $result );
	exit;
}

add_action( 'wp_ajax_wbcr_inp_ajax_get_page_list', 'wbcr_inp_ajax_get_page_list' );

/**
 * Save the Permalink slug
 */
function wbcr_inp_ajax_save_permalink() {

	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_die( - 1, 403 );
	}

	check_ajax_referer( 'winp-permalink', 'winp_permalink_nonce' );

	$code_id   = WINP_HTTP::post( 'code_id', 0 );
	$permalink = WINP_HTTP::post( 'permalink', null, true );
	$slug      = WINP_HTTP::post( 'new_slug', null, 'sanitize_file_name' );
	$filetype  = WINP_HTTP::post( 'filetype', 'css', true );

	WINP_Helper::updateMetaOption( $code_id, 'filetype', $filetype );

	if ( empty( $slug ) ) {
		$slug = (string) $code_id;
		WINP_Helper::updateMetaOption( $code_id, 'css_js_slug', '' );
	} else {
		WINP_Helper::updateMetaOption( $code_id, 'css_js_slug', $slug );
	}
	WINP_Plugin::app()->get_common_object()->edit_form_before_permalink( $slug, $permalink, $filetype );

	wp_die();
}

add_action( 'wp_ajax_winp_permalink', 'wbcr_inp_ajax_save_permalink' );

/**
 * Validate snippet code before saving (AJAX).
 * 
 * @return void
 */
function wbcr_inp_ajax_validate_snippet() {
	if ( ! WINP_Plugin::app()->current_user_car() ) {
		wp_send_json_error( [ 'message' => __( 'You don\'t have permission to perform this action. Contact your administrator.', 'insert-php' ) ], 403 );
	}

	$post_id = WINP_HTTP::post( 'post_id', 0, 'intval' );
	
	check_ajax_referer( 'winp_validate_snippet_' . $post_id, 'nonce' );

	$snippet_code = WINP_HTTP::post( 'snippet_code', '', false );
	$snippet_type = WINP_HTTP::post( 'snippet_type', WINP_SNIPPET_TYPE_PHP, true );

	// Only validate executable PHP snippets (not text, ad, css, js, html).
	if ( WINP_SNIPPET_TYPE_TEXT !== $snippet_type &&
		WINP_SNIPPET_TYPE_AD !== $snippet_type &&
		WINP_SNIPPET_TYPE_CSS !== $snippet_type &&
		WINP_SNIPPET_TYPE_JS !== $snippet_type &&
		WINP_SNIPPET_TYPE_HTML !== $snippet_type ) {

		$snippet_code = stripslashes( $snippet_code );
		
		if ( empty( $snippet_code ) ) {
			wp_send_json_success( [ 'valid' => true ] );
		}

		// Validate using the same logic as validate_code method.
		$validation_errors = [];
		
		// Set custom error handler to catch warnings and notices.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			function ( $errno, $errstr, $errfile, $errline ) use ( &$validation_errors ) {
				// Extract line number from eval'd code if present.
				if ( strpos( $errfile, "eval()'d code" ) !== false ) {
					// translators: %1$d is the line number, %2$s is the error message.
					$validation_errors[] = sprintf( __( 'Line %1$d: %2$s', 'insert-php' ), $errline, $errstr );
				} else {
					$validation_errors[] = $errstr;
				}
				return true; // Don't execute PHP internal error handler.
			}
		);

		ob_start();

		try {
			$result = WINP_SNIPPET_TYPE_UNIVERSAL === $snippet_type
				? eval( '?> ' . $snippet_code . ' <?php ' ) 
				: eval( $snippet_code );

			// Discard any output (echo/print statements are normal for snippets).
			ob_end_clean();

			// Restore error handler.
			restore_error_handler();

			// Check if any errors were caught.
			if ( ! empty( $validation_errors ) ) {
				// Show all errors, separated by line breaks.
				$error_message = implode( '<br>', $validation_errors );
				wp_send_json_error(
					[
						'valid'   => false,
						'message' => $error_message,
					] 
				);
			}

			if ( false === $result ) {
				wp_send_json_error(
					[
						'valid'   => false,
						'message' => __( 'The code contains syntax errors. Please review and fix them before saving.', 'insert-php' ),
					] 
				);
			}

			wp_send_json_success( [ 'valid' => true ] );

		} catch ( ParseError $e ) {
			ob_end_clean();
			restore_error_handler();
			wp_send_json_error(
				[
					'valid'   => false,
					// translators: %1$d is the line number, %2$s is the error message.
					'message' => sprintf( __( 'Syntax error on line %1$d: %2$s', 'insert-php' ), $e->getLine(), $e->getMessage() ),
				] 
			);
		} catch ( Throwable $e ) {
			ob_end_clean();
			restore_error_handler();
			
			// Try to extract line number from the error message.
			$error_message = $e->getMessage();
			$line          = $e->getLine();
			
			// For fatal errors in eval'd code, extract the actual line number.
			if ( strpos( $e->getFile(), "eval()'d code" ) !== false ) {
				wp_send_json_error(
					[
						'valid'   => false,
						// translators: %1$d is the line number, %2$s is the error message.
						'message' => sprintf( __( 'Error on line %1$d: %2$s', 'insert-php' ), $line, $error_message ),
					] 
				);
			} else {
				wp_send_json_error(
					[
						'valid'   => false,
						// translators: %s is the error message.
						'message' => sprintf( __( 'Error: %s', 'insert-php' ), $error_message ),
					] 
				);
			}
		}
	} else {
		// No validation needed for this type.
		wp_send_json_success( [ 'valid' => true ] );
	}
}

add_action( 'wp_ajax_wbcr_inp_ajax_validate_snippet', 'wbcr_inp_ajax_validate_snippet' );
