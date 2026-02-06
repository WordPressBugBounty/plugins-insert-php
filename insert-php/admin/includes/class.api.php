<?php

/**
 * Woody API class
 *
 * @version       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WINP_Api extends WINP_Request {

	const WINP_API_SNIPPET = 'snippet';

	/**
	 * Set page parameters
	 *
	 * @param $json
	 *
	 * @return bool
	 */
	private function set_page_params( $json ) {
		if ( ! $this->check_response( $json ) ) {
			return false;
		}

		if ( empty( $json['headers'] ) ) {
			return false;
		}

		global $winp_api_total_items;

		$winp_api_total_items = isset( $json['headers']['x-pagination-total-count'] ) ? $json['headers']['x-pagination-total-count'] : 0;

		return true;
	}

	/**
	 * Get total items for last query
	 *
	 * @return int
	 */
	public function get_total_items() {
		global $winp_api_total_items;

		return $winp_api_total_items;
	}

	/**
	 * Get all snippets
	 *
	 * @param boolean $common   - если true, то выводить общие сниппеты без привязки к пользователю
	 * @param array   $parameters
	 *
	 * @return bool|mixed
	 */
	public function get_all_snippets( $common = false, $parameters = [] ) {
		if ( get_option( WINP_PLUGIN_NAMESPACE . '_logger_flag' ) !== 'yes' ) {
			update_option( WINP_PLUGIN_NAMESPACE . '_logger_flag', 'yes' );
		}

		$url  = $common ? 'common' : self::WINP_API_SNIPPET;
		$args = $parameters ? '&' . implode( '&', $parameters ) : '';
		$json = $this->get( $url . '?expand=type' . $args );

		$this->set_page_params( $json );

		return $this->map_objects( $json, 'WINP_DTO_Snippet' );
	}

	/**
	 * Get snippet
	 *
	 * @param integer $id
	 * @param boolean $common   - если true, то запрос на общий сниппет
	 *
	 * @return bool|mixed
	 */
	public function get_snippet( $id, $common = false ) {
		$url  = $common ? 'common' : self::WINP_API_SNIPPET;
		$json = $this->get( $url . '/view?id=' . $id . '&expand=type' );

		$snippet = $this->map_object( $json, 'WINP_DTO_Snippet' );
		if ( is_object( $snippet ) && property_exists( $snippet, 'execute_everywhere' ) ) {
			$snippet->execute_everywhere = $snippet->execute_everywhere ? 'evrywhere' : 'shortcode';
		}
		return $snippet;
	}

	/**
	 * Create snippet
	 *
	 * @param string  $title
	 * @param string  $content
	 * @param string  $description
	 * @param integer $type_id
	 *
	 * @return bool|mixed
	 */
	public function create_snippet( $title, $content, $description, $type_id ) {
		$args = [
			'body' => [
				'title'       => $title,
				'content'     => $content,
				'description' => $description,
				'type_id'     => $type_id,    // Тип снипета
			],
		];

		$json = $this->post( self::WINP_API_SNIPPET . '/create', $args );

		return $this->map_object( $json, 'WINP_DTO_Snippet' );
	}

	/**
	 * Update snippet
	 *
	 * @param integer $id
	 * @param string  $title
	 * @param string  $content
	 * @param string  $description
	 * @param integer $type_id
	 *
	 * @return bool|mixed
	 */
	public function update_snippet( $id, $title, $content, $description, $type_id ) {
		$args = [
			'body' => [
				'title'       => $title,
				'content'     => $content,
				'description' => $description,
				'type_id'     => $type_id,    // Тип снипета
			],
		];

		$json = $this->put( self::WINP_API_SNIPPET . '/update/?id=' . $id, $args );

		return $this->map_object( $json, 'WINP_DTO_Snippet' );
	}

	/**
	 * Delete snippet
	 *
	 * @param integer $id
	 *
	 * @return boolean
	 */
	public function delete_snippet( $id ) {
		$json = $this->post( self::WINP_API_SNIPPET . '/delete/?id=' . $id );

		if ( 200 == $json['response']['code'] || 204 == $json['response']['code'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Get all types
	 *
	 * @return array<object>
	 */
	public function get_all_types() {
		$types_data = [
			[
				'id'    => 1,
				'slug'  => 'php',
				'title' => 'PHP',
			],
			[
				'id'    => 2,
				'slug'  => 'css',
				'title' => 'CSS',
			],
			[
				'id'    => 3,
				'slug'  => 'js',
				'title' => 'JavaScript',
			],
			[
				'id'    => 4,
				'slug'  => 'html',
				'title' => 'HTML',
			],
			[
				'id'    => 5,
				'slug'  => 'text',
				'title' => 'Text',
			],
			[
				'id'    => 6,
				'slug'  => 'universal',
				'title' => 'Universal',
			],
			[
				'id'    => 7,
				'slug'  => 'advert',
				'title' => 'Advert',
			],
		];

		$types = [];
		foreach ( $types_data as $data ) {
			try {
				$types[] = WINP_DTO_Type::from_array( $data );
			} catch ( Exception $e ) {
				// Skip invalid types.
				continue;
			}
		}

		return $types;
	}

	/**
	 * Check if snippet changed
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	public function is_changed( $post_id ) {
		$data = get_post_meta( $post_id, 'wbcr_inp_snippet_check_data', true );
		if ( ! empty( $data ) && isset( $data['content'] ) ) {
			$post = get_post( $post_id );

			return $data['content'] != $post->post_content || WINP_Helper::getMetaOption( $post->ID, 'snippet_description' ) != $data['description'];
		} else {
			return true;
		}
	}

	/**
	 * Get tipy id by type title
	 *
	 * @param $type_title
	 *
	 * @return int
	 */
	private function get_type_id_by_type( $type_title ) {
		if ( $type_title ) {
			$types = $this->get_all_types();

			foreach ( $types as $type ) {
				if ( property_exists( $type, 'slug' ) && property_exists( $type, 'id' ) && $type_title === $type->slug ) {
					return $type->id;
				}
			}
		}

		return 0;
	}

	/**
	 * Snippet synchronization
	 *
	 * @param $id
	 * @param $name
	 *
	 * @return bool|string
	 */
	public function synchronization( $id, $name ) {
		$post = get_post( $id );

		if ( $post ) {
			$type_id = WINP_Helper::getMetaOption( $post->ID, 'snippet_api_type', 0 );

			if ( ! $type_id ) {
				$type    = WINP_Helper::get_snippet_type( $post->ID );
				$type_id = $this->get_type_id_by_type( $type );
			}

			if ( $type_id ) {
				$title        = ! empty( $name ) ? $name : $post->post_title;
				$description  = WINP_Helper::getMetaOption( $post->ID, 'snippet_description', '' );
				$snippet_code = WINP_Helper::get_snippet_code( $post );
				$snippet      = $this->create_snippet( $title, $snippet_code, $description, $type_id );

				if ( $snippet ) {
					$data = [
						'content'     => $snippet_code,
						'description' => $description,
					];
					WINP_Helper::updateMetaOption( $post->ID, 'snippet_check_data', $data );
					WINP_Helper::updateMetaOption( $post->ID, 'snippet_api_snippet', $snippet->id );
					WINP_Helper::updateMetaOption( $post->ID, 'snippet_api_type', $type_id );

					return true;
				}

				return __( 'Snippet synchronization error', 'insert-php' );
			}

			return __( 'Unknown snippet type', 'insert-php' );
		}

		return false;
	}

	/**
	 * Create snippet from library
	 *
	 * @param integer $snippet_id
	 * @param integer $post_id
	 * @param boolean $common
	 *
	 * @return bool|integer
	 */
	public function create_from_library( $snippet_id, $post_id, $common ) {
		if ( $snippet_id ) {
			$snippet = $this->get_snippet( $snippet_id, $common );
			if ( $snippet ) {
				if ( ! $post_id ) {
					$post    = [
						'post_title'   => $snippet->title,
						'post_content' => $snippet->content,
						'post_type'    => WINP_SNIPPETS_POST_TYPE,
						'post_status'  => 'publish',
					];
					$post_id = wp_insert_post( $post );
					WINP_Helper::updateMetaOption( $post_id, 'snippet_activate', 0 );
				} else {
					$post = [
						'ID'           => $post_id,
						'post_title'   => $snippet->title,
						'post_content' => $snippet->content,
					];
					wp_update_post( $post );
				}

				WINP_Helper::updateMetaOption( $post_id, 'snippet_api_snippet', $snippet_id );
				WINP_Helper::updateMetaOption( $post_id, 'snippet_type', $snippet->type->slug );
				WINP_Helper::updateMetaOption( $post_id, 'snippet_api_type', $snippet->type_id );
				WINP_Helper::updateMetaOption( $post_id, 'snippet_description', $snippet->description );
				WINP_Helper::updateMetaOption( $post_id, 'snippet_scope', $snippet->execute_everywhere );

				return $post_id;
			}
		}

		return false;
	}
}
