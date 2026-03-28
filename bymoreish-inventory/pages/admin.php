<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<?php if ($bymoreish_current_user['role'] === 'staff') { wp_redirect(home_url('/bymoreish')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel – Bymoreish</title>
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

<div class="max-w-7xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-8">
    <h1 class="text-3xl font-bold flex items-center gap-3">
      <iconify-icon icon="solar:settings-bold-duotone" class="text-yellow-400 text-4xl"></iconify-icon>
      Admin Panel
    </h1>
    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase" style="background:#EECE55;color:#1a1a1a"><?php echo esc_html($bymoreish_current_user['role']); ?></span>
  </div>

  <!-- Tabs -->
  <div class="flex gap-2 mb-6 border-b border-white/10 pb-0">
    <?php foreach(['products'=>'Products','users'=>'Users','settings'=>'Settings'] as $k=>$v): ?>
    <button onclick="switchTab('<?php echo $k; ?>')" id="tab-<?php echo $k; ?>"
      class="tab-btn px-6 py-3 rounded-t-xl font-semibold transition-all <?php echo $k==='products'?'bg-yellow-400 text-gray-900':'bg-white/10 text-white hover:bg-white/20'; ?>"
      data-tab="<?php echo $k; ?>"><?php echo $v; ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Products Tab -->
  <div id="panel-products" class="tab-panel">
    <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Products</h2>
        <button onclick="openProductModal()" class="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-gray-900" style="background:#EECE55">
          <iconify-icon icon="solar:add-circle-bold"></iconify-icon> Add Product
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-white/10 text-white/60">
            <th class="text-left py-3 px-2">Name</th><th class="text-left py-3 px-2">Category</th>
            <th class="text-left py-3 px-2">Price ₦</th><th class="text-left py-3 px-2">Unit</th>
            <th class="text-left py-3 px-2">Active</th><th class="text-left py-3 px-2">Actions</th>
          </tr></thead>
          <tbody id="products-tbody"><tr><td colspan="6" class="text-center py-8 text-white/40">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Users Tab -->
  <div id="panel-users" class="tab-panel hidden">
    <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Users</h2>
        <button onclick="openUserModal()" class="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-gray-900" style="background:#EECE55">
          <iconify-icon icon="solar:user-plus-bold"></iconify-icon> Add User
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="border-b border-white/10 text-white/60">
            <th class="text-left py-3 px-2">Full Name</th><th class="text-left py-3 px-2">Username</th>
            <th class="text-left py-3 px-2">Role</th><th class="text-left py-3 px-2">Branch</th>
            <th class="text-left py-3 px-2">Status</th><th class="text-left py-3 px-2">Actions</th>
          </tr></thead>
          <tbody id="users-tbody"><tr><td colspan="6" class="text-center py-8 text-white/40">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Settings Tab -->
  <div id="panel-settings" class="tab-panel hidden">
    <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 max-w-2xl">
      <h2 class="text-xl font-bold mb-6">Site Settings</h2>
      <form id="settings-form" class="space-y-4">
        <div>
          <label class="block text-sm text-white/60 mb-1">Site Name</label>
          <input id="setting-site-name" type="text" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-yellow-400" placeholder="Bymoreish Restaurant">
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Site Description</label>
          <textarea id="setting-description" rows="3" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-yellow-400" placeholder="Your restaurant tagline"></textarea>
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Logo</label>
          <input type="file" id="setting-logo" accept="image/*" class="text-white/70">
          <img id="setting-logo-preview" src="" class="mt-2 h-16 hidden rounded-lg">
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Default Branch</label>
          <select id="setting-branch" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
            <option value="1">Branch 1</option>
            <option value="2">Branch 2</option>
          </select>
        </div>
        <button type="button" onclick="saveSettings()" class="px-6 py-3 rounded-xl font-semibold text-gray-900" style="background:#EECE55">Save Settings</button>
      </form>
      <?php if ($bymoreish_current_user['role'] === 'superadmin'): ?>
      <div class="mt-8 pt-6 border-t border-red-500/30">
        <h3 class="text-red-400 font-bold mb-2">Danger Zone</h3>
        <button onclick="deleteAllRecords()" class="px-6 py-3 rounded-xl font-semibold bg-red-600 hover:bg-red-700 text-white flex items-center gap-2">
          <iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon> Delete All Records
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Product Modal -->
<div id="product-modal" class="fixed inset-0 z-40 hidden flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
  <div class="bg-gray-900 border border-white/10 rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 id="product-modal-title" class="text-lg font-bold">Add Product</h3>
      <button onclick="closeModal('product-modal')" class="text-white/40 hover:text-white"><iconify-icon icon="solar:close-circle-bold" class="text-2xl"></iconify-icon></button>
    </div>
    <form id="product-form" class="space-y-4">
      <input type="hidden" id="product-id">
      <div><label class="block text-sm text-white/60 mb-1">Product Name</label>
        <input id="product-name" type="text" required class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400" placeholder="e.g. Jollof Rice"></div>
      <div><label class="block text-sm text-white/60 mb-1">Category</label>
        <input id="product-category" type="text" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400" placeholder="e.g. Rice"></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm text-white/60 mb-1">Price ₦</label>
          <input id="product-price" type="number" min="0" step="0.01" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400"></div>
        <div><label class="block text-sm text-white/60 mb-1">Unit</label>
          <input id="product-unit" type="text" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400" placeholder="plate"></div>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-white/60">Active</label>
        <input id="product-active" type="checkbox" checked class="w-5 h-5 rounded accent-yellow-400">
      </div>
      <div>
        <label class="block text-sm text-white/60 mb-2">Extras</label>
        <div id="extras-container" class="space-y-2"></div>
        <button type="button" onclick="addExtra()" class="mt-2 text-sm text-yellow-400 hover:underline flex items-center gap-1">
          <iconify-icon icon="solar:add-circle-linear"></iconify-icon> Add Extra
        </button>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="saveProduct()" class="flex-1 py-3 rounded-xl font-semibold text-gray-900" style="background:#EECE55">Save Product</button>
        <button type="button" onclick="closeModal('product-modal')" class="flex-1 py-3 rounded-xl font-semibold bg-white/10 hover:bg-white/20">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- User Modal -->
<div id="user-modal" class="fixed inset-0 z-40 hidden flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
  <div class="bg-gray-900 border border-white/10 rounded-2xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 id="user-modal-title" class="text-lg font-bold">Add User</h3>
      <button onclick="closeModal('user-modal')" class="text-white/40 hover:text-white"><iconify-icon icon="solar:close-circle-bold" class="text-2xl"></iconify-icon></button>
    </div>
    <form id="user-form" class="space-y-4">
      <input type="hidden" id="user-id">
      <div><label class="block text-sm text-white/60 mb-1">Full Name</label>
        <input id="user-fullname" type="text" required class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400"></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm text-white/60 mb-1">Username</label>
          <input id="user-username" type="text" required class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400"></div>
        <div><label class="block text-sm text-white/60 mb-1">Password</label>
          <input id="user-password" type="password" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400" placeholder="Leave blank to keep"></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm text-white/60 mb-1">Email</label>
          <input id="user-email" type="email" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400"></div>
        <div><label class="block text-sm text-white/60 mb-1">Phone</label>
          <input id="user-phone" type="text" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400"></div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div><label class="block text-sm text-white/60 mb-1">Role</label>
          <select id="user-role" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
            <?php if ($bymoreish_current_user['role'] === 'superadmin'): ?>
            <option value="superadmin">Superadmin</option>
            <?php endif; ?>
          </select></div>
        <div><label class="block text-sm text-white/60 mb-1">Branch</label>
          <select id="user-branch" class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
            <option value="1">Branch 1</option>
            <option value="2">Branch 2</option>
          </select></div>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-white/60">Active</label>
        <input id="user-active" type="checkbox" checked class="w-5 h-5 rounded accent-yellow-400">
      </div>
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="saveUser()" class="flex-1 py-3 rounded-xl font-semibold text-gray-900" style="background:#EECE55">Save User</button>
        <button type="button" onclick="closeModal('user-modal')" class="flex-1 py-3 rounded-xl font-semibold bg-white/10 hover:bg-white/20">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/js/main.js"></script>
<script>
const isSuperadmin = window.bymConfig.userRole === 'superadmin';

function switchTab(name) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('bg-yellow-400','text-gray-900');
    b.classList.add('bg-white/10','text-white');
  });
  document.getElementById('panel-' + name).classList.remove('hidden');
  const btn = document.getElementById('tab-' + name);
  btn.classList.add('bg-yellow-400','text-gray-900');
  btn.classList.remove('bg-white/10','text-white');
  if (name === 'products') loadProducts();
  if (name === 'users') loadUsers();
  if (name === 'settings') loadSettings();
}

function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-semibold transition-all ' + (ok ? 'bg-green-600' : 'bg-red-600');
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3000);
}

function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

async function bymAjax(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('nonce', window.bymConfig.nonce);
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch(window.bymConfig.ajaxUrl, {method:'POST', body:fd});
  return r.json();
}

// --- Products ---
async function loadProducts() {
  const res = await bymAjax('bym_get_products');
  const tbody = document.getElementById('products-tbody');
  if (!res.success || !res.data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-white/40">No products found.</td></tr>'; return;
  }
  tbody.innerHTML = res.data.map(p => `
    <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
      <td class="py-3 px-2 font-medium">${escHtml(p.name)}</td>
      <td class="py-3 px-2 text-white/60">${escHtml(p.category||'')}</td>
      <td class="py-3 px-2">₦${Number(p.price||0).toLocaleString()}</td>
      <td class="py-3 px-2 text-white/60">${escHtml(p.unit||'')}</td>
      <td class="py-3 px-2"><span class="px-2 py-0.5 rounded-full text-xs font-bold ${p.active=='1'?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'}">${p.active=='1'?'Yes':'No'}</span></td>
      <td class="py-3 px-2 flex gap-2">
        <button onclick="openProductModal(${JSON.stringify(p).replace(/"/g,'&quot;')})" class="p-1.5 rounded-lg bg-yellow-400/20 hover:bg-yellow-400/40 text-yellow-400"><iconify-icon icon="solar:pen-bold"></iconify-icon></button>
        <button onclick="deleteProduct(${p.id})" class="p-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/40 text-red-400"><iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon></button>
      </td>
    </tr>`).join('');
}

function openProductModal(p=null) {
  document.getElementById('product-modal-title').textContent = p ? 'Edit Product' : 'Add Product';
  document.getElementById('product-id').value = p ? p.id : '';
  document.getElementById('product-name').value = p ? p.name : '';
  document.getElementById('product-category').value = p ? (p.category||'') : '';
  document.getElementById('product-price').value = p ? p.price : '';
  document.getElementById('product-unit').value = p ? (p.unit||'') : '';
  document.getElementById('product-active').checked = !p || p.active == '1';
  const ec = document.getElementById('extras-container');
  ec.innerHTML = '';
  if (p && p.extras) {
    try { JSON.parse(p.extras).forEach(e => addExtra(e.name, e.price)); } catch(e){}
  }
  document.getElementById('product-modal').classList.remove('hidden');
}

function addExtra(name='', price='') {
  const div = document.createElement('div');
  div.className = 'flex gap-2 items-center';
  div.innerHTML = `<input type="text" placeholder="Extra name" value="${escHtml(name)}" class="flex-1 bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400 extra-name">
    <input type="number" placeholder="₦ price" value="${escHtml(String(price))}" class="w-24 bg-white/10 border border-white/20 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-yellow-400 extra-price">
    <button type="button" onclick="this.parentElement.remove()" class="text-red-400 hover:text-red-300"><iconify-icon icon="solar:close-circle-bold"></iconify-icon></button>`;
  document.getElementById('extras-container').appendChild(div);
}

async function saveProduct() {
  const extras = [];
  document.querySelectorAll('#extras-container > div').forEach(row => {
    const n = row.querySelector('.extra-name').value.trim();
    const p = row.querySelector('.extra-price').value;
    if (n) extras.push({name:n, price:parseFloat(p)||0});
  });
  const res = await bymAjax('bym_save_product', {
    id: document.getElementById('product-id').value,
    name: document.getElementById('product-name').value,
    category: document.getElementById('product-category').value,
    price: document.getElementById('product-price').value,
    unit: document.getElementById('product-unit').value,
    active: document.getElementById('product-active').checked ? 1 : 0,
    extras: JSON.stringify(extras)
  });
  if (res.success) { showToast('Product saved!'); closeModal('product-modal'); loadProducts(); }
  else showToast(res.data || 'Error saving product.', false);
}

async function deleteProduct(id) {
  if (!confirm('Delete this product?')) return;
  const res = await bymAjax('bym_delete_product', {id});
  if (res.success) { showToast('Product deleted.'); loadProducts(); }
  else showToast(res.data || 'Error.', false);
}

// --- Users ---
async function loadUsers() {
  const res = await bymAjax('bym_get_users');
  const tbody = document.getElementById('users-tbody');
  if (!res.success || !res.data.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-white/40">No users found.</td></tr>'; return;
  }
  tbody.innerHTML = res.data.map(u => `
    <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
      <td class="py-3 px-2 font-medium">${escHtml(u.full_name||'')}</td>
      <td class="py-3 px-2 text-white/60">${escHtml(u.username)}</td>
      <td class="py-3 px-2"><span class="px-2 py-0.5 rounded-full text-xs font-bold bg-yellow-400/20 text-yellow-400">${escHtml(u.role)}</span></td>
      <td class="py-3 px-2 text-white/60">Branch ${escHtml(String(u.branch||1))}</td>
      <td class="py-3 px-2"><span class="px-2 py-0.5 rounded-full text-xs font-bold ${u.active=='1'?'bg-green-500/20 text-green-400':'bg-red-500/20 text-red-400'}">${u.active=='1'?'Active':'Inactive'}</span></td>
      <td class="py-3 px-2 flex gap-2">
        <button onclick="openUserModal(${JSON.stringify(u).replace(/"/g,'&quot;')})" class="p-1.5 rounded-lg bg-yellow-400/20 hover:bg-yellow-400/40 text-yellow-400"><iconify-icon icon="solar:pen-bold"></iconify-icon></button>
        ${isSuperadmin ? `<button onclick="deleteUser(${u.id})" class="p-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/40 text-red-400"><iconify-icon icon="solar:trash-bin-trash-bold"></iconify-icon></button>` : ''}
      </td>
    </tr>`).join('');
}

function openUserModal(u=null) {
  document.getElementById('user-modal-title').textContent = u ? 'Edit User' : 'Add User';
  document.getElementById('user-id').value = u ? u.id : '';
  document.getElementById('user-fullname').value = u ? (u.full_name||'') : '';
  document.getElementById('user-username').value = u ? u.username : '';
  document.getElementById('user-password').value = '';
  document.getElementById('user-email').value = u ? (u.email||'') : '';
  document.getElementById('user-phone').value = u ? (u.phone||'') : '';
  document.getElementById('user-role').value = u ? u.role : 'staff';
  document.getElementById('user-branch').value = u ? (u.branch||1) : 1;
  document.getElementById('user-active').checked = !u || u.active == '1';
  document.getElementById('user-modal').classList.remove('hidden');
}

async function saveUser() {
  const res = await bymAjax('bym_save_user', {
    id: document.getElementById('user-id').value,
    full_name: document.getElementById('user-fullname').value,
    username: document.getElementById('user-username').value,
    password: document.getElementById('user-password').value,
    email: document.getElementById('user-email').value,
    phone: document.getElementById('user-phone').value,
    role: document.getElementById('user-role').value,
    branch: document.getElementById('user-branch').value,
    active: document.getElementById('user-active').checked ? 1 : 0
  });
  if (res.success) { showToast('User saved!'); closeModal('user-modal'); loadUsers(); }
  else showToast(res.data || 'Error saving user.', false);
}

async function deleteUser(id) {
  if (!isSuperadmin) return;
  if (!confirm('Delete this user?')) return;
  const res = await bymAjax('bym_delete_user', {id});
  if (res.success) { showToast('User deleted.'); loadUsers(); }
  else showToast(res.data || 'Error.', false);
}

// --- Settings ---
async function loadSettings() {
  const res = await bymAjax('bym_get_settings');
  if (res.success) {
    const s = res.data;
    document.getElementById('setting-site-name').value = s.site_name || '';
    document.getElementById('setting-description').value = s.site_description || '';
    document.getElementById('setting-branch').value = s.default_branch || 1;
    if (s.logo_url) {
      const img = document.getElementById('setting-logo-preview');
      img.src = s.logo_url; img.classList.remove('hidden');
    }
  }
}

document.getElementById('setting-logo').addEventListener('change', function() {
  const f = this.files[0]; if (!f) return;
  const img = document.getElementById('setting-logo-preview');
  img.src = URL.createObjectURL(f); img.classList.remove('hidden');
});

async function saveSettings() {
  const fd = new FormData();
  fd.append('action', 'bym_update_settings');
  fd.append('nonce', window.bymConfig.nonce);
  fd.append('site_name', document.getElementById('setting-site-name').value);
  fd.append('site_description', document.getElementById('setting-description').value);
  fd.append('default_branch', document.getElementById('setting-branch').value);
  const logo = document.getElementById('setting-logo').files[0];
  if (logo) fd.append('logo', logo);
  const r = await fetch(window.bymConfig.ajaxUrl, {method:'POST', body:fd});
  const res = await r.json();
  if (res.success) showToast('Settings saved!');
  else showToast(res.data || 'Error.', false);
}

async function deleteAllRecords() {
  if (!isSuperadmin) return;
  if (!confirm('Are you sure you want to delete ALL records? This cannot be undone.')) return;
  if (!confirm('FINAL WARNING: This will permanently delete all orders, stock history, expenses. Continue?')) return;
  const res = await bymAjax('bym_delete_all_records');
  if (res.success) showToast('All records deleted.');
  else showToast(res.data || 'Error.', false);
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
loadProducts();
</script>
</body>
</html>
