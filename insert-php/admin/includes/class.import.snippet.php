<?php

/**
 * Import snippet
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_Import_Snippet {

	/**
	 * Process import files and return results
	 *
	 * @param array<array<string, mixed>>|array<string, mixed> $files Array of files from $_FILES or REST API.
	 * @param string                                           $dup_action Duplicate action: 'ignore', 'replace', or 'skip'.
	 *
	 * @return array<string, mixed> Array with 'count', 'error', and 'errors' keys
	 */
	public function process_import_files( $files, $dup_action = 'ignore' ) {
		$count  = 0;
		$error  = false;
		$errors = [];

		// Sanitize duplicate action.
		$dup_action = sanitize_text_field( $dup_action );
		if ( ! in_array( $dup_action, [ 'ignore', 'replace', 'skip' ], true ) ) {
			$dup_action = 'ignore';
		}

		// Normalize file array structure.
		$normalized_files = [];
		if ( isset( $files['tmp_name'] ) ) {
			// Handle both single file and multiple files format.
			$file_count = count( $files['tmp_name'] );
			for ( $i = 0; $i < $file_count; $i++ ) {
				$normalized_files[] = [
					'name'     => isset( $files['name'][ $i ] ) ? sanitize_file_name( $files['name'][ $i ] ) : '',
					'type'     => isset( $files['type'][ $i ] ) ? sanitize_text_field( $files['type'][ $i ] ) : '',
					'tmp_name' => isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '',
					'error'    => isset( $files['error'][ $i ] ) ? (int) $files['error'][ $i ] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $files['size'][ $i ] ) ? (int) $files['size'][ $i ] : 0,
				];
			}
		} else {
			// Already in correct format (from REST API) - still need to sanitize.
			foreach ( $files as $file ) {
				$normalized_files[] = [
					'name'     => isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '',
					'type'     => isset( $file['type'] ) ? sanitize_text_field( $file['type'] ) : '',
					'tmp_name' => isset( $file['tmp_name'] ) ? $file['tmp_name'] : '',
					'error'    => isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE,
					'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
				];
			}
		}

		foreach ( $normalized_files as $file ) {
			// Validate tmp_name path.
			if ( empty( $file['tmp_name'] ) || ! file_exists( $file['tmp_name'] ) ) {
				$error = true;
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Invalid or missing temporary file for: %s', 'insert-php' ), $file['name'] );
				continue;
			}

			$ext         = pathinfo( $file['name'], PATHINFO_EXTENSION );
			$ext         = strtolower( sanitize_text_field( $ext ) );
			$mime_type   = $file['type'];
			$import_file = $file['tmp_name'];

			if ( 'json' === $ext || 'application/json' === $mime_type ) {
				$result = $this->import_snippet( $import_file, $dup_action );
			} elseif ( 'zip' === $ext || 'application/zip' === $mime_type ) {
				$result = $this->import_zip_snippets( $import_file, $dup_action );
			} else {
				$result = apply_filters( 'wbcr/inp/import/snippet', false, $ext, $mime_type, $import_file, $dup_action );
			}

			if ( false === $result || - 1 === $result ) {
				$error = true;
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Failed to import file: %s', 'insert-php' ), $file['name'] );
			} else {
				$count += count( $result );
			}
		}

		return [
			'count'  => $count,
			'error'  => $error,
			'errors' => $errors,
		];
	}

	/**
	 * Import snippets
	 *
	 * @param string $file File path.
	 * @param string $dup_action Duplicate action: 'ignore', 'replace', or 'skip'.
	 *
	 * @return int|bool|array<int>
	 */
	public function import_snippet( $file, $dup_action ) {
		if ( ! file_exists( $file ) || ! is_file( $file ) ) {
			return false;
		}

		$raw_data = file_get_contents( $file );
		$data     = json_decode( $raw_data, true );
		$snippets = isset( $data['snippets'] ) ? $data['snippets'] : [];

		$imported = $this->save_imported_snippets( $snippets, $dup_action );

		return $imported;
	}

	/**
	 * Import snippets from ZIP archive
	 *
	 * @param string $file File path.
	 * @param string $dup_action Duplicate action: 'ignore', 'replace', or 'skip'.
	 *
	 * @return int|bool|array<int>
	 */
	public function import_zip_snippets( $file, $dup_action ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file ) ) {
			return false;
		}

		$result     = [];
		$upload_dir = wp_get_upload_dir();
		// Use unique directory name to prevent race conditions.
		$unzip_path = $upload_dir['path'] . '/winp_' . uniqid();

		// Create extraction directory.
		if ( ! wp_mkdir_p( $unzip_path ) ) {
			$zip->close();
			return false;
		}

		// Validate and extract only safe files.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$filename = $zip->getNameIndex( $i );
			
			// Skip if filename cannot be retrieved.
			if ( false === $filename ) {
				continue;
			}
			
			// Security: Skip files with directory traversal attempts.
			if ( false !== strpos( $filename, '..' ) || false !== strpos( $filename, '/' ) || false !== strpos( $filename, '\\' ) ) {
				continue;
			}

			// Only extract files (not directories).
			$file_info = $zip->statIndex( $i );
			if ( ! empty( $file_info ) && 0 === $file_info['size'] ) {
				continue;
			}

			$zip->extractTo( $unzip_path, $filename );
		}

		$zip->close();

		// Process extracted files using modern iterator.
		try {
			$iterator = new DirectoryIterator( $unzip_path );
			foreach ( $iterator as $file_info ) {
				if ( $file_info->isFile() && $file_info->getSize() > 0 ) {
					$filepath = $file_info->getPathname();
					$_result  = $this->import_snippet( $filepath, $dup_action );
					if ( is_array( $_result ) ) {
						$result = array_merge( $result, $_result );
					}
				}
			}
		} catch ( Exception $e ) {
			$this->cleanup_directory( $unzip_path );
			return false;
		}

		// Cleanup: Delete all files and directory.
		$this->cleanup_directory( $unzip_path );

		return $result;
	}

	/**
	 * Cleanup directory and its contents
	 *
	 * @param string $dir_path Directory path.
	 * 
	 * @return void
	 */
	private function cleanup_directory( $dir_path ) {
		if ( ! is_dir( $dir_path ) ) {
			return;
		}

		try {
			$iterator = new DirectoryIterator( $dir_path );
			foreach ( $iterator as $file_info ) {
				if ( $file_info->isFile() ) {
					// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Deleting temporary files in wp_upload_dir().
					unlink( $file_info->getPathname() );
				}
			}
		} catch ( Exception $e ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Deleting temporary directory in wp_upload_dir().
		rmdir( $dir_path );
	}

	/**
	 * Update taxonomy tags
	 *
	 * @param int           $snippet_id Snippet ID.
	 * @param array<string> $tags Tags slugs.
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
	 * Update post meta
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_name Meta name.
	 * @param mixed  $meta_value Meta value.
	 * 
	 * @return void
	 */
	private function update_meta( $post_id, $meta_name, $meta_value ) {
		update_post_meta( $post_id, 'wbcr_inp_' . $meta_name, $meta_value );
	}

	/**
	 * Save snippet
	 *
	 * @param array<string, mixed> $snippet Snippet data.
	 *
	 * @return int
	 */
	private function save_snippet( $snippet ) {
		$content = $snippet['content'];

		if ( WINP_SNIPPET_TYPE_TEXT != $snippet['type'] && WINP_SNIPPET_TYPE_AD != $snippet['type'] ) {
			$content = empty( $content ) && isset( $snippet['code'] ) && ! empty( $snippet['code'] ) ? $snippet['code'] : $content;
		}

		$data = [
			'post_title'   => $snippet['title'],
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => WINP_SNIPPETS_POST_TYPE,
		];

		if ( isset( $snippet['id'] ) && 0 != $snippet['id'] ) {
			$data['ID'] = $snippet['id'];
		}

		$snippet['id'] = wp_insert_post( $data );

		$this->update_meta( $snippet['id'], 'snippet_location', $snippet['location'] );
		$this->update_meta( $snippet['id'], 'snippet_type', $snippet['type'] );
		$this->update_meta( $snippet['id'], 'snippet_filters', $snippet['filters'] );
		$this->update_meta( $snippet['id'], 'changed_filters', $snippet['changed_filters'] );
		$this->update_meta( $snippet['id'], 'snippet_scope', $snippet['scope'] );
		$this->update_meta( $snippet['id'], 'snippet_description', $snippet['description'] );
		$this->update_meta( $snippet['id'], 'snippet_tags', $snippet['attributes'] );
		$this->update_meta( $snippet['id'], 'snippet_activate', 0 );
		$this->update_meta( $snippet['id'], 'snippet_priority', $snippet['priority'] );

		$this->update_taxonomy_tags( $snippet['id'], $snippet['tags'] );

		return $snippet['id'];
	}

	/**
	 * Save imported snippets
	 *
	 * @param array<array<string, mixed>> $snippets Snippets data.
	 * @param string                      $dup_action Duplicate action: 'ignore', 'replace', or 'skip'.
	 *
	 * @return array<int> Imported snippet IDs.
	 */
	private function save_imported_snippets( $snippets, $dup_action ) {
		$existing_snippets = [];
		
		if ( 'replace' === $dup_action || 'skip' === $dup_action ) {
			$all_snippets = get_posts(
				[ 
					'post_type'      => WINP_SNIPPETS_POST_TYPE,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				] 
			);

			foreach ( $all_snippets as $snippet ) {
				// Store by both post_name (slug) and post_title for matching.
				$existing_snippets[ $snippet->post_name ]  = $snippet->ID;
				$existing_snippets[ $snippet->post_title ] = $snippet->ID;
			}
		}

		$imported = [];

		foreach ( $snippets as $snippet ) {
			$is_duplicate = false;
			$duplicate_id = null;

			if ( 'ignore' !== $dup_action ) {
				if ( isset( $snippet['name'] ) && isset( $existing_snippets[ $snippet['name'] ] ) ) {
					$is_duplicate = true;
					$duplicate_id = $existing_snippets[ $snippet['name'] ];
				} elseif ( isset( $snippet['title'] ) && isset( $existing_snippets[ $snippet['title'] ] ) ) {
					$is_duplicate = true;
					$duplicate_id = $existing_snippets[ $snippet['title'] ];
				}

				if ( $is_duplicate ) {
					if ( 'replace' === $dup_action ) {
						$snippet['id'] = $duplicate_id;
					} elseif ( 'skip' === $dup_action ) {
						continue;
					}
				}
			}

				$snippet_id = $this->save_snippet( $snippet );
			if ( $snippet_id ) {
				$imported[] = $snippet_id;
			}
		}

		return $imported;
	}
}
