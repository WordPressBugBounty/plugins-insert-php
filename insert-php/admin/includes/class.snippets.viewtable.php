<?php
/**
 * Snippets View Table
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_SnippetsViewTable
 *
 * Handles the custom columns and display for snippets list table.
 */
class WINP_SnippetsViewTable {

	/**
	 * Custom columns.
	 *
	 * @var array<string, string>
	 */
	private $columns = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks.
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Define columns.
		$this->columns = [
			'winp_actions'      => __( 'Status', 'insert-php' ),
			'title'             => __( 'Snippet title', 'insert-php' ),
			'winp_description'  => __( 'Description', 'insert-php' ),
			'winp_where_use'    => __( 'Location', 'insert-php' ),
			'winp_taxonomy'     => __( 'Tags', 'insert-php' ),
			'winp_priority'     => __( 'Priority', 'insert-php' ),
			'winp_snippet_type' => '',
		];

		// Columns.
		add_filter( 'manage_edit-' . WINP_SNIPPETS_POST_TYPE . '_columns', [ $this, 'setup_columns' ] );
		add_action( 'manage_' . WINP_SNIPPETS_POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'manage_edit-' . WINP_SNIPPETS_POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );

		// Scripts and styles.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Row actions.
		add_filter( 'post_row_actions', [ $this, 'modify_row_actions' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . WINP_SNIPPETS_POST_TYPE, [ $this, 'modify_bulk_actions' ] );

		// AJAX actions.
		add_action( 'wp_ajax_change_priority', [ $this, 'change_priority' ] );
		add_action( 'wp_ajax_change_snippet_status', [ $this, 'change_snippet_status' ] );

		// Run actions on admin_init to ensure proper loading order.
		add_action( 'admin_init', [ $this, 'run_actions' ] );
	}

	/**
	 * Setup custom columns.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function setup_columns( $columns ) {
		unset( $columns ); // Unused, we define our own columns.
		$new_columns       = [];
		$new_columns['cb'] = '<input type="checkbox" />';

		foreach ( $this->columns as $id => $title ) {
			$new_columns[ $id ] = $title;
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		$post        = get_post( $post_id );
		$method_name = 'column_' . $column;

		if ( method_exists( $this, $method_name ) && is_callable( [ $this, $method_name ] ) ) {
			$this->{$method_name}( $post );
		}
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || WINP_SNIPPETS_POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'winp-list-table', WINP_PLUGIN_URL . '/admin/assets/css/list-table.css', [], WINP_PLUGIN_VERSION );
		wp_enqueue_script( 'winp-snippet-list', WINP_PLUGIN_URL . '/admin/assets/js/snippet-list.js', [ 'jquery' ], WINP_PLUGIN_VERSION, true );
		wp_localize_script( 'winp-snippet-list', 'winp_ajax', [ 'nonce' => wp_create_nonce( 'winp_ajax' ) ] );
	}

	/**
	 * Modify row actions.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param WP_Post               $post    Post object.
	 * @return array<string, string> Modified actions.
	 */
	public function modify_row_actions( $actions, $post ) {
		if ( WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		// Remove quick edit for non-public types.
		unset( $actions['inline hide-if-no-js'] );

		return $actions;
	}

	/**
	 * Modify bulk actions.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @return array<string, string> Modified actions.
	 */
	public function modify_bulk_actions( $actions ) {
		// Remove bulk edit.
		unset( $actions['edit'] );

		return $actions;
	}

	/**
	 * Column 'Type'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_snippet_type( $post ) {
		$type  = WINP_Helper::getMetaOption( $post->ID, 'snippet_type', WINP_SNIPPET_TYPE_PHP );
		$class = 'wbcr-inp-type-' . esc_attr( $type );
		$type  = $type == 'universal' ? 'uni' : $type;
		$type  = $type == 'advert' ? 'ad' : $type;

		echo '<div class="wbcr-inp-snippet-type-label ' . esc_attr( $class ) . '">' . esc_html( $type ) . '</div>';
	}

	/**
	 * Column 'Description'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_description( $post ) {
		echo esc_html( WINP_Helper::getMetaOption( $post->ID, 'snippet_description' ) );
	}

	/**
	 * Column 'Where_use'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_where_use( $post ) {
		$value = WINP_Helper::get_where_use_text( $post );

		$is_shortcode = ( strpos( $value, '[' ) === 0 && strrpos( $value, ']' ) === strlen( $value ) - 1 );

		if ( $is_shortcode ) {
			echo "<div style='position: relative; display: inline-flex; align-items: center; width: 100%;'>";
			echo "<span class='dashicons dashicons-clipboard' style='position: absolute; left: 8px; pointer-events: none; color: #2271b1;'></span>";
			echo "<input type='text' name='wbcr_inp_shortcode_input' class='wbcr_inp_shortcode_input' value='" . esc_attr( $value ) . "' readonly='readonly' style='cursor: pointer; padding-left: 35px; width: 100%;'>";
			echo '</div>';
		} else {
			echo esc_html( $value );
		}
	}

	/**
	 * Column 'Taxonomy'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_taxonomy( $post ) {
		$post_cat = get_the_terms( $post->ID, WINP_SNIPPETS_TAXONOMY );
		$result   = [];
		if ( is_array( $post_cat ) ) {
			foreach ( $post_cat as $item ) {
				$href     = admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE . "&winp_filter_tag={$item->slug}" );
				$result[] = "<a href='{$href}' class='winp-taxonomy-href'>{$item->name}</a>";
			}
		}
		echo implode( ', ', $result );
	}

	/**
	 * Column 'Priority'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_priority( $post ) {
		$snippet_priority = WINP_Helper::getMetaOption( $post->ID, 'snippet_priority' );
		echo "<input type='number' name='wbcr_inp_input_priority' class='wbcr_inp_input_priority'
 			  data-snippet-id='{$post->ID}' value='{$snippet_priority}'>";
	}

	/**
	 * Column 'Actions'
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function column_winp_actions( $post ) {
		$post_id     = (int) $post->ID;
		$is_activate = (int) WINP_Helper::getMetaOption( $post_id, 'snippet_activate', 0 );
		$css_class   = 'winp-inactive';

		if ( $is_activate ) {
			$css_class = '';
		}

		$url = wp_nonce_url(
			admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE . '&amp;post=' . $post_id . '&amp;action=wbcr_inp_activate_snippet' ),
			'wbcr_inp_snippert_' . $post_id . '_action_nonce'
		);

		echo '<a class="winp-snippet-active-switch ' . esc_attr( $css_class ) . '" id="winp-snippet-status-switch" data-snippet-id="' . esc_attr( (string) $post_id ) . '" href="' . esc_url( $url ) . '">&nbsp;</a>';
	}

	/**
	 * Activate/Deactivate snippet
	 *
	 * @return void
	 */
	public function run_actions() {
		if ( WINP_HTTP::get( 'post_type', '', true ) == WINP_SNIPPETS_POST_TYPE ) {
			$post   = WINP_HTTP::get( 'post', 0 );
			$action = WINP_HTTP::get( 'action', '', 'sanitize_key' );

			if ( ! empty( $action ) && ! empty( $post ) && 'wbcr_inp_activate_snippet' == $action ) {
				$post_id = (int) $post;
				$wpnonce = WINP_HTTP::get( '_wpnonce', '' );

				if ( ! wp_verify_nonce( $wpnonce, 'wbcr_inp_snippert_' . $post_id . '_action_nonce' ) || ! WINP_Plugin::app()->current_user_car() ) {
					wp_die( 'Permission error. You can not edit this page.' );
				}

				$is_activate   = (int) WINP_Helper::getMetaOption( $post_id, 'snippet_activate', 0 );
				$snippet_scope = WINP_Helper::getMetaOption( $post_id, 'snippet_scope' );
				$snippet_type  = WINP_Helper::get_snippet_type( $post_id );

				/**
				 * Prevent activation of the snippet if it contains an error. This will not allow the user to break his site.
				 *
				 * @since 2.0.5
				 */
				if ( ( 'evrywhere' == $snippet_scope || 'auto' == $snippet_scope ) && $snippet_type != WINP_SNIPPET_TYPE_TEXT && $snippet_type != WINP_SNIPPET_TYPE_AD && $snippet_type != WINP_SNIPPET_TYPE_CSS && $snippet_type != WINP_SNIPPET_TYPE_JS && ! $is_activate ) {
					if ( WINP_Plugin::app()->get_execute_object()->getSnippetError( $post_id ) ) {
						wp_safe_redirect(
							add_query_arg(
								[
									'action' => 'edit',
									'post'   => $post_id,
									'wbcr_inp_save_snippet_result' => 'code-error',
								],
								admin_url( 'post.php' ) 
							) 
						);
						exit;
					}
				}

				$status = ! $is_activate;

				update_post_meta( $post_id, 'wbcr_inp_snippet_activate', $status );

				$redirect_url = add_query_arg(
					[
						'post_type'                => WINP_SNIPPETS_POST_TYPE,
						'wbcr_inp_snippet_updated' => 1,
					],
					admin_url( 'edit.php' ) 
				);

				wp_safe_redirect( $redirect_url );
				exit;
			}
		}
	}

	/**
	 * Make columns sortable.
	 *
	 * @param array<string, string> $sortable_columns Existing sortable columns.
	 * @return array<string, string> Modified sortable columns.
	 */
	public function sortable_columns( $sortable_columns ) {
		$sortable_columns['winp_priority'] = 'winp_priority';

		return $sortable_columns;
	}

	/**
	 * AJAX action for change priority.
	 *
	 * @return void
	 */
	public function change_priority() {
		check_ajax_referer( 'winp_ajax' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'error_message' => __( "You don't have permission to edit this.", 'insert-php' ) ] );
		}

		if ( isset( $_POST['snippet_id'] ) && isset( $_POST['priority'] ) ) {
			if ( is_numeric( $_POST['priority'] ) ) {
				WINP_Helper::updateMetaOption( $_POST['snippet_id'], 'snippet_priority', $_POST['priority'] );

				wp_send_json(
					[
						'message' => __( 'Priority successfully changed', 'insert-php' ),
					] 
				);
			} else {
				wp_send_json(
					[
						'error_message' => __( 'Priority was not changed. It must be a number.', 'insert-php' ),
					] 
				);
			}       
		} else {
			wp_send_json(
				[
					'error_message' => __( 'Priority is not changed!', 'insert-php' ),
				] 
			);
		}
	}

	/**
	 * AJAX action for change snippet status.
	 *
	 * @return void
	 */
	public function change_snippet_status() {
		check_ajax_referer( 'winp_ajax' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [ 'error_message' => __( "You don't have permission to edit this.", 'insert-php' ) ] );
		}

		if ( isset( $_POST['snippet_id'] ) ) {
			$snippet_id    = $_POST['snippet_id'];
			$is_activate   = (int) WINP_Helper::getMetaOption( $snippet_id, 'snippet_activate', 0 );
			$snippet_scope = WINP_Helper::getMetaOption( $snippet_id, 'snippet_scope' );
			$snippet_type  = WINP_Helper::get_snippet_type( $snippet_id );

			/**
			 * Prevent activation of the snippet if it contains an error. This will not allow the user to break his site.
			 *
			 * @since 2.0.5
			 */
			if ( ( 'evrywhere' == $snippet_scope || 'auto' == $snippet_scope ) && $snippet_type != WINP_SNIPPET_TYPE_TEXT && $snippet_type != WINP_SNIPPET_TYPE_AD && $snippet_type != WINP_SNIPPET_TYPE_CSS && $snippet_type != WINP_SNIPPET_TYPE_JS && ! $is_activate ) {
				if ( WINP_Plugin::app()->get_execute_object()->getSnippetError( $snippet_id ) ) {
					wp_send_json(
						[
							'alert'         => true,
							'error_message' => __( 'This snippet was not activated due to code errors. Please fix the errors and try again.', 'insert-php' ),
						] 
					);
				}
			}

			$status = ! $is_activate;

			$ok = update_post_meta( $snippet_id, 'wbcr_inp_snippet_activate', $status );

			if ( $ok ) {
				wp_send_json(
					[
						'message' => __( 'Snippet status changed', 'insert-php' ),
					] 
				);
			} else {
				wp_send_json(
					[
						'error_message' => __( 'Snippet status could not be changed. Please try again or check your permissions.', 'insert-php' ),
					] 
				);

			}       
		} else {
			wp_send_json(
				[
					'error_message' => __( 'Could not update snippet status. Please refresh and try again.', 'insert-php' ),
				] 
			);
		}
	}
}
