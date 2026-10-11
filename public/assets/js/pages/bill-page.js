/**
 * Professional sale bill: print/PDF + WhatsApp share.
 */
(function (global) {
  'use strict';

  var currentSale = null;
  var billSettings = {
    businessName: 'Olfat Business Management',
    phone: '',
    address: '',
    footerNote: 'Thank you for your business. Please keep this bill for your records.',
    currency: 'AFN'
  };

  function saleIdFromQuery() {
    try {
      return new URLSearchParams(global.location.search).get('id') || '';
    } catch (err) {
      return '';
    }
  }

  function money(value) {
    var n = Number(value);
    if (value == null || value === '' || isNaN(n)) {
      if (typeof value === 'string' && value.trim() !== '') {
        return value;
      }
      return '0.00';
    }
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function withCurrency(value) {
    return money(value) + ' ' + billSettings.currency;
  }

  function showError(message) {
    var box = document.getElementById('bill-error');
    var root = document.getElementById('bill-root');
    if (root) {
      root.hidden = true;
    }
    if (box) {
      box.hidden = !message;
      box.textContent = message || '';
    }
  }

  function paymentStatus(sale) {
    var remaining = Number(sale.remaining_raw || 0);
    var paid = Number(sale.paid_raw || 0);
    if (remaining <= 0.0001) {
      return { label: 'Paid', className: 'is-paid' };
    }
    if (paid <= 0.0001) {
      return { label: 'Credit', className: 'is-credit' };
    }
    return { label: 'Partial', className: 'is-mixed' };
  }

  function loadSettings() {
    if (!global.MBMS_API || typeof global.MBMS_API.get !== 'function') {
      return Promise.resolve();
    }
    return global.MBMS_API.get('settings').then(function (response) {
      var rows = global.MBMS_UTILS.unwrap(response.data) || response.data || [];
      if (!Array.isArray(rows) && rows && Array.isArray(rows.data)) {
        rows = rows.data;
      }
      if (!Array.isArray(rows)) {
        return;
      }
      var map = {};
      rows.forEach(function (row) {
        if (row && row.key != null) {
          map[row.key] = row.value;
        }
      });
      billSettings.businessName = map['app.name'] || billSettings.businessName;
      billSettings.currency = map['app.currency_code'] || billSettings.currency;
      billSettings.phone = map['bill.phone'] || '';
      billSettings.address = map['bill.address'] || '';
      billSettings.footerNote = map['bill.footer_note'] || billSettings.footerNote;
    }).catch(function () {
      /* bill still works with defaults */
    });
  }

  function renderSale(sale) {
    currentSale = sale;
    var root = document.getElementById('bill-root');
    var error = document.getElementById('bill-error');
    if (error) {
      error.hidden = true;
    }
    if (root) {
      root.hidden = false;
    }

    var status = paymentStatus(sale);
    document.title = (sale.reference_no || 'Sale') + ' · Bill';
    document.getElementById('bill-toolbar-title').textContent = sale.reference_no || 'Sale bill';
    document.getElementById('bill-business-name').textContent = billSettings.businessName;
    document.getElementById('bill-business-tag').textContent = 'Sales Invoice · ' + (sale.location || 'Province');

    var contactParts = [];
    if (billSettings.phone) {
      contactParts.push(billSettings.phone);
    }
    if (billSettings.address) {
      contactParts.push(billSettings.address);
    }
    document.getElementById('bill-business-contact').textContent = contactParts.join(' · ');

    document.getElementById('bill-number').textContent = sale.reference_no || ('#' + sale.id);
    document.getElementById('bill-date').textContent = sale.date || '—';
    document.getElementById('bill-province').textContent = sale.location || '—';

    var statusEl = document.getElementById('bill-status');
    statusEl.textContent = status.label;
    statusEl.className = 'bill-status ' + status.className;

    document.getElementById('bill-customer-name').textContent = sale.customer || 'Walk-in customer';
    document.getElementById('bill-customer-phone').textContent = sale.customer_phone
      ? ('Phone: ' + sale.customer_phone)
      : '';
    document.getElementById('bill-customer-address').textContent = sale.customer_address
      ? ('Address: ' + sale.customer_address)
      : '';

    document.getElementById('bill-seller-name').textContent = sale.seller || 'Staff';
    document.getElementById('bill-seller-province').textContent = sale.location
      ? ('Province: ' + sale.location)
      : '';
    document.getElementById('bill-payment-type').textContent = sale.payment_type
      ? ('Payment: ' + sale.payment_type)
      : '';

    var body = document.getElementById('bill-items-body');
    var items = sale.items || [];
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="5" class="text-secondary">No line items on this sale.</td></tr>';
    } else {
      body.innerHTML = items.map(function (line, index) {
        var label = [line.product, line.variant].filter(Boolean).join(' · ') || 'Item';
        var qty = line.quantity + (line.unit ? (' ' + line.unit) : '');
        return (
          '<tr>' +
          '<td>' + (index + 1) + '</td>' +
          '<td>' + global.MBMS_UTILS.escapeHtml(label) + '</td>' +
          '<td class="num">' + global.MBMS_UTILS.escapeHtml(qty) + '</td>' +
          '<td class="num">' + global.MBMS_UTILS.escapeHtml(withCurrency(line.unit_price_raw != null ? line.unit_price_raw : line.unit_price)) + '</td>' +
          '<td class="num">' + global.MBMS_UTILS.escapeHtml(withCurrency(line.line_total_raw != null ? line.line_total_raw : line.line_total)) + '</td>' +
          '</tr>'
        );
      }).join('');
    }

    document.getElementById('bill-subtotal').textContent = withCurrency(sale.total_raw != null ? sale.total_raw : sale.total);
    document.getElementById('bill-paid').textContent = withCurrency(sale.paid_raw != null ? sale.paid_raw : sale.paid);
    document.getElementById('bill-remaining').textContent = withCurrency(sale.remaining_raw != null ? sale.remaining_raw : sale.remaining);
    document.getElementById('bill-total').textContent = withCurrency(sale.total_raw != null ? sale.total_raw : sale.total);

    var notesWrap = document.getElementById('bill-notes-wrap');
    if (sale.notes) {
      notesWrap.hidden = false;
      document.getElementById('bill-notes').textContent = sale.notes;
    } else {
      notesWrap.hidden = true;
    }

    document.getElementById('bill-footer-note').textContent = billSettings.footerNote;
    document.getElementById('bill-footer-meta').textContent =
      billSettings.businessName +
      (sale.location ? (' · ' + sale.location) : '') +
      ' · Generated by MBMS';
  }

  function whatsAppText(sale) {
    var lines = [];
    lines.push('*' + billSettings.businessName + '*');
    lines.push('Sale Bill: ' + (sale.reference_no || sale.id));
    lines.push('Date: ' + (sale.date || ''));
    lines.push('Province: ' + (sale.location || ''));
    lines.push('Customer: ' + (sale.customer || ''));
    lines.push('');
    (sale.items || []).forEach(function (line, index) {
      var label = [line.product, line.variant].filter(Boolean).join(' · ') || 'Item';
      lines.push((index + 1) + '. ' + label + ' — ' + line.quantity + (line.unit ? (' ' + line.unit) : '') + ' × ' + withCurrency(line.unit_price_raw != null ? line.unit_price_raw : line.unit_price));
    });
    lines.push('');
    lines.push('Total: ' + withCurrency(sale.total_raw != null ? sale.total_raw : sale.total));
    lines.push('Paid: ' + withCurrency(sale.paid_raw != null ? sale.paid_raw : sale.paid));
    lines.push('Remaining: ' + withCurrency(sale.remaining_raw != null ? sale.remaining_raw : sale.remaining));
    if (sale.customer_phone) {
      lines.push('');
      lines.push('Customer phone: ' + sale.customer_phone);
    }
    return lines.join('\n');
  }

  function openWhatsApp() {
    if (!currentSale) {
      return;
    }
    var text = encodeURIComponent(whatsAppText(currentSale));
    var phone = String(currentSale.customer_phone || '').replace(/[^\d]/g, '');
    var url = phone
      ? ('https://wa.me/' + phone + '?text=' + text)
      : ('https://wa.me/?text=' + text);
    global.open(url, '_blank', 'noopener');
  }

  function loadBill() {
    var id = saleIdFromQuery();
    if (!id) {
      showError('Missing sale id. Open a bill from the Sales page.');
      return Promise.resolve();
    }
    if (!global.MBMS_CONFIG || !global.MBMS_CONFIG.getAuthToken || !global.MBMS_CONFIG.getAuthToken()) {
      global.location.href = (global.MBMS_NAV && global.MBMS_NAV.asset)
        ? global.MBMS_NAV.asset('pages/login.html')
        : 'login.html';
      return Promise.resolve();
    }

    return loadSettings().then(function () {
      return global.MBMS_API.get('sales/' + id);
    }).then(function (response) {
      var sale = global.MBMS_UTILS.unwrap(response.data) || response.data || {};
      if (!sale.id) {
        throw { message: 'Sale bill was not found.' };
      }
      renderSale(sale);
    }).catch(function (error) {
      showError(global.MBMS_UTILS.getErrorMessage(error, 'Unable to load this sale bill.'));
    });
  }

  global.MBMS_PAGE = {
    title: 'Sale Bill',
    prefetchLocations: false,
    onReady: function () {
      var printBtn = document.getElementById('bill-print');
      var waBtn = document.getElementById('bill-whatsapp');
      if (printBtn) {
        printBtn.addEventListener('click', function () {
          global.print();
        });
      }
      if (waBtn) {
        waBtn.addEventListener('click', openWhatsApp);
      }
      loadBill();
    }
  };
})(window);
