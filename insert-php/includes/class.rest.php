<?php
/**
 * REST Class
 * 
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Rest Class
 */
class WINP_Rest {

	/**
	 * WINP_Rest constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the license REST route.
	 * 
	 * @return void
	 */
	public function register_routes() {
		$namespace = 'woody/v1';

		register_rest_route(
			$namespace,
			'/license',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'args'                => [
						'key'    => [
							'type'              => 'string',
							'sanitize_callback' => function ( $param ) {
								return (string) esc_attr( $param );
							},
							'validate_callback' => function ( $param ) {
								return is_string( $param );
							},
						],
						'action' => [
							'type'              => 'string',
							'sanitize_callback' => function ( $param ) {
								return (string) esc_attr( $param );
							},
							'validate_callback' => function ( $param ) {
								return in_array( $param, [ 'activate', 'deactivate' ], true );
							},
						],
					],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'callback'            => [ $this, 'license' ],
				],
			] 
		);

		register_rest_route(
			$namespace,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'args'                => [
						'data' => [
							'type'              => 'object',
							'required'          => true,
							'sanitize_callback' => [ $this, 'sanitize_settings' ],
							'validate_callback' => function ( $param ) {
								if ( ! is_array( $param ) ) {
									return false;
								}

								$schema = $this->get_settings_schema();

								foreach ( array_keys( $param ) as $key ) {
									if ( ! isset( $schema[ $key ] ) ) {
										return false;
									}
								}
								return true;
							},
						],
					],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'callback'            => [ $this, 'save_settings' ],
				],
			] 
		);

		register_rest_route(
			$namespace,
			'/import',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'callback'            => [ $this, 'import_snippets' ],
				],
			] 
		);

		register_rest_route(
			$namespace,
			'/export',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'args'                => [
						'status' => [
							'type'              => 'string',
							'default'           => 'all',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'types'  => [
							'type'    => 'array',
							'default' => [],
							'items'   => [
								'type' => 'string',
							],
						],
						'tags'   => [
							'type'    => 'array',
							'default' => [],
							'items'   => [
								'type' => 'string',
							],
						],
					],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
					'callback'            => [ $this, 'export_snippets' ],
				],
			] 
		);

		register_rest_route(
			$namespace,
			'/sync',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'args'                => [
						'title' => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && ! empty( trim( $param ) );
							},
						],
						'id'    => [
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						],
					],
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
					'callback'            => [ $this, 'sync_snippet' ],
				],
			]
		);
	}

	/**
	 * Get settings schema (name => type mapping)
	 *
	 * @return array<string, string>
	 */
	private function get_settings_schema() {
		$settings = WINP_Settings::get_settings();
		$schema   = [];

		foreach ( $settings as $setting ) {
			if ( isset( $setting['name'] ) && isset( $setting['type'] ) ) {
				$schema[ $setting['name'] ] = $setting['type'];
			}
		}

		return $schema;
	}

	/**
	 * Sanitize settings data based on schema
	 *
	 * @param mixed $data Raw settings data.
	 * 
	 * @return array<string, mixed> Sanitized settings data.
	 */
	public function sanitize_settings( $data ) {
		if ( ! is_array( $data ) ) {
			return [];
		}

		$schema    = $this->get_settings_schema();
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) ) {
				continue;
			}

			switch ( $schema[ $key ] ) {
				case 'checkbox':
					if ( is_bool( $value ) ) {
						$sanitized[ $key ] = $value;
					} elseif ( is_numeric( $value ) ) {
						$sanitized[ $key ] = (bool) (int) $value;
					} elseif ( is_string( $value ) ) {
						$sanitized[ $key ] = in_array( strtolower( $value ), [ 'true', '1', 'yes', 'on' ], true );
					} else {
						$sanitized[ $key ] = (bool) $value;
					}
					break;

				case 'integer':
					$sanitized[ $key ] = absint( $value );
					break;

				case 'email':
					$sanitized_email = sanitize_email( $value );
					if ( is_email( $sanitized_email ) ) {
						$sanitized[ $key ] = $sanitized_email;
					}
					break;

				case 'dropdown':
				case 'text':
				case 'textbox':
				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Handle license activation/deactivation.
	 * 
	 * @param \WP_REST_Request<array<string, mixed>> $request Rest request.
	 * 
	 * @return \WP_REST_Response
	 */
	public function license( \WP_REST_Request $request ) {
		$data = $request->get_param( 'data' );

		if ( ! isset( $data['key'] ) || ! isset( $data['action'] ) ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'This action is no longer valid. Please refresh the page and try again.', 'insert-php' ),
					'success' => false,
				]
			);
		}

		$response = WINP_Plugin::app()->premium->toggle_license( $data['action'], $data['key'] );

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				[
					'message' => $response->get_error_message(),
					'success' => false,
				]
			);
		}

		return new \WP_REST_Response( $response );
	}

	/**
	 * Handle settings save.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Rest request.
	 * 
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ) {
		$data = $request->get_param( 'data' );

		if ( empty( $data ) ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'No changes detected. Modify at least one setting before saving.', 'insert-php' ),
					'success' => false,
				]
			);
		}

		foreach ( $data as $key => $value ) {
			update_option( 'wbcr_inp_' . $key, $value );
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'insert-php' ),
			]
		);
	}

	/**
	 * Handle snippet import.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Rest request.
	 * 
	 * @return \WP_REST_Response
	 */
	public function import_snippets( \WP_REST_Request $request ) {
		$files            = $request->get_file_params();
		$duplicate_action = $request->get_param( 'duplicate_action' );

		// Validate duplicate action.
		if ( ! in_array( $duplicate_action, [ 'ignore', 'replace', 'skip' ], true ) ) {
			return new \WP_REST_Response(
				[
					// translators: %s is the invalid duplicate action.
					'message' => sprintf( __( 'Invalid duplicate action: "%s". Expected: ignore, replace, or skip.', 'insert-php' ), $duplicate_action ),
					'success' => false,
				],
				400
			);
		}

		// Check if files were uploaded.
		if ( empty( $files ) ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'No files were uploaded. Please select a file and try again.', 'insert-php' ),
					'success' => false,
				],
				400
			);
		}

		$max_file_size = 2 * 1024 * 1024; // 2MB in bytes.
		$errors        = [];

		// Normalize file array structure (WordPress may structure it differently).
		$normalized_files = [];
		if ( isset( $files['files'] ) ) {
			// files[] format - need to normalize.
			$file_count = count( $files['files']['name'] );
			for ( $i = 0; $i < $file_count; $i++ ) {
				$normalized_files[] = [
					'name'     => $files['files']['name'][ $i ],
					'type'     => $files['files']['type'][ $i ],
					'tmp_name' => $files['files']['tmp_name'][ $i ],
					'error'    => $files['files']['error'][ $i ],
					'size'     => $files['files']['size'][ $i ],
				];
			}
		} else {
			$normalized_files = $files;
		}

		$validated_files = [];
		foreach ( $normalized_files as $file ) {
			if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
				$errors[] = __( 'The file could not be uploaded. Please ensure it\'s a valid .json or .zip file.', 'insert-php' );
				continue;
			}

			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Upload error for file: %s', 'insert-php' ), $file['name'] );
				continue;
			}

			if ( $file['size'] > $max_file_size ) {
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'File too large: %s (maximum 2MB)', 'insert-php' ), $file['name'] );
				continue;
			}

			$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $file_extension, [ 'json', 'zip' ], true ) ) {
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Invalid file type: %s (only .json and .zip allowed)', 'insert-php' ), $file['name'] );
				continue;
			}

			// Additional MIME type validation.
			$finfo = finfo_open( FILEINFO_MIME_TYPE );

			if ( false === $finfo ) {
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Could not verify the file type for "%s". Please use a .json or .zip file.', 'insert-php' ), $file['name'] );
				continue;
			}

			$mime_type = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			$allowed_mime_types = [
				'application/json',
				'text/plain',
				'application/zip',
				'application/x-zip-compressed',
			];

			if ( ! in_array( $mime_type, $allowed_mime_types, true ) ) {
				// translators: %s is the file name.
				$errors[] = sprintf( __( 'Invalid file MIME type: %s', 'insert-php' ), $file['name'] );
				continue;
			}

			$validated_files[] = $file;
		}

		// If no valid files, return error.
		if ( empty( $validated_files ) ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'No valid files to import.', 'insert-php' ),
					'success' => false,
					'errors'  => $errors,
				],
				400
			);
		}

		// Process import using the import snippet class.
		if ( ! class_exists( 'WINP_Import_Snippet' ) ) {
			require_once WINP_PLUGIN_DIR . '/admin/includes/class.import.snippet.php';
		}

		$import_handler = new WINP_Import_Snippet();
		$result         = $import_handler->process_import_files( $validated_files, $duplicate_action );

		// Merge validation errors with import errors.
		$all_errors = array_merge( $errors, $result['errors'] );

		if ( $result['count'] > 0 ) {
			// translators: %d is the number of imported snippets.
			$message = sprintf(
				// translators: %d is the number of imported snippets.
				_n(
					'Successfully imported %d snippet.',
					'Successfully imported %d snippets.',
					$result['count'],
					'insert-php'
				),
				$result['count']
			);

			if ( ! empty( $all_errors ) ) {
				$message .= ' ' . __( 'Some files had errors.', 'insert-php' );
			}

			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => $message,
					'errors'  => $all_errors,
					'count'   => $result['count'],
				]
			);
		}

		return new \WP_REST_Response(
			[
				'message' => __( 'No snippets were imported. Please check your file contains valid snippet data.', 'insert-php' ),
				'success' => false,
				'errors'  => $all_errors,
			]
		);
	}

	/**
	 * Handle snippet export.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Rest request.
	 * 
	 * @return \WP_REST_Response
	 */
	public function export_snippets( \WP_REST_Request $request ) {
		$status = $request->get_param( 'status' );
		$types  = $request->get_param( 'types' );
		$tags   = $request->get_param( 'tags' );

		$status = sanitize_text_field( $status );
		$types  = array_map( 'sanitize_text_field', (array) $types );
		$tags   = array_map( 'sanitize_text_field', (array) $tags );

		// Build query conditions.
		$meta_query_conditions = [];
		$tax_query_conditions  = [];

		// Status filter.
		if ( 'all' !== $status ) {
			if ( 'active' === $status ) {
				// Active: wbcr_inp_snippet_activate = 1.
				$meta_query_conditions[] = [
					'key'   => 'wbcr_inp_snippet_activate',
					'value' => 1,
				];
			} else {
				// Inactive: wbcr_inp_snippet_activate != 1 OR doesn't exist.
				$meta_query_conditions[] = [
					'relation' => 'OR',
					[
						'key'     => 'wbcr_inp_snippet_activate',
						'value'   => 1,
						'compare' => '!=',
					],
					[
						'key'     => 'wbcr_inp_snippet_activate',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		// Types filter.
		if ( ! empty( $types ) ) {
			if ( count( $types ) > 1 ) {
				$type_condition = [ 'relation' => 'OR' ];
				foreach ( $types as $type ) {
					$type_condition[] = [
						'key'   => 'wbcr_inp_snippet_type',
						'value' => $type,
					];
				}
			} else {
				$type_condition = [
					'key'   => 'wbcr_inp_snippet_type',
					'value' => $types[0],
				];
			}

			$meta_query_conditions[] = $type_condition;
		}

		// Tags filter.
		if ( ! empty( $tags ) ) {
			// Ensure taxonomy exists before querying.
			if ( ! taxonomy_exists( WINP_SNIPPETS_TAXONOMY ) ) {
				register_taxonomy( WINP_SNIPPETS_TAXONOMY, WINP_SNIPPETS_POST_TYPE, [] );
			}
			
			$tax_query_conditions = [
				[
					'taxonomy' => WINP_SNIPPETS_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $tags,
					'operator' => 'IN',
				],
			];
		}

		if ( count( $meta_query_conditions ) > 1 ) {
			$meta_query_conditions['relation'] = 'AND';
		}

		// Build final query.
		$conditions = [
			'post_type'   => WINP_SNIPPETS_POST_TYPE,
			'post_status' => 'publish',
			'numberposts' => -1,
		];

		if ( ! empty( $meta_query_conditions ) ) {
			$conditions['meta_query'] = $meta_query_conditions;
		}

		if ( ! empty( $tax_query_conditions ) ) {
			$conditions['tax_query'] = $tax_query_conditions; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// Query snippets.
		$snippets = get_posts( $conditions );

		if ( empty( $snippets ) ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'No snippets found. Try adjusting your filters or search terms.', 'insert-php' ),
					'success' => false,
				],
				404
			);
		}

		$ids = wp_list_pluck( $snippets, 'ID' );

		require_once WINP_PLUGIN_DIR . '/admin/includes/class.actions.snippet.php';
		$exporter = new WINP_Actions_Snippet();

		$result = $exporter->export_snippets( $ids, true );

		if ( $result['is_zip'] ) {
			// For ZIP files, encode as base64 for JSON transport.
			$data = base64_encode( $result['data'] );
		} else {
			// For JSON files, encode as pretty-printed JSON.
			$data = wp_json_encode( $result['data'], JSON_PRETTY_PRINT );
		}

		return new \WP_REST_Response(
			[
				'success'  => true,
				'filename' => $result['filename'],
				'data'     => $data,
				'count'    => $result['count'],
				'is_zip'   => $result['is_zip'],
			]
		);
	}

	/**
	 * Handle snippet sync to cloud.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Rest request.
	 * 
	 * @return \WP_REST_Response
	 */
	public function sync_snippet( \WP_REST_Request $request ) { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$title      = $request->get_param( 'title' );
		$snippet_id = absint( $request->get_param( 'id' ) );

		// Verify the snippet exists.
		$snippet = get_post( $snippet_id );
		if ( ! $snippet || WINP_SNIPPETS_POST_TYPE !== $snippet->post_type ) {
			return new \WP_REST_Response(
				[
					'message' => __( 'Snippet not found. It may have been deleted or moved.', 'insert-php' ),
					'success' => false,
				],
				404
			);
		}

		// Sync snippet using the API object.
		$result = WINP_Plugin::app()->get_api_object()->synchronization( $snippet_id, $title );

		// synchronization() returns true on success, error string on failure, or false if post doesn't exist.
		if ( true === $result ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Snippet saved as template successfully.', 'insert-php' ),
				],
				200
			);
		}

		// If result is a string, it's an error message. If false, it's a generic error.
		$error_message = is_string( $result ) ? $result : __( 'Failed to sync snippet. Please check your connection and try again.', 'insert-php' );

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $error_message,
			],
			500
		);
	}
}
