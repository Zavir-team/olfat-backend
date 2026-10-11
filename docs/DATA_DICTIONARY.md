# Data Dictionary

Metadata for every application table (Mullins Ch. 22 "Metadata Management").
Source of truth is `database/migrations/`; keep this file in sync when adding a
migration. Conventions: `id` BIGINT PK on every table; `created_at`/`updated_at`
timestamps unless noted; money `DECIMAL(15,2)`; quantities `DECIMAL(15,4)` in
the variant's `base_unit`; `reference_no` is a unique human-readable document
number (`SAL-YYYYMMDD-XXXXXX`, `PUR-…`, …).

FK actions: **R** = RESTRICT (parent cannot be deleted while children exist),
**C** = CASCADE (children deleted with parent), **N** = SET NULL.

## Reference / master data

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `locations` | Provinces / branches where stock and documents live | `name`, `code`(50), `is_active` | UNIQUE `name`, UNIQUE `code` |
| `users` | Staff accounts | `name`, `email`, `phone`, `password`(bcrypt), `is_active`, `role` (`admin`\|`staff`), `location_id` → locations (N) | UNIQUE `email`; UNIQUE `location_id` (one claimed province per user); CHECK `role` |
| `customers` | Buyers; `balance` = total they owe the business | `name`, `phone`, `address`, `notes`, `balance`, `is_active`, `created_by` → users (N) | IDX `name`, `phone`, `balance` |
| `suppliers` | Vendors; `balance` = total the business owes | same shape as customers | IDX `name`, `phone`, `balance` |
| `workers` | Paid labourers | `name`, `phone`, `notes`, `is_active`, `created_by` (N) | IDX `name` |
| `external_parties` | People/companies in informal loans | `name`, `phone`, `notes`, `is_active`, `created_by` (N) | IDX `name` |
| `products` | Catalogue head, grouped by business module | `module` (`tea`\|`rice`\|`detergent`\|`raw_material`\|`factory_material`), `name`, `slug`, `description`, `is_active` | UNIQUE `slug`; IDX `module`, (`module`,`name`); CHECK `module` |
| `product_variants` | Sellable unit of a product (size/packaging) | `product_id` → products (C), `name`, `sku`, `base_unit`(30), `package_qty`, `package_unit`, `is_active` | UNIQUE `sku`; UNIQUE (`product_id`,`name`); IDX `base_unit` |
| `settings` | Key/value app configuration | `key`, `value`, `type` (`string`\|`boolean`\|`integer`\|`decimal`\|`json`), `group`, `label`, `description` | UNIQUE `key`; IDX `group` |

## Inventory

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `stock_balances` | Current on-hand quantity per variant per location (derived; reconciled by `db:integrity-check`) | `product_variant_id` (C), `location_id` (C), `quantity` | UNIQUE (`product_variant_id`,`location_id`); IDX (`location_id`,`quantity`) |
| `stock_movements` | Immutable inventory ledger; every change to a balance | `product_variant_id` (C), `location_id` (C), `type` (`purchase`\|`sale`\|`sale_return`\|`damage`\|`adjustment`\|`transfer_in`\|`transfer_out`), `quantity_change` (signed), `quantity_after`, `unit`, `quantity_in_unit`, `reference_type`/`reference_id` (polymorphic source document), `user_id` → users (**R**), `notes`, `occurred_at` | IDX (`product_variant_id`,`location_id`), (`type`,`occurred_at`), (`reference_type`,`reference_id`), (`location_id`,`occurred_at`); CHECK `quantity_change <> 0`, CHECK `type` |
| `damaged_stocks` | Write-offs | `reference_no`, `product_variant_id` (R), `location_id` (R), `user_id` (R), `quantity`, `unit`, `reason`, `damaged_at`, `notes` | UNIQUE `reference_no`; IDX (`product_variant_id`,`damaged_at`), (`location_id`,`damaged_at`); CHECK `quantity > 0` |

## Purchasing

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `purchases` | Purchase document from a supplier | `reference_no`, `supplier_id` (R), `location_id` (R), `user_id` (R), `purchased_at`, `total_amount`, `paid_amount`, `remaining_amount`, `status` (`posted`), `notes` | UNIQUE `reference_no`; IDX (`supplier_id`,`purchased_at`), (`location_id`,`purchased_at`), `status`; CHECK amounts ≥ 0 |
| `purchase_items` | Lines of a purchase | `purchase_id` (C), `product_variant_id` (R), `quantity`, `unit`, `unit_price`(15,4), `line_total` | IDX `product_variant_id`; CHECK `quantity > 0`, prices ≥ 0 |
| `supplier_payments` | Money paid to suppliers; snapshots balance before/after | `reference_no`, `supplier_id` (R), `location_id` (R), `user_id` (R), `purchase_id` (N), `amount`, `balance_before`, `balance_after`, `paid_at`, `notes` | UNIQUE `reference_no`; IDX (`supplier_id`,`paid_at`), (`location_id`,`paid_at`); CHECK `amount > 0` |

## Sales

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `sales` | Sale document to a customer | `reference_no`, `customer_id` (R), `location_id` (R), `user_id` (R), `sold_at`, `total_amount`, `paid_amount`, `remaining_amount`, `payment_type` (`cash`\|`credit`\|`mixed`), `status` (`posted`), `notes` | UNIQUE `reference_no`; IDX (`customer_id`,`sold_at`), (`location_id`,`sold_at`), (`location_id`,`payment_type`,`sold_at`), `status`; CHECK amounts ≥ 0, CHECK `payment_type` |
| `sale_items` | Lines of a sale | `sale_id` (C), `product_variant_id` (R), `quantity`, `returned_quantity`, `unit`, `unit_price`, `line_total` | IDX `product_variant_id`; CHECK `quantity > 0`, prices ≥ 0 |
| `customer_payments` | Money received from customers; snapshots balance | `reference_no`, `customer_id` (R), `location_id` (R), `user_id` (R), `sale_id` (N), `amount`, `balance_before`, `balance_after`, `paid_at`, `notes` | UNIQUE `reference_no`; IDX (`customer_id`,`paid_at`), (`location_id`,`paid_at`); CHECK `amount > 0` |
| `sales_returns` | Goods returned against a sale | `reference_no`, `sale_id` (R), `customer_id` (R), `location_id` (R), `user_id` (R), `total_amount`, `returned_at`, `status`, `notes` | UNIQUE `reference_no`; IDX (`sale_id`,`returned_at`), (`customer_id`,`returned_at`) |
| `sales_return_items` | Lines of a return, linked to the original sale line | `sales_return_id` (C), `sale_item_id` (R), `product_variant_id` (R), `quantity`, `unit`, `unit_price`, `line_total` | — |

## People, loans and household

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `worker_payments` | Wages paid | `reference_no`, `worker_id` (R), `location_id` (R), `user_id` (R), `amount`, `paid_at`, `description` | UNIQUE `reference_no`; IDX (`worker_id`,`paid_at`); CHECK `amount > 0` |
| `external_debts` | Informal loans given/taken | `reference_no`, `external_party_id` (R), `direction` (`business_owes`\|`person_owes`), `reason`, `original_amount`, `paid_amount`, `remaining_amount`, `location_id` (R), `user_id` (R), `opened_at`, `notes`, `status` (`open`\|`closed`) | UNIQUE `reference_no`; IDX (`external_party_id`,`direction`), (`status`,`opened_at`); CHECK `direction`, `status`, amounts ≥ 0 |
| `external_debt_payments` | Instalments against a debt | `reference_no`, `external_debt_id` (R), `location_id` (R), `user_id` (R), `amount`, `remaining_before`, `remaining_after`, `paid_at`, `notes` | UNIQUE `reference_no`; IDX (`external_debt_id`,`paid_at`); CHECK `amount > 0` |
| `home_expenses` | Owner's household spending (excluded from business P&L) | `reference_no`, `category`(80), `description`, `amount`, `expense_date` (DATE), `user_id` (R), `notes` | UNIQUE `reference_no`; IDX (`category`,`expense_date`), (`user_id`,`expense_date`); CHECK `amount ≥ 0` |

## Audit & framework

| Table | Purpose | Key columns | Constraints & indexes |
|---|---|---|---|
| `audit_logs` | Append-only change history (no `updated_at`) | `event` (`created`\|`updated`\|`deleted`), `auditable_type`, `auditable_id`, `user_id` (N), `old_values` JSON, `new_values` JSON, `ip_address`, `user_agent`, `url`, `created_at` | IDX (`auditable_type`,`auditable_id`), (`user_id`,`created_at`), `created_at` |
| `personal_access_tokens` | Sanctum API tokens | `tokenable_*`, `token` (sha256), `abilities`, `last_used_at`, `expires_at` | UNIQUE `token`; IDX `expires_at` |
| `migrations` | Applied schema versions | `migration`, `batch` | — |
| `cache`, `cache_locks` | Database cache store (production) | `key`, `value`, `expiration` | PK `key` |
| `sessions`, `password_reset_tokens`, `jobs`, `job_batches`, `failed_jobs` | Laravel framework tables (unused or transient) | — | — |

## Derived-value invariants (checked by `php artisan db:integrity-check`)

- `stock_balances.quantity = Σ stock_movements.quantity_change` per variant+location
- `sales.total_amount = Σ sale_items.line_total`; `purchases.total_amount = Σ purchase_items.line_total`
- `paid_amount + remaining_amount ≤ total_amount` on sales and purchases
- `external_debts.remaining_amount = original_amount − paid_amount`; closed ⇒ remaining = 0
- No orphan child rows on any FK; at least one active admin user
