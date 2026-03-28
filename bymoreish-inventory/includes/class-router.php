<?php
/**
 * Router class for Bymoreish Inventory.
 *
 * Handles URL dispatch from the template_redirect hook.
 * Called via $router->dispatch( $page ) where $page is the bymoreish_page query var.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bymoreish_Router {

	/** @var Bymoreish_Router|null */
	private static $instance = null;

	// Routes that do NOT require authentication.
	private const PUBLIC_ROUTES = [
		'bymoreish',
		'bymoreish/login',
	];

	// Map of query-var value → page file (relative to plugin pages/ dir).
	private const ROUTE_MAP = [
		'bymoreish'                  => 'branch-selection.php',
		'bymoreish/login'            => 'login.php',
		'bymoreish/home'             => 'home.php',
		'bymoreish/orders'           => 'orders.php',
		'bymoreish/stock'            => 'stock.php',
		'bymoreish/financial'        => 'financial.php',
		'bymoreish/expenses'         => 'expenses.php',
		'bymoreish/analytics'        => 'analytics.php',
		'bymoreish/products'         => 'products.php',
		'bymoreish/admin'            => 'admin.php',
		'bymoreish/profile'          => 'profile.php',
		'bymoreish/history/orders'   => 'history-orders.php',
		'bymoreish/history/stock'    => 'history-stock.php',
		'bymoreish/history/expenses' => 'history-expenses.php',
		'bymoreish/history/financial'=> 'history-financial.php',
	];

	// Routes restricted to admin / superadmin.
	private const ADMIN_ROUTES = [
		'bymoreish/admin',
	];

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	private function __construct() {}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Dispatch
	// -----------------------------------------------------------------------

	/**
	 * Main routing entry-point called from template_redirect.
	 *
	 * @param string $page  The bymoreish_page query var value (e.g. 'bymoreish/home').
	 */
	public function dispatch( string $page ): void {
		// Normalise trailing slashes so 'bymoreish/' matches 'bymoreish'.
		$page = rtrim( $page, '/' );

		if ( ! array_key_exists( $page, self::ROUTE_MAP ) ) {
			$this->not_found();
			return;
		}

		$auth = Bymoreish_Auth::get_instance();

		// Handle logout action on the login page (requires a valid session nonce).
		if ( $page === 'bymoreish/login' ) {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
			if ( $action === 'logout' ) {
				$submitted_nonce = sanitize_text_field( wp_unslash( $_GET['_bym_nonce'] ?? '' ) );
				$stored_nonce    = (string) ( $_SESSION['bym_nonce'] ?? '' );

				// Only perform logout when a valid session nonce is present.
				if ( ! empty( $stored_nonce ) && hash_equals( $stored_nonce, $submitted_nonce ) ) {
					$auth->logout();
					wp_redirect( home_url( '/bymoreish/login' ) );
					exit;
				}

				// Invalid nonce on logout: silently redirect to login without logging out.
				wp_redirect( home_url( '/bymoreish/login' ) );
				exit;
			}
		}

		// Enforce authentication for protected routes.
		if ( ! in_array( $page, self::PUBLIC_ROUTES, true ) ) {
			if ( ! $auth->is_authenticated() ) {
				$auth->require_auth( home_url( '/' . $page ) );
				return;
			}
		}

		// Enforce admin role for admin routes.
		if ( in_array( $page, self::ADMIN_ROUTES, true ) ) {
			$user = $auth->get_current_user();
			if ( ! $auth->has_role( $user['role'] ?? '', 'admin' ) ) {
				wp_redirect( home_url( '/bymoreish/home' ) );
				exit;
			}
		}

		$this->include_page( $page, $auth );
	}

	// -----------------------------------------------------------------------
	// Page loader
	// -----------------------------------------------------------------------

	/**
	 * Include the page file and expose the $bymoreish_current_user variable.
	 *
	 * @param string          $page
	 * @param Bymoreish_Auth  $auth
	 */
	private function include_page( string $page, Bymoreish_Auth $auth ): void {
		$page_file = BYMOREISH_PLUGIN_DIR . 'pages/' . self::ROUTE_MAP[ $page ];

		if ( ! file_exists( $page_file ) ) {
			$this->page_unavailable( $page );
			return;
		}

		// Make current user data available to all page templates.
		$bymoreish_current_user = $auth->get_current_user();

		// Make the AJAX nonce available to page templates.
		$bymoreish_nonce = $this->get_nonce();

		require $page_file;
	}

	// -----------------------------------------------------------------------
	// Nonce helper
	// -----------------------------------------------------------------------

	/**
	 * Return the session-based AJAX nonce, creating it if it does not yet exist.
	 */
	public function get_nonce(): string {
		if ( ! session_id() ) {
			session_start();
		}

		if ( empty( $_SESSION['bym_nonce'] ) ) {
			$_SESSION['bym_nonce'] = bin2hex( random_bytes( 16 ) );
		}

		return (string) $_SESSION['bym_nonce'];
	}

	// -----------------------------------------------------------------------
	// Error pages
	// -----------------------------------------------------------------------

	/**
	 * Render a minimal 404-style page for unknown routes.
	 */
	private function not_found(): void {
		status_header( 404 );
		bymoreish_render_page(
			'Page Not Found',
			'<div class="flex flex-col items-center justify-center min-h-screen gap-4">
				<h1 class="text-4xl font-bold text-white">404</h1>
				<p class="text-gray-400">The page you requested does not exist.</p>
				<a href="' . esc_url( home_url( '/bymoreish/home' ) ) . '"
				   class="px-4 py-2 bg-amber-500 hover:bg-amber-600 rounded-lg text-white text-sm font-medium transition-colors">
					Go Home
				</a>
			</div>',
			false
		);
	}

	/**
	 * Render a placeholder when a page file has not been created yet.
	 *
	 * @param string $page
	 */
	private function page_unavailable( string $page ): void {
		$label = ucwords( str_replace( [ 'bymoreish/', '/' ], [ '', ' › ' ], $page ) );
		bymoreish_render_page(
			$label,
			'<div class="flex flex-col items-center justify-center min-h-screen gap-4">
				<span class="iconify text-6xl text-amber-500" data-icon="solar:construction-bold"></span>
				<h1 class="text-2xl font-bold text-white">' . esc_html( $label ) . '</h1>
				<p class="text-gray-400">This page is under construction.</p>
				<a href="' . esc_url( home_url( '/bymoreish/home' ) ) . '"
				   class="px-4 py-2 bg-amber-500 hover:bg-amber-600 rounded-lg text-white text-sm font-medium transition-colors">
					Go Home
				</a>
			</div>'
		);
	}
}
