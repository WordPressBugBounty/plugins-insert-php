<?php
/**
 * Registers the plugin abilities with the WordPress Abilities API.
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Abilities class.
 */
class WINP_Abilities {

	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'woody-snippets';

	/**
	 * Maximum number of snippets returned per page.
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Maximum length of the conditions JSON string.
	 */
	const MAX_CONDITIONS_LENGTH = 20000;

	/**
	 * Maximum nesting depth of a condition value.
	 */
	const MAX_VALUE_DEPTH = 4;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Woody Code Snippets', 'insert-php' ),
				'description' => __( 'Manage Woody code snippets: code, insertion location, display conditions and activation state.', 'insert-php' ),
			]
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'woody/list-snippets',
			[
				'label'               => __( 'List snippets', 'insert-php' ),
				'description'         => __( 'Lists Woody snippets with their type, insertion scope, location and activation state. Snippet code is not included; use woody/get-snippet to read it.', 'insert-php' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_list_input_schema(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'snippets'    => [
							'type'  => 'array',
							'items' => $this->get_snippet_schema( false ),
						],
						'total'       => [ 'type' => 'integer' ],
						'page'        => [ 'type' => 'integer' ],
						'per_page'    => [ 'type' => 'integer' ],
						'total_pages' => [ 'type' => 'integer' ],
					],
				],
				'execute_callback'    => [ $this, 'list_snippets' ],
				'permission_callback' => [ $this, 'can_manage_snippets' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'woody/get-snippet',
			[
				'label'               => __( 'Get snippet', 'insert-php' ),
				'description'         => __( 'Returns one Woody snippet with its code, type, insertion scope, location, display conditions and activation state.', 'insert-php' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'snippet_id' => [
							'type'        => 'integer',
							'description' => __( 'Snippet ID.', 'insert-php' ),
							'minimum'     => 1,
						],
					],
					'required'             => [ 'snippet_id' ],
					'additionalProperties' => false,
				],
				'output_schema'       => $this->get_snippet_schema( true ),
				'execute_callback'    => [ $this, 'get_snippet' ],
				'permission_callback' => [ $this, 'can_manage_snippets' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
				],
			]
		);

		wp_register_ability(
			'woody/upsert-snippet',
			[
				'label'               => __( 'Create or update snippet', 'insert-php' ),
				'description'         => __( 'Creates a snippet (always inactive) or updates the code and execution settings of an existing one. PHP and universal snippets that run everywhere or are inserted automatically are validated first by evaluating the code once, exactly as the snippet editor does; nothing is saved when validation fails. With dry_run=true only the validation runs and nothing is saved. After an update an active snippet stays active only when the "activate by default" setting is on; this ability never activates an inactive snippet, use woody/set-snippet-state for that. CSS and JS snippet code cannot be written through this ability.', 'insert-php' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_upsert_input_schema(),
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'dry_run'     => [ 'type' => 'boolean' ],
						'operation'   => [
							'type' => 'string',
							'enum' => [ 'create', 'update' ],
						],
						'valid'       => [ 'type' => 'boolean' ],
						'validated'   => [
							'type'        => 'boolean',
							'description' => __( 'Whether the code was evaluated. False for snippet types or scopes the editor does not validate.', 'insert-php' ),
						],
						'message'     => [ 'type' => 'string' ],
						'revision_id' => [ 'type' => 'integer' ],
						'snippet'     => $this->get_snippet_schema( true ),
					],
				],
				'execute_callback'    => [ $this, 'upsert_snippet' ],
				'permission_callback' => [ $this, 'can_manage_snippets' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					],
					'show_in_rest' => true,
					'ai_connect'   => false,
				],
			]
		);

		wp_register_ability(
			'woody/set-snippet-state',
			[
				'label'               => __( 'Activate or deactivate snippet', 'insert-php' ),
				'description'         => __( 'Activates or deactivates one snippet. Activating a PHP, universal or HTML snippet that runs everywhere or is inserted automatically first executes it once to check for errors, exactly as the activation switch in the snippets list does, and is refused when the check fails.', 'insert-php' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'snippet_id' => [
							'type'        => 'integer',
							'description' => __( 'Snippet ID.', 'insert-php' ),
							'minimum'     => 1,
						],
						'state'      => [
							'type'        => 'string',
							'description' => __( 'Target state.', 'insert-php' ),
							'enum'        => [ 'active', 'inactive' ],
						],
					],
					'required'             => [ 'snippet_id', 'state' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'changed' => [ 'type' => 'boolean' ],
						'snippet' => $this->get_snippet_schema( false ),
					],
				],
				'execute_callback'    => [ $this, 'set_snippet_state' ],
				'permission_callback' => [ $this, 'can_manage_snippets' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
					'show_in_rest' => true,
					'ai_connect'   => false,
				],
			]
		);
	}

	/**
	 * Permission check shared by all abilities.
	 *
	 * Mirrors the snippets admin screens: the plugin capability gate plus the
	 * capability of the snippets post type.
	 *
	 * @return bool
	 */
	public function can_manage_snippets() {
		if ( ! WINP_Plugin::app()->current_user_car() ) {
			return false;
		}

		return current_user_can( $this->get_post_type_cap( 'edit_posts' ) );
	}

	/**
	 * Get a capability of the snippets post type.
	 *
	 * @param string $cap Post type capability key.
	 *
	 * @return string
	 */
	private function get_post_type_cap( $cap ) {
		$post_type = get_post_type_object( WINP_SNIPPETS_POST_TYPE );

		if ( $post_type && isset( $post_type->cap->$cap ) ) {
			return (string) $post_type->cap->$cap;
		}

		// Same value the post type registration maps 'edit_posts' and 'create_posts' to.
		return 'edit_' . WINP_SNIPPETS_POST_TYPE . 's';
	}

	/**
	 * Execute callback: list snippets.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 *
	 * @return array<string, mixed>
	 */
	public function list_snippets( $input = null ) {
		$input    = is_array( $input ) ? $input : [];
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$per_page = min( self::MAX_PER_PAGE, max( 1, $per_page ) );
		$status   = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';

		$statuses = [
			'any'     => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'publish' => [ 'publish' ],
			'draft'   => [ 'draft' ],
			'trash'   => [ 'trash' ],
		];

		$args = [
			'post_type'      => WINP_SNIPPETS_POST_TYPE,
			'post_status'    => isset( $statuses[ $status ] ) ? $statuses[ $status ] : $statuses['any'],
			// As on the snippets list screen: private snippets of other users need read_private_posts.
			'perm'           => 'readable',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'DESC',
		];

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}

		$meta_query = [];

		if ( ! empty( $input['type'] ) ) {
			$type = sanitize_key( (string) $input['type'] );

			if ( WINP_SNIPPET_TYPE_PHP === $type ) {
				// Snippets without a stored type are PHP snippets.
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'   => 'wbcr_inp_snippet_type',
						'value' => $type,
					],
					[
						'key'     => 'wbcr_inp_snippet_type',
						'compare' => 'NOT EXISTS',
					],
				];
			} else {
				$meta_query[] = [
					'key'   => 'wbcr_inp_snippet_type',
					'value' => $type,
				];
			}
		}

		if ( ! empty( $input['scope'] ) ) {
			$meta_query[] = [
				'key'   => 'wbcr_inp_snippet_scope',
				'value' => $this->scope_to_meta( sanitize_key( (string) $input['scope'] ) ),
			];
		}

		if ( isset( $input['active'] ) ) {
			if ( $input['active'] ) {
				$meta_query[] = [
					'key'   => 'wbcr_inp_snippet_activate',
					'value' => '1',
				];
			} else {
				$meta_query[] = [
					'relation' => 'OR',
					[
						'key'     => 'wbcr_inp_snippet_activate',
						'value'   => '1',
						'compare' => '!=',
					],
					[
						'key'     => 'wbcr_inp_snippet_activate',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query;
		}

		$query    = new WP_Query( $args );
		$snippets = [];

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$snippets[] = $this->format_snippet( $post, false );
			}
		}

		return [
			'snippets'    => $snippets,
			'total'       => (int) $query->found_posts,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) $query->max_num_pages,
		];
	}

	/**
	 * Execute callback: get one snippet.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_snippet( $input = null ) {
		$input = is_array( $input ) ? $input : [];
		$post  = $this->get_snippet_post( isset( $input['snippet_id'] ) ? (int) $input['snippet_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Same check the snippet editor applies before showing the code (post.php, edit_post).
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'woody_forbidden', __( 'You are not allowed to edit this snippet.', 'insert-php' ) );
		}

		return $this->format_snippet( $post, true );
	}

	/**
	 * Execute callback: create or update a snippet.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function upsert_snippet( $input = null ) {
		$input      = is_array( $input ) ? $input : [];
		$dry_run    = ! empty( $input['dry_run'] );
		$snippet_id = isset( $input['snippet_id'] ) ? (int) $input['snippet_id'] : 0;
		$post       = null;

		if ( ! post_type_exists( WINP_SNIPPETS_POST_TYPE ) ) {
			return new WP_Error( 'woody_unavailable', __( 'The snippets post type is not registered in this context.', 'insert-php' ) );
		}

		if ( $snippet_id ) {
			$post = $this->get_snippet_post( $snippet_id );

			if ( is_wp_error( $post ) ) {
				return $post;
			}

			if ( 'trash' === $post->post_status ) {
				return new WP_Error( 'woody_snippet_trashed', __( 'The snippet is in the trash and cannot be edited.', 'insert-php' ) );
			}

			if ( ! current_user_can( 'edit_post', $snippet_id ) ) {
				return new WP_Error( 'woody_forbidden', __( 'You are not allowed to edit this snippet.', 'insert-php' ) );
			}
		} elseif ( ! current_user_can( $this->get_post_type_cap( 'create_posts' ) ) ) {
			return new WP_Error( 'woody_forbidden', __( 'You are not allowed to create snippets.', 'insert-php' ) );
		}

		$data = $this->resolve_snippet_data( $input, $post );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$was_active = $post ? $this->is_active( $post->ID ) : false;

		// Same rule the editor uses to decide whether the code has to be evaluated before saving.
		$is_executable    = in_array( $data['type'], [ WINP_SNIPPET_TYPE_PHP, WINP_SNIPPET_TYPE_UNIVERSAL ], true );
		$needs_validation = $is_executable && 'shortcode' !== $data['scope'] && '' !== trim( $data['code'] ) && $data['execution_changed'];
		$validation       = [
			'valid'   => true,
			'message' => '',
		];

		if ( $needs_validation ) {
			if ( WINP_Helper::is_safe_mode() ) {
				return new WP_Error( 'woody_safe_mode', __( 'Safe mode is enabled, snippet code cannot be evaluated. Disable safe mode first.', 'insert-php' ) );
			}

			$validation = WINP_Code_Validator::validate_code( $this->prepare_code( $data['code'], $data['type'] ), $data['type'] );

			if ( ! $validation['valid'] ) {
				$validation['message'] = wp_strip_all_tags( str_replace( '<br>', "\n", $validation['message'] ) );

				if ( $was_active ) {
					$validation['message'] .= ' ' . __( 'The snippet is currently active and has already run in this request; deactivate it first if the error is caused by its own declarations.', 'insert-php' );
				}
			}
		}

		$operation = $post ? 'update' : 'create';

		if ( $dry_run ) {
			return [
				'dry_run'   => true,
				'operation' => $operation,
				'valid'     => $validation['valid'],
				'validated' => $needs_validation,
				'message'   => $validation['message'],
			];
		}

		if ( ! $validation['valid'] ) {
			return new WP_Error( 'woody_invalid_code', $validation['message'] );
		}

		$saved_id = $this->save_snippet( $data, $post, $was_active );

		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		$saved = get_post( $saved_id );

		if ( ! $saved instanceof WP_Post ) {
			return new WP_Error( 'woody_save_failed', __( 'The snippet could not be saved.', 'insert-php' ) );
		}

		$revisions = wp_get_post_revisions(
			$saved_id,
			[
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		$latest      = reset( $revisions );
		$revision_id = $latest instanceof WP_Post ? $latest->ID : ( is_int( $latest ) ? $latest : 0 );

		return [
			'dry_run'     => false,
			'operation'   => $operation,
			'valid'       => true,
			'validated'   => $needs_validation,
			'message'     => '',
			'revision_id' => $revision_id,
			'snippet'     => $this->format_snippet( $saved, true ),
		];
	}

	/**
	 * Execute callback: activate or deactivate a snippet.
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function set_snippet_state( $input = null ) {
		$input      = is_array( $input ) ? $input : [];
		$snippet_id = isset( $input['snippet_id'] ) ? (int) $input['snippet_id'] : 0;
		$state      = isset( $input['state'] ) ? sanitize_key( (string) $input['state'] ) : '';

		if ( ! in_array( $state, [ 'active', 'inactive' ], true ) ) {
			return new WP_Error( 'woody_invalid_state', __( 'State must be "active" or "inactive".', 'insert-php' ) );
		}

		$post = $this->get_snippet_post( $snippet_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( post_type_exists( WINP_SNIPPETS_POST_TYPE ) && ! current_user_can( 'edit_post', $snippet_id ) ) {
			return new WP_Error( 'woody_forbidden', __( 'You are not allowed to edit this snippet.', 'insert-php' ) );
		}

		$is_active = $this->is_active( $snippet_id );
		$activate  = 'active' === $state;

		if ( $activate === $is_active ) {
			return [
				'changed' => false,
				'snippet' => $this->format_snippet( $post, false ),
			];
		}

		if ( $activate ) {
			if ( 'trash' === $post->post_status ) {
				return new WP_Error( 'woody_snippet_trashed', __( 'The snippet is in the trash and cannot be activated.', 'insert-php' ) );
			}

			$error = $this->check_before_activation( $post );

			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		update_post_meta( $snippet_id, 'wbcr_inp_snippet_activate', $activate ? 1 : 0 );

		return [
			'changed' => true,
			'snippet' => $this->format_snippet( $post, false ),
		];
	}

	/**
	 * Run the checks the activation switch runs before a snippet is activated.
	 *
	 * @param WP_Post $post Snippet post.
	 *
	 * @return true|WP_Error
	 */
	private function check_before_activation( WP_Post $post ) {
		$scope = (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_scope', true );
		$type  = $this->get_type( $post->ID );

		$skipped_types = [ WINP_SNIPPET_TYPE_TEXT, WINP_SNIPPET_TYPE_AD, WINP_SNIPPET_TYPE_CSS, WINP_SNIPPET_TYPE_JS ];

		if ( ! in_array( $scope, [ 'evrywhere', 'auto' ], true ) || in_array( $type, $skipped_types, true ) ) {
			return true;
		}

		if ( WINP_Helper::is_safe_mode() ) {
			return new WP_Error( 'woody_safe_mode', __( 'Safe mode is enabled, snippet code cannot be executed. Disable safe mode first.', 'insert-php' ) );
		}

		if ( in_array( $type, [ WINP_SNIPPET_TYPE_PHP, WINP_SNIPPET_TYPE_UNIVERSAL ], true ) ) {
			// Redeclaring a function inside eval() ends the request, so detect it before executing the snippet.
			$code          = $this->prepare_code( (string) WINP_Helper::get_snippet_code( $post ), $type );
			$redeclaration = WINP_Code_Validator::find_function_redeclaration( $code, $type );

			if ( null !== $redeclaration ) {
				return new WP_Error(
					'woody_invalid_code',
					// translators: %1$d is the line number, %2$s is the fully qualified function name.
					sprintf( __( 'Line %1$d: Cannot redeclare function %2$s(). Rename the function or guard its declaration with function_exists().', 'insert-php' ), $redeclaration['line'], $redeclaration['name'] )
				);
			}
		}

		$error = WINP_Plugin::app()->get_execute_object()->getSnippetError( $post->ID );

		if ( $error ) {
			$message = is_array( $error ) && isset( $error['message'] ) ? wp_strip_all_tags( (string) $error['message'] ) : '';

			return new WP_Error(
				'woody_invalid_code',
				trim( __( 'The snippet was not activated because its code contains an error.', 'insert-php' ) . ' ' . $message )
			);
		}

		return true;
	}

	/**
	 * Merge the input over the stored snippet and validate the result.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @param WP_Post|null         $post  Existing snippet, null when creating.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function resolve_snippet_data( $input, $post ) {
		$old_type  = $post ? $this->get_type( $post->ID ) : '';
		$old_scope = $post ? (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_scope', true ) : '';
		$old_scope = '' === $old_scope ? 'shortcode' : $old_scope;
		$old_code  = $post ? (string) WINP_Helper::get_snippet_code( $post ) : '';

		if ( ! $post ) {
			foreach ( [ 'title', 'code', 'type' ] as $required ) {
				if ( ! isset( $input[ $required ] ) || '' === trim( (string) $input[ $required ] ) ) {
					// translators: %s is the name of the missing input field.
					return new WP_Error( 'woody_missing_field', sprintf( __( 'The "%s" field is required to create a snippet.', 'insert-php' ), $required ) );
				}
			}
		}

		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : $old_type;

		if ( $post && $type !== $old_type ) {
			return new WP_Error( 'woody_type_immutable', __( 'The type of an existing snippet cannot be changed.', 'insert-php' ) );
		}

		$writable_types = [ WINP_SNIPPET_TYPE_PHP, WINP_SNIPPET_TYPE_UNIVERSAL, WINP_SNIPPET_TYPE_HTML, WINP_SNIPPET_TYPE_TEXT, WINP_SNIPPET_TYPE_AD ];
		$file_types     = [ WINP_SNIPPET_TYPE_CSS, WINP_SNIPPET_TYPE_JS ];

		if ( in_array( $type, $file_types, true ) ) {
			// CSS and JS snippets are served from a file that only the editor save handler generates.
			if ( ! $post || isset( $input['code'] ) ) {
				return new WP_Error( 'woody_type_not_supported', __( 'The code of CSS and JS snippets can only be saved from the snippet editor.', 'insert-php' ) );
			}
		} elseif ( ! in_array( $type, $writable_types, true ) ) {
			return new WP_Error( 'woody_invalid_type', __( 'Unknown snippet type.', 'insert-php' ) );
		}

		$title = $post ? $post->post_title : '';

		if ( isset( $input['title'] ) ) {
			$title = sanitize_text_field( (string) $input['title'] );

			if ( '' === $title ) {
				return new WP_Error( 'woody_missing_field', __( 'The snippet title cannot be empty.', 'insert-php' ) );
			}
		}

		$code = isset( $input['code'] ) ? (string) $input['code'] : $old_code;

		// Scope: PHP snippets run everywhere or by shortcode, the other types are inserted automatically or by shortcode.
		$scope = isset( $input['scope'] ) ? $this->scope_to_meta( sanitize_key( (string) $input['scope'] ) ) : $old_scope;

		$allowed_scopes = WINP_SNIPPET_TYPE_PHP === $type ? [ 'evrywhere', 'shortcode' ] : [ 'auto', 'shortcode' ];

		if ( ! in_array( $scope, $allowed_scopes, true ) ) {
			return new WP_Error(
				'woody_invalid_scope',
				WINP_SNIPPET_TYPE_PHP === $type
					? __( 'PHP snippets support the "everywhere" and "shortcode" scopes.', 'insert-php' )
					: __( 'This snippet type supports the "auto" and "shortcode" scopes.', 'insert-php' )
			);
		}

		$data = [
			'type'              => $type,
			'title'             => $title,
			'code'              => $code,
			'scope'             => $scope,
			'execution_changed' => ! $post || $code !== $old_code || $scope !== $old_scope,
		];

		if ( isset( $input['description'] ) ) {
			$data['description'] = sanitize_text_field( (string) $input['description'] );
		}

		$has_placement = isset( $input['location'] ) || isset( $input['location_number'] );

		if ( WINP_SNIPPET_TYPE_PHP === $type ) {
			if ( $has_placement || isset( $input['conditions'] ) ) {
				return new WP_Error( 'woody_not_applicable', __( 'PHP snippets do not support an insertion location or display conditions.', 'insert-php' ) );
			}

			return $data;
		}

		if ( $has_placement || ! $post ) {
			$placement = $this->resolve_placement( $input, $post );

			if ( is_wp_error( $placement ) ) {
				return $placement;
			}

			$data['location']        = $placement['location'];
			$data['location_number'] = $placement['location_number'];
		}

		if ( isset( $input['conditions'] ) ) {
			$conditions = $this->parse_conditions( (string) $input['conditions'] );

			if ( is_wp_error( $conditions ) ) {
				return $conditions;
			}

			$data['conditions'] = $conditions;
		}

		return $data;
	}

	/**
	 * Validate the automatic insertion location.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @param WP_Post|null         $post  Existing snippet, null when creating.
	 *
	 * @return array{location:string,location_number:int}|WP_Error
	 */
	private function resolve_placement( $input, $post ) {
		$location = $post ? (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_location', true ) : '';
		$number   = $post ? (int) get_post_meta( $post->ID, 'wbcr_inp_snippet_p_number', true ) : 0;

		if ( isset( $input['location'] ) ) {
			$location = sanitize_key( (string) $input['location'] );
		}

		if ( isset( $input['location_number'] ) ) {
			$number = max( 0, (int) $input['location_number'] );
		}

		// The editor default.
		$location  = '' === $location ? 'header' : $location;
		$locations = ( new WINP_Insertion_Locations() )->getList();

		if ( ! isset( $locations[ $location ] ) ) {
			return new WP_Error(
				'woody_invalid_location',
				// translators: %s is a comma separated list of location slugs.
				sprintf( __( 'Unknown insertion location. Available locations: %s.', 'insert-php' ), implode( ', ', array_keys( $locations ) ) )
			);
		}

		// The editor disables the WooCommerce locations when WooCommerce is not active.
		if ( 0 === strpos( $location, 'woo_' ) && ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'woody_woocommerce_required', __( 'WooCommerce locations require WooCommerce to be active.', 'insert-php' ) );
		}

		$settings        = isset( $locations[ $location ][2] ) && is_array( $locations[ $location ][2] ) ? $locations[ $location ][2] : [];
		$requires_number = ! empty( $settings['requiresLocationNumber'] );

		if ( $requires_number && $number < 1 ) {
			return new WP_Error( 'woody_location_number_required', __( 'This insertion location requires a location_number of 1 or more.', 'insert-php' ) );
		}

		return [
			'location'        => $location,
			'location_number' => $number,
		];
	}

	/**
	 * Get the upgrade link reported by the abilities that need the premium plugin.
	 *
	 * @param string $area Gated feature, used as the campaign.
	 *
	 * @return string
	 */
	private function get_upgrade_url( $area ) {
		return tsdk_utmify( WINP_UPGRADE, $area, 'mcp' );
	}

	/**
	 * Parse and validate the display conditions JSON.
	 *
	 * The stored structure is the one the conditions editor posts: a list of
	 * groups, each holding AND-ed scopes that hold OR-ed conditions.
	 *
	 * @param string $json Conditions JSON.
	 *
	 * @return array<int, stdClass>|WP_Error
	 */
	private function parse_conditions( $json ) {
		if ( strlen( $json ) > self::MAX_CONDITIONS_LENGTH ) {
			return new WP_Error( 'woody_invalid_conditions', __( 'The conditions value is too long.', 'insert-php' ) );
		}

		$json = trim( $json );

		if ( '' === $json ) {
			return [];
		}

		$groups = json_decode( $json );

		if ( ! is_array( $groups ) ) {
			return new WP_Error( 'woody_invalid_conditions', __( 'Conditions must be a JSON array of condition groups.', 'insert-php' ) );
		}

		$free_params = [
			'user-role',
			'user-registered',
			'user-cookie-name',
			'location-page',
			'location-referrer',
			'location-post-type',
			'location-taxonomy',
			'page-taxonomy',
			'location-some-page',
		];
		$operators   = [ 'equals', 'notequal', 'less', 'older', 'greater', 'younger', 'contains', 'notcontain', 'between' ];
		$is_premium  = WINP_Plugin::app()->is_premium();
		$result      = [];

		foreach ( $groups as $group ) {
			if ( ! is_object( $group ) || ! isset( $group->conditions ) || ! is_array( $group->conditions ) ) {
				return new WP_Error( 'woody_invalid_conditions', __( 'Each condition group needs a "conditions" array.', 'insert-php' ) );
			}

			$group_type = isset( $group->type ) ? sanitize_key( (string) $group->type ) : 'showif';

			if ( ! in_array( $group_type, [ 'showif', 'hideif' ], true ) ) {
				return new WP_Error( 'woody_invalid_conditions', __( 'A condition group type must be "showif" or "hideif".', 'insert-php' ) );
			}

			$scopes = [];

			foreach ( $group->conditions as $scope ) {
				if ( ! is_object( $scope ) || ! isset( $scope->conditions ) || ! is_array( $scope->conditions ) ) {
					return new WP_Error( 'woody_invalid_conditions', __( 'Each condition scope needs a "conditions" array.', 'insert-php' ) );
				}

				$conditions = [];

				foreach ( $scope->conditions as $condition ) {
					if ( ! is_object( $condition ) || ! isset( $condition->param, $condition->operator ) ) {
						return new WP_Error( 'woody_invalid_conditions', __( 'Each condition needs a "param" and an "operator".', 'insert-php' ) );
					}

					$param    = sanitize_key( (string) $condition->param );
					$operator = sanitize_key( (string) $condition->operator );

					if ( ! in_array( $operator, $operators, true ) ) {
						// translators: %s is a comma separated list of operators.
						return new WP_Error( 'woody_invalid_conditions', sprintf( __( 'Unknown condition operator. Available operators: %s.', 'insert-php' ), implode( ', ', $operators ) ) );
					}

					$is_premium_param = 0 === strpos( $param, 'technology-' ) || 0 === strpos( $param, 'auditory-' );

					if ( $is_premium_param && ! $is_premium ) {
						$upgrade_url = $this->get_upgrade_url( 'display-conditions' );

						return new WP_Error(
							'woody_premium_required',
							// translators: %1$s is the condition parameter, %2$s is the upgrade URL.
							sprintf( __( 'The "%1$s" condition requires Woody Code Snippets Premium. Upgrade: %2$s', 'insert-php' ), $param, $upgrade_url ),
							[ 'upgrade_url' => $upgrade_url ]
						);
					}

					if ( ! $is_premium_param && ! in_array( $param, $free_params, true ) ) {
						// translators: %s is a comma separated list of condition parameters.
						return new WP_Error( 'woody_invalid_conditions', sprintf( __( 'Unknown condition param. Available params: %s.', 'insert-php' ), implode( ', ', $free_params ) ) );
					}

					$clean           = new stdClass();
					$clean->param    = $param;
					$clean->operator = $operator;
					$clean->type     = isset( $condition->type ) ? sanitize_key( (string) $condition->type ) : 'text';
					$clean->value    = $this->sanitize_condition_value( isset( $condition->value ) ? $condition->value : '', 0 );

					$conditions[] = $clean;
				}

				$clean_scope             = new stdClass();
				$clean_scope->type       = 'scope';
				$clean_scope->conditions = $conditions;

				$scopes[] = $clean_scope;
			}

			$clean_group             = new stdClass();
			$clean_group->conditions = $scopes;
			$clean_group->type       = $group_type;

			$result[] = $clean_group;
		}

		return $result;
	}

	/**
	 * Sanitize a condition value, keeping its scalar, list or object shape.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $depth Current nesting depth.
	 *
	 * @return mixed
	 */
	private function sanitize_condition_value( $value, $depth ) {
		if ( $depth > self::MAX_VALUE_DEPTH ) {
			return '';
		}

		if ( is_object( $value ) ) {
			$clean = new stdClass();

			foreach ( get_object_vars( $value ) as $key => $item ) {
				$key = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $key );

				if ( '' !== $key && null !== $key ) {
					$clean->$key = $this->sanitize_condition_value( $item, $depth + 1 );
				}
			}

			return $clean;
		}

		if ( is_array( $value ) ) {
			$clean = [];

			foreach ( $value as $item ) {
				$clean[] = $this->sanitize_condition_value( $item, $depth + 1 );
			}

			return $clean;
		}

		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Persist the snippet.
	 *
	 * @param array<string, mixed> $data       Validated snippet data.
	 * @param WP_Post|null         $post       Existing snippet, null when creating.
	 * @param bool                 $was_active Whether the snippet was active before the update.
	 *
	 * @return int|WP_Error Snippet ID.
	 */
	private function save_snippet( $data, $post, $was_active ) {
		$postarr = [
			'post_title'   => $data['title'],
			'post_content' => $data['code'],
			'post_type'    => WINP_SNIPPETS_POST_TYPE,
		];

		if ( $post ) {
			$postarr['ID'] = $post->ID;
		} else {
			$postarr['post_status'] = 'publish';
		}

		// Like the editor, keep the content filters away from code; text and ad snippets stay filtered.
		$is_code  = ! in_array( $data['type'], [ WINP_SNIPPET_TYPE_TEXT, WINP_SNIPPET_TYPE_AD ], true );
		$had_kses = $is_code && false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		$had_rel  = $is_code && false !== has_filter( 'content_save_pre', 'wp_targeted_link_rel' );

		if ( $had_kses ) {
			kses_remove_filters();
		}

		if ( $had_rel ) {
			remove_filter( 'content_save_pre', 'wp_targeted_link_rel' );
		}

		$saved_id = $post ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );

		if ( $had_kses ) {
			kses_init_filters();
		}

		if ( $had_rel ) {
			add_filter( 'content_save_pre', 'wp_targeted_link_rel' );
		}

		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		update_post_meta( $saved_id, 'wbcr_inp_snippet_type', $data['type'] );
		update_post_meta( $saved_id, 'wbcr_inp_snippet_scope', $data['scope'] );

		if ( isset( $data['location'] ) ) {
			update_post_meta( $saved_id, 'wbcr_inp_snippet_location', $data['location'] );
			update_post_meta( $saved_id, 'wbcr_inp_snippet_p_number', (string) $data['location_number'] );
		}

		if ( isset( $data['description'] ) ) {
			update_post_meta( $saved_id, 'wbcr_inp_snippet_description', $data['description'] );
		}

		if ( isset( $data['conditions'] ) ) {
			update_post_meta( $saved_id, 'wbcr_inp_snippet_filters', empty( $data['conditions'] ) ? '' : $data['conditions'] );
			update_post_meta( $saved_id, 'wbcr_inp_changed_filters', 1 );
		}

		if ( ! $post ) {
			update_post_meta( $saved_id, 'wbcr_inp_snippet_priority', WINP_Helper::get_next_snippet_priority() );
		}

		// New snippets are always inactive. Like the editor, an update keeps an active
		// snippet active only when the "activate by default" setting is on.
		$keep_active = $post && $was_active && get_option( 'wbcr_inp_activate_by_default', true );

		update_post_meta( $saved_id, 'wbcr_inp_snippet_activate', $keep_active ? 1 : 0 );

		return $saved_id;
	}

	/**
	 * Remove the PHP tags the executor removes before evaluating a PHP snippet.
	 *
	 * Same replacements as WINP_Execute_Snippet::prepareCode(), which needs a
	 * saved snippet to resolve the type.
	 *
	 * @param string $code Snippet code.
	 * @param string $type Snippet type.
	 *
	 * @return string
	 */
	private function prepare_code( $code, $type ) {
		if ( WINP_SNIPPET_TYPE_PHP !== $type ) {
			return $code;
		}

		$code = (string) preg_replace( '|^[\s]*<\?(php)?|', '', $code );

		return (string) preg_replace( '|\?>[\s]*$|', '', $code );
	}

	/**
	 * Get a snippet post by ID.
	 *
	 * @param int $snippet_id Snippet ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private function get_snippet_post( $snippet_id ) {
		$post = $snippet_id > 0 ? get_post( $snippet_id ) : null;

		if ( ! $post instanceof WP_Post || WINP_SNIPPETS_POST_TYPE !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return new WP_Error( 'woody_snippet_not_found', __( 'Snippet not found.', 'insert-php' ) );
		}

		return $post;
	}

	/**
	 * Get the stored snippet type.
	 *
	 * @param int $snippet_id Snippet ID.
	 *
	 * @return string
	 */
	private function get_type( $snippet_id ) {
		$type = get_post_meta( $snippet_id, 'wbcr_inp_snippet_type', true );

		return is_string( $type ) && '' !== $type ? $type : WINP_SNIPPET_TYPE_PHP;
	}

	/**
	 * Whether the snippet is activated.
	 *
	 * @param int $snippet_id Snippet ID.
	 *
	 * @return bool
	 */
	private function is_active( $snippet_id ) {
		return (bool) (int) get_post_meta( $snippet_id, 'wbcr_inp_snippet_activate', true );
	}

	/**
	 * Convert the public scope name to the stored value.
	 *
	 * @param string $scope Public scope name.
	 *
	 * @return string
	 */
	private function scope_to_meta( $scope ) {
		return 'everywhere' === $scope ? 'evrywhere' : $scope;
	}

	/**
	 * Build the public representation of a snippet.
	 *
	 * @param WP_Post $post      Snippet post.
	 * @param bool    $with_code Whether to include the code and display conditions.
	 *
	 * @return array<string, mixed>
	 */
	private function format_snippet( WP_Post $post, $with_code ) {
		require_once WINP_PLUGIN_DIR . '/includes/class.snippet.php';

		$type  = $this->get_type( $post->ID );
		$scope = (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_scope', true );
		$scope = '' === $scope ? 'shortcode' : $scope;

		$snippet = [
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'type'              => $type,
			'status'            => $post->post_status,
			'active'            => $this->is_active( $post->ID ),
			'scope'             => 'evrywhere' === $scope ? 'everywhere' : $scope,
			'location'          => (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_location', true ),
			'location_number'   => (int) get_post_meta( $post->ID, 'wbcr_inp_snippet_p_number', true ),
			'priority'          => (int) get_post_meta( $post->ID, 'wbcr_inp_snippet_priority', true ),
			'description'       => (string) get_post_meta( $post->ID, 'wbcr_inp_snippet_description', true ),
			'shortcode'         => wp_specialchars_decode( WINP_Helper::get_shortcode_text( $post ), ENT_QUOTES ),
			// False when DISALLOW_UNFILTERED_HTML stops the plugin from running this snippet type.
			'execution_allowed' => ( new WINP_Snippet( $post ) )->is_allowed(),
			'modified_gmt'      => $post->post_modified_gmt,
		];

		if ( $with_code ) {
			$filters = get_post_meta( $post->ID, 'wbcr_inp_snippet_filters', true );
			$encoded = empty( $filters ) ? false : wp_json_encode( $filters );
			$decoded = false === $encoded ? [] : json_decode( $encoded, true );

			$snippet['code']       = (string) WINP_Helper::get_snippet_code( $post );
			$snippet['conditions'] = is_array( $decoded ) ? array_values( $decoded ) : [];
		}

		return $snippet;
	}

	/**
	 * Input schema of woody/list-snippets.
	 *
	 * @return array<string, mixed>
	 */
	private function get_list_input_schema() {
		return [
			'type'                 => 'object',
			'properties'           => [
				'type'     => [
					'type'        => 'string',
					'description' => __( 'Only snippets of this type.', 'insert-php' ),
					'enum'        => [ WINP_SNIPPET_TYPE_PHP, WINP_SNIPPET_TYPE_UNIVERSAL, WINP_SNIPPET_TYPE_HTML, WINP_SNIPPET_TYPE_CSS, WINP_SNIPPET_TYPE_JS, WINP_SNIPPET_TYPE_TEXT, WINP_SNIPPET_TYPE_AD ],
				],
				'scope'    => [
					'type'        => 'string',
					'description' => __( 'Only snippets with this insertion scope.', 'insert-php' ),
					'enum'        => [ 'everywhere', 'auto', 'shortcode' ],
				],
				'active'   => [
					'type'        => 'boolean',
					'description' => __( 'Only active (true) or inactive (false) snippets.', 'insert-php' ),
				],
				'status'   => [
					'type'        => 'string',
					'description' => __( 'Post status filter. "any" excludes the trash.', 'insert-php' ),
					'enum'        => [ 'any', 'publish', 'draft', 'trash' ],
					'default'     => 'any',
				],
				'search'   => [
					'type'        => 'string',
					'description' => __( 'Search term matched against the snippet title and code.', 'insert-php' ),
				],
				'page'     => [
					'type'    => 'integer',
					'minimum' => 1,
					'default' => 1,
				],
				'per_page' => [
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_PER_PAGE,
					'default' => 20,
				],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * Input schema of woody/upsert-snippet.
	 *
	 * @return array<string, mixed>
	 */
	private function get_upsert_input_schema() {
		return [
			'type'                 => 'object',
			'properties'           => [
				'snippet_id'      => [
					'type'        => 'integer',
					'description' => __( 'ID of the snippet to update. Omit to create a new, inactive snippet.', 'insert-php' ),
					'minimum'     => 1,
				],
				'title'           => [
					'type'        => 'string',
					'description' => __( 'Snippet title. Required when creating.', 'insert-php' ),
				],
				'code'            => [
					'type'        => 'string',
					'description' => __( 'Snippet code or content. Required when creating.', 'insert-php' ),
				],
				'type'            => [
					'type'        => 'string',
					'description' => __( 'Snippet type. Required when creating; it cannot be changed afterwards.', 'insert-php' ),
					'enum'        => [ WINP_SNIPPET_TYPE_PHP, WINP_SNIPPET_TYPE_UNIVERSAL, WINP_SNIPPET_TYPE_HTML, WINP_SNIPPET_TYPE_TEXT, WINP_SNIPPET_TYPE_AD ],
				],
				'scope'           => [
					'type'        => 'string',
					'description' => __( 'Where the snippet runs. PHP snippets: "everywhere" or "shortcode". Other types: "auto" (automatic insertion at "location") or "shortcode". Defaults to "shortcode".', 'insert-php' ),
					'enum'        => [ 'everywhere', 'auto', 'shortcode' ],
				],
				'location'        => [
					'type'        => 'string',
					'description' => __( 'Automatic insertion location for non-PHP snippets, for example header, footer, before_content, after_content, before_paragraph, after_paragraph, before_post, after_post. Defaults to "header".', 'insert-php' ),
				],
				'location_number' => [
					'type'        => 'integer',
					'description' => __( 'Paragraph or post number for locations that need one, such as before_paragraph.', 'insert-php' ),
					'minimum'     => 0,
				],
				'description'     => [
					'type'        => 'string',
					'description' => __( 'Snippet description.', 'insert-php' ),
				],
				'conditions'      => [
					'type'        => 'string',
					'description' => __( 'Display conditions for non-PHP snippets as a JSON string, in the format the conditions editor stores: [{"type":"showif","conditions":[{"type":"scope","conditions":[{"param":"location-some-page","operator":"equals","type":"select","value":"base_web"}]}]}]. Groups hold AND-ed scopes, scopes hold OR-ed conditions. Pass "[]" to clear. Params starting with technology- or auditory- require the premium plugin.', 'insert-php' ),
				],
				'dry_run'         => [
					'type'        => 'boolean',
					'description' => __( 'Validate only; nothing is saved. Validation of PHP and universal snippets evaluates the code once.', 'insert-php' ),
					'default'     => false,
				],
			],
			'additionalProperties' => false,
		];
	}

	/**
	 * Output schema of a snippet.
	 *
	 * @param bool $with_code Whether the code and display conditions are included.
	 *
	 * @return array<string, mixed>
	 */
	private function get_snippet_schema( $with_code ) {
		$properties = [
			'id'                => [ 'type' => 'integer' ],
			'title'             => [ 'type' => 'string' ],
			'type'              => [ 'type' => 'string' ],
			'status'            => [ 'type' => 'string' ],
			'active'            => [ 'type' => 'boolean' ],
			'scope'             => [ 'type' => 'string' ],
			'location'          => [ 'type' => 'string' ],
			'location_number'   => [ 'type' => 'integer' ],
			'priority'          => [ 'type' => 'integer' ],
			'description'       => [ 'type' => 'string' ],
			'shortcode'         => [ 'type' => 'string' ],
			'execution_allowed' => [ 'type' => 'boolean' ],
			'modified_gmt'      => [ 'type' => 'string' ],
		];

		if ( $with_code ) {
			$properties['code']       = [ 'type' => 'string' ];
			$properties['conditions'] = [
				'type'  => 'array',
				'items' => [ 'type' => 'object' ],
			];
		}

		return [
			'type'       => 'object',
			'properties' => $properties,
		];
	}
}
