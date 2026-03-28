/**
 * Bymoreish Inventory – Analytics Page
 *
 * Features:
 *  - Period filter tabs: Daily / Weekly / Monthly / Yearly
 *  - Custom date range picker
 *  - Chart.js integration:
 *      · Revenue vs Expenses (line)
 *      · Best-selling products (pie / doughnut)
 *      · Product performance (horizontal bar)
 *      · Payment mode breakdown (bar)
 *  - KPI summary cards
 *  - AI-style insight cards generated from data trends
 *
 * Depends on:
 *  - window.bymConfig  (ajaxUrl, nonce, currentUser)
 *  - assets/js/main.js (BymoreishApp, bymAjax, etc.)
 *  - Chart.js (loaded via CDN in page template)
 */

const AnalyticsPage = (() => {
  /* ----------------------------------------------------------
     Chart instances (kept for destroy-before-recreate)
     ---------------------------------------------------------- */
  const _charts = {
    revenue:  null,
    pie:      null,
    bar:      null,
    payment:  null,
  };

  /* ----------------------------------------------------------
     State
     ---------------------------------------------------------- */
  let _currentPeriod = 'monthly';
  let _dateFrom = '';
  let _dateTo   = '';

  /* ----------------------------------------------------------
     Chart.js defaults
     ---------------------------------------------------------- */
  const CHART_COLORS = {
    gold:    '#EECE55',
    green:   '#4CB050',
    red:     '#ef4444',
    blue:    '#60a5fa',
    purple:  '#a78bfa',
    orange:  '#fb923c',
    teal:    '#2dd4bf',
    pink:    '#f472b6',
  };

  const PALETTE = Object.values(CHART_COLORS);

  function _applyChartDefaults() {
    if (!window.Chart) return;
    Chart.defaults.color           = 'rgba(255,255,255,0.5)';
    Chart.defaults.font.family     = "'Inter', ui-sans-serif, system-ui, sans-serif";
    Chart.defaults.font.size       = 12;
    Chart.defaults.borderColor     = 'rgba(255,255,255,0.06)';
    Chart.defaults.plugins.legend.labels.color = 'rgba(255,255,255,0.7)';
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(10,10,20,0.95)';
    Chart.defaults.plugins.tooltip.borderColor     = 'rgba(238,206,85,0.3)';
    Chart.defaults.plugins.tooltip.borderWidth     = 1;
    Chart.defaults.plugins.tooltip.padding         = 12;
    Chart.defaults.plugins.tooltip.titleColor      = '#EECE55';
    Chart.defaults.plugins.tooltip.bodyColor       = 'rgba(255,255,255,0.8)';
    Chart.defaults.plugins.tooltip.cornerRadius    = 10;
  }

  /* ----------------------------------------------------------
     Init
     ---------------------------------------------------------- */

  function init() {
    _applyChartDefaults();
    _bindFilterTabs();
    _bindDateRange();
    _setDefaultRange('monthly');
    _loadAnalytics();
  }

  /* ----------------------------------------------------------
     Period helpers
     ---------------------------------------------------------- */

  function _setDefaultRange(period) {
    const now   = new Date();
    let from, to;

    to = now.toISOString().slice(0, 10);

    switch (period) {
      case 'daily':
        from = to;
        break;
      case 'weekly': {
        const d = new Date(now);
        d.setDate(d.getDate() - 6);
        from = d.toISOString().slice(0, 10);
        break;
      }
      case 'monthly':
        from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        break;
      case 'yearly':
        from = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10);
        break;
      default:
        from = to;
    }

    _dateFrom = from;
    _dateTo   = to;

    const fromEl = document.getElementById('analytics-date-from');
    const toEl   = document.getElementById('analytics-date-to');
    if (fromEl) fromEl.value = from;
    if (toEl)   toEl.value   = to;
  }

  /* ----------------------------------------------------------
     Bind controls
     ---------------------------------------------------------- */

  function _bindFilterTabs() {
    document.querySelectorAll('.filter-tab[data-period]').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.filter-tab[data-period]').forEach((t) =>
          t.classList.remove('active'));
        tab.classList.add('active');
        _currentPeriod = tab.dataset.period;
        _setDefaultRange(_currentPeriod);
        _loadAnalytics();
      });
    });
  }

  function _bindDateRange() {
    const fromEl = document.getElementById('analytics-date-from');
    const toEl   = document.getElementById('analytics-date-to');
    const btn    = document.getElementById('btn-analytics-apply');

    if (btn) {
      btn.addEventListener('click', () => {
        _dateFrom = fromEl?.value || _dateFrom;
        _dateTo   = toEl?.value   || _dateTo;
        _loadAnalytics();
      });
    }

    // Allow Enter key in date inputs.
    [fromEl, toEl].forEach((el) => {
      if (el) el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') btn?.click();
      });
    });
  }

  /* ----------------------------------------------------------
     Data fetch
     ---------------------------------------------------------- */

  async function _loadAnalytics() {
    try {
      BymoreishApp.showLoading();

      const data = await BymoreishApp.bymAjax('bym_get_analytics', {
        branch_id: _getBranchId(),
        date_from: _dateFrom,
        date_to:   _dateTo,
      });

      _renderKPIs(data);
      _renderRevenueChart(data);
      _renderPieChart(data);
      _renderBarChart(data);
      _renderPaymentChart(data);
      _renderInsights(data);

    } catch (err) {
      BymoreishApp.showToast(err.message || 'Failed to load analytics.', 'error');
    } finally {
      BymoreishApp.hideLoading();
    }
  }

  /* ----------------------------------------------------------
     KPI Cards
     ---------------------------------------------------------- */

  function _renderKPIs(data) {
    const totals      = data.totals      || {};
    const totalExp    = parseFloat(data.total_expenses || 0);
    const netProfit   = parseFloat(data.net_profit     || 0);
    const totalRev    = parseFloat(totals.total_revenue || 0);
    const totalOrders = parseInt(totals.total_orders   || 0, 10);

    _setKPI('kpi-revenue',    BymoreishApp.formatNaira(totalRev));
    _setKPI('kpi-expenses',   BymoreishApp.formatNaira(totalExp));
    _setKPI('kpi-profit',     BymoreishApp.formatNaira(netProfit), netProfit >= 0 ? 'up' : 'down');
    _setKPI('kpi-orders',     String(totalOrders));
    _setKPI('kpi-transfer',   BymoreishApp.formatNaira(totals.total_transfer || 0));
    _setKPI('kpi-card',       BymoreishApp.formatNaira(totals.total_card     || 0));
    _setKPI('kpi-cash',       BymoreishApp.formatNaira(totals.total_cash     || 0));
  }

  function _setKPI(id, value, trend = '') {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = value;
    if (trend) {
      el.className = `kpi-value bold-num ${trend === 'up' ? 'kpi-trend-up' : 'kpi-trend-down'}`;
    }
  }

  /* ----------------------------------------------------------
     Revenue vs Expenses Line Chart
     ---------------------------------------------------------- */

  function _renderRevenueChart(data) {
    const canvas = document.getElementById('chart-revenue');
    if (!canvas || !window.Chart) return;

    // Merge daily_revenue and daily_expenses by date.
    const revMap = {};
    (data.daily_revenue || []).forEach((r) => {
      revMap[r.date] = parseFloat(r.total_revenue || 0);
    });
    const expMap = {};
    (data.daily_expenses || []).forEach((e) => {
      expMap[e.date] = parseFloat(e.total_expenses || 0);
    });

    const allDates = [...new Set([
      ...(data.daily_revenue || []).map((r) => r.date),
      ...(data.daily_expenses || []).map((e) => e.date),
    ])].sort();

    if (_charts.revenue) { _charts.revenue.destroy(); _charts.revenue = null; }

    _charts.revenue = new Chart(canvas, {
      type: 'line',
      data: {
        labels:   allDates.map((d) => _formatChartDate(d)),
        datasets: [
          {
            label:           'Revenue',
            data:            allDates.map((d) => revMap[d] || 0),
            borderColor:     CHART_COLORS.gold,
            backgroundColor: 'rgba(238,206,85,0.08)',
            borderWidth:     2.5,
            pointBackgroundColor: CHART_COLORS.gold,
            pointRadius:          3,
            pointHoverRadius:     6,
            fill:            true,
            tension:         0.4,
          },
          {
            label:           'Expenses',
            data:            allDates.map((d) => expMap[d] || 0),
            borderColor:     CHART_COLORS.red,
            backgroundColor: 'rgba(239,68,68,0.06)',
            borderWidth:     2,
            pointBackgroundColor: CHART_COLORS.red,
            pointRadius:          3,
            pointHoverRadius:     6,
            fill:            true,
            tension:         0.4,
          },
        ],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                ` ${ctx.dataset.label}: ${BymoreishApp.formatNaira(ctx.raw)}`,
            },
          },
        },
        scales: {
          x: {
            grid:  { color: 'rgba(255,255,255,0.05)' },
            ticks: { maxTicksLimit: 10 },
          },
          y: {
            grid:  { color: 'rgba(255,255,255,0.05)' },
            ticks: {
              callback: (v) => BymoreishApp.formatNaira(v),
            },
            beginAtZero: true,
          },
        },
      },
    });
  }

  /* ----------------------------------------------------------
     Best-Selling Products Doughnut Chart
     ---------------------------------------------------------- */

  function _renderPieChart(data) {
    const canvas = document.getElementById('chart-pie');
    if (!canvas || !window.Chart) return;

    const top = (data.top_products || []).slice(0, 8);
    if (top.length === 0) {
      _showEmptyChart(canvas, 'No sales data for this period.');
      return;
    }

    if (_charts.pie) { _charts.pie.destroy(); _charts.pie = null; }

    _charts.pie = new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels:   top.map((p) => p.product_name),
        datasets: [{
          data:            top.map((p) => parseFloat(p.total_qty || 0)),
          backgroundColor: PALETTE.slice(0, top.length),
          borderColor:     'rgba(10,10,18,0.8)',
          borderWidth:     2,
          hoverOffset:     8,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: true,
        cutout:              '62%',
        plugins: {
          legend: { position: 'right' },
          tooltip: {
            callbacks: {
              label: (ctx) =>
                ` ${ctx.label}: ${ctx.raw} units (${BymoreishApp.formatNaira(
                  (data.top_products[ctx.dataIndex] || {}).total_revenue || 0)})`,
            },
          },
        },
      },
    });
  }

  /* ----------------------------------------------------------
     Product Performance Horizontal Bar Chart
     ---------------------------------------------------------- */

  function _renderBarChart(data) {
    const canvas = document.getElementById('chart-bar');
    if (!canvas || !window.Chart) return;

    const top = (data.top_products || []).slice(0, 10).reverse();
    if (top.length === 0) {
      _showEmptyChart(canvas, 'No product performance data.');
      return;
    }

    if (_charts.bar) { _charts.bar.destroy(); _charts.bar = null; }

    _charts.bar = new Chart(canvas, {
      type: 'bar',
      data: {
        labels:   top.map((p) => p.product_name),
        datasets: [
          {
            label:           'Revenue (₦)',
            data:            top.map((p) => parseFloat(p.total_revenue || 0)),
            backgroundColor: 'rgba(238,206,85,0.75)',
            borderColor:     CHART_COLORS.gold,
            borderWidth:     1.5,
            borderRadius:    6,
            yAxisID:         'y',
          },
          {
            label:           'Units Sold',
            data:            top.map((p) => parseFloat(p.total_qty || 0)),
            backgroundColor: 'rgba(76,176,80,0.65)',
            borderColor:     CHART_COLORS.green,
            borderWidth:     1.5,
            borderRadius:    6,
            yAxisID:         'y1',
          },
        ],
      },
      options: {
        indexAxis:           'y',
        responsive:          true,
        maintainAspectRatio: true,
        interaction:         { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: (ctx) => {
                if (ctx.datasetIndex === 0)
                  return ` Revenue: ${BymoreishApp.formatNaira(ctx.raw)}`;
                return ` Units: ${ctx.raw}`;
              },
            },
          },
        },
        scales: {
          x: {
            grid:  { color: 'rgba(255,255,255,0.05)' },
            ticks: { callback: (v) => BymoreishApp.formatNaira(v) },
          },
          y: { grid: { color: 'rgba(255,255,255,0.05)' } },
          y1: {
            position: 'right',
            grid:     { drawOnChartArea: false },
          },
        },
      },
    });
  }

  /* ----------------------------------------------------------
     Payment Mode Breakdown Bar Chart
     ---------------------------------------------------------- */

  function _renderPaymentChart(data) {
    const canvas = document.getElementById('chart-payment');
    if (!canvas || !window.Chart) return;

    const totals = data.totals || {};
    const xfer   = parseFloat(totals.total_transfer || 0);
    const card   = parseFloat(totals.total_card     || 0);
    const cash   = parseFloat(totals.total_cash     || 0);

    if (_charts.payment) { _charts.payment.destroy(); _charts.payment = null; }

    _charts.payment = new Chart(canvas, {
      type: 'bar',
      data: {
        labels:   ['Transfer', 'Card (POS)', 'Cash'],
        datasets: [{
          label:           'Amount',
          data:            [xfer, card, cash],
          backgroundColor: [
            'rgba(96,165,250,0.75)',
            'rgba(167,139,250,0.75)',
            'rgba(76,176,80,0.75)',
          ],
          borderColor:     [CHART_COLORS.blue, CHART_COLORS.purple, CHART_COLORS.green],
          borderWidth:     1.5,
          borderRadius:    8,
        }],
      },
      options: {
        responsive:          true,
        maintainAspectRatio: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => ` ${BymoreishApp.formatNaira(ctx.raw)}`,
            },
          },
        },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.05)' } },
          y: {
            grid:  { color: 'rgba(255,255,255,0.05)' },
            ticks: { callback: (v) => BymoreishApp.formatNaira(v) },
            beginAtZero: true,
          },
        },
      },
    });
  }

  /* ----------------------------------------------------------
     AI-style Insights
     ---------------------------------------------------------- */

  function _renderInsights(data) {
    const container = document.getElementById('analytics-insights');
    if (!container) return;

    const insights = _generateInsights(data);

    container.innerHTML = insights.map((insight) => `
      <div class="insight-card">
        <div class="insight-icon">
          <span class="iconify text-yellow-400 text-lg" data-icon="${insight.icon}"></span>
        </div>
        <div class="insight-text">${insight.text}</div>
      </div>
    `).join('');

    if (window.Iconify) window.Iconify.scan(container);
  }

  /**
   * Generate human-readable insights from the analytics data.
   * @param {object} data
   * @returns {Array<{icon:string, text:string}>}
   */
  function _generateInsights(data) {
    const insights  = [];
    const totals    = data.totals    || {};
    const topProds  = data.top_products || [];
    const revArr    = data.daily_revenue  || [];
    const expArr    = data.daily_expenses || [];
    const totalRev  = parseFloat(totals.total_revenue || 0);
    const totalExp  = parseFloat(data.total_expenses  || 0);
    const netProfit = parseFloat(data.net_profit       || 0);
    const orders    = parseInt(totals.total_orders     || 0, 10);

    // Profit insight.
    if (netProfit > 0) {
      const margin = totalRev > 0 ? ((netProfit / totalRev) * 100).toFixed(1) : 0;
      insights.push({
        icon: 'solar:chart-2-bold',
        text: `<strong>Profit Margin is ${margin}%.</strong> Your revenue of
               ${BymoreishApp.formatNaira(totalRev)} exceeds expenses by
               ${BymoreishApp.formatNaira(netProfit)} — well done!`,
      });
    } else if (netProfit < 0) {
      insights.push({
        icon: 'solar:danger-triangle-bold',
        text: `<strong>Operating at a loss of ${BymoreishApp.formatNaira(Math.abs(netProfit))}.</strong>
               Expenses (${BymoreishApp.formatNaira(totalExp)}) exceed revenue
               (${BymoreishApp.formatNaira(totalRev)}). Review cost drivers.`,
      });
    }

    // Best seller.
    if (topProds.length > 0) {
      const top = topProds[0];
      insights.push({
        icon: 'solar:star-bold',
        text: `<strong>${BymoreishApp.escapeHtml(top.product_name)}</strong> is your
               best-selling item with <strong>${top.total_qty} units</strong> sold,
               generating ${BymoreishApp.formatNaira(top.total_revenue)}.`,
      });
    }

    // Payment mix.
    const xfer = parseFloat(totals.total_transfer || 0);
    const card = parseFloat(totals.total_card     || 0);
    const cash = parseFloat(totals.total_cash     || 0);
    if (totalRev > 0) {
      const dominant = [
        { label: 'bank transfer', val: xfer },
        { label: 'card (POS)',    val: card },
        { label: 'cash',          val: cash },
      ].sort((a, b) => b.val - a.val)[0];
      insights.push({
        icon: 'solar:wallet-money-bold',
        text: `<strong>${(dominant.val / totalRev * 100).toFixed(0)}% of revenue</strong>
               was received via <strong>${dominant.label}</strong>
               (${BymoreishApp.formatNaira(dominant.val)}). Consider promoting
               the most cost-efficient payment channel.`,
      });
    }

    // Revenue trend.
    if (revArr.length >= 3) {
      const last3  = revArr.slice(-3).map((r) => parseFloat(r.total_revenue || 0));
      const trend  = last3[2] - last3[0];
      if (trend > 0) {
        insights.push({
          icon: 'solar:graph-up-bold',
          text: `<strong>Upward revenue trend</strong> over the last 3 data points
                 (+${BymoreishApp.formatNaira(trend)}). Keep up the momentum!`,
        });
      } else if (trend < 0) {
        insights.push({
          icon: 'solar:graph-down-bold',
          text: `<strong>Declining revenue trend</strong> over the last 3 data points
                 (${BymoreishApp.formatNaira(trend)}). Consider promotions or menu updates.`,
        });
      }
    }

    // Average order value.
    if (orders > 0 && totalRev > 0) {
      const aov = totalRev / orders;
      insights.push({
        icon: 'solar:receipt-bold',
        text: `<strong>Average order value is ${BymoreishApp.formatNaira(aov)}</strong>
               across ${orders} completed orders. Upselling extras can grow this metric.`,
      });
    }

    // Low product diversity warning.
    if (topProds.length === 1) {
      insights.push({
        icon: 'solar:info-circle-bold',
        text: `All revenue is concentrated in a <strong>single product</strong>.
               Diversifying your menu reduces risk and attracts more customers.`,
      });
    }

    return insights.length > 0 ? insights : [{
      icon: 'solar:graph-bold',
      text: 'No data available for the selected period. Try a wider date range.',
    }];
  }

  /* ----------------------------------------------------------
     Helpers
     ---------------------------------------------------------- */

  function _formatChartDate(isoDate) {
    if (!isoDate) return '';
    const d = new Date(isoDate + 'T00:00:00');
    return d.toLocaleDateString('en-NG', { day: 'numeric', month: 'short' });
  }

  function _showEmptyChart(canvas, message) {
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'rgba(255,255,255,0.25)';
    ctx.font      = '14px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
  }

  function _getBranchId() {
    const el = document.getElementById('bym-branch-id');
    return el ? parseInt(el.value, 10) : 0;
  }

  /* ----------------------------------------------------------
     Public API
     ---------------------------------------------------------- */
  return { init };
})();

document.addEventListener('DOMContentLoaded', () => AnalyticsPage.init());

window.AnalyticsPage = AnalyticsPage;
