# Backend API — Phase 3 (Catalog & Stock Ledger)

Base URL: `/api/v1`  
Auth: Bearer token  

## Architecture choice (interim)

Unified catalog:

- `products` (module: `tea` | `rice` | `detergent` | `raw_material` | `factory_material`)
- `product_variants` (bag size / package / unit configuration)
- `stock_balances` (qty per variant + location, always in `base_unit`)
- `stock_movements` (append-only ledger)

**OPEN QUESTION:** team may still prefer fully separate tea/rice/detergent tables. Current design supports all modules without hard-coding packaging permanently (Master Spec §9).

---

## Products

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/products` | Query: `module`, `search`, `is_active`, `per_page` |
| POST | `/api/v1/products` | Optional nested `variants[]` |
| GET | `/api/v1/products/{id}` | Includes variants |
| PUT/PATCH | `/api/v1/products/{id}` | |
| DELETE | `/api/v1/products/{id}` | Cascades variants |

### Create fields
- `module` (required)
- `name` (required)
- `slug` (optional; auto-generated if omitted)
- `description`, `is_active`
- `variants[]`: `name`, `base_unit`, `sku?`, `package_qty?`, `package_unit?`, `is_active?`

---

## Product variants

| Method | Endpoint |
|--------|----------|
| GET/POST | `/api/v1/product-variants` |
| GET/PUT/PATCH/DELETE | `/api/v1/product-variants/{id}` |

Filter list with `product_id` or `module`.

---

## Stock

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/stock/balances` | Filters: `location_id`, `product_variant_id`, `module` |
| GET | `/api/v1/stock/movements` | Filters: `location_id`, `product_variant_id`, `type` |
| POST | `/api/v1/stock/adjustments` | Manual adjustment only in this phase |

### Adjustment request
- `product_variant_id` (required)
- `location_id` (required)
- `quantity_change` (required, signed, non-zero; in **base_unit**)
- `unit`, `quantity_in_unit`, `notes`, `occurred_at` optional
- `allow_negative` optional (default false)

Stock changes use DB transactions + `lockForUpdate`.

Purchases/sales/returns/damage will post movements via the same `StockService` in later phases.

---

## Seeded catalog (CatalogSeeder)

- Tea: Green / Black × 20, 30, 35, 50, 68 kg
- Rice: general starter × 20, 24.5 kg
- Detergent: Vaseline, Liquid Dishwashing, Shampoo, Powder, Soap packages
- Raw materials: 170kg / 220kg bags

---

## OPEN QUESTIONS (not invented)

1. Exact KG conversion/pricing rules for tea & detergent (Master Spec §28.10–11)
2. Exact rice type/quality list
3. Soap pieces-per-carton configuration details
4. Whether factory materials require stock tracking (§28.5)
5. Whether production auto-consumes raw materials (§28.6)
6. Confirm unified catalog vs separate module tables with Dev 3
