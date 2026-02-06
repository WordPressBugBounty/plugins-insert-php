<?php
/**
 * API Type DTO class
 *
 * Data Transfer Object for snippet types from the Woody API.
 * Replaces the non-GPL-compatible JsonMapper implementation.
 *
 * @package    Woody_Code_Snippets
 * @copyright  Copyright (c) 2025, Themeisle
 * @license    GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_DTO_Type class - represents a snippet type/category from the API.
 */
class WINP_DTO_Type extends WINP_DTO_Base {

	/**
	 * Type ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Type title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Type slug.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Create instance from associative array.
	 *
	 * @param array<string, mixed> $data Associative array of data.
	 *
	 * @return self
	 * @throws Exception If required fields are missing.
	 */
	public static function from_array( array $data ) {
		// Validate required fields.
		self::require_field( $data, 'id', 'Type' );
		self::require_field( $data, 'title', 'Type' );
		self::require_field( $data, 'slug', 'Type' );

		$instance = new self();

		$instance->id    = (int) $data['id'];
		$instance->title = (string) $data['title'];
		$instance->slug  = (string) $data['slug'];

		return $instance;
	}
}
