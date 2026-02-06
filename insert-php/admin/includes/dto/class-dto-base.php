<?php
/**
 * Base DTO class
 *
 * Base Data Transfer Object with common factory methods.
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
 * Abstract base class for Data Transfer Objects.
 */
abstract class WINP_DTO_Base {

	/**
	 * Create instance from associative array.
	 *
	 * Must be implemented by child classes.
	 *
	 * @param array<string, mixed> $data Associative array of data.
	 *
	 * @return static
	 * @throws Exception If required fields are missing.
	 */
	abstract public static function from_array( array $data );

	/**
	 * Create instance from JSON object.
	 *
	 * @param mixed $json JSON object from json_decode().
	 *
	 * @return static
	 * @throws Exception If required fields are missing or invalid type.
	 */
	public static function from_json( $json ) {
		if ( ! is_object( $json ) ) {
			$class = static::class;
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error message for developers only.
			throw new Exception( "{$class}::from_json() requires an object, " . gettype( $json ) . ' given' );
		}

		return static::from_array( (array) $json );
	}

	/**
	 * Create array of instances from JSON array.
	 *
	 * @param array<mixed> $json_array Array of JSON objects or arrays.
	 *
	 * @return static[]
	 * @throws Exception If data is invalid.
	 */
	public static function array_from_json( array $json_array ) {
		$result = [];

		foreach ( $json_array as $item ) {
			if ( is_object( $item ) ) {
				$result[] = static::from_json( $item );
			} elseif ( is_array( $item ) ) {
				$result[] = static::from_array( $item );
			} else {
				$class = static::class;
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error message for developers only.
				throw new Exception( "Invalid item type in {$class} array: " . gettype( $item ) );
			}
		}

		return $result;
	}

	/**
	 * Validate required field exists in data array.
	 *
	 * @param array<string, mixed> $data       Data array to check.
	 * @param string               $field_name Required field name.
	 * @param string               $class_name Class name for error message.
	 *
	 * @return void
	 * @throws Exception If required field is missing.
	 */
	protected static function require_field( array $data, $field_name, $class_name ) {
		if ( ! isset( $data[ $field_name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Error message for developers only.
			throw new Exception( "Required field \"{$field_name}\" is missing in {$class_name} data" );
		}
	}
}
