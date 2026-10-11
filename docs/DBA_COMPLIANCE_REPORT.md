# DBA Compliance Report

Audit of the Olfat Backend database and application against
**Craig S. Mullins, *Database Administration: The Complete Guide to DBA
Practices and Procedures*, 2nd ed. (Addison-Wesley, 2013)**.

Date: 2026-10-11 · Scope: schema (34 migrations), services, API, operations ·
Baseline: 66 tests passing → after hardening: 73 tests passing.

Legend: ✅ compliant before audit · 🔧 fixed in this audit · ⚠️ accepted gap / manual step

---

## Chapter 2 — Creating the Database Environment

| Topic | Status | Notes |
|---|---|---|
| DBMS choice documented | ✅ | MySQL/MariaDB/PostgreSQL in production, SQLite for dev only (`README.md`, `DEPLOY.md`). |
| Naming conventions | ✅ | snake_case plural tables, `*_id` FKs, `*_at` timestamps, `reference_no` for documents — consistent across all 30 tables. |
| Separate environments | ✅ 🔧 | local (SQLite) / testing (in-memory) / production. `scripts/prepare-production.php` now refuses SQLite and debug logging in production and accepts `DB_URL`. |
| Standards & procedures written down | 🔧 | Added `docs/BACKUP_AND_RECOVERY.md`, `docs/DATA_DICTIONARY.md`, `VERCEL_DEPLOY.md`. |

## Chapter 3 & 4 — Data Modeling, Normalization, Database Design

| Topic | Status | Notes |
|---|---|---|
| 3NF logical model | ✅ | Products → variants → stock balances per location; documents (sales, purchases, returns) with item tables; payments as separate entities. No repeating groups. |
| Controlled denormalization | ✅ | `customers.balance`, `suppliers.balance`, `*.remaining_amount`, `stock_balances.quantity` are *derivable* data kept for performance (Ch. 4 "Derivable Data"). Every write happens inside a transaction with `lockForUpdate`, and `db:integrity-check` now reconciles them against the source rows. |
| Primary keys on every table | ✅ | Surrogate `id` BIGINT. Business keys enforced by UNIQUE (`reference_no`, `email`, `sku`, `slug`, `(product_id,name)`, `(product_variant_id,location_id)`). |
| Referential constraints for all relationships | ✅ 🔧 | All FKs declared. **Fixed:** `stock_movements.user_id` was `CASCADE` (deleting a user would erase inventory history) → now `RESTRICT`. |
| Index design | ✅ 🔧 | Indexes on every FK, on report filters (`location_id`+date), balances. **Added:** `stock_movements(location_id, occurred_at)`, `customer_payments(location_id, paid_at)`, `supplier_payments(location_id, paid_at)`, `sales(status)`, `purchases(status)`. |
| Data types | ✅ | Money as `DECIMAL(15,2)`, quantities `DECIMAL(15,4)`, no floats in storage; `utf8mb4` on MySQL. |

## Chapter 5 — Application Design (transactions & locking)

| Topic | Status | Notes |
|---|---|---|
| Unit of work = one DB transaction | ✅ | Sale/purchase/payment/return services wrap all writes in `DB::transaction`. |
| Row-level locking to avoid lost updates | ✅ | `lockForUpdate()` on stock balances, customers, suppliers, sales. |
| Deadlock handling | 🔧 | Outer transactions in the five money/stock services now retry up to 3 times on DBMS deadlock (`attempts: 3`). |
| Set-at-a-time SQL / bounded reports | ✅ | Aggregates via `SUM/COUNT`, date ranges capped at 366 days, pagination capped at 100. |
| Parameterised SQL (no injection) | ✅ | Eloquent/Query Builder everywhere; the only raw fragments are constant aggregate expressions. |

## Chapter 7 — Database Change Management

| Topic | Status | Notes |
|---|---|---|
| Versioned, repeatable schema changes | ✅ | Laravel migrations with `up/down`. |
| Source control | 🔧 | The project was **not** a Git repository. Initialised (`main`) with a safe `.gitignore`; commit and push are the first deployment steps. |
| Change procedure | 🔧 | Documented in `VERCEL_DEPLOY.md` §9: migrate production before shipping code that depends on it. |

## Chapter 8 — Data Availability

| Topic | Status | Notes |
|---|---|---|
| Health probe for monitoring | 🔧 | `GET /api/health` checks DB + cache round-trip, returns 503 when degraded; `/up` kept for the platform. |
| Automate DBA functions | 🔧 | Scheduler (`routes/console.php`): daily backup, weekly integrity audit, daily token/reset pruning. HTTP trigger for cron-less hosts. |
| Proxy/HTTPS correctness | 🔧 | `trustProxies('*')` so `ForceHttps` and secure cookies behave behind Vercel/Cloudflare/Nginx (prevents redirect loops = outage). |

## Chapters 9–12 — Performance

| Topic | Status | Notes |
|---|---|---|
| Report caching with invalidation | ✅ | `ReportCacheObserver` + versioned cache keys. |
| Access paths | ✅ | Covered by composite indexes listed above. |
| SQLite dev tuning | 🔧 | `busy_timeout=5000`, `journal_mode=WAL`, `synchronous=NORMAL` (local only). |

## Chapter 13 — Data Integrity

| Topic | Status | Notes |
|---|---|---|
| Entity integrity / uniqueness | ✅ | See Ch. 4 row. |
| **Check constraints in the DBMS** | 🔧 | None existed — all rules lived only in PHP. Added 20 `CHECK` constraints (`2026_10_11_183211_add_data_integrity_constraints`): domain values for `role`, `module`, `payment_type`, `direction`, `status`, movement `type`; `quantity > 0`; amounts `>= 0`; `quantity_change <> 0`. Enforced on MySQL 8.0.16+/MariaDB/PostgreSQL; SQLite (dev) relies on application validation. |
| Referential integrity actions | ✅ 🔧 | `RESTRICT` for documents, `CASCADE` for item rows, `SET NULL` for `created_by`. History FK fixed (see above). |
| Semantic integrity audit tool | 🔧 | `php artisan db:integrity-check`: orphan detection on 15 relationships, stock balance vs. movements ledger, document totals vs. items, paid+remaining ≤ total, debt arithmetic, domain values, "at least one active admin". |

## Chapter 14 — Database Security

| Topic | Status | Notes |
|---|---|---|
| Authentication / authorization | ✅ | Sanctum tokens (12 h expiry), roles (admin/staff), active-user and province-scope middleware, admin-only routes. |
| Brute-force protection | ✅ | Login limited to 5/min per IP and per email+IP; API 120/min. |
| Password storage | ✅ | bcrypt (12 rounds), `Password::defaults()` policy. |
| Encryption in transit | ✅ | HTTPS forced in production; TLS to DB via `MYSQL_ATTR_SSL_CA` / `sslmode=require`. |
| **Auditing** | 🔧 | Added append-only `audit_logs` (who/what/when/old/new/IP/UA) via `AuditLogObserver` on 20 models; passwords redacted; admin-only read endpoint. |
| HTTP hardening | 🔧 | `SecurityHeaders` middleware: HSTS, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`. |
| Least-privilege DB account | ⚠️ | Manual: create an app user with DML-only rights; use an admin user only for migrations (documented). |
| Cron endpoint | 🔧 | Constant-time secret comparison, disabled when no secret is configured, rate-limited. |

## Chapter 15 — Regulatory Compliance

| Topic | Status | Notes |
|---|---|---|
| Data access tracking | 🔧 | `audit_logs` (see Ch. 14). |
| Data retention / archiving | ⚠️ | No purge policy yet; `audit_logs` grows ~1 row per write. Review yearly; archive rows older than N years if volume becomes an issue. |

## Chapter 16 & 17 — Backup, Recovery, Disaster Planning

| Topic | Status | Notes |
|---|---|---|
| **Backup procedure** | 🔧 | None existed. Added `php artisan db:backup`: full logical backup (native `mysqldump` or portable dumper for MySQL/PostgreSQL/SQLite), consistent snapshot, gzip, post-write verification, retention pruning. Scheduled daily. |
| Documented & tested recovery | 🔧 | `docs/BACKUP_AND_RECOVERY.md` with restore commands per DBMS, PITR, row-level recovery via audit log, quarterly drill log, disaster matrix. Backup+restore-readability covered by an automated test. |
| DDL backup | ✅ | Migrations in Git. |
| Off-site copy | ⚠️ | Operator procedure documented (Vercel disk is ephemeral). Optional: add an S3 disk and set `BACKUP_DISK=s3` (requires `league/flysystem-aws-s3-v3`). |

## Chapter 18 — Storage & Capacity

| Topic | Status | Notes |
|---|---|---|
| Growth | ✅ | BIGINT keys; DECIMAL(15,x) supports values up to 10^13. Largest tables (`stock_movements`, `audit_logs`) are append-only and indexed by date. |
| Serverless storage | 🔧 | All writable paths redirected to `/tmp`; logs to stderr; cache in DB. |

## Chapter 22 — Metadata Management

| Topic | Status | Notes |
|---|---|---|
| Data dictionary | 🔧 | `docs/DATA_DICTIONARY.md` created (every table, purpose, key columns, constraints). |

## Chapter 24 — DBA Rules of Thumb

*Write down everything* → runbooks added. *Automate!* → scheduler + health + integrity check. *Keep everything* → audit trail, Git. *Measure twice, cut once* → integrity check and tests before/after every change.

---

## Remaining manual items (owner: you)

1. Create the production DB user with least privilege (Ch. 14).
2. Push the repo to GitHub and deploy following `VERCEL_DEPLOY.md`.
3. Put `APP_KEY`, `CRON_SECRET`, DB credentials in a password manager (Ch. 17).
4. Run the first recovery drill after go-live and record it in `docs/BACKUP_AND_RECOVERY.md` §5.
5. Decide an audit-log retention period (Ch. 15) after 6–12 months of data.
