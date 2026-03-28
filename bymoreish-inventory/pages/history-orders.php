<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order History – Bymoreish</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.iconify.design/iconify-icon/1.0.8/iconify-icon.min.js"></script>
<link rel="stylesheet" href="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/css/style.css">
<script>
window.bymConfig = {
  ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
  nonce: '<?php echo esc_js($bymoreish_nonce ?? ''); ?>',
  userId: <?php echo intval($bymoreish_current_user['id'] ?? 0); ?>,
  userRole: '<?php echo esc_js($bymoreish_current_user['role'] ?? ''); ?>',
  branchId: <?php echo intval($bymoreish_current_user['branch'] ?? 1); ?>,
  pluginUrl: '<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>'
};
</script>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white">
<div id="toast" class="fixed top-4 right-4 z-50 hidden px-6 py-3 rounded-xl shadow-lg text-white font-semibold transition-all"></div>

<!-- Receipt Modal -->
<div id="receipt-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl p-4 w-full max-w-sm max-h-[90vh] overflow-y-auto text-gray-900">
    <div class="flex justify-between items-center mb-3">
      <button onclick="printReceipt()" class="px-4 py-2 rounded-xl text-white text-sm font-semibold flex items-center gap-1" style="background:#4CB050">
        <iconify-icon icon="solar:printer-bold"></iconify-icon> Print
      </button>
      <button onclick="closeReceiptModal()" class="text-gray-400 hover:text-gray-700"><iconify-icon icon="solar:close-circle-bold" class="text-2xl"></iconify-icon></button>
    </div>
    <div id="receipt-content"></div>
  </div>
</div>

<div class="max-w-7xl mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold flex items-center gap-3 mb-6">
    <iconify-icon icon="solar:clipboard-list-bold-duotone" class="text-yellow-400 text-4xl"></iconify-icon>
    Order History
  </h1>

  <!-- Filters -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <div><label class="block text-xs text-white/50 mb-1">From</label>
        <input type="date" id="filter-from" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">To</label>
        <input type="date" id="filter-to" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <?php if (in_array($bymoreish_current_user['role'], ['admin','superadmin'])): ?>
      <div><label class="block text-xs text-white/50 mb-1">Branch</label>
        <select id="filter-branch" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400">
          <option value="">All Branches</option>
          <option value="1">Branch 1</option>
          <option value="2">Branch 2</option>
        </select></div>
      <?php endif; ?>
      <div><label class="block text-xs text-white/50 mb-1">Status</label>
        <select id="filter-status" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400">
          <option value="">All</option>
          <option value="prepared">Prepared</option>
          <option value="delivered">Delivered</option>
        </select></div>
      <div><label class="block text-xs text-white/50 mb-1">Search</label>
        <input type="text" id="filter-search" placeholder="Customer / Order #"
          class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
    </div>
    <div class="flex gap-2 mt-3">
      <button onclick="loadOrders(1)" class="px-5 py-2 rounded-xl text-gray-900 font-semibold text-sm" style="background:#EECE55">
        <iconify-icon icon="solar:magnifer-bold"></iconify-icon> Search
      </button>
      <button onclick="clearFilters()" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 font-semibold text-sm">Clear</button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php foreach([
      ['kpi-total-orders','Total Orders','solar:bag-5-bold-duotone','text-yellow-400'],
      ['kpi-revenue','Total Revenue','solar:wallet-money-bold-duotone','text-green-400'],
      ['kpi-delivered','Delivered','solar:check-circle-bold-duotone','text-blue-400'],
      ['kpi-pending','Pending','solar:clock-circle-bold-duotone','text-orange-400'],
    ] as [$id,$label,$icon,$color]): ?>
    <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 flex items-center gap-3">
      <iconify-icon icon="<?php echo esc_attr($icon); ?>" class="<?php echo esc_attr($color); ?> text-3xl flex-shrink-0"></iconify-icon>
      <div><p class="text-xs text-white/50"><?php echo esc_html($label); ?></p>
        <p id="<?php echo esc_attr($id); ?>" class="text-xl font-bold">–</p></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Table -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/10 text-white/50 bg-white/5">
          <th class="text-left py-3 px-3">Order #</th>
          <th class="text-left py-3 px-3">Date / Time</th>
          <th class="text-left py-3 px-3">Customer</th>
          <th class="text-left py-3 px-3">Staff</th>
          <th class="text-left py-3 px-3">Items</th>
          <th class="text-left py-3 px-3">Payment</th>
          <th class="text-left py-3 px-3">Total ₦</th>
          <th class="text-left py-3 px-3">Status</th>
          <th class="text-left py-3 px-3">Actions</th>
        </tr></thead>
        <tbody id="orders-tbody"><tr><td colspan="9" class="text-center py-12 text-white/30">Loading orders…</td></tr></tbody>
      </table>
    </div>
    <!-- Pagination -->
    <div class="flex items-center justify-between px-4 py-3 border-t border-white/10">
      <span id="pagination-info" class="text-sm text-white/50"></span>
      <div class="flex gap-2" id="pagination-btns"></div>
    </div>
  </div>
</div>

<script src="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/js/main.js"></script>
<script>
let currentPage = 1;
const isSuperadmin = window.bymConfig.userRole === 'superadmin';

function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-semibold ' + (ok ? 'bg-green-600' : 'bg-red-600');
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3000);
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtMoney(n) { return '₦' + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }

function clearFilters() {
  ['filter-from','filter-to','filter-search'].forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
  ['filter-branch','filter-status'].forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
  loadOrders(1);
}

async function loadOrders(page=1) {
  currentPage = page;
  const params = {
    page,
    per_page: 20,
    date_from: document.getElementById('filter-from')?.value || '',
    date_to: document.getElementById('filter-to')?.value || '',
    status: document.getElementById('filter-status')?.value || '',
    search: document.getElementById('filter-search')?.value || '',
    branch: document.getElementById('filter-branch')?.value || window.bymConfig.branchId
  };
  const fd = new FormData();
  fd.append('action','bym_get_orders');
  fd.append('nonce', window.bymConfig.nonce);
  for(const [k,v] of Object.entries(params)) fd.append(k,v);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if (!res.success) { showToast(res.data||'Error loading orders.',false); return; }

  const {orders, total, total_revenue, delivered, pending, pages} = res.data;
  document.getElementById('kpi-total-orders').textContent = total||0;
  document.getElementById('kpi-revenue').textContent = fmtMoney(total_revenue);
  document.getElementById('kpi-delivered').textContent = delivered||0;
  document.getElementById('kpi-pending').textContent = pending||0;

  const tbody = document.getElementById('orders-tbody');
  if (!orders || !orders.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-12 text-white/30">No orders found.</td></tr>';
    document.getElementById('pagination-info').textContent = '';
    document.getElementById('pagination-btns').innerHTML = '';
    return;
  }

  const statusColors = {prepared:'bg-yellow-500/20 text-yellow-300', delivered:'bg-green-500/20 text-green-400', cancelled:'bg-red-500/20 text-red-400'};

  tbody.innerHTML = orders.map(o => {
    const sc = statusColors[o.status] || 'bg-white/10 text-white/60';
    return `<tr class="border-b border-white/5 hover:bg-white/5 cursor-pointer" onclick="toggleOrderDetail(this,${o.id})">
      <td class="py-3 px-3 font-mono font-bold text-yellow-400">#${escHtml(String(o.order_number||o.id))}</td>
      <td class="py-3 px-3 text-white/70">${escHtml(o.created_at||'')}</td>
      <td class="py-3 px-3">${escHtml(o.customer_name||'Walk-in')}</td>
      <td class="py-3 px-3 text-white/60">${escHtml(o.staff_name||'')}</td>
      <td class="py-3 px-3 text-center">${escHtml(String(o.item_count||0))}</td>
      <td class="py-3 px-3 text-white/60 capitalize">${escHtml(o.payment_mode||'')}</td>
      <td class="py-3 px-3 font-semibold">${fmtMoney(o.total)}</td>
      <td class="py-3 px-3"><span class="px-2 py-0.5 rounded-full text-xs font-bold ${sc}">${escHtml(o.status||'')}</span></td>
      <td class="py-3 px-3 flex gap-2" onclick="event.stopPropagation()">
        <button onclick="showReceipt(${o.id})" class="p-1.5 rounded-lg bg-blue-500/20 hover:bg-blue-500/40 text-blue-400" title="Receipt">
          <iconify-icon icon="solar:printer-bold"></iconify-icon></button>
        ${isSuperadmin?`<button onclick="deleteOrder(${o.id})" class="p-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/40 text-red-400" title="Delete"><iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon></button>`:''}
      </td>
    </tr>
    <tr id="detail-row-${o.id}" class="hidden bg-white/5">
      <td colspan="9" class="px-6 py-4" id="detail-content-${o.id}">
        <div class="text-white/40 text-sm">Loading items…</div>
      </td>
    </tr>`;
  }).join('');

  document.getElementById('pagination-info').textContent = `Page ${page} of ${pages||1} (${total} orders)`;
  const btns = document.getElementById('pagination-btns');
  btns.innerHTML = '';
  for(let p=1;p<=(pages||1);p++) {
    const b = document.createElement('button');
    b.textContent = p;
    b.className = `px-3 py-1 rounded-lg text-sm font-semibold transition-colors ${p===page?'text-gray-900':'bg-white/10 hover:bg-white/20 text-white'}`;
    if(p===page) b.style.background='#EECE55';
    b.onclick = () => loadOrders(p);
    btns.appendChild(b);
  }
}

async function toggleOrderDetail(row, orderId) {
  const detailRow = document.getElementById(`detail-row-${orderId}`);
  if (detailRow.classList.contains('hidden')) {
    detailRow.classList.remove('hidden');
    const content = document.getElementById(`detail-content-${orderId}`);
    const fd = new FormData();
    fd.append('action','bym_get_order_items');
    fd.append('nonce',window.bymConfig.nonce);
    fd.append('order_id',orderId);
    const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
    const res = await r.json();
    if(res.success && res.data.length) {
      content.innerHTML = `<div class="text-xs text-white/50 mb-2 font-semibold uppercase">Order Items</div>
        <table class="text-sm w-full max-w-lg">
          <thead><tr class="text-white/40"><th class="text-left py-1">Item</th><th class="text-left py-1">Qty</th><th class="text-left py-1">Price</th><th class="text-left py-1">Total</th></tr></thead>
          <tbody>${res.data.map(i=>`<tr class="border-t border-white/10">
            <td class="py-1">${escHtml(i.name)}${i.extras?`<div class="text-xs text-white/40">${escHtml(i.extras)}</div>`:''}</td>
            <td class="py-1">${escHtml(String(i.qty))}</td>
            <td class="py-1">${fmtMoney(i.unit_price)}</td>
            <td class="py-1 font-semibold">${fmtMoney(i.total)}</td>
          </tr>`).join('')}</tbody>
        </table>`;
    } else content.innerHTML = '<div class="text-white/40 text-sm">No items found.</div>';
  } else detailRow.classList.add('hidden');
}

async function showReceipt(orderId) {
  const fd = new FormData();
  fd.append('action','bym_get_receipt');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('order_id',orderId);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(res.success) {
    document.getElementById('receipt-content').innerHTML = res.data.html || '';
    document.getElementById('receipt-modal').classList.remove('hidden');
  } else showToast(res.data||'Error loading receipt.',false);
}

function closeReceiptModal() { document.getElementById('receipt-modal').classList.add('hidden'); }

function printReceipt() { window.print(); }

async function deleteOrder(id) {
  if(!confirm('Delete this order?')) return;
  const fd = new FormData();
  fd.append('action','bym_delete_order');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('id',id);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(res.success) { showToast('Order deleted.'); loadOrders(currentPage); }
  else showToast(res.data||'Error.',false);
}

const today = new Date().toISOString().slice(0,10);
document.getElementById('filter-from').value = today;
document.getElementById('filter-to').value = today;
loadOrders(1);
</script>
</body>
</html>
