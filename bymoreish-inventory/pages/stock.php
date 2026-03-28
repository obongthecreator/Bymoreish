<?php
/**
 * Stock Management Page – Bymoreish Inventory
 *
 * Tracks daily stock levels: opening stock, new imports, sales and
 * remaining stock. Staff enter sold quantities; everything else is
 * calculated or pulled from the database via AJAX.
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

$today = current_time( 'l, j F Y' );
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Stock Management – Bymoreish Inventory</title>

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

		/* ── Stock table ── */
		.stock-table th {
			background: rgba(76,176,80,0.07);
			color: #4CB050;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0.75rem 1rem;
			white-space: nowrap;
		}
		.stock-table td {
			padding: 0.65rem 0.75rem;
			border-bottom: 1px solid rgba(255,255,255,0.05);
			vertical-align: middle;
		}
		.stock-table tr:last-child td { border-bottom: none; }
		.stock-table tr:hover td { background: rgba(255,255,255,0.025); }
		.stock-table tfoot td {
			padding: 0.75rem 1rem;
			background: rgba(238,206,85,0.06);
			border-top: 1px solid rgba(238,206,85,0.15);
			font-weight: 700;
			color: #EECE55;
			font-size: 0.85rem;
		}

		/* ── Inputs ── */
		.bym-input {
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.12);
			border-radius: 0.5rem;
			color: #fff;
			padding: 0.45rem 0.7rem;
			font-size: 0.85rem;
			outline: none;
			transition: border-color 0.2s, box-shadow 0.2s;
			width: 100%;
		}
		.bym-input:focus {
			border-color: rgba(76,176,80,0.5);
			box-shadow: 0 0 0 3px rgba(76,176,80,0.1);
		}
		.bym-input[readonly] {
			background: rgba(255,255,255,0.03);
			color: #64748b;
			cursor: default;
		}
		.bym-input.editable {
			background: rgba(76,176,80,0.06);
			border-color: rgba(76,176,80,0.25);
			color: #fff;
		}
		.bym-input.editable:focus {
			border-color: rgba(76,176,80,0.6);
			box-shadow: 0 0 0 3px rgba(76,176,80,0.12);
		}

		/* ── Stock level indicators ── */
		.stock-low    { color: #ef4444; }
		.stock-medium { color: #f97316; }
		.stock-ok     { color: #4CB050; }

		/* ── Summary cards ── */
		.summary-card {
			background: rgba(255,255,255,0.04);
			border: 1px solid rgba(255,255,255,0.08);
			border-radius: 1rem;
			padding: 1.25rem;
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
				<h1 class="text-lg font-bold text-white leading-tight truncate">Stock Management</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">Track daily inventory levels</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/stock' ) ); ?>"
			   class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all"
			   style="background:rgba(76,176,80,0.1);border:1px solid rgba(76,176,80,0.2);color:#4CB050;">
				<iconify-icon icon="solar:history-linear" style="font-size:1rem;"></iconify-icon>
				View Stock History →
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

		<!-- ── Date banner + mobile history link ── -->
		<div class="fade-up delay-1 flex flex-wrap items-center justify-between gap-3">
			<div class="flex items-center gap-3 px-5 py-3 rounded-xl"
			     style="background:rgba(76,176,80,0.08);border:1px solid rgba(76,176,80,0.18);">
				<iconify-icon icon="solar:calendar-linear" style="font-size:1.1rem;color:#4CB050;"></iconify-icon>
				<div>
					<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stock Date</p>
					<p class="text-sm font-bold text-white"><?php echo esc_html( $today ); ?></p>
				</div>
			</div>
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/stock' ) ); ?>"
			   class="sm:hidden inline-flex items-center gap-2 text-sm font-semibold" style="color:#4CB050;">
				<iconify-icon icon="solar:history-linear"></iconify-icon>
				View Stock History →
			</a>
		</div>

		<!-- ── Summary KPI cards ── -->
		<section class="fade-up delay-1" aria-labelledby="stock-kpi-heading">
			<h2 id="stock-kpi-heading" class="sr-only">Stock Summary</h2>
			<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

				<div class="summary-card flex items-start gap-4">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
					     style="background:rgba(238,206,85,0.12);">
						<iconify-icon icon="solar:box-linear" style="color:#EECE55;font-size:1.2rem;"></iconify-icon>
					</div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 uppercase tracking-wider mb-0.5">Opening Stock</p>
						<p id="kpi-opening" class="text-xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600">From yesterday</p>
					</div>
				</div>

				<div class="summary-card flex items-start gap-4">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
					     style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:arrow-down-linear" style="color:#4CB050;font-size:1.2rem;"></iconify-icon>
					</div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 uppercase tracking-wider mb-0.5">New Imports</p>
						<p id="kpi-imports" class="text-xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600">Today's receipts</p>
					</div>
				</div>

				<div class="summary-card flex items-start gap-4">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
					     style="background:rgba(249,115,22,0.12);">
						<iconify-icon icon="solar:cart-check-linear" style="color:#f97316;font-size:1.2rem;"></iconify-icon>
					</div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 uppercase tracking-wider mb-0.5">Sold Today</p>
						<p id="kpi-sold" class="text-xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600">Units sold</p>
					</div>
				</div>

				<div class="summary-card flex items-start gap-4">
					<div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
					     style="background:rgba(99,102,241,0.12);">
						<iconify-icon icon="solar:layers-linear" style="color:#818cf8;font-size:1.2rem;"></iconify-icon>
					</div>
					<div class="min-w-0">
						<p class="text-xs text-gray-500 uppercase tracking-wider mb-0.5">Closing Stock</p>
						<p id="kpi-closing" class="text-xl font-extrabold text-white tabular-nums">—</p>
						<p class="text-xs text-gray-600">Remaining units</p>
					</div>
				</div>

			</div>
		</section>

		<!-- ── Stock table ── -->
		<section class="fade-up delay-2">
			<div class="glass rounded-2xl overflow-hidden">

				<!-- Section header -->
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(76,176,80,0.12);">
							<iconify-icon icon="solar:box-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Daily Stock Sheet</h2>
					</div>
					<div id="stock-loading" class="hidden flex items-center gap-2 text-xs text-gray-400">
						<iconify-icon icon="solar:refresh-linear" class="animate-spin"></iconify-icon>
						Loading…
					</div>
				</div>

				<!-- Table -->
				<div class="overflow-x-auto">
					<table class="stock-table w-full text-sm text-white" id="stock-items-table">
						<thead>
							<tr>
								<th>Item</th>
								<th class="w-28 text-center">In Stock</th>
								<th class="w-28 text-center">New Stock</th>
								<th class="w-28 text-center">Total Stock</th>
								<th class="w-32 text-center">Sold Stock</th>
								<th class="w-28 text-center">Stock Left</th>
							</tr>
						</thead>
						<tbody id="stock-items-body">
							<tr id="stock-empty-row">
								<td colspan="6" class="text-center py-10 text-gray-500 text-xs">
									<iconify-icon icon="solar:refresh-linear" class="animate-spin text-xl block mx-auto mb-2"></iconify-icon>
									Loading stock items…
								</td>
							</tr>
						</tbody>
						<tfoot>
							<tr id="stock-totals-row">
								<td class="text-xs uppercase tracking-wider" style="color:#EECE55;">
									<iconify-icon icon="solar:calculator-minimalistic-linear" class="inline mr-1"></iconify-icon>
									Totals
								</td>
								<td class="text-center tabular-nums" id="total-in-stock">—</td>
								<td class="text-center tabular-nums" id="total-new-stock">—</td>
								<td class="text-center tabular-nums" id="total-total-stock">—</td>
								<td class="text-center tabular-nums" id="total-sold-stock">—</td>
								<td class="text-center tabular-nums" id="total-stock-left">—</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</section>

		<!-- ── Remarks + Submit ── -->
		<section class="fade-up delay-3">
			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

				<!-- Remarks -->
				<div class="lg:col-span-2 glass rounded-2xl p-6 space-y-4">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:notes-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Staff Remarks</h2>
					</div>
					<textarea id="stock-remarks" rows="4"
					          placeholder="Add any notes about today's stock — discrepancies, spoilage, supplier issues…"
					          class="bym-input resize-none w-full"
					          style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:0.75rem;color:#fff;padding:0.75rem 1rem;font-size:0.875rem;outline:none;transition:border-color 0.2s;height:auto;"></textarea>
				</div>

				<!-- Submit card -->
				<div class="glass rounded-2xl p-6 flex flex-col justify-between gap-4">
					<div>
						<div class="flex items-center gap-3 mb-3">
							<div class="w-8 h-8 rounded-lg flex items-center justify-center"
							     style="background:rgba(76,176,80,0.12);">
								<iconify-icon icon="solar:check-circle-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
							</div>
							<h2 class="text-sm font-bold text-white">Save Stock Record</h2>
						</div>
						<p class="text-xs text-gray-500 leading-relaxed">
							Once saved, today's stock record will be stored and used as the
							opening balance for tomorrow.
						</p>
					</div>

					<!-- Status indicator -->
					<div id="stock-save-status" class="hidden rounded-xl px-4 py-3 text-xs font-medium"></div>

					<!-- Submit -->
					<button id="submit-stock" type="button"
					        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-full text-sm font-bold transition-all duration-300 hover:scale-105 active:scale-95"
					        style="background:linear-gradient(135deg,#4CB050,#3a9040);color:#fff;">
						<iconify-icon icon="solar:diskette-linear" style="font-size:1.2rem;"></iconify-icon>
						Save Stock Record
					</button>
				</div>
			</div>
		</section>

	</div><!-- /.page body -->
</div><!-- /#main-content -->

<!-- ================================================================
     SCRIPTS
     ================================================================ -->
<script>
	/* ── bymConfig global ── */
	window.bymConfig = {
		ajaxUrl:   <?php echo wp_json_encode( $ajax_url ); ?>,
		nonce:     <?php echo wp_json_encode( $nonce ); ?>,
		userId:    <?php echo (int) $user_id; ?>,
		userRole:  <?php echo wp_json_encode( $user_role ); ?>,
		branchId:  <?php echo wp_json_encode( $branch_id ); ?>,
		pluginUrl: <?php echo wp_json_encode( $plugin_url ); ?>,
		today:     <?php echo wp_json_encode( current_time( 'Y-m-d' ) ); ?>,
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

	/* ── Textarea auto-resize ── */
	(function () {
		const ta = document.getElementById('stock-remarks');
		if (!ta) return;
		ta.addEventListener('input', function () {
			this.style.height = 'auto';
			this.style.height = this.scrollHeight + 'px';
		});
	})();
</script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/pages/stock.js"></script>
</body>
</html>
