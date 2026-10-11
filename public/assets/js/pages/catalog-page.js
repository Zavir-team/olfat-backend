/**
 * Shared catalog UI for Tea, Rice, and Detergents.
 * Displays backend/mock values only. Does not calculate official stock or profit.
 */
(function (global) {
  'use strict';

  var config = {
    tea: {
      title: 'Tea',
      itemLabel: 'Tea type',
      collection: 'types',
      endpoint: 'tea',
      mockKey: 'tea',
      variantLabel: 'Bag size',
      showBags: true,
      showRemaining: true,
      creditNote: 'Tea can be sold for cash or on credit. Official remaining debt comes from Laravel.'
    },
    rice: {
      title: 'Rice',
      itemLabel: 'Type / quality',
      collection: 'types',
      endpoint: 'rice',
      mockKey: 'rice',
      variantLabel: 'Bag size',
      showBags: true,
      showRemaining: false,
      creditNote: 'Rice may be sold for cash or credit. Supplier-credit rules are not implemented in this UI.'
    },
    detergents: {
      title: 'Detergents',
      itemLabel: 'Product',
      collection: 'products',
      endpoint: 'detergents',
      mockKey: 'detergents',
      variantLabel: 'Unit',
      showBags: false,
      showRemaining: false,
      creditNote: 'Optional customer credit is supported in the form only. Credit rules will come from Laravel.'
    }
  };

  function currentConfig() {
    return config[document.body.getAttribute('data-catalog')] || config.tea;
  }

  function mockRoot() {
    var key = currentConfig().mockKey;
    return (global.MBMS_MOCK && global.MBMS_MOCK[key]) || {};
  }

  function itemsFrom(data) {
    var cfg = currentConfig();
    if (data && data[cfg.collection]) {
      return data[cfg.collection];
    }
    return mockRoot()[cfg.collection] || [];
  }

  function sizesFrom(data) {
    var root = data || mockRoot();
    if (currentConfig().mockKey === 'detergents') {
      return [];
    }
    return root.sizes || [];
  }

  function unitsFor(product) {
    return (product && product.units) || [];
  }

  function variantsFor(itemId, data) {
    var variants = (data && data.variants) || mockRoot().variants || [];
    return variants.filter(function (row) {
      return row.type_id === itemId || row.product_id === itemId;
    });
  }

  function variantName(row, data) {
    if (row.label) {
      return row.label;
    }
    if (row.size_id) {
      var size = sizesFrom(data).filter(function (item) { return item.id === row.size_id; })[0];
      return size ? size.label : row.size_id;
    }
    if (row.unit_id) {
      return row.unit_id;
    }
    return row.label || 'Variant';
  }

  function findItem(id, data) {
    return itemsFrom(data).filter(function (item) { return item.id === id; })[0] || null;
  }

  function matchSearch(item, query) {
    if (!query) {
      return true;
    }
    return String(item.name || '').toLowerCase().indexOf(query) !== -1;
  }

  function renderList(data, source) {
    var cfg = currentConfig();
    var query = (document.getElementById('module-search') || {}).value || '';
    query = query.toLowerCase().trim();
    var filter = (document.getElementById('module-filter') || {}).value || '';
    var rows = itemsFrom(data).filter(function (item) {
      return matchSearch(item, query) && (!filter || item.id === filter);
    });

    global.MBMS_RECORDS.showBanner(document.getElementById('source-banner'), source);
    global.MBMS_RECORDS.fillSelect(
      document.getElementById('module-filter'),
      itemsFrom(data),
      function (item) { return item.id; },
      function (item) { return item.name; },
      filter,
      'All'
    );

    var body = document.getElementById('catalog-list-body');
    global.MBMS_RECORDS.renderTable(body, rows, [
      { key: 'name' },
      {
        render: function (item) {
          return variantsFor(item.id, data).map(function (row) {
            return '<span class="mbms-chip me-1">' + global.MBMS_UTILS.escapeHtml(variantName(row, data)) + '</span>';
          }).join('') || '<span class="text-secondary">No variants yet</span>';
        }
      },
      {
        render: function (item) {
          var stock = variantsFor(item.id, data).map(function (row) { return row.stock_display; }).filter(Boolean);
          return stock.length ? global.MBMS_UTILS.escapeHtml(stock.join(' · ')) : '—';
        }
      },
      {
        render: function (item) {
          return '<a class="btn btn-sm btn-outline-primary" href="?id=' + encodeURIComponent(item.id) + '">Open</a>';
        }
      }
    ], 'No ' + cfg.title.toLowerCase() + ' records match.');
  }

  function renderDetail(item, data, source) {
    var cfg = currentConfig();
    document.getElementById('detail-title').textContent = item.name;
    document.getElementById('detail-note').textContent = item.example || item.notes || cfg.itemLabel;
    global.MBMS_RECORDS.showBanner(document.getElementById('source-banner'), source);

    var variants = variantsFor(item.id, data);
    var stockHost = document.getElementById('variant-stock');
    stockHost.innerHTML = variants.length
      ? variants.map(function (row) {
          return (
            '<div class="col-6 col-lg-3">' +
            '<article class="mbms-card card mbms-stat-card"><div class="card-body">' +
            '<div class="mbms-stat-label">' + global.MBMS_UTILS.escapeHtml(variantName(row, data)) + '</div>' +
            '<div class="mbms-stat-value">' + global.MBMS_UTILS.escapeHtml(row.stock_display || '—') + '</div>' +
            '<span class="badge text-bg-secondary">Backend value</span>' +
            '</div></article></div>'
          );
        }).join('')
      : '<div class="col-12"><p class="text-secondary mb-0">No stock values yet.</p></div>';

    global.MBMS_RECORDS.renderTable(document.getElementById('variants-body'), variants, [
      { render: function (row) { return global.MBMS_UTILS.escapeHtml(variantName(row, data)); } },
      { key: 'stock_display' },
      { render: function () { return '<span class="badge text-bg-secondary">Sample / API</span>'; } }
    ], 'No variants yet. Future sizes or units will come from the backend.');

    var purchases = ((data && data.purchases) || mockRoot().purchases || []).filter(function (row) {
      return row.type_id === item.id || row.product_id === item.id;
    }).concat(global.MBMS_RECORDS.storeList(cfg.endpoint + '-purchases').filter(function (row) {
      return row.type_id === item.id || row.product_id === item.id;
    }));
    var sales = ((data && data.sales) || mockRoot().sales || []).filter(function (row) {
      return row.type_id === item.id || row.product_id === item.id;
    }).concat(global.MBMS_RECORDS.storeList(cfg.endpoint + '-sales').filter(function (row) {
      return row.type_id === item.id || row.product_id === item.id;
    }));

    global.MBMS_RECORDS.renderTable(document.getElementById('purchase-history-body'), purchases, [
      { key: 'date' }, { key: 'supplier' }, { render: function (row) { return global.MBMS_UTILS.escapeHtml(row.size_id || row.unit_id || ''); } }, { key: 'quantity' }, { key: 'total' }, { key: 'location' }
    ]);
    global.MBMS_RECORDS.renderTable(document.getElementById('sales-history-body'), sales, [
      { key: 'date' }, { key: 'customer' }, { render: function (row) { return global.MBMS_UTILS.escapeHtml(row.size_id || row.unit_id || ''); } }, { key: 'quantity' }, { key: 'total' }, { key: 'payment_type' }
    ]);

    var history = purchases.map(function (row) {
      return Object.assign({ history_type: 'Purchase' }, row);
    }).concat(sales.map(function (row) {
      return Object.assign({ history_type: 'Sale', supplier: row.customer }, row);
    }));
    global.MBMS_RECORDS.renderTable(document.getElementById('all-history-body'), history, [
      { key: 'date' }, { key: 'history_type' }, { render: function (row) { return global.MBMS_UTILS.escapeHtml(row.supplier || row.customer || ''); } }, { key: 'quantity' }, { key: 'total' }
    ]);

    setupForms(item, data);
  }

  function setupForms(item, data) {
    var cfg = currentConfig();
    document.getElementById('purchase-item-label').textContent = item.name;
    document.getElementById('sale-item-label').textContent = item.name;
    document.querySelectorAll('[data-bags-field]').forEach(function (el) {
      el.hidden = !cfg.showBags;
    });
    document.querySelectorAll('[data-remaining-field]').forEach(function (el) {
      el.hidden = !cfg.showRemaining;
    });
    document.getElementById('sale-credit-note').textContent = cfg.creditNote;

    var variantLabel = cfg.variantLabel;
    global.MBMS_UTILS.qsa('label[for="purchase-variant"], label[for="sale-variant"]').forEach(function (label) {
      label.textContent = variantLabel;
    });
    var variantOptions = variantsFor(item.id, data).map(function (row) {
      return { id: row.id, label: variantName(row, data) };
    });
    if (!variantOptions.length) {
      variantOptions = unitsFor(item);
    }
    global.MBMS_RECORDS.fillSelect(document.getElementById('purchase-variant'), variantOptions, function (row) { return row.id; }, function (row) { return row.label; });
    global.MBMS_RECORDS.fillSelect(document.getElementById('sale-variant'), variantOptions, function (row) { return row.id; }, function (row) { return row.label; });
    global.MBMS_RECORDS.fillSelect(document.getElementById('purchase-supplier'), global.MBMS_MOCK.suppliers || [], function (row) { return row.id; }, function (row) { return row.name; });
    global.MBMS_RECORDS.fillSelect(document.getElementById('sale-customer'), global.MBMS_MOCK.customers || [], function (row) { return row.id; }, function (row) { return row.name; });
    global.MBMS_RECORDS.fillWorkLocation(document.getElementById('purchase-location'));
    global.MBMS_RECORDS.fillWorkLocation(document.getElementById('sale-location'));
  }

  function bindForms() {
    var cfg = currentConfig();
    var purchaseForm = document.getElementById('purchase-form');
    var saleForm = document.getElementById('sale-form');

    if (purchaseForm) {
      global.MBMS_UI.bindForm(purchaseForm, {
        onSubmit: function (values) {
          values.id = 'local-' + Date.now();
          values.type_id = global.MBMS_RECORDS.queryParam('id');
          values.product_id = values.type_id;
          values.size_id = values.variant;
          values.unit_id = values.variant;
          return global.MBMS_RECORDS.submitOrMock('post', cfg.endpoint + '/purchases', values, function (body) {
            return global.MBMS_RECORDS.storeSave(cfg.endpoint + '-purchases', body);
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api' ? 'Purchase sent to the API.' : 'API not connected. Saved as a local sample record only.'
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('purchaseModal')).hide();
            purchaseForm.reset();
            load();
          });
        }
      });
    }

    if (saleForm) {
      global.MBMS_UI.bindForm(saleForm, {
        onSubmit: function (values) {
          values.id = 'local-' + Date.now();
          values.type_id = global.MBMS_RECORDS.queryParam('id');
          values.product_id = values.type_id;
          values.size_id = values.variant;
          values.unit_id = values.variant;
          return global.MBMS_RECORDS.submitOrMock('post', cfg.endpoint + '/sales', values, function (body) {
            return global.MBMS_RECORDS.storeSave(cfg.endpoint + '-sales', body);
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api' ? 'Sale sent to the API.' : 'API not connected. Saved as a local sample record only.'
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('saleModal')).hide();
            saleForm.reset();
            load();
          });
        }
      });
    }
  }

  function setMode(detail) {
    document.getElementById('list-view').hidden = !!detail;
    document.getElementById('detail-view').hidden = !detail;
  }

  function load() {
    var cfg = currentConfig();
    var root = document.getElementById('catalog-root');
    var id = global.MBMS_RECORDS.queryParam('id');
    setMode(!!id);
    if (!id) {
      var cached = global.MBMS_API && typeof global.MBMS_API.getCached === 'function'
        ? global.MBMS_API.getCached(cfg.endpoint)
        : null;
      renderList(cached || mockRoot(), cached ? 'api' : undefined);
    }
    return global.MBMS_RECORDS.fetchOrMock(cfg.endpoint, function () { return mockRoot(); })
      .then(function (result) {
        var data = result.data || mockRoot();
        if (id) {
          var item = findItem(id, data);
          if (!item) {
            setMode(false);
            global.MBMS_UI.notify({ type: 'danger', message: 'That record is not available.' });
            renderList(data, result.source);
            return;
          }
          setMode(true);
          renderDetail(item, data, result.source);
        } else {
          setMode(false);
          renderList(data, result.source);
        }
      })
      .catch(function (error) {
        global.MBMS_UI.renderError(root, {
          title: 'Unable to load',
          message: global.MBMS_UTILS.getErrorMessage(error),
          actionLabel: 'Try again',
          onAction: load
        });
      })
      ;
  }

  global.MBMS_PAGE = {
    active: document.body.getAttribute('data-page'),
    title: (currentConfig() && currentConfig().title) || 'Catalog',
    onReady: function () {
      var search = document.getElementById('module-search');
      var filter = document.getElementById('module-filter');
      if (search) {
        search.addEventListener('input', global.MBMS_UTILS.debounce(load, 250));
      }
      if (filter) {
        filter.addEventListener('change', load);
      }
      bindForms();
      load();
    }
  };
})(window);
