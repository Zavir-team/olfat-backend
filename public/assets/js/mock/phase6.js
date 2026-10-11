/**
 * Phase 6 sample profit/loss and report rows.
 * Display only. These are not official calculations.
 */
(function (global) {
  'use strict';

  global.MBMS_MOCK = global.MBMS_MOCK || {};

  global.MBMS_MOCK.profitLoss = {
    notice: 'Sample P&L display values. Official revenue, cost, and profit come from Laravel. Costing method is not chosen here.',
    summary: {
      revenue: { label: 'Revenue', display: '18,240' },
      cost: { label: 'Cost', display: '12,100' },
      gross_profit: { label: 'Gross Profit', display: '6,140' },
      operating_expenses: { label: 'Operating Expenses', display: '2,960' },
      net_profit: { label: 'Net Profit/Loss', display: '3,180' }
    },
    rows: [
      { module: 'Tea', product: 'Green Tea', location: 'Kabul', revenue: '640', cost: '—', result: '—' },
      { module: 'Rice', product: 'Sample quality A', location: 'Ghazni', revenue: '440', cost: '—', result: '—' },
      { module: 'Detergents', product: 'Vaseline', location: 'Kabul', revenue: '36', cost: '—', result: '—' }
    ]
  };

  global.MBMS_MOCK.reports = {
    sales: {
      daily: [{ date: '2026-09-22', product: 'Green Tea', customer: 'Sample customer A', amount: '640', location: 'Kabul' }],
      weekly: [{ period: '2026-W38', amount: '1,116', location: 'All' }],
      monthly: [{ period: '2026-09', amount: '1,116', location: 'All' }],
      yearly: [{ period: '2026', amount: '1,116', location: 'All' }]
    },
    purchases: {
      daily: [{ date: '2026-09-20', product: 'Green Tea', supplier: 'Sample supplier A', amount: '5,000', location: 'Kabul' }],
      weekly: [{ period: '2026-W38', amount: '5,400', location: 'All' }],
      monthly: [{ period: '2026-09', amount: '5,400', location: 'All' }],
      yearly: [{ period: '2026', amount: '5,400', location: 'All' }]
    },
    stock: {
      current: [{ category: 'tea', product: 'Green Tea', variant: '20kg', stock_display: '36 bags', location: 'Kabul' }],
      movements: [{ date: '2026-09-22', type: 'Sale', product: 'Green Tea', quantity: '2 bags', location: 'Kabul' }],
      damaged: [{ date: '2026-09-10', product: 'Soap', quantity: '1 carton', location: 'Maidan/Wardak' }],
      returned: [{ date: '2026-09-15', product: 'Vaseline', quantity: '1 package', location: 'Kabul' }]
    },
    financial: {
      'customer-debts': [{ person: 'Sample customer A', remaining_display: '1,250', location: 'Kabul' }],
      'supplier-debts': [{ person: 'Sample supplier A', remaining_display: '2,000', location: 'Kabul' }],
      'external-debts': [{ person: 'Ahmad', direction: 'Business owes this person', remaining_display: '400', location: 'Kabul' }],
      payments: [{ date: '2026-09-22', person: 'Sample customer A', amount: '640', location: 'Kabul' }]
    },
    workers: {
      payments: [{ date: '2026-09-20', worker: 'Sample worker A', amount: '180', location: 'Kabul' }],
      monthly: [{ month: '2026-09', worker: 'Sample worker A', monthly_total_display: '380' }]
    },
    factory: {
      'raw-materials': [{ product: 'Sample raw material A', stock_display: '8 bags · 170kg', location: 'Kabul' }],
      'factory-materials': [{ product: 'Cartons', note: 'Stock tracking not finalized', location: 'Kabul' }]
    },
    location: {
      kabul: [{ date: '2026-09-22', type: 'Sale', product: 'Green Tea', amount: '640', location: 'Kabul' }],
      'maidan-wardak': [{ date: '2026-09-10', type: 'Damage', product: 'Soap', amount: '—', location: 'Maidan/Wardak' }],
      ghazni: [{ date: '2026-09-21', type: 'Sale', product: 'Sample quality A', amount: '440', location: 'Ghazni' }]
    }
  };

  global.MBMS_MOCK.profile = {
    name: 'Signed-in user',
    email: 'user@example.com',
    location: 'Kabul'
  };
})(window);
