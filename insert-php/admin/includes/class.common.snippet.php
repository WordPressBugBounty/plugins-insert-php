<?php
/**
 * Common functions for snippets
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for common snippet functions
 */
class WINP_Common_Snippet {

	/**
	 * Register hooks
	 * 
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'current_screen', [ $this, 'current_screen' ] );
		add_action( 'edit_form_before_permalink', [ $this, 'edit_form_before_permalink' ] );
		add_action( 'admin_notices', [ $this, 'create_uploads_directory' ] );
		add_action( 'before_delete_post', [ $this, 'before_delete_post' ] );
		add_action( 'save_post', [ $this, 'save_post' ] );
		add_action( 'save_post_' . WINP_SNIPPETS_POST_TYPE, [ $this, 'save_snippet' ], 10, 3 );
		add_action( 'auto-draft_to_publish', [ $this, 'publish_snippet' ] );

		add_filter( 'script_loader_src', [ $this, 'unload_scripts' ], 10, 2 );
	}

	/**
	 * Create the custom-css-js dir in uploads directory
	 *
	 * Show a message if the directory is not writable
	 *
	 * Create an empty index.php file inside
	 * 
	 * @return void
	 */
	public function create_uploads_directory() {
		$current_screen = get_current_screen();

		// Check if we are editing a custom-css-js post.
		if ( 'post' !== $current_screen->base || WINP_SNIPPETS_POST_TYPE !== $current_screen->post_type ) {
			return;
		}

		$dir = WINP_UPLOAD_DIR;

		// Create the dir if it doesn't exist.
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Show a message if it couldn't create the dir.
		if ( ! file_exists( $dir ) ) { ?>
			<div class="notice notice-error is-dismissible">
				<?php
				/* translators: %s: Directory name */
				?>
				<p>
				<?php
				printf(
					/* translators: %s: Directory path */
					esc_html__( 'The %s directory could not be created', 'insert-php' ),
					'<b>winp-css-js</b>'
				);
				?>
					</p>
				<p><?php _e( 'Please run the following commands in order to make the directory', 'insert-php' ); ?>:
					<br/><strong>mkdir <?php echo $dir; ?>; </strong><br/><strong>chmod 777 <?php echo $dir; ?>
						;</strong></p>
			</div>
			<?php
			return;
		}

		// Show a message if the dir is not writable.
		if ( ! wp_is_writable( $dir ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
				<?php
				printf(
					/* translators: %s: Directory path */
					esc_html__( 'The %s directory is not writable, therefore the CSS and JS files cannot be saved.', 'insert-php' ),
					'<b>' . esc_html( $dir ) . '</b>'
				);
				?>
				</p>
				<p><?php _e( 'Please run the following command to make the directory writable', 'insert-php' ); ?>:<br/><strong>chmod
						777 <?php echo $dir; ?> </strong></p>
			</div>
			<?php
			return;
		}

		// Write a blank index.php.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			$content = '<?php' . PHP_EOL . '// Silence is golden.';
			@file_put_contents( $dir . '/index.php', $content );
		}
	}

	/**
	 * Add quick buttons
	 * 
	 * @return void
	 */
	public function current_screen_edit() {
		$strings = [
			'php'       => __( 'PHP Snippet', 'insert-php' ),
			'css'       => __( 'CSS Snippet', 'insert-php' ),
			'js'        => __( 'JS Snippet', 'insert-php' ),
			'text'      => __( 'Text Snippet', 'insert-php' ),
			'html'      => __( 'HTML Snippet', 'insert-php' ),
			'advert'    => __( 'Advertisement', 'insert-php' ),
			'universal' => __( 'Universal', 'insert-php' ),
		];

		$this->render_snippet_dropdown( $strings, __( 'New Snippet: PHP', 'insert-php' ) );
	}

	/**
	 * Add quick buttons
	 * 
	 * @return void
	 */
	public function current_screen_post() {
		global $post;
		$post_id = null;
		
		if ( ! empty( $post ) && is_object( $post ) && isset( $post->ID ) ) {
			$post_id = $post->ID;
		} elseif ( ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$post_id = WINP_HTTP::get( 'post', null );
		}
		
		$type = WINP_Helper::get_snippet_type( $post_id );
		
		$strings = [
			'php'       => __( 'PHP Snippet', 'insert-php' ),
			'css'       => __( 'CSS Snippet', 'insert-php' ),
			'js'        => __( 'JS Snippet', 'insert-php' ),
			'text'      => __( 'Text Snippet', 'insert-php' ),
			'html'      => __( 'HTML Snippet', 'insert-php' ),
			'advert'    => __( 'Advertisement', 'insert-php' ),
			'universal' => __( 'Universal', 'insert-php' ),
		];

		$label_map = [
			'php'       => __( 'New Snippet: PHP', 'insert-php' ),
			'css'       => __( 'New Snippet: CSS', 'insert-php' ),
			'js'        => __( 'New Snippet: JS', 'insert-php' ),
			'text'      => __( 'New Snippet: Text', 'insert-php' ),
			'html'      => __( 'New Snippet: HTML', 'insert-php' ),
			'advert'    => __( 'New Snippet: Advertisement', 'insert-php' ),
			'universal' => __( 'New Snippet: Universal', 'insert-php' ),
		];

		$button_label = isset( $label_map[ $type ] ) ? $label_map[ $type ] : __( 'New Snippet: PHP', 'insert-php' );

		$this->render_snippet_dropdown( $strings, $button_label, ! empty( $post_id ) );
	}

	/**
	 * Render snippet type dropdown
	 * 
	 * @param array<string, string> $strings Snippet type labels (type => label).
	 * @param string                $button_label Button label text.
	 * @param bool                  $show_title Whether to show the edit title.
	 * 
	 * @return void
	 */
	private function render_snippet_dropdown( $strings, $button_label, $show_title = false ) {
		$type_display_labels = [
			'php'       => __( 'PHP', 'insert-php' ),
			'css'       => __( 'CSS', 'insert-php' ),
			'js'        => __( 'JS', 'insert-php' ),
			'text'      => __( 'Text', 'insert-php' ),
			'html'      => __( 'HTML', 'insert-php' ),
			'advert'    => __( 'Advertisement', 'insert-php' ),
			'universal' => __( 'Universal', 'insert-php' ),
		];
		
		// Get edit title if needed.
		$edit_title = '';
		if ( $show_title ) {
			global $post;
			$post_id = null;
			
			if ( ! empty( $post ) && is_object( $post ) && isset( $post->ID ) ) {
				$post_id = $post->ID;
			} elseif ( ! empty( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			} else {
				$post_id = WINP_HTTP::get( 'post', null );
			}
			
			if ( ! empty( $post_id ) ) {
				$type        = WINP_Helper::get_snippet_type( $post_id );
				$edit_labels = [
					'php'       => __( 'Edit PHP Snippet', 'insert-php' ),
					'css'       => __( 'Edit CSS Snippet', 'insert-php' ),
					'js'        => __( 'Edit JS Snippet', 'insert-php' ),
					'text'      => __( 'Edit Text Snippet', 'insert-php' ),
					'html'      => __( 'Edit HTML Snippet', 'insert-php' ),
					'advert'    => __( 'Edit Advertisement Snippet', 'insert-php' ),
					'universal' => __( 'Edit Universal Snippet', 'insert-php' ),
				];
				$edit_title  = isset( $edit_labels[ $type ] ) ? $edit_labels[ $type ] : '';
			}
		}
		?>
		<style>
			.winp-dropdown-button {
				position: relative;
				display: inline-flex;
				margin-left: 10px;
			}
			.winp-main-button {
				background: #6366f1;
				color: #fff;
				border: none;
				border-radius: 3px 0 0 3px;
				padding: 6px 12px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				gap: 6px;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
				text-decoration: none;
			}
			.winp-main-button:hover {
				background: #4f46e5;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
				color: #fff;
			}
			.winp-main-button .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
			}
			.winp-dropdown-toggle {
				background: #6366f1;
				color: #fff;
				border: none;
				border-left: 1px solid rgba(255,255,255,0.2);
				border-radius: 0 3px 3px 0;
				padding: 6px 8px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				display: inline-flex;
				align-items: center;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
			}
			.winp-dropdown-toggle:hover {
				background: #4f46e5;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.winp-dropdown-toggle.active {
				background: #4f46e5 !important;
				border-color: rgba(255,255,255,0.2) !important;
				color: #fff !important;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
			}
			.winp-dropdown-toggle .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				transition: transform 0.3s ease;
			}
			.winp-dropdown-toggle.active .dashicons-arrow-down-alt2 {
				transform: rotate(180deg);
			}
			.winp-dropdown-menu {
				display: block;
				position: absolute;
				top: 100%;
				right: 0;
				margin-top: 4px;
				background: #fff;
				border: 1px solid #ddd;
				border-radius: 4px;
				box-shadow: 0 4px 12px rgba(0,0,0,0.15);
				min-width: 180px;
				z-index: 99999;
				opacity: 0;
				visibility: hidden;
				transform: translateY(-8px);
				transition: all 0.2s ease;
				overflow: hidden;
			}
			.winp-dropdown-menu.show {
				opacity: 1;
				visibility: visible;
				transform: translateY(0);
			}
			.winp-dropdown-item {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 6px 12px;
				cursor: pointer;
				text-decoration: none;
				color: #2c3338;
				border-bottom: 1px solid #f0f0f1;
				font-size: 13px;
				position: relative;
			}
			.winp-dropdown-item:first-child {
				border-top-left-radius: 4px;
				border-top-right-radius: 4px;
			}
			.winp-dropdown-item:last-child {
				border-bottom: none;
				border-bottom-left-radius: 4px;
				border-bottom-right-radius: 4px;
			}
			.winp-dropdown-item:hover,
			.winp-dropdown-item:focus {
				background: #eef2ff;
				color: #6366f1;
				outline: none;
			}
			.winp-snippet-icon {
				width: 26px;
				height: 26px;
				border-radius: 3px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 10px;
				font-weight: 700;
				color: #fff;
				flex-shrink: 0;
				box-shadow: 0 1px 2px rgba(0,0,0,0.1);
			}
			.winp-snippet-icon.php { background: linear-gradient(135deg, #8892BF 0%, #7380b0 100%); }
			.winp-snippet-icon.css { background: linear-gradient(135deg, #4A90E2 0%, #357abd 100%); }
			.winp-snippet-icon.js { background: linear-gradient(135deg, #F7C948 0%, #e6b938 100%); color: #333; }
			.winp-snippet-icon.text { background: linear-gradient(135deg, #7E8C8D 0%, #6d7a7b 100%); }
			.winp-snippet-icon.html { background: linear-gradient(135deg, #E74C3C 0%, #d63c2d 100%); }
			.winp-snippet-icon.advert { background: linear-gradient(135deg, #27AE60 0%, #229d56 100%); }
			.winp-snippet-icon.universal { background: linear-gradient(135deg, #9B59B6 0%, #8a4aa5 100%); }
			
			/* Keyboard navigation highlight */
			.winp-dropdown-item.keyboard-focus {
				background: #eef2ff;
				color: #6366f1;
			}
		</style>
		<script type="text/javascript">
			/* <![CDATA[ */
			jQuery(window).ready(function ($) {
				$('#wpbody-content a.page-title-action').hide();
				
				<?php if ( $show_title && ! empty( $edit_title ) ) : ?>
				// Update the h1 with edit title
				var currentH1 = $('#wpbody-content h1').text();
				$('#wpbody-content h1').text('<?php echo esc_js( $edit_title ); ?>');
				<?php endif; ?>
				
				// Determine the initial selected type from the button label
				var snippetTypes = {
					<?php 
					$first = true;
					foreach ( $strings as $type => $label ) : 
						if ( ! $first ) {
							echo ',';
						}
						echo "'" . esc_js( $type ) . "': '" . esc_js( $label ) . "'";
						$first = false;
					endforeach; 
					?>
				};
				
				var buttonLabel = '<?php echo esc_js( $button_label ); ?>';
				var selectedType = 'php'; // default
				
				// Try to determine the selected type from the button label
				for (var type in snippetTypes) {
					if (buttonLabel.indexOf(snippetTypes[type]) !== -1) {
						selectedType = type;
						break;
					}
				}
				
				// Base URL for creating new snippets
				var baseUrl = <?php echo wp_json_encode( admin_url( 'post-new.php?post_type=' . WINP_SNIPPETS_POST_TYPE . '&winp_item=' ) ); ?>;

				var dropdownHtml = '<div class="winp-dropdown-button">';
				dropdownHtml += '<a href="' + baseUrl + selectedType + '" class="winp-main-button" id="winp-main-action">';
				dropdownHtml += '<span class="dashicons dashicons-plus-alt2"></span>';
				dropdownHtml += '<span class="winp-button-text"><?php echo esc_js( $button_label ); ?></span>';
				dropdownHtml += '</a>';
				dropdownHtml += '<button class="winp-dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">';
				dropdownHtml += '<span class="dashicons dashicons-arrow-down-alt2"></span>';
				dropdownHtml += '</button>';
				dropdownHtml += '<div class="winp-dropdown-menu" role="menu">';
				<?php foreach ( $strings as $type => $label ) : ?>
				dropdownHtml += '<a href="#" class="winp-dropdown-item" data-type="<?php echo esc_js( $type ); ?>" role="menuitem" tabindex="-1">';
				dropdownHtml += '<span class="winp-snippet-icon <?php echo esc_js( $type ); ?>"><?php echo esc_js( strtoupper( 'advert' === $type ? 'AD' : ( 'universal' === $type ? 'UNI' : $type ) ) ); ?></span>';
				dropdownHtml += '<span><?php echo esc_js( $label ); ?></span>';
				dropdownHtml += '</a>';
				<?php endforeach; ?>
				dropdownHtml += '</div>';
				dropdownHtml += '</div>';
				
				$('#wpbody-content h1').append(dropdownHtml);
				
				var $toggle = $('.winp-dropdown-toggle');
				var $mainButton = $('.winp-main-button');
				var $buttonText = $('.winp-button-text');
				var $menu = $('.winp-dropdown-menu');
				var $items = $('.winp-dropdown-item');
				var currentFocus = -1;
				var currentSelectedType = selectedType;
				
				// Type display labels from PHP
				var typeLabels = {
					<?php 
					$first = true;
					foreach ( $type_display_labels as $type => $display_label ) : 
						if ( ! $first ) {
							echo ',';
						}
						echo "'" . esc_js( $type ) . "': '" . esc_js( $display_label ) . "'";
						$first = false;
					endforeach; 
					?>
				};
				
				// Handle dropdown item click
				$items.on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					var type = $(this).data('type');
					currentSelectedType = type;
					
					// Update button text and href
					if (typeLabels[type]) {
						var newLabel = 'New Snippet: ' + typeLabels[type];
						$buttonText.text(newLabel);
						$mainButton.attr('href', baseUrl + type);
					}
					
					closeDropdown();
				});
				
				// Toggle dropdown
				$toggle.on('click', function(e) {
					e.stopPropagation();
					var isOpen = $menu.hasClass('show');
					
					if (isOpen) {
						closeDropdown();
					} else {
						openDropdown();
					}
				});
				
				function openDropdown() {
					$menu.addClass('show');
					$toggle.addClass('active').attr('aria-expanded', 'true');
					$items.first().focus();
					currentFocus = 0;
				}
				
				function closeDropdown() {
					$menu.removeClass('show');
					$toggle.removeClass('active').attr('aria-expanded', 'false');
					$items.removeClass('keyboard-focus');
					currentFocus = -1;
				}
				
				// Close on outside click
				$(document).on('click', function(e) {
					if (!$(e.target).closest('.winp-dropdown-button').length) {
						closeDropdown();
					}
				});
				
				// Keyboard navigation
				$(document).on('keydown', function(e) {
					if (!$menu.hasClass('show')) {
						return;
					}
					
					// ESC key
					if (e.keyCode === 27) {
						e.preventDefault();
						closeDropdown();
						$toggle.focus();
					}
					
					// Arrow Down
					if (e.keyCode === 40) {
						e.preventDefault();
						currentFocus++;
						if (currentFocus >= $items.length) {
							currentFocus = 0;
						}
						setFocus();
					}
					
					// Arrow Up
					if (e.keyCode === 38) {
						e.preventDefault();
						currentFocus--;
						if (currentFocus < 0) {
							currentFocus = $items.length - 1;
						}
						setFocus();
					}
					
					// Enter or Space
					if (e.keyCode === 13 || e.keyCode === 32) {
						if (currentFocus > -1) {
							e.preventDefault();
							$items.eq(currentFocus).trigger('click');
						}
					}
				});
				
				function setFocus() {
					$items.removeClass('keyboard-focus');
					$items.eq(currentFocus).addClass('keyboard-focus').focus();
				}
				
				// Hover updates focus
				$items.on('mouseenter', function() {
					currentFocus = $items.index(this);
					$items.removeClass('keyboard-focus');
				});
			});
			/* ]]> */
		</script>
		<?php
	}

	/**
	 * Add quick buttons
	 *
	 * @param \WP_Screen $current_screen WordPress screen object.
	 * 
	 * @return void
	 */
	public function current_screen( $current_screen ) {
		if ( WINP_SNIPPETS_POST_TYPE !== $current_screen->post_type ) {
			return;
		}

		if ( 'post' === $current_screen->base ) {
			add_action( 'admin_head', [ $this, 'current_screen_post' ] );
		}

		if ( 'edit' === $current_screen->base ) {
			add_action( 'admin_head', [ $this, 'current_screen_edit' ] );
		}
	}

	/**
	 * Show the Permalink edit form
	 *
	 * @param string|\WP_Post $filename Filename or post object.
	 * @param string          $permalink Permalink URL.
	 * @param string          $filetype File type.
	 * 
	 * @return void
	 */
	public function edit_form_before_permalink( $filename = '', $permalink = '', $filetype = 'css' ) {
		$filetype = WINP_HTTP::get( 'winp_item', $filetype, true );

		if ( ! in_array( $filetype, [ 'css', 'js' ] ) ) {
			return;
		}

		if ( ! is_string( $filename ) ) {
			global $post;

			if ( ! is_object( $post ) ) {
				return;
			}

			if ( WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
				return;
			}

			$post             = $filename;
			$slug             = WINP_Helper::getMetaOption( $post->ID, 'css_js_slug', '' );
			$default_filetype = WINP_Helper::getMetaOption( $post->ID, 'filetype', '' );
			if ( $default_filetype ) {
				$filetype = $default_filetype;
			} else {
				$filetype = WINP_Helper::get_snippet_type( $post->ID );
			}

			if ( ! in_array( $filetype, [ 'css', 'js' ] ) ) {
				return;
			}

			if ( ! @file_exists( WINP_UPLOAD_DIR . '/' . $slug . '.' . $filetype ) ) {
				$slug = false;
			}
			$filename = ( $slug ) ? $slug : $post->ID;
		}

		if ( empty( $permalink ) ) {
			$permalink = WINP_UPLOAD_URL . '/' . $filename . '.' . $filetype;
		}
		?>
		<div class="inside">
			<div id="edit-slug-box" class="hide-if-no-js">
				<strong><?php _e( 'Permalink', 'insert-php' ); ?>:</strong>
				<span id="sample-permalink"><a
							href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( WINP_UPLOAD_URL ) . '/'; ?><span
								id="editable-post-name"><?php echo esc_html( $filename ); ?></span>.<?php echo esc_html( $filetype ); ?></a></span>
				<span id="winp-edit-slug-buttons"><button type="button"
															class="winp-edit-slug button button-small hide-if-no-js"
															aria-label="<?php _e( 'Edit URL Slug', 'insert-php' ); ?>"><?php _e( 'Edit', 'insert-php' ); ?></button></span>
				<span id="editable-post-name-full"><?php echo esc_html( $filename ); ?></span>
			</div>
			<?php wp_nonce_field( 'winp-permalink', 'winp-permalink-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Remove the JS/CSS file from the disk when deleting the post
	 *
	 * @param int $postid Post ID.
	 * 
	 * @return void
	 */
	public function before_delete_post( $postid ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return;
		}
		if ( WINP_SNIPPETS_POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( ! wp_is_writable( WINP_UPLOAD_DIR ) ) {
			return;
		}

		$default_filetype = WINP_Helper::get_snippet_type( $post->ID );
		$filetype         = WINP_Helper::getMetaOption( $postid, 'filetype', $default_filetype );

		if ( ! in_array( $filetype, [ 'css', 'js' ] ) ) {
			return;
		}

		$slug      = WINP_Helper::getMetaOption( $postid, 'css_js_slug' );
		$file_name = $postid . '.' . $filetype;

		@unlink( WINP_UPLOAD_DIR . '/' . $file_name );

		if ( ! empty( $slug ) ) {
			@unlink( WINP_UPLOAD_DIR . '/' . $slug . '.' . $filetype );
		}
	}

	/**
	 * Save post
	 *
	 * @param int $post_id Post ID.
	 * 
	 * @return void
	 */
	public function save_post( $post_id ) {
		$nonce = WINP_HTTP::post( 'winp-permalink-nonce' );

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'winp-permalink' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( WINP_SNIPPETS_POST_TYPE != WINP_HTTP::post( 'post_type' ) ) {
			return;
		}

		$default_filetype = WINP_Helper::get_snippet_type( $post_id );
		$filetype         = WINP_Helper::getMetaOption( $post_id, 'filetype', $default_filetype );

		if ( ! in_array( $filetype, [ 'css', 'js' ] ) ) {
			return;
		}

		$plugin_title = __( 'Woody Code Snippets', 'insert-php' );
		$before       = $after = '';

		$snippet_linking = WINP_HTTP::post( 'wbcr_inp_snippet_linking' );
		$linking         = ! empty( $snippet_linking ) ? $snippet_linking : 'external';
		$content         = get_post( $post_id )->post_content;

		// Save the Custom Code in a file in `wp-content/uploads/winp-css-js`.
		if ( 'inline' == $linking ) {
			$before = '<!-- start ' . $plugin_title . ' CSS and JS -->' . PHP_EOL;
			$after  = '<!-- end ' . $plugin_title . ' CSS and JS -->' . PHP_EOL;

			if ( 'css' == $filetype ) {
				$before .= '<style type="text/css">' . PHP_EOL;
				$after   = '</style>' . PHP_EOL . $after;
			}

			if ( 'js' == $filetype ) {
				if ( ! preg_match( '/<script\b[^>]*>([\s\S]*?)<\/script>/im', $content ) ) {
					$before .= '<script type="text/javascript">' . PHP_EOL;
					$after   = PHP_EOL . '</script>' . PHP_EOL . $after;
				} else {
					// the content has a <script> tag, then remove the comments so they don't show up on the frontend.
					$content = preg_replace( '@/\*[\s\S]*?\*/@', '', $content );
				}
			}
		}

		if ( 'external' == $linking ) {
			$before = '/******* Do not edit this file *******' . PHP_EOL . $plugin_title . ' CSS and JS' . PHP_EOL . 'Saved: ' . date( 'M d Y | H:i:s' ) . ' */' . PHP_EOL;

			// Save version for js and css file.
			WINP_Helper::updateMetaOption( $post_id, 'css_js_version', time() );
		}

		if ( wp_is_writable( WINP_UPLOAD_DIR ) ) {
			$file_content = $before . $content . $after;

			// save the file as the Permalink slug.
			$slug = WINP_Helper::getMetaOption( $post_id, 'css_js_slug' );
			if ( $slug ) {
				$file_name = $slug . '.' . $filetype;
				$file_slug = $slug;
			} else {
				$file_name = $post_id . '.' . $filetype;
				$file_slug = $post_id;
			}

			// Delete old file.
			$old_slug = WINP_Helper::getMetaOption( $post_id, 'css_js_exist_slug' );
			if ( $old_slug ) {
				@unlink( WINP_UPLOAD_DIR . '/' . $old_slug . '.' . $filetype );
			}

			// Save exist file slug.
			WINP_Helper::updateMetaOption( $post_id, 'css_js_exist_slug', $file_slug );

			@file_put_contents( WINP_UPLOAD_DIR . '/' . $file_name, $file_content );
		}
	}

	/**
	 * Action for pre saved snippet.
	 * Если это не обновление поста, если это "черновик", и есть параметр с id сниппета, то заполняем данные сниппета для просмотра
	 *
	 * @param int      $post_ID Post ID.
	 * @param \WP_Post $current_post Current post object.
	 * @param bool     $update Whether this is an existing post being updated.
	 * 
	 * @return void
	 */
	public function save_snippet( $post_ID, $current_post, $update ) {
		$snippet_id = WINP_HTTP::get( 'snippet_id' );
		$common     = WINP_HTTP::get( 'common', false );

		if ( ! $update && 'auto-draft' == $current_post->post_status && ! empty( $snippet_id ) && WINP_SNIPPETS_POST_TYPE == $current_post->post_type ) {
			$snippet        = [];
			$saved_snippets = get_user_meta( get_current_user_id(), 'wbcr_inp_current_snippets', true );

			if ( ! empty( $saved_snippets ) && isset( $saved_snippets[ $snippet_id ] ) ) {
				$snippet = $saved_snippets[ $snippet_id ];
			}

			if ( empty( $snippet ) ) {
				$_snippet = WINP_Plugin::app()->get_api_object()->get_snippet( $snippet_id, $common );
				if ( ! empty( $_snippet ) ) {
					$snippet = [
						'title'    => $_snippet->title,
						'desc'     => $_snippet->description,
						'type'     => $_snippet->type->slug,
						'content'  => $_snippet->content,
						'type_id'  => $_snippet->type_id,
						'scope'    => $_snippet->execute_everywhere,
						'priority' => $_snippet->priority,
					];
				}
			}

			if ( ! empty( $snippet ) ) {
				$post_data = [
					'ID'           => $post_ID,
					'post_title'   => $snippet['title'],
					'post_content' => $snippet['content'],
				];
				wp_update_post( $post_data );

				WINP_Helper::updateMetaOption( $post_ID, 'snippet_api_snippet', $snippet_id );
				WINP_Helper::updateMetaOption( $post_ID, 'snippet_type', $snippet['type'] );
				WINP_Helper::updateMetaOption( $post_ID, 'snippet_api_type', $snippet['type_id'] );
				WINP_Helper::updateMetaOption( $post_ID, 'snippet_description', $snippet['desc'] );
				WINP_Helper::updateMetaOption( $post_ID, 'snippet_draft', true );
				WINP_Helper::updateMetaOption( $post_ID, 'snippet_scope', $snippet['scope'] );

				if ( isset( $snippet['priority'] ) ) {
					WINP_Helper::updateMetaOption( $post_ID, 'snippet_priority', $snippet['priority'] );
				}

				wp_safe_redirect( admin_url( 'post.php?post=' . $post_ID . '&action=edit' ) );
			}
		}
	}

	/**
	 * Delete auto-draft status after post snippet is publishing
	 *
	 * @param \WP_Post $post Post object.
	 * 
	 * @return void
	 */
	public function publish_snippet( $post ) {
		if ( WINP_SNIPPETS_POST_TYPE == $post->post_type ) {
			delete_post_meta( $post->ID, 'wbcr_inp_snippet_draft' );
		}
	}

	/**
	 * Action admin_footer
	 * 
	 * @return void
	 */
	public function admin_footer() {
		?>
		<script type="text/javascript">!function (e, t, n) {
				function a() {
					var e = t.getElementsByTagName("script")[0], n = t.createElement("script");
					n.type = "text/javascript", n.async = !0, n.src = "https://beacon-v2.helpscout.net", e.parentNode.insertBefore(n, e)
				}

				if (e.Beacon = n = function (t, n, a) {
					e.Beacon.readyQueue.push({
						method: t,
						options: n,
						data: a
					})
				}, n.readyQueue = [], "complete" === t.readyState) {
					return a();
				}
				e.attachEvent ? e.attachEvent("onload", a) : e.addEventListener("load", a, !1)
			}(window, document, window.Beacon || function () {
			});</script>
		<script type="text/javascript">window.Beacon('init', '1a4078fd-3e77-4692-bcfa-47bb4da0cee5')</script>
		<?php
	}

	/**
	 * Unload specific scripts
	 *
	 * @param string $src Script source URL.
	 * @param string $handle Script handle.
	 *
	 * @return bool|string False to prevent loading, otherwise the script source URL.
	 */
	public function unload_scripts( $src, $handle ) {
		global $post;

		// Check if we are editing a snippet post.
		if ( is_admin() && ! empty( $post ) && $post->post_type == WINP_SNIPPETS_POST_TYPE ) {
			// Unload ckeditor.js from theme The Rex.
			if ( 'bk-ckeditor-js' == $handle ) {
				return false;
			}
		}

		return $src;
	}
}