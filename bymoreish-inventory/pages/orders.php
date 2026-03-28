<?php
/**
 * Order Management Page – Bymoreish Inventory
 *
 * Create and manage customer orders. Supports per-item extras,
 * split payment allocation and a receipt modal.
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
$nonce        = wp_create_nonce( 'bymoreish_nonce' );
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
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Order Management – Bymoreish Inventory</title>

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
		.glass-dark {
			background: rgba(0,0,0,0.35);
			backdrop-filter: blur(16px);
			-webkit-backdrop-filter: blur(16px);
			border: 1px solid rgba(255,255,255,0.08);
			box-shadow: 0 4px 24px rgba(0,0,0,0.4);
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

		/* ── Table ── */
		.order-table th {
			background: rgba(238,206,85,0.07);
			color: #EECE55;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0.75rem 1rem;
			white-space: nowrap;
		}
		.order-table td {
			padding: 0.6rem 0.75rem;
			border-bottom: 1px solid rgba(255,255,255,0.05);
			vertical-align: middle;
		}
		.order-table tr:last-child td { border-bottom: none; }
		.order-table tr:hover td { background: rgba(255,255,255,0.03); }

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
			border-color: rgba(238,206,85,0.5);
			box-shadow: 0 0 0 3px rgba(238,206,85,0.1);
		}
		.bym-input[readonly] {
			background: rgba(255,255,255,0.03);
			color: #94a3b8;
			cursor: default;
		}
		.bym-select {
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.12);
			border-radius: 0.5rem;
			color: #fff;
			padding: 0.45rem 0.7rem;
			font-size: 0.85rem;
			outline: none;
			transition: border-color 0.2s;
		}
		.bym-select:focus { border-color: rgba(238,206,85,0.5); }
		.bym-select option { background: #1a1a2e; color: #fff; }

		/* ── Payment checkbox ── */
		.pay-checkbox:checked { accent-color: #EECE55; }

		/* ── Status pills ── */
		.status-pending   { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
		.status-prepared  { background: rgba(249,115,22,0.15); color: #f97316; border: 1px solid rgba(249,115,22,0.3); }
		.status-delivered { background: rgba(76,176,80,0.15);  color: #4CB050; border: 1px solid rgba(76,176,80,0.3);  }

		/* ── Modal ── */
		#receipt-modal {
			display: none;
			position: fixed; inset: 0; z-index: 100;
			background: rgba(0,0,0,0.7);
			backdrop-filter: blur(6px);
			align-items: center;
			justify-content: center;
		}
		#receipt-modal.open { display: flex; }
		#receipt-modal .modal-box {
			background: #13131f;
			border: 1px solid rgba(255,255,255,0.1);
			border-radius: 1.25rem;
			width: 100%;
			max-width: 440px;
			max-height: 90vh;
			overflow-y: auto;
			box-shadow: 0 24px 64px rgba(0,0,0,0.6);
		}

		/* ── Print receipt ── */
		@media print {
			body > *:not(#receipt-modal) { display: none !important; }
			#receipt-modal { display: flex !important; position: static !important; background: #fff !important; }
			#receipt-modal .modal-box { background: #fff !important; color: #000 !important; max-height: none !important; box-shadow: none !important; }
			.no-print { display: none !important; }
			.print-color { color: #000 !important; }
		}

		/* ── Scrollbar ── */
		#sidebar::-webkit-scrollbar { width: 4px; }
		#sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 2px; }
		#main-content::-webkit-scrollbar { width: 6px; }
		#main-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 3px; }
		#main-content::-webkit-scrollbar-thumb:hover { background: rgba(238,206,85,0.3); }

		/* ── Extras dropdown ── */
		.extras-dropdown {
			position: absolute;
			top: calc(100% + 4px);
			left: 0;
			min-width: 220px;
			z-index: 50;
			background: #1a1a2e;
			border: 1px solid rgba(255,255,255,0.12);
			border-radius: 0.75rem;
			padding: 0.5rem;
			box-shadow: 0 12px 32px rgba(0,0,0,0.5);
			display: none;
		}
		.extras-dropdown.open { display: block; }
		.extras-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.5rem; border-radius: 0.5rem; cursor: pointer; }
		.extras-item:hover { background: rgba(255,255,255,0.06); }
		.extras-item input[type="checkbox"] { accent-color: #EECE55; width: 1rem; height: 1rem; flex-shrink: 0; }
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
				<h1 class="text-lg font-bold text-white leading-tight truncate">Order Management</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">Create and track customer orders</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/orders' ) ); ?>"
			   class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all"
			   style="background:rgba(238,206,85,0.1);border:1px solid rgba(238,206,85,0.2);color:#EECE55;">
				<iconify-icon icon="solar:history-linear" style="font-size:1rem;"></iconify-icon>
				View Order History →
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

		<!-- Mobile history link -->
		<div class="sm:hidden">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/orders' ) ); ?>"
			   class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#EECE55;">
				<iconify-icon icon="solar:history-linear"></iconify-icon>
				View Order History →
			</a>
		</div>

		<!-- ──────────────────────────────────────────────────────────
		     NEW ORDER FORM
		     ────────────────────────────────────────────────────────── -->
		<section class="fade-up delay-1">
			<div class="glass rounded-2xl overflow-hidden">

				<!-- Section header -->
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:cart-large-2-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">New Order</h2>
					</div>
					<div id="order-loading" class="hidden flex items-center gap-2 text-xs text-gray-400">
						<iconify-icon icon="solar:refresh-linear" class="animate-spin"></iconify-icon>
						Loading menu…
					</div>
				</div>

				<!-- Order items table -->
				<div class="overflow-x-auto">
					<table class="order-table w-full text-sm text-white" id="order-items-table">
						<thead>
							<tr>
								<th class="w-10">#</th>
								<th>Item</th>
								<th class="w-36">Price (₦)</th>
								<th class="w-28">Qty</th>
								<th class="w-40">Extras</th>
								<th class="w-36">Total (₦)</th>
							</tr>
						</thead>
						<tbody id="order-items-body">
							<!-- Rows injected by orders.js after AJAX menu load -->
							<tr id="order-empty-row">
								<td colspan="6" class="text-center py-8 text-gray-500 text-xs">
									<iconify-icon icon="solar:refresh-linear" class="animate-spin text-lg block mx-auto mb-2"></iconify-icon>
									Loading menu items…
								</td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<td colspan="5" class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider">
									Order Subtotal
								</td>
								<td class="px-3 py-3">
									<span id="order-subtotal" class="text-base font-extrabold text-white tabular-nums">₦0</span>
								</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</section>

		<!-- ──────────────────────────────────────────────────────────
		     CUSTOMER DETAILS
		     ────────────────────────────────────────────────────────── -->
		<section class="fade-up delay-2">
			<div class="glass rounded-2xl p-6 space-y-5">
				<div class="flex items-center gap-3 mb-1">
					<div class="w-8 h-8 rounded-lg flex items-center justify-center"
					     style="background:rgba(76,176,80,0.12);">
						<iconify-icon icon="solar:user-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
					</div>
					<h2 class="text-sm font-bold text-white">Customer Details</h2>
					<span class="text-xs text-gray-500">(Optional)</span>
				</div>

				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
					<!-- Customer Name -->
					<div class="space-y-1.5">
						<label for="customer-name" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
							Customer Name
						</label>
						<div class="relative">
							<iconify-icon icon="solar:user-circle-linear"
							              class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none"
							              style="font-size:1rem;"></iconify-icon>
							<input id="customer-name" type="text" placeholder="e.g. Adaeze Okafor"
							       class="bym-input pl-9" autocomplete="off">
						</div>
					</div>

					<!-- Customer Type -->
					<div class="space-y-1.5">
						<label for="customer-type" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
							Customer Type
						</label>
						<div class="relative">
							<iconify-icon icon="solar:users-group-rounded-linear"
							              class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none"
							              style="font-size:1rem;"></iconify-icon>
							<select id="customer-type" class="bym-select w-full pl-9">
								<option value="new">New Customer</option>
								<option value="returning">Returning Customer</option>
							</select>
						</div>
					</div>

					<!-- Phone Number -->
					<div class="space-y-1.5">
						<label for="customer-phone" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
							Phone Number
						</label>
						<div class="relative">
							<iconify-icon icon="solar:phone-linear"
							              class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none"
							              style="font-size:1rem;"></iconify-icon>
							<input id="customer-phone" type="tel" placeholder="e.g. 08012345678"
							       class="bym-input pl-9" autocomplete="off">
						</div>
					</div>

					<!-- Remarks (full width) -->
					<div class="sm:col-span-2 lg:col-span-3 space-y-1.5">
						<label for="customer-remarks" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
							Customer Remarks
						</label>
						<textarea id="customer-remarks" rows="2"
						          placeholder="Any special requests or notes…"
						          class="bym-input resize-none" style="height:auto;"></textarea>
					</div>
				</div>
			</div>
		</section>

		<!-- ──────────────────────────────────────────────────────────
		     PAYMENT MODE + GRAND TOTAL
		     ────────────────────────────────────────────────────────── -->
		<section class="fade-up delay-3">
			<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

				<!-- Payment Mode -->
				<div class="glass rounded-2xl p-6 space-y-5">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:card-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Payment Mode</h2>
					</div>

					<!-- Checkboxes -->
					<div class="flex flex-wrap gap-3">
						<label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl cursor-pointer select-none transition-all"
						       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);"
						       id="label-transfer">
							<input type="checkbox" class="pay-checkbox w-4 h-4" id="pay-transfer" value="transfer">
							<iconify-icon icon="solar:transfer-horizontal-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
							<span class="text-sm font-medium text-gray-300">Transfer</span>
						</label>
						<label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl cursor-pointer select-none transition-all"
						       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);"
						       id="label-card">
							<input type="checkbox" class="pay-checkbox w-4 h-4" id="pay-card" value="card">
							<iconify-icon icon="solar:card-2-linear" style="font-size:1rem;color:#EECE55;"></iconify-icon>
							<span class="text-sm font-medium text-gray-300">Card (POS)</span>
						</label>
						<label class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl cursor-pointer select-none transition-all"
						       style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);"
						       id="label-cash">
							<input type="checkbox" class="pay-checkbox w-4 h-4" id="pay-cash" value="cash">
							<iconify-icon icon="solar:banknote-linear" style="font-size:1rem;color:#4CB050;"></iconify-icon>
							<span class="text-sm font-medium text-gray-300">Cash</span>
						</label>
					</div>

					<!-- Amount inputs -->
					<div class="space-y-3" id="payment-inputs">
						<div class="flex items-center gap-3" id="field-transfer">
							<iconify-icon icon="solar:transfer-horizontal-linear" class="text-gray-500 shrink-0" style="font-size:1.1rem;"></iconify-icon>
							<div class="flex-1 space-y-1">
								<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Transfer Amount</p>
								<div class="relative">
									<span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold pointer-events-none">₦</span>
									<input id="amt-transfer" type="number" min="0" step="0.01" placeholder="0.00"
									       class="bym-input pl-7 tabular-nums" readonly>
								</div>
							</div>
						</div>
						<div class="flex items-center gap-3" id="field-card">
							<iconify-icon icon="solar:card-2-linear" class="text-gray-500 shrink-0" style="font-size:1.1rem;"></iconify-icon>
							<div class="flex-1 space-y-1">
								<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Card Amount</p>
								<div class="relative">
									<span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold pointer-events-none">₦</span>
									<input id="amt-card" type="number" min="0" step="0.01" placeholder="0.00"
									       class="bym-input pl-7 tabular-nums" readonly>
								</div>
							</div>
						</div>
						<div class="flex items-center gap-3" id="field-cash">
							<iconify-icon icon="solar:banknote-linear" class="text-gray-500 shrink-0" style="font-size:1.1rem;"></iconify-icon>
							<div class="flex-1 space-y-1">
								<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cash Amount</p>
								<div class="relative">
									<span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold pointer-events-none">₦</span>
									<input id="amt-cash" type="number" min="0" step="0.01" placeholder="0.00"
									       class="bym-input pl-7 tabular-nums" readonly>
								</div>
							</div>
						</div>
					</div>

					<!-- Payment note -->
					<p id="payment-hint" class="text-xs text-gray-500 italic">Select one or more payment methods above.</p>
				</div>

				<!-- Grand Total + Submit -->
				<div class="glass rounded-2xl p-6 flex flex-col justify-between space-y-6">
					<!-- Total display -->
					<div>
						<div class="flex items-center gap-3 mb-4">
							<div class="w-8 h-8 rounded-lg flex items-center justify-center"
							     style="background:rgba(76,176,80,0.12);">
								<iconify-icon icon="solar:calculator-minimalistic-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
							</div>
							<h2 class="text-sm font-bold text-white">Grand Total</h2>
						</div>
						<div class="rounded-xl p-5 text-center"
						     style="background:rgba(238,206,85,0.06);border:1px solid rgba(238,206,85,0.15);">
							<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Amount Due</p>
							<p id="grand-total" class="text-4xl font-extrabold tabular-nums" style="color:#EECE55;">₦0</p>
						</div>

						<!-- Payment confirmed -->
						<label class="flex items-center gap-3 mt-4 px-4 py-3 rounded-xl cursor-pointer select-none transition-all"
						       style="background:rgba(76,176,80,0.06);border:1px solid rgba(76,176,80,0.15);">
							<input type="checkbox" id="payment-confirmed" class="pay-checkbox w-4 h-4 flex-shrink-0">
							<div>
								<p class="text-sm font-semibold text-white">Payment Confirmed</p>
								<p class="text-xs text-gray-500">Check this to confirm full payment received</p>
							</div>
						</label>
					</div>

					<!-- Submit button -->
					<button id="submit-order" type="button" disabled
					        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-full text-sm font-bold transition-all duration-300 opacity-50 cursor-not-allowed"
					        style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
						<iconify-icon icon="solar:check-circle-linear" style="font-size:1.2rem;"></iconify-icon>
						Submit Order
					</button>
				</div>
			</div>
		</section>

		<!-- ──────────────────────────────────────────────────────────
		     TODAY'S RECENT ORDERS
		     ────────────────────────────────────────────────────────── -->
		<section class="fade-up delay-4">
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(76,176,80,0.12);">
							<iconify-icon icon="solar:list-check-linear" style="color:#4CB050;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Today's Orders</h2>
					</div>
					<button id="refresh-orders" type="button"
					        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/10 transition-all">
						<iconify-icon icon="solar:refresh-linear" style="font-size:1rem;"></iconify-icon>
						Refresh
					</button>
				</div>
				<div class="overflow-x-auto">
					<table class="order-table w-full text-sm text-white">
						<thead>
							<tr>
								<th>Order #</th>
								<th>Customer</th>
								<th>Items</th>
								<th>Total (₦)</th>
								<th>Payment</th>
								<th>Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="recent-orders-body">
							<tr>
								<td colspan="7" class="text-center py-8 text-gray-500 text-xs">
									<iconify-icon icon="solar:refresh-linear" class="animate-spin text-lg block mx-auto mb-2"></iconify-icon>
									Loading today's orders…
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

	</div><!-- /.page body -->
</div><!-- /#main-content -->

<!-- ================================================================
     RECEIPT MODAL
     ================================================================ -->
<div id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
	<div class="modal-box p-6">
		<!-- Header -->
		<div class="flex items-center justify-between mb-4">
			<div class="flex items-center gap-3">
				<div class="w-9 h-9 rounded-xl flex items-center justify-center"
				     style="background:linear-gradient(135deg,#EECE55,#d4b043);">
					<iconify-icon icon="solar:receipt-linear" style="font-size:1.2rem;color:#0a0a0f;"></iconify-icon>
				</div>
				<div>
					<h3 id="receipt-title" class="text-base font-bold text-white">Order Receipt</h3>
					<p id="receipt-order-no" class="text-xs text-gray-400">#—</p>
				</div>
			</div>
			<button id="close-receipt" type="button" class="no-print w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all">
				<iconify-icon icon="solar:close-circle-linear" style="font-size:1.2rem;"></iconify-icon>
			</button>
		</div>

		<!-- Divider -->
		<div style="border-top:1px dashed rgba(255,255,255,0.12);margin-bottom:1rem;"></div>

		<!-- Restaurant info -->
		<div class="text-center mb-4">
			<p class="text-base font-extrabold" style="color:#EECE55;">Bymoreish Restaurant</p>
			<p id="receipt-date" class="text-xs text-gray-400"></p>
		</div>

		<!-- Customer line -->
		<div class="flex justify-between text-xs text-gray-400 mb-3">
			<span>Customer:</span>
			<span id="receipt-customer" class="text-white font-medium">—</span>
		</div>

		<!-- Items list -->
		<div id="receipt-items" class="space-y-1 mb-4 text-xs"></div>

		<!-- Divider -->
		<div style="border-top:1px dashed rgba(255,255,255,0.12);margin:0.75rem 0;"></div>

		<!-- Totals -->
		<div class="space-y-1 text-xs mb-4">
			<div class="flex justify-between text-gray-400">
				<span>Subtotal</span>
				<span id="receipt-subtotal" class="tabular-nums">₦0</span>
			</div>
			<div class="flex justify-between font-extrabold text-base mt-2" style="color:#EECE55;">
				<span>TOTAL</span>
				<span id="receipt-total" class="tabular-nums">₦0</span>
			</div>
			<div class="flex justify-between text-gray-400">
				<span>Payment</span>
				<span id="receipt-payment" class="text-white">—</span>
			</div>
		</div>

		<!-- Thank you -->
		<div style="border-top:1px dashed rgba(255,255,255,0.12);padding-top:0.75rem;" class="text-center">
			<p class="text-xs text-gray-400">Thank you for choosing Bymoreish!</p>
			<p class="text-xs text-gray-600 mt-0.5">Please come again 🍽️</p>
		</div>

		<!-- Actions -->
		<div class="no-print flex gap-3 mt-5">
			<button id="print-receipt" type="button"
			        class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-full text-sm font-semibold transition-all"
			        style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
				<iconify-icon icon="solar:printer-minimalistic-linear" style="font-size:1rem;"></iconify-icon>
				Print Receipt
			</button>
			<button id="new-order-btn" type="button"
			        class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-full text-sm font-semibold transition-all"
			        style="background:rgba(76,176,80,0.15);border:1px solid rgba(76,176,80,0.3);color:#4CB050;">
				<iconify-icon icon="solar:add-circle-linear" style="font-size:1rem;"></iconify-icon>
				New Order
			</button>
		</div>
	</div>
</div>

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
	};

	/* ── Clock ── */
	(function () {
		const days  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
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

	/* ── Payment confirmed enables submit ── */
	(function () {
		const cb  = document.getElementById('payment-confirmed');
		const btn = document.getElementById('submit-order');
		if (cb && btn) {
			cb.addEventListener('change', () => {
				btn.disabled = !cb.checked;
				btn.classList.toggle('opacity-50', !cb.checked);
				btn.classList.toggle('cursor-not-allowed', !cb.checked);
				btn.classList.toggle('hover:scale-105', cb.checked);
			});
		}
	})();
</script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/pages/orders.js"></script>
</body>
</html>
