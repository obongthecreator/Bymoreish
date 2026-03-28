<?php if (!isset($bymoreish_current_user)) { wp_redirect(home_url('/bymoreish/login')); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile – Bymoreish</title>
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

<div class="max-w-2xl mx-auto px-4 py-10">
  <!-- Welcome -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 mb-6 flex items-center gap-4">
    <div class="relative group cursor-pointer" onclick="document.getElementById('avatar-input').click()">
      <img id="avatar-preview" src="<?php echo esc_url($bymoreish_current_user['avatar'] ?? (BYMOREISH_PLUGIN_URL . 'assets/img/default-avatar.png')); ?>"
        class="w-20 h-20 rounded-full object-cover border-4 border-yellow-400 shadow-lg">
      <div class="absolute inset-0 rounded-full bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
        <iconify-icon icon="solar:camera-bold" class="text-white text-2xl"></iconify-icon>
      </div>
      <input type="file" id="avatar-input" accept="image/*" class="hidden">
    </div>
    <div>
      <h1 class="text-2xl font-bold">Welcome back, <span id="display-name"><?php echo esc_html($bymoreish_current_user['full_name'] ?? $bymoreish_current_user['username']); ?></span>! 👋</h1>
      <div class="flex items-center gap-2 mt-1">
        <span class="px-2 py-0.5 rounded-full text-xs font-bold" style="background:#EECE55;color:#1a1a1a"><?php echo esc_html($bymoreish_current_user['role'] ?? ''); ?></span>
        <span class="text-white/50 text-sm">Branch <?php echo intval($bymoreish_current_user['branch'] ?? 1); ?></span>
      </div>
    </div>
  </div>

  <!-- Profile Form -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 mb-6">
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <iconify-icon icon="solar:user-bold-duotone" class="text-yellow-400"></iconify-icon> Profile Details
    </h2>
    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-white/60 mb-1">Full Name</label>
          <input id="profile-fullname" type="text" value="<?php echo esc_attr($bymoreish_current_user['full_name'] ?? ''); ?>"
            class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Username</label>
          <input type="text" value="<?php echo esc_attr($bymoreish_current_user['username'] ?? ''); ?>" readonly
            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white/40 cursor-not-allowed">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-white/60 mb-1">Email</label>
          <input id="profile-email" type="email" value="<?php echo esc_attr($bymoreish_current_user['email'] ?? ''); ?>"
            class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Phone</label>
          <input id="profile-phone" type="text" value="<?php echo esc_attr($bymoreish_current_user['phone'] ?? ''); ?>"
            class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-400">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm text-white/60 mb-1">Role</label>
          <div class="px-4 py-3 bg-white/5 border border-white/10 rounded-xl">
            <span class="px-2 py-0.5 rounded-full text-xs font-bold" style="background:#EECE55;color:#1a1a1a"><?php echo esc_html($bymoreish_current_user['role'] ?? ''); ?></span>
          </div>
        </div>
        <div>
          <label class="block text-sm text-white/60 mb-1">Branch</label>
          <div class="px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white/60">Branch <?php echo intval($bymoreish_current_user['branch'] ?? 1); ?></div>
        </div>
      </div>
      <button onclick="saveProfile()" class="w-full py-3 rounded-xl font-semibold text-gray-900 flex items-center justify-center gap-2" style="background:#EECE55">
        <iconify-icon icon="solar:diskette-bold"></iconify-icon> Save Profile
      </button>
    </div>
  </div>

  <!-- Change Password -->
  <div class="backdrop-blur-md bg-white/5 border border-white/10 rounded-2xl p-6 mb-6">
    <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
      <iconify-icon icon="solar:lock-password-bold-duotone" class="text-yellow-400"></iconify-icon> Change Password
    </h2>
    <div class="space-y-4">
      <?php foreach([['current-password','Current Password'],['new-password','New Password'],['confirm-password','Confirm Password']] as [$id,$label]): ?>
      <div>
        <label class="block text-sm text-white/60 mb-1"><?php echo esc_html($label); ?></label>
        <div class="relative">
          <input id="<?php echo esc_attr($id); ?>" type="password"
            class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3 pr-12 text-white focus:outline-none focus:border-yellow-400" placeholder="••••••••">
          <button type="button" onclick="togglePwd('<?php echo esc_attr($id); ?>')" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white">
            <iconify-icon id="eye-<?php echo esc_attr($id); ?>" icon="solar:eye-bold"></iconify-icon>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
      <button onclick="changePassword()" class="w-full py-3 rounded-xl font-semibold text-white flex items-center justify-center gap-2" style="background:#4CB050">
        <iconify-icon icon="solar:shield-keyhole-bold"></iconify-icon> Change Password
      </button>
    </div>
  </div>

  <!-- Logout -->
  <button onclick="if(confirm('Log out?')) window.location.href='<?php echo esc_url(home_url('/bymoreish/logout')); ?>'"
    class="w-full py-3 rounded-xl font-semibold bg-white/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 flex items-center justify-center gap-2 transition-colors">
    <iconify-icon icon="solar:logout-2-bold"></iconify-icon> Logout
  </button>
</div>

<script src="<?php echo esc_url(BYMOREISH_PLUGIN_URL); ?>assets/js/main.js"></script>
<script>
function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-xl shadow-lg text-white font-semibold transition-all ' + (ok ? 'bg-green-600' : 'bg-red-600');
  t.classList.remove('hidden');
  setTimeout(() => t.classList.add('hidden'), 3000);
}

function togglePwd(id) {
  const input = document.getElementById(id);
  const icon = document.getElementById('eye-' + id);
  if (input.type === 'password') {
    input.type = 'text';
    icon.setAttribute('icon', 'solar:eye-closed-bold');
  } else {
    input.type = 'password';
    icon.setAttribute('icon', 'solar:eye-bold');
  }
}

async function bymAjax(action, data={}) {
  const fd = new FormData();
  fd.append('action', action);
  fd.append('nonce', window.bymConfig.nonce);
  for (const [k,v] of Object.entries(data)) fd.append(k, v);
  const r = await fetch(window.bymConfig.ajaxUrl, {method:'POST', body:fd});
  return r.json();
}

async function saveProfile() {
  const res = await bymAjax('bym_save_user', {
    id: window.bymConfig.userId,
    full_name: document.getElementById('profile-fullname').value,
    email: document.getElementById('profile-email').value,
    phone: document.getElementById('profile-phone').value
  });
  if (res.success) {
    showToast('Profile saved!');
    document.getElementById('display-name').textContent = document.getElementById('profile-fullname').value;
  } else showToast(res.data || 'Error saving profile.', false);
}

async function changePassword() {
  const current = document.getElementById('current-password').value;
  const newPwd = document.getElementById('new-password').value;
  const confirm = document.getElementById('confirm-password').value;
  if (!current || !newPwd) { showToast('Please fill all password fields.', false); return; }
  if (newPwd !== confirm) { showToast('New passwords do not match.', false); return; }
  const res = await bymAjax('bym_change_password', {
    id: window.bymConfig.userId,
    current_password: current,
    new_password: newPwd
  });
  if (res.success) {
    showToast('Password changed!');
    ['current-password','new-password','confirm-password'].forEach(id => document.getElementById(id).value = '');
  } else showToast(res.data || 'Error changing password.', false);
}

document.getElementById('avatar-input').addEventListener('change', async function() {
  const file = this.files[0]; if (!file) return;
  const preview = document.getElementById('avatar-preview');
  preview.src = URL.createObjectURL(file);
  const fd = new FormData();
  fd.append('action', 'bym_upload_profile_picture');
  fd.append('nonce', window.bymConfig.nonce);
  fd.append('id', window.bymConfig.userId);
  fd.append('avatar', file);
  const r = await fetch(window.bymConfig.ajaxUrl, {method:'POST', body:fd});
  const res = await r.json();
  if (res.success) showToast('Profile picture updated!');
  else showToast(res.data || 'Upload failed.', false);
});
</script>
</body>
</html>
