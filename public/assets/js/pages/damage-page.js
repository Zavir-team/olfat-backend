/**
 * Damaged stock UI. Backend removes stock. Frontend only submits the request.
 */
(function (global) {
  'use strict';

  function mockList() {
    return (global.MBMS_MOCK && global.MBMS_MOCK.damages) || [];
  }

  function allRows() {
    return mockList();
  }

  function render(source) {
    global.MBMS_RECORDS.showBanner(document.getElementById('source-banner'), source);
    var rows = allRows();
    if (!rows.length) {
      document.getElementById('dmg-table-wrap').hidden = true;
      document.getElementById('dmg-empty').hidden = false;
      global.MBMS_UI.renderEmpty(document.getElementById('dmg-empty'), {
        title: 'No damage records',
        message: 'Damaged stock you record will show up here.'
      });
      return;
    }
    document.getElementById('dmg-empty').hidden = true;
    document.getElementById('dmg-table-wrap').hidden = false;
    global.MBMS_RECORDS.renderTable(document.getElementById('dmg-body'), rows, [
      { key: 'date' }, { key: 'product' }, { key: 'variant' }, { key: 'quantity' }, { key: 'reason' }, { key: 'location' }
    ]);
  }

  function load() {
    render();
    return global.MBMS_RECORDS.fetchOrMock('damaged-stock', mockList)
      .then(function (result) {
        render(result.source);
      });
  }

  function fillProducts() {
    var category = document.getElementById('dmg-category').value;
    global.MBMS_RECORDS.fillSelect(
      document.getElementById('dmg-product'),
      global.MBMS_CATALOG.products(category),
      function (row) { return row.id; },
      function (row) { return row.name; }
    );
    fillVariants();
  }

  function fillVariants() {
    var category = document.getElementById('dmg-category').value;
    var productId = document.getElementById('dmg-product').value;
    global.MBMS_RECORDS.fillSelect(
      document.getElementById('dmg-variant'),
      global.MBMS_CATALOG.variants(category, productId),
      function (row) { return row.id; },
      function (row) { return row.label; }
    );
  }

  global.MBMS_PAGE = {
    active: 'damaged-stock',
    title: 'Damaged Stock',
    onReady: function () {
      global.MBMS_RECORDS.fillSelect(
        document.getElementById('dmg-category'),
        global.MBMS_CATALOG.purchaseCategories(),
        function (row) { return row.id; },
        function (row) { return row.label; }
      );
      global.MBMS_RECORDS.fillWorkLocation(document.getElementById('dmg-location'));
      document.getElementById('dmg-category').addEventListener('change', fillProducts);
      document.getElementById('dmg-product').addEventListener('change', fillVariants);
      fillProducts();
      global.MBMS_UI.bindReset(document.getElementById('dmg-form'), document.getElementById('dmg-reset'), fillProducts);

      var form = document.getElementById('dmg-form');
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (form.dataset.submitting === '1') {
          return;
        }
        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          return;
        }
        global.MBMS_UI.confirm({
          title: 'Record damage',
          message: 'This will request a damage record. Available stock will only change if Laravel accepts it.',
          confirmLabel: 'Submit damage'
        }).then(function (ok) {
          if (!ok) {
            return;
          }
          form.dataset.submitting = '1';
          var submitter = form.querySelector('[type="submit"]');
          global.MBMS_UI.setButtonLoading(submitter, true);
          var values = global.MBMS_UTILS.formToObject(form);
          var product = global.MBMS_CATALOG.products(values.category).filter(function (row) { return row.id === values.product; })[0];
          var variant = global.MBMS_CATALOG.variants(values.category, values.product).filter(function (row) { return row.id === values.variant; })[0];
          var record = {
            product: product ? product.name : values.product,
            variant: variant ? variant.label : values.variant,
            variant_id: values.variant,
            quantity: values.quantity,
            unit: values.unit,
            reason: values.reason,
            date: values.date,
            location: values.location,
            location_id: values.location
          };
          global.MBMS_RECORDS.submitOrMock('post', 'damaged-stock', record, function (body) {
            return global.MBMS_RECORDS.storeSave('damages', body);
          }).then(function (result) {
            global.MBMS_UI.notify({
              type: result.source === 'api' ? 'success' : 'warning',
              message: result.source === 'api'
                ? 'Damage sent. The backend will remove stock.'
                : 'API not connected. Sample damage only. Browser stock was not changed.'
            });
            form.reset();
            form.classList.remove('was-validated');
            fillProducts();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('dmgModal')).hide();
            load();
          }).catch(function (error) {
            global.MBMS_UI.notify({
              type: 'danger',
              title: 'Damage not saved',
              message: global.MBMS_UTILS.getErrorMessage(error)
            });
          }).finally(function () {
            form.dataset.submitting = '';
            global.MBMS_UI.setButtonLoading(submitter, false);
          });
        });
      });

      load();
    }
  };
})(window);
