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
	 * Validate executable snippet code by evaluating it once.
	 *
	 * Runs the redeclaration preflight and then evaluates the code with output
	 * discarded, reporting syntax errors, warnings and uncaught errors. The code
	 * is executed in the current request, exactly as the snippet editor does.
	 *
	 * @param string $snippet_code Unslashed snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return array{valid:bool,message:string} Validation result.
	 */
	public static function validate_code( $snippet_code, $snippet_type ) {
		if ( empty( $snippet_code ) ) {
			return [
				'valid'   => true,
				'message' => '',
			];
		}

		$redeclaration = self::find_function_redeclaration( $snippet_code, $snippet_type );
		if ( null !== $redeclaration ) {
			return [
				'valid'   => false,
				// translators: %1$d is the line number, %2$s is the fully qualified function name.
				'message' => sprintf( __( 'Line %1$d: Cannot redeclare function %2$s(). Rename the function or guard its declaration with function_exists().', 'insert-php' ), $redeclaration['line'], $redeclaration['name'] ),
			];
		}

		$validation_errors = [];

		// Set custom error handler to catch warnings and notices.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			function ( $errno, $errstr, $errfile, $errline ) use ( &$validation_errors ) {
				// Extract line number from eval'd code if present.
				if ( strpos( $errfile, "eval()'d code" ) !== false ) {
					// translators: %1$d is the line number, %2$s is the error message.
					$validation_errors[] = sprintf( __( 'Line %1$d: %2$s', 'insert-php' ), $errline, $errstr );
				} else {
					$validation_errors[] = $errstr;
				}
				return true; // Don't execute PHP internal error handler.
			}
		);

		ob_start();

		try {
			$result = WINP_SNIPPET_TYPE_UNIVERSAL === $snippet_type
				? eval( '?> ' . $snippet_code . ' <?php ' )
				: eval( $snippet_code );
		} catch ( ParseError $e ) {
			ob_end_clean();
			restore_error_handler();

			return [
				'valid'   => false,
				// translators: %1$d is the line number, %2$s is the error message.
				'message' => sprintf( __( 'Syntax error on line %1$d: %2$s', 'insert-php' ), $e->getLine(), $e->getMessage() ),
			];
		} catch ( Throwable $e ) {
			ob_end_clean();
			restore_error_handler();

			// For fatal errors in eval'd code, report the actual line number.
			if ( strpos( $e->getFile(), "eval()'d code" ) !== false ) {
				return [
					'valid'   => false,
					// translators: %1$d is the line number, %2$s is the error message.
					'message' => sprintf( __( 'Error on line %1$d: %2$s', 'insert-php' ), $e->getLine(), $e->getMessage() ),
				];
			}

			return [
				'valid'   => false,
				// translators: %s is the error message.
				'message' => sprintf( __( 'Error: %s', 'insert-php' ), $e->getMessage() ),
			];
		}

		// Discard any output (echo/print statements are normal for snippets).
		ob_end_clean();
		restore_error_handler();

		if ( ! empty( $validation_errors ) ) {
			return [
				'valid'   => false,
				'message' => implode( '<br>', $validation_errors ),
			];
		}

		if ( false === $result ) {
			return [
				'valid'   => false,
				'message' => __( 'The code contains syntax errors. Please review and fix them before saving.', 'insert-php' ),
			];
		}

		return [
			'valid'   => true,
			'message' => '',
		];
	}

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
