<?php
/**
 * Financial Summary Page – Bymoreish Inventory
 *
 * Read-only daily financial overview: sales breakdown by payment method,
 * expenses, net profit/loss, and historical comparison. Data is fetched
 * via AJAX. Supports date-picker navigation and PDF export via print.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! bymoreish_is_authenticated() ) {
	wp_redirect( home_url( '/bymoreish/login' ) );
	exit;
}

$current_user = bymoreish_current_user();
$user_role    = $current_user['role'];
$full_name    = $current_user['full_name'];
$user_id      = $current_user['id'];
$branch_id    = $current_user['branch'];
$plugin_url   = BYMOREISH_PLUGIN_URL;
$ajax_url     = admin_url( 'admin-ajax.php' );
$nonce        = $bymoreish_nonce ?? '';
$logout_url   = home_url( '/bymoreish/login?action=logout&_bym_nonce=' . rawurlencode( (string) ( $_SESSION['bym_nonce'] ?? '' ) ) );

$is_admin = in_array( $user_role, [ 'admin', 'superadmin' ], true );

$nav_items = [
	[ 'href' => '/bymoreish/home',      'icon' => 'solar:home-linear',               'label' => 'Dashboard'         ],
	[ 'href' => '/bymoreish/orders',    'icon' => 'solar:cart-large-2-linear',        'label' => 'Orders'            ],
	[ 'href' => '/bymoreish/stock',     'icon' => 'solar:box-linear',                'label' => 'Stock Management'  ],
	[ 'href' => '/bymoreish/financial', 'icon' => 'solar:dollar-minimalistic-linear', 'label' => 'Financial Summary' ],
	[ 'href' => '/bymoreish/expenses',  'icon' => 'solar:wallet-money-linear',        'label' => 'Expenses'          ],
	[ 'href' => '/bymoreish/products',  'icon' => 'solar:bag-linear',                'label' => 'Product Summary'   ],
	[ 'href' => '/bymoreish/analytics', 'icon' => 'solar:chart-linear',              'label' => 'Analytics'         ],
	[ 'href' => '/bymoreish/',          'icon' => 'solar:buildings-2-linear',         'label' => 'Multi Branch'      ],
];
if ( $is_admin ) {
	$nav_items[] = [ 'href' => '/bymoreish/admin', 'icon' => 'solar:settings-linear', 'label' => 'Admin Panel' ];
}
$nav_items[] = [ 'href' => '/bymoreish/profile', 'icon' => 'solar:user-circle-linear', 'label' => 'Profile' ];

$today = current_time( 'Y-m-d' );
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Financial Summary – Bymoreish Inventory</title>

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
		body { background: #0a0a0f; overflow-x: hidden; }

		/* ── Sidebar ── */
		#sidebar { transition: transform 0.3s cubic-bezier(.4,0,.2,1); }
		@media (max-width: 1023px) {
			#sidebar       { transform: translateX(-100%); }
			#sidebar.open  { transform: translateX(0); }
		}
		#sidebar-overlay { display: none; }
		#sidebar-overlay.open { display: block; }

		/* ── Nav active ── */
		.nav-item.active { background: rgba(238,206,85,0.12) !important; color: #EECE55 !important; }
		.nav-item.active iconify-icon { color: #EECE55 !important; }

		/* ── Glassmorphism ── */
		.glass {
			background: rgba(255,255,255,0.06);
			backdrop-filter: blur(16px);
			-webkit-backdrop-filter: blur(16px);
			border: 1px solid rgba(255,255,255,0.10);
			box-shadow: 0 4px 24px rgba(0,0,0,0.3);
		}

		/* ── Pill beam ── */
		.pill-beam { position: relative; overflow: hidden; }
		.pill-beam::after {
			content: '';
			position: absolute;
			top: 0; left: -100%;
			width: 60%; height: 100%;
			background: linear-gradient(90deg, transparent, rgba(238,206,85,0.25), transparent);
			animation: beamSlide 3.5s ease-in-out infinite;
		}
		@keyframes beamSlide {
			0%   { left: -100%; }
			55%  { left: 130%; }
			100% { left: 130%; }
		}

		/* ── Layout ── */
		#hamburger { display: none; }
		@media (max-width: 1023px) { #hamburger { display: flex; } }
		@media (min-width: 1024px) { #main-content { margin-left: 256px; } }

		/* ── Animations ── */
		@keyframes fadeUp {
			from { opacity: 0; transform: translateY(16px); }
			to   { opacity: 1; transform: translateY(0); }
		}
		.fade-up { animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) forwards; }
		.delay-1 { animation-delay: 0.08s; opacity: 0; }
		.delay-2 { animation-delay: 0.16s; opacity: 0; }
		.delay-3 { animation-delay: 0.24s; opacity: 0; }
		.delay-4 { animation-delay: 0.32s; opacity: 0; }

		/* ── Financial cards ── */
		.fin-card {
			background: rgba(255,255,255,0.05);
			border: 1px solid rgba(255,255,255,0.09);
			border-radius: 1.25rem;
			padding: 1.5rem;
			transition: border-color 0.25s, box-shadow 0.25s, transform 0.25s;
			position: relative;
			overflow: hidden;
		}
		.fin-card::before {
			content: '';
			position: absolute;
			top: 0; left: 0; right: 0;
			height: 3px;
			border-radius: 1.25rem 1.25rem 0 0;
		}
		.fin-card.gold::before  { background: linear-gradient(90deg, #EECE55, rgba(238,206,85,0.3)); }
		.fin-card.green::before { background: linear-gradient(90deg, #4CB050, rgba(76,176,80,0.3)); }
		.fin-card.blue::before  { background: linear-gradient(90deg, #6366f1, rgba(99,102,241,0.3)); }
		.fin-card.orange::before{ background: linear-gradient(90deg, #f97316, rgba(249,115,22,0.3)); }
		.fin-card.red::before   { background: linear-gradient(90deg, #ef4444, rgba(239,68,68,0.3)); }
		.fin-card.purple::before{ background: linear-gradient(90deg, #a855f7, rgba(168,85,247,0.3)); }
		.fin-card:hover {
			transform: translateY(-3px);
			box-shadow: 0 12px 32px rgba(0,0,0,0.35);
		}

		/* ── Profit indicator ── */
		.profit-positive { color: #4CB050; }
		.profit-negative { color: #ef4444; }
		.profit-neutral  { color: #94a3b8; }

		/* ── Comparison badge ── */
		.compare-up   { background: rgba(76,176,80,0.12);  color: #4CB050; border: 1px solid rgba(76,176,80,0.25);  }
		.compare-down { background: rgba(239,68,68,0.12);  color: #ef4444; border: 1px solid rgba(239,68,68,0.25);  }
		.compare-flat { background: rgba(148,163,184,0.1); color: #94a3b8; border: 1px solid rgba(148,163,184,0.2); }

		/* ── Date input ── */
		.bym-date {
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.12);
			border-radius: 0.65rem;
			color: #fff;
			padding: 0.5rem 0.85rem;
			font-size: 0.875rem;
			outline: none;
			transition: border-color 0.2s, box-shadow 0.2s;
			color-scheme: dark;
		}
		.bym-date:focus {
			border-color: rgba(238,206,85,0.5);
			box-shadow: 0 0 0 3px rgba(238,206,85,0.1);
		}

		/* ── History table ── */
		.hist-table th {
			background: rgba(238,206,85,0.07);
			color: #EECE55;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0.75rem 1rem;
		}
		.hist-table td {
			padding: 0.65rem 1rem;
			border-bottom: 1px solid rgba(255,255,255,0.05);
			font-size: 0.85rem;
			vertical-align: middle;
		}
		.hist-table tr:last-child td { border-bottom: none; }
		.hist-table tr:hover td { background: rgba(255,255,255,0.03); }

		/* ── Print styles ── */
		@media print {
			#sidebar, #hamburger, #sidebar-overlay,
			.no-print, header { display: none !important; }
			#main-content { margin-left: 0 !important; }
			body { background: #fff !important; color: #000 !important; }
			.glass, .fin-card { background: #f9f9f9 !important; border-color: #ddd !important; box-shadow: none !important; }
			.fin-card::before { display: none !important; }
			* { color: #000 !important; }
			.profit-positive { color: #15803d !important; }
			.profit-negative { color: #dc2626 !important; }
		}

		/* ── Scrollbar ── */
		#sidebar::-webkit-scrollbar { width: 4px; }
		#sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
		#main-content::-webkit-scrollbar { width: 6px; }
		#main-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 3px; }
		#main-content::-webkit-scrollbar-thumb:hover { background: rgba(238,206,85,0.3); }
	</style>
</head>
<body class="dark min-h-screen text-white antialiased">

<!-- ── Sidebar overlay (mobile) ── -->
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
				<p class="text-sm font-semibold text-white truncate"><?php echo esc_html( $full_name ); ?></p>
				<p class="text-xs capitalize" style="color:#EECE55;"><?php echo esc_html( $user_role ); ?></p>
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

	<!-- Footer -->
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

	<!-- Top header -->
	<header class="sticky top-0 z-20 flex items-center justify-between gap-4 px-6 py-4"
	        style="background:rgba(10,10,15,0.85);backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,0.07);">
		<div class="flex items-center gap-4 min-w-0">
			<button id="hamburger" type="button" aria-label="Open navigation menu"
			        class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all lg:hidden">
				<iconify-icon icon="solar:hamburger-menu-linear" style="font-size:1.4rem;"></iconify-icon>
			</button>
			<div class="min-w-0">
				<h1 class="text-lg font-bold text-white leading-tight truncate">Financial Summary</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">Daily revenue, profit and expense overview</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/financial' ) ); ?>"
			   class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all no-print"
			   style="background:rgba(238,206,85,0.1);border:1px solid rgba(238,206,85,0.2);color:#EECE55;">
				<iconify-icon icon="solar:history-linear" style="font-size:1rem;"></iconify-icon>
				View Financial History →
			</a>
			<div class="pill-beam flex items-center gap-2 px-4 py-2 rounded-full shrink-0"
			     style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.18);">
				<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
				<div>
					<p id="header-clock" class="text-sm font-bold tabular-nums" style="color:#EECE55;">00:00:00</p>
					<p id="header-date"  class="text-xs text-gray-500 hidden sm:block"></p>
				</div>
			</div>
		</div>
	</header>

	<!-- Page body -->
	<div class="flex-1 px-4 sm:px-6 lg:px-8 py-8 space-y-8">

		<!-- ── Date picker + controls ── -->
		<section class="fade-up delay-1">
			<div class="glass rounded-2xl p-5">
				<div class="flex flex-wrap items-center justify-between gap-4">
					<div class="flex flex-wrap items-center gap-4">
						<!-- Date picker -->
						<div class="flex items-center gap-3">
							<div class="w-8 h-8 rounded-lg flex items-center justify-center"
							     style="background:rgba(238,206,85,0.12);">
								<iconify-icon icon="solar:calendar-linear" style="color:#EECE55;font-size:1rem;"></iconify-icon>
							</div>
							<div class="space-y-0.5">
								<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Select Date</p>
								<input type="date" id="financial-date" value="<?php echo esc_attr( $today ); ?>"
								       max="<?php echo esc_attr( $today ); ?>"
								       class="bym-date">
							</div>
						</div>

						<!-- Load button -->
						<button id="load-financial" type="button"
						        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all hover:scale-105 active:scale-95 no-print"
						        style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
							<iconify-icon icon="solar:refresh-linear" style="font-size:1rem;"></iconify-icon>
							Load Summary
						</button>
					</div>

					<!-- Export + history links -->
					<div class="flex items-center gap-3 no-print">
						<a href="<?php echo esc_url( home_url( '/bymoreish/history/financial' ) ); ?>"
						   class="sm:hidden inline-flex items-center gap-2 text-sm font-semibold" style="color:#EECE55;">
							<iconify-icon icon="solar:history-linear"></iconify-icon>
							History →
						</a>
						<button id="export-pdf" type="button"
						        class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all hover:scale-105 active:scale-95"
						        style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);color:#818cf8;">
							<iconify-icon icon="solar:file-download-linear" style="font-size:1rem;"></iconify-icon>
							Export PDF
						</button>
					</div>
				</div>

				<!-- Loading state -->
				<div id="financial-loading" class="hidden mt-4 flex items-center gap-2 text-xs text-gray-400">
					<iconify-icon icon="solar:refresh-linear" class="animate-spin"></iconify-icon>
					Fetching financial data…
				</div>

				<!-- Error state -->
				<div id="financial-error" class="hidden mt-4 rounded-xl px-4 py-3 text-xs font-medium text-red-300"
				     style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);"></div>
			</div>
		</section>

		<!-- ── Main financial cards ── -->
		<section class="fade-up delay-2" id="financial-summary-section" aria-labelledby="fin-summary-heading">
			<div class="flex items-center justify-between mb-4">
				<h2 id="fin-summary-heading" class="text-sm font-bold text-white flex items-center gap-2">
					<iconify-icon icon="solar:wallet-money-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
					Today's Financial Summary
				</h2>
				<p id="summary-date-label" class="text-xs text-gray-500"></p>
			</div>

			<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

				<!-- Total Sales -->
				<div class="fin-card gold">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:dollar-minimalistic-linear" style="color:#EECE55;font-size:1.3rem;"></iconify-icon>
						</div>
						<span id="badge-total-sales" class="text-xs px-2 py-0.5 rounded-full compare-flat">—</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Sales</p>
					<p id="fin-total-sales" class="text-3xl font-extrabold text-white tabular-nums">₦—</p>
					<p class="text-xs text-gray-600 mt-1">Transfer + Card + Cash</p>
				</div>

				<!-- Transfer Sales -->
				<div class="fin-card blue">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(99,102,241,0.12);">
							<iconify-icon icon="solar:transfer-horizontal-linear" style="color:#818cf8;font-size:1.3rem;"></iconify-icon>
						</div>
						<span id="badge-transfer" class="text-xs px-2 py-0.5 rounded-full compare-flat">—</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Transfer Sales</p>
					<p id="fin-transfer" class="text-3xl font-extrabold text-white tabular-nums">₦—</p>
					<p class="text-xs text-gray-600 mt-1">Bank transfer payments</p>
				</div>

				<!-- Card (POS) Sales -->
				<div class="fin-card orange">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(249,115,22,0.12);">
							<iconify-icon icon="solar:card-2-linear" style="color:#f97316;font-size:1.3rem;"></iconify-icon>
						</div>
						<span id="badge-card" class="text-xs px-2 py-0.5 rounded-full compare-flat">—</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Card Sales (POS)</p>
					<p id="fin-card" class="text-3xl font-extrabold text-white tabular-nums">₦—</p>
					<p class="text-xs text-gray-600 mt-1">Point-of-sale card payments</p>
				</div>

				<!-- Cash Sales -->
				<div class="fin-card green">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(76,176,80,0.12);">
							<iconify-icon icon="solar:banknote-linear" style="color:#4CB050;font-size:1.3rem;"></iconify-icon>
						</div>
						<span id="badge-cash" class="text-xs px-2 py-0.5 rounded-full compare-flat">—</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Cash Sales</p>
					<p id="fin-cash" class="text-3xl font-extrabold text-white tabular-nums">₦—</p>
					<p class="text-xs text-gray-600 mt-1">Cash payments received</p>
				</div>

				<!-- Total Expenses -->
				<div class="fin-card red">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(239,68,68,0.12);">
							<iconify-icon icon="solar:wallet-money-linear" style="color:#ef4444;font-size:1.3rem;"></iconify-icon>
						</div>
						<span id="badge-expenses" class="text-xs px-2 py-0.5 rounded-full compare-flat">—</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Expenses</p>
					<p id="fin-expenses" class="text-3xl font-extrabold text-white tabular-nums">₦—</p>
					<p class="text-xs text-gray-600 mt-1">From expense management</p>
				</div>

				<!-- Net Profit -->
				<div class="fin-card purple">
					<div class="flex items-start justify-between mb-3">
						<div class="w-10 h-10 rounded-xl flex items-center justify-center"
						     style="background:rgba(168,85,247,0.12);">
							<iconify-icon icon="solar:graph-new-up-linear" style="color:#a855f7;font-size:1.3rem;"></iconify-icon>
						</div>
						<!-- Profit indicator icon -->
						<span id="profit-indicator-icon" class="w-8 h-8 rounded-full flex items-center justify-center text-lg"
						      style="background:rgba(148,163,184,0.1);">
							<iconify-icon icon="solar:minus-circle-linear" style="color:#94a3b8;font-size:1.1rem;"></iconify-icon>
						</span>
					</div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Net Profit</p>
					<p id="fin-net-profit" class="text-3xl font-extrabold tabular-nums profit-neutral">₦—</p>
					<p id="fin-profit-note" class="text-xs text-gray-600 mt-1">Total sales − Total expenses</p>
				</div>

			</div>
		</section>

		<!-- ── Historical comparison ── -->
		<section class="fade-up delay-3" id="historical-section">
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:chart-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Historical Comparison</h2>
					</div>
					<p class="text-xs text-gray-500">vs previous periods</p>
				</div>

				<div class="overflow-x-auto">
					<table class="hist-table w-full text-white">
						<thead>
							<tr>
								<th>Period</th>
								<th class="text-right">Total Sales</th>
								<th class="text-right">Expenses</th>
								<th class="text-right">Net Profit</th>
								<th class="text-center">vs Selected</th>
							</tr>
						</thead>
						<tbody id="historical-tbody">
							<tr>
								<td colspan="5" class="text-center py-8 text-gray-500 text-xs">
									<iconify-icon icon="solar:refresh-linear" class="animate-spin text-xl block mx-auto mb-2"></iconify-icon>
									Load a date above to see historical comparison
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

		<!-- ── Save + export actions ── -->
		<section class="fade-up delay-4 no-print">
			<div class="glass rounded-2xl p-6">
				<div class="flex flex-wrap items-center justify-between gap-4">
					<div>
						<h2 class="text-sm font-bold text-white mb-1 flex items-center gap-2">
							<iconify-icon icon="solar:diskette-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
							Save Financial Summary
						</h2>
						<p class="text-xs text-gray-500">
							Save today's financial record to the database for reporting and auditing.
						</p>
					</div>
					<div class="flex items-center gap-3">
						<!-- Status message -->
						<div id="fin-save-status" class="hidden text-xs font-medium px-3 py-2 rounded-lg"></div>

						<!-- Save button -->
						<button id="save-financial" type="button"
						        class="flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold transition-all hover:scale-105 active:scale-95"
						        style="background:linear-gradient(135deg,#4CB050,#3a9040);color:#fff;">
							<iconify-icon icon="solar:diskette-linear" style="font-size:1.1rem;"></iconify-icon>
							Save Summary
						</button>

						<!-- Export PDF -->
						<button id="export-pdf-bottom" type="button"
						        class="flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold transition-all hover:scale-105 active:scale-95"
						        style="background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);color:#818cf8;">
							<iconify-icon icon="solar:printer-minimalistic-linear" style="font-size:1.1rem;"></iconify-icon>
							Export PDF
						</button>
					</div>
				</div>
			</div>
		</section>

	</div><!-- /.page body -->
</div><!-- /#main-content -->

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
	/* ── Naira formatter utility ── */
	window.formatNaira = function (amount) {
		const n = parseFloat(amount) || 0;
		return '₦' + n.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	};

	/* ── bymConfig global ── */
	window.bymConfig = {
		ajaxUrl:   <?php echo wp_json_encode( $ajax_url ); ?>,
		nonce:     <?php echo wp_json_encode( $nonce ); ?>,
		userId:    <?php echo (int) $user_id; ?>,
		userRole:  <?php echo wp_json_encode( $user_role ); ?>,
		branchId:  <?php echo wp_json_encode( $branch_id ); ?>,
		pluginUrl: <?php echo wp_json_encode( $plugin_url ); ?>,
		today:     <?php echo wp_json_encode( $today ); ?>,
	};

	/* ── Clock ── */
	(function () {
		const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
		const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
		function tick() {
			const now = new Date();
			const h = String(now.getHours()).padStart(2,'0');
			const m = String(now.getMinutes()).padStart(2,'0');
			const s = String(now.getSeconds()).padStart(2,'0');
			const t = `${h}:${m}:${s}`;
			const d = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
			document.querySelectorAll('#sidebar-clock,#header-clock').forEach(el => el && (el.textContent = t));
			document.querySelectorAll('#sidebar-date,#header-date').forEach(el => el && (el.textContent = d));
		}
		tick();
		setInterval(tick, 1000);
	})();

	/* ── Hamburger sidebar toggle ── */
	(function () {
		const hamburger = document.getElementById('hamburger');
		const sidebar   = document.getElementById('sidebar');
		const overlay   = document.getElementById('sidebar-overlay');
		function open()  { sidebar.classList.add('open'); overlay.classList.add('open'); }
		function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); }
		hamburger && hamburger.addEventListener('click', open);
		overlay   && overlay.addEventListener('click', close);
	})();

	/* ── Export PDF (print) ── */
	(function () {
		function doExport() { window.print(); }
		document.getElementById('export-pdf')        && document.getElementById('export-pdf').addEventListener('click', doExport);
		document.getElementById('export-pdf-bottom') && document.getElementById('export-pdf-bottom').addEventListener('click', doExport);
	})();

	/* ── Profit indicator helper ── */
	window.updateProfitIndicator = function (profit) {
		const el   = document.getElementById('fin-net-profit');
		const icon = document.getElementById('profit-indicator-icon');
		const note = document.getElementById('fin-profit-note');
		if (!el) return;
		el.textContent = window.formatNaira(profit);
		if (profit > 0) {
			el.className = 'text-3xl font-extrabold tabular-nums profit-positive';
			if (icon) icon.innerHTML = '<iconify-icon icon="solar:arrow-up-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>';
			if (icon) icon.style.background = 'rgba(76,176,80,0.12)';
			if (note) note.textContent = '🎉 Profitable day!';
		} else if (profit < 0) {
			el.className = 'text-3xl font-extrabold tabular-nums profit-negative';
			if (icon) icon.innerHTML = '<iconify-icon icon="solar:arrow-down-linear" style="color:#ef4444;font-size:1.1rem;"></iconify-icon>';
			if (icon) icon.style.background = 'rgba(239,68,68,0.12)';
			if (note) note.textContent = '⚠️ Operating at a loss';
		} else {
			el.className = 'text-3xl font-extrabold tabular-nums profit-neutral';
			if (icon) icon.innerHTML = '<iconify-icon icon="solar:minus-circle-linear" style="color:#94a3b8;font-size:1.1rem;"></iconify-icon>';
			if (icon) icon.style.background = 'rgba(148,163,184,0.1)';
			if (note) note.textContent = 'Break even';
		}
		/* Trigger Iconify re-scan for dynamically inserted icons */
		if (window.Iconify) Iconify.scan();
	};

	/* ── Financial data loader ── */
	async function loadFinancialData() {
		const dateInput  = document.getElementById('financial-date');
		const loadingEl  = document.getElementById('financial-loading');
		const errorEl    = document.getElementById('financial-error');
		const selectedDate = dateInput ? dateInput.value : window.bymConfig.today;

		if (loadingEl) loadingEl.classList.remove('hidden');
		if (errorEl)   errorEl.classList.add('hidden');

		try {
			const body = new URLSearchParams({
				action:    'bym_get_financial_summary',
				nonce:     window.bymConfig.nonce,
				branch_id: window.bymConfig.branchId,
				date_from: selectedDate,
				date_to:   selectedDate,
			});

			const res  = await fetch(window.bymConfig.ajaxUrl, {
				method:      'POST',
				credentials: 'same-origin',
				headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:        body.toString(),
			});
			const json = await res.json();

			if (!json || !json.success) {
				throw new Error(json?.data?.message || 'Failed to load financial data.');
			}

			const d      = json.data || {};
			const totals = d.totals || {};

			// If no rows exist, compute from orders directly.
			if ((!d.rows || d.rows.length === 0) && totals.total_sales === 0) {
				await loadFinancialFromOrders(selectedDate);
				return;
			}

			displayFinancialData(totals, selectedDate);
		} catch (err) {
			if (errorEl) {
				errorEl.textContent = err.message || 'Failed to load data.';
				errorEl.classList.remove('hidden');
			}
		} finally {
			if (loadingEl) loadingEl.classList.add('hidden');
		}
	}

	/**
	 * Fallback: compute financial figures directly from delivered orders
	 * and expenses when no bym_financial_summary row exists yet.
	 */
	async function loadFinancialFromOrders(selectedDate) {
		try {
			// Fetch orders for the date.
			const ordersBody = new URLSearchParams({
				action:    'bym_get_orders',
				nonce:     window.bymConfig.nonce,
				branch_id: window.bymConfig.branchId,
				date_from: selectedDate,
				date_to:   selectedDate,
			});
			const ordersRes  = await fetch(window.bymConfig.ajaxUrl, {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: ordersBody.toString(),
			});
			const ordersJson = await ordersRes.json();
			const orders     = (ordersJson?.data?.orders || ordersJson?.data || []);

			let total_sales    = 0;
			let transfer_sales = 0;
			let card_sales     = 0;
			let cash_sales     = 0;

			(Array.isArray(orders) ? orders : []).forEach(o => {
				if (o.status === 'delivered') {
					total_sales    += parseFloat(o.grand_total)      || 0;
					transfer_sales += parseFloat(o.transfer_amount)  || 0;
					card_sales     += parseFloat(o.card_amount)      || 0;
					cash_sales     += parseFloat(o.cash_amount)      || 0;
				}
			});

			// Fetch expenses for the date.
			const expBody = new URLSearchParams({
				action:    'bym_get_expenses',
				nonce:     window.bymConfig.nonce,
				branch_id: window.bymConfig.branchId,
				date_from: selectedDate,
				date_to:   selectedDate,
			});
			const expRes  = await fetch(window.bymConfig.ajaxUrl, {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: expBody.toString(),
			});
			const expJson = await expRes.json();
			const expenses = expJson?.data?.expenses || expJson?.data || [];
			let total_expenses = 0;
			(Array.isArray(expenses) ? expenses : []).forEach(e => {
				total_expenses += parseFloat(e.grand_total) || 0;
			});

			displayFinancialData({
				total_sales,
				transfer_sales,
				card_sales,
				cash_sales,
				total_expenses,
				profit: total_sales - total_expenses,
			}, selectedDate);
		} catch (_) {
			// Silently fail
		}
	}

	function displayFinancialData(totals, selectedDate) {
		const set = (id, val) => {
			const el = document.getElementById(id);
			if (el) el.textContent = window.formatNaira(val);
		};

		set('fin-total-sales', totals.total_sales    || 0);
		set('fin-transfer',    totals.transfer_sales  || 0);
		set('fin-card',        totals.card_sales      || 0);
		set('fin-cash',        totals.cash_sales      || 0);
		set('fin-expenses',    totals.total_expenses  || 0);

		const profit = parseFloat(totals.profit) || ((parseFloat(totals.total_sales) || 0) - (parseFloat(totals.total_expenses) || 0));
		window.updateProfitIndicator(profit);

		// Update date label.
		const label = document.getElementById('summary-date-label');
		if (label) label.textContent = selectedDate;

		// Store values for save button.
		window._currentFinancialData = totals;
	}

	/* ── Save financial summary ── */
	async function saveFinancialSummary() {
		const totals = window._currentFinancialData;
		if (!totals) {
			alert('No financial data loaded. Load a date first.');
			return;
		}

		const statusEl = document.getElementById('fin-save-status');
		try {
			const dateInput  = document.getElementById('financial-date');
			const selectedDate = dateInput ? dateInput.value : window.bymConfig.today;

			const body = new URLSearchParams({
				action:          'bym_save_financial_summary',
				nonce:           window.bymConfig.nonce,
				branch_id:       window.bymConfig.branchId,
				summary_date:    selectedDate,
				total_sales:     totals.total_sales    || 0,
				transfer_sales:  totals.transfer_sales || 0,
				card_sales:      totals.card_sales     || 0,
				cash_sales:      totals.cash_sales     || 0,
				total_expenses:  totals.total_expenses || 0,
			});

			const res  = await fetch(window.bymConfig.ajaxUrl, {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			});
			const json = await res.json();

			if (statusEl) {
				statusEl.classList.remove('hidden');
				if (json?.success) {
					statusEl.textContent = '✓ Saved successfully';
					statusEl.style.color = '#4CB050';
					statusEl.style.background = 'rgba(76,176,80,0.1)';
				} else {
					statusEl.textContent = '✗ ' + (json?.data?.message || 'Save failed');
					statusEl.style.color = '#ef4444';
					statusEl.style.background = 'rgba(239,68,68,0.1)';
				}
				setTimeout(() => statusEl.classList.add('hidden'), 3000);
			}
		} catch (err) {
			if (statusEl) {
				statusEl.classList.remove('hidden');
				statusEl.textContent = '✗ Network error';
				statusEl.style.color = '#ef4444';
				statusEl.style.background = 'rgba(239,68,68,0.1)';
				setTimeout(() => statusEl.classList.add('hidden'), 3000);
			}
		}
	}

	/* ── Bind events ── */
	document.addEventListener('DOMContentLoaded', function () {
		// Load on page load.
		loadFinancialData();

		// Load button.
		const loadBtn = document.getElementById('load-financial');
		if (loadBtn) loadBtn.addEventListener('click', loadFinancialData);

		// Save button.
		const saveBtn = document.getElementById('save-financial');
		if (saveBtn) saveBtn.addEventListener('click', saveFinancialSummary);
	});
</script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>
</body>
</html>
