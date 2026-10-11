# Backend API — Phase 8 (Workers, External Debts, Home Expenses)

Base URL: `/api/v1`  
Auth: Bearer token  

These modules are separate from normal customers/suppliers and from business P&L unless the team changes the Master Spec.

---

## Workers

| Method | Endpoint |
|--------|----------|
| CRUD | `/api/v1/workers` |
| GET/POST/GET{id} | `/api/v1/worker-payments` |

Worker payment body:
```json
{
  "worker_id": 1,
  "location_id": 1,
  "amount": 120.5,
  "paid_at": "2026-09-26T10:00:00",
  "description": "Weekly pay"
}
```

Records user + location. No full payroll system.

---

## External parties & debts

Completely separate from customers/suppliers.

### Parties
CRUD: `/api/v1/external-parties`

### Debts
| Method | Endpoint |
|--------|----------|
| GET/POST | `/api/v1/external-debts` |
| GET | `/api/v1/external-debts/{id}` (includes payments) |
| POST | `/api/v1/external-debts/{id}/payments` |

Direction values:
- `business_owes` — business owes person
- `person_owes` — person owes business

Create debt:
```json
{
  "external_party_id": 1,
  "direction": "business_owes",
  "reason": "Loan",
  "original_amount": 500,
  "paid_amount": 0,
  "location_id": 1
}
```

Backend calculates `remaining_amount`. Payments cannot exceed remaining; status becomes `closed` at zero.

---

## Home expenses

Separate from business accounting. **Must not affect business profit** (Master Spec §15).

CRUD: `/api/v1/home-expenses`

```json
{
  "category": "Food",
  "description": "Market",
  "amount": 45.75,
  "expense_date": "2026-09-26",
  "notes": "optional"
}
```

Records creating user. No location required.

---

## OPEN QUESTIONS

1. External debt opening-balance process beyond create-with-amounts (Master Spec §28.9)
2. Home-expense visibility among the three users — currently all authenticated users can see all (§28.8)
3. Whether worker payments should later feed operating-expense P&L (deferred until expense/P&L rules are finalized)
