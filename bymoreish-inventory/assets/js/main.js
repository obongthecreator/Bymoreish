/**
 * Bymoreish Inventory – Main JavaScript
 *
 * Global utilities: currency, animations, navigation, toasts,
 * digital clock, page transitions, loading state, receipt printing.
 *
 * Depends on: window.bymConfig = { ajaxUrl, nonce, currentUser }
 * Set this object in each PHP page template before loading this script.
 */

/* ============================================================
   Config / Globals
   ============================================================ */

/** @type {{ ajaxUrl: string, nonce: string, currentUser: object }} */
const BYM = window.bymConfig || { ajaxUrl: '', nonce: '', currentUser: {} };

/* ============================================================
   1. Currency Formatting
   ============================================================ */

/**
 * Format a numeric amount as a Naira string.
 * e.g. 1000 → "₦1,000"  |  100000 → "₦100,000"
 * @param {number|string} amount
 * @returns {string}
 */
function formatNaira(amount) {
  const num = parseFloat(String(amount).replace(/[^0-9.]/g, '')) || 0;
  return '₦' + num.toLocaleString('en-NG', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
}

/**
 * Parse a formatted Naira string back to a float.
 * e.g. "₦1,000" → 1000
 * @param {string} value
 * @returns {number}
 */
function parseNaira(value) {
  return parseFloat(String(value).replace(/[^0-9.]/g, '')) || 0;
}

/**
 * Attach real-time Naira formatting to all inputs matching `selector`.
 * Strips non-numeric chars, formats with commas, prepends ₦, preserves cursor.
 * @param {string} selector  CSS selector for currency inputs
 */
function attachCurrencyInput(selector) {
  document.querySelectorAll(selector).forEach((input) => {
    input.addEventListener('input', _handleCurrencyInput);
    input.addEventListener('focus', _handleCurrencyFocus);
    input.addEventListener('blur',  _handleCurrencyBlur);
  });
}

function _handleCurrencyInput(e) {
  const input  = e.target;
  const raw    = input.value.replace(/[^0-9.]/g, '');
  const parts  = raw.split('.');
  const int    = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const dec    = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
  const cursor = input.selectionStart;
  const old    = input.value;
  const formatted = raw === '' ? '' : '₦' + int + dec;

  input.value = formatted;

  // Attempt to preserve cursor position.
  const diff = formatted.length - old.length;
  const newPos = Math.max(0, cursor + diff);
  input.setSelectionRange(newPos, newPos);

  // Store raw numeric value in data attribute for easy retrieval.
  input.dataset.rawValue = raw;
}

function _handleCurrencyFocus(e) {
  const input = e.target;
  if (input.value === '₦0' || input.value === '₦') {
    input.value = '';
  }
}

function _handleCurrencyBlur(e) {
  const input = e.target;
  const raw   = parseFloat(input.dataset.rawValue || '0') || 0;
  if (raw === 0 && input.value.trim() === '') {
    input.value = '';
  }
}

/* ============================================================
   2. Letter-by-Letter Text Animation
   ============================================================ */

/**
 * Split each matching element's text into individual <span> characters
 * and apply staggered CSS letterReveal animation (80ms per character).
 * @param {string} selector  CSS selector (targets .text-animate elements)
 */
function initLetterAnimation(selector) {
  document.querySelectorAll(selector).forEach((el) => {
    const text    = el.textContent || '';
    el.innerHTML  = '';
    el.classList.add('text-animate');

    [...text].forEach((char, i) => {
      const span = document.createElement('span');
      span.textContent        = char === ' ' ? '\u00A0' : char;
      span.style.animationDelay = `${i * 80}ms`;
      el.appendChild(span);
    });
  });
}

/* ============================================================
   3. Page Transitions
   ============================================================ */

let _isNavigating = false;

/**
 * Transition-aware navigation.  Adds .page-exit, waits, then follows the URL.
 * @param {string} url  Destination URL
 */
function navigateTo(url) {
  if (_isNavigating) return;
  _isNavigating = true;

  const main = document.getElementById('bym-main-content') ||
               document.querySelector('main') ||
               document.body;

  main.classList.add('page-exit');

  setTimeout(() => {
    window.location.href = url;
  }, 350);
}

/**
 * Intercept internal Bymoreish links and use navigateTo() instead.
 */
function initPageTransitions() {
  // Add page-enter class on initial load.
  const main = document.getElementById('bym-main-content') ||
               document.querySelector('main');
  if (main) {
    main.classList.add('page-enter');
    main.addEventListener('animationend', () => {
      main.classList.remove('page-enter');
    }, { once: true });
  }

  // Intercept internal navigation links.
  document.querySelectorAll('a[href]').forEach((link) => {
    const href = link.getAttribute('href') || '';
    if (href.includes('/bymoreish/') && !href.includes('logout')) {
      link.addEventListener('click', (e) => {
        // Allow modifier keys (open in new tab, etc.)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        navigateTo(link.href);
      });
    }
  });
}

/* ============================================================
   4. Loading State (Three-Dot Overlay)
   ============================================================ */

let _loadingRefCount = 0;

/** Show the full-screen loading overlay. */
function showLoading() {
  _loadingRefCount++;
  let overlay = document.getElementById('bym-loading-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'bym-loading-overlay';
    overlay.innerHTML = `
      <span class="loading-dot"></span>
      <span class="loading-dot"></span>
      <span class="loading-dot"></span>
    `;
    document.body.appendChild(overlay);
  }
  overlay.classList.remove('hidden');
}

/** Hide the loading overlay (reference-counted so nested calls are safe). */
function hideLoading() {
  _loadingRefCount = Math.max(0, _loadingRefCount - 1);
  if (_loadingRefCount > 0) return;
  const overlay = document.getElementById('bym-loading-overlay');
  if (overlay) overlay.classList.add('hidden');
}

/* ============================================================
   5. Navigation (Mobile Drawer + Active Links)
   ============================================================ */

function initNavigation() {
  const hamburger = document.getElementById('bym-hamburger');
  const drawer    = document.getElementById('bym-nav-drawer');
  const overlay   = document.getElementById('bym-nav-overlay');

  if (hamburger && drawer) {
    hamburger.addEventListener('click', () => toggleDrawer(true));
  }

  if (overlay) {
    overlay.addEventListener('click', () => toggleDrawer(false));
  }

  // Close drawer on Escape key.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') toggleDrawer(false);
  });

  // Highlight the active nav link based on the current URL.
  highlightActiveNavLink();
}

function toggleDrawer(open) {
  const drawer  = document.getElementById('bym-nav-drawer');
  const overlay = document.getElementById('bym-nav-overlay');
  if (!drawer) return;

  drawer.classList.toggle('open', open);
  if (overlay) overlay.classList.toggle('visible', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

function highlightActiveNavLink() {
  const path = window.location.pathname.replace(/\/$/, '');
  document.querySelectorAll('.nav-item[href]').forEach((link) => {
    const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/$/, '');
    const isActive = path === linkPath || (linkPath !== '/bymoreish' && path.startsWith(linkPath));
    link.classList.toggle('active', isActive);
  });
}

/* ============================================================
   6. Digital Clock + Date Display
   ============================================================ */

/**
 * Start the digital clock. Updates all elements with class .bym-clock-time
 * and .bym-clock-date every second.
 */
function initDigitalClock() {
  function tick() {
    const now     = new Date();
    const hh      = String(now.getHours()).padStart(2, '0');
    const mm      = String(now.getMinutes()).padStart(2, '0');
    const ss      = String(now.getSeconds()).padStart(2, '0');
    const timeStr = `${hh}:${mm}:${ss}`;

    const days   = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const dateStr = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;

    document.querySelectorAll('.bym-clock-time').forEach((el) => {
      el.textContent = timeStr;
    });
    document.querySelectorAll('.bym-clock-date').forEach((el) => {
      el.textContent = dateStr;
    });
  }

  tick();
  setInterval(tick, 1000);
}

/* ============================================================
   7. Toast Notifications
   ============================================================ */

/**
 * Show a slide-in toast notification.
 * @param {string} message   Text to display
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {number} duration  Auto-dismiss ms (default 3500)
 */
function showToast(message, type = 'success', duration = 3500) {
  let container = document.getElementById('bym-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'bym-toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: 'solar:check-circle-bold',
    error:   'solar:close-circle-bold',
    warning: 'solar:danger-triangle-bold',
    info:    'solar:info-circle-bold',
  };

  const toast = document.createElement('div');
  toast.className = `bym-toast bym-toast-${type}`;
  toast.innerHTML = `
    <span class="bym-toast-icon iconify" data-icon="${icons[type] || icons.info}"></span>
    <span class="bym-toast-message">${escapeHtml(message)}</span>
  `;

  container.appendChild(toast);

  // Trigger Iconify render on the new element.
  if (window.Iconify) window.Iconify.scan(toast);

  const dismiss = () => {
    toast.classList.add('removing');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
  };

  toast.addEventListener('click', dismiss);
  setTimeout(dismiss, duration);
}

/* ============================================================
   8. Receipt Printing
   ============================================================ */

/**
 * Fetch receipt data for an order and trigger the print dialog.
 * Optionally attempts Web Bluetooth for direct thermal printer connection.
 * @param {number} orderId
 */
async function printReceipt(orderId) {
  showLoading();
  try {
    const data = await bymAjax('bym_generate_receipt', { order_id: orderId });
    _renderAndPrintReceipt(data.order, data.items, data.settings);
  } catch (err) {
    showToast(err.message || 'Failed to load receipt.', 'error');
  } finally {
    hideLoading();
  }
}

function _renderAndPrintReceipt(order, items, settings) {
  const restaurantName = settings.restaurant_name || 'Bymoreish';
  const address        = settings.address || '';
  const phone          = settings.phone  || '';
  const footer         = settings.receipt_footer || 'Thank you for dining with us!';

  const itemRows = items.map((item) => {
    const extras = (item.extras || [])
      .map((e) => `<div class="receipt-row" style="padding-left:8px;font-size:10px;">
        <span>+ ${escapeHtml(e.extra_name)}</span>
        <span>${formatNaira(e.extra_price)}</span>
      </div>`)
      .join('');
    return `
      <div class="receipt-row">
        <span>${escapeHtml(item.product_name)} x${item.quantity}</span>
        <span>${formatNaira(item.item_total)}</span>
      </div>
      ${extras}
    `;
  }).join('');

  const paymentLines = [];
  if (parseFloat(order.transfer_amount) > 0)
    paymentLines.push(`<div class="receipt-row"><span>Transfer</span><span>${formatNaira(order.transfer_amount)}</span></div>`);
  if (parseFloat(order.card_amount) > 0)
    paymentLines.push(`<div class="receipt-row"><span>Card (POS)</span><span>${formatNaira(order.card_amount)}</span></div>`);
  if (parseFloat(order.cash_amount) > 0)
    paymentLines.push(`<div class="receipt-row"><span>Cash</span><span>${formatNaira(order.cash_amount)}</span></div>`);

  const html = `
    <div class="receipt-wrapper">
      <div class="receipt-header">
        <h2>${escapeHtml(restaurantName)}</h2>
        ${address ? `<div>${escapeHtml(address)}</div>` : ''}
        ${phone   ? `<div>Tel: ${escapeHtml(phone)}</div>` : ''}
      </div>
      <div class="receipt-row"><span>Order #</span><span>${order.id}</span></div>
      <div class="receipt-row"><span>Date</span><span>${order.order_date}</span></div>
      <div class="receipt-row"><span>Staff</span><span>${escapeHtml(order.staff_name || '')}</span></div>
      <div class="receipt-divider"></div>
      ${itemRows}
      <div class="receipt-divider"></div>
      <div class="receipt-row total"><span>TOTAL</span><span>${formatNaira(order.grand_total)}</span></div>
      <div class="receipt-divider"></div>
      <div style="font-size:10px;margin-bottom:4px;">Payment</div>
      ${paymentLines.join('')}
      <div class="receipt-footer">${escapeHtml(footer)}</div>
    </div>
  `;

  // Write into a hidden iframe then print it.
  let iframe = document.getElementById('bym-receipt-frame');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id   = 'bym-receipt-frame';
    iframe.name = 'bym-receipt-frame';
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:80mm;height:1px;border:none;';
    document.body.appendChild(iframe);
  }

  // Inject receipt CSS + content.
  const doc = iframe.contentDocument || iframe.contentWindow.document;
  doc.open();
  doc.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family:'Courier New',Courier,monospace; font-size:11px; width:80mm; }
      .receipt-wrapper { width:80mm; padding:6px; }
      .receipt-header { text-align:center; border-bottom:1px dashed #000; padding-bottom:6px; margin-bottom:6px; }
      .receipt-header h2 { font-size:14px; font-weight:bold; }
      .receipt-divider { border-top:1px dashed #000; margin:5px 0; }
      .receipt-row { display:flex; justify-content:space-between; font-size:11px; line-height:1.5; }
      .receipt-row.total { font-weight:bold; font-size:12px; margin-top:4px; }
      .receipt-footer { text-align:center; border-top:1px dashed #000; padding-top:6px; margin-top:8px; font-size:10px; }
    </style>
  </head><body>${html}</body></html>`);
  doc.close();

  setTimeout(() => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    _tryBluetoothPrint(html);
  }, 250);
}

/**
 * Non-blocking attempt to detect a Web Bluetooth thermal printer.
 * Falls back gracefully if Bluetooth is unavailable or denied.
 * @param {string} _receiptHtml  Receipt HTML (for future ESC/POS encoding)
 */
async function _tryBluetoothPrint(_receiptHtml) {
  if (!navigator.bluetooth) return;
  try {
    // Attempt discovery – user must grant permission; any rejection is silently ignored.
    // UUID 000018f0-0000-1000-8000-00805f9b34fb is the "Simple Keys" / generic serial
    // profile used by many low-cost Bluetooth thermal receipt printers (e.g. HPRT, Rongta).
    const device = await navigator.bluetooth.requestDevice({
      filters:          [{ services: ['000018f0-0000-1000-8000-00805f9b34fb'] }],
      optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb'],
    });
    if (device) {
      showToast('Bluetooth printer detected. Using window.print() fallback.', 'info');
    }
  } catch (_) {
    // User cancelled or Bluetooth unavailable – silent failure.
  }
}

/* ============================================================
   9. AJAX Helper
   ============================================================ */

/**
 * Perform a WordPress AJAX request with the session nonce.
 * @param {string} action   WordPress AJAX action name (e.g. 'bym_get_products')
 * @param {object} data     POST fields (action + _bym_nonce are added automatically)
 * @returns {Promise<any>}  Resolves with response.data, rejects on error
 */
function bymAjax(action, data = {}) {
  return new Promise((resolve, reject) => {
    const body = new FormData();
    body.append('action',    action);
    body.append('_bym_nonce', BYM.nonce || '');

    Object.entries(data).forEach(([k, v]) => {
      if (v !== null && v !== undefined) {
        body.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
      }
    });

    fetch(BYM.ajaxUrl || '/wp-admin/admin-ajax.php', {
      method:      'POST',
      credentials: 'same-origin',
      body,
    })
      .then((res) => res.json())
      .then((json) => {
        if (json.success) {
          resolve(json.data);
        } else {
          reject(new Error(json.data?.message || json.data || 'Request failed.'));
        }
      })
      .catch((err) => reject(new Error(err.message || 'Network error.')));
  });
}

/* ============================================================
   10. Login Page – Animated Background (Canvas)
   ============================================================ */

function initLoginBackground() {
  const canvas = document.getElementById('login-bg-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const items = [];
  const emojis = ['🍜', '🌭', '🍞', '🍳', '🥩', '🍲', '🥗', '🍕', '🥚', '🍖'];

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  // Seed floating food particles.
  for (let i = 0; i < 18; i++) {
    items.push({
      emoji:  emojis[Math.floor(Math.random() * emojis.length)],
      x:      Math.random() * window.innerWidth,
      y:      Math.random() * window.innerHeight,
      size:   18 + Math.random() * 22,
      speedX: (Math.random() - 0.5) * 0.3,
      speedY: (Math.random() - 0.5) * 0.3,
      angle:  Math.random() * Math.PI * 2,
      spin:   (Math.random() - 0.5) * 0.008,
      alpha:  0.08 + Math.random() * 0.12,
    });
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    items.forEach((item) => {
      ctx.save();
      ctx.globalAlpha = item.alpha;
      ctx.translate(item.x, item.y);
      ctx.rotate(item.angle);
      ctx.font = `${item.size}px serif`;
      ctx.textAlign    = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(item.emoji, 0, 0);
      ctx.restore();

      // Move.
      item.x     += item.speedX;
      item.y     += item.speedY;
      item.angle += item.spin;

      // Wrap around edges.
      if (item.x < -50)                item.x = canvas.width  + 50;
      if (item.x > canvas.width  + 50) item.x = -50;
      if (item.y < -50)                item.y = canvas.height + 50;
      if (item.y > canvas.height + 50) item.y = -50;
    });

    requestAnimationFrame(draw);
  }

  draw();
}

/* ============================================================
   11. HTML Escape Helper
   ============================================================ */

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/* ============================================================
   12. Date Helpers
   ============================================================ */

/** @returns {string}  Today's date as "YYYY-MM-DD" */
function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

/** Format an ISO date string as a human-readable short date. */
function formatDate(isoStr) {
  if (!isoStr) return '';
  const d = new Date(isoStr + 'T00:00:00');
  return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' });
}

/* ============================================================
   13. DOM Ready Init
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  initPageTransitions();
  initNavigation();
  initDigitalClock();
  attachCurrencyInput('.currency-input');
  initLetterAnimation('[data-letter-animate]');
  initLoginBackground();

  // Render any Iconify icons added dynamically.
  if (window.Iconify) window.Iconify.scan();
});

/* ============================================================
   14. Expose Public API
   ============================================================ */

window.BymoreishApp = {
  formatNaira,
  parseNaira,
  attachCurrencyInput,
  initLetterAnimation,
  navigateTo,
  showLoading,
  hideLoading,
  showToast,
  printReceipt,
  bymAjax,
  escapeHtml,
  todayISO,
  formatDate,
  toggleDrawer,
};
