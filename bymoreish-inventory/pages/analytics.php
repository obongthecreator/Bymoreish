<?php
/**
 * Analytics Page – Bymoreish Inventory
 *
 * Full analytics dashboard with Chart.js charts, KPIs, tables and AI insights.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! isset( $bymoreish_current_user ) ) {
	wp_redirect( home_url( '/bymoreish/login' ) );
	exit;
}

$current_user  = $bymoreish_current_user;
$user_role     = $current_user['role'];
$full_name     = $current_user['full_name'];
$user_id       = $current_user['id'];
$branch_id     = $current_user['branch'];
$nonce         = $bymoreish_nonce ?? '';
$logout_url    = home_url( '/bymoreish/login?action=logout&_bym_nonce=' . rawurlencode( $nonce ) );
$is_admin      = in_array( $user_role, [ 'admin', 'superadmin' ], true );

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
	$nav_items[] = [ 'href' => '/bymoreish/admin',   'icon' => 'solar:settings-linear',    'label' => 'Admin Panel' ];
}
$nav_items[] = [ 'href' => '/bymoreish/profile', 'icon' => 'solar:user-circle-linear', 'label' => 'Profile' ];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Analytics – Bymoreish Inventory</title>

	<script src="https://cdn.tailwindcss.com"></script>
	<script>
		tailwind.config = {
			darkMode: 'class',
			theme: { extend: { colors: { gold: '#EECE55', green: '#4CB050' } } },
		};
	</script>
	<script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
	<link rel="stylesheet" href="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/css/style.css">

	<style>
		body { background: #0a0a0f; overflow-x: hidden; }
		#sidebar { transition: transform 0.3s cubic-bezier(.4,0,.2,1); }
		@media (max-width: 1023px) { #sidebar { transform: translateX(-100%); } #sidebar.open { transform: translateX(0); } }
		#sidebar-overlay { display: none; } #sidebar-overlay.open { display: block; }
		.nav-item.active { background: rgba(238,206,85,0.12) !important; color: #EECE55 !important; }
		.nav-item.active iconify-icon { color: #EECE55 !important; }
		.glass { background: rgba(255,255,255,0.06); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.10); box-shadow: 0 4px 24px rgba(0,0,0,0.3); }
		.pill-beam { position: relative; overflow: hidden; }
		.pill-beam::after { content: ''; position: absolute; top: 0; left: -100%; width: 60%; height: 100%; background: linear-gradient(90deg, transparent, rgba(238,206,85,0.25), transparent); animation: beamSlide 3.5s ease-in-out infinite; }
		@keyframes beamSlide { 0% { left: -100%; } 55% { left: 130%; } 100% { left: 130%; } }
		#hamburger { display: none; }
		@media (max-width: 1023px) { #hamburger { display: flex; } }
		@media (min-width: 1024px) { #main-content { margin-left: 256px; } }
		@keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
		.fade-up { animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) forwards; }
		.delay-1 { animation-delay:0.08s;opacity:0; } .delay-2 { animation-delay:0.16s;opacity:0; } .delay-3 { animation-delay:0.24s;opacity:0; } .delay-4 { animation-delay:0.32s;opacity:0; } .delay-5 { animation-delay:0.40s;opacity:0; }
		.bym-input { background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:.5rem;color:#fff;padding:.45rem .7rem;font-size:.83rem;outline:none;transition:border-color .2s;width:100%; }
		.bym-input:focus { border-color:rgba(238,206,85,.5);box-shadow:0 0 0 3px rgba(238,206,85,.1); }
		/* Filter tabs */
		.filter-tab { padding:.45rem 1rem;border-radius:999px;font-size:.78rem;font-weight:700;cursor:pointer;transition:all .2s;color:#9ca3af;background:transparent;border:1px solid transparent; }
		.filter-tab.active { color:#0a0a0f;background:linear-gradient(135deg,#EECE55,#d4b043);border-color:#EECE55; }
		.filter-tab:not(.active):hover { color:#fff;background:rgba(255,255,255,.08); }
		/* KPI */
		.kpi-card { transition:transform .2s,box-shadow .2s; }
		.kpi-card:hover { transform:translateY(-2px);box-shadow:0 0 24px rgba(238,206,85,.15); }
		/* Table */
		.ana-table th { background:rgba(238,206,85,.07);color:#EECE55;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:.65rem 1rem;white-space:nowrap; }
		.ana-table td { padding:.6rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);vertical-align:middle; }
		.ana-table tr:last-child td { border-bottom:none; }
		.ana-table tr:hover td { background:rgba(255,255,255,.03); }
		/* Chart containers */
		.chart-box { position:relative;min-height:200px; }
		/* AI insight card */
		.ai-card { background:rgba(238,206,85,.05);border:1px solid rgba(238,206,85,.15);border-radius:1rem;padding:1rem 1.2rem;display:flex;gap:.75rem;align-items:flex-start; }
		.ai-icon { width:2rem;height:2rem;border-radius:.6rem;background:rgba(238,206,85,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0; }
		#sidebar::-webkit-scrollbar { width:4px; } #sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1);border-radius:2px; }
		#main-content::-webkit-scrollbar { width:6px; } #main-content::-webkit-scrollbar-thumb { background:rgba(255,255,255,.12);border-radius:3px; }
		/* Custom date range */
		#custom-range { display:none; }
		#custom-range.open { display:flex; }
	</style>
</head>
<body class="dark min-h-screen text-white antialiased">

<div id="sidebar-overlay" class="fixed inset-0 z-30 lg:hidden" style="background:rgba(0,0,0,0.6);backdrop-filter:blur(3px);" aria-hidden="true"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="fixed top-0 left-0 z-40 w-64 h-full flex flex-col overflow-y-auto" style="background:#111118;border-right:1px solid rgba(255,255,255,0.08);">
	<div class="flex items-center gap-3 px-5 py-5 shrink-0" style="border-bottom:1px solid rgba(255,255,255,0.08);">
		<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,#EECE55,#d4b043);">
			<iconify-icon icon="solar:chef-hat-linear" style="font-size:1.25rem;color:#0a0a0f;"></iconify-icon>
		</div>
		<div><p class="font-bold text-white text-sm leading-tight">Bymoreish</p><p class="text-xs" style="color:#EECE55;">Inventory</p></div>
	</div>
	<div class="px-4 py-3 shrink-0" style="border-bottom:1px solid rgba(255,255,255,0.06);">
		<div class="pill-beam flex items-center gap-3 px-3 py-2.5 rounded-xl" style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.15);">
			<div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0" style="background:linear-gradient(135deg,#EECE55,#d4b043);">
				<iconify-icon icon="solar:user-linear" style="font-size:1rem;color:#0a0a0f;"></iconify-icon>
			</div>
			<div class="min-w-0">
				<p class="text-sm font-semibold text-white truncate"><?php echo esc_html( $full_name ); ?></p>
				<p class="text-xs capitalize" style="color:#EECE55;"><?php echo esc_html( $user_role ); ?></p>
			</div>
		</div>
	</div>
	<div class="px-4 py-3 shrink-0" style="border-bottom:1px solid rgba(255,255,255,0.06);">
		<div class="pill-beam flex items-center gap-3 px-3 py-2.5 rounded-xl" style="background:rgba(76,176,80,0.08);border:1px solid rgba(76,176,80,0.15);">
			<iconify-icon icon="solar:clock-circle-linear" style="font-size:1.1rem;color:#4CB050;flex-shrink:0;"></iconify-icon>
			<div>
				<p id="sidebar-clock" class="text-sm font-bold tabular-nums" style="color:#4CB050;">00:00:00</p>
				<p id="sidebar-date" class="text-xs text-gray-400"></p>
			</div>
		</div>
	</div>
	<nav class="flex-1 px-3 py-4 space-y-0.5" aria-label="Main navigation">
		<?php foreach ( $nav_items as $item ) :
			$href   = esc_url( home_url( $item['href'] ) );
			$icon   = esc_attr( $item['icon'] );
			$label  = esc_html( $item['label'] );
			$active = ( rtrim( $_SERVER['REQUEST_URI'] ?? '', '/' ) === rtrim( parse_url( home_url( $item['href'] ), PHP_URL_PATH ) ?? '', '/' ) ) ? ' active' : '';
		?>
		<a href="<?php echo $href; ?>" class="nav-item<?php echo $active; ?> flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-white/[0.07] transition-all duration-200">
			<iconify-icon icon="<?php echo $icon; ?>" style="font-size:1.2rem;flex-shrink:0;"></iconify-icon>
			<span><?php echo $label; ?></span>
		</a>
		<?php endforeach; ?>
	</nav>
	<div class="px-4 py-4 shrink-0" style="border-top:1px solid rgba(255,255,255,0.08);">
		<div class="flex items-center justify-between gap-2">
			<p class="text-xs text-gray-600 truncate">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Bymoreish</p>
			<a href="<?php echo esc_url( $logout_url ); ?>" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-red-400 hover:text-white hover:bg-red-500/20 transition-all shrink-0">
				<iconify-icon icon="solar:logout-linear" style="font-size:1rem;"></iconify-icon>Logout
			</a>
		</div>
	</div>
</aside>

<!-- MAIN CONTENT -->
<div id="main-content" class="min-h-screen flex flex-col">
	<header class="sticky top-0 z-20 flex items-center justify-between gap-4 px-6 py-4" style="background:rgba(10,10,15,0.85);backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,0.07);">
		<div class="flex items-center gap-4 min-w-0">
			<button id="hamburger" type="button" aria-label="Open navigation menu" class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all lg:hidden">
				<iconify-icon icon="solar:hamburger-menu-linear" style="font-size:1.4rem;"></iconify-icon>
			</button>
			<div class="min-w-0">
				<h1 class="text-lg font-bold text-white leading-tight truncate">Analytics</h1>
				<p class="text-xs text-gray-500 hidden sm:block">Business intelligence and performance insights</p>
			</div>
		</div>
		<div class="pill-beam flex items-center gap-2 px-4 py-2 rounded-full shrink-0" style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.18);">
			<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
			<div>
				<p id="header-clock" class="text-sm font-bold tabular-nums" style="color:#EECE55;">00:00:00</p>
				<p id="header-date" class="text-xs text-gray-500 hidden sm:block"></p>
			</div>
		</div>
	</header>

	<div class="flex-1 px-4 sm:px-6 lg:px-8 py-8 space-y-6">

		<!-- Filter tabs -->
		<div class="fade-up delay-1">
			<div class="glass rounded-2xl p-4">
				<div class="flex flex-wrap items-center gap-2">
					<button class="filter-tab active" data-period="daily">Daily</button>
					<button class="filter-tab" data-period="weekly">Weekly</button>
					<button class="filter-tab" data-period="monthly">Monthly</button>
					<button class="filter-tab" data-period="yearly">Yearly</button>
					<button class="filter-tab" data-period="custom">Custom Range</button>
					<div id="custom-range" class="flex items-center gap-2 flex-wrap">
						<input type="date" id="custom-from" class="bym-input" style="width:auto;">
						<span class="text-gray-500 text-xs">to</span>
						<input type="date" id="custom-to" class="bym-input" style="width:auto;">
						<button id="btn-apply-custom" type="button" class="px-4 py-1.5 rounded-full text-xs font-bold" style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">Apply</button>
					</div>
				</div>
			</div>
		</div>

		<!-- KPI Row -->
		<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 fade-up delay-2" id="kpi-row">
			<?php
			$kpis = [
				['id'=>'kpi-revenue',   'label'=>'Revenue',          'icon'=>'solar:dollar-minimalistic-linear', 'color'=>'#EECE55'],
				['id'=>'kpi-expenses',  'label'=>'Expenses',         'icon'=>'solar:wallet-money-linear',         'color'=>'#ef4444'],
				['id'=>'kpi-profit',    'label'=>'Profit',           'icon'=>'solar:graph-up-linear',             'color'=>'#4CB050'],
				['id'=>'kpi-orders',    'label'=>'Total Orders',     'icon'=>'solar:cart-large-2-linear',         'color'=>'#EECE55'],
				['id'=>'kpi-aov',       'label'=>'Avg Order Value',  'icon'=>'solar:tag-price-linear',            'color'=>'#4CB050'],
			];
			foreach ($kpis as $k) : ?>
			<div class="glass kpi-card rounded-2xl p-5">
				<div class="flex items-center gap-2 mb-2">
					<iconify-icon icon="<?php echo esc_attr($k['icon']); ?>" style="font-size:1.1rem;color:<?php echo esc_attr($k['color']); ?>;flex-shrink:0;"></iconify-icon>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight"><?php echo esc_html($k['label']); ?></p>
				</div>
				<p id="<?php echo esc_attr($k['id']); ?>" class="text-xl font-extrabold text-white tabular-nums">—</p>
			</div>
			<?php endforeach; ?>
		</div>

		<!-- Charts Bento Grid -->
		<div class="fade-up delay-3 grid grid-cols-1 lg:grid-cols-4 gap-4">

			<!-- Revenue vs Expenses (2×2 large) -->
			<div class="glass rounded-2xl p-5 lg:col-span-2 lg:row-span-2 flex flex-col">
				<div class="flex items-center justify-between mb-4">
					<div class="flex items-center gap-2">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:graph-linear" style="font-size:1.1rem;color:#EECE55;"></iconify-icon>
						</div>
						<h3 class="text-sm font-bold text-white">Revenue vs Expenses</h3>
					</div>
					<div class="flex items-center gap-3 text-xs text-gray-400">
						<span class="flex items-center gap-1"><span class="w-3 h-1.5 rounded-full inline-block" style="background:#EECE55;"></span>Revenue</span>
						<span class="flex items-center gap-1"><span class="w-3 h-1.5 rounded-full inline-block" style="background:#ef4444;"></span>Expenses</span>
					</div>
				</div>
				<div class="flex-1 chart-box" style="min-height:280px;"><canvas id="chart-rev-exp"></canvas></div>
			</div>

			<!-- Best Selling Products doughnut (1×1) -->
			<div class="glass rounded-2xl p-5 flex flex-col">
				<div class="flex items-center gap-2 mb-4">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:pie-chart-2-linear" style="font-size:1.1rem;color:#4CB050;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Best Sellers</h3>
				</div>
				<div class="flex-1 chart-box" style="min-height:180px;"><canvas id="chart-best-sellers"></canvas></div>
			</div>

			<!-- Expense Breakdown doughnut (1×1) -->
			<div class="glass rounded-2xl p-5 flex flex-col">
				<div class="flex items-center gap-2 mb-4">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(239,68,68,0.12);">
						<iconify-icon icon="solar:chart-2-linear" style="font-size:1.1rem;color:#ef4444;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Expense Breakdown</h3>
				</div>
				<div class="flex-1 chart-box" style="min-height:180px;"><canvas id="chart-expense-breakdown"></canvas></div>
			</div>

			<!-- Product Performance bar (2×1) -->
			<div class="glass rounded-2xl p-5 lg:col-span-2 flex flex-col">
				<div class="flex items-center gap-2 mb-4">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:chart-square-linear" style="font-size:1.1rem;color:#EECE55;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Product Performance</h3>
				</div>
				<div class="flex-1 chart-box" style="min-height:200px;"><canvas id="chart-product-perf"></canvas></div>
			</div>

			<!-- Payment Mode (1×1) -->
			<div class="glass rounded-2xl p-5 lg:col-span-2 flex flex-col">
				<div class="flex items-center gap-2 mb-4">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:card-linear" style="font-size:1.1rem;color:#4CB050;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Payment Mode Distribution</h3>
				</div>
				<div class="flex-1 chart-box" style="min-height:200px;"><canvas id="chart-payment-mode"></canvas></div>
			</div>

		</div><!-- /bento grid -->

		<!-- Bottom tables -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 fade-up delay-4">

			<!-- Top Products -->
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center gap-3 px-5 py-4" style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:medal-ribbon-star-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Top Products</h3>
				</div>
				<table class="ana-table w-full text-sm text-white">
					<thead><tr><th class="text-center w-8">Rank</th><th>Product</th><th class="text-right">Sold</th><th class="text-right">Revenue</th></tr></thead>
					<tbody id="top-products-body">
						<tr><td colspan="4" class="text-center py-8 text-gray-500 text-xs"><iconify-icon icon="solar:refresh-linear" class="animate-spin text-xl block mx-auto mb-1" style="color:#EECE55;"></iconify-icon>Loading…</td></tr>
					</tbody>
				</table>
			</div>

			<!-- Top Expense Categories -->
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center gap-3 px-5 py-4" style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(239,68,68,0.12);">
						<iconify-icon icon="solar:wallet-money-linear" style="font-size:1rem;color:#ef4444;"></iconify-icon>
					</div>
					<h3 class="text-sm font-bold text-white">Top Expense Categories</h3>
				</div>
				<table class="ana-table w-full text-sm text-white">
					<thead><tr><th class="text-center w-8">Rank</th><th>Category</th><th class="text-right">Amount</th><th class="text-right">% Share</th></tr></thead>
					<tbody id="top-expenses-body">
						<tr><td colspan="4" class="text-center py-8 text-gray-500 text-xs"><iconify-icon icon="solar:refresh-linear" class="animate-spin text-xl block mx-auto mb-1" style="color:#EECE55;"></iconify-icon>Loading…</td></tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- AI Insights -->
		<section class="fade-up delay-5">
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center gap-3 px-6 py-4" style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.15);">
						<iconify-icon icon="solar:bolt-linear" style="font-size:1.1rem;color:#EECE55;"></iconify-icon>
					</div>
					<div>
						<h2 class="text-sm font-bold text-white">AI Insights</h2>
						<p class="text-xs text-gray-500">Smart observations based on your data</p>
					</div>
				</div>
				<div id="ai-insights-grid" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
					<div class="ai-card animate-pulse">
						<div class="ai-icon"><iconify-icon icon="solar:bolt-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon></div>
						<div class="space-y-1.5 flex-1"><div class="h-3 bg-white/10 rounded w-3/4"></div><div class="h-3 bg-white/10 rounded w-full"></div><div class="h-3 bg-white/10 rounded w-2/3"></div></div>
					</div>
					<div class="ai-card animate-pulse">
						<div class="ai-icon"><iconify-icon icon="solar:bolt-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon></div>
						<div class="space-y-1.5 flex-1"><div class="h-3 bg-white/10 rounded w-2/3"></div><div class="h-3 bg-white/10 rounded w-full"></div><div class="h-3 bg-white/10 rounded w-1/2"></div></div>
					</div>
				</div>
			</div>
		</section>

	</div>
</div>

<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/pages/analytics.js"></script>
<script>
window.bymConfig = {
	ajaxUrl:  '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
	nonce:    '<?php echo esc_js( $nonce ); ?>',
	userId:   <?php echo (int) $user_id; ?>,
	userRole: '<?php echo esc_js( $user_role ); ?>',
	branchId: <?php echo (int) $branch_id; ?>,
	pluginUrl:'<?php echo esc_js( BYMOREISH_PLUGIN_URL ); ?>',
};

/* ── Clock ── */
function updateClock() {
	const now = new Date(), t = now.toLocaleTimeString('en-NG',{hour12:false}), d = now.toLocaleDateString('en-NG',{weekday:'short',year:'numeric',month:'short',day:'numeric'});
	['header-clock','sidebar-clock'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=t;});
	['header-date','sidebar-date'].forEach(id=>{const e=document.getElementById(id);if(e)e.textContent=d;});
}
updateClock(); setInterval(updateClock, 1000);

/* ── Sidebar ── */
const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('sidebar-overlay'),hamburger=document.getElementById('hamburger');
function openSidebar(){sidebar.classList.add('open');overlay.classList.add('open');document.body.style.overflow='hidden';}
function closeSidebar(){sidebar.classList.remove('open');overlay.classList.remove('open');document.body.style.overflow='';}
if(hamburger)hamburger.addEventListener('click',openSidebar);
if(overlay)overlay.addEventListener('click',closeSidebar);
sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{if(window.innerWidth<1024)closeSidebar();}));

/* ── Utils ── */
function naira(n){return '₦'+(parseFloat(String(n).replace(/[^0-9.]/g,''))||0).toLocaleString('en-NG',{minimumFractionDigits:2,maximumFractionDigits:2});}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

/* ── Chart instances ── */
const charts = {};
const CHART_DEFAULTS = {
	plugins:{ legend:{ labels:{ color:'#9ca3af', font:{ size:11 } } }, tooltip:{ backgroundColor:'rgba(17,17,24,0.95)', titleColor:'#EECE55', bodyColor:'#e2e8f0', borderColor:'rgba(238,206,85,0.2)', borderWidth:1 } },
	scales:{ x:{ ticks:{ color:'#6b7280', font:{size:10} }, grid:{ color:'rgba(255,255,255,0.04)' } }, y:{ ticks:{ color:'#6b7280', font:{size:10} }, grid:{ color:'rgba(255,255,255,0.06)' } } },
};

function destroyChart(id){ if(charts[id]){ charts[id].destroy(); delete charts[id]; } }

function buildRevExpChart(labels, revData, expData) {
	destroyChart('rev-exp');
	const ctx = document.getElementById('chart-rev-exp');
	if (!ctx) return;
	charts['rev-exp'] = new Chart(ctx, {
		type:'line',
		data:{ labels, datasets:[
			{ label:'Revenue', data:revData, borderColor:'#EECE55', backgroundColor:'rgba(238,206,85,0.08)', fill:true, tension:0.4, pointBackgroundColor:'#EECE55', pointRadius:3 },
			{ label:'Expenses', data:expData, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.06)', fill:true, tension:0.4, pointBackgroundColor:'#ef4444', pointRadius:3 },
		]},
		options:{ responsive:true, maintainAspectRatio:false, interaction:{ mode:'index', intersect:false }, plugins:{ ...CHART_DEFAULTS.plugins, tooltip:{ ...CHART_DEFAULTS.plugins.tooltip, callbacks:{ label:ctx=>' '+naira(ctx.parsed.y) } } }, scales:{ x:{ ...CHART_DEFAULTS.scales.x }, y:{ ...CHART_DEFAULTS.scales.y, ticks:{ ...CHART_DEFAULTS.scales.y.ticks, callback:v=>naira(v) } } } },
	});
}

function buildBestSellersChart(labels, values) {
	destroyChart('best-sellers');
	const ctx = document.getElementById('chart-best-sellers');
	if (!ctx) return;
	const colors = ['#EECE55','#4CB050','#60a5fa','#f97316','#a78bfa','#f472b6','#34d399'];
	charts['best-sellers'] = new Chart(ctx, {
		type:'doughnut',
		data:{ labels, datasets:[{ data:values, backgroundColor:colors.slice(0,labels.length), borderColor:'#111118', borderWidth:2 }] },
		options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ color:'#9ca3af', font:{size:10}, boxWidth:10, padding:8 } } } },
	});
}

function buildExpenseBreakdownChart(labels, values) {
	destroyChart('exp-breakdown');
	const ctx = document.getElementById('chart-expense-breakdown');
	if (!ctx) return;
	const colors = ['#ef4444','#f97316','#fbbf24','#a78bfa','#60a5fa','#34d399'];
	charts['exp-breakdown'] = new Chart(ctx, {
		type:'doughnut',
		data:{ labels, datasets:[{ data:values, backgroundColor:colors.slice(0,labels.length), borderColor:'#111118', borderWidth:2 }] },
		options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{ color:'#9ca3af', font:{size:10}, boxWidth:10, padding:8 } } } },
	});
}

function buildProductPerfChart(labels, values) {
	destroyChart('prod-perf');
	const ctx = document.getElementById('chart-product-perf');
	if (!ctx) return;
	charts['prod-perf'] = new Chart(ctx, {
		type:'bar',
		data:{ labels, datasets:[{ label:'Units Sold', data:values, backgroundColor:'rgba(238,206,85,0.7)', borderColor:'#EECE55', borderWidth:1, borderRadius:4, borderSkipped:false }] },
		options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ ...CHART_DEFAULTS.plugins, legend:{ display:false } }, scales:{ x:{ ...CHART_DEFAULTS.scales.x }, y:{ ...CHART_DEFAULTS.scales.y } } },
	});
}

function buildPaymentChart(labels, values) {
	destroyChart('payment');
	const ctx = document.getElementById('chart-payment-mode');
	if (!ctx) return;
	const colors = ['#EECE55','#4CB050','#60a5fa','#a78bfa'];
	charts['payment'] = new Chart(ctx, {
		type:'bar',
		data:{ labels, datasets:[{ label:'Transactions', data:values, backgroundColor:colors.slice(0,labels.length), borderRadius:6 }] },
		options:{ responsive:true, maintainAspectRatio:false, plugins:{ ...CHART_DEFAULTS.plugins, legend:{ display:false } }, scales:{ x:{ ...CHART_DEFAULTS.scales.x }, y:{ ...CHART_DEFAULTS.scales.y } } },
	});
}

/* ── Tables ── */
function renderTopProducts(products) {
	const medals = ['🥇','🥈','🥉'];
	const tbody  = document.getElementById('top-products-body');
	if (!products || !products.length) { tbody.innerHTML='<tr><td colspan="4" class="text-center py-8 text-gray-500 text-xs">No data.</td></tr>'; return; }
	tbody.innerHTML = products.slice(0,10).map((p,i)=>
		'<tr><td class="text-center text-sm">' + (medals[i]||('#'+(i+1))) + '</td>' +
		'<td class="font-semibold text-white">' + esc(p.name) + '</td>' +
		'<td class="text-right tabular-nums text-gray-300">' + (p.qty||0).toLocaleString() + '</td>' +
		'<td class="text-right tabular-nums font-bold" style="color:#EECE55;">' + naira(p.revenue||0) + '</td></tr>'
	).join('');
}

function renderTopExpenses(categories) {
	const tbody = document.getElementById('top-expenses-body');
	if (!categories || !categories.length) { tbody.innerHTML='<tr><td colspan="4" class="text-center py-8 text-gray-500 text-xs">No data.</td></tr>'; return; }
	const total = categories.reduce((s,c)=>s+(parseFloat(c.amount)||0),0);
	tbody.innerHTML = categories.slice(0,10).map((c,i)=>{
		const pct = total > 0 ? ((parseFloat(c.amount)||0)/total*100).toFixed(1) : '0.0';
		return '<tr><td class="text-center text-gray-400 text-xs font-semibold">#' + (i+1) + '</td>' +
		'<td class="text-white">' + esc(c.category||c.description||'Other') + '</td>' +
		'<td class="text-right tabular-nums text-red-400 font-bold">' + naira(c.amount||0) + '</td>' +
		'<td class="text-right tabular-nums text-gray-400 text-xs">' + pct + '%</td></tr>';
	}).join('');
}

/* ── AI Insights ── */
const INSIGHT_ICONS = ['solar:bolt-linear','solar:graph-up-linear','solar:star-linear','solar:fire-linear','solar:info-circle-linear'];
function renderInsights(insights) {
	const grid = document.getElementById('ai-insights-grid');
	if (!insights || !insights.length) {
		grid.innerHTML = '<p class="col-span-2 text-center text-gray-500 text-xs py-6">No insights available for this period.</p>';
		return;
	}
	grid.innerHTML = insights.map((ins,i)=>
		'<div class="ai-card">' +
		'<div class="ai-icon"><iconify-icon icon="' + esc(INSIGHT_ICONS[i % INSIGHT_ICONS.length]) + '" style="font-size:1rem;color:#EECE55;"></iconify-icon></div>' +
		'<div><p class="text-xs font-bold text-white mb-0.5">' + esc(ins.title||'Insight') + '</p><p class="text-xs text-gray-400 leading-relaxed">' + esc(ins.body||ins) + '</p></div>' +
		'</div>'
	).join('');
}

function generateLocalInsights(d) {
	const insights = [];
	if (d.revenue && d.expenses) {
		const margin = d.revenue > 0 ? ((d.revenue - d.expenses) / d.revenue * 100).toFixed(1) : 0;
		insights.push({ title:'Profit Margin', body: 'Your profit margin for this period is ' + margin + '%. ' + (margin >= 30 ? 'Excellent! Keep managing expenses well.' : margin >= 15 ? 'Decent margin. Look for ways to reduce overhead.' : 'Margin is low — review your expenses carefully.') });
	}
	if (d.top_products && d.top_products[0]) {
		insights.push({ title:'Top Performer', body: '"' + d.top_products[0].name + '" is your best-selling product with ' + (d.top_products[0].qty||0).toLocaleString() + ' units sold. Consider promoting it further.' });
	}
	if (d.total_orders && d.revenue) {
		const aov = (d.revenue / d.total_orders).toFixed(0);
		insights.push({ title:'Order Value Trend', body: 'Average order value is ' + naira(aov) + '. Upselling extras or combo deals can increase this.' });
	}
	if (d.payment_labels && d.payment_labels.length > 1) {
		insights.push({ title:'Payment Preferences', body: 'Your most used payment method is "' + d.payment_labels[0] + '". Ensuring seamless checkout for this method will reduce abandoned orders.' });
	}
	return insights;
}

/* ── Fetch Analytics ── */
let activePeriod = 'daily';

async function fetchAnalytics(period, from, to) {
	const body = new URLSearchParams({ action:'bym_get_analytics', nonce:window.bymConfig.nonce, period, from:from||'', to:to||'', branch_id:window.bymConfig.branchId });
	try {
		const res  = await fetch(window.bymConfig.ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body.toString() });
		const data = await res.json();
		if (!data || !data.success) return;
		const d = data.data;

		// KPIs
		const setKPI = (id,v) => { const e=document.getElementById(id); if(e) e.textContent=v; };
		setKPI('kpi-revenue',  naira(d.revenue||0));
		setKPI('kpi-expenses', naira(d.expenses||0));
		setKPI('kpi-profit',   naira((d.revenue||0)-(d.expenses||0)));
		setKPI('kpi-orders',   (d.total_orders||0).toLocaleString());
		setKPI('kpi-aov',      naira(d.total_orders ? (d.revenue||0)/d.total_orders : 0));

		// Charts
		buildRevExpChart(d.trend_labels||[], d.trend_revenue||[], d.trend_expenses||[]);
		buildBestSellersChart((d.top_products||[]).slice(0,6).map(p=>p.name), (d.top_products||[]).slice(0,6).map(p=>p.qty||0));
		buildExpenseBreakdownChart((d.expense_categories||[]).map(c=>c.category||'Other'), (d.expense_categories||[]).map(c=>parseFloat(c.amount)||0));
		buildProductPerfChart((d.top_products||[]).slice(0,8).map(p=>p.name).reverse(), (d.top_products||[]).slice(0,8).map(p=>p.qty||0).reverse());
		buildPaymentChart(d.payment_labels||[], d.payment_values||[]);

		// Tables
		renderTopProducts(d.top_products||[]);
		renderTopExpenses(d.expense_categories||[]);

		// Insights
		renderInsights(d.insights && d.insights.length ? d.insights : generateLocalInsights(d));
	} catch(e) {
		console.warn('Analytics fetch failed', e);
	}
}

/* ── Filter tabs ── */
document.querySelectorAll('.filter-tab').forEach(btn => {
	btn.addEventListener('click', function() {
		document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
		this.classList.add('active');
		activePeriod = this.dataset.period;
		const cr = document.getElementById('custom-range');
		if (activePeriod === 'custom') { cr.classList.add('open'); }
		else { cr.classList.remove('open'); fetchAnalytics(activePeriod); }
	});
});

document.getElementById('btn-apply-custom').addEventListener('click', function() {
	const from = document.getElementById('custom-from').value;
	const to   = document.getElementById('custom-to').value;
	if (!from || !to) return;
	fetchAnalytics('custom', from, to);
});

/* ── Active nav + init ── */
document.addEventListener('DOMContentLoaded', function() {
	const currentPath = window.location.pathname.replace(/\/$/, '');
	document.querySelectorAll('.nav-item').forEach(a => {
		const href = a.getAttribute('href'); if(!href) return;
		try { if(new URL(href,window.location.origin).pathname.replace(/\/$/, '')===currentPath) a.classList.add('active'); } catch(_){}
	});
	// Default custom date range to this month
	const now = new Date(), from = new Date(now.getFullYear(), now.getMonth(), 1);
	document.getElementById('custom-from').value = from.toISOString().slice(0,10);
	document.getElementById('custom-to').value   = now.toISOString().slice(0,10);
	fetchAnalytics(activePeriod);
});
</script>
</body>
</html>
