/**
 * Phase 4 sample transaction records. Display only.
 * The frontend does not treat these as official stock or debt results.
 */
(function (global) {
  'use strict';

  global.MBMS_MOCK = global.MBMS_MOCK || {};

  global.MBMS_MOCK.rawMaterials = [
    { id: 'rm-1', name: 'Sample raw material A', units: [{ id: '170kg', label: '170kg bag' }, { id: '220kg', label: '220kg bag' }] },
    { id: 'rm-2', name: 'Sample raw material B', units: [{ id: '170kg', label: '170kg bag' }, { id: '220kg', label: '220kg bag' }] }
  ];

  global.MBMS_MOCK.factoryMaterials = [
    { id: 'fm-cartons', name: 'Cartons', units: [{ id: 'piece', label: 'Piece' }, { id: 'other', label: 'Other configured unit' }] },
    { id: 'fm-labels', name: 'Labels', units: [{ id: 'piece', label: 'Piece' }, { id: 'roll', label: 'Roll' }] },
    { id: 'fm-tape', name: 'Tape', units: [{ id: 'roll', label: 'Roll' }, { id: 'other', label: 'Other configured unit' }] }
  ];

  global.MBMS_MOCK.sales = [
    { id: 'sale-1', category: 'tea', product: 'Green Tea', variant: '20kg', customer: 'Sample customer A', quantity: '40kg', price: '16.00', total: '640', paid: '640', remaining: '0', payment_type: 'cash', date: '2026-09-22', location: 'Kabul', notes: 'Sample sale' },
    { id: 'sale-2', category: 'rice', product: 'Sample quality A', variant: '20kg', customer: 'Sample customer B', quantity: '20kg', price: '22.00', total: '440', paid: '440', remaining: '0', payment_type: 'cash', date: '2026-09-21', location: 'Ghazni', notes: '' },
    { id: 'sale-3', category: 'detergents', product: 'Vaseline', variant: 'Package', customer: 'Sample customer A', quantity: '2', price: '18.00', total: '36', paid: '0', remaining: '36', payment_type: 'credit', date: '2026-09-19', location: 'Kabul', notes: '' }
  ];

  global.MBMS_MOCK.purchases = [
    { id: 'pur-1', category: 'tea', product: 'Green Tea', variant: '20kg', supplier: 'Sample supplier A', quantity: '400kg', price: '12.50', total: '5,000', paid: '3,000', remaining: '2,000', date: '2026-09-20', location: 'Kabul' },
    { id: 'pur-2', category: 'raw-materials', product: 'Sample raw material A', variant: '170kg bag', supplier: 'Sample supplier A', quantity: '4 bags', price: '80.00', total: '320', paid: '320', remaining: '0', date: '2026-09-14', location: 'Kabul' },
    { id: 'pur-3', category: 'factory-materials', product: 'Cartons', variant: 'Piece', supplier: 'Sample supplier A', quantity: '200', price: '0.40', total: '80', paid: '80', remaining: '0', date: '2026-09-11', location: 'Kabul' }
  ];

  global.MBMS_MOCK.customerPayments = [
    { id: 'pay-c-1', person: 'Sample customer A', amount: '640', date: '2026-09-22', description: 'Sale payment', user: 'Signed-in user', location: 'Kabul' }
  ];

  global.MBMS_MOCK.supplierPayments = [
    { id: 'pay-s-1', person: 'Sample supplier A', amount: '3,000', date: '2026-09-20', description: 'Purchase payment', user: 'Signed-in user', location: 'Kabul' }
  ];

  global.MBMS_MOCK.stock = [
    { id: 'st-1', category: 'tea', product: 'Green Tea', variant: '20kg', stock_display: '36 bags', location: 'Kabul' },
    { id: 'st-2', category: 'rice', product: 'Sample quality A', variant: '20kg', stock_display: '18 bags', location: 'Ghazni' },
    { id: 'st-3', category: 'detergents', product: 'Soap', variant: 'Carton', stock_display: '16 cartons', location: 'Kabul' }
  ];

  global.MBMS_MOCK.stockMovements = [
    { id: 'mv-1', date: '2026-09-20', type: 'Purchase', product: 'Green Tea', variant: '20kg', quantity: '20 bags', location: 'Kabul', reference: 'pur-1' },
    { id: 'mv-2', date: '2026-09-22', type: 'Sale', product: 'Green Tea', variant: '20kg', quantity: '2 bags', location: 'Kabul', reference: 'sale-1' },
    { id: 'mv-3', date: '2026-09-15', type: 'Return', product: 'Vaseline', variant: 'Package', quantity: '1 package', location: 'Kabul', reference: 'ret-1' },
    { id: 'mv-4', date: '2026-09-10', type: 'Damage', product: 'Soap', variant: 'Carton', quantity: '1 carton', location: 'Maidan/Wardak', reference: 'dmg-1' }
  ];

  global.MBMS_MOCK.returns = [
    { id: 'ret-1', sale_id: 'sale-1', product: 'Vaseline', variant: 'Package', quantity: '1', unit: 'package', reason: 'Sample return', condition: '', date: '2026-09-15', location: 'Kabul' }
  ];

  global.MBMS_MOCK.damages = [
    { id: 'dmg-1', product: 'Soap', variant: 'Carton', quantity: '1', unit: 'carton', reason: 'Sample damage record', date: '2026-09-10', location: 'Maidan/Wardak' }
  ];
})(window);
