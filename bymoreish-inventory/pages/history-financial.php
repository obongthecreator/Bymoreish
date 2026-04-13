<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financial History – Bymoreish</title>
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
<div id="toast" class="fixed top-4 right-4 z-50 hidden px-6 py-3 rounded-xl shadow-lg text-white font-semibold"></div>

<div class="max-w-7xl mx-auto px-4 py-8">
  <h1 class="text-3xl font-bold flex items-center gap-3 mb-6">
    <iconify-icon icon="solar:chart-2-bold-duotone" class="text-yellow-400 text-4xl"></iconify-icon>
    Financial History
  </h1>

  <!-- Filters -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div><label class="block text-xs text-white/50 mb-1">From</label>
        <input type="date" id="filter-from" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">To</label>
        <input type="date" id="filter-to" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">Period</label>
        <select id="filter-period" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400">
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
          <option value="monthly" selected>Monthly</option>
        </select></div>
      <?php if (in_array($bymoreish_current_user['role'], ['admin','superadmin'])): ?>
      <div><label class="block text-xs text-white/50 mb-1">Branch</label>
        <select id="filter-branch" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400">
          <option value="">All</option><option value="1">Branch 1</option><option value="2">Branch 2</option>
        </select></div>
      <?php endif; ?>
    </div>
    <div class="flex gap-2 mt-3">
      <button onclick="loadFinancial()" class="px-5 py-2 rounded-xl text-gray-900 font-semibold text-sm" style="background:#EECE55">
        <iconify-icon icon="solar:magnifer-bold"></iconify-icon> Search
      </button>
      <button onclick="clearFilters()" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 font-semibold text-sm">Clear</button>
    </div>
  </div>

  <!-- Table -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/10 text-white/50 bg-white/5">
          <th class="text-left py-3 px-3">Date</th>
          <th class="text-right py-3 px-3">Total Sales ₦</th>
          <th class="text-right py-3 px-3">Transfer ₦</th>
          <th class="text-right py-3 px-3">Card ₦</th>
          <th class="text-right py-3 px-3">Cash ₦</th>
          <th class="text-right py-3 px-3">Expenses ₦</th>
          <th class="text-right py-3 px-3">Net Profit ₦</th>
        </tr></thead>
        <tbody id="financial-tbody"><tr><td colspan="7" class="text-center py-12 text-white/30">Loading…</td></tr></tbody>
        <tfoot id="financial-tfoot" class="border-t-2 border-white/20 bg-white/5 font-bold"></tfoot>
      </table>
    </div>
  </div>
</div>

<script src="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/js/main.js"></script>
<script>
function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-semibold ' + (ok?'bg-green-600':'bg-red-600');
  t.classList.remove('hidden');
  setTimeout(()=>t.classList.add('hidden'),3000);
}

function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtMoney(n){return '₦'+Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}

function clearFilters() {
  ['filter-from','filter-to'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  const b=document.getElementById('filter-branch'); if(b) b.value='';
  document.getElementById('filter-period').value='monthly';
  loadFinancial();
}

async function loadFinancial() {
  const fd = new FormData();
  fd.append('action','bym_get_financial_summary');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('date_from',document.getElementById('filter-from')?.value||'');
  fd.append('date_to',document.getElementById('filter-to')?.value||'');
  fd.append('period',document.getElementById('filter-period')?.value||'monthly');
  fd.append('branch',document.getElementById('filter-branch')?.value||window.bymConfig.branchId);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(!res.success){showToast(res.data||'Error.',false);return;}
  const {rows,totals} = res.data;
  const tbody = document.getElementById('financial-tbody');
  if(!rows||!rows.length){tbody.innerHTML='<tr><td colspan="7" class="text-center py-12 text-white/30">No records found.</td></tr>';document.getElementById('financial-tfoot').innerHTML='';return;}

  tbody.innerHTML = rows.map(row=>{
    const profit = Number(row.net_profit||0);
    const profitClass = profit>=0 ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
    return `<tr class="border-b border-white/5 hover:bg-white/5">
      <td class="py-3 px-3 font-medium">${escHtml(row.date||'')}</td>
      <td class="py-3 px-3 text-right font-semibold" style="color:#EECE55">${fmtMoney(row.total_sales)}</td>
      <td class="py-3 px-3 text-right text-white/70">${fmtMoney(row.transfer)}</td>
      <td class="py-3 px-3 text-right text-white/70">${fmtMoney(row.card)}</td>
      <td class="py-3 px-3 text-right text-white/70">${fmtMoney(row.cash)}</td>
      <td class="py-3 px-3 text-right text-red-400">${fmtMoney(row.expenses)}</td>
      <td class="py-3 px-3 text-right ${profitClass}">${fmtMoney(profit)}</td>
    </tr>`;
  }).join('');

  if(totals) {
    const tp = Number(totals.net_profit||0);
    document.getElementById('financial-tfoot').innerHTML = `
      <tr>
        <td class="py-3 px-3 text-white/60 uppercase text-xs tracking-wider">Totals</td>
        <td class="py-3 px-3 text-right" style="color:#EECE55">${fmtMoney(totals.total_sales)}</td>
        <td class="py-3 px-3 text-right text-white/80">${fmtMoney(totals.transfer)}</td>
        <td class="py-3 px-3 text-right text-white/80">${fmtMoney(totals.card)}</td>
        <td class="py-3 px-3 text-right text-white/80">${fmtMoney(totals.cash)}</td>
        <td class="py-3 px-3 text-right text-red-400">${fmtMoney(totals.expenses)}</td>
        <td class="py-3 px-3 text-right ${tp>=0?'text-green-400':'text-red-400'}">${fmtMoney(tp)}</td>
      </tr>`;
  }
}

const now = new Date();
const firstOfMonth = new Date(now.getFullYear(),now.getMonth(),1).toISOString().slice(0,10);
document.getElementById('filter-from').value = firstOfMonth;
document.getElementById('filter-to').value = now.toISOString().slice(0,10);
loadFinancial();
</script>
</body>
</html>
