# Backend API — Phase 6 (Payments)

Base URL: `/api/v1`  
Auth: Bearer token  

## Behavior

Payments run in a DB transaction with row lock on the party balance.

### Customer payment
- Decreases customer `balance` (receivable)
- Records `balance_before` / `balance_after`
- Optional `sale_id` (must belong to same customer)
- Rejects amount > balance unless `allow_overpayment=true`

### Supplier payment
- Decreases supplier `balance` (payable)
- Optional `purchase_id` (must belong to same supplier)
- Same overpayment rule

Both record: user, location, paid_at, reference number.

---

## Endpoints

### Customer payments
| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/customer-payments` |
| POST | `/api/v1/customer-payments` |
| GET | `/api/v1/customer-payments/{id}` |

POST body:
```json
{
  "customer_id": 1,
  "location_id": 1,
  "amount": 150,
  "sale_id": null,
  "paid_at": "2026-09-25T14:00:00",
  "notes": "optional",
  "allow_overpayment": false
}
```

### Supplier payments
| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/supplier-payments` |
| POST | `/api/v1/supplier-payments` |
| GET | `/api/v1/supplier-payments/{id}` |

POST body mirrors customer payments with `supplier_id` / `purchase_id`.

---

## OPEN QUESTIONS

1. Whether overpayment (credit balance) should be allowed by default
2. Whether payment must allocate to specific sale/purchase lines (FIFO allocation) — currently optional reference only; does not auto-update sale/purchase `paid_amount`/`remaining_amount`
3. Whether sale/purchase remaining fields should be updated when a linked payment is recorded
