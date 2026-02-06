<?php
/**
 * Export/clone snippet
 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_Actions_Snippet
 */
class WINP_Actions_Snippet {

	/**
	 * WINP_Actions_Snippet constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register hooks
	 * 
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'post_row_actions', [ $this, 'post_row_actions' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . WINP_SNIPPETS_POST_TYPE, [ $this, 'action_bulk_edit_post' ] );
		add_filter(
			'handle_bulk_actions-edit-' . WINP_SNIPPETS_POST_TYPE,
			[
				$this,
				'handle_action_bulk_edit_post',
			],
			10,
			3 
		);

		add_action( 'post_submitbox_start', [ $this, 'post_submitbox_start' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'current_screen', [ $this, 'current_screen' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Get export url
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_export_url( $post_id ) {
		$url = admin_url( 'post.php?post=' . $post_id );

		return add_query_arg(
			[
				'action'   => 'export',
				'_wpnonce' => wp_create_nonce( 'WINP_Actions_Snippet_' . $post_id ),
			],
			$url 
		);
	}

	/**
	 * Get close url
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_close_url( $post_id ) {
		$url = admin_url( 'post.php?post=' . $post_id );

		return add_query_arg(
			[
				'action'   => 'close',
				'_wpnonce' => wp_create_nonce( 'winp_close_snippet_' . $post_id ),
			],
			$url 
		);
	}

	/**
	 * Get clone url
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	private function get_clone_url( $post_id ) {
		$url = admin_url( 'post.php?post=' . $post_id );

		return add_query_arg(
			[
				'action'   => 'clone',
				'_wpnonce' => wp_create_nonce( 'winp_clone_snippet_' . $post_id ),
			],
			$url 
		);
	}

	/**
	 * Post row actions
	 *
	 * @param array<string, string> $actions Array of row actions.
	 * @param WP_Post               $post  WP_Post object.
	 *
	 * @return mixed
	 */
	public function post_row_actions( $actions, $post ) {
		if ( WINP_SNIPPETS_POST_TYPE === $post->post_type ) {
			$export_link = $this->get_export_url( $post->ID );
			$clone_link  = $this->get_clone_url( $post->ID );

			if ( isset( $actions['trash'] ) ) {
				$trash = $actions['trash'];
				unset( $actions['trash'] );
			}

			$actions['export'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $export_link ), esc_html( __( 'Export', 'insert-php' ) ) );
			$actions['clone']  = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $clone_link ), esc_html( __( 'Clone', 'insert-php' ) ) );

			if ( isset( $trash ) ) {
				$actions['trash'] = $trash;
			}
		}

		return $actions;
	}

	/**
	 * Action bulk_edit_post
	 *
	 * @param array<string, string> $bulk_actions Array of bulk actions.
	 *
	 * @return mixed
	 */
	public function action_bulk_edit_post( $bulk_actions ) {
		$bulk_actions['activate']   = __( 'Activate', 'insert-php' );
		$bulk_actions['deactivate'] = __( 'Deactivate', 'insert-php' );
		$bulk_actions['exportsnp']  = __( 'Export', 'insert-php' );

		// Don't show delete action for posts already in trash.
		$current_screen = get_current_screen();
		if ( ! $current_screen || ( isset( $_GET['post_status'] ) && 'trash' !== $_GET['post_status'] ) || ( ! isset( $_GET['post_status'] ) ) ) {
			$bulk_actions['deletesnp'] = __( 'Delete', 'insert-php' );
			$bulk_actions['clonenp']   = __( 'Clone', 'insert-php' );
		}

		return $bulk_actions;
	}

	/**
	 * Handle action bulk edit post
	 *
	 * @param string     $redirect_to URL to redirect to.
	 * @param string     $doaction Do action.
	 * @param array<int> $post_ids Array of post IDs.
	 *
	 * @return mixed
	 */
	public function handle_action_bulk_edit_post( $redirect_to, $doaction, $post_ids ) {
		if ( ! WINP_Plugin::app()->current_user_car() ) {
			return false;
		}

		check_admin_referer( 'bulk-posts' );

		$actions = [
			'exportsnp'  => 1,
			'deletesnp'  => 1,
			'deactivate' => 1,
			'activate'   => 1,
			'clonenp'    => 1,
		];

		if ( ! isset( $actions[ $doaction ] ) ) {
			return $redirect_to;
		}

		if ( count( $post_ids ) ) {
			switch ( $doaction ) {
				case 'exportsnp':
					$this->quick_export_snippets( $post_ids );
					break;

				case 'clonenp':
					$this->clone_snippets( $post_ids );
					break;

				case 'deletesnp':
					$this->delete_snippets( $post_ids );
					break;

				case 'deactivate':
					$this->deactivate_snippets( $post_ids );
					break;

				case 'activate':
					$this->activate_snippets( $post_ids );
					break;
			}
		}

		return $redirect_to;
	}

	/**
	 * Post submitbox start
	 * 
	 * @return void
	 */
	public function post_submitbox_start() {
		global $post;

		if ( $post && WINP_SNIPPETS_POST_TYPE == $post->post_type ) {
			if ( WINP_Helper::getMetaOption( $post->ID, 'snippet_draft', false ) ) {
				$close_link = $this->get_close_url( $post->ID );
				echo "<div id='winp-close-action'>" . sprintf( '<a href="%1$s" class="button button-large">%2$s</a>', esc_url( $close_link ), esc_html( __( 'Close', 'insert-php' ) ) ) . '</div>';
			} else {
				$export_link = $this->get_export_url( $post->ID );
				echo "<div id='winp-export-action'>" . sprintf( '<a href="%1$s">%2$s</a>', esc_url( $export_link ), esc_html( __( 'Export', 'insert-php' ) ) ) . '</div>';
			}
		}
	}

	/**
	 * Prepare data
	 *
	 * @param array<int> $ids Snippet IDs.
	 *
	 * @return array<mixed>
	 */
	private function prepare_data( $ids ) {
		$snippets = [];

		if ( count( $ids ) ) {
			foreach ( $ids as $id ) {
				$post    = get_post( $id );

				if ( ! $post ) {
					continue;
				}

				$snippet = [
					'name'            => $post->post_name,
					'title'           => $post->post_title,
					'content'         => $post->post_content,
					'location'        => $this->get_meta( $id, 'snippet_location' ),
					'type'            => $this->get_meta( $id, 'snippet_type' ),
					'filters'         => $this->get_meta( $id, 'snippet_filters' ),
					'changed_filters' => $this->get_meta( $id, 'changed_filters' ),
					'scope'           => $this->get_meta( $id, 'snippet_scope' ),
					'priority'        => $this->get_meta( $id, 'snippet_priority' ),
					'description'     => $this->get_meta( $id, 'snippet_description' ),
					'attributes'      => $this->get_meta( $id, 'snippet_tags' ),
					'tags'            => $this->get_taxonomy_tags( $id ),
				];

				$snippets[] = apply_filters( 'wbcr/inp/snippet/prepare_data', $snippet, $id, $this );
			}
		}

		return $snippets;
	}

	/**
	 * Get file name
	 *
	 * @param string       $format File format.
	 * @param array<int>   $ids Snippet IDs.
	 * @param array<mixed> $snippets Snippets data.
	 *
	 * @return string
	 */
	public function get_filename( $format, $ids, $snippets ) {
		$snippets = empty( $snippets ) ? $this->prepare_data( $ids ) : $snippets;

		/* Build the export filename */
		if ( 1 == count( $ids ) ) {
			$name  = $snippets[0]['title'];
			$title = strtolower( $name );
		} else {
			/* Otherwise, use the site name as set in Settings > General */
			$title = strtolower( get_bloginfo( 'name' ) );
		}

		$filename = "{$title}.php-code-snippets.{$format}";

		return $filename;
	}

	/**
	 * Export snippets - prepare and return export data
	 *
	 * @param array<int> $ids Snippet IDs to export.
	 * @param bool       $zip Whether to create a zip file.
	 * @return array<mixed> Export data with filename, data, and count.
	 */
	public function export_snippets( $ids, $zip = false ) {
		$snippets = $this->prepare_data( $ids );
		$filename = $this->get_filename( 'json', $ids, $snippets );

		$data = [
			'generator'    => 'PHP Code Snippets v' . WINP_PLUGIN_VERSION,
			'date_created' => gmdate( 'Y-m-d H:i' ),
			'snippets'     => $snippets,
		];

		if ( $zip ) {
			$zipname    = str_replace( '.json', '.zip', $filename );
			$upload_dir = wp_upload_dir();
			$zippath    = $upload_dir['path'] . '/' . $zipname;

			$zip_archive = new ZipArchive();
			if ( true === $zip_archive->open( $zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				// Add individual JSON file for each snippet.
				foreach ( $snippets as $snippet ) {
					$snippet_data = [
						'generator'    => 'PHP Code Snippets v' . WINP_PLUGIN_VERSION,
						'date_created' => gmdate( 'Y-m-d H:i' ),
						'snippets'     => [ $snippet ],
					];
					$json_content = wp_json_encode( $snippet_data, JSON_PRETTY_PRINT );

					if ( false === $json_content ) {
						continue;
					}

					$snippet_filename = sanitize_file_name( strtolower( $snippet['title'] ) ) . '.php-code-snippets.json';
					$zip_archive->addFromString( $snippet_filename, $json_content );
				}

				$zip_archive->close();
				// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Reading local file from wp_upload_dir(), not remote.
				$zip_content = file_get_contents( $zippath );

				// Cleanup temporary file.
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Deleting temporary files in wp_upload_dir().
				unlink( $zippath );

				return [
					'filename' => $zipname,
					'data'     => $zip_content,
					'count'    => count( $snippets ),
					'is_zip'   => true,
				];
			}
		}

		return [
			'filename' => $filename,
			'data'     => $data,
			'count'    => count( $snippets ),
			'is_zip'   => false,
		];
	}

	/**
	 * Export snippets in JSON format
	 *
	 * @param array<int> $ids Snippet IDs.
	 * 
	 * @return void
	 */
	public function quick_export_snippets( $ids ) {
		$snippets = $this->export_snippets( $ids, count( $ids ) > 1 );

		$filename  = $snippets['filename'];
		$data      = $snippets['data'];
		$is_zip    = $snippets['is_zip'];
		$mime_type = $is_zip ? 'application/zip' : 'application/json';

		/* Set HTTP headers */
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );
		header( "Content-Type: $mime_type; charset=" . get_bloginfo( 'charset' ) );

		// For zip files, output raw binary data. For JSON, encode the data.
		if ( $is_zip ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary zip file content, escaping would corrupt the file.
			echo $data;
		} else {
			echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		}
		exit;
	}

	/**
	 * Clone snippets
	 *
	 * @param array<int> $ids Snippet IDs.
	 * 
	 * @return void
	 */
	public function clone_snippets( $ids ) {
		$snippets = $this->prepare_data( $ids );

		if ( $snippets ) {
			foreach ( $snippets as $snippet ) {
				$data = [
					'post_title'   => $snippet['title'] . ' copy',
					'post_content' => $snippet['content'],
					'post_status'  => 'draft',
					'post_type'    => WINP_SNIPPETS_POST_TYPE,
				];

				$snippet['id'] = wp_insert_post( $data );

				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_location', $snippet['location'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_type', $snippet['type'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_filters', $snippet['filters'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_changed_filters', $snippet['changed_filters'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_scope', $snippet['scope'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_priority', $snippet['priority'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_description', $snippet['description'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_tags', $snippet['attributes'] );
				update_post_meta( $snippet['id'], 'wbcr_inp_snippet_activate', 0 );
				$this->update_taxonomy_tags( $snippet['id'], $snippet['tags'] );

				do_action( 'wbcr/inp/snippet/clone_meta', $snippet['id'], $snippet, $this );
			}
		}
	}

	/**
	 * Action admin_init
	 * 
	 * @return void
	 */
	public function admin_init() {
		if ( ! WINP_Plugin::app()->current_user_car() ) {
			return;
		}

		$post_id = WINP_HTTP::get( 'post', 0, 'intval' );
		$action  = WINP_HTTP::get( 'action' );

		if ( ! empty( $action ) && ! empty( $post_id ) ) {
			switch ( $action ) {
				case 'export':
					check_admin_referer( 'WINP_Actions_Snippet_' . $post_id );
					$this->quick_export_snippets( [ $post_id ] );
					break;

				case 'clone':
					check_admin_referer( 'winp_clone_snippet_' . $post_id );
					$this->clone_snippets( [ $post_id ] );
					wp_safe_redirect( admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ) );
					exit();

				case 'close':
					check_admin_referer( 'winp_close_snippet_' . $post_id );
					wp_delete_post( $post_id );
					wp_safe_redirect( admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE . '&page=snippet-library' ) );
					exit();

				default:
					return;
			}
		}
	}

	/**
	 * Action current_screen
	 * Add script for disabled export button
	 * 
	 * @return void
	 */
	public function current_screen() {
		$current_screen = get_current_screen();

		if ( null === $current_screen ) {
			return;
		}

		if ( 'edit-wbcr-snippets' === $current_screen->id && WINP_SNIPPETS_POST_TYPE === $current_screen->post_type && ! WINP_Plugin::app()->get_api_object()->is_key() ) {
			do_action( 'themeisle_internal_page', WINP_PLUGIN_SLUG, 'wbcr-snippets' );
		}
	}

	/**
	 * Add style for close button for auto-draft snippet (preview)
	 * 
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		global $post;

		$current_screen = get_current_screen();

		if ( null === $current_screen ) {
			return;
		}

		if ( 'wbcr-snippets' === $current_screen->id && WINP_SNIPPETS_POST_TYPE === $post->post_type && WINP_Helper::getMetaOption( get_the_ID(), 'snippet_draft', false ) ) {
			wp_enqueue_style( 'winp-snippet-preview', WINP_PLUGIN_URL . '/admin/assets/css/snippet-preview.css', [], WINP_PLUGIN_VERSION );

			// Remove Clear cache button for WP-Rocket plugin in preview snippet page.
			remove_action( 'post_submitbox_start', 'rocket_post_submitbox_start' );
		}
	}

	/**
	 * Delete snippets
	 *
	 * @param array<int> $ids Snippet IDs.
	 * 
	 * @return void
	 */
	private function delete_snippets( $ids ) {
		if ( count( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_trash_post( $id );
			}
		}
	}

	/**
	 * Deactivate snippets
	 *
	 * @param array<int> $ids Snippet IDs.
	 * 
	 * @return void
	 */
	private function deactivate_snippets( $ids ) {
		if ( count( $ids ) ) {
			foreach ( $ids as $id ) {
				update_post_meta( $id, 'wbcr_inp_snippet_activate', 0 );
			}
		}
	}

	/**
	 * Activate snippets
	 *
	 * @param array<int> $ids Snippet IDs.
	 * 
	 * @return void
	 */
	private function activate_snippets( $ids ) {
		if ( count( $ids ) ) {
			foreach ( $ids as $id ) {
				$is_activate   = (int) WINP_Helper::getMetaOption( $id, 'snippet_activate', 0 );
				$snippet_scope = WINP_Helper::getMetaOption( $id, 'snippet_scope' );
				$snippet_type  = WINP_Helper::get_snippet_type( $id );

				if ( ( 'evrywhere' === $snippet_scope || 'auto' === $snippet_scope ) && ! $is_activate && WINP_SNIPPET_TYPE_TEXT !== $snippet_type && WINP_SNIPPET_TYPE_AD !== $snippet_type && WINP_Plugin::app()->get_execute_object()->getSnippetError( $id ) ) {
					continue;
				}

				update_post_meta( $id, 'wbcr_inp_snippet_activate', 1 );
			}
		}
	}


	/**
	 * Get taxonomy tags
	 *
	 * @param int $snippet_id Snippet ID.
	 *
	 * @return array<string>
	 */
	private function get_taxonomy_tags( $snippet_id ) {
		$tags = [];

		if ( $snippet_id ) {
			$terms = wp_get_post_terms( $snippet_id, WINP_SNIPPETS_TAXONOMY, [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $terms ) ) {
				return $terms;
			}
		}

		return $tags;
	}

	/**
	 * Update taxonomy tags
	 *
	 * @param int           $snippet_id Snippet ID.
	 * @param array<string> $tags Tags array.
	 * 
	 * @return void
	 */
	private function update_taxonomy_tags( $snippet_id, $tags ) {
		if ( ! empty( $tags ) ) {
			foreach ( $tags as $tag_slug ) {
				$term = get_term_by( 'slug', $tag_slug, WINP_SNIPPETS_TAXONOMY );
				if ( $term ) {
					wp_set_post_terms( $snippet_id, [ $term->term_id ], WINP_SNIPPETS_TAXONOMY, true );
				}
			}
		}
	}

	/**
	 * Get post meta
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_name Meta name.
	 *
	 * @return mixed
	 */
	private function get_meta( $post_id, $meta_name ) {
		return get_post_meta( $post_id, 'wbcr_inp_' . $meta_name, true );
	}
}
