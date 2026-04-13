<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock History – Bymoreish</title>
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
    <iconify-icon icon="solar:box-bold-duotone" class="text-yellow-400 text-4xl"></iconify-icon>
    Stock History
  </h1>

  <!-- Filters -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div><label class="block text-xs text-white/50 mb-1">From</label>
        <input type="date" id="filter-from" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">To</label>
        <input type="date" id="filter-to" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">Product</label>
        <input type="text" id="filter-product" placeholder="Search product…"
          class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <?php if (in_array($bymoreish_current_user['role'], ['admin','superadmin'])): ?>
      <div><label class="block text-xs text-white/50 mb-1">Branch</label>
        <select id="filter-branch" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400">
          <option value="">All</option><option value="1">Branch 1</option><option value="2">Branch 2</option>
        </select></div>
      <?php endif; ?>
    </div>
    <div class="flex gap-2 mt-3">
      <button onclick="loadStock(1)" class="px-5 py-2 rounded-xl text-gray-900 font-semibold text-sm" style="background:#EECE55">
        <iconify-icon icon="solar:magnifer-bold"></iconify-icon> Search
      </button>
      <button onclick="clearFilters()" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 font-semibold text-sm">Clear</button>
    </div>
  </div>

  <!-- Summary -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6 flex items-center gap-3">
    <iconify-icon icon="solar:layers-bold-duotone" class="text-yellow-400 text-3xl"></iconify-icon>
    <div><p class="text-xs text-white/50">Total Entries</p><p id="kpi-total" class="text-2xl font-bold">–</p></div>
  </div>

  <!-- Table -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/10 text-white/50 bg-white/5">
          <th class="text-left py-3 px-3">Date</th>
          <th class="text-left py-3 px-3">Product</th>
          <th class="text-right py-3 px-3">In Stock</th>
          <th class="text-right py-3 px-3">New Stock</th>
          <th class="text-right py-3 px-3">Total Stock</th>
          <th class="text-right py-3 px-3">Sold</th>
          <th class="text-right py-3 px-3">Stock Left</th>
          <th class="text-left py-3 px-3">Staff</th>
          <th class="text-left py-3 px-3">Remarks</th>
        </tr></thead>
        <tbody id="stock-tbody"><tr><td colspan="9" class="text-center py-12 text-white/30">Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="flex items-center justify-between px-4 py-3 border-t border-white/10">
      <span id="pagination-info" class="text-sm text-white/50"></span>
      <div class="flex gap-2" id="pagination-btns"></div>
    </div>
  </div>
</div>

<script src="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/js/main.js"></script>
<script>
let currentPage = 1;

function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-semibold ' + (ok?'bg-green-600':'bg-red-600');
  t.classList.remove('hidden');
  setTimeout(()=>t.classList.add('hidden'),3000);
}

function escHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function clearFilters() {
  ['filter-from','filter-to','filter-product'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  const b=document.getElementById('filter-branch'); if(b) b.value='';
  loadStock(1);
}

async function loadStock(page=1) {
  currentPage = page;
  const fd = new FormData();
  fd.append('action','bym_get_stock');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('page',page);
  fd.append('per_page',20);
  fd.append('date_from',document.getElementById('filter-from')?.value||'');
  fd.append('date_to',document.getElementById('filter-to')?.value||'');
  fd.append('product',document.getElementById('filter-product')?.value||'');
  fd.append('branch',document.getElementById('filter-branch')?.value||window.bymConfig.branchId);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(!res.success){showToast(res.data||'Error.',false);return;}
  const {records,total,pages} = res.data;
  document.getElementById('kpi-total').textContent = total||0;
  const tbody = document.getElementById('stock-tbody');
  if(!records||!records.length){tbody.innerHTML='<tr><td colspan="9" class="text-center py-12 text-white/30">No records found.</td></tr>';return;}
  tbody.innerHTML = records.map(s=>`
    <tr class="border-b border-white/5 hover:bg-white/5">
      <td class="py-3 px-3 text-white/70">${escHtml(s.date||'')}</td>
      <td class="py-3 px-3 font-medium">${escHtml(s.product_name||'')}</td>
      <td class="py-3 px-3 text-right text-white/70">${escHtml(String(s.in_stock||0))}</td>
      <td class="py-3 px-3 text-right text-green-400 font-semibold">+${escHtml(String(s.new_stock||0))}</td>
      <td class="py-3 px-3 text-right font-semibold">${escHtml(String(s.total_stock||0))}</td>
      <td class="py-3 px-3 text-right text-red-400">${escHtml(String(s.sold_stock||0))}</td>
      <td class="py-3 px-3 text-right font-bold" style="color:#4CB050">${escHtml(String(s.stock_left||0))}</td>
      <td class="py-3 px-3 text-white/60">${escHtml(s.staff_name||'')}</td>
      <td class="py-3 px-3 text-white/50 italic">${escHtml(s.remarks||'')}</td>
    </tr>`).join('');

  document.getElementById('pagination-info').textContent = `Page ${page} of ${pages||1} (${total} records)`;
  const btns = document.getElementById('pagination-btns');
  btns.innerHTML='';
  for(let p=1;p<=(pages||1);p++){
    const b=document.createElement('button');
    b.textContent=p;
    b.className=`px-3 py-1 rounded-lg text-sm font-semibold transition-colors ${p===page?'text-gray-900':'bg-white/10 hover:bg-white/20 text-white'}`;
    if(p===page) b.style.background='#EECE55';
    b.onclick=()=>loadStock(p);
    btns.appendChild(b);
  }
}

const today = new Date().toISOString().slice(0,10);
document.getElementById('filter-from').value = today;
document.getElementById('filter-to').value = today;
loadStock(1);
</script>
</body>
</html>
