/**
 * Bymoreish Inventory – Orders Page
 *
 * Handles:
 *  - Loading products via AJAX and building the order form
 *  - Quantity controls and extras selection per product
 *  - Real-time item total and grand total calculation
 *  - Payment mode allocation logic
 *  - Order submission and receipt display
 *  - Order status updates (pending → prepared → delivered)
 *  - Today's orders table with live refresh
 *
 * Depends on:
 *  - window.bymConfig  (ajaxUrl, nonce, currentUser)
 *  - assets/js/main.js (BymoreishApp, bymAjax, showToast, etc.)
 */

/* ============================================================
   State
   ============================================================ */

const OrdersPage = (() => {
  /** @type {Array<ProductRow>} */
  let _products = [];
  /** @type {Map<number, CartItem>}  keyed by product id */
  const _cart = new Map();
  /** @type {'transfer'|'card'|'cash'|'transfer_card'|'transfer_cash'|'card_cash'|'all'} */
  let _paymentMode = 'cash';

  /* ----------------------------------------------------------
     Public init
     ---------------------------------------------------------- */

  function init() {
    _loadProducts();
    _loadTodaysOrders();
    _bindPaymentTabs();
    _bindSubmit();
    _bindStatusFilter();
  }

  /* ----------------------------------------------------------
     Product loading
     ---------------------------------------------------------- */

  async function _loadProducts() {
    const container = document.getElementById('orders-product-list');
    if (!container) return;

    try {
      BymoreishApp.showLoading();
      const branchId = _getBranchId();
      _products = await BymoreishApp.bymAjax('bym_get_products', { branch_id: branchId });
      _renderProductList(_products, container);
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to load products.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  function _renderProductList(products, container) {
    if (!products || products.length === 0) {
      container.innerHTML = `
        <div class="text-center py-12 text-gray-500">
          <span class="iconify text-4xl mb-3" data-icon="solar:box-minimalistic-bold"></span>
          <p class="text-sm">No products found.</p>
        </div>`;
      if (window.Iconify) window.Iconify.scan(container);
      return;
    }

    // Group products by category.
    const byCategory = products.reduce((acc, p) => {
      const cat = p.category || 'Other';
      if (!acc[cat]) acc[cat] = [];
      acc[cat].push(p);
      return acc;
    }, {});

    container.innerHTML = '';

    Object.entries(byCategory).forEach(([category, items]) => {
      const section = document.createElement('div');
      section.className = 'mb-6';
      section.innerHTML = `
        <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-500 px-2 mb-3 flex items-center gap-2">
          <span class="iconify" data-icon="solar:tag-bold"></span>${BymoreishApp.escapeHtml(category)}
        </h3>
        <div class="space-y-1">
          ${items.map((p) => _productRowHtml(p)).join('')}
        </div>
      `;
      container.appendChild(section);
    });

    if (window.Iconify) window.Iconify.scan(container);

    // Bind controls.
    container.querySelectorAll('.qty-btn').forEach((btn) => {
      btn.addEventListener('click', _handleQtyBtn);
    });
    container.querySelectorAll('.extras-item input[type="checkbox"]').forEach((cb) => {
      cb.addEventListener('change', _handleExtraChange);
    });
  }

  function _productRowHtml(product) {
    const extrasHtml = (product.extras || []).length > 0
      ? `<div class="extras-list mt-2 hidden" id="extras-${product.id}">
          ${(product.extras).map((e) => `
            <label class="extras-item">
              <input type="checkbox"
                data-product-id="${product.id}"
                data-extra-id="${e.id}"
                data-extra-name="${BymoreishApp.escapeHtml(e.extra_name)}"
                data-extra-price="${e.extra_price}">
              <span>${BymoreishApp.escapeHtml(e.extra_name)}</span>
              <span class="extra-price">${BymoreishApp.formatNaira(e.extra_price)}</span>
            </label>`).join('')}
         </div>
         <button type="button" class="text-xs text-gray-500 hover:text-yellow-400 mt-1 transition-colors"
                 onclick="document.getElementById('extras-${product.id}').classList.toggle('hidden')">
           + Extras
         </button>`
      : '';

    return `
      <div class="order-row glass-card" data-product-id="${product.id}"
           data-product-name="${BymoreishApp.escapeHtml(product.name)}"
           data-unit-price="${product.price}">
        <div class="min-w-0">
          <div class="text-sm font-semibold text-white leading-tight">
            ${BymoreishApp.escapeHtml(product.name)}
          </div>
          <div class="text-xs text-gray-500 mt-0.5">
            ${BymoreishApp.formatNaira(product.price)} / ${BymoreishApp.escapeHtml(product.unit || 'unit')}
          </div>
          ${extrasHtml}
        </div>
        <div class="qty-control" data-product-id="${product.id}">
          <button type="button" class="qty-btn" data-action="dec" data-product-id="${product.id}">−</button>
          <span class="qty-display" id="qty-${product.id}">0</span>
          <button type="button" class="qty-btn" data-action="inc" data-product-id="${product.id}">+</button>
        </div>
        <div class="text-sm font-semibold text-yellow-400 w-24 text-right tabular-nums"
             id="item-total-${product.id}">—</div>
      </div>`;
  }

  /* ----------------------------------------------------------
     Cart operations
     ---------------------------------------------------------- */

  function _handleQtyBtn(e) {
    const btn       = e.currentTarget;
    const action    = btn.dataset.action;
    const productId = parseInt(btn.dataset.productId, 10);
    const product   = _products.find((p) => parseInt(p.id, 10) === productId);
    if (!product) return;

    const item = _cart.get(productId) || {
      product_id:   product.id,
      product_name: product.name,
      unit_price:   parseFloat(product.price) || 0,
      quantity:     0,
      extras:       [],
      item_total:   0,
    };

    item.quantity = Math.max(0, item.quantity + (action === 'inc' ? 1 : -1));

    if (item.quantity === 0) {
      _cart.delete(productId);
    } else {
      _recalcItemTotal(item);
      _cart.set(productId, item);
    }

    _updateQtyDisplay(productId, item.quantity);
    _updateGrandTotal();
  }

  function _handleExtraChange(e) {
    const cb        = e.currentTarget;
    const productId = parseInt(cb.dataset.productId, 10);
    const extraId   = parseInt(cb.dataset.extraId, 10);
    const extraName = cb.dataset.extraName;
    const extraPrice= parseFloat(cb.dataset.extraPrice) || 0;

    const item = _cart.get(productId);
    if (!item) {
      // Auto-add item with qty=1 when an extra is checked.
      if (cb.checked) {
        const product = _products.find((p) => parseInt(p.id, 10) === productId);
        if (!product) return;
        const newItem = {
          product_id:   product.id,
          product_name: product.name,
          unit_price:   parseFloat(product.price) || 0,
          quantity:     1,
          extras:       [{ extra_id: extraId, extra_name: extraName, extra_price: extraPrice }],
          item_total:   0,
        };
        _recalcItemTotal(newItem);
        _cart.set(productId, newItem);
        _updateQtyDisplay(productId, 1);
        _updateGrandTotal();
      }
      return;
    }

    if (cb.checked) {
      if (!item.extras.find((ex) => ex.extra_id === extraId)) {
        item.extras.push({ extra_id: extraId, extra_name: extraName, extra_price: extraPrice });
      }
    } else {
      item.extras = item.extras.filter((ex) => ex.extra_id !== extraId);
    }

    _recalcItemTotal(item);
    _cart.set(productId, item);
    _updateGrandTotal();
  }

  function _recalcItemTotal(item) {
    const extrasSum = item.extras.reduce((s, e) => s + (parseFloat(e.extra_price) || 0), 0);
    item.item_total = item.quantity * (item.unit_price + extrasSum);

    const el = document.getElementById(`item-total-${item.product_id}`);
    if (el) el.textContent = BymoreishApp.formatNaira(item.item_total);
  }

  function _updateQtyDisplay(productId, qty) {
    const el = document.getElementById(`qty-${productId}`);
    if (el) el.textContent = String(qty);

    // Highlight active rows.
    const row = document.querySelector(`.order-row[data-product-id="${productId}"]`);
    if (row) {
      row.style.background = qty > 0
        ? 'rgba(238, 206, 85, 0.06)'
        : '';
    }
  }

  function _grandTotal() {
    let total = 0;
    _cart.forEach((item) => { total += item.item_total; });
    return total;
  }

  function _updateGrandTotal() {
    const total = _grandTotal();
    const el = document.getElementById('orders-grand-total');
    if (el) el.textContent = BymoreishApp.formatNaira(total);

    // Also update the separate grand total display in the payment section.
    const gtDisplay = document.getElementById('grand-total-display');
    if (gtDisplay) gtDisplay.textContent = BymoreishApp.formatNaira(total);

    _updatePaymentFields(total);
  }

  /* ----------------------------------------------------------
     Payment mode logic
     ---------------------------------------------------------- */

  function _bindPaymentTabs() {
    document.querySelectorAll('.payment-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.payment-tab').forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        _paymentMode = tab.dataset.mode || 'cash';
        _updatePaymentFields(_grandTotal());
        _showPaymentInputs(_paymentMode);
      });
    });
  }

  function _showPaymentInputs(mode) {
    const fields = {
      'transfer':       ['transfer'],
      'card':           ['card'],
      'cash':           ['cash'],
      'transfer_card':  ['transfer', 'card'],
      'transfer_cash':  ['transfer', 'cash'],
      'card_cash':      ['card', 'cash'],
      'all':            ['transfer', 'card', 'cash'],
    };

    ['transfer', 'card', 'cash'].forEach((type) => {
      const wrap = document.getElementById(`payment-field-${type}`);
      if (wrap) {
        const visible = (fields[mode] || []).includes(type);
        wrap.classList.toggle('hidden', !visible);
      }
    });
  }

  /**
   * Auto-allocate payment amounts based on mode.
   *
   * Rules:
   *  single mode      → full total to that mode
   *  transfer+card    → total to transfer; as card amount entered deducts from transfer
   *  transfer+cash    → total to transfer; as cash entered deducts from transfer
   *  card+cash        → total to card (POS); as cash entered deducts from card
   *  all three        → total to transfer; deduct card first, then cash
   */
  function _updatePaymentFields(total) {
    const cardInput  = document.getElementById('payment-card');
    const cashInput  = document.getElementById('payment-cash');
    const xferInput  = document.getElementById('payment-transfer');

    const cardAmt  = BymoreishApp.parseNaira(cardInput?.value  || '0');
    const cashAmt  = BymoreishApp.parseNaira(cashInput?.value  || '0');

    const setReadonly = (input, value) => {
      if (!input) return;
      input.value = value > 0 ? BymoreishApp.formatNaira(value) : '';
      input.dataset.rawValue = String(value);
    };

    switch (_paymentMode) {
      case 'transfer':
        setReadonly(xferInput, total);
        break;

      case 'card':
        setReadonly(cardInput, total);
        break;

      case 'cash':
        setReadonly(cashInput, total);
        break;

      case 'transfer_card': {
        const remaining = Math.max(0, total - cardAmt);
        setReadonly(xferInput, remaining);
        break;
      }

      case 'transfer_cash': {
        const remaining = Math.max(0, total - cashAmt);
        setReadonly(xferInput, remaining);
        break;
      }

      case 'card_cash': {
        const remaining = Math.max(0, total - cashAmt);
        setReadonly(cardInput, remaining);
        break;
      }

      case 'all': {
        const remaining = Math.max(0, total - cardAmt - cashAmt);
        setReadonly(xferInput, remaining);
        break;
      }
    }

    // Live-recalculate whenever card/cash inputs change.
    if (cardInput && !cardInput.dataset.boundPayment) {
      cardInput.dataset.boundPayment = '1';
      cardInput.addEventListener('input', () => _updatePaymentFields(_grandTotal()));
    }
    if (cashInput && !cashInput.dataset.boundPayment) {
      cashInput.dataset.boundPayment = '1';
      cashInput.addEventListener('input', () => _updatePaymentFields(_grandTotal()));
    }
  }

  /* ----------------------------------------------------------
     Order submission
     ---------------------------------------------------------- */

  function _bindSubmit() {
    const btn = document.getElementById('btn-submit-order');
    if (btn) btn.addEventListener('click', _submitOrder);
  }

  async function _submitOrder() {
    const total = _grandTotal();
    if (_cart.size === 0) {
      BymoreishApp.showToast('Add at least one item to the order.', 'warning');
      return;
    }
    if (total <= 0) {
      BymoreishApp.showToast('Grand total must be greater than zero.', 'warning');
      return;
    }

    const items = Array.from(_cart.values()).map((item) => ({
      product_id:   item.product_id,
      product_name: item.product_name,
      quantity:     item.quantity,
      unit_price:   item.unit_price,
      item_total:   item.item_total,
      extras:       item.extras,
    }));

    const xferInput = document.getElementById('payment-transfer');
    const cardInput = document.getElementById('payment-card');
    const cashInput = document.getElementById('payment-cash');

    // Gather customer details from the form.
    const customerName    = (document.getElementById('customer-name')    || {}).value || '';
    const customerPhone   = (document.getElementById('customer-phone')   || {}).value || '';
    const customerType    = (document.getElementById('customer-type')    || {}).value || 'new';
    const customerRemarks = (document.getElementById('customer-remarks') || {}).value || '';

    const payload = {
      branch_id:        _getBranchId(),
      items:            items,
      payment_mode:     _paymentMode,
      transfer_amount:  BymoreishApp.parseNaira(xferInput?.dataset.rawValue || xferInput?.value || '0'),
      card_amount:      BymoreishApp.parseNaira(cardInput?.dataset.rawValue || cardInput?.value || '0'),
      cash_amount:      BymoreishApp.parseNaira(cashInput?.dataset.rawValue || cashInput?.value || '0'),
      customer_name:    customerName,
      customer_phone:   customerPhone,
      customer_type:    customerType,
      customer_remarks: customerRemarks,
      status:           'pending',
    };

    try {
      BymoreishApp.showLoading();
      const result = await BymoreishApp.bymAjax('bym_save_order', payload);
      BymoreishApp.showToast('Order saved successfully!', 'success');
      _resetOrder();
      _loadTodaysOrders();
      if (result.order_id) {
        _showReceiptPrompt(result.order_id);
      }
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to save order.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  function _resetOrder() {
    _cart.clear();
    _products.forEach((p) => {
      _updateQtyDisplay(parseInt(p.id, 10), 0);
      const el = document.getElementById(`item-total-${p.id}`);
      if (el) el.textContent = '—';
    });
    _updateGrandTotal();

    // Reset extras checkboxes.
    document.querySelectorAll('.extras-item input[type="checkbox"]').forEach((cb) => {
      cb.checked = false;
    });

    // Reset payment inputs.
    ['payment-transfer', 'payment-card', 'payment-cash'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) { el.value = ''; el.dataset.rawValue = '0'; }
    });

    // Reset customer form fields.
    ['customer-name', 'customer-phone', 'customer-remarks'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const customerType = document.getElementById('customer-type');
    if (customerType) customerType.value = 'new';

    // Uncheck payment confirmed and disable submit.
    const pcb = document.getElementById('payment-confirmed');
    if (pcb) pcb.checked = false;
    const submitBtn = document.getElementById('btn-submit-order');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
      submitBtn.classList.remove('hover:scale-105');
    }
  }

  function _showReceiptPrompt(orderId) {
    const modal = document.getElementById('receipt-prompt-modal');
    if (modal) {
      modal.classList.remove('hidden');
      const printBtn = document.getElementById('btn-print-receipt');
      if (printBtn) {
        printBtn.onclick = () => {
          modal.classList.add('hidden');
          BymoreishApp.printReceipt(orderId);
        };
      }
      const skipBtn = document.getElementById('btn-skip-receipt');
      if (skipBtn) {
        skipBtn.onclick = () => modal.classList.add('hidden');
      }
    }
  }

  /* ----------------------------------------------------------
     Today's orders table
     ---------------------------------------------------------- */

  async function _loadTodaysOrders(status = '') {
    const tbody = document.getElementById('orders-table-body');
    if (!tbody) return;

    try {
      const response = await BymoreishApp.bymAjax('bym_get_orders', {
        branch_id: _getBranchId(),
        date_from: BymoreishApp.todayISO(),
        date_to:   BymoreishApp.todayISO(),
        status,
      });

      // Handle both flat array and structured response.
      const orders = Array.isArray(response) ? response : (response.orders || []);

      if (!orders || orders.length === 0) {
        tbody.innerHTML = `
          <tr><td colspan="7" class="text-center py-8 text-gray-500 text-sm">
            No orders for today.
          </td></tr>`;
        return;
      }

      tbody.innerHTML = orders.map((order) => `
        <tr>
          <td class="px-4 py-3 font-semibold text-white">#${order.id}</td>
          <td class="px-4 py-3 text-sm text-gray-400">${BymoreishApp.escapeHtml(order.customer_name || 'Walk-in')}</td>
          <td class="px-4 py-3 text-sm text-gray-300">${_summariseItems(order.items)}</td>
          <td class="px-4 py-3 font-semibold text-yellow-400 tabular-nums">
            ${BymoreishApp.formatNaira(order.grand_total)}
          </td>
          <td class="px-4 py-3 text-xs text-gray-400 capitalize">${BymoreishApp.escapeHtml(order.payment_mode || '')}</td>
          <td class="px-4 py-3">
            <span class="status-badge status-${BymoreishApp.escapeHtml(order.status)}">${BymoreishApp.escapeHtml(order.status)}</span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-2">
              ${_statusActionBtn(order)}
              <button type="button" class="pill-btn py-1 px-3 text-xs btn-text"
                      onclick="OrdersPage.printReceipt(${parseInt(order.id, 10)})">
                <span class="iconify" data-icon="solar:printer-bold"></span>
              </button>
            </div>
          </td>
        </tr>`).join('');

      if (window.Iconify) window.Iconify.scan(tbody);
    } catch (err) {
      console.error('Failed to load orders:', err);
    }
  }

  function _summariseItems(items) {
    if (!items || items.length === 0) return '—';
    const names = items.slice(0, 2).map((i) => `${i.product_name} ×${i.quantity}`);
    const more  = items.length > 2 ? ` +${items.length - 2} more` : '';
    return names.join(', ') + more;
  }

  function _statusActionBtn(order) {
    const next = { pending: 'prepared', prepared: 'delivered' };
    const nextStatus = next[order.status];
    if (!nextStatus) return '';
    const orderId = parseInt(order.id, 10);
    return `
      <button type="button"
              class="pill-btn pill-btn-green py-1 px-3 text-xs btn-text"
              onclick="OrdersPage.updateStatus(${orderId}, '${nextStatus}')">
        Mark ${nextStatus}
      </button>`;
  }

  function _bindStatusFilter() {
    document.querySelectorAll('[data-orders-filter]').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('[data-orders-filter]').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        _loadTodaysOrders(btn.dataset.ordersFilter);
      });
    });
  }

  /* ----------------------------------------------------------
     Public: status update & receipt
     ---------------------------------------------------------- */

  async function updateStatus(orderId, newStatus) {
    try {
      BymoreishApp.showLoading();
      await BymoreishApp.bymAjax('bym_update_order_status', {
        order_id: orderId,
        status:   newStatus,
      });
      BymoreishApp.showToast(`Order #${orderId} marked as ${newStatus}.`, 'success');
      _loadTodaysOrders();
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to update status.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  /* ----------------------------------------------------------
     Helpers
     ---------------------------------------------------------- */

  function _getBranchId() {
    const el = document.getElementById('bym-branch-id');
    return el ? parseInt(el.value, 10) : 0;
  }

  /* ----------------------------------------------------------
     Public API
     ---------------------------------------------------------- */
  return {
    init,
    updateStatus,
    printReceipt: (id) => BymoreishApp.printReceipt(id),
  };
})();

document.addEventListener('DOMContentLoaded', () => OrdersPage.init());

window.OrdersPage = OrdersPage;
