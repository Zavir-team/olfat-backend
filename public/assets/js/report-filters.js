/**
 * Reusable report / P&L filter bar.
 * Collects filter values and fills lookups. Does not calculate results.
 */
(function (global) {
  'use strict';

  function products() {
    var catalog = global.MBMS_CATALOG;
    if (!catalog) {
      return [];
    }
    return ['tea', 'rice', 'detergents'].reduce(function (list, category) {
      return list.concat((catalog.products(category) || []).map(function (row) {
        return { id: row.id, label: row.name, category: category };
      }));
    }, []);
  }

  function fill(root) {
    if (!root || !global.MBMS_RECORDS) {
      return;
    }
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="location"]'),
      global.MBMS_RECORDS.locations(),
      function (row) { return row.id; },
      function (row) { return row.name; },
      '',
      'All locations'
    );
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="category"]'),
      global.MBMS_CATALOG ? global.MBMS_CATALOG.purchaseCategories() : [],
      function (row) { return row.id; },
      function (row) { return row.label; },
      '',
      'All categories'
    );
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="product"]'),
      products(),
      function (row) { return row.id; },
      function (row) { return row.label; },
      '',
      'All products'
    );
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="module"]'),
      [
        { id: 'tea', label: 'Tea' },
        { id: 'rice', label: 'Rice' },
        { id: 'detergents', label: 'Detergents' },
        { id: 'raw-materials', label: 'Raw Materials' },
        { id: 'factory-materials', label: 'Factory Materials' }
      ],
      function (row) { return row.id; },
      function (row) { return row.label; },
      '',
      'All modules'
    );
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="customer"]'),
      global.MBMS_CATALOG ? global.MBMS_CATALOG.customers() : [],
      function (row) { return row.id; },
      function (row) { return row.name; },
      '',
      'All customers'
    );
    global.MBMS_RECORDS.fillSelect(
      root.querySelector('[name="supplier"]'),
      global.MBMS_CATALOG ? global.MBMS_CATALOG.suppliers() : [],
      function (row) { return row.id; },
      function (row) { return row.name; },
      '',
      'All suppliers'
    );
  }

  function values(root) {
    var data = {};
    if (!root) {
      return data;
    }
    Array.prototype.forEach.call(root.querySelectorAll('[name]'), function (el) {
      data[el.name] = el.value || '';
    });
    return data;
  }

  function params(root) {
    var raw = values(root);
    return {
      search: raw.search || '',
      date_from: raw.date_from || '',
      date_to: raw.date_to || '',
      category: raw.category || '',
      product_id: raw.product || '',
      customer_id: raw.customer || '',
      supplier_id: raw.supplier || '',
      location_id: raw.location || '',
      module: raw.module || '',
      transaction_type: raw.transaction_type || ''
    };
  }

  function show(root, names) {
    if (!root) {
      return;
    }
    Array.prototype.forEach.call(root.querySelectorAll('[name]'), function (el) {
      var visible = !names || names.indexOf(el.name) !== -1;
      el.hidden = !visible;
      if (el.parentNode && el.parentNode !== root && el.parentNode.children.length === 1) {
        el.parentNode.hidden = !visible;
      }
    });
  }

  function locationLabel(id) {
    var match = (global.MBMS_RECORDS.locations() || []).filter(function (row) {
      return row.id === id || row.name === id;
    })[0];
    return match ? match.name : id;
  }

  function personLabel(kind, id) {
    var list = global.MBMS_CATALOG && kind === 'supplier'
      ? global.MBMS_CATALOG.suppliers()
      : (global.MBMS_CATALOG ? global.MBMS_CATALOG.customers() : []);
    var match = (list || []).filter(function (row) {
      return row.id === id || row.name === id;
    })[0];
    return match ? match.name : id;
  }

  function bind(root, onChange) {
    if (!root) {
      return;
    }
    fill(root);
    Array.prototype.forEach.call(root.querySelectorAll('input, select'), function (el) {
      el.addEventListener('change', function () {
        if (typeof onChange === 'function') {
          onChange(values(root));
        }
      });
      if (el.type === 'search' || el.type === 'text') {
        el.addEventListener('input', global.MBMS_UTILS.debounce(function () {
          if (typeof onChange === 'function') {
            onChange(values(root));
          }
        }, 250));
      }
    });
  }

  global.MBMS_FILTERS = {
    fill: fill,
    values: values,
    params: params,
    show: show,
    bind: bind,
    products: products,
    locationLabel: locationLabel,
    personLabel: personLabel
  };
})(window);
