/**
 * Profit & Loss display. Shows backend-provided figures only.
 * Does not choose FIFO or weighted average, and does not compute profit.
 */
(function (global) {
  'use strict';

  function mockData() {
    return (global.MBMS_MOCK && global.MBMS_MOCK.profitLoss) || { summary: {}, rows: [] };
  }

  function filters() {
    return global.MBMS_FILTERS ? global.MBMS_FILTERS.params(document.getElementById('pl-filters')) : {};
  }

  function renderSummary(summary) {
    var keys = [
      ['revenue', 'Revenue'],
      ['cost', 'Cost'],
      ['gross_profit', 'Gross Profit'],
      ['operating_expenses', 'Operating Expenses'],
      ['net_profit', 'Net Profit/Loss']
    ];
    var host = document.getElementById('pl-summary');
    host.innerHTML = keys.map(function (pair) {
      var item = summary[pair[0]] || {};
      return (
        '<div class="col-6 col-lg">' +
        '<article class="mbms-card card mbms-stat-card"><div class="card-body">' +
        '<div class="mbms-stat-label">' + global.MBMS_UTILS.escapeHtml(item.label || pair[1]) + '</div>' +
        '<div class="mbms-stat-value">' + global.MBMS_UTILS.escapeHtml(item.display != null ? item.display : '—') + '</div>' +
        '<span class="badge text-bg-secondary">Backend value</span>' +
        '</div></article></div>'
      );
    }).join('');
  }

  function render(result) {
    var data = result.data || mockData();
    if (!data.summary && result.source === 'api') {
      data = Object.assign({ summary: {}, rows: [] }, data);
    }
    if (result.source !== 'api') {
      data = mockData();
    }
    global.MBMS_RECORDS.showBanner(
      document.getElementById('source-banner'),
      result.source,
      data.notice || 'Showing sample P&L figures. Official calculations stay on Laravel.'
    );
    renderSummary(data.summary || {});
    var rows = data.rows || [];
    if (!rows.length) {
      document.getElementById('pl-table-wrap').hidden = true;
      document.getElementById('pl-empty').hidden = false;
      global.MBMS_UI.renderEmpty(document.getElementById('pl-empty'), {
        title: 'No profit/loss rows',
        message: 'Choose a date range to see profit and loss. Home expenses are not included here.'
      });
      return;
    }
    document.getElementById('pl-empty').hidden = true;
    document.getElementById('pl-table-wrap').hidden = false;
    global.MBMS_RECORDS.renderTable(document.getElementById('pl-body'), rows, [
      { key: 'module' }, { key: 'product' }, { key: 'location' }, { key: 'revenue' }, { key: 'cost' }, { key: 'result' }
    ]);
  }

  function load() {
    render(mockData());
    return global.MBMS_RECORDS.fetchOrMock('reports/profit-loss', mockData, filters())
      .then(render)
      .catch(function (error) {
        global.MBMS_UI.renderError(document.getElementById('pl-empty'), {
          title: 'Unable to load profit/loss',
          message: global.MBMS_UTILS.getErrorMessage(error),
          actionLabel: 'Try again',
          onAction: load
        });
        document.getElementById('pl-empty').hidden = false;
        document.getElementById('pl-table-wrap').hidden = true;
      });
  }

  global.MBMS_PAGE = {
    active: 'profit-loss',
    title: 'Profit & Loss',
    onReady: function () {
      if (global.MBMS_FILTERS) {
        global.MBMS_FILTERS.bind(document.getElementById('pl-filters'), load);
      }
      load();
    }
  };
})(window);
