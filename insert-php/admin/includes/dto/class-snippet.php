<?php
/**
 * API Snippet DTO class
 *
 * Data Transfer Object for snippets from the Woody API.
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
 * WINP_DTO_Snippet class - represents a code snippet from the API.
 */
class WINP_DTO_Snippet extends WINP_DTO_Base {

	/**
	 * Snippet ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Snippet title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Snippet description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Snippet content.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * Snippets Library: video link.
	 *
	 * @var string|null
	 */
	public $video_link;

	/**
	 * Snippet scope.
	 *
	 * @var string|null
	 */
	public $execute_everywhere;

	/**
	 * Snippet status.
	 *
	 * @var bool
	 */
	public $status;

	/**
	 * Type ID.
	 *
	 * @var int
	 */
	public $type_id;

	/**
	 * Snippet premium marker.
	 *
	 * @var bool|null
	 */
	public $is_premium;

	/**
	 * Snippet updated time (timestamp).
	 *
	 * @var int
	 */
	public $updated_at;

	/**
	 * Snippet created time (timestamp).
	 *
	 * @var int
	 */
	public $created_at;

	/**
	 * Snippet type object.
	 *
	 * @var WINP_DTO_Type|null
	 */
	public $type;

	/**
	 * Snippet priority.
	 *
	 * @var int
	 */
	public $priority;

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
		self::require_field( $data, 'id', 'Snippet' );
		self::require_field( $data, 'title', 'Snippet' );

		$instance = new self();

		// Map scalar properties.
		$instance->id          = (int) $data['id'];
		$instance->title       = (string) $data['title'];
		$instance->description = isset( $data['description'] ) ? (string) $data['description'] : '';
		$instance->content     = isset( $data['content'] ) ? (string) $data['content'] : '';
		$instance->type_id     = isset( $data['type_id'] ) ? (int) $data['type_id'] : 0;
		$instance->status      = isset( $data['status'] ) ? (bool) $data['status'] : false;
		$instance->updated_at  = isset( $data['updated_at'] ) ? (int) $data['updated_at'] : 0;
		$instance->created_at  = isset( $data['created_at'] ) ? (int) $data['created_at'] : 0;
		$instance->priority    = isset( $data['priority'] ) ? (int) $data['priority'] : 0;

		// Nullable properties.
		$instance->video_link         = isset( $data['video_link'] ) ? (string) $data['video_link'] : null;
		$instance->execute_everywhere = isset( $data['execute_everywhere'] ) ? $data['execute_everywhere'] : null;
		$instance->is_premium         = isset( $data['is_premium'] ) ? (bool) $data['is_premium'] : null;

		// Nested type object.
		if ( isset( $data['type'] ) ) {
			if ( is_object( $data['type'] ) ) {
				$instance->type = WINP_DTO_Type::from_json( $data['type'] );
			} elseif ( is_array( $data['type'] ) ) {
				/**
				 * Type data array.
				 *
				 * @var array<string, mixed> $type_array
				 */
				$type_array     = $data['type'];
				$instance->type = WINP_DTO_Type::from_array( $type_array );
			}
		} else {
			$instance->type = null;
		}

		return $instance;
	}
}
