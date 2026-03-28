<?php
/**
 * Plugin Name: Bymoreish Inventory Management
 * Plugin URI:  https://bymoreish.com
 * Description: Restaurant Inventory Management System for Bymoreish
 * Version:     1.0.0
 * Author:      Bymoreish
 * Text Domain: bymoreish-inventory
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'BYMOREISH_VERSION',    '1.0.0' );
define( 'BYMOREISH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BYMOREISH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Autoload class files
// ---------------------------------------------------------------------------
$bym_includes = [
	'includes/class-database.php',
	'includes/class-auth.php',
	'includes/class-router.php',
	'includes/class-assets.php',
];

foreach ( $bym_includes as $bym_file ) {
	$bym_path = BYMOREISH_PLUGIN_DIR . $bym_file;
	if ( file_exists( $bym_path ) ) {
		require_once $bym_path;
	}
}

// ---------------------------------------------------------------------------
// Activation / Deactivation hooks
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'bymoreish_activate' );
register_deactivation_hook( __FILE__, 'bymoreish_deactivate' );

function bymoreish_activate() {
	$db = Bymoreish_Database::get_instance();
	$db->create_tables();
	$db->insert_default_data();

	// Register rewrite rules so they are flushed on activation.
	bymoreish_register_rewrite_rules();
	flush_rewrite_rules();
}

function bymoreish_deactivate() {
	flush_rewrite_rules();
}

// ---------------------------------------------------------------------------
// WordPress hooks
// ---------------------------------------------------------------------------
add_action( 'init',                  'bymoreish_init' );
add_action( 'template_redirect',     'bymoreish_template_redirect' );
add_action( 'wp_enqueue_scripts',    'bymoreish_enqueue_assets' );
add_filter( 'query_vars',            'bymoreish_query_vars' );

// ---------------------------------------------------------------------------
// Session bootstrap (must happen before headers are sent)
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'bymoreish_start_session', 1 );

function bymoreish_start_session() {
	if ( ! session_id() ) {
		session_start();
	}
}

// ---------------------------------------------------------------------------
// Rewrite rules
// ---------------------------------------------------------------------------
function bymoreish_register_rewrite_rules() {
	$pages = [
		'bymoreish/?$',
		'bymoreish/login/?$',
		'bymoreish/home/?$',
		'bymoreish/orders/?$',
		'bymoreish/stock/?$',
		'bymoreish/financial/?$',
		'bymoreish/expenses/?$',
		'bymoreish/analytics/?$',
		'bymoreish/products/?$',
		'bymoreish/admin/?$',
		'bymoreish/profile/?$',
		'bymoreish/history/orders/?$',
		'bymoreish/history/stock/?$',
		'bymoreish/history/expenses/?$',
		'bymoreish/history/financial/?$',
	];

	foreach ( $pages as $regex ) {
		// Extract the slug portion to use as the query var value.
		$slug = rtrim( preg_replace( '/\??\$/', '', $regex ), '/' );
		add_rewrite_rule( $regex, 'index.php?bymoreish_page=' . rawurlencode( $slug ), 'top' );
	}
}

function bymoreish_query_vars( $vars ) {
	$vars[] = 'bymoreish_page';
	return $vars;
}

function bymoreish_init() {
	bymoreish_register_rewrite_rules();
}

// ---------------------------------------------------------------------------
// Template redirect – intercept all Bymoreish URLs
// ---------------------------------------------------------------------------
function bymoreish_template_redirect() {
	$page = get_query_var( 'bymoreish_page' );

	if ( $page === '' || $page === false ) {
		return; // Not a Bymoreish URL; let WordPress handle it normally.
	}

	// Ensure session is active.
	if ( ! session_id() ) {
		session_start();
	}

	$router = Bymoreish_Router::get_instance();
	$router->dispatch( $page );
	exit;
}

// ---------------------------------------------------------------------------
// Asset enqueueing (used by the router when it outputs pages)
// ---------------------------------------------------------------------------
function bymoreish_enqueue_assets() {
	if ( get_query_var( 'bymoreish_page' ) === '' ) {
		return;
	}

	if ( class_exists( 'Bymoreish_Assets' ) ) {
		Bymoreish_Assets::enqueue();
	}
}

// ---------------------------------------------------------------------------
// Helper: render a full HTML5 page shell with Tailwind + Iconify
// ---------------------------------------------------------------------------
function bymoreish_render_page( string $title, string $body_html, bool $include_nav = true ) {
	$login_url = home_url( '/bymoreish/login' );
	$nav_html  = '';

	if ( $include_nav && bymoreish_is_authenticated() ) {
		$current_user = bymoreish_current_user();
		$full_name    = esc_html( $current_user['full_name'] ?? 'User' );
		$role         = esc_html( $current_user['role'] ?? 'staff' );

		$nav_links = [
			[ 'href' => '/bymoreish/home',              'icon' => 'solar:home-2-bold',           'label' => 'Home' ],
			[ 'href' => '/bymoreish/orders',            'icon' => 'solar:clipboard-list-bold',   'label' => 'Orders' ],
			[ 'href' => '/bymoreish/stock',             'icon' => 'solar:box-bold',              'label' => 'Stock' ],
			[ 'href' => '/bymoreish/financial',         'icon' => 'solar:wallet-money-bold',     'label' => 'Financial' ],
			[ 'href' => '/bymoreish/expenses',          'icon' => 'solar:card-bold',             'label' => 'Expenses' ],
			[ 'href' => '/bymoreish/analytics',         'icon' => 'solar:chart-bold',            'label' => 'Analytics' ],
			[ 'href' => '/bymoreish/products',          'icon' => 'solar:hamburger-bold',        'label' => 'Products' ],
		];

		if ( in_array( $role, [ 'admin', 'superadmin' ], true ) ) {
			$nav_links[] = [ 'href' => '/bymoreish/admin', 'icon' => 'solar:settings-bold', 'label' => 'Admin' ];
		}

		$links_html = '';
		foreach ( $nav_links as $link ) {
			$href  = esc_url( home_url( $link['href'] ) );
			$icon  = esc_attr( $link['icon'] );
			$label = esc_html( $link['label'] );
			$links_html .= <<<HTML
				<a href="{$href}"
				   class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium
				          text-gray-300 hover:text-white hover:bg-white/10 transition-all duration-200">
					<span class="iconify text-xl shrink-0" data-icon="{$icon}"></span>
					<span>{$label}</span>
				</a>
HTML;
		}

		$profile_url = esc_url( home_url( '/bymoreish/profile' ) );
		$logout_url  = esc_url( home_url( '/bymoreish/login?action=logout' ) );

		$nav_html = <<<HTML
		<aside class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 border-r border-white/10 flex flex-col">
			<!-- Brand -->
			<div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
				<div class="w-9 h-9 rounded-xl bg-amber-500 flex items-center justify-center shrink-0">
					<span class="iconify text-white text-xl" data-icon="solar:chef-hat-bold"></span>
				</div>
				<div>
					<p class="font-bold text-white leading-tight">Bymoreish</p>
					<p class="text-xs text-gray-400">Inventory</p>
				</div>
			</div>

			<!-- Nav links -->
			<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
				{$links_html}
			</nav>

			<!-- User footer -->
			<div class="px-4 py-4 border-t border-white/10">
				<div class="flex items-center gap-3 mb-3">
					<div class="w-9 h-9 rounded-full bg-amber-500 flex items-center justify-center shrink-0">
						<span class="iconify text-white" data-icon="solar:user-bold"></span>
					</div>
					<div class="min-w-0">
						<p class="text-sm font-medium text-white truncate">{$full_name}</p>
						<p class="text-xs text-gray-400 capitalize">{$role}</p>
					</div>
				</div>
				<div class="flex gap-2">
					<a href="{$profile_url}"
					   class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg
					          text-xs text-gray-400 hover:text-white hover:bg-white/10 transition-all">
						<span class="iconify" data-icon="solar:user-circle-bold"></span>
						Profile
					</a>
					<a href="{$logout_url}"
					   class="flex-1 flex items-center justify-center gap-1 px-2 py-1.5 rounded-lg
					          text-xs text-red-400 hover:text-white hover:bg-red-500/20 transition-all">
						<span class="iconify" data-icon="solar:logout-bold"></span>
						Logout
					</a>
				</div>
			</div>
		</aside>
		<div class="ml-64 min-h-screen bg-gray-950">
HTML;
	}

	$close_wrapper = ( $include_nav && bymoreish_is_authenticated() ) ? '</div>' : '';

	echo <<<HTML
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$title} – Bymoreish Inventory</title>

	<!-- Tailwind CSS CDN -->
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			darkMode: 'class',
			theme: {
				extend: {
					colors: {
						gray: {
							950: '#0a0a0f',
						}
					}
				}
			}
		};
	</script>

	<!-- Iconify (Solar icon set) -->
	<script src="https://code.iconify.design/3/3.1.1/iconify.min.js"></script>

	<style>
		*, *::before, *::after { box-sizing: border-box; }
		body { margin: 0; }
	</style>
</head>
<body class="dark h-full bg-gray-950 text-white antialiased">
	{$nav_html}
	{$body_html}
	{$close_wrapper}
</body>
</html>
HTML;
}

// ---------------------------------------------------------------------------
// Authentication helpers (session-based, not WP nonces)
// ---------------------------------------------------------------------------
function bymoreish_is_authenticated(): bool {
	if ( ! session_id() ) {
		session_start();
	}
	return ! empty( $_SESSION['bym_user_id'] ) && ! empty( $_SESSION['bym_authenticated'] );
}

function bymoreish_current_user(): array {
	if ( ! bymoreish_is_authenticated() ) {
		return [];
	}
	return [
		'id'        => (int) ( $_SESSION['bym_user_id']   ?? 0 ),
		'username'  => (string) ( $_SESSION['bym_username'] ?? '' ),
		'full_name' => (string) ( $_SESSION['bym_full_name'] ?? '' ),
		'role'      => (string) ( $_SESSION['bym_role']     ?? 'staff' ),
		'branch'    => (string) ( $_SESSION['bym_branch']   ?? '' ),
	];
}

function bymoreish_login_user( array $user_row ) {
	if ( ! session_id() ) {
		session_start();
	}
	session_regenerate_id( true );
	$_SESSION['bym_authenticated'] = true;
	$_SESSION['bym_user_id']       = (int) $user_row['id'];
	$_SESSION['bym_username']      = (string) $user_row['username'];
	$_SESSION['bym_full_name']     = (string) $user_row['full_name'];
	$_SESSION['bym_role']          = (string) $user_row['role'];
	$_SESSION['bym_branch']        = (string) $user_row['branch'];
}

function bymoreish_logout_user() {
	if ( ! session_id() ) {
		session_start();
	}
	$_SESSION = [];
	if ( ini_get( 'session.use_cookies' ) ) {
		$params = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);
	}
	session_destroy();
}

function bymoreish_require_auth( string $redirect_to = '' ) {
	if ( ! bymoreish_is_authenticated() ) {
		$login = home_url( '/bymoreish/login' );
		if ( $redirect_to !== '' ) {
			$login = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login );
		}
		wp_redirect( $login );
		exit;
	}
}

function bymoreish_require_role( array $allowed_roles ) {
	$user = bymoreish_current_user();
	if ( ! in_array( $user['role'] ?? '', $allowed_roles, true ) ) {
		wp_redirect( home_url( '/bymoreish/home' ) );
		exit;
	}
}
