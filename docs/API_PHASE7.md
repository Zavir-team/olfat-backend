# Backend API — Phase 7 (Sales Returns & Damaged Stock)

Base URL: `/api/v1`  
Auth: Bearer token  

---

## Sales returns

Creates a return against an original sale.

### Behavior (DB transaction)
1. Validates each `sale_item_id` belongs to the sale
2. Blocks returning more than `quantity − returned_quantity`
3. Uses original sale line `unit_price` (backend totals)
4. Increases stock (`sale_return` movement)
5. Decreases customer `balance` by return total (receivable ↓ / credit ↑)
6. Updates `sale_items.returned_quantity`
7. Records user, location, date/time, reference (`SRET-…`)

Original sale `paid_amount` / `remaining_amount` are **not rewritten** in this phase.

### Endpoints
| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/sales-returns` |
| POST | `/api/v1/sales-returns` |
| GET | `/api/v1/sales-returns/{id}` |

### POST body
```json
{
  "sale_id": 1,
  "location_id": 1,
  "returned_at": "2026-09-26T10:00:00",
  "notes": "optional",
  "items": [
    { "sale_item_id": 10, "quantity": 2 }
  ]
}
```

`location_id` optional — defaults to original sale location.

---

## Damaged stock

Permanent damage record + stock decrease.

### Behavior
1. Creates `damaged_stocks` row
2. Decreases available stock (`damage` movement)
3. Fails if insufficient stock
4. Records reason, user, location, date/time (`DMG-…`)

### Endpoints
| Method | Endpoint |
|--------|----------|
| GET | `/api/v1/damaged-stocks` |
| POST | `/api/v1/damaged-stocks` |
| GET | `/api/v1/damaged-stocks/{id}` |

### POST body
```json
{
  "product_variant_id": 1,
  "location_id": 1,
  "quantity": 3,
  "unit": "kg",
  "reason": "Water damage",
  "damaged_at": "2026-09-26T11:00:00",
  "notes": "optional"
}
```

---

## OPEN QUESTIONS

1. Return condition rules (resellable vs scrap) — currently all returns restore available stock
2. Whether original sale financial fields should be rewritten on return
3. Whether customer balance may go negative (store credit) after return of a cash sale — currently allowed
4. Whether damaged stock should also post an expense/P&L entry (deferred until costing method is chosen)
