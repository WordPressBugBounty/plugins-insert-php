<?php
/**
 * Execute snippet
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_Execute_Snippet {

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * @var array
	 */
	public $snippets;

	/**
	 * @var WINP_Insertion_Locations
	 */
	public $snippets_locations;

	/**
	 * Current snippet ID being executed.
	 * 
	 * @var int Current snippet ID being executed (for error handling).
	 */
	private static $current_snippet_id = 0;

	/**
	 * Indicates whether shutdown handler has been registered.
	 * 
	 * @var bool Whether shutdown handler has been registered.
	 */
	private static $shutdown_registered = false;

	/**
	 * Tracked executed snippets on current page.
	 * 
	 * @var array<int, array{id: int, name: string, type: string, location: string, scope: string}>
	 */
	private $executed_snippets = [];

	public static function app() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get executed snippets on current page.
	 * 
	 * @return array<int, array{id: int, name: string, type: string, location: string, scope: string}>
	 */
	public function get_executed_snippets() {
		return $this->executed_snippets;
	}

	/**
	 * WINP_Execute_Snippet constructor.
	 */
	public function __construct() {
		self::$instance = $this;
		
		// Check if this is an AJAX validation request and skip the snippet being validated.
		if ( wp_doing_ajax() 
			&& isset( $_POST['action'] ) && 'wbcr_inp_ajax_validate_snippet' === $_POST['action'] 
			&& isset( $_POST['post_id'] ) ) {
			$validating_snippet_id = (int) $_POST['post_id'];
			add_filter(
				'winp_skip_snippet_execution',
				function ( $should_skip, $snippet_id ) use ( $validating_snippet_id ) {
					return $should_skip || $snippet_id === $validating_snippet_id;
				},
				1,
				2
			);
		}

		if ( ! defined( 'WINP_UPLOAD_DIR' ) ) {
			$dir = wp_upload_dir();
			define( 'WINP_UPLOAD_DIR', $dir['basedir'] . '/winp-css-js' );
		}

		if ( ! defined( 'WINP_UPLOAD_URL' ) ) {
			$dir = wp_upload_dir();
			define( 'WINP_UPLOAD_URL', $dir['baseurl'] . '/winp-css-js' );
		}
		global $wpdb;

		// todo: Simplify your request. Seems written ugly
		$sql = "SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_content, p2.meta_value as priority
 					FROM {$wpdb->posts}
 					INNER JOIN {$wpdb->postmeta} p1 ON ({$wpdb->posts}.ID = p1.post_id)
 					INNER JOIN {$wpdb->postmeta} p2 ON ({$wpdb->posts}.ID = p2.post_id)
 					INNER JOIN {$wpdb->postmeta} p3 ON ({$wpdb->posts}.ID = p3.post_id)
					WHERE (( p1.meta_key = 'wbcr_inp_snippet_scope' AND p1.meta_value = '%s')
					     AND
					      ( p3.meta_key = 'wbcr_inp_snippet_activate' AND p3.meta_value = '1')
						 AND p2.meta_key = 'wbcr_inp_snippet_priority' ) 
 					AND {$wpdb->posts}.post_type = '" . WINP_SNIPPETS_POST_TYPE . "' 
 					AND ({$wpdb->posts}.post_status = 'publish')
 					ORDER BY CAST(priority AS UNSIGNED) %s";

		$this->snippets['evrywhere'] = $wpdb->get_results( sprintf( $sql, 'evrywhere', 'DESC' ) );
		$this->snippets['auto']      = $wpdb->get_results( sprintf( $sql, 'auto', 'ASC' ) );

		global $winp_snippets_locations;
		$this->snippets_locations = new WINP_Insertion_Locations();
	}

	/**
	 * Register hooks
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'execute_everywhere_snippets' ], 1 );

		if ( ! is_admin() ) { // issue PCS-45 fix bug with WPBPage Builder Frontend Editor
			add_action( 'wp_head', [ $this, 'execute_header_snippets' ] );
			add_action( 'wp_footer', [ $this, 'execute_footer_snippets' ] );
			add_action( 'the_post', [ $this, 'executePostSnippets' ], 10, 2 );
			add_filter( 'the_content', [ $this, 'executeContentSnippets' ] );
			add_filter( 'the_excerpt', [ $this, 'executeExcerptSnippets' ] );
			// Бесполезный хук, который вызывается на каждый комментарий. Если их много, увеличивается нагрузка
			// add_filter( 'wp_list_comments_args', [ $this, 'executeListCommentsSnippets' ] );

			// add_action( 'wp_head', [ $this, 'executeWoocommerceSnippets' ] );

			if ( ! empty( $this->snippets_locations->getInsertion( 'custom' ) ) ) {
				add_action( 'wp_head', [ $this, 'executeCustomSnippets' ] );
			}
		}
	}

	/**
	 * Execute the everywhere snippets once the plugins are loaded
	 */
	public function execute_everywhere_snippets() {
		echo $this->execute_active_snippets( 'evrywhere' );
	}

	/**
	 * Execute the snippets in header of page once the plugins are loaded
	 */
	public function execute_header_snippets() {
		echo $this->execute_active_snippets( 'auto', 'header' );
	}

	/**
	 * Execute the snippets in footer of page once the plugins are loaded
	 */
	public function execute_footer_snippets() {
		echo $this->execute_active_snippets( 'auto', 'footer' );
	}

	/**
	 * Execute the snippets before post
	 *
	 * @param WP_Post  $post
	 * @param WP_Query $query
	 */
	public function executePostSnippets( $post, $query ) {
		$content = '';

		$post_type = ! empty( $post ) ? $post->post_type : get_post( $post->ID )->post_type;
		if ( is_singular( [ $post_type ] ) ) {
			if ( did_action( 'get_header' ) ) {
				// Перед заголовком
				$content = $this->execute_active_snippets( 'auto', 'before_post' );
			}
		} elseif ( $query->post_count > 0 ) {
			if ( $query->post_count > 1 && $query->current_post > 0 && $query->post_count > $query->current_post ) {
				// Между записями
				$content = $this->execute_active_snippets( 'auto', 'between_posts' );
			}
				// Перед записью
				$content .= $this->execute_active_snippets( 'auto', 'before_posts', '', $query );

				// После записи
				$content .= $this->execute_active_snippets( 'auto', 'after_posts', '', $query );
		}

		echo $content;
	}

	/**
	 * Handle paragraph content
	 *
	 * @param $content
	 * @param $snippet_content
	 * @param $paragraph_number
	 * @param $type
	 *
	 * @return mixed
	 */
	private function handleParagraphContent( $content, $snippet_content, $paragraph_number, $type = 'before' ) {
		if ( 'before' == $type ) {
			preg_match_all( '/<p(.*?)>/', $content, $matches );
		} else {
			preg_match_all( '/<\/p>/', $content, $matches );
		}
		$paragraphs = $matches[0];

		if ( $paragraph_number == 0 ) {
			$paragraph_number = 1;
		}

		if ( $content && $snippet_content && $paragraphs && $paragraph_number <= count( $paragraphs ) ) {
			$offset = 0;
			foreach ( $paragraphs as $paragraph_key => $paragraph ) {
				$position = strpos( $content, $paragraph, $offset ); // Позиция тега параграфа
				// Если указанный номер параграфа совпадает с текущим
				if ( $paragraph_key + 1 == $paragraph_number ) {
					if ( 'before' == $type ) {
						$content = substr( $content, 0, $position ) . $snippet_content . substr( $content, $position );
					} else {
						$content = substr( $content, 0, $position + 4 ) . $snippet_content . substr( $content, $position + 4 );
					}
					break;
				} else {
					$offset = $position + 1;
				}
			}
		}

		return $content;
	}

	/**
	 * Handle posts content
	 *
	 * @param string  $content
	 * @param string  $snippet_content
	 * @param integer $post_number
	 * @param string  $type
	 * @param object  $query
	 *
	 * @return mixed
	 */
	private function handlePostsContent( $content, $snippet_content, $post_number, $type, $query ) {
		global $winp_after_post_content;
		if ( $query->post_count > 0 ) {
			if ( $post_number == 0 ) {
				$post_number = 1;
			}

			if ( 'before' == $type && $query->current_post + 1 == $post_number ) {
				return $snippet_content;
			} elseif ( 'after' == $type ) {
				// Номер поста совпадает
				if ( $query->current_post == $post_number ) {
					return $snippet_content;
					// Если это последний пост и указанный номер поста больше общего количества постов,
					// то нужно сохранить контент сниппета для вывода в конце данного поста
				} elseif ( $query->current_post + 1 == $query->post_count && $post_number >= $query->post_count ) {
					$winp_after_post_content[ $query->post->ID ] = $snippet_content;
				}
			}
		}

		return $content;
	}

	/**
	 * Execute the snippets page content
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	public function executeContentSnippets( $content ) {
		global $post, $winp_after_post_content;

		$post_type = ! empty( $post ) ? $post->post_type : false;

		if ( is_category() || is_archive() || is_tag() || is_tax() || is_search() ) {
			// Перед коротким описанием
			$content = $this->execute_active_snippets( 'auto', 'before_excerpt' ) . $content;

			// После короткого описания
			$content .= $this->execute_active_snippets( 'auto', 'after_excerpt' );
		}

		if ( is_singular( [ $post_type ] ) ) {
			// Перед параграфом
			$content = $this->execute_active_snippets( 'auto', 'before_paragraph', $content );

			// После параграфа
			$content = $this->execute_active_snippets( 'auto', 'after_paragraph', $content );

			// После заголовка
			$content = $this->execute_active_snippets( 'auto', 'before_content' ) . $content;

			// После текста
			$content .= $this->execute_active_snippets( 'auto', 'after_content' );

			// После поста
			$content .= $this->execute_active_snippets( 'auto', 'after_post' );

			if ( ! comments_open( $post->ID ) && ! get_comments_number( $post->ID ) ) {
				remove_filter( 'wp_list_comments_args', [ $this, 'executeListCommentsSnippets' ] );
			}
		} elseif ( ! is_null( $post ) && isset( $winp_after_post_content[ $post->ID ] ) ) {
			// После последнего поста в списке
			$content .= $winp_after_post_content[ $post->ID ];
			unset( $winp_after_post_content[ $post->ID ] );
		}

		return $content;
	}

	/**
	 * Execute the snippets page excerpt
	 *
	 * @param $excerpt
	 *
	 * @return mixed
	 */
	public function executeExcerptSnippets( $excerpt ) {
		if ( is_category() || is_archive() || is_tag() || is_tax() || is_search() ) {
			// Перед коротким описанием
			$excerpt = $this->execute_active_snippets( 'auto', 'before_excerpt' ) . $excerpt;

			// После короткого описания
			$excerpt .= $this->execute_active_snippets( 'auto', 'after_excerpt' );
		}

		return $excerpt;
	}

	/**
	 * Execute the list comments filter
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public function executeListCommentsSnippets( $args ) {
		global $winp_wp_data;

		$winp_wp_data['winp_comments_saved_end_callback'] = $args['end-callback'];
		$args['end-callback']                             = [ $this, 'executeCommentsSnippets' ];

		return $args;
	}

	/**
	 * Execute the snippets after page comments
	 *
	 * @param $comment
	 * @param $args
	 * @param $depth
	 */
	public function executeCommentsSnippets( $comment, $args, $depth ) {
		global $winp_wp_data, $post;

		if ( ! empty( $winp_wp_data['winp_comments_saved_end_callback'] ) ) {
			echo call_user_func( $winp_wp_data['winp_comments_saved_end_callback'], $comment, $args, $depth );
		}

		$content = '';

		$post_type = ! empty( $post ) ? $post->post_type : false;
		if ( is_singular( [ $post_type ] ) ) {
			// После комментариев
			$content = $this->execute_active_snippets( 'auto', 'after_post' );
		}

		echo $content;
	}

	/**
	 * Execute the custom snippets
	 *
	 * @since 2.4
	 */
	public function executeCustomSnippets() {
		$locations = $this->snippets_locations->getInsertion( 'custom' );
		foreach ( $locations as $location => $data ) {
			$this->execute_active_snippets( 'auto', $location );
		}
	}

	/**
	 * Execute Woocommerce actions/hooks
	 *
	 * @param $location
	 * @param $snippet_content
	 *
	 * @since 2.4
	 */
	public function woocommerce_actions( $location, $snippet_content = '' ) {
		$action = function () use ( $location, $snippet_content ) {
			echo $snippet_content;
		};

		switch ( $location ) {
			case 'woo_before_shop_loop':
				add_filter(
					'woocommerce_product_loop_start',
					function ( $content ) use ( $snippet_content ) {
						return $snippet_content . $content;
					} 
				);
				break;
			case 'woo_after_shop_loop':
				add_filter(
					'woocommerce_product_loop_end',
					function ( $content ) use ( $snippet_content ) {
						return $content . $snippet_content;
					} 
				);
				break;
			case 'woo_before_single_product':
				add_action( 'woocommerce_before_single_product', $action, 10, 2 );
				break;
			case 'woo_after_single_product':
				add_action( 'woocommerce_after_single_product', $action, 10, 2 );
				break;
			case 'woo_before_single_product_summary':
				add_action( 'woocommerce_before_single_product_summary', $action, 10, 2 );
				break;
			case 'woo_after_single_product_summary':
				add_action( 'woocommerce_after_single_product_summary', $action, 10, 2 );
				break;
			case 'woo_single_product_summary_title':
				add_action( 'woocommerce_single_product_summary', $action, 6, 2 );
				break;
			case 'woo_single_product_summary_price':
				add_action( 'woocommerce_single_product_summary', $action, 15, 2 );
				break;
			case 'woo_single_product_summary_excerpt':
				add_action( 'woocommerce_single_product_summary', $action, 25, 2 );
				break;
			default:
				break;
		}
	}

	/**
	 * Execute Woocommerce actions/hooks
	 *
	 * @param $location
	 * @param $snippet_content
	 *
	 * @since 2.4
	 */
	public function custom_actions( $location, $snippet_content = '' ) {
		if ( ! empty( $this->snippets_locations->getLocation( $location ) ) ) {
			/**
			 * Action for a custom location applied in 'wbcr/woody/add_custom_location' filter
			 *
			 * @param array $location Slug of the location.
			 * @param string $snippet_content Rendered snippet content
			 *
			 * @since 2.4
			 */
			do_action( "wbcr/woody/do_custom_location/{$location}", $snippet_content );
		}
	}

	/**
	 * Execute the snippets once the plugins are loaded
	 *
	 * @param string $scope
	 * @param string $location
	 * @param string $content
	 * @param array  $custom_params
	 *
	 * @return string
	 */
	public function execute_active_snippets( $scope = 'evrywhere', $location = '', $content = '', $custom_params = [] ) {
		$snippets = $this->snippets[ $scope ] ?? [];

		if ( ! empty( $snippets ) ) {
			foreach ( (array) $snippets as $snippet ) {
				$id = (int) $snippet->ID;
				
				// Allow filtering to skip specific snippet IDs.
				$should_skip = apply_filters( 'winp_skip_snippet_execution', false, $id );
				if ( $should_skip ) {
					continue;
				}
				
				// $is_active = (int) WINP_Helper::getMetaOption( $id, 'snippet_activate', 0 );
				// Если это сниппет с автовставкой и выбранное место подходит под активный action
				$avail_place = ( 'auto' == $scope ? $location == WINP_Helper::getMetaOption( $id, 'snippet_location', '' ) : true );
				// Если условие отображения сниппета выполняется
				$snippet_type = WINP_Helper::getMetaOption( $id, 'snippet_type', WINP_SNIPPET_TYPE_PHP );
				$is_condition = $snippet_type != WINP_SNIPPET_TYPE_PHP ? $this->checkCondition( $id ) : true;

				if ( $avail_place && $is_condition ) {
					$post_id = (int) WINP_HTTP::post( 'post_ID', 0 );

					if ( ( isset( $_POST['wbcr_inp_snippet_scope'] )
							&& $post_id === $id
							&& WINP_Plugin::app()->current_user_car() ) || WINP_Helper::is_safe_mode() ) {
						return $content;
					}

					// WPML Compatibility
					if ( defined( 'WPML_PLUGIN_FILE' ) ) {
						$wpml_langs = WINP_Helper::getMetaOption( $id, 'snippet_wpml_lang', '' );
						if ( $wpml_langs !== '' && defined( 'ICL_LANGUAGE_CODE' ) ) {
							if ( ! in_array( ICL_LANGUAGE_CODE, explode( ',', $wpml_langs ) ) ) {
								continue;
							}
						}
					}

					$snippet_code = WINP_Helper::get_snippet_code( $snippet );

					/**
					 * Filter snippet code before execute
					 */
					$snippet_code = apply_filters( 'wbcr/inp/execute_snippet/snippet_code', $snippet_code, $id );

					// Track this snippet as executed.
					$this->track_executed_snippet( $id, $scope );

					if ( get_option( 'wbcr_inp_execute_shortcode' ) ) {
						$snippet_code = do_shortcode( $snippet_code );
					}

					if ( $snippet_type === WINP_SNIPPET_TYPE_TEXT || $snippet_type === WINP_SNIPPET_TYPE_AD ) {
						$snippet_content = '<div class="winp-text-snippet-container">' . $snippet_code . '</div>';
					} elseif ( $snippet_type === WINP_SNIPPET_TYPE_CSS || $snippet_type === WINP_SNIPPET_TYPE_JS ) {
						$snippet_content = self::getJsCssSnippetData( $id );
					} elseif ( $snippet_type === WINP_SNIPPET_TYPE_HTML ) {
						$snippet_content = $snippet_code;
					} else {
						$code = $this->prepareCode( $snippet_code, $id );
						ob_start();
						$this->executeSnippet( $code, $id, false );
						$snippet_content = ob_get_contents();
						ob_end_clean();
					}

					// If the user has prohibited the insertion of unfiltered HTML,
					// we prohibit the execution of snippets.
					if ( ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML )
						&& ! in_array(
							$snippet_type,
							[
								WINP_SNIPPET_TYPE_TEXT,
								WINP_SNIPPET_TYPE_AD,
								WINP_SNIPPET_TYPE_CSS,
							] 
						) ) {
						$snippet_content = '';

						if ( is_user_logged_in() && WINP_Plugin::app()->current_user_car() ) {
							$error_text = __( 'This Woody snippet cannot run because unfiltered HTML insertion is disabled.', 'insert-php' );

							switch ( $location ) {
								case 'header':
								case 'footer':
									$snippet_content = '<!-- ' . $error_text . '-->';
									break;
								default:
									$snippet_content = $error_text;
							}
						}
					}

					if ( 'auto' == $scope ) {
						switch ( $location ) {
							case 'before_paragraph': // Перед параграфом
								$location_number = WINP_Helper::getMetaOption( $id, 'snippet_p_number', 0 );
								$content         = $this->handleParagraphContent( $content, $snippet_content, $location_number );
								break;
							case 'after_paragraph': // После параграфа
								$location_number = WINP_Helper::getMetaOption( $id, 'snippet_p_number', 0 );
								$content         = $this->handleParagraphContent( $content, $snippet_content, $location_number, 'after' );
								break;
							case 'before_posts':   // Перед записью
								$location_number = WINP_Helper::getMetaOption( $id, 'snippet_p_number', 0 );
								$content         = $this->handlePostsContent( $content, $snippet_content, $location_number, 'before', $custom_params );
								break;
							case 'after_posts':   // После записи
								$location_number = WINP_Helper::getMetaOption( $id, 'snippet_p_number', 0 );
								$content         = $this->handlePostsContent( $content, $snippet_content, $location_number, 'after', $custom_params );
								break;
							default:
								$content = $snippet_content . $content;
						}

						/**
						 * Action for woo actions
						 *
						 * @param array $location Slug of the location.
						 * @param string $snippet_content Rendered snippet content
						 *
						 * @since 2.4
						 */
						do_action( 'wbcr/woody/do_woocommerce_actions', $location, $snippet_content );

						// $this->woocommerce_actions( $location, $snippet_content );
						$this->custom_actions( $location, $snippet_content );
					} else {
						$content = $snippet_content . $content;
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Track shortcode snippet execution.
	 * 
	 * Public method for shortcode classes to call.
	 * 
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	public function track_shortcode_snippet( $snippet_id ) {
		$this->track_executed_snippet( $snippet_id, 'shortcode' );
	}

	/**
	 * Track executed snippet.
	 * 
	 * @param int    $snippet_id Snippet ID.
	 * @param string $scope Snippet scope.
	 * @return void
	 */
	private function track_executed_snippet( $snippet_id, $scope ) {
		if ( isset( $this->executed_snippets[ $snippet_id ] ) ) {
			return; // Already tracked.
		}

		$snippet = get_post( $snippet_id );
		if ( ! $snippet ) {
			return;
		}

		$snippet_type     = WINP_Helper::getMetaOption( $snippet_id, 'snippet_type', WINP_SNIPPET_TYPE_PHP );
		$snippet_location = WINP_Helper::getMetaOption( $snippet_id, 'snippet_location', '' );

		// Map location to human-readable label.
		$location_label = $this->get_location_label( $scope, $snippet_location );

		$this->executed_snippets[ $snippet_id ] = [
			'id'       => $snippet_id,
			'name'     => $snippet->post_title,
			'type'     => $this->get_type_label( $snippet_type ),
			'location' => $location_label,
			'scope'    => $scope,
		];
	}

	/**
	 * Get human-readable location label.
	 * 
	 * @param string $scope Snippet scope.
	 * @param string $location Snippet location.
	 * @return string
	 */
	private function get_location_label( $scope, $location ) {
		if ( 'evrywhere' === $scope ) {
			return __( 'Everywhere', 'insert-php' );
		}

		if ( 'shortcode' === $scope ) {
			return __( 'Shortcode', 'insert-php' );
		}

		$location_labels = [
			'header'           => __( 'Header', 'insert-php' ),
			'footer'           => __( 'Footer', 'insert-php' ),
			'before_post'      => __( 'Before Post', 'insert-php' ),
			'before_content'   => __( 'Before Content', 'insert-php' ),
			'before_paragraph' => __( 'Before Paragraph', 'insert-php' ),
			'after_paragraph'  => __( 'After Paragraph', 'insert-php' ),
			'after_content'    => __( 'After Content', 'insert-php' ),
			'after_post'       => __( 'After Post', 'insert-php' ),
			'before_excerpt'   => __( 'Before Excerpt', 'insert-php' ),
			'after_excerpt'    => __( 'After Excerpt', 'insert-php' ),
			'before_posts'     => __( 'Before Posts', 'insert-php' ),
			'after_posts'      => __( 'After Posts', 'insert-php' ),
			'after_comments'   => __( 'After Comments', 'insert-php' ),
		];

		return $location_labels[ $location ] ?? ucwords( str_replace( '_', ' ', $location ) );
	}

	/**
	 * Get human-readable type label.
	 * 
	 * @param string $type Snippet type.
	 * @return string
	 */
	private function get_type_label( $type ) {
		$type_labels = [
			WINP_SNIPPET_TYPE_PHP       => 'PHP',
			WINP_SNIPPET_TYPE_UNIVERSAL => 'Universal',
			WINP_SNIPPET_TYPE_HTML      => 'HTML',
			WINP_SNIPPET_TYPE_CSS       => 'CSS',
			WINP_SNIPPET_TYPE_JS        => 'JS',
			WINP_SNIPPET_TYPE_TEXT      => 'TEXT',
			WINP_SNIPPET_TYPE_AD        => 'AD',
		];

		return $type_labels[ $type ] ?? 'PHP';
	}

	/**
	 * Get js or css snippet data
	 *
	 * @param $snippet_id
	 *
	 * @return mixed|string
	 */
	public static function getJsCssSnippetData( $snippet_id ) {
		$snippet_type = WINP_Helper::get_snippet_type( $snippet_id );

		$linking  = WINP_Helper::getMetaOption( $snippet_id, 'snippet_linking' );
		$filetype = WINP_Helper::getMetaOption( $snippet_id, 'filetype', $snippet_type );

		$file_name = $snippet_id . '.' . $filetype;
		$slug      = WINP_Helper::getMetaOption( $snippet_id, 'css_js_slug' );
		if ( ! empty( $slug ) ) {
			$file_name = $slug . '.' . $filetype;
		}

		if ( file_exists( WINP_UPLOAD_DIR . '/' . $file_name ) ) {
			if ( 'inline' == $linking ) {
				return file_get_contents( WINP_UPLOAD_DIR . '/' . $file_name );
			}

			if ( 'external' == $linking ) {
				$file_name .= '?ver=' . WINP_Helper::getMetaOption( $snippet_id, 'css_js_version', time() );

				if ( 'js' == $snippet_type ) {
					return PHP_EOL . "<script type='text/javascript' src='" . WINP_UPLOAD_URL . '/' . $file_name . "'></script>" . PHP_EOL;
				}

				if ( 'css' == $snippet_type ) {
					$short_filename = preg_replace( '@\.css\?ver=.*$@', '', $file_name );

					return PHP_EOL . "<link rel='stylesheet' id='" . $short_filename . "-css'  href='" . WINP_UPLOAD_URL . '/' . $file_name . "' type='text/css' media='all' />" . PHP_EOL;
				}
			}
		}

		return '';
	}

	/**
	 * Execute a snippet
	 *
	 * Code must NOT be escaped, as
	 * it will be executed directly
	 *
	 * @param string $code The snippet code to execute.
	 * @param int    $id The snippet ID.
	 * @param bool   $catch_output Whether to attempt to suppress the output of execution using buffers.
	 *
	 * @return mixed        The result of the code execution
	 */
	public function executeSnippet( $code, $id = 0, $catch_output = true ) {
		$id = (int) $id;

		if ( ! $id || empty( $code ) ) {
			return false;
		}

		if ( $catch_output ) {
			ob_start();
		}

		$snippet = get_post( $id );

		if ( empty( $snippet ) || $snippet->post_type !== WINP_SNIPPETS_POST_TYPE ) {
			return false;
		}

		$snippet_type = WINP_Helper::getMetaOption( $id, 'snippet_type', true );

		// Set current snippet ID for error handling.
		self::$current_snippet_id = $id;

		// Register shutdown function once to catch fatal errors.
		if ( ! self::$shutdown_registered ) {
			register_shutdown_function( [ $this, 'handle_snippet_shutdown' ] );
			self::$shutdown_registered = true;
		}

		if ( $snippet_type == WINP_SNIPPET_TYPE_UNIVERSAL ) {
			$result = eval( '?>' . $code . '<?php ' );
		} elseif ( $snippet_type == WINP_SNIPPET_TYPE_PHP ) {
			$result = eval( $code );
		} else {
			$result = ! empty( $code );
		}

		if ( $catch_output ) {
			ob_end_clean();
		}

		return $result;
	}

	/**
	 * Handle fatal errors during snippet execution.
	 * 
	 * @return void
	 */
	public function handle_snippet_shutdown() {
		$error = error_get_last();

		// If there's a fatal error and a snippet was being executed, check if it's from the snippet.
		if ( self::$current_snippet_id && $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ] ) ) {
			// Only log if the error originated from the snippet code itself.
			if ( $this->is_error_from_snippet( $error ) ) {
				$this->log_snippet_error( self::$current_snippet_id, $error );
			}
		}
		
		// Clear the snippet ID after processing.
		self::$current_snippet_id = 0;
	}

	/**
	 * Check if the error originated from the snippet code.
	 * 
	 * @param array<string, mixed> $error Error details.
	 * 
	 * @return bool True if error is from snippet, false otherwise.
	 */
	private function is_error_from_snippet( $error ) {
		if ( ! isset( $error['file'] ) ) {
			return false;
		}

		$error_file = $error['file'];

		// Check if error is from eval'd code (PHP snippets executed via eval).
		if ( strpos( $error_file, "eval()'d code" ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Log snippet error via admin notice
	 *
	 * @param int                  $snippet_id Snippet ID.
	 * @param array<string, mixed> $error Error details.
	 * 
	 * @return void
	 */
	private function log_snippet_error( $snippet_id, $error ) {
		// Validate error array has required keys.
		if ( ! isset( $error['message'], $error['file'], $error['line'] ) ) {
			return;
		}
		
		// Generate unique notice ID based on snippet ID and error details.
		$error_signature = md5( $snippet_id . $error['message'] . $error['file'] . $error['line'] );
		$notice_id       = 'snippet_error_' . $error_signature;

		// Get snippet title for better context.
		$snippet       = get_post( $snippet_id );
		$snippet_title = $snippet ? $snippet->post_title : "ID {$snippet_id}";

		// Extract the main error message (before stack trace).
		$error_message = $error['message'];
		if ( strpos( $error_message, ' Stack trace:' ) !== false ) {
			$error_message = substr( $error_message, 0, strpos( $error_message, ' Stack trace:' ) );
		}
		$error_message = trim( $error_message );

		// Shorten file path for readability.
		$file_path = str_replace( ABSPATH, '', $error['file'] );

		// Get edit link for the snippet.
		$edit_link = admin_url( 'post.php?post=' . $snippet_id . '&action=edit' );

		// Build notice message.
		$message = sprintf(
			'<div class="winp-error-notice-header"><strong>%s</strong> %s <a href="%s" class="winp-snippet-edit-link" target="_blank">%s</a></div><details><summary>%s</summary><div class="winp-error-details"><div class="winp-error-message"><strong>%s:</strong><br><code>%s</code></div><div class="winp-error-location"><strong>%s:</strong><br>%s <span class="winp-line-number">%s %d</span></div></div></details><div class="winp-error-notice-footer"><p class="winp-dismiss-help">%s</p><button type="button" class="button winp-manual-dismiss-btn">%s</button></div>',
			__( 'Snippet Error Detected', 'insert-php' ),
			// Translators: 1: Snippet title.
			sprintf( __( 'Snippet "%s" caused a fatal error.', 'insert-php' ), esc_html( $snippet_title ) ),
			esc_url( $edit_link ),
			__( 'Edit Snippet', 'insert-php' ),
			__( 'Show error details', 'insert-php' ),
			__( 'Error', 'insert-php' ),
			esc_html( $error_message ),
			__( 'Location', 'insert-php' ),
			esc_html( $file_path ),
			__( 'line', 'insert-php' ),
			$error['line'],
			__( 'If you have fixed this error, you can dismiss this notice.', 'insert-php' ),
			__( 'Dismiss', 'insert-php' )
		);

		// Store error notice in option to be displayed on next admin page load.
		$pending_notices = get_option( 'winp_pending_error_notices', [] );
		if ( ! is_array( $pending_notices ) ) {
			$pending_notices = [];
		}

		// Only add if not already present (avoid race conditions).
		if ( ! isset( $pending_notices[ $notice_id ] ) ) {
			$pending_notices[ $notice_id ] = [
				'message' => $message,
				'type'    => 'error',
			];
			
			update_option( 'winp_pending_error_notices', $pending_notices, false );

			// Send email notification if enabled and this is a new error.
			$this->send_error_email( $notice_id, $snippet_id, $snippet_title, $error_message, $file_path, $error['line'], $edit_link );
		}
	}

	/**
	 * Send email notification for snippet error
	 *
	 * @param string $error_signature Unique error identifier.
	 * @param int    $snippet_id The ID of the snippet.
	 * @param string $snippet_title The title of the snippet.
	 * @param string $error_message The error message.
	 * @param string $file_path The file path where error occurred.
	 * @param int    $line_number The line number where error occurred.
	 * @param string $edit_link Link to edit the snippet.
	 *
	 * @return void
	 */
	private function send_error_email( $error_signature, $snippet_id, $snippet_title, $error_message, $file_path, $line_number, $edit_link ) {
		// Check if email notifications are enabled.
		$email_enabled = get_option( 'wbcr_inp_error_email_enabled' );
		if ( ! $email_enabled ) {
			return;
		}

		// Get email address.
		$email_address = get_option( 'wbcr_inp_error_email_address', get_option( 'admin_email' ) );
		if ( empty( $email_address ) || ! is_email( $email_address ) ) {
			return;
		}

		// Check if we've already emailed about this error.
		$emailed_errors = get_option( 'winp_emailed_errors', [] );
		if ( ! is_array( $emailed_errors ) ) {
			$emailed_errors = [];
		}

		// If we've already emailed about this error, skip.
		if ( isset( $emailed_errors[ $error_signature ] ) ) {
			return;
		}

		// Clean up old entries (older than 30 days).
		$thirty_days_ago = time() - ( 30 * DAY_IN_SECONDS );
		foreach ( $emailed_errors as $hash => $timestamp ) {
			if ( $timestamp < $thirty_days_ago ) {
				unset( $emailed_errors[ $hash ] );
			}
		}

		// Build email content.
		$site_name = get_bloginfo( 'name' );
		$admin_url = admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE );
		$subject   = sprintf( '[%s] Snippet Error Detected: %s', $site_name, $snippet_title );

		$email_body = $this->get_error_email_template( $site_name, $snippet_id, $snippet_title, $error_message, $file_path, $line_number, $edit_link, $admin_url );

		// Set email headers for HTML.
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		// Send email.
		$sent = wp_mail( $email_address, $subject, $email_body, $headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail

		// If email sent successfully, mark this error as emailed.
		if ( $sent ) {
			$emailed_errors[ $error_signature ] = time();
			update_option( 'winp_emailed_errors', $emailed_errors, false );
		}
	}

	/**
	 * Get HTML email template for error notification
	 *
	 * @param string $site_name Site name.
	 * @param int    $snippet_id Snippet ID.
	 * @param string $snippet_title Snippet title.
	 * @param string $error_message Error message.
	 * @param string $file_path File path.
	 * @param int    $line_number Line number.
	 * @param string $edit_link Edit link.
	 * @param string $admin_url Admin URL.
	 *
	 * @return string HTML email template.
	 */
	private function get_error_email_template( $site_name, $snippet_id, $snippet_title, $error_message, $file_path, $line_number, $edit_link, $admin_url ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( __( 'Snippet Error Notification', 'insert-php' ) ); ?></title>
		</head>
		<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif; background-color: #f0f0f1;">
			<table role="presentation" style="width: 100%; border-collapse: collapse;">
				<tr>
					<td align="center" style="padding: 40px 20px;">
						<table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border: 1px solid #c3c4c7; border-radius: 2px;">
							<!-- Header -->
							<tr>
								<td style="padding: 24px 30px; background-color: #2271b1; border-bottom: 1px solid #135e96;">
									<h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">
										⚠️ <?php echo esc_html( __( 'Snippet Error Detected', 'insert-php' ) ); ?>
									</h1>
								</td>
							</tr>

							<!-- Content -->
							<tr>
								<td style="padding: 30px;">
									<p style="margin: 0 0 16px; color: #1d2327; font-size: 14px; line-height: 1.6;">
										<?php echo esc_html( __( 'Hello,', 'insert-php' ) ); ?>
									</p>

									<p style="margin: 0 0 20px; color: #1d2327; font-size: 14px; line-height: 1.6;">
										<?php
										printf(
											// translators: 1: snippet title, 2: site name.
											esc_html( __( 'A fatal error has been detected in the snippet "%1$s" on your site %2$s.', 'insert-php' ) ),
											'<strong>' . esc_html( $snippet_title ) . '</strong>',
											'<strong>' . esc_html( $site_name ) . '</strong>'
										);
										?>
									</p>

									<!-- Error Details Box -->
									<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 24px 0; background-color: #fcf0f1; border-left: 4px solid #d63638; border-radius: 0;">
										<tr>
											<td style="padding: 16px 20px;">
												<p style="margin: 0 0 12px; color: #8c1c13; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
													<?php echo esc_html( __( 'Error Details', 'insert-php' ) ); ?>
												</p>

												<div style="margin-bottom: 12px;">
													<p style="margin: 0 0 6px; color: #3c434a; font-size: 13px; font-weight: 600;">
														<?php echo esc_html( __( 'Error Message:', 'insert-php' ) ); ?>
													</p>
													<p style="margin: 0; padding: 8px 12px; background-color: #ffffff; border: 1px solid #dcdcde; color: #1d2327; font-size: 13px; font-family: Consolas, Monaco, monospace; word-break: break-word; line-height: 1.5;">
														<?php echo esc_html( $error_message ); ?>
													</p>
												</div>

												<div style="margin-bottom: 12px;">
													<p style="margin: 0 0 6px; color: #3c434a; font-size: 13px; font-weight: 600;">
														<?php echo esc_html( __( 'Location:', 'insert-php' ) ); ?>
													</p>
													<p style="margin: 0; padding: 8px 12px; background-color: #ffffff; border: 1px solid #dcdcde; color: #1d2327; font-size: 13px; font-family: Consolas, Monaco, monospace; line-height: 1.5;">
														<?php echo esc_html( $file_path ); ?> <span style="color: #646970;"><?php echo esc_html( __( 'line', 'insert-php' ) ); ?> <?php echo intval( $line_number ); ?></span>
													</p>
												</div>

												<div>
													<p style="margin: 0 0 6px; color: #3c434a; font-size: 13px; font-weight: 600;">
														<?php echo esc_html( __( 'Snippet:', 'insert-php' ) ); ?>
													</p>
													<p style="margin: 0; padding: 8px 12px; background-color: #ffffff; border: 1px solid #dcdcde; color: #1d2327; font-size: 13px; line-height: 1.5;">
														<?php echo esc_html( $snippet_title ); ?> <span style="color: #646970;">(ID: <?php echo intval( $snippet_id ); ?>)</span>
													</p>
												</div>
											</td>
										</tr>
									</table>

									<!-- Action Buttons -->
									<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 24px 0;">
										<tr>
											<td style="padding: 0;">
												<a href="<?php echo esc_url( $edit_link ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 3px; font-weight: 500; font-size: 13px; margin-right: 8px; border: 1px solid #2271b1;">
													<?php echo esc_html( __( 'Edit Snippet', 'insert-php' ) ); ?>
												</a>
												<a href="<?php echo esc_url( $admin_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #f6f7f7; color: #2c3338; text-decoration: none; border-radius: 3px; font-weight: 500; font-size: 13px; border: 1px solid #c3c4c7;">
													<?php echo esc_html( __( 'View All Snippets', 'insert-php' ) ); ?>
												</a>
											</td>
										</tr>
									</table>

									<p style="margin: 20px 0 0; color: #646970; font-size: 13px; line-height: 1.6;">
										<?php echo esc_html( __( 'This notification is sent only once per unique error. You can disable these notifications in the plugin settings.', 'insert-php' ) ); ?>
									</p>
								</td>
							</tr>

							<!-- Footer -->
							<tr>
								<td style="padding: 16px 30px; background-color: #f6f7f7; border-top: 1px solid #dcdcde;">
									<p style="margin: 0; color: #646970; font-size: 12px; text-align: center;">
										<?php
										printf(
											// translators: %s: site name.
											esc_html( __( 'This email was sent by Woody Code Snippets on %s', 'insert-php' ) ),
											'<strong>' . esc_html( $site_name ) . '</strong>'
										);
										?>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php
		$output = ob_get_clean();
		return false !== $output ? $output : '';
	}

	/**
	 * Get property value
	 *
	 * @param $value
	 * @param $property
	 *
	 * @return null
	 */
	private function getPropertyValue( $value, $property ) {
		if ( is_object( $value ) ) {
			return $value->$property ?? null;
		} elseif ( isset( $value[ $property ] ) ) {
			return $value[ $property ];
		}

		return null;
	}

	/**
	 * Check conditional execution logic for the snippet
	 *
	 * @param $snippet_id
	 *
	 * @return bool
	 */
	public function checkCondition( $snippet_id ) {
		// Итоговый результат условий
		$result = true;
		// Получаем сохранённые параметры условий
		$filters = get_post_meta( $snippet_id, 'wbcr_inp_snippet_filters' );
		// Если условия указаны
		if ( ! ( empty( $filters ) || isset( $filters[0] ) && empty( $filters[0] ) ) ) {
			foreach ( $filters[0] as $filter ) {
				$conditions = $this->getPropertyValue( $filter, 'conditions' );
				// Если условия пусты, то пропускаем цикл
				if ( empty( $conditions ) ) {
					continue;
				}
				// Промежуточный результат AND условий
				$and_conditions = null;
				// Проходим по AND условиям
				foreach ( $conditions as $scope ) {
					$scope_conditions = $this->getPropertyValue( $scope, 'conditions' );
					// Если условия пусты, то пропускаем цикл
					if ( empty( $scope_conditions ) ) {
						continue;
					}
					// Промежуточный результат OR условий
					$or_conditions = null;
					// Проходим по OR условиям
					foreach ( $scope_conditions as $condition ) {
						$method_name = str_replace( '-', '_', $this->getPropertyValue( $condition, 'param' ) );
						$operator    = $this->getPropertyValue( $condition, 'operator' );
						$value       = $this->getPropertyValue( $condition, 'value' );
						// Получаем результат OR условий
						$or_conditions = is_null( $or_conditions ) ? $this->call_method( $method_name, $operator, $value ) : $or_conditions || $this->call_method( $method_name, $operator, $value );
					}
					// Получаем результат AND условий
					$and_conditions = is_null( $and_conditions ) ? $or_conditions : $and_conditions && $or_conditions;
				}
				// Получаем результат блока условий
				$result = $this->getPropertyValue( $filter, 'type' ) == 'showif' ? $and_conditions : ! $and_conditions;
			}
		}

		return $result;
	}

	/**
	 * Call specified method
	 *
	 * @param $method_name
	 * @param $operator
	 * @param $value
	 *
	 * @return bool
	 */
	private function call_method( $method_name, $operator, $value ) {
		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name( $operator, $value );
		} else {
			return apply_filters( 'wbcr/inp/execute/check_condition', false, $method_name, $operator, $value );
		}
	}

	/**
	 * Retrieve the first error in a snippet's code
	 *
	 * @param int $snippet_id
	 *
	 * @return array|bool
	 */
	public function getSnippetError( $snippet_id ) {
		if ( ! intval( $snippet_id ) ) {
			return false;
		}

		$snippet = get_post( $snippet_id );

		if ( ! $snippet ) {
			return false;
		}

		$snippet_code = WINP_Helper::get_snippet_code( $snippet );
		$snippet_code = $this->prepareCode( $snippet_code, $snippet_id );

		$result = $this->executeSnippet( $snippet_code, $snippet_id );

		if ( false !== $result ) {
			return false;
		}

		$error = error_get_last();

		if ( is_null( $error ) ) {
			return false;
		}

		return $error;
	}

	/**
	 * Prepare the code by removing php tags from beginning and end
	 *
	 * @param string  $code
	 * @param integer $snippet_id
	 *
	 * @return string
	 */
	public function prepareCode( $code, $snippet_id ) {
		$snippet_type = WINP_Helper::get_snippet_type( $snippet_id );

		if ( $snippet_type != WINP_SNIPPET_TYPE_UNIVERSAL
			&& $snippet_type != WINP_SNIPPET_TYPE_CSS
			&& $snippet_type != WINP_SNIPPET_TYPE_JS
			&& $snippet_type != WINP_SNIPPET_TYPE_HTML ) {

			/* Remove <?php and <? from beginning of snippet */
			$code = preg_replace( '|^[\s]*<\?(php)?|', '', $code );

			/* Remove ?> from end of snippet */
			$code = preg_replace( '|\?>[\s]*$|', '', $code );
		}

		return $code;
	}

	/**
	 * Get current URL
	 *
	 * @return string
	 */
	private function getCurrentUrl() {
		$out = '';
		$url = explode( '?', $_SERVER['REQUEST_URI'], 2 );
		if ( isset( $url[0] ) ) {
			$out = trim( $url[0], '/' );
		}

		return $out ? urldecode( $out ) : '/';
	}

	/**
	 * Get referer URL
	 *
	 * @return string
	 */
	private function getRefererUrl() {
		$out = '';
		$url = explode( '?', str_replace( site_url(), '', $_SERVER['HTTP_REFERER'] ), 2 );
		if ( isset( $url[0] ) ) {
			$out = trim( $url[0], '/' );
		}

		return $out ? urldecode( $out ) : '/';
	}

	/**
	 * Check by operator
	 *
	 * @param string $operation Comparison operator.
	 * @param mixed  $first First value.
	 * @param mixed  $second Second value.
	 * @param bool   $third Third value.
	 *
	 * @return bool
	 */
	public function check_by_operator( $operation, $first, $second, $third = false ) {
		switch ( $operation ) {
			case 'equals':
				if ( is_array( $second ) ) {
					return in_array( $first, $second );
				} else {
					return $first === $second;
				}
			case 'notequal':
				if ( is_array( $second ) ) {
					return ! in_array( $first, $second );
				} else {
					return $first !== $second;
				}
			case 'less':
			case 'older':
				return $first > $second;
			case 'greater':
			case 'younger':
				return $first < $second;
			case 'contains':
				return strpos( $first, $second ) !== false;
			case 'notcontain':
				return strpos( $first, $second ) === false;
			case 'between':
				return $first < $second && $second < $third;

			default:
				return $first === $second;
		}
	}

	/**
	 * A role of the user who views your website. The role "guest" is applied for unregistered users.
	 *
	 * @param string $operator
	 * @param string $value
	 *
	 * @return boolean
	 */
	private function user_role( $operator, $value ) {
		if ( ! is_user_logged_in() ) {
			return $this->check_by_operator( $operator, $value, 'guest' );
		} else {
			$current_user = wp_get_current_user();
			if ( ! ( $current_user instanceof WP_User ) ) {
				return false;
			}

			return $this->check_by_operator( $operator, $value, $current_user->roles[0] );
		}
	}

	/**
	 * Get timestamp
	 *
	 * @param string $units Time units.
	 * @param mixed  $count Count of units.
	 *
	 * @return integer
	 */
	private function get_timestamp( $units, $count ) {
		if ( ! is_numeric( $count ) ) {
			return 0;
		}
		
		$count = (int) $count;
		
		switch ( $units ) {
			case 'seconds':
				return $count;
			case 'minutes':
				return $count * MINUTE_IN_SECONDS;
			case 'hours':
				return $count * HOUR_IN_SECONDS;
			case 'days':
				return $count * DAY_IN_SECONDS;
			case 'weeks':
				return $count * WEEK_IN_SECONDS;
			case 'months':
				return $count * MONTH_IN_SECONDS;
			case 'years':
				return $count * YEAR_IN_SECONDS;

			default:
				return $count;
		}
	}

	/**
	 * Get date timestamp
	 *
	 * @param mixed $value Date value.
	 *
	 * @return int|mixed Returns 0 on validation failure, timestamp calculation, or the original value
	 */
	public function get_date_timestamp( $value ) {
		if ( is_object( $value ) ) {
			if ( ! isset( $value->units ) || ! isset( $value->unitsCount ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				return 0;
			}
			return ( current_time( 'timestamp' ) - $this->get_timestamp( $value->units, $value->unitsCount ) ) * 1000; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase, WordPress.DateTime.CurrentTimeTimestamp.Requested
		} else {
			return $value;
		}
	}

	/**
	 * The date when the user who views your website was registered.
	 * For unregistered users this date always equals to 1 Jan 1970.
	 *
	 * @param string $operator Comparison operator.
	 * @param mixed  $value  Comparison value (object for 'between', mixed otherwise).
	 *
	 * @return boolean
	 */
	private function user_registered( $operator, $value ) {
		if ( ! is_user_logged_in() ) {
			return false;
		} else {
			$user       = wp_get_current_user();
			$registered = strtotime( $user->data->user_registered );
			
			// Validate that we have a valid registration timestamp.
			if ( false === $registered ) {
				return false;
			}
			
			$registered = $registered * 1000;

			if ( 'equals' === $operator || 'notequal' === $operator ) {
				$registered = $registered / 1000;
				$timestamp  = $this->get_date_timestamp( $value );
				
				if ( ! $timestamp || $timestamp <= 0 ) {
					return false;
				}
				
				$timestamp = round( $timestamp / 1000 );

				return $this->check_by_operator( $operator, gmdate( 'Y-m-d', (int) $timestamp ), gmdate( 'Y-m-d', (int) $registered ) );
			} elseif ( 'between' === $operator ) {
				if ( ! is_object( $value ) || ! isset( $value->start ) || ! isset( $value->end ) ) {
					return false;
				}
				$start_timestamp = $this->get_date_timestamp( $value->start );
				$end_timestamp   = $this->get_date_timestamp( $value->end );
				
				if ( ! $start_timestamp || $start_timestamp <= 0 || ! $end_timestamp || $end_timestamp <= 0 ) {
					return false;
				}

				return $this->check_by_operator( $operator, $start_timestamp, $registered, $end_timestamp );
			} else {
				$timestamp = $this->get_date_timestamp( $value );
				
				if ( ! $timestamp || $timestamp <= 0 ) {
					return false;
				}

				return $this->check_by_operator( $operator, $timestamp, $registered );
			}
		}
	}

	/**
	 * Check the user views your website from mobile device or not
	 *
	 * @param string $operator Comparison operator.
	 * @param string $value Comparison value.
	 *
	 * @return boolean
	 *
	 * @link https://stackoverflow.com/a/4117597
	 */
	private function user_mobile( $operator, $value ) {
		$useragent = $_SERVER['HTTP_USER_AGENT'];

		if ( preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent ) || preg_match( '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr( $useragent, 0, 4 ) ) ) {
			return $operator === 'equals' && $value === 'yes' || $operator === 'notequal' && $value === 'no';
		} else {
			return $operator === 'notequal' && $value === 'yes' || $operator === 'equals' && $value === 'no';
		}
	}

	/**
	 * Determines whether the user's browser has a cookie with a given name
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	private function user_cookie_name( $operator, $value ) {
		if ( isset( $_COOKIE[ $value ] ) ) {
			return $operator === 'equals';
		} else {
			return $operator === 'notequal';
		}
	}

	/**
	 * A some selected page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	private function location_some_page( $operator, $value ) {
		$post_id = ( ! is_404() && ! is_search() && ! is_archive() && ! is_home() ) ? get_the_ID() : false;

		switch ( $value ) {
			case 'base_web':    // Basic - Entire Website
				$result = true;
				break;
			case 'base_sing':   // Basic - All Singulars
				$result = is_singular();
				break;
			case 'base_arch':   // Basic - All Archives
				$result = is_archive();
				break;
			case 'spec_404':    // Special Pages - 404 Page
				$result = is_404();
				break;
			case 'spec_search': // Special Pages - Search Page
				$result = is_search();
				break;
			case 'spec_blog':   // Special Pages - Blog / Posts Page
				$result = is_home();
				break;
			case 'spec_front':  // Special Pages - Front Page
				$result = is_front_page();
				break;
			case 'spec_date':   // Special Pages - Date Archive
				$result = is_date();
				break;
			case 'spec_auth':   // Special Pages - Author Archive
				$result = is_author();
				break;
			case 'post_all':    // Posts - All Posts
			case 'page_all':    // Pages - All Pages
				$result = false;
				if ( false !== $post_id ) {
					$post_type = 'post_all' == $value ? 'post' : 'page';
					$result    = $post_type == get_post_type( $post_id );
				}
				break;
			case 'post_arch':   // Posts - All Posts Archive
			case 'page_arch':   // Pages - All Pages Archive
				$result = false;
				if ( is_archive() ) {
					$post_type = 'post_arch' == $value ? 'post' : 'page';
					$result    = $post_type == get_post_type();
				}
				break;
			case 'post_cat':    // Posts - All Categories Archive
			case 'post_tag':    // Posts - All Tags Archive
				$result = false;
				if ( is_archive() && 'post' == get_post_type() ) {
					$taxonomy = 'post_tag' == $value ? 'post_tag' : 'category';
					$obj      = get_queried_object();

					$current_taxonomy = '';
					if ( '' !== $obj && null !== $obj ) {
						$current_taxonomy = $obj->taxonomy;
					}

					if ( $current_taxonomy == $taxonomy ) {
						$result = true;
					}
				}
				break;

			default:
				$result = false;
		}

		if ( WINP_Helper::is_woo_active() ) {
			switch ( $value ) {
				case 'woo_product':
					$result = is_product();
					break;
				case 'woo_arch':
					$result = is_shop();
					break;
				case 'woo_cart':
					$result = is_cart();
					break;
				case 'woo_checkout':
					$result = is_checkout();
					break;
				case 'woo_checkout_pay':
					$result = is_checkout_pay_page();
					break;
				case 'woo_cat':
					$result = is_product_category();
					break;
				case 'woo_tag':
					$result = is_product_tag();
					break;
			}
		}

		return $this->check_by_operator( $operator, $result, true );
	}

	/**
	 * An URL of the current page where a user who views your website is located
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	private function location_page( $operator, $value ) {
		$url = $this->getCurrentUrl();

		return $url ? $this->check_by_operator( $operator, trim( $url, '/' ), trim( $value, '/' ) ) : false;
	}

	/**
	 * A referrer URL which has brought a user to the current page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	private function location_referrer( $operator, $value ) {
		$url = $this->getRefererUrl();

		return $url ? $this->check_by_operator( $operator, trim( $url, '/' ), trim( $value, '/' ) ) : false;
	}

	/**
	 * A post type of the current page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 */
	private function location_post_type( $operator, $value ) {
		if ( is_singular() ) {
			return $this->check_by_operator( $operator, $value, get_post_type() );
		}

		return false;
	}

	/**
	 * A taxonomy page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 * @since 2.2.8 The bug is fixed, the condition was not checked
	 *              for tachonomies, only posts.
	 */
	private function location_taxonomy( $operator, $value ) {
		$term_id = null;

		if ( is_tax() || is_tag() || is_category() ) {
			$term_id = get_queried_object()->term_id;

			if ( $term_id ) {
				return $this->check_by_operator( $operator, intval( $value ), $term_id );
			}
		}

		return false;
	}

	/**
	 * A taxonomy of the current page
	 *
	 * @param $operator
	 * @param $value
	 *
	 * @return boolean
	 * @since 2.4.0
	 */
	private function page_taxonomy( $operator, $value ) {
		$term_id = null;

		if ( is_singular() ) {
			$post_cat = get_the_category( get_the_ID() );
			if ( is_array( $post_cat ) ) {
				foreach ( $post_cat as $item ) {
					$term_id[] = $item->term_id;
				}
			}
		}

		if ( $term_id ) {
			return $this->check_by_operator( $operator, intval( $value ), $term_id );
		}

		return false;
	}
}
