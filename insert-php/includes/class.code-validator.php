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
	 * Opening tag a universal snippet may start with to be evaluated from inside it.
	 */
	private const LEADING_OPEN_TAG = '/^<\?php\s/i';

	/**
	 * Build the code eval() runs for a universal snippet.
	 *
	 * Code that starts with an opening tag is evaluated from inside that tag rather
	 * than after a closing one, so a leading strict_types declaration stays the
	 * first statement. Output and line numbers are the same either way.
	 *
	 * @param string $snippet_code Snippet code.
	 * @return string
	 */
	public static function get_universal_eval_code( string $snippet_code ): string {
		if ( preg_match( self::LEADING_OPEN_TAG, $snippet_code ) ) {
			return substr( $snippet_code, 5 ) . '<?php ';
		}

		return '?>' . $snippet_code . '<?php ';
	}

	/**
	 * Validate executable snippet code by evaluating it once.
	 *
	 * Runs the preflight checks and then evaluates the code with output
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

		$preflight_error = self::get_preflight_error( $snippet_code, $snippet_type );
		if ( null !== $preflight_error ) {
			return [
				'valid'   => false,
				'message' => $preflight_error->getMessage(),
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
				? eval( self::get_universal_eval_code( $snippet_code ) )
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
	 * Check executable snippet code for syntax errors without running it.
	 *
	 * The tokenizer parses the code in TOKEN_PARSE mode and never executes it.
	 * Errors PHP raises only when the code runs, such as a call to an undefined
	 * function, are left to the check that runs when the snippet is activated.
	 * Without the tokenizer extension nothing is checked and the result says so.
	 *
	 * @param string $snippet_code Snippet code, without the opening tag for PHP snippets.
	 * @param string $snippet_type Snippet type.
	 * @return array{valid:bool,checked:bool,message:string} Check result.
	 */
	public static function check_syntax( string $snippet_code, $snippet_type ): array {
		if ( ! self::can_tokenize() ) {
			return [
				'valid'   => true,
				'checked' => false,
				'message' => '',
			];
		}

		try {
			token_get_all( self::get_source( $snippet_code, $snippet_type ), TOKEN_PARSE );
		} catch ( CompileError $e ) {
			return [
				'valid'   => false,
				'checked' => true,
				// translators: %1$d is the line number, %2$s is the error message.
				'message' => sprintf( __( 'Syntax error on line %1$d: %2$s', 'insert-php' ), $e->getLine(), $e->getMessage() ),
			];
		}

		$strict_types_line = self::find_misplaced_strict_types( $snippet_code, $snippet_type );
		if ( null !== $strict_types_line ) {
			return [
				'valid'   => false,
				'checked' => true,
				// translators: %1$d is the line number, %2$s is the error message.
				'message' => sprintf( __( 'Syntax error on line %1$d: %2$s', 'insert-php' ), $strict_types_line, self::get_strict_types_message() ),
			];
		}

		return [
			'valid'   => true,
			'checked' => true,
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
		$redeclaration = self::scan_redeclarations( (string) $snippet_code, $snippet_type, false );
		if ( null === $redeclaration ) {
			return null;
		}

		return [
			'name' => $redeclaration['name'],
			'line' => $redeclaration['line'],
		];
	}

	/**
	 * Find a top-level function, class, interface, trait or enum that would be redeclared by eval().
	 *
	 * PHP terminates the request when eval() declares a name that is already in
	 * use, so this conflict has to be detected before evaluating the snippet.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return array{kind:string,name:string,line:int}|null Conflict details, or null when none is found.
	 */
	public static function find_redeclaration( string $snippet_code, $snippet_type ): ?array {
		return self::scan_redeclarations( $snippet_code, $snippet_type, true );
	}

	/**
	 * Describe a redeclaration found by find_redeclaration().
	 *
	 * @param array{kind:string,name:string,line:int} $redeclaration Conflict details.
	 * @return string
	 */
	public static function get_redeclaration_message( array $redeclaration ): string {
		switch ( $redeclaration['kind'] ) {
			case 'class':
				// translators: %s is the fully qualified class name.
				return sprintf( __( 'Cannot declare class %s, because the name is already in use. Rename the class or guard its declaration with class_exists().', 'insert-php' ), $redeclaration['name'] );
			case 'interface':
				// translators: %s is the fully qualified interface name.
				return sprintf( __( 'Cannot declare interface %s, because the name is already in use. Rename the interface or guard its declaration with interface_exists().', 'insert-php' ), $redeclaration['name'] );
			case 'trait':
				// translators: %s is the fully qualified trait name.
				return sprintf( __( 'Cannot declare trait %s, because the name is already in use. Rename the trait or guard its declaration with trait_exists().', 'insert-php' ), $redeclaration['name'] );
			case 'enum':
				// translators: %s is the fully qualified enum name.
				return sprintf( __( 'Cannot declare enum %s, because the name is already in use. Rename the enum or guard its declaration with enum_exists().', 'insert-php' ), $redeclaration['name'] );
			default:
				// translators: %s is the fully qualified function name.
				return sprintf( __( 'Cannot redeclare function %s(). Rename the function or guard its declaration with function_exists().', 'insert-php' ), $redeclaration['name'] );
		}
	}

	/**
	 * Build the error for a top-level declaration the snippet would redeclare.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return ErrorException|null Error carrying the snippet line, or null when none is found.
	 */
	public static function get_redeclaration_error( string $snippet_code, $snippet_type ): ?ErrorException {
		$redeclaration = self::find_redeclaration( $snippet_code, $snippet_type );
		if ( null === $redeclaration ) {
			return null;
		}

		return self::build_preflight_exception( $redeclaration['line'], self::get_redeclaration_message( $redeclaration ) );
	}

	/**
	 * Build the error for any check that has to run before eval().
	 *
	 * Both a misplaced strict_types declaration and a redeclaration end the
	 * request inside eval(), where they cannot be caught.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return ErrorException|null Error carrying the snippet line, or null when the code can be evaluated.
	 */
	public static function get_preflight_error( string $snippet_code, $snippet_type ): ?ErrorException {
		$preflight_error = self::find_preflight_error( $snippet_code, $snippet_type );
		if ( null === $preflight_error ) {
			return null;
		}

		return self::build_preflight_exception( $preflight_error['line'], $preflight_error['message'] );
	}

	/**
	 * Build the error a preflight check reports at a snippet line.
	 *
	 * @param int    $line    Snippet line.
	 * @param string $message Error message.
	 * @return ErrorException
	 */
	private static function build_preflight_exception( int $line, string $message ): ErrorException {
		return new ErrorException(
			// translators: %1$d is the line number, %2$s is the error message.
			sprintf( __( 'Line %1$d: %2$s', 'insert-php' ), $line, $message ),
			0,
			E_COMPILE_ERROR,
			__FILE__,
			$line
		);
	}

	/**
	 * Find the first problem get_preflight_error() reports.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return array{line:int,message:string}|null Snippet line and message, or null when the code can be evaluated.
	 */
	public static function find_preflight_error( string $snippet_code, $snippet_type ): ?array {
		$strict_types_line = self::find_misplaced_strict_types( $snippet_code, $snippet_type );
		if ( null !== $strict_types_line ) {
			return [
				'line'    => $strict_types_line,
				'message' => self::get_strict_types_message(),
			];
		}

		$redeclaration = self::find_redeclaration( $snippet_code, $snippet_type );
		if ( null === $redeclaration ) {
			return null;
		}

		return [
			'line'    => $redeclaration['line'],
			'message' => self::get_redeclaration_message( $redeclaration ),
		];
	}

	/**
	 * Find a strict_types declaration that eval() would reject.
	 *
	 * PHP only accepts strict_types as a statement of its own, before any statement
	 * other than another declare(). A universal snippet may only start with the
	 * opening tag get_universal_eval_code() evaluates from inside of.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return int|null Line of the declaration, or null when none is misplaced.
	 */
	public static function find_misplaced_strict_types( string $snippet_code, $snippet_type ): ?int {
		if ( ! self::can_tokenize() ) {
			return null;
		}

		$tokens          = token_get_all( self::get_source( $snippet_code, $snippet_type ) );
		$first_statement = self::find_first_statement( $tokens );

		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) || T_DECLARE !== $token[0] || self::is_name_reference( $tokens, $index ) ) {
				continue;
			}

			$close = self::find_closing_parenthesis( $tokens, $index );
			if ( null === $close || ! self::declares_strict_types( $tokens, $index, $close ) ) {
				continue;
			}

			if ( $index > $first_statement || null === self::find_statement_end( $tokens, $close ) ) {
				return (int) $token[2];
			}
		}

		return null;
	}

	/**
	 * Describe a strict_types declaration found by find_misplaced_strict_types().
	 *
	 * @return string
	 */
	private static function get_strict_types_message(): string {
		return __( 'The strict_types declaration must be the very first statement in the snippet. Move declare(strict_types=1); to the top, directly after the opening PHP tag, and do not wrap code in a declare block.', 'insert-php' );
	}

	/**
	 * Find the first top-level declaration whose name is already in use.
	 *
	 * Top-level functions are bound when eval() compiles the code, so a conflict
	 * always ends the request. Classes, interfaces, traits and enums are declared
	 * when execution reaches them, so they are not reported after a return, exit,
	 * throw or goto outside any function or class body, which may guard them.
	 * Declarations inside braces are conditional and are skipped, even when the
	 * condition is always true. Class-like declarations inside alternative-syntax
	 * blocks, as in if ( ... ): ... endif;, are skipped too, while functions there
	 * are still reported. Nothing is reported without the tokenizer extension.
	 *
	 * @param string $snippet_code    Snippet code.
	 * @param string $snippet_type    Snippet type.
	 * @param bool   $include_classes Whether class-like declarations are checked.
	 * @return array{kind:string,name:string,line:int}|null Conflict details, or null when none is found.
	 */
	private static function scan_redeclarations( string $snippet_code, $snippet_type, bool $include_classes ): ?array {
		if ( ! self::can_tokenize() ) {
			return null;
		}

		$tokens            = token_get_all( self::get_source( $snippet_code, $snippet_type ) );
		$declaration_kinds = self::get_declaration_kinds();
		$blocks            = []; // One entry per open brace, true when it opens a function or class body.
		$opens_body        = false;
		$alternative_depth = 0;
		$may_stop          = false;
		$namespace         = '';
		$namespace_depth   = 0;
		$reading_namespace = false;
		$namespace_name    = '';
		$declared          = [];

		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) ) {
				if ( $reading_namespace ) {
					$reading_namespace = false;

					if ( ';' === $token ) {
						$namespace       = trim( $namespace_name, '\\' );
						$namespace_depth = 0;
						continue;
					}

					if ( '{' !== $token ) {
						$namespace_name = '';
						continue;
					}

					$blocks[]        = false;
					$namespace       = trim( $namespace_name, '\\' );
					$namespace_depth = count( $blocks );
					continue;
				}

				if ( '{' === $token ) {
					$blocks[]   = $opens_body;
					$opens_body = false;
				} elseif ( '}' === $token ) {
					array_pop( $blocks );
					if ( $namespace_depth > count( $blocks ) ) {
						$namespace       = '';
						$namespace_depth = 0;
					}
				} elseif ( ';' === $token ) {
					// Abstract and interface methods have no body.
					$opens_body = false;
				}
				continue;
			}

			$token_id = $token[0];

			if ( in_array( $token_id, [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) {
				$blocks[] = false;
				continue;
			}

			if ( T_NAMESPACE === $token_id ) {
				$reading_namespace = true;
				$namespace_name    = '';
				continue;
			}

			if ( $reading_namespace ) {
				if ( self::is_namespace_name_token( $token_id ) ) {
					$namespace_name .= $token[1];
				} elseif ( ! self::is_insignificant( $token ) ) {
					$reading_namespace = false;
					$namespace_name    = '';
				}
				continue;
			}

			if ( in_array( $token_id, [ T_RETURN, T_EXIT, T_THROW, T_GOTO ], true ) ) {
				$may_stop = $may_stop || ! in_array( true, $blocks, true );
				continue;
			}

			if ( in_array( $token_id, [ T_IF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH ], true ) ) {
				if ( self::opens_alternative_block( $tokens, $index ) ) {
					++$alternative_depth;
				}
				continue;
			}

			if ( in_array( $token_id, [ T_ENDIF, T_ENDWHILE, T_ENDFOR, T_ENDFOREACH, T_ENDSWITCH ], true ) ) {
				$alternative_depth = max( 0, $alternative_depth - 1 );
				continue;
			}

			if ( ! isset( $declaration_kinds[ $token_id ] ) || self::is_name_reference( $tokens, $index ) ) {
				continue;
			}

			// Named and anonymous functions and classes open their body at the next brace.
			$opens_body  = true;
			$kind        = $declaration_kinds[ $token_id ];
			$is_function = 'function' === $kind;

			if ( count( $blocks ) !== $namespace_depth || ( ! $is_function && ( ! $include_classes || $may_stop || $alternative_depth > 0 ) ) ) {
				continue;
			}

			$name = self::get_declared_name( $tokens, $index );
			if ( null === $name ) {
				continue;
			}

			$qualified_name = $namespace ? $namespace . '\\' . $name : $name;
			$symbol_key     = ( $is_function ? 'function:' : 'class:' ) . strtolower( $qualified_name );
			$in_use         = $is_function ? function_exists( $qualified_name ) : self::is_class_name_in_use( $qualified_name );

			if ( isset( $declared[ $symbol_key ] ) || $in_use ) {
				return [
					'kind' => $kind,
					'name' => $qualified_name,
					'line' => (int) $token[2],
				];
			}

			$declared[ $symbol_key ] = true;
		}

		return null;
	}

	/**
	 * Check whether the tokenizer extension is available.
	 *
	 * @return bool
	 */
	private static function can_tokenize(): bool {
		return function_exists( 'token_get_all' );
	}

	/**
	 * Build the source the tokenizer reads: PHP snippets are stored without their opening tag.
	 *
	 * @param string $snippet_code Snippet code.
	 * @param string $snippet_type Snippet type.
	 * @return string
	 */
	private static function get_source( string $snippet_code, $snippet_type ): string {
		return WINP_SNIPPET_TYPE_UNIVERSAL === $snippet_type ? $snippet_code : '<?php ' . $snippet_code;
	}

	/**
	 * Map the declaration tokens to the kind of name they declare.
	 *
	 * @return array<int, string>
	 */
	private static function get_declaration_kinds(): array {
		$kinds = [
			T_FUNCTION  => 'function',
			T_CLASS     => 'class',
			T_INTERFACE => 'interface',
			T_TRAIT     => 'trait',
		];

		if ( defined( 'T_ENUM' ) ) {
			$kinds[ constant( 'T_ENUM' ) ] = 'enum';
		}

		return $kinds;
	}

	/**
	 * Check whether a class, interface, trait or enum with this name is already loaded.
	 *
	 * Autoloading is skipped: eval() only fails on names that are already declared.
	 *
	 * @param string $name Fully qualified name.
	 * @return bool
	 */
	private static function is_class_name_in_use( string $name ): bool {
		return class_exists( $name, false ) || interface_exists( $name, false ) || trait_exists( $name, false );
	}

	/**
	 * Check whether a token is whitespace or a comment.
	 *
	 * @param array<int, int|string>|string $token Token.
	 * @return bool
	 */
	private static function is_insignificant( $token ): bool {
		return is_array( $token ) && in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true );
	}

	/**
	 * Check whether a declaration keyword only refers to a name, as in Example::class or use function example;.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $index  Keyword token index.
	 * @return bool
	 */
	private static function is_name_reference( array $tokens, int $index ): bool {
		$preceding = [ T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_USE ];

		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
			$preceding[] = constant( 'T_NULLSAFE_OBJECT_OPERATOR' );
		}

		for ( $previous = $index - 1; $previous >= 0; --$previous ) {
			if ( ! self::is_insignificant( $tokens[ $previous ] ) ) {
				return is_array( $tokens[ $previous ] ) && in_array( $tokens[ $previous ][0], $preceding, true );
			}
		}

		return false;
	}

	/**
	 * Check whether a control structure uses the alternative syntax, as in if ( ... ): ... endif;.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $index  Control structure token index.
	 * @return bool
	 */
	private static function opens_alternative_block( array $tokens, int $index ): bool {
		$close = self::find_closing_parenthesis( $tokens, $index );

		return null !== $close && self::is_followed_by( $tokens, $close, [ ':' ] );
	}

	/**
	 * Find the index of the statement that starts after the opening tag and any declare() statements.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @return int Token index, or the token count when there is no such statement.
	 */
	private static function find_first_statement( array $tokens ): int {
		$token_count = count( $tokens );

		for ( $index = 0; $index < $token_count; ++$index ) {
			if ( self::precedes_statements( $tokens, $index ) ) {
				continue;
			}

			if ( ! is_array( $tokens[ $index ] ) || T_DECLARE !== $tokens[ $index ][0] ) {
				return $index;
			}

			$close = self::find_closing_parenthesis( $tokens, $index );
			$end   = null === $close ? null : self::find_statement_end( $tokens, $close );

			if ( null === $end ) {
				return $index;
			}

			$index = $end;
		}

		return $token_count;
	}

	/**
	 * Check whether a token emits nothing and starts no statement.
	 *
	 * Whitespace, comments, the leading opening tag and a tag that reopens PHP
	 * directly after a closing tag qualify.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $index  Token index.
	 * @return bool
	 */
	private static function precedes_statements( array $tokens, int $index ): bool {
		$token = $tokens[ $index ];

		if ( self::is_insignificant( $token ) ) {
			return true;
		}

		if ( 0 === $index ) {
			return self::is_leading_open_tag( $token );
		}

		return is_array( $token ) && T_OPEN_TAG === $token[0] && self::is_close_tag( $tokens[ $index - 1 ] );
	}

	/**
	 * Find the token that ends a declare() statement: a semicolon, a closing tag or the end of the code.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $close  Closing parenthesis index of the declare().
	 * @return int|null Terminator index, the token count at the end of the code, or null when a body follows.
	 */
	private static function find_statement_end( array $tokens, int $close ): ?int {
		$next = self::find_next_significant( $tokens, $close );
		if ( null === $next ) {
			return count( $tokens );
		}

		return ';' === $tokens[ $next ] || self::is_close_tag( $tokens[ $next ] ) ? $next : null;
	}

	/**
	 * Check whether a token is a closing tag.
	 *
	 * @param array<int, int|string>|string $token Token.
	 * @return bool
	 */
	private static function is_close_tag( $token ): bool {
		return is_array( $token ) && T_CLOSE_TAG === $token[0];
	}

	/**
	 * Check whether a token is the opening tag get_universal_eval_code() evaluates from inside of.
	 *
	 * @param array<int, int|string>|string $token Token.
	 * @return bool
	 */
	private static function is_leading_open_tag( $token ): bool {
		return is_array( $token ) && T_OPEN_TAG === $token[0] && 1 === preg_match( self::LEADING_OPEN_TAG, (string) $token[1] );
	}

	/**
	 * Check whether a declare() between two token indexes sets strict_types.
	 *
	 * Only a directive name is followed by =, unlike a parameter type or named
	 * argument in a method called declare.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $start  declare token index.
	 * @param int                                       $end    Closing parenthesis index.
	 * @return bool
	 */
	private static function declares_strict_types( array $tokens, int $start, int $end ): bool {
		for ( $index = $start + 1; $index < $end; ++$index ) {
			if ( is_array( $tokens[ $index ] ) && T_STRING === $tokens[ $index ][0] && 'strict_types' === strtolower( (string) $tokens[ $index ][1] ) && self::is_followed_by( $tokens, $index, [ '=' ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the parenthesis that closes the group following a keyword, as in if ( ... ) or declare( ... ).
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $index  Keyword token index.
	 * @return int|null Closing parenthesis index, or null when no complete group follows the keyword.
	 */
	private static function find_closing_parenthesis( array $tokens, int $index ): ?int {
		$token_count = count( $tokens );
		$depth       = 0;

		for ( $current = $index + 1; $current < $token_count; ++$current ) {
			$token = $tokens[ $current ];

			if ( self::is_insignificant( $token ) ) {
				continue;
			}

			if ( '(' === $token ) {
				++$depth;
				continue;
			}

			if ( 0 === $depth ) {
				return null;
			}

			if ( ')' === $token && 0 === --$depth ) {
				return $current;
			}
		}

		return null;
	}

	/**
	 * Find the next token that is not whitespace or a comment.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $index  Token index to search after.
	 * @return int|null Token index, or null at the end of the code.
	 */
	private static function find_next_significant( array $tokens, int $index ): ?int {
		$token_count = count( $tokens );

		for ( $next = $index + 1; $next < $token_count; ++$next ) {
			if ( ! self::is_insignificant( $tokens[ $next ] ) ) {
				return $next;
			}
		}

		return null;
	}

	/**
	 * Check whether the next significant token is one of the given characters.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens     Token stream.
	 * @param int                                       $index      Token index to search after.
	 * @param array<int, string>                        $characters Single-character tokens to match.
	 * @return bool
	 */
	private static function is_followed_by( array $tokens, int $index, array $characters ): bool {
		$next = self::find_next_significant( $tokens, $index );

		return null !== $next && in_array( $tokens[ $next ], $characters, true );
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
	 * Get the name following a declaration token, ignoring whitespace and references.
	 *
	 * @param array<int, array<int, int|string>|string> $tokens Token stream.
	 * @param int                                       $declaration_index Declaration token index.
	 * @return string|null Null for closures, anonymous classes and other unnamed uses.
	 */
	private static function get_declared_name( $tokens, $declaration_index ) {
		$token_count = count( $tokens );

		for ( $index = $declaration_index + 1; $index < $token_count; ++$index ) {
			$token = $tokens[ $index ];

			if ( is_array( $token ) ) {
				if ( self::is_insignificant( $token ) || '&' === $token[1] ) {
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
