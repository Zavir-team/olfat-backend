# Deploying Olfat Backend to Vercel — step by step

This guide takes the project from your PC to a live HTTPS URL on Vercel, with a
managed database, scheduled backups/maintenance, and the first admin user.

Vercel runs PHP as **serverless functions** (via the community `vercel-php`
runtime). That has three consequences the project is already prepared for:

| Constraint | How this project handles it |
|---|---|
| No local database on Vercel | Use a managed DB (PostgreSQL or MySQL). Migrations are run **from your PC** against it. |
| Filesystem is read-only (except `/tmp`) | `api/index.php` redirects Laravel's compiled caches to `/tmp`; logs go to `stderr`; cache uses the `database` store. |
| No system cron | `vercel.json` defines a Vercel Cron Job that calls `GET /api/cron/schedule` (protected by `CRON_SECRET`), which runs the Laravel scheduler. |

Files involved: `vercel.json`, `api/index.php`, `.vercelignore`, `.env.vercel.example`.

---

## 0. Prerequisites (one time, on your PC)

```powershell
php -v        # 8.3+ (you have 8.5)
composer -V   # 2.x
node -v       # 18+ (for the Vercel CLI)
npm i -g vercel
vercel login
```

Also create a free account at https://vercel.com (GitHub login is simplest).

> Your project folder name contains an apostrophe (`d:\Olfat's Project`).
> Some tools choke on that. A junction without the apostrophe exists at
> `D:\OlfatProject` — use it for all commands below.

---

## 1. Put the code in Git (recommended; enables automatic deploys)

The repository was initialised for you (`main` branch) with everything staged.
Commit it and push it to GitHub:

```powershell
cd D:\OlfatProject
git commit -m "Production hardening + Vercel deployment setup"
# Create an empty repo on github.com (no README), then:
git remote add origin https://github.com/<you>/olfat-backend.git
git push -u origin main
```

`.gitignore` already excludes `.env`, `vendor/`, `node_modules/`, the SQLite
file, backups and the local `.tools` folder — **never commit secrets**.

---

## 2. Create the production database

Pick **one**. Both are supported by the code (no MySQL-only SQL is used).

### Option A — PostgreSQL via Vercel Marketplace (Neon) — simplest

1. Vercel dashboard → your project (create it in step 4 first, or do this after) → **Storage** → **Create Database** → **Neon Postgres** → Free plan.
2. Vercel injects `DATABASE_URL`, `PGHOST`, … into the project automatically.
3. In **Settings → Environment Variables** add:
   - `DB_CONNECTION` = `pgsql`
   - `DB_URL` = the value of `DATABASE_URL` (copy it; must contain `?sslmode=require`).

Neon gives you automated backups + point-in-time restore on the provider side.

### Option B — MySQL 8 (TiDB Cloud Serverless, Aiven, Railway, PlanetScale)

Create a MySQL 8 database, note host/port/db/user/password, and make sure TLS
is required. Set:

- `DB_CONNECTION` = `mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `MYSQL_ATTR_SSL_CA` = `/etc/pki/tls/certs/ca-bundle.crt` (the CA bundle inside the Vercel PHP runtime)

> DBA note (least privilege, Mullins Ch. 14): create a dedicated DB user for the
> app with only `SELECT, INSERT, UPDATE, DELETE` on the app schema. Use a
> separate admin user for migrations from your PC.

---

## 3. Generate the secrets you will need

```powershell
cd D:\OlfatProject
php artisan key:generate --show        # -> base64:....   (APP_KEY)
php -r "echo bin2hex(random_bytes(32));"   # -> 64 hex chars (CRON_SECRET)
```

Pick a strong admin password (≥ 12 chars) for `SEED_ADMIN_PASSWORD`.

---

## 4. Create the Vercel project and set environment variables

### 4a. Create the project

**With GitHub (recommended):** Vercel dashboard → **Add New → Project** →
import your GitHub repo → *Framework Preset*: **Other** → leave build settings
as they are (they are read from `vercel.json`) → **Deploy**. The first deploy
will fail or show errors until the env vars below exist — that is expected.

**Without GitHub (CLI):**

```powershell
cd D:\OlfatProject
vercel          # answer: set up and deploy? Y, scope: you, link to existing? N, name: olfat-backend, directory: ./
```

### 4b. Environment variables

Vercel dashboard → Project → **Settings → Environment Variables** → add each
key for the **Production** environment. The full list with comments is in
`.env.vercel.example`. Minimum required:

| Key | Value |
|---|---|
| `APP_NAME` | `Olfat Backend` |
| `APP_ENV` | `production` |
| `APP_KEY` | from step 3 |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<your-project>.vercel.app` (update later for a custom domain) |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` + DB vars | from step 2 |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `array` |
| `QUEUE_CONNECTION` | `sync` |
| `CRON_SECRET` | from step 3 |
| `SANCTUM_TOKEN_EXPIRATION` | `720` |
| `BCRYPT_ROUNDS` | `12` |

CLI alternative: `vercel env add APP_KEY production` (repeat per key), or
`vercel env pull .env.vercel` to verify what is set (file is git-ignored).

---

## 5. Run migrations and create the first admin (from your PC)

Vercel functions cannot run `php artisan migrate`, so you run it locally
against the production database. Create a temporary `.env.deploy` **outside
the repo** (e.g. `C:\Users\<you>\olfat.env.deploy`) with:

```
APP_ENV=production
APP_KEY=<same APP_KEY as Vercel>
APP_DEBUG=false
APP_URL=https://<your-project>.vercel.app
DB_CONNECTION=pgsql            # or mysql
DB_URL=postgresql://...        # or DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
SEED_ADMIN_NAME=Administrator
SEED_ADMIN_EMAIL=admin@your-domain.com
SEED_ADMIN_PASSWORD=<strong password, 12+ chars>
LOG_CHANNEL=stderr
```

Then:

```powershell
cd D:\OlfatProject
copy .env .env.local.bak                       # keep your local config
copy C:\Users\<you>\olfat.env.deploy .env
php scripts/prepare-production.php              # readiness check must PASS
php artisan migrate --force                     # creates all tables + CHECK constraints
php artisan db:seed --force                     # locations, settings, first admin only
php artisan db:integrity-check                  # should print "passed"
copy .env.local.bak .env                        # restore local config
```

The production seeder only creates reference data and the admin from
`SEED_ADMIN_*` — it never creates the demo `user1@example.com` accounts.

---

## 6. Deploy

- **GitHub flow:** every `git push` to `main` creates a production deployment. Trigger one now: `git commit --allow-empty -m "deploy" && git push`.
- **CLI flow:** `vercel --prod`.

Watch the build log. You should see the `vercel-php` runtime run
`composer install` (it reads `composer.json`/`composer.lock`).

---

## 7. Verify (do all of these)

1. `https://<your-project>.vercel.app/api/health` → `{"status":"ok","checks":{"database":{"ok":true},"cache":{"ok":true}}}`
2. `https://<your-project>.vercel.app/pages/login.html` → login page loads with styles/icons (static assets served by Vercel CDN).
3. Log in with the `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`.
4. Dashboard loads live data; create a small purchase and a sale; check Stock and Profit & Loss update.
5. Admin → `GET /api/v1/audit-logs` (with your bearer token) shows the rows you just created.
6. Response headers include `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`.
7. Vercel dashboard → **Settings → Cron Jobs** shows `/api/cron/schedule` at `0 2 * * *`. Click **Run** once; the function log should show `schedule:run` output.

---

## 8. Custom domain (optional)

Vercel → Project → **Settings → Domains** → add `app.your-domain.com` and follow
the DNS instructions. Then update `APP_URL` to the new https URL and redeploy.

---

## 9. Operating it long term (DBA routine)

| Task | How | Frequency |
|---|---|---|
| Backups | Provider automated backups/PITR **plus** an offline copy: `php artisan db:backup` run from your PC with the production `.env.deploy` (writes `storage/app/private/backups/*.sql.gz`, keeps 30 days). On Vercel the daily cron also runs it, but the function disk is ephemeral, so treat the provider backup + your offline copy as the real ones. | Daily (auto) + weekly offline |
| Integrity audit | `php artisan db:integrity-check` (also runs weekly via scheduler; non-zero exit = problems). | Weekly |
| Schema changes | Add a migration → test locally (`php artisan test`) → commit → run `php artisan migrate --force` against production **before** pushing the code that needs it. | Per change |
| Token hygiene | `sanctum:prune-expired` runs daily via scheduler. | Auto |
| Monitoring | Point an uptime monitor (UptimeRobot, Better Stack, Vercel Checks) at `/api/health`; alert on non-200. | Continuous |
| Logs | Vercel → Project → **Logs** (runtime). `LOG_LEVEL=error` keeps noise low. | As needed |
| Rotate secrets | Change `APP_KEY` only with a planned re-login of all users (tokens stay valid, encrypted values would not). Rotate DB password and `CRON_SECRET` yearly or on staff change. | Yearly |

Restore procedure and recovery drills are documented in
`docs/BACKUP_AND_RECOVERY.md`.

---

## 10. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| 500 on every request, log says `bootstrap/cache` or `storage/framework/views` not writable | `api/index.php` sets `/tmp` paths automatically when `VERCEL` env is present. If you overrode `APP_*_CACHE` vars, point them to `/tmp/...`. |
| Infinite redirect loop to https | `ForceHttps` + proxy headers: `trustProxies('*')` is enabled in `bootstrap/app.php`; make sure `APP_URL` starts with `https://`. |
| `could not find driver` | `DB_CONNECTION` must be `pgsql` or `mysql` (both extensions exist in the runtime). |
| `SQLSTATE[HY000] [2002]` / SSL errors | DB host/port/firewall, or missing `?sslmode=require` (Postgres) / `MYSQL_ATTR_SSL_CA` (MySQL). |
| Login works but dashboard empty | Migrations/seed not run (step 5) or `CACHE_STORE=database` but `cache` table missing → run migrations. |
| Cron returns 401 | `CRON_SECRET` not set on Vercel, or differs from what Vercel sends. |
| Static CSS/JS 404 | Confirm `outputDirectory` is `public` in `vercel.json` and that assets exist under `public/assets`. |
| Function too large | `.vercelignore` and `excludeFiles` already drop tests, tools, docs and node_modules. Keep `composer install --no-dev` semantics by not adding heavy dev packages to `require`. |
