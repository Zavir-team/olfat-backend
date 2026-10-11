/**
 * Phase 3 sample records for UI only.
 * Stock, balances, and totals are placeholders, not official calculations.
 */
(function (global) {
  'use strict';

  var locations = [
    { id: 'kabul', name: 'Kabul' },
    { id: 'maidan-wardak', name: 'Maidan/Wardak' },
    { id: 'ghazni', name: 'Ghazni' }
  ];

  global.MBMS_MOCK = global.MBMS_MOCK || {};
  global.MBMS_MOCK.locations = locations;

  global.MBMS_MOCK.tea = {
    types: [
      { id: 'green', name: 'Green Tea', notes: 'Specification type' },
      { id: 'black', name: 'Black Tea', notes: 'Specification type' }
    ],
    sizes: [
      { id: '20kg', label: '20kg' },
      { id: '30kg', label: '30kg' },
      { id: '35kg', label: '35kg' },
      { id: '50kg', label: '50kg' },
      { id: '68kg', label: '68kg' }
    ],
    variants: [
      { id: 'green-20kg', type_id: 'green', size_id: '20kg', stock_display: '36 bags', source: 'mock' },
      { id: 'green-30kg', type_id: 'green', size_id: '30kg', stock_display: '12 bags', source: 'mock' },
      { id: 'black-50kg', type_id: 'black', size_id: '50kg', stock_display: '8 bags', source: 'mock' },
      { id: 'black-68kg', type_id: 'black', size_id: '68kg', stock_display: '5 bags', source: 'mock' }
    ],
    purchases: [
      { id: 'tp-1', type_id: 'green', size_id: '20kg', supplier: 'Sample supplier A', bags: '20', quantity: '400kg', price: '12.50', total: '5,000', paid: '3,000', remaining: '2,000', date: '2026-09-20', location: 'Kabul' }
    ],
    sales: [
      { id: 'ts-1', type_id: 'green', size_id: '20kg', customer: 'Sample customer A', quantity: '40kg', price: '16.00', total: '640', paid: '640', remaining: '0', payment_type: 'cash', date: '2026-09-22', location: 'Kabul' }
    ]
  };

  global.MBMS_MOCK.rice = {
    types: [
      { id: 'quality-a', name: 'Sample quality A', notes: 'Rice types/qualities are separate records' },
      { id: 'quality-b', name: 'Sample quality B', notes: 'Names will come from the backend' }
    ],
    sizes: [
      { id: '20kg', label: '20kg' },
      { id: '24.5kg', label: '24.5kg' }
    ],
    variants: [
      { id: 'quality-a-20kg', type_id: 'quality-a', size_id: '20kg', stock_display: '18 bags', source: 'mock' },
      { id: 'quality-b-24.5kg', type_id: 'quality-b', size_id: '24.5kg', stock_display: '9 bags', source: 'mock' }
    ],
    purchases: [
      { id: 'rp-1', type_id: 'quality-a', size_id: '20kg', supplier: 'Sample rice supplier', bags: '10', quantity: '200kg', price: '18.00', total: '3,600', paid: '', remaining: '', date: '2026-09-18', location: 'Ghazni' }
    ],
    sales: [
      { id: 'rs-1', type_id: 'quality-a', size_id: '20kg', customer: 'Sample customer B', quantity: '20kg', price: '22.00', total: '440', paid: '440', remaining: '0', payment_type: 'cash', date: '2026-09-21', location: 'Ghazni' }
    ]
  };

  global.MBMS_MOCK.detergents = {
    products: [
      {
        id: 'vaseline',
        name: 'Vaseline',
        example: 'Specification example: 6 pieces per package. Also supports kilogram sales. Package quantity is configurable.',
        units: [
          { id: 'package', label: 'Package' },
          { id: 'piece', label: 'Piece' },
          { id: 'kg', label: 'Kilogram' }
        ]
      },
      {
        id: 'liquid-dishwashing',
        name: 'Liquid Dishwashing',
        example: 'Specification examples: 3, 6, or 12 bottles; kilogram sales where applicable; 20-liter drum. Quantities are configurable.',
        units: [
          { id: 'package', label: 'Package' },
          { id: 'bottle', label: 'Bottle' },
          { id: 'kg', label: 'Kilogram' },
          { id: 'drum-20l', label: '20-liter drum' },
          { id: 'other', label: 'Other configured unit' }
        ]
      },
      {
        id: 'shampoo',
        name: 'Shampoo',
        example: 'Specification example: 16 pieces per package. Future package quantities may be added.',
        units: [
          { id: 'package', label: 'Package' },
          { id: 'piece', label: 'Piece' }
        ]
      },
      {
        id: 'powder',
        name: 'Powder',
        example: 'Specification examples: 1kg, 2kg, 5kg packages and 10kg, 20kg bags. These are current examples, not fixed logic.',
        units: [
          { id: 'package', label: 'Package' },
          { id: 'bag', label: 'Bag' },
          { id: 'kg', label: 'Kilogram' }
        ]
      },
      {
        id: 'soap',
        name: 'Soap',
        example: 'Soap is managed by carton. Pieces per carton may differ and must stay configurable.',
        units: [
          { id: 'carton', label: 'Carton' },
          { id: 'piece', label: 'Piece' }
        ]
      }
    ],
    variants: [
      { id: 'vaseline-package', product_id: 'vaseline', unit_id: 'package', stock_display: '24 packages', source: 'mock' },
      { id: 'vaseline-kg', product_id: 'vaseline', unit_id: 'kg', stock_display: '15 kg', source: 'mock' },
      { id: 'liquid-drum', product_id: 'liquid-dishwashing', unit_id: 'drum-20l', stock_display: '4 drums', source: 'mock' },
      { id: 'shampoo-package', product_id: 'shampoo', unit_id: 'package', stock_display: '11 packages', source: 'mock' },
      { id: 'powder-5kg', product_id: 'powder', unit_id: 'package', stock_display: '30 packages', source: 'mock' },
      { id: 'soap-carton', product_id: 'soap', unit_id: 'carton', stock_display: '16 cartons', source: 'mock' }
    ],
    purchases: [
      { id: 'dp-1', product_id: 'soap', unit_id: 'carton', supplier: 'Sample detergent supplier', quantity: '20', price: '45.00', total: '900', paid: '900', date: '2026-09-12', location: 'Kabul' }
    ],
    sales: [
      { id: 'ds-1', product_id: 'vaseline', unit_id: 'package', customer: 'Sample customer A', quantity: '2', price: '18.00', total: '36', paid: '36', payment_type: 'cash', date: '2026-09-23', location: 'Kabul' }
    ]
  };

  global.MBMS_MOCK.customers = [
    { id: 'c-1', name: 'Sample customer A', phone: '0700000001', address: 'Sample street', location: 'Kabul', balance_display: '1,250', notes: 'Sample receivable. Official balance comes from Laravel.' },
    { id: 'c-2', name: 'Sample customer B', phone: '0700000002', address: 'Sample market', location: 'Ghazni', balance_display: '0', notes: 'Sample receivable. Official balance comes from Laravel.' }
  ];

  global.MBMS_MOCK.suppliers = [
    { id: 's-1', name: 'Sample supplier A', phone: '0780000001', address: 'Sample warehouse', location: 'Kabul', balance_display: '2,000', notes: 'Sample payable. Official balance comes from Laravel.' },
    { id: 's-2', name: 'Sample rice supplier', phone: '0780000002', address: 'Sample depot', location: 'Ghazni', balance_display: '800', notes: 'Sample payable. Official balance comes from Laravel.' }
  ];

  global.MBMS_MOCK.customerHistory = {
    'c-1': {
      sales: [{ id: 'cs-1', date: '2026-09-22', detail: 'Green Tea 20kg', amount: '640', status: 'Paid' }],
      payments: [{ id: 'cp-1', date: '2026-09-22', amount: '640', method: 'Cash', location: 'Kabul' }],
      transactions: [
        { id: 'ct-1', date: '2026-09-22', type: 'Sale', detail: 'Green Tea 20kg', amount: '640' },
        { id: 'ct-2', date: '2026-09-22', type: 'Payment', detail: 'Sale payment', amount: '640' }
      ]
    },
    'c-2': { sales: [], payments: [], transactions: [] }
  };

  global.MBMS_MOCK.supplierHistory = {
    's-1': {
      purchases: [{ id: 'sp-1', date: '2026-09-20', detail: 'Green Tea 20kg', amount: '5,000' }],
      payments: [{ id: 'spp-1', date: '2026-09-20', amount: '3,000', method: 'Cash', location: 'Kabul' }],
      transactions: [
        { id: 'st-1', date: '2026-09-20', type: 'Purchase', detail: 'Green Tea 20kg', amount: '5,000' },
        { id: 'st-2', date: '2026-09-20', type: 'Payment', detail: 'Supplier payment', amount: '3,000' }
      ]
    },
    's-2': { purchases: [], payments: [], transactions: [] }
  };
})(window);
