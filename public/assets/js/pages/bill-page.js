/**
 * Professional A4 sale bill:
 *   • pulls the full business + invoice branding from GET /api/v1/settings
 *   • renders the sale with print-ready A4 layout
 *   • prints via browser (Save as PDF) and shares via WhatsApp
 *
 * Setting keys read (all optional, defaults fall back cleanly):
 *   business.legal_name, business.tagline, business.logo_url,
 *   business.address_line1, business.address_line2, business.phone,
 *   business.email, business.website, business.tax_id,
 *   invoice.prefix, invoice.header_note, invoice.footer_note,
 *   invoice.terms, invoice.bank_details,
 *   invoice.signature_customer_label, invoice.signature_authorized_label,
 *   invoice.show_watermark,
 *   app.name, app.currency_code, app.currency_symbol, app.currency_position
 *
 * Legacy fallbacks: bill.phone, bill.address, bill.footer_note.
 */
(function (global) {
  'use strict';

  var currentSale = null;

  var billSettings = {
    businessName: 'Business Name',
    tagline: 'Sales Invoice',
    logoUrl: '',
    addressLine1: '',
    addressLine2: '',
    phone: '',
    email: '',
    website: '',
    taxId: '',

    invoicePrefix: 'INV',
    headerNote: '',
    footerNote: 'Thank you for your business. Please keep this bill for your records.',
    terms: '',
    bankDetails: '',
    signatureCustomer: 'Customer signature',
    signatureAuthorized: 'Authorized signature',
    showWatermark: true,

    currencyCode: 'AFN',
    currencySymbol: '',
    currencyPosition: 'suffix' // 'prefix' | 'suffix'
  };

  // ------------------------------------------------------------------
  // Utilities
  // ------------------------------------------------------------------

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
    return n.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function withCurrency(value) {
    var amount = money(value);
    var token = billSettings.currencySymbol || billSettings.currencyCode || '';
    if (!token) {
      return amount;
    }
    return billSettings.currencyPosition === 'prefix'
      ? (token + ' ' + amount)
      : (amount + ' ' + token);
  }

  function textOrFallback(primary, fallback) {
    var value = (primary == null ? '' : String(primary)).trim();
    return value || fallback || '';
  }

  function truthy(value) {
    if (value === true || value === 1 || value === '1') {
      return true;
    }
    if (typeof value === 'string') {
      var lower = value.toLowerCase();
      return lower === 'true' || lower === 'yes' || lower === 'on';
    }
    return false;
  }

  function toggle(el, show) {
    if (el) {
      el.hidden = !show;
    }
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) {
      el.textContent = value == null ? '' : String(value);
    }
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

  // ------------------------------------------------------------------
  // Settings loader (reads bulk /settings)
  // ------------------------------------------------------------------

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

      billSettings.businessName = textOrFallback(
        map['business.legal_name'],
        textOrFallback(map['app.name'], billSettings.businessName)
      );
      billSettings.tagline = textOrFallback(map['business.tagline'], billSettings.tagline);
      billSettings.logoUrl = textOrFallback(map['business.logo_url'], '');
      billSettings.addressLine1 = textOrFallback(
        map['business.address_line1'],
        textOrFallback(map['bill.address'], '')
      );
      billSettings.addressLine2 = textOrFallback(map['business.address_line2'], '');
      billSettings.phone = textOrFallback(
        map['business.phone'],
        textOrFallback(map['bill.phone'], '')
      );
      billSettings.email = textOrFallback(map['business.email'], '');
      billSettings.website = textOrFallback(map['business.website'], '');
      billSettings.taxId = textOrFallback(map['business.tax_id'], '');

      billSettings.invoicePrefix = textOrFallback(map['invoice.prefix'], billSettings.invoicePrefix);
      billSettings.headerNote = textOrFallback(map['invoice.header_note'], '');
      billSettings.footerNote = textOrFallback(
        map['invoice.footer_note'],
        textOrFallback(map['bill.footer_note'], billSettings.footerNote)
      );
      billSettings.terms = textOrFallback(map['invoice.terms'], '');
      billSettings.bankDetails = textOrFallback(map['invoice.bank_details'], '');
      billSettings.signatureCustomer = textOrFallback(
        map['invoice.signature_customer_label'],
        billSettings.signatureCustomer
      );
      billSettings.signatureAuthorized = textOrFallback(
        map['invoice.signature_authorized_label'],
        billSettings.signatureAuthorized
      );
      if (map['invoice.show_watermark'] != null) {
        billSettings.showWatermark = truthy(map['invoice.show_watermark']);
      }

      billSettings.currencyCode = textOrFallback(map['app.currency_code'], billSettings.currencyCode);
      billSettings.currencySymbol = textOrFallback(map['app.currency_symbol'], billSettings.currencySymbol);
      billSettings.currencyPosition = textOrFallback(map['app.currency_position'], billSettings.currencyPosition);
    }).catch(function () {
      /* Bill still renders with defaults when settings are unavailable. */
    });
  }

  // ------------------------------------------------------------------
  // Rendering
  // ------------------------------------------------------------------

  function paymentStatus(sale) {
    var remaining = Number(sale.remaining_raw != null ? sale.remaining_raw : sale.remaining || 0);
    var paid = Number(sale.paid_raw != null ? sale.paid_raw : sale.paid || 0);
    if (remaining <= 0.0001) {
      return { label: 'Paid', className: 'is-paid', watermark: 'PAID' };
    }
    if (paid <= 0.0001) {
      return { label: 'Credit', className: 'is-credit', watermark: 'UNPAID' };
    }
    return { label: 'Partial', className: 'is-mixed', watermark: 'PARTIAL' };
  }

  function renderHeader(sale, status) {
    // Logo
    var logo = document.getElementById('bill-logo');
    if (logo) {
      if (billSettings.logoUrl) {
        logo.src = billSettings.logoUrl;
        logo.hidden = false;
      } else {
        logo.hidden = true;
      }
    }

    setText('bill-business-name', billSettings.businessName);
    setText('bill-business-tag', billSettings.tagline);

    // Address lines joined with newline so pre-line CSS keeps them on new lines
    var addressLines = [billSettings.addressLine1, billSettings.addressLine2]
      .filter(function (line) { return !!line; });
    setText('bill-business-address', addressLines.join('\n'));

    // Phone · email · website
    var contactBits = [];
    if (billSettings.phone) contactBits.push(billSettings.phone);
    if (billSettings.email) contactBits.push(billSettings.email);
    if (billSettings.website) contactBits.push(billSettings.website);
    setText('bill-business-contact', contactBits.join(' · '));

    // Tax / registration number
    var taxEl = document.getElementById('bill-business-tax');
    if (taxEl) {
      if (billSettings.taxId) {
        taxEl.textContent = 'Tax ID: ' + billSettings.taxId;
        taxEl.hidden = false;
      } else {
        taxEl.hidden = true;
      }
    }

    // Meta box
    var invoiceNumber = sale.reference_no
      ? (billSettings.invoicePrefix ? billSettings.invoicePrefix + '-' + sale.reference_no : sale.reference_no)
      : ('#' + sale.id);
    setText('bill-number', invoiceNumber);
    setText('bill-date', sale.date || '—');
    setText('bill-province', sale.location || '—');

    var statusEl = document.getElementById('bill-status');
    if (statusEl) {
      statusEl.textContent = status.label;
      statusEl.className = 'bill-status ' + status.className;
    }

    var headerNote = document.getElementById('bill-header-note');
    if (headerNote) {
      if (billSettings.headerNote) {
        headerNote.textContent = billSettings.headerNote;
        headerNote.hidden = false;
      } else {
        headerNote.hidden = true;
      }
    }
  }

  function renderParties(sale) {
    setText('bill-customer-name', sale.customer || 'Walk-in customer');
    setText(
      'bill-customer-phone',
      sale.customer_phone ? ('Phone: ' + sale.customer_phone) : ''
    );
    setText(
      'bill-customer-address',
      sale.customer_address ? ('Address: ' + sale.customer_address) : ''
    );

    setText('bill-seller-name', sale.seller || 'Staff');
    setText(
      'bill-seller-province',
      sale.location ? ('Province: ' + sale.location) : ''
    );
    setText(
      'bill-payment-type',
      sale.payment_type ? ('Payment: ' + sale.payment_type) : ''
    );
  }

  function renderItems(sale) {
    var body = document.getElementById('bill-items-body');
    if (!body) {
      return;
    }
    var items = sale.items || [];
    if (!items.length) {
      body.innerHTML = '<tr><td colspan="5" class="text-secondary">No line items on this sale.</td></tr>';
      return;
    }

    body.innerHTML = items.map(function (line, index) {
      var label = [line.product, line.variant].filter(Boolean).join(' · ') || 'Item';
      var qtyRaw = line.quantity_raw != null ? line.quantity_raw : line.quantity;
      var qty = (qtyRaw == null ? '' : qtyRaw) + (line.unit ? (' ' + line.unit) : '');
      var unitPrice = line.unit_price_raw != null ? line.unit_price_raw : line.unit_price;
      var lineTotal = line.line_total_raw != null ? line.line_total_raw : line.line_total;
      return (
        '<tr>' +
        '<td class="bill-col-num">' + (index + 1) + '</td>' +
        '<td class="bill-col-item">' + global.MBMS_UTILS.escapeHtml(label) + '</td>' +
        '<td class="bill-col-qty num">' + global.MBMS_UTILS.escapeHtml(String(qty)) + '</td>' +
        '<td class="bill-col-price num">' + global.MBMS_UTILS.escapeHtml(withCurrency(unitPrice)) + '</td>' +
        '<td class="bill-col-total num">' + global.MBMS_UTILS.escapeHtml(withCurrency(lineTotal)) + '</td>' +
        '</tr>'
      );
    }).join('');
  }

  function renderTotals(sale) {
    var total = sale.total_raw != null ? sale.total_raw : sale.total;
    var paid = sale.paid_raw != null ? sale.paid_raw : sale.paid;
    var remaining = sale.remaining_raw != null ? sale.remaining_raw : sale.remaining;
    setText('bill-subtotal', withCurrency(total));
    setText('bill-paid', withCurrency(paid));
    setText('bill-remaining', withCurrency(remaining));
    setText('bill-total', withCurrency(total));
  }

  function renderNotes(sale) {
    var notesWrap = document.getElementById('bill-notes-wrap');
    if (!notesWrap) {
      return;
    }
    if (sale.notes) {
      notesWrap.hidden = false;
      setText('bill-notes', sale.notes);
    } else {
      notesWrap.hidden = true;
    }
  }

  function renderSignatures() {
    setText('bill-sign-customer', billSettings.signatureCustomer);
    setText('bill-sign-authorized', billSettings.signatureAuthorized);
  }

  function renderFooter(sale) {
    var termsWrap = document.getElementById('bill-footer-terms-wrap');
    if (termsWrap) {
      if (billSettings.terms) {
        termsWrap.hidden = false;
        setText('bill-footer-terms', billSettings.terms);
      } else {
        termsWrap.hidden = true;
      }
    }

    var bankWrap = document.getElementById('bill-footer-bank-wrap');
    if (bankWrap) {
      if (billSettings.bankDetails) {
        bankWrap.hidden = false;
        setText('bill-footer-bank', billSettings.bankDetails);
      } else {
        bankWrap.hidden = true;
      }
    }

    setText('bill-footer-note', billSettings.footerNote);

    var metaBits = [billSettings.businessName];
    if (sale.location) metaBits.push(sale.location);
    metaBits.push('Generated by MBMS');
    setText('bill-footer-meta', metaBits.join(' · '));
  }

  function renderWatermark(status) {
    var watermark = document.getElementById('bill-watermark');
    if (!watermark) {
      return;
    }
    if (!billSettings.showWatermark || !status.watermark) {
      watermark.hidden = true;
      return;
    }
    watermark.hidden = false;
    watermark.className = 'bill-watermark ' + status.className;
    watermark.setAttribute('data-text', status.watermark);
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
    setText('bill-toolbar-title', sale.reference_no || 'Sale bill');

    renderHeader(sale, status);
    renderParties(sale);
    renderItems(sale);
    renderTotals(sale);
    renderNotes(sale);
    renderSignatures();
    renderFooter(sale);
    renderWatermark(status);
  }

  // ------------------------------------------------------------------
  // WhatsApp share
  // ------------------------------------------------------------------

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
      var qtyRaw = line.quantity_raw != null ? line.quantity_raw : line.quantity;
      var unitPrice = line.unit_price_raw != null ? line.unit_price_raw : line.unit_price;
      lines.push(
        (index + 1) + '. ' + label + ' — ' + qtyRaw + (line.unit ? (' ' + line.unit) : '') +
        ' × ' + withCurrency(unitPrice)
      );
    });
    lines.push('');
    var total = sale.total_raw != null ? sale.total_raw : sale.total;
    var paid = sale.paid_raw != null ? sale.paid_raw : sale.paid;
    var remaining = sale.remaining_raw != null ? sale.remaining_raw : sale.remaining;
    lines.push('Total: ' + withCurrency(total));
    lines.push('Paid: ' + withCurrency(paid));
    lines.push('Remaining: ' + withCurrency(remaining));
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

  // ------------------------------------------------------------------
  // Bootstrap
  // ------------------------------------------------------------------

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
