<?php
/**
 * Admin Bar functionality
 *
 * @package Woody_Code_Snippets
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WINP_Admin_Bar
 * 
 * Adds Woody Code Snippets menu to WordPress admin bar.
 */
class WINP_Admin_Bar {

	/**
	 * Class instance.
	 * 
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Active snippets on current page.
	 *
	 * @var array<int, array{id: int, name: string, type: string, location: string, scope: string}>
	 */
	private $active_snippets = [];

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 100 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'wp_footer', [ $this, 'update_admin_bar_with_js' ], 9999 );
		add_action( 'admin_footer', [ $this, 'update_admin_bar_with_js' ], 9999 );
	}

	/**
	 * Enqueue admin bar styles.
	 * 
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		wp_add_inline_style(
			'admin-bar',
			'
			/* Main menu item */
			#wp-admin-bar-woody-snippets .ab-icon:before {
				content: "{ }";
				font-family: monospace;
				font-size: 16px;
				font-weight: bold;
				top: -2px;
			}
			
			#wp-admin-bar-woody-snippets > .ab-item .ab-label {
				padding-left: 4px;
			}
			
			#wp-admin-bar-woody-snippets .woody-count {
				display: inline-block;
				background: #10b981;
				color: #fff;
				border-radius: 50%;
				width: 20px;
				height: 20px;
				line-height: 20px;
				text-align: center;
				font-size: 11px;
				font-weight: 600;
				margin-left: 6px;
				vertical-align: middle;
			}
			
			#wp-admin-bar-woody-snippets .woody-safe-mode {
                display: inline-block;
                background: #dba617;
                color: #fff;
                border-radius: 4px;
                padding: 0px 6px;
                font-size: 8px;
                font-weight: 600;
                margin-left: 6px;
                vertical-align: middle;
                text-transform: uppercase;
                letter-spacing: 0.5px;
			}
			
			/* Dropdown submenu */
			#wp-admin-bar-woody-snippets > .ab-sub-wrapper {
				width: 320px;
			}
			
			#wp-admin-bar-woody-snippets .ab-submenu {
				background: #23272a;
			}
			
			/* Header section */
			#wp-admin-bar-woody-snippets-header {
				background: #23272a !important;
				border-bottom: 2px solid #3b82f6 !important;
			}
			
			#wp-admin-bar-woody-snippets-header > .ab-item {
				color: #60a5fa !important;
				background: transparent !important;
				font-weight: 600;
				text-transform: uppercase;
				font-size: 11px;
				letter-spacing: 0.1em;
				cursor: default !important;
				padding: 4px 16px !important;
				line-height: 1.2;
				min-height: auto !important;
			}
			
			#wp-admin-bar-woody-snippets-header:hover > .ab-item {
				background: transparent !important;
				color: #60a5fa !important;
			}
			
			/* Snippet items */
			#wp-admin-bar-woody-snippets .woody-snippet-item > .ab-item {
				padding: 8px 16px !important;
                height: auto !important;
				line-height: 1.4;
				min-height: auto !important;
				background: #2c3338 !important;
                border-bottom: 1px solid rgb(60 67 74);
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-item {
				border-bottom: 1px solid rgba(255, 255, 255, 0.05);
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-item:hover > .ab-item {
				background: #32373c !important;
			}
			
			#wp-admin-bar-woody-snippets li.woody-snippet-item:last-of-type {
				border-bottom: none;
			}
			
			/* Snippet content wrapper */
			#wp-admin-bar-woody-snippets .woody-snippet-content {
				display: flex;
				flex-direction: column;
				gap: 8px;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-name {
				font-weight: 400;
				font-size: 15px;
				color: #e8eaed;
				white-space: wrap;
				overflow: hidden;
				text-overflow: ellipsis;
				line-height: 1.3;
			}
			
			/* Snippet meta (type + location) */
			#wp-admin-bar-woody-snippets .woody-snippet-meta {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			
			/* Type badge */
			#wp-admin-bar-woody-snippets .woody-snippet-type {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				padding: 5px 11px;
				border-radius: 4px;
				font-weight: 700;
				font-size: 10px;
				text-transform: uppercase;
				letter-spacing: 0.02em;
				flex-shrink: 0;
				line-height: 1;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-js {
				background: #f7df1e;
				color: #655b0e;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-html {
				background: #e44d26;
				color: #fffffd;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-php {
				background: #777bb3;
				color: #ffffff;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-universal {
				background: #607D8B;
				color: #ffffff;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-css {
				background: #1572b6;
				color: #ffffff;
			}
		
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-text {
				background: #4CAF50;
				color: #fffffd;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-type.type-ad {
				background: #26aa5d;
				color: #fffffd;
			}
			
			#wp-admin-bar-woody-snippets .woody-snippet-location {
				color: #a0a5aa;
				font-size: 12px;
				font-weight: 400;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				line-height: 1.3;
			}
			
			/* View all link */
			#wp-admin-bar-woody-snippets-view-all {
				border-top: 1px solid rgba(255, 255, 255, 0.05);
				background: #23272a !important;
			}
			
			#wp-admin-bar-woody-snippets-view-all > .ab-item {
				text-align: center;
				color: #60a5fa !important;
				font-weight: 500;
				padding: 13px 16px !important;
				min-height: auto !important;
				background: transparent !important;
                padding: 3px 0 !important;
			}
			
			#wp-admin-bar-woody-snippets-view-all > .ab-item:after {
				content: " →";
			}
			
			#wp-admin-bar-woody-snippets-view-all:hover > .ab-item {
				color: #93c5fd !important;
				background: rgba(59, 130, 246, 0.1) !important;
			}
			
			/* Empty state */
			#wp-admin-bar-woody-snippets-empty > .ab-item {
				color: #a0a5aa !important;
				font-style: italic;
				padding: 16px !important;
				min-height: auto !important;
				background: #2c3338 !important;
			}
			
			/* Safe mode message */
			#wp-admin-bar-woody-snippets-safe-mode-message > .ab-item {
				padding: 16px !important;
				min-height: auto !important;
				background: #2c3338 !important;
				line-height: 1.5;
				color: #e8eaed;
				font-size: 14px;
				white-space: wrap !important;
				height: auto !important;
			}
			
			/* Disable safe mode button (styled like view all) */
			#wp-admin-bar-woody-snippets-disable-safe-mode {
				border-top: 1px solid rgba(255, 255, 255, 0.05);
				background: #23272a !important;
			}
			
			#wp-admin-bar-woody-snippets-disable-safe-mode > .ab-item {
				text-align: center;
				color: #60a5fa !important;
				font-weight: 500;
				padding: 13px 16px !important;
				min-height: auto !important;
				background: transparent !important;
				padding: 3px 0 !important;
			}
			
			#wp-admin-bar-woody-snippets-disable-safe-mode:hover > .ab-item {
				color: #93c5fd !important;
				background: rgba(59, 130, 246, 0.1) !important;
			}
			
			/* Pagination */
			#wp-admin-bar-woody-snippets-pagination {
				background: #23272a !important;
				border-top: 1px solid rgba(255, 255, 255, 0.05);
				border-bottom: 1px solid rgba(255, 255, 255, 0.05);
			}
			
			#wp-admin-bar-woody-snippets-pagination > .ab-item {
				display: flex !important;
				align-items: center;
				justify-content: space-between;
				padding: 8px 16px !important;
				min-height: auto !important;
				background: transparent !important;
				cursor: default !important;
			}
			
			#wp-admin-bar-woody-snippets-pagination .woody-pagination-buttons {
				display: flex;
				gap: 8px;
			}
			
			#wp-admin-bar-woody-snippets-pagination .woody-pagination-btn {
				background: #32373c;
				color: #a0a5aa;
				border: 1px solid rgba(255, 255, 255, 0.1);
				border-radius: 3px;
				padding: 4px 10px;
				font-size: 11px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.2s;
			}
			
			#wp-admin-bar-woody-snippets-pagination .woody-pagination-btn:hover:not(:disabled) {
				background: #3b82f6;
				color: #fff;
				border-color: #3b82f6;
			}
			
			#wp-admin-bar-woody-snippets-pagination .woody-pagination-btn:disabled {
				opacity: 0.4;
				cursor: not-allowed;
			}
			
			#wp-admin-bar-woody-snippets-pagination .woody-pagination-info {
				color: #a0a5aa;
				font-size: 12px;
			}
			'
		);
	}

	/**
	 * Add admin bar menu.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get active snippets (static for now).
		$this->collect_active_snippets();

		$count = count( $this->active_snippets );
		
		// Check if safe mode is enabled.
		$is_safe_mode = WINP_Helper::is_safe_mode();

		// Add parent menu item.
		$wp_admin_bar->add_node(
			[
				'id'     => 'woody-snippets',
				'parent' => 'top-secondary',
				'title'  => $is_safe_mode 
					? sprintf(
						'<span class="ab-icon"></span><span class="ab-label">%s</span><span class="woody-safe-mode">%s</span>',
						esc_html__( 'Woody', 'insert-php' ),
						esc_html__( 'Safe Mode', 'insert-php' )
					)
					: sprintf(
						'<span class="ab-icon"></span><span class="ab-label">%s</span><span class="woody-count">%d</span>',
						esc_html__( 'Woody', 'insert-php' ),
						$count
					),
				'href'   => admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ),
				'meta'   => [
					'title' => $is_safe_mode 
						? __( 'Safe mode is enabled - snippets are not executing', 'insert-php' )
						: __( 'Active snippets on this page', 'insert-php' ),
				],
			]
		);

		// Add header.
		$wp_admin_bar->add_node(
			[
				'parent' => 'woody-snippets',
				'id'     => 'woody-snippets-header',
				'title'  => $is_safe_mode 
					? __( 'Safe Mode Enabled', 'insert-php' )
					: __( 'Active Snippets on This Page', 'insert-php' ),
				'meta'   => [
					'class' => 'woody-snippets-header',
				],
			]
		);
		
		// Add safe mode message if enabled.
		if ( $is_safe_mode ) {
			$wp_admin_bar->add_node(
				[
					'parent' => 'woody-snippets',
					'id'     => 'woody-snippets-safe-mode-message',
					'title'  => __( 'Safe Mode is active. All snippets are temporarily disabled. You can re-enable them after fixing any issues.', 'insert-php' ),
					'meta'   => [
						'class' => 'woody-safe-mode-message',
					],
				]
			);
		}

		// Add snippet items (limited to 5 per page).
		if ( ! empty( $this->active_snippets ) ) {
			$snippets_to_show = array_slice( $this->active_snippets, 0, 5 );
			foreach ( $snippets_to_show as $snippet ) {
				$wp_admin_bar->add_node(
					[
						'parent' => 'woody-snippets',
						'id'     => 'woody-snippet-' . $snippet['id'],
						'title'  => $this->render_snippet_item( $snippet ),
						'href'   => admin_url( 'post.php?post=' . $snippet['id'] . '&action=edit' ),
						'meta'   => [
							'class' => 'woody-snippet-item',
						],
					]
				);
			}
			
			// Add pagination if more than 5 snippets.
			if ( count( $this->active_snippets ) > 5 ) {
				$wp_admin_bar->add_node(
					[
						'parent' => 'woody-snippets',
						'id'     => 'woody-snippets-pagination',
						'title'  => $this->render_pagination( 1, count( $this->active_snippets ) ),
					]
				);
			}
		} elseif ( ! $is_safe_mode ) {
			// Only show empty state if not in safe mode.
			$wp_admin_bar->add_node(
				[
					'parent' => 'woody-snippets',
					'id'     => 'woody-snippets-empty',
					'title'  => __( 'No active snippets on this page', 'insert-php' ),
				]
			);
		}

		// Add disable safe mode button or view all link.
		if ( $is_safe_mode ) {
			$disable_url = admin_url( 'edit.php?post_type=wbcr-snippets&wbcr-php-snippets-disable-safe-mode=1' );
			$wp_admin_bar->add_node(
				[
					'parent' => 'woody-snippets',
					'id'     => 'woody-snippets-disable-safe-mode',
					'title'  => __( 'Disable Safe Mode', 'insert-php' ),
					'href'   => $disable_url,
				]
			);
		} else {
			$wp_admin_bar->add_node(
				[
					'parent' => 'woody-snippets',
					'id'     => 'woody-snippets-view-all',
					'title'  => __( 'View All Snippets', 'insert-php' ),
					'href'   => admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ),
				]
			);
		}
	}

	/**
	 * Render snippet item HTML.
	 *
	 * @param array{id: int, name: string, type: string, location: string, scope: string} $snippet Snippet data.
	 * @return string
	 */
	private function render_snippet_item( $snippet ) {
		$type_class = 'type-' . strtolower( $snippet['type'] );
		
		return sprintf(
			'<div class="woody-snippet-content">
				<div class="woody-snippet-name">%s</div>
				<div class="woody-snippet-meta">
					<span class="woody-snippet-type %s">%s</span>
					<span class="woody-snippet-location">%s</span>
				</div>
			</div>',
			esc_html( $snippet['name'] ),
			esc_attr( $type_class ),
			esc_html( $snippet['type'] ),
			esc_html( $snippet['location'] )
		);
	}

	/**
	 * Render pagination controls.
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_count Total number of snippets.
	 * @return string
	 */
	private function render_pagination( $current_page, $total_count ) {
		$total_pages = ceil( $total_count / 5 );
		$start       = ( ( $current_page - 1 ) * 5 ) + 1;
		$end         = min( $current_page * 5, $total_count );
		
		return sprintf(
			'<div style="display: flex; justify-content: space-between; align-items: center; width: 100%%;">
				<span class="woody-pagination-info">%s</span>
				<div class="woody-pagination-buttons">
					<button class="woody-pagination-btn" data-page="prev" %s>← %s</button>
					<button class="woody-pagination-btn" data-page="next" %s>%s →</button>
				</div>
			</div>',
			// translators: 1: start number, 2: end number, 3: total count.
			sprintf( __( '%1$d-%2$d of %3$d', 'insert-php' ), $start, $end, $total_count ),
			$current_page <= 1 ? 'disabled' : '',
			esc_html__( 'Previous', 'insert-php' ),
			$current_page >= $total_pages ? 'disabled' : '',
			esc_html__( 'Next', 'insert-php' )
		);
	}

	/**
	 * Collect active snippets on current page.
	 * 
	 * Gets the actually executed snippets from the execution tracker.
	 * 
	 * @return void
	 */
	private function collect_active_snippets() {
		// Get executed snippets from the execution tracker.
		if ( class_exists( 'WINP_Execute_Snippet' ) ) {
			$execute_instance = WINP_Execute_Snippet::app();
			if ( $execute_instance ) {
				$executed              = $execute_instance->get_executed_snippets();
				$this->active_snippets = array_values( $executed );
			}
		}
	}

	/**
	 * Update admin bar with JavaScript after all snippets have executed.
	 * 
	 * Outputs inline JavaScript that updates the admin bar with the final
	 * list of executed snippets, including footer snippets and shortcodes.
	 * 
	 * @return void
	 */
	public function update_admin_bar_with_js() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if safe mode is enabled.
		$is_safe_mode = WINP_Helper::is_safe_mode();
		
		// If safe mode is enabled, don't update the snippets list.
		if ( $is_safe_mode ) {
			return;
		}

		// Get the final list of executed snippets.
		$this->collect_active_snippets();
		$count       = count( $this->active_snippets );
		$total_pages = ceil( $count / 5 );

		// Prepare snippet data for JavaScript.
		$snippets_data = [];
		foreach ( $this->active_snippets as $snippet ) {
			$snippets_data[] = [
				'id'       => $snippet['id'],
				'name'     => $snippet['name'],
				'type'     => $snippet['type'],
				'location' => $snippet['location'],
				'edit_url' => admin_url( 'post.php?post=' . $snippet['id'] . '&action=edit' ),
			];
		}

		?>
		<script type="text/javascript">
		(function() {
			const snippetsData = <?php echo wp_json_encode( $snippets_data ); ?>;
			const totalCount = <?php echo intval( $count ); ?>;
			const perPage = 5;
			let currentPage = 1;
			
			function renderSnippetItem(snippet) {
				const typeClass = 'type-' + snippet.type.toLowerCase();
				return `<div class="woody-snippet-content">
					<div class="woody-snippet-name">${snippet.name}</div>
					<div class="woody-snippet-meta">
						<span class="woody-snippet-type ${typeClass}">${snippet.type}</span>
						<span class="woody-snippet-location">${snippet.location}</span>
					</div>
				</div>`;
			}
			
			function renderPagination() {
				const totalPages = Math.ceil(totalCount / perPage);
				const start = ((currentPage - 1) * perPage) + 1;
				const end = Math.min(currentPage * perPage, totalCount);
				const prevDisabled = currentPage <= 1 ? 'disabled' : '';
				const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
				
				return `<div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
					<span class="woody-pagination-info">${start}-${end} of ${totalCount}</span>
					<div class="woody-pagination-buttons">
						<button class="woody-pagination-btn" data-page="prev" ${prevDisabled}>← Prev</button>
						<button class="woody-pagination-btn" data-page="next" ${nextDisabled}>Next →</button>
					</div>
				</div>`;
			}
			
			function updateDisplay() {
				const submenu = document.querySelector('#wp-admin-bar-woody-snippets .ab-submenu');
				if (!submenu) return;
				
				let html = '<li id="wp-admin-bar-woody-snippets-header"><a class="ab-item" href="javascript:void(0)"><?php echo esc_js( __( 'Active Snippets on This Page', 'insert-php' ) ); ?></a></li>';
				
				if (snippetsData.length === 0) {
					html += '<li id="wp-admin-bar-woody-snippets-empty"><a class="ab-item" href="javascript:void(0)"><?php echo esc_js( __( 'No active snippets on this page', 'insert-php' ) ); ?></a></li>';
				} else {
					const start = (currentPage - 1) * perPage;
					const end = start + perPage;
					const pageSnippets = snippetsData.slice(start, end);
					
					pageSnippets.forEach(snippet => {
						html += `<li id="wp-admin-bar-woody-snippet-${snippet.id}" class="woody-snippet-item"><a class="ab-item" href="${snippet.edit_url}">${renderSnippetItem(snippet)}</a></li>`;
					});
					
					if (snippetsData.length > perPage) {
						html += `<li id="wp-admin-bar-woody-snippets-pagination"><a class="ab-item" href="javascript:void(0)">${renderPagination()}</a></li>`;
					}
				}
				
				html += '<li id="wp-admin-bar-woody-snippets-view-all"><a class="ab-item" href="<?php echo esc_js( admin_url( 'edit.php?post_type=' . WINP_SNIPPETS_POST_TYPE ) ); ?>"><?php echo esc_js( __( 'View All Snippets', 'insert-php' ) ); ?></a></li>';
				
				submenu.innerHTML = html;
				
				// Attach pagination handlers
				const prevBtn = submenu.querySelector('[data-page="prev"]');
				const nextBtn = submenu.querySelector('[data-page="next"]');
				
				if (prevBtn) {
					prevBtn.addEventListener('click', (e) => {
						e.preventDefault();
						e.stopPropagation();
						if (currentPage > 1) {
							currentPage--;
							updateDisplay();
						}
					});
				}
				
				if (nextBtn) {
					nextBtn.addEventListener('click', (e) => {
						e.preventDefault();
						e.stopPropagation();
						const totalPages = Math.ceil(totalCount / perPage);
						if (currentPage < totalPages) {
							currentPage++;
							updateDisplay();
						}
					});
				}
			}
			
			function updateAdminBar() {
				// Update the count badge
				const countBadge = document.querySelector('#wp-admin-bar-woody-snippets .woody-count');
				if (countBadge) {
					countBadge.textContent = totalCount;
				}
				
				// Update the submenu with pagination
				updateDisplay();
			}
			
			// Try immediately
			updateAdminBar();
			
			// Also try after a small delay in case admin bar loads late
			setTimeout(updateAdminBar, 100);
		})();
		</script>
		<?php
	}
}
