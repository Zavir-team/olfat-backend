# Backend API Index (for Frontend)

**Base URL:** `/api/v1`  
**Auth:** `Authorization: Bearer {token}` (Laravel Sanctum)  
**Envelope:**

```json
{ "success": true, "message": "OK", "data": {} }
```

Seeded logins (password `password`):
- `user1@example.com`
- `user2@example.com`
- `user3@example.com`

Detailed phase docs: `docs/API_PHASE1.md` … `docs/API_PHASE10.md`

---

## Auth
| Method | Path | Auth |
|--------|------|------|
| POST | `/auth/login` | no |
| GET | `/auth/me` | yes |
| POST | `/auth/logout` | yes |

## Core
| Resource | Paths |
|----------|--------|
| Users | `/users` |
| Locations | `/locations` |
| Settings | `/settings`, `/settings/{key}` |
| Dashboard | `/dashboard` |

## Parties
| Resource | Paths |
|----------|--------|
| Customers | `/customers` |
| Suppliers | `/suppliers` |
| External parties | `/external-parties` |

## Catalog & stock
| Resource | Paths |
|----------|--------|
| Products | `/products` |
| Variants | `/product-variants` |
| Stock balances | `/stock/balances` |
| Stock movements | `/stock/movements` |
| Stock adjustments | `POST /stock/adjustments` |
| Damaged stock | `/damaged-stocks` |

## Trading
| Resource | Paths |
|----------|--------|
| Purchases | `/purchases` (index/store/show) |
| Sales | `/sales` (index/store/show) |
| Sales returns | `/sales-returns` |
| Customer payments | `/customer-payments` |
| Supplier payments | `/supplier-payments` |

## Other modules
| Resource | Paths |
|----------|--------|
| Workers | `/workers` |
| Worker payments | `/worker-payments` |
| External debts | `/external-debts`, `POST /external-debts/{id}/payments` |
| Home expenses | `/home-expenses` |

## Reports
| Path |
|------|
| `/reports/sales` |
| `/reports/purchases` |
| `/reports/stock` |
| `/reports/stock-movements` |
| `/reports/financial` |
| `/reports/workers` |
| `/reports/damaged-stock` |
| `/reports/sales-returns` |
| `/reports/home-expenses` |
| `/reports/profit-loss` (placeholder) |

---

## Important backend rules for FE

1. Never trust/send totals as source of truth — backend recalculates.
2. Customer/supplier `balance` cannot be set from party create/update.
3. Sales fail if stock is insufficient.
4. Payments cannot exceed balance by default.
5. Home expenses do not affect business P&L.
6. Profit/loss is not available until costing method is decided.

## OPEN QUESTIONS (team decisions still required)

1. FIFO vs Weighted Average
2. Operating expenses in net profit?
3. Multi-location user access rules
4. Home expense visibility
5. Factory material stock tracking / auto production consume
6. Exact KG conversion/pricing rules
7. Currency confirmation
