<?php
/**
 * Product Summary Page – Bymoreish Inventory
 *
 * Analytics and summary view for product sales performance.
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
$plugin_url    = BYMOREISH_PLUGIN_URL;
$ajax_url      = admin_url( 'admin-ajax.php' );
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
	<title>Product Summary – Bymoreish Inventory</title>

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
		@keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
		.fade-up  { animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) forwards; }
		.delay-1  { animation-delay: 0.08s; opacity: 0; }
		.delay-2  { animation-delay: 0.16s; opacity: 0; }
		.delay-3  { animation-delay: 0.24s; opacity: 0; }
		.delay-4  { animation-delay: 0.32s; opacity: 0; }
		.bym-input { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 0.5rem; color: #fff; padding: 0.45rem 0.7rem; font-size: 0.83rem; outline: none; transition: border-color 0.2s; width: 100%; }
		.bym-input:focus { border-color: rgba(238,206,85,0.5); box-shadow: 0 0 0 3px rgba(238,206,85,0.1); }
		.bym-select { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 0.5rem; color: #fff; padding: 0.45rem 0.7rem; font-size: 0.83rem; outline: none; transition: border-color 0.2s; }
		.bym-select:focus { border-color: rgba(238,206,85,0.5); }
		.bym-select option { background: #1a1a2e; color: #fff; }
		.prod-table th { background: rgba(238,206,85,0.07); color: #EECE55; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 0.75rem 1rem; white-space: nowrap; cursor: pointer; user-select: none; }
		.prod-table th:hover { background: rgba(238,206,85,0.12); }
		.prod-table th.sort-asc::after  { content: ' ↑'; color: #EECE55; }
		.prod-table th.sort-desc::after { content: ' ↓'; color: #EECE55; }
		.prod-table td { padding: 0.65rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
		.prod-table tr:last-child td { border-bottom: none; }
		.prod-table tr:hover td { background: rgba(255,255,255,0.03); }
		#sidebar::-webkit-scrollbar { width: 4px; }
		#sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
		#main-content::-webkit-scrollbar { width: 6px; }
		#main-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 3px; }
		.sparkline-canvas { max-height: 32px; }
		.pagination-btn { padding: 0.35rem 0.7rem; border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.12); color: #9ca3af; background: rgba(255,255,255,0.05); cursor: pointer; transition: all 0.2s; }
		.pagination-btn:hover, .pagination-btn.active { border-color: rgba(238,206,85,0.4); color: #EECE55; background: rgba(238,206,85,0.1); }
		.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
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
				<h1 class="text-lg font-bold text-white leading-tight truncate">Product Summary</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">Sales performance and product analytics</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<button id="btn-print" type="button" class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all" style="background:rgba(76,176,80,0.1);border:1px solid rgba(76,176,80,0.2);color:#4CB050;">
				<iconify-icon icon="solar:printer-linear" style="font-size:1rem;"></iconify-icon>Print / PDF
			</button>
			<div class="pill-beam flex items-center gap-2 px-4 py-2 rounded-full shrink-0" style="background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.18);">
				<iconify-icon icon="solar:clock-circle-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
				<div>
					<p id="header-clock" class="text-sm font-bold tabular-nums" style="color:#EECE55;">00:00:00</p>
					<p id="header-date" class="text-xs text-gray-500 hidden sm:block"></p>
				</div>
			</div>
		</div>
	</header>

	<div class="flex-1 px-4 sm:px-6 lg:px-8 py-8 space-y-6">

		<!-- Filter bar -->
		<div class="glass rounded-2xl p-4 fade-up delay-1">
			<div class="flex flex-wrap gap-3 items-end">
				<div class="space-y-1 flex-1 min-w-[140px]">
					<label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">From</label>
					<input type="date" id="filter-from" class="bym-input">
				</div>
				<div class="space-y-1 flex-1 min-w-[140px]">
					<label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">To</label>
					<input type="date" id="filter-to" class="bym-input">
				</div>
				<div class="space-y-1 flex-1 min-w-[130px]">
					<label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Period</label>
					<select id="filter-period" class="bym-select w-full">
						<option value="daily">Daily</option>
						<option value="weekly">Weekly</option>
						<option value="monthly" selected>Monthly</option>
						<option value="yearly">Yearly</option>
					</select>
				</div>
				<div class="space-y-1 flex-1 min-w-[150px]">
					<label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Product</label>
					<select id="filter-product" class="bym-select w-full">
						<option value="">All Products</option>
					</select>
				</div>
				<button id="btn-apply-filter" type="button" class="flex items-center gap-2 px-5 py-2 rounded-full text-sm font-bold transition-all shrink-0" style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
					<iconify-icon icon="solar:filter-linear" style="font-size:1rem;"></iconify-icon>Apply
				</button>
			</div>
		</div>

		<!-- Summary Cards -->
		<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 fade-up delay-2">
			<div class="glass rounded-2xl p-5">
				<div class="flex items-center gap-3 mb-3">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:bag-check-linear" style="font-size:1.2rem;color:#EECE55;"></iconify-icon>
					</div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight">Total Items Sold</p>
				</div>
				<p id="kpi-total-sold" class="text-2xl font-extrabold text-white tabular-nums">—</p>
				<canvas id="spark-sold" class="sparkline-canvas w-full mt-2"></canvas>
			</div>
			<div class="glass rounded-2xl p-5">
				<div class="flex items-center gap-3 mb-3">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:dollar-minimalistic-linear" style="font-size:1.2rem;color:#4CB050;"></iconify-icon>
					</div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight">Total Revenue</p>
				</div>
				<p id="kpi-total-revenue" class="text-2xl font-extrabold text-white tabular-nums">—</p>
				<canvas id="spark-revenue" class="sparkline-canvas w-full mt-2"></canvas>
			</div>
			<div class="glass rounded-2xl p-5">
				<div class="flex items-center gap-3 mb-3">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:star-linear" style="font-size:1.2rem;color:#EECE55;"></iconify-icon>
					</div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight">Most Popular</p>
				</div>
				<p id="kpi-most-popular" class="text-base font-bold text-white truncate">—</p>
				<p id="kpi-most-popular-qty" class="text-xs text-gray-400 mt-0.5"></p>
			</div>
			<div class="glass rounded-2xl p-5">
				<div class="flex items-center gap-3 mb-3">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0" style="background:rgba(100,100,120,0.15);">
						<iconify-icon icon="solar:sort-from-bottom-to-top-linear" style="font-size:1.2rem;color:#94a3b8;"></iconify-icon>
					</div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider leading-tight">Least Popular</p>
				</div>
				<p id="kpi-least-popular" class="text-base font-bold text-white truncate">—</p>
				<p id="kpi-least-popular-qty" class="text-xs text-gray-400 mt-0.5"></p>
			</div>
		</div>

		<!-- Product Table -->
		<section class="fade-up delay-3">
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:bag-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Detailed Product Performance</h2>
					</div>
					<span id="table-info" class="text-xs text-gray-500"></span>
				</div>
				<div class="overflow-x-auto">
					<table class="prod-table w-full text-sm text-white" id="products-table">
						<thead>
							<tr>
								<th data-col="name" class="text-left">Product Name</th>
								<th data-col="category" class="text-left">Category</th>
								<th data-col="total_sold" class="text-right">Total Sold</th>
								<th data-col="total_revenue" class="text-right">Revenue (₦)</th>
								<th data-col="sold_by" class="text-left">Sold By</th>
								<th data-col="last_sold" class="text-left">Last Sold</th>
								<th class="text-center">Detail</th>
							</tr>
						</thead>
						<tbody id="products-body">
							<tr><td colspan="7" class="text-center py-10 text-gray-500 text-xs">
								<iconify-icon icon="solar:refresh-linear" class="animate-spin text-2xl block mx-auto mb-2" style="color:#EECE55;"></iconify-icon>Loading…
							</td></tr>
						</tbody>
					</table>
				</div>
				<!-- Pagination -->
				<div class="flex items-center justify-between px-6 py-4" style="border-top:1px solid rgba(255,255,255,0.07);">
					<p id="pagination-info" class="text-xs text-gray-500"></p>
					<div id="pagination-controls" class="flex items-center gap-1.5"></div>
				</div>
			</div>
		</section>

	</div>
</div>

<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>
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
	const now = new Date();
	const t = now.toLocaleTimeString('en-NG', { hour12: false });
	const d = now.toLocaleDateString('en-NG', { weekday:'short', year:'numeric', month:'short', day:'numeric' });
	['header-clock','sidebar-clock'].forEach(id => { const e = document.getElementById(id); if(e) e.textContent = t; });
	['header-date','sidebar-date'].forEach(id => { const e = document.getElementById(id); if(e) e.textContent = d; });
}
updateClock(); setInterval(updateClock, 1000);

/* ── Sidebar ── */
const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebar-overlay'), hamburger = document.getElementById('hamburger');
function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('open');    document.body.style.overflow='hidden'; }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow=''; }
if(hamburger) hamburger.addEventListener('click', openSidebar);
if(overlay)   overlay.addEventListener('click', closeSidebar);
sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', () => { if(window.innerWidth < 1024) closeSidebar(); }));

/* ── Utils ── */
function naira(n) { return '₦' + (parseFloat(String(n).replace(/[^0-9.]/g,''))||0).toLocaleString('en-NG',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── State ── */
let allData = [], sortCol = 'total_sold', sortDir = 'desc', page = 1;
const PER_PAGE = 15;

/* ── Sparkline ── */
const sparkCharts = {};
function renderSparkline(id, labels, values, color) {
	const canvas = document.getElementById(id);
	if (!canvas) return;
	if (sparkCharts[id]) sparkCharts[id].destroy();
	sparkCharts[id] = new Chart(canvas, {
		type: 'line',
		data: { labels, datasets: [{ data: values, borderColor: color, borderWidth: 1.5, fill: true, backgroundColor: color + '22', tension: 0.4, pointRadius: 0 }] },
		options: { animation: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } }, responsive: true, maintainAspectRatio: false },
	});
}

/* ── Fetch ── */
async function fetchSummary() {
	const from   = document.getElementById('filter-from').value;
	const to     = document.getElementById('filter-to').value;
	const period = document.getElementById('filter-period').value;
	const prod   = document.getElementById('filter-product').value;
	const body   = new URLSearchParams({ action:'bym_get_product_summary', nonce: window.bymConfig.nonce, from, to, period, product_id: prod, branch_id: window.bymConfig.branchId });
	document.getElementById('products-body').innerHTML = '<tr><td colspan="7" class="text-center py-10 text-gray-500 text-xs"><iconify-icon icon="solar:refresh-linear" class="animate-spin text-2xl block mx-auto mb-2" style="color:#EECE55;"></iconify-icon>Loading…</td></tr>';
	try {
		const res  = await fetch(window.bymConfig.ajaxUrl, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() });
		const data = await res.json();
		if (!data || !data.success) { document.getElementById('products-body').innerHTML = '<tr><td colspan="7" class="text-center py-10 text-gray-500 text-xs">No data found.</td></tr>'; return; }
		allData = data.data.products || [];
		populateProductFilter(allData);
		updateKPIs(data.data);
		renderSparkline('spark-sold',    data.data.trend_labels || [], data.data.trend_sold    || [], '#EECE55');
		renderSparkline('spark-revenue', data.data.trend_labels || [], data.data.trend_revenue || [], '#4CB050');
		page = 1;
		renderTable();
	} catch(e) {
		document.getElementById('products-body').innerHTML = '<tr><td colspan="7" class="text-center py-10 text-red-400 text-xs">Failed to load data.</td></tr>';
	}
}

function updateKPIs(d) {
	const set = (id,v) => { const e=document.getElementById(id); if(e) e.textContent=v; };
	set('kpi-total-sold',    (d.total_sold||0).toLocaleString());
	set('kpi-total-revenue', naira(d.total_revenue||0));
	set('kpi-most-popular',  d.most_popular?.name  || '—');
	set('kpi-most-popular-qty', d.most_popular ? (d.most_popular.qty||0).toLocaleString() + ' sold' : '');
	set('kpi-least-popular', d.least_popular?.name || '—');
	set('kpi-least-popular-qty', d.least_popular ? (d.least_popular.qty||0).toLocaleString() + ' sold' : '');
}

function populateProductFilter(products) {
	const sel = document.getElementById('filter-product');
	const cur = sel.value;
	sel.innerHTML = '<option value="">All Products</option>';
	products.forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.name; sel.appendChild(o); });
	sel.value = cur;
}

function sortedData() {
	return [...allData].sort((a,b) => {
		let av = a[sortCol] ?? '', bv = b[sortCol] ?? '';
		if (!isNaN(parseFloat(av)) && !isNaN(parseFloat(bv))) { av = parseFloat(av); bv = parseFloat(bv); }
		if (av < bv) return sortDir === 'asc' ? -1 : 1;
		if (av > bv) return sortDir === 'asc' ?  1 : -1;
		return 0;
	});
}

function renderTable() {
	const data    = sortedData();
	const total   = data.length;
	const pages   = Math.max(1, Math.ceil(total / PER_PAGE));
	page          = Math.min(Math.max(1, page), pages);
	const slice   = data.slice((page-1)*PER_PAGE, page*PER_PAGE);
	const tbody   = document.getElementById('products-body');

	if (!slice.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-10 text-gray-500 text-xs">No products found.</td></tr>'; }
	else {
		tbody.innerHTML = slice.map((p,i) =>
			'<tr>' +
			'<td class="font-semibold text-white">' + esc(p.name) + '</td>' +
			'<td><span class="px-2 py-0.5 rounded-full text-xs font-semibold" style="background:rgba(76,176,80,0.1);color:#4CB050;">' + esc(p.category||'—') + '</span></td>' +
			'<td class="text-right tabular-nums font-bold text-white">' + (p.total_sold||0).toLocaleString() + '</td>' +
			'<td class="text-right tabular-nums font-bold" style="color:#EECE55;">' + naira(p.total_revenue||0) + '</td>' +
			'<td class="text-gray-400 text-xs">' + esc(p.sold_by||'—') + '</td>' +
			'<td class="text-gray-500 text-xs tabular-nums">' + esc(p.last_sold||'—') + '</td>' +
			'<td class="text-center"><a href="' + esc(window.bymConfig.pluginUrl.replace(/\/$/, '') + '?product=' + encodeURIComponent(p.id||'')) + '" class="text-xs px-2 py-1 rounded-lg transition-all" style="color:#EECE55;background:rgba(238,206,85,0.08);border:1px solid rgba(238,206,85,0.2);">View →</a></td>' +
			'</tr>'
		).join('');
	}

	document.getElementById('table-info').textContent = total + ' product' + (total!==1?'s':'') + ' found';
	document.getElementById('pagination-info').textContent = 'Page ' + page + ' of ' + pages;

	// Pagination buttons
	const pc = document.getElementById('pagination-controls');
	pc.innerHTML = '';
	const prevBtn = document.createElement('button');
	prevBtn.className = 'pagination-btn'; prevBtn.textContent = '← Prev'; prevBtn.disabled = page <= 1;
	prevBtn.addEventListener('click', () => { page--; renderTable(); });
	pc.appendChild(prevBtn);
	const range = 2;
	for (let p2 = Math.max(1, page-range); p2 <= Math.min(pages, page+range); p2++) {
		const b = document.createElement('button');
		b.className = 'pagination-btn' + (p2 === page ? ' active' : '');
		b.textContent = p2;
		b.addEventListener('click', () => { page = p2; renderTable(); });
		pc.appendChild(b);
	}
	const nextBtn = document.createElement('button');
	nextBtn.className = 'pagination-btn'; nextBtn.textContent = 'Next →'; nextBtn.disabled = page >= pages;
	nextBtn.addEventListener('click', () => { page++; renderTable(); });
	pc.appendChild(nextBtn);
}

/* ── Sort headers ── */
document.querySelectorAll('.prod-table th[data-col]').forEach(th => {
	th.addEventListener('click', function() {
		const col = this.dataset.col;
		if (sortCol === col) { sortDir = sortDir === 'asc' ? 'desc' : 'asc'; }
		else { sortCol = col; sortDir = 'desc'; }
		document.querySelectorAll('.prod-table th').forEach(t => t.classList.remove('sort-asc','sort-desc'));
		this.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
		renderTable();
	});
});

/* ── Filter defaults ── */
function setDefaultDates() {
	const now  = new Date();
	const from = new Date(now.getFullYear(), now.getMonth(), 1);
	document.getElementById('filter-from').value = from.toISOString().slice(0,10);
	document.getElementById('filter-to').value   = now.toISOString().slice(0,10);
}

document.getElementById('btn-apply-filter').addEventListener('click', fetchSummary);
document.getElementById('btn-print').addEventListener('click', () => window.print());
document.getElementById('filter-period').addEventListener('change', function() {
	const now = new Date(); let from = new Date();
	if (this.value==='daily')   { from = now; }
	else if (this.value==='weekly')  { from = new Date(now); from.setDate(now.getDate()-6); }
	else if (this.value==='monthly') { from = new Date(now.getFullYear(), now.getMonth(), 1); }
	else if (this.value==='yearly')  { from = new Date(now.getFullYear(), 0, 1); }
	document.getElementById('filter-from').value = from.toISOString().slice(0,10);
	document.getElementById('filter-to').value   = now.toISOString().slice(0,10);
});

document.addEventListener('DOMContentLoaded', function() {
	const currentPath = window.location.pathname.replace(/\/$/, '');
	document.querySelectorAll('.nav-item').forEach(a => {
		const href = a.getAttribute('href'); if (!href) return;
		try { if (new URL(href, window.location.origin).pathname.replace(/\/$/, '') === currentPath) a.classList.add('active'); } catch(_) {}
	});
	setDefaultDates();
	fetchSummary();
});
</script>
</body>
</html>
