# Backend API — Phase 9 (Dashboard & Reports)

Base URL: `/api/v1`  
Auth: Bearer token  

P&L is **not** fully implemented yet — costing method is still an OPEN QUESTION.

---

## Dashboard

`GET /api/v1/dashboard?location_id=`

Returns:
- today's sales/purchases totals + counts
- customer receivables / supplier payables
- external open debts totals
- low stock warnings (`quantity <= 5`)
- recent sales/purchases
- `profit_loss.available = false` + open question note

---

## Reports

| Endpoint | Filters |
|----------|---------|
| `GET /api/v1/reports/sales` | `from`, `to`, `location_id` |
| `GET /api/v1/reports/purchases` | `from`, `to`, `location_id` |
| `GET /api/v1/reports/stock` | `location_id`, `module` |
| `GET /api/v1/reports/stock-movements` | `from`, `to`, `location_id`, `type` |
| `GET /api/v1/reports/financial` | — |
| `GET /api/v1/reports/workers` | `from`, `to`, `worker_id`, `location_id` |
| `GET /api/v1/reports/damaged-stock` | `from`, `to`, `location_id` |
| `GET /api/v1/reports/sales-returns` | `from`, `to`, `location_id` |
| `GET /api/v1/reports/home-expenses` | `from`, `to`, `category` |
| `GET /api/v1/reports/profit-loss` | placeholder only |

Sales/purchases include summary totals and a `by_day` breakdown.

Home expenses report notes they are excluded from business profit.

---

## OPEN QUESTIONS (blocking full P&L)

1. FIFO vs Weighted Average (§28.1)
2. Which operating expenses affect net profit (§28.2)
