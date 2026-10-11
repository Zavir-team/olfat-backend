# Backup & Recovery Runbook

Reference: Mullins, *Database Administration* (2nd ed.), Ch. 16 "Database Backup
and Recovery" and Ch. 17 "Disaster Planning". The book's rule: *"The most
important aspect of any backup plan is to thoroughly test each different type
of recovery… Document the type of backup taken for each database object, along
with a schedule of when each is backed up."* This file is that document.

## 1. What is backed up

| Object | Method | Schedule | Retention |
|---|---|---|---|
| All business tables (users, locations, customers, suppliers, products, variants, stock, sales, purchases, payments, returns, damages, workers, debts, expenses, settings, audit_logs, migrations) | **Full logical backup** `php artisan db:backup` (`mysqldump` when available, otherwise portable INSERT dump; gzip; verified after write) | Daily 02:00 via scheduler | `BACKUP_KEEP_DAYS` (default 30) |
| Transient tables (`cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`) | Excluded on purpose — safe to recreate | — | — |
| Schema (DDL) | Versioned in `database/migrations/*` (Git) — the "database object definition backup" the book asks for | Every change | Forever (Git) |
| Managed DB provider (Neon / TiDB / Aiven …) | Provider snapshots + point-in-time recovery | Continuous | Per plan (7–30 days) |

Full backups are used (not incremental) because the database is small; the
book recommends full image copies for small objects.

Backups are written to the `BACKUP_DISK` filesystem under `BACKUP_DIRECTORY`
(`storage/app/private/backups` on a normal server). On **Vercel** the function
disk is ephemeral: rely on the provider's backups and run `db:backup` from an
operator PC (see section 4) for offline copies stored off-site (the book's
"remote site" guidance).

## 2. Commands

```bash
php artisan db:backup                       # default disk/retention from config/backup.php
php artisan db:backup --disk=s3 --keep=90   # alternative disk (requires the disk in config/filesystems.php)
php artisan db:backup --no-compress         # plain .sql
php artisan db:integrity-check              # semantic/referential audit, exit 1 on problems
php artisan db:integrity-check --json
```

## 3. Restore procedures (tested paths)

Always restore into a **new, empty database** first, verify, then switch the
app over (or copy data across). Never restore over the live DB without a fresh
backup of it.

### 3.1 From a `db:backup` dump

```bash
# 1. create empty schema
php artisan migrate --force              # against the TARGET database (.env points to it)

# 2. load data
gunzip -c olfat-backend-mysql-2026-10-11_020000.sql.gz | mysql -h HOST -u USER -p DBNAME       # MySQL
gunzip -c olfat-backend-pgsql-2026-10-11_020000.sql.gz | psql "postgresql://USER:PW@HOST/DBNAME" # PostgreSQL
gunzip -c olfat-backend-sqlite-2026-10-11_020000.sql.gz | sqlite3 database/database.sqlite        # SQLite (dev)

# 3. verify
php artisan db:integrity-check
php artisan tinker --execute 'echo App\Models\Sale::count();'
```

The portable dump wraps everything in one transaction and disables FK checks
while loading, so table order does not matter.

### 3.2 Point-in-time (provider)

Neon: Console → Branches → *Restore* to a timestamp → copy the new connection
string into `DB_URL`. TiDB/Aiven: *Backups* → *Restore to new cluster*. Then
run `php artisan db:integrity-check` and switch `DB_URL` on Vercel.

### 3.3 Single-row / single-document recovery

Because `audit_logs` stores `old_values` / `new_values` for every change, an
accidental edit can be reversed by reading the row:

```
GET /api/v1/audit-logs?auditable_type=Customer&auditable_id=42   (admin token)
```

and re-applying the old values through the normal API, which creates a new
audit entry (no history is ever overwritten).

## 4. Off-site operator backup (Vercel deployments)

```powershell
cd D:\OlfatProject
copy .env .env.local.bak
copy C:\Users\<you>\olfat.env.deploy .env     # production DB credentials, never in the repo
php artisan db:backup --keep=0
copy .env.local.bak .env
# copy storage\app\private\backups\*.sql.gz to an external drive / cloud folder
```

## 5. Recovery drill (do this quarterly)

1. Take a fresh `db:backup`.
2. Create an empty local SQLite or Docker MySQL database.
3. Restore following 3.1; run `php artisan db:integrity-check` and open the UI against it.
4. Record date, duration and any issues below.

| Date | Operator | Source backup | Target | Duration | Result |
|---|---|---|---|---|---|
| 2026-10-11 | automated test (`OperationsHardeningTest`) | in-memory SQLite | gz dump verified | < 1 s | PASS |

## 6. Disaster scenarios → action

| Scenario | Action |
|---|---|
| Provider outage | Wait / failover per provider status; app returns 503 on `/api/health`. |
| Database deleted | Provider PITR (3.2) or latest off-site dump (3.1). RPO ≤ 24 h with daily backups, ≤ minutes with PITR. |
| Bad deploy corrupts data | Roll back the deployment in Vercel (one click), then fix data via audit log (3.3) or PITR. |
| Lost `APP_KEY` | Users must log in again; no stored data is encrypted with it except session/cookie payloads. Keep `APP_KEY` in a password manager. |
| Lost Vercel account | Code is in GitHub; DB is at the provider; redeploy with the same env vars. Keep `.env.vercel.example` filled in a password manager (not Git). |
