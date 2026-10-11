# Backend API — Phase 4 (Purchases)

Base URL: `/api/v1`  
Auth: Bearer token  

## Behavior

Creating a purchase (single DB transaction):

1. Backend calculates each `line_total = quantity × unit_price`
2. Backend calculates `total_amount`, `remaining_amount = total − paid`
3. Increases stock via `StockService` (movement type `purchase`)
4. If `remaining_amount > 0`, increases supplier `balance` (payable)
5. Records `user_id`, `location_id`, `purchased_at`, `reference_no`

Frontend-sent `total_amount` / `line_total` / `remaining_amount` are **ignored**.

---

## Endpoints

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/purchases` | Filters: `supplier_id`, `location_id`, `from`, `to`, `per_page` |
| POST | `/api/v1/purchases` | Create posted purchase |
| GET | `/api/v1/purchases/{id}` | Includes items, supplier, location, user |

No update/delete of posted purchases in this phase.

### POST body
```json
{
  "supplier_id": 1,
  "location_id": 1,
  "purchased_at": "2026-09-25T10:00:00",
  "paid_amount": 100,
  "notes": "optional",
  "items": [
    {
      "product_variant_id": 1,
      "quantity": 10,
      "unit_price": 50,
      "unit": "kg"
    }
  ]
}
```

### Response highlights
- `reference_no` e.g. `PUR-20260925-AB12CD`
- `total_amount`, `paid_amount`, `remaining_amount`
- `items[].line_total`
- `status`: `posted`

---

## Balance rule

- Supplier `balance` += purchase `remaining_amount`
- Fully paid purchase does not change supplier balance
- Later supplier payments (Phase 6) will reduce balance

---

## OPEN QUESTIONS

1. Rice / detergent supplier-credit rules (Master Spec §28.3–4) — general paid/remaining supported; no module-specific credit limits invented
2. Whether posted purchases can be edited/voided later
3. Exact multi-unit conversion when purchase unit ≠ base_unit (currently quantity is treated as base_unit stock qty)
