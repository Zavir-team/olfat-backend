# Backend API — Phase 2 (Customers & Suppliers)

Base URL: `/api/v1`  
Auth: Bearer token (Sanctum)  
Envelope: `{ success, message, data }`

**Rule:** `balance` is backend-owned. Create/update APIs ignore any client-sent `balance`. It starts at `0.00` and will change only via sales, purchases, and payments (later phases).

---

## Customers

| Method | Endpoint | Auth | Notes |
|--------|----------|------|--------|
| GET | `/api/v1/customers` | yes | Query: `search`, `is_active`, `per_page` (paginated) |
| POST | `/api/v1/customers` | yes | Create |
| GET | `/api/v1/customers/{id}` | yes | Show |
| PUT/PATCH | `/api/v1/customers/{id}` | yes | Update profile fields only |
| DELETE | `/api/v1/customers/{id}` | yes | Delete |

### Create / update fields
- `name` (required on create)
- `phone` (optional)
- `address` (optional)
- `notes` (optional)
- `is_active` (optional boolean)

### Response highlights
- `balance` — decimal string, e.g. `"0.00"` (customer receivable)
- `created_by` — user id who created the record

---

## Suppliers

| Method | Endpoint | Auth | Notes |
|--------|----------|------|--------|
| GET | `/api/v1/suppliers` | yes | Query: `search`, `is_active`, `per_page` |
| POST | `/api/v1/suppliers` | yes | Create |
| GET | `/api/v1/suppliers/{id}` | yes | Show |
| PUT/PATCH | `/api/v1/suppliers/{id}` | yes | Update profile fields only |
| DELETE | `/api/v1/suppliers/{id}` | yes | Delete |

Same request fields as customers.

### Balance meaning (v1)
- Customer `balance` > 0 → customer owes the business (receivable)
- Supplier `balance` > 0 → business owes the supplier (payable)

**OPEN QUESTION:** opening balances / sign convention confirmation with the team before financial posting modules.

---

## Not in this phase
- Transaction history endpoints (come with sales/purchases/payments)
- Manual balance adjustment API
