<?php
/**
 * Snippets Taxonomy
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_SnippetsTaxonomy
 *
 * Handles registration of the snippets taxonomy.
 */
class WINP_SnippetsTaxonomy {

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	private $name = WINP_SNIPPETS_TAXONOMY;

	/**
	 * Post type(s) this taxonomy applies to.
	 *
	 * @var string
	 */
	private $post_types = WINP_SNIPPETS_POST_TYPE;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Register the taxonomy.
	 *
	 * @return void
	 */
	public function register() {
		$labels = [
			'name'                       => __( 'Tags', 'insert-php' ),
			'singular_name'              => __( 'Tag', 'insert-php' ),
			'search_items'               => __( 'Search Tags', 'insert-php' ),
			'popular_items'              => __( 'Popular Tags', 'insert-php' ),
			'all_items'                  => __( 'All Tags', 'insert-php' ),
			'parent_item'                => __( 'Parent Tag', 'insert-php' ),
			'parent_item_colon'          => __( 'Parent Tag:', 'insert-php' ),
			'edit_item'                  => __( 'Edit Tag', 'insert-php' ),
			'update_item'                => __( 'Update Tag', 'insert-php' ),
			'add_new_item'               => __( 'Add New Tag', 'insert-php' ),
			'new_item_name'              => __( 'New Tag Name', 'insert-php' ),
			'separate_items_with_commas' => __( 'Separate Tags with commas', 'insert-php' ),
			'add_or_remove_items'        => __( 'Add or remove Tags', 'insert-php' ),
			'choose_from_most_used'      => __( 'Choose from the most used Tags', 'insert-php' ),
			'not_found'                  => __( 'No Tags found.', 'insert-php' ),
			'menu_name'                  => __( 'Tags', 'insert-php' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'show_in_rest'       => false,
			'hierarchical'       => false,
			'publicly_queryable' => false,
			'exclude_from_search' => true,
			'query_var'          => true,
			'rewrite'            => false,
			'capabilities'       => [
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			],
		];

		register_taxonomy( $this->name, $this->post_types, $args );
	}
}
