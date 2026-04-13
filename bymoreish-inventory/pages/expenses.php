<?php
/**
 * Expense Management Page – Bymoreish Inventory
 *
 * Log and manage operational expenses. Supports dynamic multi-row entry,
 * auto-calculated totals and AJAX submission.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Auth guard – router sets $bymoreish_current_user before including this file.
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
$is_superadmin = $user_role === 'superadmin';

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
	$nav_items[] = [ 'href' => '/bymoreish/admin',   'icon' => 'solar:settings-linear',      'label' => 'Admin Panel' ];
}
$nav_items[] = [ 'href' => '/bymoreish/profile', 'icon' => 'solar:user-circle-linear', 'label' => 'Profile' ];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Expense Management – Bymoreish Inventory</title>

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
	<link rel="stylesheet" href="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/css/style.css">

	<style>
		body { background: #0a0a0f; overflow-x: hidden; }

		/* ── Sidebar ── */
		#sidebar { transition: transform 0.3s cubic-bezier(.4,0,.2,1); }
		@media (max-width: 1023px) {
			#sidebar      { transform: translateX(-100%); }
			#sidebar.open { transform: translateX(0); }
		}
		#sidebar-overlay       { display: none; }
		#sidebar-overlay.open  { display: block; }

		/* ── Nav active ── */
		.nav-item.active                { background: rgba(238,206,85,0.12) !important; color: #EECE55 !important; }
		.nav-item.active iconify-icon   { color: #EECE55 !important; }

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
		.fade-up  { animation: fadeUp 0.55s cubic-bezier(.22,1,.36,1) forwards; }
		.delay-1  { animation-delay: 0.08s; opacity: 0; }
		.delay-2  { animation-delay: 0.16s; opacity: 0; }
		.delay-3  { animation-delay: 0.24s; opacity: 0; }
		.delay-4  { animation-delay: 0.32s; opacity: 0; }

		/* ── Table ── */
		.exp-table th {
			background: rgba(238,206,85,0.07);
			color: #EECE55;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0.75rem 0.75rem;
			white-space: nowrap;
		}
		.exp-table td {
			padding: 0.5rem 0.75rem;
			border-bottom: 1px solid rgba(255,255,255,0.05);
			vertical-align: middle;
		}
		.exp-table tr:last-child td { border-bottom: none; }
		.exp-table tr:hover td { background: rgba(255,255,255,0.03); }
		.exp-table tfoot td {
			border-top: 1px solid rgba(238,206,85,0.2);
			padding: 0.75rem;
		}

		/* ── Inputs ── */
		.bym-input {
			background: rgba(255,255,255,0.06);
			border: 1px solid rgba(255,255,255,0.12);
			border-radius: 0.5rem;
			color: #fff;
			padding: 0.45rem 0.6rem;
			font-size: 0.83rem;
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

		/* ── Scrollbar ── */
		#sidebar::-webkit-scrollbar              { width: 4px; }
		#sidebar::-webkit-scrollbar-thumb        { background: rgba(255,255,255,0.1); border-radius: 2px; }
		#main-content::-webkit-scrollbar         { width: 6px; }
		#main-content::-webkit-scrollbar-thumb   { background: rgba(255,255,255,0.12); border-radius: 3px; }
		#main-content::-webkit-scrollbar-thumb:hover { background: rgba(238,206,85,0.3); }

		/* ── Toast ── */
		#toast-container {
			position: fixed;
			bottom: 1.5rem;
			right: 1.5rem;
			z-index: 200;
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
			pointer-events: none;
		}
		.toast {
			padding: 0.75rem 1.2rem;
			border-radius: 0.75rem;
			font-size: 0.85rem;
			font-weight: 600;
			color: #fff;
			backdrop-filter: blur(12px);
			pointer-events: auto;
			transform: translateX(120%);
			transition: transform 0.35s cubic-bezier(.22,1,.36,1);
			display: flex;
			align-items: center;
			gap: 0.6rem;
			min-width: 220px;
			max-width: 340px;
		}
		.toast.show { transform: translateX(0); }
		.toast-success { background: rgba(76,176,80,0.25); border: 1px solid rgba(76,176,80,0.4); }
		.toast-error   { background: rgba(239,68,68,0.25); border: 1px solid rgba(239,68,68,0.4); }

		/* ── Confirm modal ── */
		#confirm-modal {
			display: none;
			position: fixed; inset: 0; z-index: 100;
			background: rgba(0,0,0,0.7);
			backdrop-filter: blur(6px);
			align-items: center;
			justify-content: center;
		}
		#confirm-modal.open { display: flex; }
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
		<?php foreach ( $nav_items as $item ) :
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

	<!-- Top header -->
	<header class="sticky top-0 z-20 flex items-center justify-between gap-4 px-6 py-4"
	        style="background:rgba(10,10,15,0.85);backdrop-filter:blur(16px);border-bottom:1px solid rgba(255,255,255,0.07);">
		<div class="flex items-center gap-4 min-w-0">
			<button id="hamburger" type="button" aria-label="Open navigation menu"
			        class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 transition-all lg:hidden">
				<iconify-icon icon="solar:hamburger-menu-linear" style="font-size:1.4rem;"></iconify-icon>
			</button>
			<div class="min-w-0">
				<h1 class="text-lg font-bold text-white leading-tight truncate">Expense Management</h1>
				<p class="text-xs text-gray-500 truncate hidden sm:block">Log and track operational expenses</p>
			</div>
		</div>
		<div class="flex items-center gap-3">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/expenses' ) ); ?>"
			   class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-full text-xs font-semibold transition-all"
			   style="background:rgba(238,206,85,0.1);border:1px solid rgba(238,206,85,0.2);color:#EECE55;">
				<iconify-icon icon="solar:history-linear" style="font-size:1rem;"></iconify-icon>
				View Expense History →
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
	<div class="flex-1 px-4 sm:px-6 lg:px-8 py-8 space-y-6">

		<!-- Mobile history link -->
		<div class="sm:hidden fade-up">
			<a href="<?php echo esc_url( home_url( '/bymoreish/history/expenses' ) ); ?>"
			   class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#EECE55;">
				<iconify-icon icon="solar:history-linear"></iconify-icon>
				View Expense History →
			</a>
		</div>

		<!-- ── Summary Cards ── -->
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 fade-up delay-1">

			<!-- Today's Total -->
			<div class="glass rounded-2xl p-5 flex items-center gap-4">
				<div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
				     style="background:rgba(238,206,85,0.12);">
					<iconify-icon icon="solar:wallet-money-linear" style="font-size:1.5rem;color:#EECE55;"></iconify-icon>
				</div>
				<div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Today's Total Spent</p>
					<p id="summary-today-total" class="text-2xl font-extrabold text-white tabular-nums mt-0.5">₦0</p>
				</div>
			</div>

			<!-- Number of Entries Today -->
			<div class="glass rounded-2xl p-5 flex items-center gap-4">
				<div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
				     style="background:rgba(76,176,80,0.12);">
					<iconify-icon icon="solar:notes-linear" style="font-size:1.5rem;color:#4CB050;"></iconify-icon>
				</div>
				<div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Entries Today</p>
					<p id="summary-today-count" class="text-2xl font-extrabold text-white tabular-nums mt-0.5">0</p>
				</div>
			</div>

			<!-- This Month Total -->
			<div class="glass rounded-2xl p-5 flex items-center gap-4">
				<div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
				     style="background:rgba(238,206,85,0.12);">
					<iconify-icon icon="solar:calendar-linear" style="font-size:1.5rem;color:#EECE55;"></iconify-icon>
				</div>
				<div>
					<p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">This Month</p>
					<p id="summary-month-total" class="text-2xl font-extrabold text-white tabular-nums mt-0.5">₦0</p>
				</div>
			</div>
		</div>

		<!-- ── Expense Entry Form ── -->
		<section class="fade-up delay-2">
			<div class="glass rounded-2xl overflow-hidden">

				<!-- Section header -->
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:add-circle-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">New Expense Entry</h2>
					</div>
					<button id="btn-add-row" type="button"
					        class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-bold transition-all"
					        style="background:rgba(76,176,80,0.12);border:1px solid rgba(76,176,80,0.25);color:#4CB050;">
						<iconify-icon icon="solar:add-square-linear" style="font-size:1rem;"></iconify-icon>
						Add Row
					</button>
				</div>

				<!-- Expense rows table -->
				<div class="overflow-x-auto">
					<table class="exp-table w-full text-sm text-white" id="expense-table">
						<thead>
							<tr>
								<th class="w-10 text-center">#</th>
								<th>Description</th>
								<th class="w-44">Price (₦)</th>
								<th class="w-28">Quantity</th>
								<th class="w-44">Total (₦)</th>
								<th class="w-20 text-center">Remove</th>
							</tr>
						</thead>
						<tbody id="expense-rows">
							<!-- Initial row injected by JS -->
						</tbody>
						<tfoot>
							<tr>
								<td colspan="4" class="text-right text-xs font-bold uppercase tracking-wider"
								    style="color:#EECE55;">Grand Total</td>
								<td class="tabular-nums">
									<span id="grand-total" class="text-lg font-extrabold" style="color:#EECE55;">₦0</span>
								</td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>

				<!-- Remarks + Submit -->
				<div class="px-6 py-5 space-y-4" style="border-top:1px solid rgba(255,255,255,0.07);">
					<div class="space-y-1.5">
						<label for="expense-remarks" class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">
							Remarks <span class="text-gray-600 font-normal normal-case">(optional)</span>
						</label>
						<textarea id="expense-remarks" rows="3"
						          placeholder="Add a note about this expense batch…"
						          class="bym-input resize-none"
						          style="font-size:0.85rem;padding:0.6rem 0.75rem;"></textarea>
					</div>
					<div class="flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center">
						<p class="text-xs text-gray-500">
							<iconify-icon icon="solar:info-circle-linear" style="vertical-align:middle;margin-right:0.25rem;"></iconify-icon>
							<?php if ( $is_admin ) : ?>
								As <strong class="text-gray-300"><?php echo esc_html( $user_role ); ?></strong>,
								you can delete <?php echo $is_superadmin ? 'any' : "today's"; ?> expenses.
							<?php else : ?>
								Submitted expenses cannot be deleted by staff.
							<?php endif; ?>
						</p>
						<button id="btn-submit-expense" type="button"
						        class="pill-beam flex items-center gap-2 px-7 py-2.5 rounded-full text-sm font-bold transition-all shrink-0"
						        style="background:linear-gradient(135deg,#EECE55,#d4b043);color:#0a0a0f;">
							<iconify-icon icon="solar:check-circle-linear" style="font-size:1.1rem;"></iconify-icon>
							Submit Expense
						</button>
					</div>
				</div>
			</div>
		</section>

		<!-- ── Today's Expenses Table ── -->
		<section class="fade-up delay-3">
			<div class="glass rounded-2xl overflow-hidden">
				<div class="flex items-center justify-between px-6 py-4"
				     style="border-bottom:1px solid rgba(255,255,255,0.07);">
					<div class="flex items-center gap-3">
						<div class="w-8 h-8 rounded-lg flex items-center justify-center"
						     style="background:rgba(238,206,85,0.12);">
							<iconify-icon icon="solar:clipboard-list-linear" style="color:#EECE55;font-size:1.1rem;"></iconify-icon>
						</div>
						<h2 class="text-sm font-bold text-white">Today's Expenses</h2>
					</div>
					<button id="btn-refresh-today" type="button"
					        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs text-gray-400 hover:text-white hover:bg-white/10 transition-all">
						<iconify-icon icon="solar:refresh-linear" style="font-size:1rem;"></iconify-icon>
						Refresh
					</button>
				</div>
				<div class="overflow-x-auto">
					<table class="exp-table w-full text-sm text-white" id="today-expenses-table">
						<thead>
							<tr>
								<th class="w-10 text-center">#</th>
								<th>Description</th>
								<th class="w-44">Price (₦)</th>
								<th class="w-28">Qty</th>
								<th class="w-44">Total (₦)</th>
								<th class="w-40">Submitted By</th>
								<th class="w-32">Time</th>
								<?php if ( $is_admin ) : ?>
								<th class="w-20 text-center">Delete</th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody id="today-expenses-body">
							<tr>
								<td colspan="<?php echo $is_admin ? 8 : 7; ?>" class="text-center py-10 text-gray-500 text-xs">
									<iconify-icon icon="solar:refresh-linear" class="animate-spin text-2xl block mx-auto mb-2" style="color:#EECE55;"></iconify-icon>
									Loading today's expenses…
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</section>

	</div><!-- /page body -->
</div><!-- /main-content -->

<!-- ── Confirm Delete Modal ── -->
<div id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
	<div class="glass rounded-2xl p-6 w-full max-w-sm mx-4 space-y-4">
		<div class="flex items-center gap-3">
			<div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
			     style="background:rgba(239,68,68,0.12);">
				<iconify-icon icon="solar:trash-bin-trash-linear" style="font-size:1.3rem;color:#ef4444;"></iconify-icon>
			</div>
			<h3 id="confirm-title" class="text-base font-bold text-white">Delete Expense</h3>
		</div>
		<p class="text-sm text-gray-400">Are you sure you want to delete this expense entry? This action cannot be undone.</p>
		<div class="flex gap-3 justify-end pt-1">
			<button id="confirm-cancel" type="button"
			        class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-400 hover:text-white hover:bg-white/10 transition-all">
				Cancel
			</button>
			<button id="confirm-ok" type="button"
			        class="px-5 py-2 rounded-xl text-sm font-bold transition-all"
			        style="background:rgba(239,68,68,0.2);border:1px solid rgba(239,68,68,0.4);color:#ef4444;">
				Delete
			</button>
		</div>
	</div>
</div>

<!-- ── Toast container ── -->
<div id="toast-container" aria-live="polite"></div>

<!-- Plugin JS -->
<script src="<?php echo esc_url( BYMOREISH_PLUGIN_URL ); ?>assets/js/main.js"></script>

<script>
/* ============================================================
   Config
   ============================================================ */
window.bymConfig = {
	ajaxUrl:  '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
	nonce:    '<?php echo esc_js( $nonce ); ?>',
	userId:   <?php echo (int) $user_id; ?>,
	userRole: '<?php echo esc_js( $user_role ); ?>',
	branchId: <?php echo (int) $branch_id; ?>,
	pluginUrl:'<?php echo esc_js( BYMOREISH_PLUGIN_URL ); ?>',
};
const IS_ADMIN      = <?php echo $is_admin      ? 'true' : 'false'; ?>;
const IS_SUPERADMIN = <?php echo $is_superadmin ? 'true' : 'false'; ?>;

/* ============================================================
   Utilities
   ============================================================ */
function naira(n) {
	const num = parseFloat(String(n).replace(/[^0-9.]/g, '')) || 0;
	return '₦' + num.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function rawNum(str) {
	return parseFloat(String(str).replace(/[^0-9.]/g, '')) || 0;
}

/* ============================================================
   Clock
   ============================================================ */
function updateClock() {
	const now  = new Date();
	const time = now.toLocaleTimeString('en-NG', { hour12: false });
	const date = now.toLocaleDateString('en-NG', { weekday:'short', year:'numeric', month:'short', day:'numeric' });
	['header-clock','sidebar-clock'].forEach(function(id) {
		const el = document.getElementById(id);
		if (el) el.textContent = time;
	});
	['header-date','sidebar-date'].forEach(function(id) {
		const el = document.getElementById(id);
		if (el) el.textContent = date;
	});
}
updateClock();
setInterval(updateClock, 1000);

/* ============================================================
   Sidebar toggle (mobile)
   ============================================================ */
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebar-overlay');
const hamburger = document.getElementById('hamburger');

function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }

if (hamburger) hamburger.addEventListener('click', openSidebar);
if (overlay)   overlay.addEventListener('click', closeSidebar);
sidebar.querySelectorAll('a').forEach(function(a) {
	a.addEventListener('click', function() { if (window.innerWidth < 1024) closeSidebar(); });
});

/* ============================================================
   Toast
   ============================================================ */
function showToast(msg, type) {
	const tc   = document.getElementById('toast-container');
	const icon = type === 'success' ? 'solar:check-circle-linear' : 'solar:close-circle-linear';
	const div  = document.createElement('div');
	div.className = 'toast toast-' + type;
	div.innerHTML = '<iconify-icon icon="' + icon + '" style="font-size:1.1rem;flex-shrink:0;"></iconify-icon><span>' + msg + '</span>';
	tc.appendChild(div);
	requestAnimationFrame(function() { requestAnimationFrame(function() { div.classList.add('show'); }); });
	setTimeout(function() {
		div.classList.remove('show');
		setTimeout(function() { div.remove(); }, 400);
	}, 3500);
}

/* ============================================================
   Dynamic expense rows
   ============================================================ */
let rowCount = 0;

function createRow() {
	rowCount++;
	const idx = rowCount;
	const tr = document.createElement('tr');
	tr.dataset.idx = idx;
	tr.innerHTML =
		'<td class="text-center text-gray-500 text-xs font-semibold w-10">' + idx + '</td>' +
		'<td><input type="text" placeholder="e.g. Gas refill" class="bym-input exp-desc" data-idx="' + idx + '"></td>' +
		'<td><input type="text" inputmode="decimal" placeholder="0.00" class="bym-input exp-price" data-idx="' + idx + '"></td>' +
		'<td><input type="number" min="1" value="1" placeholder="1" class="bym-input exp-qty" data-idx="' + idx + '"></td>' +
		'<td><input type="text" readonly class="bym-input exp-total" data-idx="' + idx + '" placeholder="₦0.00"></td>' +
		'<td class="text-center">' +
			'<button type="button" class="btn-remove-row w-7 h-7 rounded-lg flex items-center justify-center mx-auto transition-all text-gray-500 hover:text-red-400 hover:bg-red-500/10" data-idx="' + idx + '">' +
				'<iconify-icon icon="solar:trash-bin-trash-linear" style="font-size:1rem;"></iconify-icon>' +
			'</button>' +
		'</td>';
	return tr;
}

function recalcRow(tr) {
	const price = rawNum(tr.querySelector('.exp-price').value);
	const qty   = parseFloat(tr.querySelector('.exp-qty').value) || 1;
	const total = price * qty;
	tr.querySelector('.exp-total').value = naira(total);
	return total;
}

function recalcAll() {
	let grand = 0;
	document.querySelectorAll('#expense-rows tr').forEach(function(tr) {
		grand += recalcRow(tr);
	});
	document.getElementById('grand-total').textContent = naira(grand);
}

function renumberRows() {
	document.querySelectorAll('#expense-rows tr').forEach(function(tr, i) {
		const numCell = tr.querySelector('td:first-child');
		if (numCell) numCell.textContent = i + 1;
	});
}

function addRow() {
	const tbody = document.getElementById('expense-rows');
	const tr    = createRow();
	tbody.appendChild(tr);
	// Bind events
	tr.querySelector('.exp-price').addEventListener('input', recalcAll);
	tr.querySelector('.exp-qty').addEventListener('input', recalcAll);
	tr.querySelector('.btn-remove-row').addEventListener('click', function() {
		if (document.querySelectorAll('#expense-rows tr').length <= 1) {
			showToast('At least one row is required.', 'error');
			return;
		}
		tr.remove();
		renumberRows();
		recalcAll();
	});
	recalcAll();
}

document.getElementById('btn-add-row').addEventListener('click', addRow);

// Seed with one row on load
addRow();

/* ============================================================
   Submit expense
   ============================================================ */
document.getElementById('btn-submit-expense').addEventListener('click', async function() {
	const rows = [];
	let valid  = true;
	document.querySelectorAll('#expense-rows tr').forEach(function(tr) {
		const desc  = tr.querySelector('.exp-desc').value.trim();
		const price = rawNum(tr.querySelector('.exp-price').value);
		const qty   = parseFloat(tr.querySelector('.exp-qty').value) || 1;
		if (!desc || price <= 0) { valid = false; return; }
		rows.push({ description: desc, price: price, quantity: qty, total: price * qty });
	});
	if (!valid) { showToast('Please fill in all descriptions and valid prices.', 'error'); return; }
	if (!rows.length) { showToast('Add at least one expense row.', 'error'); return; }

	const remarks = document.getElementById('expense-remarks').value.trim();
	const btn     = this;
	btn.disabled  = true;
	btn.innerHTML = '<iconify-icon icon="solar:refresh-linear" class="animate-spin" style="font-size:1.1rem;"></iconify-icon> Submitting…';

	try {
		const body = new URLSearchParams({
			action:   'bym_save_expense',
			nonce:    window.bymConfig.nonce,
			rows:     JSON.stringify(rows),
			remarks:  remarks,
			branch_id: window.bymConfig.branchId,
		});
		const res  = await fetch(window.bymConfig.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		});
		const data = await res.json();
		if (data && data.success) {
			showToast('Expense submitted successfully!', 'success');
			// Clear form
			document.getElementById('expense-rows').innerHTML = '';
			rowCount = 0;
			addRow();
			document.getElementById('expense-remarks').value = '';
			document.getElementById('grand-total').textContent = naira(0);
			loadTodayExpenses();
		} else {
			showToast((data && data.data) ? data.data : 'Failed to submit expense.', 'error');
		}
	} catch (e) {
		showToast('Network error. Please try again.', 'error');
	}
	btn.disabled  = false;
	btn.innerHTML = '<iconify-icon icon="solar:check-circle-linear" style="font-size:1.1rem;"></iconify-icon> Submit Expense';
});

/* ============================================================
   Today's expenses
   ============================================================ */
async function loadTodayExpenses() {
	const tbody = document.getElementById('today-expenses-body');
	const cols  = IS_ADMIN ? 8 : 7;
	tbody.innerHTML = '<tr><td colspan="' + cols + '" class="text-center py-8 text-gray-500 text-xs">' +
		'<iconify-icon icon="solar:refresh-linear" class="animate-spin text-xl block mx-auto mb-1" style="color:#EECE55;"></iconify-icon>Loading…</td></tr>';

	try {
		const body = new URLSearchParams({
			action:    'bym_get_expenses',
			nonce:     window.bymConfig.nonce,
			period:    'today',
			branch_id: window.bymConfig.branchId,
		});
		const res  = await fetch(window.bymConfig.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		});
		const data = await res.json();
		if (!data || !data.success || !data.data) {
			tbody.innerHTML = '<tr><td colspan="' + cols + '" class="text-center py-8 text-gray-500 text-xs">No expenses found for today.</td></tr>';
			return;
		}

		// Handle both structured and flat response.
		var raw = data.data;
		var expenseGroups = Array.isArray(raw) ? raw : (raw.expenses || []);
		// Flatten expense groups into individual items for display.
		var expenses = [];
		expenseGroups.forEach(function(group) {
			if (group.items && group.items.length) {
				group.items.forEach(function(item) {
					expenses.push({
						id:           group.id,
						description:  item.description || '',
						price:        item.price || 0,
						quantity:     item.quantity || 0,
						total:        item.total || 0,
						submitted_by: group.staff_name || '',
						time:         group.created_at || '',
						created_at:   group.created_at || '',
					});
				});
			} else {
				expenses.push({
					id:           group.id,
					description:  group.remarks || 'Expense',
					price:        group.grand_total || 0,
					quantity:     1,
					total:        group.grand_total || 0,
					submitted_by: group.staff_name || '',
					time:         group.created_at || '',
					created_at:   group.created_at || '',
				});
			}
		});
		let todayTotal = 0;
		let html       = '';

		expenses.forEach(function(exp, i) {
			const total = rawNum(exp.total);
			todayTotal += total;
			html += '<tr data-id="' + esc(exp.id) + '">' +
				'<td class="text-center text-gray-500 text-xs">' + (i + 1) + '</td>' +
				'<td class="text-white">' + esc(exp.description) + '</td>' +
				'<td class="tabular-nums text-gray-300">' + naira(exp.price) + '</td>' +
				'<td class="tabular-nums text-gray-300">' + esc(exp.quantity) + '</td>' +
				'<td class="tabular-nums font-semibold text-white">' + naira(total) + '</td>' +
				'<td class="text-gray-400 text-xs">' + esc(exp.submitted_by || '—') + '</td>' +
				'<td class="text-gray-500 text-xs tabular-nums">' + esc(exp.time || '—') + '</td>' +
				(IS_ADMIN ? '<td class="text-center"><button type="button" class="btn-del-expense w-7 h-7 rounded-lg flex items-center justify-center mx-auto text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition-all" data-id="' + esc(exp.id) + '" data-created="' + esc(exp.created_at || '') + '"><iconify-icon icon="solar:trash-bin-trash-linear" style="font-size:1rem;"></iconify-icon></button></td>' : '') +
				'</tr>';
		});

		tbody.innerHTML = html || '<tr><td colspan="' + cols + '" class="text-center py-8 text-gray-500 text-xs">No expenses logged today.</td></tr>';

		// Update summary cards
		const total_el = document.getElementById('summary-today-total');
		const count_el = document.getElementById('summary-today-count');
		if (total_el) total_el.textContent = naira(todayTotal);
		if (count_el) count_el.textContent = expenses.length;

		// Update month total if present in response
		if (data.month_total !== undefined) {
			const month_el = document.getElementById('summary-month-total');
			if (month_el) month_el.textContent = naira(data.month_total);
		}

		// Bind delete buttons
		if (IS_ADMIN) bindDeleteButtons();
	} catch (e) {
		tbody.innerHTML = '<tr><td colspan="' + cols + '" class="text-center py-8 text-red-400 text-xs">Failed to load expenses.</td></tr>';
	}
}

function esc(str) {
	if (str === null || str === undefined) return '';
	return String(str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
}

/* ============================================================
   Delete expense
   ============================================================ */
let pendingDeleteId = null;

function bindDeleteButtons() {
	document.querySelectorAll('.btn-del-expense').forEach(function(btn) {
		btn.addEventListener('click', function() {
			const id      = this.dataset.id;
			const created = this.dataset.created || '';
			// Superadmin can delete any; admin only today's
			if (!IS_SUPERADMIN) {
				const today = new Date().toISOString().slice(0, 10);
				if (created && !created.startsWith(today)) {
					showToast('Admins can only delete today\'s expenses.', 'error');
					return;
				}
			}
			pendingDeleteId = id;
			document.getElementById('confirm-modal').classList.add('open');
		});
	});
}

document.getElementById('confirm-cancel').addEventListener('click', function() {
	pendingDeleteId = null;
	document.getElementById('confirm-modal').classList.remove('open');
});

document.getElementById('confirm-ok').addEventListener('click', async function() {
	if (!pendingDeleteId) return;
	document.getElementById('confirm-modal').classList.remove('open');
	try {
		const body = new URLSearchParams({
			action:     'bym_delete_expense',
			nonce:      window.bymConfig.nonce,
			expense_id: pendingDeleteId,
		});
		const res  = await fetch(window.bymConfig.ajaxUrl, {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		});
		const data = await res.json();
		if (data && data.success) {
			showToast('Expense deleted.', 'success');
			loadTodayExpenses();
		} else {
			showToast((data && data.data) ? data.data : 'Delete failed.', 'error');
		}
	} catch (e) {
		showToast('Network error.', 'error');
	}
	pendingDeleteId = null;
});

document.getElementById('btn-refresh-today').addEventListener('click', loadTodayExpenses);

/* ============================================================
   Active nav + init
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
	const currentPath = window.location.pathname.replace(/\/$/, '');
	document.querySelectorAll('.nav-item').forEach(function(a) {
		const href = a.getAttribute('href');
		if (!href) return;
		try {
			const linkPath = new URL(href, window.location.origin).pathname.replace(/\/$/, '');
			if (linkPath === currentPath) a.classList.add('active');
		} catch (_) {}
	});

	loadTodayExpenses();
});
</script>
</body>
</html>
