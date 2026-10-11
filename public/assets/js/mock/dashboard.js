/**
 * Clearly separated sample dashboard values.
 * These are placeholders for UI layout only.
 * They are not official sales, stock, debt, or profit figures.
 */
(function (global) {
  'use strict';

  global.MBMS_MOCK = global.MBMS_MOCK || {};
  global.MBMS_MOCK.dashboard = {
    source: 'mock',
    notice: 'Sample data — not official. These cards are layout placeholders until Laravel is connected.',
    widgets: {
      todaySales: { label: "Today's Sales", display: '12,450', hint: 'Placeholder' },
      todayPurchases: { label: "Today's Purchases", display: '8,200', hint: 'Placeholder' },
      currentStock: { label: 'Current Stock', display: '1,184', hint: 'Placeholder' },
      customerReceivables: { label: 'Customer Receivables', display: '21,300', hint: 'Placeholder' },
      supplierPayables: { label: 'Supplier Payables', display: '14,750', hint: 'Placeholder' },
      profitLoss: { label: 'Profit/Loss', display: '3,180', hint: 'Placeholder' },
      workerPayments: { label: 'Worker Payments', display: '2,400', hint: 'Placeholder' }
    },
    recentTransactions: [
      { when: '2026-09-24 09:15', type: 'Sale', detail: 'Sample customer A', amount: '1,250' },
      { when: '2026-09-24 08:40', type: 'Purchase', detail: 'Sample supplier B', amount: '2,100' },
      { when: '2026-09-23 17:05', type: 'Payment', detail: 'Sample payment', amount: '500' }
    ],
    stockWarnings: [
      { item: 'Sample tea item', message: 'Low stock placeholder' },
      { item: 'Sample rice item', message: 'Reorder placeholder' }
    ]
  };
})(window);
