/**
 * Shared UI for Customers and Suppliers.
 * Balances are displayed from API/mock only and are not calculated here.
 */
(function (global) {
  'use strict';

  function kind() {
    return document.body.getAttribute('data-contact') || 'customers';
  }

  function isCustomer() {
    return kind() === 'customers';
  }

  function seedList() {
    return (global.MBMS_MOCK && global.MBMS_MOCK[kind()]) || [];
  }

  function allRecords() {
    return seedList();
  }

  function findRecord(id) {
    return allRecords().filter(function (row) { return row.id === id; })[0] || null;
  }

  function historyFor(id) {
    var key = isCustomer() ? 'customerHistory' : 'supplierHistory';
    var seed = (global.MBMS_MOCK && global.MBMS_MOCK[key] && global.MBMS_MOCK[key][id]) || {};
    return {
      sales: seed.sales || [],
      purchases: seed.purchases || [],
      payments: seed.payments || [],
      transactions: seed.transactions || []
    };
  }

  function renderList(source) {
    var query = String((document.getElementById('module-search') || {}).value || '').toLowerCase().trim();
    var location = (document.getElementById('module-filter') || {}).value || '';
    var rows = allRecords().filter(function (row) {
      var matchName = !query || String(row.name || '').toLowerCase().indexOf(query) !== -1;
      var matchLocation = !location || row.location === location;
      return matchName && matchLocation;
    });
    global.MBMS_RECORDS.showBanner(document.getElementById('source-banner'), source);
    global.MBMS_RECORDS.fillSelect(
      document.getElementById('module-filter'),
      global.MBMS_RECORDS.locations(),
      function (row) { return row.name; },
      function (row) { return row.name; },
      location,
      'All locations'
    );
    global.MBMS_RECORDS.renderTable(document.getElementById('contact-list-body'), rows, [
      { key: 'name' },
      { key: 'phone' },
      { key: 'location' },
      { key: 'balance_display' },
      {
        render: function (row) {
          return '<a class="btn btn-sm btn-outline-primary" href="?id=' + encodeURIComponent(row.id) + '">Open</a>';
        }
      }
    ], 'No records match.');
  }

  function renderDetail(record, source) {
    document.getElementById('detail-title').textContent = record.name;
    document.getElementById('detail-phone').textContent = record.phone || 'No phone';
    document.getElementById('detail-address').textContent = record.address || 'No address';
    document.getElementById('detail-location').textContent = record.location || '—';
    document.getElementById('detail-balance').textContent = record.balance_display || '—';
    document.getElementById('detail-balance-note').textContent = record.notes || 'Official balance will come from Laravel.';
    global.MBMS_RECORDS.showBanner(document.getElementById('source-banner'), source);

    var history = historyFor(record.id);
    if (isCustomer()) {
      global.MBMS_RECORDS.renderTable(document.getElementById('sales-history-body'), history.sales, [
        { key: 'date' }, { key: 'detail' }, { key: 'amount' }, { key: 'status' }
      ]);
    } else {
      global.MBMS_RECORDS.renderTable(document.getElementById('purchase-history-body'), history.purchases, [
        { key: 'date' }, { key: 'detail' }, { key: 'amount' }
      ]);
    }
    global.MBMS_RECORDS.renderTable(document.getElementById('payment-history-body'), history.payments, [
      { key: 'date' }, { key: 'amount' }, { key: 'method' }, { key: 'location' }
    ]);
    global.MBMS_RECORDS.renderTable(document.getElementById('transaction-history-body'), history.transactions, [
      { key: 'date' }, { key: 'type' }, { key: 'detail' }, { key: 'amount' }
    ]);

    global.MBMS_UTILS.fillForm(document.getElementById('contact-form'), record);
    document.getElementById('contact-form-title').textContent = 'Edit ' + (isCustomer() ? 'customer' : 'supplier');
  }

  function setMode(detail) {
    document.getElementById('list-view').hidden = !!detail;
    document.getElementById('detail-view').hidden = !detail;
  }

  function load() {
    var root = document.getElementById('contacts-root');
    var id = global.MBMS_RECORDS.queryParam('id');
    setMode(!!id);
    if (!id) {
      renderList();
    }
    return global.MBMS_RECORDS.fetchOrMock(kind(), seedList)
      .then(function (result) {
        if (id) {
          var record = findRecord(id);
          if (!record && result.source === 'api' && result.data) {
            var list = Array.isArray(result.data) ? result.data : result.data.data || [];
            record = list.filter(function (row) { return row.id === id; })[0] || null;
          }
          if (!record) {
            setMode(false);
            global.MBMS_UI.notify({ type: 'danger', message: 'That record is not available.' });
            renderList(result.source);
            return;
          }
          setMode(true);
          renderDetail(record, result.source);
        } else {
          setMode(false);
          renderList(result.source);
        }
      })
      ;
  }

  function openCreate() {
    var form = document.getElementById('contact-form');
    form.reset();
    document.getElementById('contact-id').value = '';
    document.getElementById('contact-form-title').textContent = 'Add ' + (isCustomer() ? 'customer' : 'supplier');
  }

  global.MBMS_PAGE = {
    active: document.body.getAttribute('data-page'),
    title: isCustomer() ? 'Customers' : 'Suppliers',
    onReady: function () {
      global.MBMS_RECORDS.fillSelect(
        document.getElementById('contact-location'),
        global.MBMS_RECORDS.locations(),
        function (row) { return row.name; },
        function (row) { return row.name; }
      );

      var search = document.getElementById('module-search');
      var filter = document.getElementById('module-filter');
      if (search) {
        search.addEventListener('input', global.MBMS_UTILS.debounce(load, 250));
      }
      if (filter) {
        filter.addEventListener('change', load);
      }

      document.getElementById('add-contact').addEventListener('click', openCreate);
      var editBtn = document.getElementById('edit-contact');
      if (editBtn) {
        editBtn.addEventListener('click', function () {
          document.getElementById('contact-form-title').textContent = 'Edit ' + (isCustomer() ? 'customer' : 'supplier');
        });
      }

      global.MBMS_UI.bindForm(document.getElementById('contact-form'), {
        onSubmit: function (values) {
          var id = values.id;
          var method = id ? 'put' : 'post';
          var path = kind() + (id ? '/' + id : '');
          if (!id) {
            values.id = 'local-' + Date.now();
            values.balance_display = '—';
            values.notes = 'New sample record. Official balance will come from Laravel.';
          }
          return global.MBMS_RECORDS.submitOrMock(method, path, values, function (body) {
            if (id) {
              return global.MBMS_RECORDS.storeUpdate(kind(), id, body) || global.MBMS_RECORDS.storeSave(kind(), body);
            }
            return global.MBMS_RECORDS.storeSave(kind(), body);
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api' ? 'Saved to the API.' : 'API not connected. Saved as a local sample record only.'
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('contactModal')).hide();
            if (!id) {
              global.location.search = '?id=' + encodeURIComponent((result.data && result.data.id) || values.id);
              return;
            }
            load();
          });
        }
      });

      load();
    }
  };
})(window);
