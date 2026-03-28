<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense History – Bymoreish</title>
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
    <iconify-icon icon="solar:bill-list-bold-duotone" class="text-yellow-400 text-4xl"></iconify-icon>
    Expense History
  </h1>

  <!-- Filters -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
      <div><label class="block text-xs text-white/50 mb-1">From</label>
        <input type="date" id="filter-from" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">To</label>
        <input type="date" id="filter-to" class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
      <div><label class="block text-xs text-white/50 mb-1">Search</label>
        <input type="text" id="filter-search" placeholder="Staff / Remarks…"
          class="w-full bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400"></div>
    </div>
    <div class="flex gap-2 mt-3">
      <button onclick="loadExpenses(1)" class="px-5 py-2 rounded-xl text-gray-900 font-semibold text-sm" style="background:#EECE55">
        <iconify-icon icon="solar:magnifer-bold"></iconify-icon> Search
      </button>
      <button onclick="clearFilters()" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 font-semibold text-sm">Clear</button>
    </div>
  </div>

  <!-- Summary -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-4 mb-6 flex items-center gap-3">
    <iconify-icon icon="solar:wallet-bold-duotone" class="text-red-400 text-3xl"></iconify-icon>
    <div><p class="text-xs text-white/50">Total Expenses This Month</p><p id="kpi-total" class="text-2xl font-bold">–</p></div>
  </div>

  <!-- Table -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-white/10 text-white/50 bg-white/5">
          <th class="text-left py-3 px-3 w-8"></th>
          <th class="text-left py-3 px-3">Date</th>
          <th class="text-left py-3 px-3">Staff</th>
          <th class="text-right py-3 px-3">Items</th>
          <th class="text-right py-3 px-3">Grand Total ₦</th>
          <th class="text-left py-3 px-3">Remarks</th>
          <th class="text-left py-3 px-3">Actions</th>
        </tr></thead>
        <tbody id="expenses-tbody"><tr><td colspan="7" class="text-center py-12 text-white/30">Loading…</td></tr></tbody>
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
const isSuperadmin = window.bymConfig.userRole === 'superadmin';
const isAdmin = ['admin','superadmin'].includes(window.bymConfig.userRole);

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
  ['filter-from','filter-to','filter-search'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  loadExpenses(1);
}

async function loadExpenses(page=1) {
  currentPage = page;
  const fd = new FormData();
  fd.append('action','bym_get_expenses');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('page',page);
  fd.append('per_page',20);
  fd.append('date_from',document.getElementById('filter-from')?.value||'');
  fd.append('date_to',document.getElementById('filter-to')?.value||'');
  fd.append('search',document.getElementById('filter-search')?.value||'');
  fd.append('branch',window.bymConfig.branchId);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(!res.success){showToast(res.data||'Error.',false);return;}
  const {expenses,total,monthly_total,pages} = res.data;
  document.getElementById('kpi-total').textContent = fmtMoney(monthly_total);
  const tbody = document.getElementById('expenses-tbody');
  if(!expenses||!expenses.length){tbody.innerHTML='<tr><td colspan="7" class="text-center py-12 text-white/30">No expenses found.</td></tr>';return;}
  tbody.innerHTML = expenses.map(e=>`
    <tr class="border-b border-white/5 hover:bg-white/5">
      <td class="py-3 px-3">
        <button onclick="toggleExpenseDetail(this,${e.id})" class="text-yellow-400 hover:text-yellow-300 transition-transform" id="expand-${e.id}">
          <iconify-icon icon="solar:alt-arrow-right-bold"></iconify-icon>
        </button>
      </td>
      <td class="py-3 px-3 text-white/70">${escHtml(e.date||'')}</td>
      <td class="py-3 px-3 font-medium">${escHtml(e.staff_name||'')}</td>
      <td class="py-3 px-3 text-right text-white/60">${escHtml(String(e.item_count||0))}</td>
      <td class="py-3 px-3 text-right font-bold text-red-400">${fmtMoney(e.grand_total)}</td>
      <td class="py-3 px-3 text-white/50 italic">${escHtml(e.remarks||'')}</td>
      <td class="py-3 px-3">
        ${isAdmin?`<button onclick="deleteExpense(${e.id})" class="p-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/40 text-red-400"><iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon></button>`:''}
      </td>
    </tr>
    <tr id="exp-detail-${e.id}" class="hidden bg-white/5">
      <td colspan="7" class="px-6 py-4" id="exp-content-${e.id}">
        <div class="text-white/40 text-sm">Loading items…</div>
      </td>
    </tr>`).join('');

  document.getElementById('pagination-info').textContent = `Page ${page} of ${pages||1} (${total} entries)`;
  const btns = document.getElementById('pagination-btns');
  btns.innerHTML='';
  for(let p=1;p<=(pages||1);p++){
    const b=document.createElement('button');
    b.textContent=p;
    b.className=`px-3 py-1 rounded-lg text-sm font-semibold ${p===page?'text-gray-900':'bg-white/10 hover:bg-white/20 text-white'}`;
    if(p===page) b.style.background='#EECE55';
    b.onclick=()=>loadExpenses(p);
    btns.appendChild(b);
  }
}

async function toggleExpenseDetail(btn, expId) {
  const row = document.getElementById(`exp-detail-${expId}`);
  const icon = document.getElementById(`expand-${expId}`)?.querySelector('iconify-icon');
  if(row.classList.contains('hidden')) {
    row.classList.remove('hidden');
    if(icon) icon.setAttribute('icon','solar:alt-arrow-down-bold');
    const content = document.getElementById(`exp-content-${expId}`);
    const fd = new FormData();
    fd.append('action','bym_get_expense_items');
    fd.append('nonce',window.bymConfig.nonce);
    fd.append('expense_id',expId);
    const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
    const res = await r.json();
    if(res.success && res.data.length) {
      content.innerHTML = `<div class="text-xs text-white/50 mb-2 font-semibold uppercase">Expense Items</div>
        <table class="text-sm w-full max-w-md">
          <thead><tr class="text-white/40"><th class="text-left py-1">Item</th><th class="text-right py-1">Qty</th><th class="text-right py-1">Unit Cost</th><th class="text-right py-1">Total</th></tr></thead>
          <tbody>${res.data.map(i=>`<tr class="border-t border-white/10">
            <td class="py-1">${escHtml(i.name)}</td>
            <td class="py-1 text-right">${escHtml(String(i.qty))}</td>
            <td class="py-1 text-right">${fmtMoney(i.unit_cost)}</td>
            <td class="py-1 text-right font-semibold text-red-400">${fmtMoney(i.total)}</td>
          </tr>`).join('')}</tbody>
        </table>`;
    } else content.innerHTML = '<div class="text-white/40 text-sm">No items.</div>';
  } else {
    row.classList.add('hidden');
    if(icon) icon.setAttribute('icon','solar:alt-arrow-right-bold');
  }
}

async function deleteExpense(id) {
  if(!confirm('Delete this expense record?')) return;
  const fd = new FormData();
  fd.append('action','bym_delete_expense');
  fd.append('nonce',window.bymConfig.nonce);
  fd.append('id',id);
  const r = await fetch(window.bymConfig.ajaxUrl,{method:'POST',body:fd});
  const res = await r.json();
  if(res.success){showToast('Expense deleted.');loadExpenses(currentPage);}
  else showToast(res.data||'Error.',false);
}

const today = new Date().toISOString().slice(0,10);
const firstOfMonth = new Date(new Date().getFullYear(),new Date().getMonth(),1).toISOString().slice(0,10);
document.getElementById('filter-from').value = firstOfMonth;
document.getElementById('filter-to').value = today;
loadExpenses(1);
</script>
</body>
</html>
