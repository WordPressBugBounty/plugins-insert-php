<?php
/**
 * Preflight checks for executable snippets.
 *
 * @package WP_Plugin_Insert_PHP
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WINP_Code_Validator class.
 */
class WINP_Code_Validator {

	/**
	 * Find a top-level function declaration that would be redeclared by eval().
	 *
	 * PHP terminates the request when eval() declares an already-loaded function,
	 * so this conflict has to be detected before evaluating the snippet.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return array{name:string,line:int}|null Conflict details, or null when none is found.
	 */
	public static function find_function_redeclaration( $snippet_code, $snippet_type ) {
		$source = WINP_SNIPPET_TYPE_UNIVERSAL === $snippet_type
			? $snippet_code
			: '<?php ' . $snippet_code;
		$tokens = token_get_all( $source );

		$brace_depth       = 0;
		$namespace         = '';
		$namespace_depth   = 0;
		$reading_namespace = false;
		$namespace_name    = '';
		$declared          = [];

		foreach ( $tokens as $index => $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) {
					++$brace_depth;
					continue;
				}

				if ( T_NAMESPACE === $token[0] ) {
					$reading_namespace = true;
					$namespace_name    = '';
					continue;
				}

				if ( $reading_namespace ) {
					if ( self::is_namespace_name_token( $token[0] ) ) {
						$namespace_name .= $token[1];
					} elseif ( ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
						$reading_namespace = false;
						$namespace_name    = '';
					}
					continue;
				}

				if ( T_FUNCTION !== $token[0] || $brace_depth !== $namespace_depth ) {
					continue;
				}

				$function_name = self::get_function_name( $tokens, $index );
				if ( null === $function_name ) {
					continue;
				}

				$qualified_name  = $namespace ? $namespace . '\\' . $function_name : $function_name;
				$normalized_name = strtolower( $qualified_name );

				if ( isset( $declared[ $normalized_name ] ) || function_exists( $qualified_name ) ) {
					return [
						'name' => $qualified_name,
						'line' => (int) $token[2],
					];
				}

				$declared[ $normalized_name ] = true;
				continue;
			}

			if ( $reading_namespace ) {
				if ( ';' === $token ) {
					$namespace         = trim( $namespace_name, '\\' );
					$namespace_depth   = 0;
					$reading_namespace = false;
				} elseif ( '{' === $token ) {
					++$brace_depth;
					$namespace         = trim( $namespace_name, '\\' );
					$namespace_depth   = $brace_depth;
					$reading_namespace = false;
				} else {
					$reading_namespace = false;
					$namespace_name    = '';
				}
				continue;
			}

			if ( '{' === $token ) {
				++$brace_depth;
			} elseif ( '}' === $token ) {
				$brace_depth = max( 0, $brace_depth - 1 );
				if ( $namespace_depth > $brace_depth ) {
					$namespace       = '';
					$namespace_depth = 0;
				}
			}
		}

		return null;
	}

	/**
	 * Check whether a token can be part of a namespace name.
	 *
	 * @param int $token_id Token identifier.
	 * @return bool
	 */
	private static function is_namespace_name_token( $token_id ) {
		$name_tokens = [ T_STRING, T_NS_SEPARATOR ];

		foreach ( [ 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED' ] as $constant_name ) {
			if ( defined( $constant_name ) ) {
				$name_tokens[] = constant( $constant_name );
			}
		}

		return in_array( $token_id, $name_tokens, true );
	}

	/**
	 * Get the name following a function token, ignoring whitespace and references.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $function_index Function token index.
	 * @return string|null
	 */
	private static function get_function_name( $tokens, $function_index ) {
		$token_count = count( $tokens );

		for ( $index = $function_index + 1; $index < $token_count; ++$index ) {
			$token = $tokens[ $index ];

			if ( is_array( $token ) ) {
				if ( in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) || '&' === $token[1] ) {
					continue;
				}

				return T_STRING === $token[0] ? (string) $token[1] : null;
			}

			if ( '&' === $token ) {
				continue;
			}

			return null;
		}

		return null;
	}
}
