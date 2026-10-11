# Backend API — Phase 5 (Sales)

Base URL: `/api/v1`  
Auth: Bearer token  

## Behavior

Creating a sale (single DB transaction):

1. Backend calculates `line_total` and `total_amount`
2. `remaining_amount = total − paid`
3. Decreases stock via `StockService` (type `sale`) — fails if insufficient stock
4. If `remaining_amount > 0`, increases customer `balance` (receivable)
5. Sets `payment_type`: `cash` | `credit` | `mixed`
6. Records user, location, date/time, reference (`SAL-…`)

Frontend totals are ignored.

---

## Endpoints

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/sales` | Filters: `customer_id`, `location_id`, `payment_type`, `from`, `to` |
| POST | `/api/v1/sales` | Create posted sale |
| GET | `/api/v1/sales/{id}` | Includes items, customer, location, user |

No update/delete of posted sales in this phase.

### POST body
```json
{
  "customer_id": 1,
  "location_id": 1,
  "sold_at": "2026-09-25T12:00:00",
  "paid_amount": 50,
  "notes": "optional",
  "items": [
    {
      "product_variant_id": 1,
      "quantity": 5,
      "unit_price": 40,
      "unit": "kg"
    }
  ]
}
```

---

## Balance rule

- Customer `balance` += sale `remaining_amount`
- Fully paid (cash) sale does not change customer balance
- Later customer payments (Phase 6) will reduce balance

---

## OPEN QUESTIONS

1. Whether posted sales can be edited/voided
2. Exact multi-unit conversion when sale unit ≠ base_unit (qty treated as base_unit for stock)
3. Module-specific credit policies (tea/rice/detergent) — general cash/credit supported; no extra limits invented
