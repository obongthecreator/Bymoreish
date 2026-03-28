/**
 * Bymoreish Inventory – Stock Management Page
 *
 * Field rules (enforced in JS):
 *  in_stock    – read-only; carried from previous day's stock_left
 *  new_stock   – read-only; populated from imports
 *  total_stock – read-only; auto = in_stock + new_stock
 *  sold_stock  – user-editable
 *  stock_left  – read-only; auto = total_stock - sold_stock
 *
 * Depends on:
 *  - window.bymConfig  (ajaxUrl, nonce, currentUser)
 *  - assets/js/main.js (BymoreishApp, bymAjax, showToast, etc.)
 */

const StockPage = (() => {
  /* ----------------------------------------------------------
     State
     ---------------------------------------------------------- */
  /** @type {StockRow[]} */
  let _rows = [];
  /** @type {string} */
  let _currentDate = BymoreishApp.todayISO();
  /** @type {Array} stock item products */
  let _stockItems = [];

  /* ----------------------------------------------------------
     Init
     ---------------------------------------------------------- */

  function init() {
    _bindDatePicker();
    _loadStockItems();
    _loadStock(_currentDate);
    _bindSave();
    _bindSearch();
    _bindImportSave();
  }

  /* ----------------------------------------------------------
     Date picker
     ---------------------------------------------------------- */

  function _bindDatePicker() {
    const picker = document.getElementById('stock-date-picker');
    if (!picker) return;

    picker.value = _currentDate;
    picker.addEventListener('change', (e) => {
      _currentDate = e.target.value || BymoreishApp.todayISO();
      _loadStock(_currentDate);
    });
  }

  /* ----------------------------------------------------------
     Load stock items (ingredients) for the import form
     ---------------------------------------------------------- */

  async function _loadStockItems() {
    try {
      const items = await BymoreishApp.bymAjax('bym_get_stock_items', {});
      _stockItems = Array.isArray(items) ? items : [];
      _renderImportForm(_stockItems);
    } catch (err) {
      // Fallback: import grid stays empty
    }
  }

  /* ----------------------------------------------------------
     Load stock data from server
     ---------------------------------------------------------- */

  async function _loadStock(date) {
    const container = document.getElementById('stock-table-body');
    if (!container) return;

    try {
      BymoreishApp.showLoading();
      const response = await BymoreishApp.bymAjax('bym_get_stock', {
        branch_id:  _getBranchId(),
        stock_date: date,
      });

      // Handle both flat array and structured response.
      _rows = Array.isArray(response) ? response : (response.records || []);

      if (!_rows || _rows.length === 0) {
        // Fallback: load stock items (ingredients) and pre-populate with zeros.
        await _seedFromStockItems(date, container);
      } else {
        _renderTable(_rows, container);
      }

      _updateSummary();
      _updateKPIs();
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to load stock.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  /**
   * When no stock records exist for the chosen date, pre-fill the table
   * with stock items (ingredients) so the user can enter sold_stock directly.
   */
  async function _seedFromStockItems(date, container) {
    try {
      // Use already-loaded stock items, or fetch them.
      let items = _stockItems;
      if (!items || items.length === 0) {
        items = await BymoreishApp.bymAjax('bym_get_stock_items', {});
        _stockItems = Array.isArray(items) ? items : [];
        items = _stockItems;
      }

      // If still no stock items, try all products as fallback.
      if (!items || items.length === 0) {
        items = await BymoreishApp.bymAjax('bym_get_products', {
          branch_id: _getBranchId(),
        });
      }

      _rows = (items || []).map((p) => ({
        product_id:   p.id,
        product_name: p.name,
        product_unit: p.unit,
        in_stock:     0,
        new_stock:    0,
        total_stock:  0,
        sold_stock:   0,
        stock_left:   0,
        remarks:      '',
        stock_date:   date,
      }));

      _renderTable(_rows, container);
    } catch (err) {
      container.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-gray-500 text-sm">
        No stock items found. Please contact admin to set up stock items.
      </td></tr>`;
    }
  }

  /* ----------------------------------------------------------
     Render table
     ---------------------------------------------------------- */

  function _renderTable(rows, container) {
    if (!rows || rows.length === 0) {
      container.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-gray-500 text-sm">
        No stock data available for this date.
      </td></tr>`;
      return;
    }

    container.innerHTML = rows.map((row, idx) => _rowHtml(row, idx)).join('');

    // Bind sold_stock input change.
    container.querySelectorAll('.sold-stock-input').forEach((input) => {
      input.addEventListener('input', _handleSoldInput);
    });

    // Bind remarks input.
    container.querySelectorAll('.remarks-input').forEach((input) => {
      input.addEventListener('input', _handleRemarksInput);
    });
  }

  function _rowHtml(row, idx) {
    const inStock    = parseInt(row.in_stock,    10) || 0;
    const newStock   = parseInt(row.new_stock,   10) || 0;
    const totalStock = inStock + newStock;
    const soldStock  = parseInt(row.sold_stock,  10) || 0;
    const stockLeft  = totalStock - soldStock;

    return `
      <tr data-row-idx="${idx}" data-product-id="${row.product_id}">
        <td class="px-4 py-3">
          <div class="font-medium text-white text-sm">${BymoreishApp.escapeHtml(row.product_name)}</div>
          <div class="text-xs text-gray-500">${BymoreishApp.escapeHtml(row.product_unit || '')}</div>
        </td>
        <td class="px-4 py-3 stock-readonly-cell tabular-nums text-center"
            id="in-stock-${idx}">${inStock}</td>
        <td class="px-4 py-3 stock-readonly-cell tabular-nums text-center"
            id="new-stock-${idx}">${newStock}</td>
        <td class="px-4 py-3 stock-readonly-cell tabular-nums text-center font-semibold text-white"
            id="total-stock-${idx}">${totalStock}</td>
        <td class="px-4 py-3 stock-input-cell text-center">
          <input type="number"
                 class="sold-stock-input"
                 min="0"
                 max="${totalStock}"
                 value="${soldStock}"
                 data-row-idx="${idx}"
                 data-product-id="${row.product_id}"
                 aria-label="Sold stock for ${BymoreishApp.escapeHtml(row.product_name)}">
        </td>
        <td class="px-4 py-3 tabular-nums text-center font-semibold"
            id="stock-left-${idx}"
            style="color: ${stockLeft < 5 ? '#ef4444' : stockLeft < 20 ? '#f59e0b' : '#4CB050'}">
          ${stockLeft}
        </td>
        <td class="px-4 py-3">
          <input type="text"
                 class="remarks-input glass-input text-xs"
                 placeholder="Optional remarks…"
                 value="${BymoreishApp.escapeHtml(row.remarks || '')}"
                 data-row-idx="${idx}">
        </td>
      </tr>`;
  }

  /* ----------------------------------------------------------
     Real-time calculation on sold_stock input
     ---------------------------------------------------------- */

  function _handleSoldInput(e) {
    const input    = e.currentTarget;
    const idx      = parseInt(input.dataset.rowIdx, 10);
    const row      = _rows[idx];
    if (!row) return;

    const inStock    = parseInt(row.in_stock,  10) || 0;
    const newStock   = parseInt(row.new_stock, 10) || 0;
    const totalStock = inStock + newStock;
    let   soldStock  = parseInt(input.value,   10) || 0;

    // Clamp to valid range.
    if (soldStock < 0)           soldStock = 0;
    if (soldStock > totalStock)  soldStock = totalStock;
    input.value = String(soldStock);

    const stockLeft = totalStock - soldStock;

    // Update display.
    const leftCell = document.getElementById(`stock-left-${idx}`);
    if (leftCell) {
      leftCell.textContent = String(stockLeft);
      leftCell.style.color = stockLeft < 5
        ? '#ef4444'
        : stockLeft < 20
          ? '#f59e0b'
          : '#4CB050';
    }

    // Persist to local state.
    row.sold_stock  = soldStock;
    row.total_stock = totalStock;
    row.stock_left  = stockLeft;

    _updateSummary();
    _updateKPIs();
  }

  function _handleRemarksInput(e) {
    const input = e.currentTarget;
    const idx   = parseInt(input.dataset.rowIdx, 10);
    if (_rows[idx]) _rows[idx].remarks = input.value;
  }

  /* ----------------------------------------------------------
     Summary row
     ---------------------------------------------------------- */

  function _updateSummary() {
    let totalIn    = 0;
    let totalNew   = 0;
    let totalTotal = 0;
    let totalSold  = 0;
    let totalLeft  = 0;

    _rows.forEach((row) => {
      totalIn    += parseInt(row.in_stock,    10) || 0;
      totalNew   += parseInt(row.new_stock,   10) || 0;
      totalTotal += (parseInt(row.in_stock, 10) || 0) + (parseInt(row.new_stock, 10) || 0);
      totalSold  += parseInt(row.sold_stock,  10) || 0;
      totalLeft  += parseInt(row.stock_left,  10) || 0;
    });

    const ids = ['summary-in', 'summary-new', 'summary-total', 'summary-sold', 'summary-left'];
    const vals = [totalIn, totalNew, totalTotal, totalSold, totalLeft];
    ids.forEach((id, i) => {
      const el = document.getElementById(id);
      if (el) el.textContent = String(vals[i]);
    });
  }

  /* ----------------------------------------------------------
     KPI cards
     ---------------------------------------------------------- */

  function _updateKPIs() {
    let totalIn   = 0;
    let totalNew  = 0;
    let totalSold = 0;
    let totalLeft = 0;

    _rows.forEach((row) => {
      totalIn   += parseInt(row.in_stock,   10) || 0;
      totalNew  += parseInt(row.new_stock,  10) || 0;
      totalSold += parseInt(row.sold_stock, 10) || 0;
      totalLeft += parseInt(row.stock_left, 10) || 0;
    });

    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = String(v); };
    set('kpi-opening', totalIn);
    set('kpi-imports', totalNew);
    set('kpi-sold',    totalSold);
    set('kpi-closing', totalLeft);
  }

  /* ----------------------------------------------------------
     Search / filter
     ---------------------------------------------------------- */

  function _bindSearch() {
    const input = document.getElementById('stock-search');
    if (!input) return;

    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      document.querySelectorAll('#stock-table-body tr[data-product-id]').forEach((row) => {
        const name = (row.querySelector('td:first-child')?.textContent || '').toLowerCase();
        row.style.display = name.includes(query) ? '' : 'none';
      });
    });
  }

  /* ----------------------------------------------------------
     Save stock
     ---------------------------------------------------------- */

  function _bindSave() {
    const btn = document.getElementById('btn-save-stock');
    if (btn) btn.addEventListener('click', _saveStock);
  }

  async function _saveStock() {
    if (_rows.length === 0) {
      BymoreishApp.showToast('No stock entries to save.', 'warning');
      return;
    }

    const entries = _rows.map((row) => ({
      product_id:  row.product_id,
      in_stock:    parseInt(row.in_stock,   10) || 0,
      sold_stock:  parseInt(row.sold_stock, 10) || 0,
      remarks:     row.remarks || '',
    }));

    try {
      BymoreishApp.showLoading();
      const result = await BymoreishApp.bymAjax('bym_save_stock', {
        branch_id:  _getBranchId(),
        stock_date: _currentDate,
        entries,
      });
      BymoreishApp.showToast(
        `Saved ${result.saved || entries.length} stock record(s).`, 'success'
      );
      // Reload to get fresh server-calculated values.
      _loadStock(_currentDate);
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to save stock.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  /* ----------------------------------------------------------
     Import form
     ---------------------------------------------------------- */

  function _renderImportForm(items) {
    const grid = document.getElementById('import-items-grid');
    if (!grid) return;

    if (!items || items.length === 0) {
      grid.innerHTML = '<div class="text-center py-6 text-gray-500 text-xs col-span-full">No stock items available.</div>';
      return;
    }

    grid.innerHTML = items.map((item) => `
      <div class="glass rounded-xl p-4 space-y-2">
        <p class="text-sm font-semibold text-white">${BymoreishApp.escapeHtml(item.name)}</p>
        <p class="text-xs text-gray-500">${BymoreishApp.escapeHtml(item.unit || '')}</p>
        <input type="number" min="0" value="0"
               class="import-qty-input bym-input w-full text-center"
               data-product-id="${item.id}"
               placeholder="Qty">
      </div>
    `).join('');
  }

  function _bindImportSave() {
    const btn = document.getElementById('btn-save-import');
    if (btn) btn.addEventListener('click', _saveImports);
  }

  async function _saveImports() {
    const inputs  = document.querySelectorAll('.import-qty-input');
    const entries = [];

    inputs.forEach((input) => {
      const qty = parseInt(input.value, 10) || 0;
      if (qty > 0) {
        entries.push({
          product_id: parseInt(input.dataset.productId, 10),
          quantity:   qty,
        });
      }
    });

    if (entries.length === 0) {
      BymoreishApp.showToast('Enter at least one import quantity.', 'warning');
      return;
    }

    try {
      BymoreishApp.showLoading();
      await BymoreishApp.bymAjax('bym_save_import', {
        branch_id:   _getBranchId(),
        import_date: _currentDate,
        entries,
      });
      BymoreishApp.showToast(`Saved ${entries.length} import(s).`, 'success');

      // Reset import inputs.
      inputs.forEach((input) => { input.value = '0'; });

      // Reload stock to reflect new imports.
      _loadStock(_currentDate);
    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to save imports.', 'error');
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
  return { init };
})();

document.addEventListener('DOMContentLoaded', () => StockPage.init());

window.StockPage = StockPage;
