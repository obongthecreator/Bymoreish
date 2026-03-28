<?php
/**
 * Home / Dashboard Page – Bymoreish Inventory
 *
 * Main dashboard after login. Shows KPI metrics and navigation cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Require auth
if ( ! bymoreish_is_authenticated() ) {
	wp_redirect( home_url( '/bymoreish/login' ) );
	exit;
}

$current_user = bymoreish_current_user();
$user_role    = $current_user['role'];
$full_name    = $current_user['full_name'];
$plugin_url   = BYMOREISH_PLUGIN_URL;
$ajax_url     = admin_url( 'admin-ajax.php' );
$logout_url   = home_url( '/bymoreish/login?action=logout' );

$is_admin = in_array( $user_role, [ 'admin', 'superadmin' ], true );

// Nav items definition
$nav_items = [
	[ 'href' => '/bymoreish/home',      'icon' => 'solar:home-linear',                 'label' => 'Dashboard'         ],
	[ 'href' => '/bymoreish/orders',    'icon' => 'solar:cart-large-2-linear',          'label' => 'Orders'            ],
	[ 'href' => '/bymoreish/stock',     'icon' => 'solar:box-linear',                  'label' => 'Stock Management'  ],
	[ 'href' => '/bymoreish/financial', 'icon' => 'solar:dollar-minimalistic-linear',   'label' => 'Financial Summary' ],
	[ 'href' => '/bymoreish/expenses',  'icon' => 'solar:wallet-money-linear',          'label' => 'Expenses'          ],
	[ 'href' => '/bymoreish/products',  'icon' => 'solar:bag-linear',                  'label' => 'Product Summary'   ],
	[ 'href' => '/bymoreish/analytics', 'icon' => 'solar:chart-linear',                'label' => 'Analytics'         ],
	[ 'href' => '/bymoreish/',          'icon' => 'solar:buildings-2-linear',           'label' => 'Multi Branch'      ],
];
if ( $is_admin ) {
	$nav_items[] = [ 'href' => '/bymoreish/admin', 'icon' => 'solar:settings-linear', 'label' => 'Admin Panel' ];
}
$nav_items[] = [ 'href' => '/bymoreish/profile', 'icon' => 'solar:user-circle-linear', 'label' => 'Profile' ];

// Feature cards
$feature_cards = [
	[
		'href'  => '/bymoreish/orders',
		'icon'  => 'solar:cart-large-2-linear',
		'color' => '#EECE55',
		'title' => 'Order Management',
		'desc'  => 'Create, track and manage customer orders in real time',
	],
	[
		'href'  => '/bymoreish/stock',
		'icon'  => 'solar:box-linear',
		'color' => '#4CB050',
		'title' => 'Stock Management',
		'desc'  => 'Monitor inventory levels, set reorder points, log restocks',
	],
	[
		'href'  => '/bymoreish/financial',
		'icon'  => 'solar:dollar-minimalistic-linear',
		'color' => '#EECE55',
		'title' => 'Financial Summary',
		'desc'  => 'View daily revenue, profit margins and financial trends',
	],
	[
		'href'  => '/bymoreish/products',
		'icon'  => 'solar:bag-linear',
		'color' => '#4CB050',
		'title' => 'Product Summary',
		'desc'  => 'Manage menu items, categories, pricing and availability',
	],
	[
		'href'  => '/bymoreish/analytics',
		'icon'  => 'solar:chart-linear',
		'color' => '#EECE55',
		'title' => 'Analytics',
		'desc'  => 'Visualise sales performance, top sellers and business insights',
	],
	[
		'href'  => '/bymoreish/expenses',
		'icon'  => 'solar:wallet-money-linear',
		'color' => '#4CB050',
		'title' => 'Expense Management',
		'desc'  => 'Log operational costs, supplier payments and overhead expenses',
	],
	[
		'href'  => '/bymoreish/',
		'icon'  => 'solar:buildings-2-linear',
		'color' => '#EECE55',
		'title' => 'Multi Branch',
		'desc'  => 'Switch between branch locations and compare performance',
	],
];
if ( $is_admin ) {
	$feature_cards[] = [
		'href'  => '/bymoreish/admin',
		'icon'  => 'solar:settings-linear',
		'color' => '#94a3b8',
		'title' => 'Admin Panel',
		'desc'  => 'Manage users, roles, settings and system configuration',
	];
}
$feature_cards[] = [
	'href'  => '/bymoreish/profile',
	'icon'  => 'solar:user-circle-linear',
	'color' => '#4CB050',
	'title' => 'Profile Management',
	'desc'  => 'Update your credentials, profile info and preferences',
];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard – Bymoreish Inventory</title>

	<!-- Tailwind CSS CDN -->
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			darkMode: 'class',
			theme: {
				extend: {
					colors: {
						gold:  '#EECE55',
						green: '#4CB050',
					},
				},
			},
		};
	</script>

	<!-- Iconify CDN (Solar icons) -->
	<script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>

	<!-- Plugin CSS -->
	<link rel="stylesheet" href="<?php echo esc_url( $plugin_url ); ?>assets/css/style.css">

	<style>
		/* ---------- Base ---------- */
		body { background: #0a0a0f; overflow-x: hidden; }

		/* ---------- Sidebar ---------- */
		#sidebar {
			transition: transform 0.3s cubic-bezier(.4,0,.2,1);
		}
		@media (max-width: 1023px) {
			#sidebar       { transform: translateX(-100%); }
			#sidebar.open  { transform: translateX(0); }
		}

		/* ---------- Sidebar overlay (mobile) ---------- */
		#sidebar-overlay {
			display: none;
		}
		#sidebar-overlay.open {
			display: block;
		}

		/* ---------- Nav item active state ---------- */
		.nav-item.active {
			background: rgba(238,206,85,0.12) !important;
			color: #EECE55 !important;
		}
		.nav-item.active iconify-icon {
			color: #EECE55 !important;
		}

		/* ---------- Glassmorphism ---------- */
		.glass {
			background: rgba(255,255,255,0.06);
			backdrop-filter: blur(16px);
			-webkit-backdrop-filter: blur(16px);
			border: 1px solid rgba(255,255,255,0.10);
			box-shadow: 0 4px 24px rgba(0,0,0,0.3);
		}

		/* ---------- KPI card accent bar ---------- */
		.kpi-bar {
			width: 4px;
			border-radius: 2px;
			background: linear-gradient(180deg, #EECE55, rgba(238,206,85,0.3));
		}
		.kpi-bar.green {
			background: linear-gradient(180deg, #4CB050, rgba(76,176,80,0.3));
		}

		/* ---------- Feature card hover ---------- */
		.feature-card {
			transition: border-color 0.3s, box-shadow 0.3s, transform 0.3s;
			cursor: pointer;
		}
		.feature-card:hover {
			transform: translateY(-3px);
			box-shadow: 0 0 28px rgba(238,206,85,0.2), 0 8px 32px rgba(0,0,0,0.4) !important;
			border-color: rgba(238,206,85,0.35) !important;
		}

		/* ---------- Pill beam ---------- */
		.pill-beam {
			position: relative;
			overflow: hidden;
		}
		.pill-beam::after {
			content: '';
			position: absolute;
			top: 0; left: -100%;
			width: 60%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(238,206,85,0.25), transparent);
			animation: beamSlide 3.5s ease-in-out infinite;
		}
		@keyframes beamSlide {
			0%   { left: -100%; }
			55%  { left: 130%; }
			100% { left: 130%; }
		}

		/* ---------- Fade-in page ---------- */
		@keyframes fadeUp {
			from { opacity: 0; transform: translateY(16px); }
			to   { opacity: 1; transform: translateY(0); }
		}
		.fade-up { animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) forwards; }
		.delay-1 { animation-delay: 0.08s; opacity: 0; }
		.delay-2 { animation-delay: 0.16s; opacity: 0; }
		.delay-3 { animation-delay: 0.24s; opacity: 0; }
		.delay-4 { animation-delay: 0.32s; opacity: 0; }
		.delay-5 { animation-delay: 0.40s; opacity: 0; }

		/* ---------- Hamburger button ---------- */
		#hamburger { display: none; }
		@media (max-width: 1023px) {
			#hamburger { display: flex; }
		}

		/* ---------- Sidebar push on desktop ---------- */
		@media (min-width: 1024px) {
			#main-content { margin-left: 256px; }
		}

		/* ---------- Scrollbar ---------- */
		#sidebar::-webkit-scrollbar { width: 4px; }
		#sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius:2px; }
		#main-content::-webkit-scrollbar { width: 6px; }
		#main-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius:3px; }
		#main-content::-webkit-scrollbar-thumb:hover { background: rgba(238,206,85,0.3); }
	</style>
</head>
<body class="dark min-h-screen text-white antialiased">

<!-- ================================================================
     SIDEBAR OVERLAY (mobile)
     ================================================================ -->
<div id="sidebar-overlay"
     class="fixed inset-0 z-30 lg:hidden"
     style="background:rgba(0,0,0,0.6);backdrop-filter:blur(3px);"
     aria-hidden="true"></div>

<!-- ================================================================
     LEFT SIDEBAR
     ================================================================ -->
<aside id="sidebar"
       class="fixed top-0 left-0 z-40 w-64 h-full flex flex-col overflow-y-auto"
       style="background:#111118;border-right:1px solid rgba(255,255,255,0.08);">

	<!-- Brand -->
	<div class="flex items-center gap-3 px-5 py-5 shrink-0"
	     style="border-bottom:1px solid rgba(255,255,255,0.08);">
		<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
		     style="background:linear-gradient(135deg,#EECE55,#d4b043);">
			<iconify-icon icon="solar:chef-hat-linear" style="font-size:1.25rem;color:#0a0a0f;"></iconify-icon>
		</div>
		<div>
			<p class="font-bold text-white text-sm leading-tight">Bymoreish</p>
			<p class="text-xs" style="color:#EECE55;">Inventory</p>
		</div>
	</div>

	<!-- User pill -->
	<div class="px-4 py-3 shrink-0" style="border-bottom:1px solid rgba(255,255,255,0.06);">
		<div class="pill-beam flex items-center gap-3 px-3 py-2.5 rounded-xl"
		     style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.15);">
			<div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
			     style="background:linear-gradient(135deg,#EECE55,#d4b043);">
				<iconify-icon icon="solar:user-linear" style="font-size:1rem;color:#0a0a0f;"></iconify-icon>
			</div>
			<div class="min-w-0">
				<p class="text-sm font-semibold text-white truncate">
					<?php echo esc_html( $full_name ); ?>
				</p>
				<p class="text-xs capitalize" style="color:#EECE55;">
					<?php echo esc_html( $user_role ); ?>
				</p>
			</div>
		</div>
	</div>

	<!-- Clock pill -->
	<div class="px-4 py-3 shrink-0" style="border-bottom:1px solid rgba(255,255,255,0.06);">
		<div class="pill-beam flex items-center gap-3 px-3 py-2.5 rounded-xl"
		     style="background:rgba(76,176,80,0.08);border:1px solid rgba(76,176,80,0.15);">
			<iconify-icon icon="solar:clock-circle-linear" style="font-size:1.1rem;color:#4CB050;flex-shrink:0;"></iconify-icon>
			<div>
				<p id="sidebar-clock" class="text-sm font-bold tabular-nums" style="color:#4CB050;">00:00:00</p>
				<p id="sidebar-date"  class="text-xs text-gray-400"></p>
			</div>
		</div>
	</div>

	<!-- Nav links -->
	<nav class="flex-1 px-3 py-4 space-y-0.5" aria-label="Main navigation">
		<?php foreach ( $nav_items as $item ) : ?>
			<?php
			$href   = esc_url( home_url( $item['href'] ) );
			$icon   = esc_attr( $item['icon'] );
			$label  = esc_html( $item['label'] );
			$active = ( rtrim( $_SERVER['REQUEST_URI'] ?? '', '/' ) === rtrim( parse_url( home_url( $item['href'] ), PHP_URL_PATH ) ?? '', '/' ) ) ? ' active' : '';
			?>
			<a href="<?php echo $href; ?>"
			   class="nav-item<?php echo $active; ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-white/[0.07] transition-all duration-200">
				<iconify-icon icon="<?php echo $icon; ?>" style="font-size:1.2rem;flex-shrink:0;"></iconify-icon>
				<span><?php echo $label; ?></span>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- Sidebar footer -->
	<div class="px-4 py-4 shrink-0" style="border-top:1px solid rgba(255,255,255,0.08);">
		<div class="flex items-center justify-between gap-2">
			<p class="text-xs text-gray-600 truncate">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Bymoreish</p>
			<a href="<?php echo esc_url( $logout_url ); ?>"
			   class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-400 hover:text-white hover:bg-red-500/20 transition-all shrink-0">
				<iconify-icon icon="solar:logout-linear" style="font-size:1rem;"></iconify-icon>
				Logout
			</a>
		</div>
	</div>
</aside>

<!-- ================================================================
     MAIN CONTENT
     ================================================================ -->
<div id="main-content" class="min-h-screen flex flex-col">

	<!-- ── Top header bar ── -->
	<header class="sticky top-0 z-20 flex items-center justify-between gap-4 px-6 py-4"
	        style="background:rgba(10,10,15,0.85);backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,0.07);">

		<!-- Left: hamburger + title -->
		<div class="flex items-center gap-4 min-w-0">
			<button id="hamburger"
			        type="button"
			        aria-label="Open navigation menu"
			        class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all lg:hidden">
				<iconify-icon icon="solar:hamburger-menu-linear" style="font-size:1.4rem;"></iconify-icon>
			</button>
			<div class="min-w-0">
				<h1 class="text-lg font-bold text-white leading-tight truncate">Dashboard</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">
					Good to see you, <?php echo esc_html( $full_name ); ?>!
				</p>
			</div>
		</div>

		<!-- Right: clock pill -->
		<div class="pill-beam flex items-center gap-2 px-4 py-2 rounded-full shrink-0"
		     style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.18);">
			<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
			<div>
				<p id="header-clock" class="text-sm font-bold tabular-nums" style="color:#EECE55;">00:00:00</p>
				<p id="header-date"  class="text-xs text-gray-500 hidden sm:block"></p>
			</div>
		</div>
	</header>

	<!-- ── Page body ── -->
	<div class="flex-1 px-4 sm:px-6 lg:px-8 py-8 space-y-10">

		<!-- ── KPI row ── -->
		<section aria-labelledby="kpi-heading">
			<h2 id="kpi-heading" class="sr-only">Key Performance Indicators</h2>
			<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 fade-up delay-1">

				<!-- Today's Revenue -->
				<div class="glass rounded-2xl p-5 flex items-start gap-4">
					<div class="kpi-bar self-stretch"></div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Today's Revenue</p>
						<p id="kpi-revenue" class="text-2xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600 mt-0.5">All orders today</p>
					</div>
					<div class="ml-auto shrink-0 w-9 h-9 rounded-xl flex items-center justify-center"
					     style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:dollar-minimalistic-linear" style="font-size:1.2rem;color:#EECE55;"></iconify-icon>
					</div>
				</div>

				<!-- Today's Orders -->
				<div class="glass rounded-2xl p-5 flex items-start gap-4">
					<div class="kpi-bar green self-stretch"></div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Today's Orders</p>
						<p id="kpi-orders" class="text-2xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600 mt-0.5">Total order count</p>
					</div>
					<div class="ml-auto shrink-0 w-9 h-9 rounded-xl flex items-center justify-center"
					     style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:cart-large-2-linear" style="font-size:1.2rem;color:#4CB050;"></iconify-icon>
					</div>
				</div>

				<!-- Low Stock Alert -->
				<div class="glass rounded-2xl p-5 flex items-start gap-4">
					<div class="kpi-bar self-stretch" style="background:linear-gradient(180deg,#ef4444,rgba(239,68,68,0.3));"></div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Low Stock</p>
						<p id="kpi-low-stock" class="text-2xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600 mt-0.5">Items below threshold</p>
					</div>
					<div class="ml-auto shrink-0 w-9 h-9 rounded-xl flex items-center justify-center"
					     style="background:rgba(239,68,68,0.12);">
						<iconify-icon icon="solar:danger-triangle-linear" style="font-size:1.2rem;color:#ef4444;"></iconify-icon>
					</div>
				</div>

				<!-- Pending Orders -->
				<div class="glass rounded-2xl p-5 flex items-start gap-4">
					<div class="kpi-bar self-stretch" style="background:linear-gradient(180deg,#a78bfa,rgba(167,139,250,0.3));"></div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Pending Orders</p>
						<p id="kpi-pending" class="text-2xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600 mt-0.5">Awaiting fulfilment</p>
					</div>
					<div class="ml-auto shrink-0 w-9 h-9 rounded-xl flex items-center justify-center"
					     style="background:rgba(167,139,250,0.12);">
						<iconify-icon icon="solar:hourglass-line-linear" style="font-size:1.2rem;color:#a78bfa;"></iconify-icon>
					</div>
				</div>
			</div>
		</section>

		<!-- ── Feature cards grid ── -->
		<section aria-labelledby="features-heading">
			<div class="flex items-center justify-between mb-5 fade-up delay-2">
				<h2 id="features-heading" class="text-base font-bold text-white">Quick Access</h2>
				<span class="text-xs text-gray-500">
					<?php echo count( $feature_cards ); ?> modules
				</span>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 fade-up delay-3">
				<?php foreach ( $feature_cards as $idx => $card ) : ?>
					<?php
					$card_href  = esc_url( home_url( $card['href'] ) );
					$card_icon  = esc_attr( $card['icon'] );
					$card_color = esc_attr( $card['color'] );
					$card_title = esc_html( $card['title'] );
					$card_desc  = esc_html( $card['desc'] );
					?>
					<a href="<?php echo $card_href; ?>"
					   class="feature-card glass rounded-2xl p-6 flex flex-col gap-4 no-underline group"
					   style="animation-delay:<?php echo number_format( 0.04 * $idx, 2 ); ?>s;">

						<!-- Icon -->
						<div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
						     style="background:<?php echo esc_attr( preg_replace( '/[^a-fA-F0-9#]/', '', $card['color'] ) ); ?>1a;
						            border:1px solid <?php echo esc_attr( preg_replace( '/[^a-fA-F0-9#]/', '', $card['color'] ) ); ?>33;">
							<iconify-icon icon="<?php echo $card_icon; ?>"
							              style="font-size:1.4rem;color:<?php echo $card_color; ?>;"></iconify-icon>
						</div>

						<!-- Text -->
						<div class="flex-1 min-w-0">
							<h3 class="text-sm font-bold text-white mb-1 group-hover:text-[#EECE55] transition-colors">
								<?php echo $card_title; ?>
							</h3>
							<p class="text-xs text-gray-500 leading-relaxed">
								<?php echo $card_desc; ?>
							</p>
						</div>

						<!-- Arrow -->
						<div class="flex items-center justify-end">
							<iconify-icon icon="solar:arrow-right-up-linear"
							              style="font-size:1rem;color:#EECE55;opacity:0.5;"
							              class="group-hover:opacity-100 transition-opacity"></iconify-icon>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	</div>

	<!-- ── Footer ── -->
	<footer class="px-6 py-5 mt-auto fade-up delay-5"
	        style="border-top:1px solid rgba(255,255,255,0.06);">
		<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
			<div class="flex items-center gap-2">
				<div class="w-6 h-6 rounded-lg flex items-center justify-center"
				     style="background:linear-gradient(135deg,#EECE55,#d4b043);">
					<iconify-icon icon="solar:chef-hat-linear" style="font-size:0.75rem;color:#0a0a0f;"></iconify-icon>
				</div>
				<p class="text-xs text-gray-500">Bymoreish Inventory Management System</p>
			</div>
			<p class="text-xs text-gray-600">
				© <?php echo esc_html( gmdate( 'Y' ) ); ?> Bymoreish. All rights reserved.
			</p>
		</div>
	</footer>
</div><!-- /#main-content -->

<!-- Plugin JS -->
<script src="<?php echo esc_url( $plugin_url ); ?>assets/js/main.js" defer></script>

<script>
/* ============================================================
   Config exposed to JS
   ============================================================ */
window.bymConfig = {
	ajaxUrl:     <?php echo wp_json_encode( $ajax_url ); ?>,
	nonce:       '',
	currentUser: {
		id:       <?php echo (int) $current_user['id']; ?>,
		username: <?php echo wp_json_encode( $current_user['username'] ); ?>,
		fullName: <?php echo wp_json_encode( $full_name ); ?>,
		role:     <?php echo wp_json_encode( $user_role ); ?>,
	},
};
const BYM_AJAX_URL = window.bymConfig.ajaxUrl;

/* ============================================================
   Digital clock
   ============================================================ */
function updateClock() {
	const now  = new Date();
	const time = now.toLocaleTimeString('en-NG', { hour12: false, hour:'2-digit', minute:'2-digit', second:'2-digit' });
	const date = now.toLocaleDateString('en-NG', { weekday:'short', day:'numeric', month:'short', year:'numeric' });

	['header-clock', 'sidebar-clock'].forEach(function(id) {
		const el = document.getElementById(id);
		if (el) el.textContent = time;
	});
	['header-date', 'sidebar-date'].forEach(function(id) {
		const el = document.getElementById(id);
		if (el) el.textContent = date;
	});
}
updateClock();
setInterval(updateClock, 1000);

/* ============================================================
   Sidebar toggle (mobile)
   ============================================================ */
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebar-overlay');
const hamburger = document.getElementById('hamburger');

function openSidebar() {
	sidebar.classList.add('open');
	overlay.classList.add('open');
	document.body.style.overflow = 'hidden';
}
function closeSidebar() {
	sidebar.classList.remove('open');
	overlay.classList.remove('open');
	document.body.style.overflow = '';
}

if (hamburger)  hamburger.addEventListener('click', openSidebar);
if (overlay)    overlay.addEventListener('click', closeSidebar);

// Close sidebar on nav link click (mobile)
sidebar.querySelectorAll('a').forEach(function(a) {
	a.addEventListener('click', function() {
		if (window.innerWidth < 1024) closeSidebar();
	});
});

/* ============================================================
   KPI AJAX fetch
   ============================================================ */
async function fetchKPIs() {
	try {
		const body = new URLSearchParams({ action: 'bym_dashboard_kpis' });
		const res  = await fetch(BYM_AJAX_URL, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		});
		if (!res.ok) return;
		const data = await res.json();
		if (!data || !data.success) return;

		const d = data.data;
		setKPI('kpi-revenue',   d.revenue,   formatNaira);
		setKPI('kpi-orders',    d.orders,    function(v) { return Number(v).toLocaleString(); });
		setKPI('kpi-low-stock', d.low_stock, function(v) { return Number(v).toLocaleString(); });
		setKPI('kpi-pending',   d.pending,   function(v) { return Number(v).toLocaleString(); });
	} catch (_) {
		// Silently fail – KPIs are non-critical on load
	}
}

/* ============================================================
   KPI helper – update a single KPI element if value is present
   ============================================================ */
function setKPI(id, value, formatter) {
	if (value === undefined || value === null) return;
	const el = document.getElementById(id);
	if (el) el.textContent = formatter(value);
}

/* ============================================================
   Currency formatter (fallback if main.js not loaded yet)
   ============================================================ */
function formatNaira(amount) {
	const num = parseFloat(String(amount).replace(/[^0-9.]/g, '')) || 0;
	return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

/* ============================================================
   Init
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
	fetchKPIs();
	// Mark active nav item by current path
	const currentPath = window.location.pathname.replace(/\/$/, '');
	document.querySelectorAll('.nav-item').forEach(function(a) {
		const href = a.getAttribute('href');
		if (!href) return;
		try {
			const linkPath = new URL(href, window.location.origin).pathname.replace(/\/$/, '');
			if (linkPath === currentPath) {
				a.classList.add('active');
			}
		} catch (_) {}
	});
});
</script>
</body>
</html>
