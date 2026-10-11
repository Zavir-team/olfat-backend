# PERFORMANCE AND LOGIN DIAGNOSIS

**Project:** Olfat Backend / MBMS (Mobile Business Management System)  
**Date:** 2026-09-30  
**Scope:** Diagnosis only — no application code was modified for this report.  
**Method:** Static inspection of frontend (`public/assets/js`, `public/pages`, `public/index.html`), Laravel routes/controllers/services, migrations, plus live HTTP timing against `php artisan serve` on `http://127.0.0.1:8000`.

---

## Measurement context (evidence)

Database contents at measurement time:

| Table / model        | Count |
|----------------------|------:|
| Products             |     9 |
| Product variants     |    28 |
| Stock balances       |     0 |
| Customers            |     0 |
| Workers              |     0 |
| Worker payments      |     0 |

Runtime:

- PHP via project `.tools/php/php.exe`
- Server: `php artisan serve --host=127.0.0.1 --port=8000` (single-threaded built-in server)
- DB: SQLite (`DB_CONNECTION=sqlite`)
- `APP_DEBUG=true`
- Cache driver: `file`

**Conclusion from data size:** Slow menu navigation cannot be explained by “large database.” The bottleneck is request architecture + blocking UI bootstrap + server serialization, with secondary per-request Laravel/SQLite overhead even on empty tables.

---

## Architecture note (required to understand navigation cost)

This is **not** a React SPA.

- UI lives as separate HTML documents under `public/` (`index.html`, `pages/tea.html`, `pages/customers.html`, etc.).
- Sidebar links in `public/assets/js/nav.js` are normal `<a href="...">` full-page navigations.
- Each menu click reloads HTML + all JS (`config.js`, `api.js`, `bridge.js`, `app.js`, page script, …).
- In-memory caches inside `public/assets/js/bridge.js` (`cache`, `stockByVariant`, `MBMS_MOCK` built by prefetch) are **destroyed on every menu change**.

Frontend bootstrap order on every authenticated page:

1. `DOMContentLoaded` → `public/assets/js/app.js` `boot()`
2. Auth gate (redirect to login if no token)
3. **`MBMS_API.prefetch()` must finish**
4. Only then `init()` → `MBMS_LAYOUT.mount()` + page `MBMS_PAGE.onReady()`

Evidence — `public/assets/js/app.js`:

```javascript
var pending = (!loginPage && global.MBMS_API && global.MBMS_API.prefetch)
  ? global.MBMS_API.prefetch()
  : Promise.resolve();
pending.then(init);
```

---

# 1. Slow menu navigation

## 1.1 Root cause(s)

### Cause A — Blocking global prefetch on every page (PRIMARY)

`app.js` does not mount layout or run page loaders until `prefetch()` resolves. The user perceives “menu takes forever” because the shell and module UI are gated behind a network barrier that is unrelated to the clicked menu.

### Cause B — Prefetch always fires seven API requests on every menu

`public/assets/js/bridge.js` defines `MBMS_API.prefetch()` as `Promise.all` of:

1. `GET locations` (params `per_page: 100`)
2. `GET products` (params `per_page: 100`)
3. `GET customers` (params `per_page: 100`)
4. `GET suppliers` (params `per_page: 100`)
5. `GET stock/balances` (params `per_page: 100`)
6. `GET external-parties` (params `per_page: 100`)
7. `GET auth/me`

These run for Dashboard, Tea, Rice, Detergents, Customers, Workers, Reports, and essentially every authenticated HTML page.

Evidence — `public/assets/js/bridge.js` (`MBMS_API.prefetch`):

```javascript
return Promise.all([
  rawRequest('GET', 'locations', { params: { per_page: 100 } }),
  rawRequest('GET', 'products', { params: { per_page: 100 } }),
  rawRequest('GET', 'customers', { params: { per_page: 100 } }),
  rawRequest('GET', 'suppliers', { params: { per_page: 100 } }),
  rawRequest('GET', 'stock/balances', { params: { per_page: 100 } }),
  rawRequest('GET', 'external-parties', { params: { per_page: 100 } }),
  rawRequest('GET', 'auth/me')
]).then(function (responses) { /* fill cache + MBMS_MOCK catalogs */ });
```

### Cause C — Full page reload destroys cache

Prefetch fills:

- `cache.locations`, `cache.products`, `cache.customers`, `cache.suppliers`, `cache.parties`
- `stockByVariant` via `indexStock(...)`
- `MBMS_MOCK.tea` / `rice` / `detergents` / customers / suppliers / locations

Because navigation is MPA full reload, none of that survives to the next menu. Prefetch therefore repeats on every click.

### Cause D — `php artisan serve` serializes “parallel” prefetch (AMPLIFIER)

Prefetch uses `Promise.all`, but PHP’s built-in server processes one request at a time. Browser-parallel requests become a queue.

**Measured wall time** for the seven prefetch endpoints fired together:

- `PARALLEL_WALL` ≈ **37,144 ms**

Individual queued request times during that parallel burst reached ~15–21 seconds for several endpoints.

### Cause E — Duplicate page-level fetches after prefetch

After the blocking prefetch, page scripts still call their own APIs:

- Catalog pages call `tea` / `rice` / `detergents`, which `bridge.js` remaps to `products?module=...`
- Contacts pages call `customers` / `suppliers` again
- Dashboard calls `dashboard` after waiting for prefetch
- Workers calls `workers`, which then chains another request (Cause F)

### Cause F — Extra chained request on Workers list

In `bridge.js` `send()`, after a successful `GET workers`:

```javascript
if (planned.original === 'workers' && planned.method === 'GET') {
  return rawRequest('GET', 'worker-payments', { params: { per_page: 200 } }).then(...);
}
```

So opening Workers always adds `GET /api/v1/worker-payments?per_page=200` even for a simple list view.

### Cause G — Some empty endpoints are still slow alone (SECONDARY)

Not caused by row volume. Measured sequentially with a valid Sanctum token:

| Endpoint | Time | Rows in DB |
|----------|------|------------|
| `GET /api/v1/locations` | 547 ms | 3 (seeded locations) |
| `GET /api/v1/products?per_page=100` | 850 ms | 9 |
| `GET /api/v1/customers?per_page=100` | 507 ms | 0 |
| `GET /api/v1/suppliers?per_page=100` | 432 ms | 0 |
| **`GET /api/v1/stock/balances?per_page=100`** | **3774 ms** | **0** |
| `GET /api/v1/external-parties?per_page=100` | 880 ms | small/seeded |
| `GET /api/v1/auth/me` | 477 ms | — |
| **Prefetch sequential sum** | **≈ 7.5 s** | — |
| `GET /api/v1/products?module=tea&per_page=200` | 498 ms | tea subset |
| `GET /api/v1/products?module=rice&per_page=200` | 370 ms | rice subset |
| `GET /api/v1/workers?per_page=100` | 1928 ms | 0 |
| `GET /api/v1/worker-payments?per_page=200` | 2787 ms | 0 |
| `GET /api/v1/dashboard` | 1084 ms | aggregates |

Likely contributors to Cause G (not “big data”):

- Fresh Laravel bootstrap per HTTP request
- Sanctum bearer auth on every call
- SQLite file I/O (migrations on this machine were also multi-second per migration)
- `APP_DEBUG=true`
- File cache driver
- Single-threaded `artisan serve`

Cause G makes Causes A/B catastrophic; removing/blocking less prefetch still yields large UX gains even before deeper backend profiling.

---

## 1.2 Exact files involved

### Frontend

| File | Responsibility for slowness |
|------|-----------------------------|
| `public/assets/js/app.js` | Blocks `init()` on `prefetch()` |
| `public/assets/js/bridge.js` | Prefetch of 7 endpoints; workers→payments chain; path remaps (`tea`→`products`, etc.); in-memory-only cache |
| `public/assets/js/api.js` | Central `fetch` wrapper used by all API calls |
| `public/assets/js/nav.js` | Menu catalog with full-page `href`s (MPA) |
| `public/assets/js/layout.js` | Shell mount only after `init()` (after prefetch) |
| `public/assets/js/records.js` | `fetchOrMock()` always hits API; no “use fresh prefetch cache” short-circuit |
| `public/assets/js/pages/catalog-page.js` | Tea/Rice/Detergents loader; refetches catalog endpoint after prefetch |
| `public/assets/js/pages/contacts-page.js` | Customers/Suppliers loader; refetches list after prefetch |
| `public/assets/js/pages/workers-page.js` | Workers loader; triggers payments chain via bridge |
| `public/assets/js/pages/dashboard.js` | Dashboard loader; starts only after prefetch gate |
| `public/assets/js/pages/reports-page.js` | Report loads after same prefetch gate; some report kinds use dual requests (`also`) |
| `public/index.html`, `public/pages/*.html` | Separate documents → full reload per menu |

### Backend

| File | Role |
|------|------|
| `routes/api.php` | `/api/v1/*` routes; authenticated group `auth:sanctum` + `active` |
| `app/Http/Controllers/Api/V1/LocationController.php` | locations index |
| `app/Http/Controllers/Api/V1/ProductController.php` | products index (`with('variants')`) |
| `app/Http/Controllers/Api/V1/CustomerController.php` | customers index |
| `app/Http/Controllers/Api/V1/SupplierController.php` | suppliers index |
| `app/Http/Controllers/Api/V1/StockController.php` | stock balances / movements |
| `app/Http/Controllers/Api/V1/ExternalPartyController.php` | external parties index |
| `app/Http/Controllers/Api/V1/AuthController.php` | `auth/me`, login, logout |
| `app/Http/Controllers/Api/V1/WorkerController.php` | workers index |
| `app/Http/Controllers/Api/V1/WorkerPaymentController.php` | worker payments index |
| `app/Http/Controllers/Api/V1/DashboardController.php` | dashboard show |
| `app/Services/Report/ReportService.php` | `dashboard()` aggregates + 45s cache |
| `app/Http/Middleware/EnsureUserIsActive.php` | `active` middleware on API group |
| `bootstrap/app.php` | middleware aliases; `throttleApi` |

---

## 1.3 Frontend routing / navigation flow

1. User clicks a sidebar/bottom-nav link rendered by `layout.js` from `nav.js` groups (e.g. Tea → `pages/tea.html`).
2. Browser performs full navigation to that HTML file.
3. Page sets `window.MBMS_BASE_PATH` (`'.'` on dashboard, `'..'` under `pages/`).
4. Deferred scripts load; `app.js` `ready(boot)` runs.
5. If no auth token (`MBMS_CONFIG.getAuthToken()` / localStorage `MBMS_AUTH_TOKEN`) → redirect to `pages/login.html`.
6. If authenticated → **await `MBMS_API.prefetch()`** (7 API calls).
7. Prefetch fills `cache` + `MBMS_MOCK` catalogs.
8. `init()` mounts layout and calls page `onReady`.
9. Page-specific `load()` / `fetchOrMock` / `MBMS_API.get` runs (more API calls).

There is **no client-side router**. “Slow navigation” = slow full document bootstrap + network gate.

---

## 1.4 API requests made when opening pages

### Global (every authenticated page, before UI mounts)

Resolved against API base `/api/v1` (`public/assets/js/config.js`):

1. `GET /api/v1/locations`
2. `GET /api/v1/products?per_page=100`
3. `GET /api/v1/customers?per_page=100`
4. `GET /api/v1/suppliers?per_page=100`
5. `GET /api/v1/stock/balances?per_page=100`
6. `GET /api/v1/external-parties?per_page=100`
7. `GET /api/v1/auth/me`

### Page-specific (after prefetch)

| Page | Frontend call | Bridged backend path |
|------|---------------|----------------------|
| Dashboard | `MBMS_API.get('dashboard')` | `GET /api/v1/dashboard` |
| Tea | `fetchOrMock('tea')` | `GET /api/v1/products?module=tea&per_page=200` |
| Rice | `fetchOrMock('rice')` | `GET /api/v1/products?module=rice&per_page=200` |
| Detergents | `fetchOrMock('detergents')` | `GET /api/v1/products?module=detergent&per_page=200` |
| Customers | `fetchOrMock('customers')` | `GET /api/v1/customers?per_page=100` |
| Suppliers | `fetchOrMock('suppliers')` | `GET /api/v1/suppliers?per_page=100` |
| Workers | `fetchOrMock('workers')` then chain | `GET /api/v1/workers` + `GET /api/v1/worker-payments?per_page=200` |
| Reports | `reports/...` via bridge `reportTarget` | varies (`sales`, `purchases`, `stock/balances`, `worker-payments`, etc.); financial payments may also hit a second path via `options.also` |

Bridge remap evidence for catalog — `bridge.js` `plan()`:

```javascript
} else if (nextPath === 'tea' || nextPath === 'rice' || nextPath === 'detergents' || ...) {
  if (verb === 'GET') {
    params.module = moduleOf(nextPath);
    params.per_page = params.per_page || 200;
    nextPath = 'products';
  }
}
```

---

## 1.5 Duplicate or unnecessary API requests

| Request | When unnecessary |
|---------|------------------|
| Prefetch `products` (all modules) | Opening Customers, Workers, Payments, many non-catalog pages |
| Prefetch `customers` | Opening Tea/Rice/Detergents/Stock/Reports (unless forms need names — and forms can load on demand) |
| Prefetch `suppliers` | Same |
| Prefetch `stock/balances` | Opening pages that do not display stock; also the slowest empty endpoint |
| Prefetch `external-parties` | Outside External Debts flows |
| Prefetch `auth/me` every navigation | User id already storable after login; repeated every HTML load |
| Tea/Rice/Detergents second `products?module=...` | Prefetch already loaded all products and built `MBMS_MOCK.tea/rice/detergents` |
| Customers second `customers` | Prefetch already loaded customers into `MBMS_MOCK.customers` |
| Workers `worker-payments?per_page=200` on list | Not required to render the workers table; monthly/detail can load later |

---

## 1.6 Slow endpoints

Ranked by measured sequential latency (empty/tiny DB):

1. `GET /api/v1/stock/balances?per_page=100` — **3774 ms** (0 rows) — worst prefetch member
2. `GET /api/v1/worker-payments?per_page=200` — **2787 ms** (0 rows)
3. `GET /api/v1/workers?per_page=100` — **1928 ms** (0 rows)
4. Prefetch parallel wall on `artisan serve` — **≈ 37 s**
5. Prefetch sequential sum — **≈ 7.5 s** before any page-specific work

---

## 1.7 Controllers / services involved

| HTTP route | Controller method | Service |
|------------|-------------------|---------|
| `GET /api/v1/locations` | `LocationController@index` | — |
| `GET /api/v1/products` | `ProductController@index` | — |
| `GET /api/v1/customers` | `CustomerController@index` | — |
| `GET /api/v1/suppliers` | `SupplierController@index` | — |
| `GET /api/v1/stock/balances` | `StockController@balances` | — |
| `GET /api/v1/external-parties` | `ExternalPartyController@index` | — |
| `GET /api/v1/auth/me` | `AuthController@me` | — |
| `GET /api/v1/workers` | `WorkerController@index` | — |
| `GET /api/v1/worker-payments` | `WorkerPaymentController@index` | — |
| `GET /api/v1/dashboard` | `DashboardController@show` | `ReportService::dashboard()` |

All of the above (except login) sit behind `Route::middleware(['auth:sanctum', 'active'])` in `routes/api.php`.

---

## 1.8 Database queries involved

### Prefetch / list endpoints

- `Location::query()->orderBy('name')->get()`  
  (`LocationController@index` — note: ignores `per_page`; returns full collection)
- `Product::query()->with('variants')->orderBy('name')` + optional `where('module', ...)` + `paginate(...)`
- `Customer::query()->orderBy('name')->paginate(...)`
- `Supplier::query()->orderBy('name')->paginate(...)`
- `StockBalance::query()->with(['variant.product', 'location'])->orderBy('id')->paginate(...)`  
  optional `whereHas('variant.product', module filter)` when `module` present
- External parties paginated index
- Sanctum personal access token resolution + user for `auth/me`
- `Worker::...->paginate(...)`
- `WorkerPayment::query()->with(['worker', 'location', 'user'])->paginate(...)`

### Dashboard (`ReportService::buildDashboard`)

Wrapped in `Cache::remember('mbms.dashboard.'.($locationId ?? 'all'), 45, ...)`.

Queries inside builder include:

- Sales today aggregate (`COUNT` / `SUM(total_amount)` between day bounds)
- Purchases today aggregate
- Low stock: `StockBalance::with(['variant.product','location'])->where('quantity','<=',threshold)->limit(10)`
- Recent sales (limit 5) with `customer`, `location`
- Recent purchases (limit 5) with `supplier`, `location`
- `Customer::sum('balance')`
- Open external debts sums by direction
- `Supplier::sum('balance')`
- Worker payments today sum
- Stock quantity sum

Dashboard is legitimate page work, but it currently cannot start until the unrelated seven-request prefetch finishes.

---

## 1.9 N+1 queries

**No meaningful N+1 problem was identified as the cause of current slow menu navigation.**

Evidence of eager loading already present:

- Products: `with('variants')`
- Stock balances: `with(['variant.product', 'location'])`
- Worker payments: `with(['worker', 'location', 'user'])`
- Sales/purchases list controllers also eager-load relations when those pages are opened

With 0–9 rows, even a hypothetical N+1 would not explain multi-second waits. The dominant cost is **number of blocking HTTP round-trips per navigation** + **server request queuing** + **per-request framework overhead**.

---

## 1.10 Missing or inefficient indexes

**No missing index was identified as the cause of the current empty-DB slowness.**

Existing relevant indexes include:

- `database/migrations/2026_09_28_100002_add_performance_indexes.php` (balances, location/quantity, sales payment_type/sold_at, etc.)
- Products: `module`, `[module, name]`
- Customers/suppliers: `name`, `phone`
- Stock movements, sales, purchases, worker payments date/composite indexes in their create migrations

**Recommendation for this round:** do **not** add indexes. They will not fix the blocking prefetch architecture and are not implicated by the measured empty-table timings.

---

## 1.11 Unnecessary relationships / data loading

1. **Global prefetch of stock balances with `variant.product` + `location`** on every menu, including pages that never show stock.
2. **Global prefetch of all products + all variants** when opening non-catalog pages.
3. **Global prefetch of customers, suppliers, external parties** for menus that do not need them immediately.
4. **Workers list always loading up to 200 worker payments with three relations** via bridge chain.
5. Prefetch builds full Tea/Rice/Detergent mock catalogs from all products, then catalog pages **fetch products again** instead of using that data.

---

## 1.12 Frontend re-renders / loading problems

Not React — no virtual DOM re-render storms.

Observed loading problems:

1. **Hard gate:** UI bootstrap (`layout.mount`, `onReady`) waits for prefetch (`app.js`).
2. **Double loading indicators in practice:** long invisible wait during prefetch, then page `MBMS_UI.showLoading(...)` during the page’s own request.
3. **Catalog search/filter:** `debounce(load, 250)` in `catalog-page.js` / contacts triggers full `load()` → another API GET while typing/filtering.
4. **CDN Bootstrap** loaded every page (`cdn.jsdelivr.net`) — minor compared to API gate; noted as secondary asset cost only.

---

## 1.13 Other performance bottlenecks

1. **MPA full reload** of many JS files on every menu.
2. **`php artisan serve` single-threadedness** turning parallel prefetch into a long queue (~37 s measured).
3. **SQLite + slow filesystem behavior** on this environment (also seen during migrate/seed durations earlier).
4. **`APP_DEBUG=true`** increases framework overhead per request.
5. Reports financial “payments” path can issue a second request via `planned.options.also` (`customer-payments` + `supplier-payments`) — relevant when opening that report kind, on top of prefetch.

---

## 1.14 Evidence summary from actual project code

| Claim | Evidence location |
|-------|-------------------|
| Prefetch blocks init | `public/assets/js/app.js` `boot()` → `pending.then(init)` |
| Prefetch = 7 GETs | `public/assets/js/bridge.js` `MBMS_API.prefetch` |
| Workers chains payments | `public/assets/js/bridge.js` `send()` after `present` |
| Tea→products remap | `public/assets/js/bridge.js` `plan()` |
| Catalog refetches after mount | `public/assets/js/pages/catalog-page.js` `load()` → `fetchOrMock(cfg.endpoint)` |
| Customers refetch | `public/assets/js/pages/contacts-page.js` `load()` |
| Dashboard after gate | `public/assets/js/pages/dashboard.js` `onReady` → `loadDashboard()` |
| API routes | `routes/api.php` |
| Stock balances query | `app/Http/Controllers/Api/V1/StockController.php` `balances()` |
| Dashboard service | `app/Services/Report/ReportService.php` `dashboard()` / `buildDashboard()` |
| Live timings | Measured 2026-09-30 against running server (see tables above) |

---

# 2. At least three slow menus / pages (detailed)

## 2.1 MENU: Tea

| Item | Detail |
|------|--------|
| **User action** | Click **Tea** in sidebar (`nav.js` item `tea` → `pages/tea.html`) |
| **Frontend file / “component”** | `public/pages/tea.html` + `public/assets/js/pages/catalog-page.js` (`data-catalog="tea"`) + shared `app.js` / `bridge.js` / `layout.js` |
| **API request(s)** | (1–7) Prefetch seven endpoints; (8) `GET /api/v1/products?module=tea&per_page=200` |
| **Backend route(s)** | Prefetch routes listed in §1.4; page: `GET /api/v1/products` (`routes/api.php` `apiResource('products')`) |
| **Controller / service** | Prefetch controllers in §1.7; page: `ProductController@index` |
| **Database query** | Prefetch queries in §1.8; page: `Product::with('variants')->where('module','tea')->orderBy('name')->paginate(200)` |
| **Identified bottleneck** | Blocking 7-request prefetch (includes unused `stock/balances` ~3.7 s) before Tea UI mounts; then duplicate products fetch; MPA kills cache so this repeats when leaving/returning |
| **Recommended fix** | Do not block Tea on full prefetch; reuse session-cached catalog or skip refetch when `MBMS_MOCK.tea` fresh; stop prefetching stock/customers/suppliers/parties on Tea open |

**Request count:** ≈ 8  
**Duplicates:** products (all + tea-filtered)

---

## 2.2 MENU: Customers

| Item | Detail |
|------|--------|
| **User action** | Click **Customers** → `pages/customers.html` |
| **Frontend file / “component”** | `public/pages/customers.html` + `public/assets/js/pages/contacts-page.js` (`data-contact="customers"`) + `app.js` / `bridge.js` |
| **API request(s)** | Prefetch ×7; then `GET /api/v1/customers?per_page=100` |
| **Backend route** | `GET /api/v1/customers` |
| **Controller / service** | `CustomerController@index` (plus prefetch controllers) |
| **Database query** | `Customer::orderBy('name')->paginate(...)` (empty table at measurement); plus heavy unrelated prefetch queries especially `StockBalance::with(...)->paginate` |
| **Identified bottleneck** | Customers list itself is light; user still waits for global prefetch gate (products, stock balances, external parties, etc.) then a duplicate customers GET |
| **Recommended fix** | Mount page without waiting for non-customer prefetch; if customers already in session cache from a prior page, render from cache or skip duplicate GET; shrink global prefetch |

**Request count:** ≈ 8  
**Duplicates:** customers

---

## 2.3 MENU: Workers

| Item | Detail |
|------|--------|
| **User action** | Click **Workers** → `pages/workers.html` |
| **Frontend file / “component”** | `public/pages/workers.html` + `public/assets/js/pages/workers-page.js` + `bridge.js` chain |
| **API request(s)** | Prefetch ×7; `GET /api/v1/workers`; chained `GET /api/v1/worker-payments?per_page=200` |
| **Backend route** | `GET /api/v1/workers`, `GET /api/v1/worker-payments` |
| **Controller / service** | `WorkerController@index`, `WorkerPaymentController@index` |
| **Database query** | Workers paginate (0 rows); WorkerPayment `with(['worker','location','user'])->paginate` (0 rows) — still **1928 ms** + **2787 ms** measured |
| **Identified bottleneck** | Global prefetch gate + automatic payments preload on every workers list open + slow empty endpoints under current runtime |
| **Recommended fix** | Remove automatic payments chain from list GET; load payments when opening a worker detail or monthly report section; unblock UI from full prefetch |

**Request count:** ≈ 9  

---

## 2.4 (Additional) MENU: Dashboard

| Item | Detail |
|------|--------|
| **User action** | Open app root / click Dashboard → `index.html` |
| **Frontend file** | `public/index.html` + `public/assets/js/pages/dashboard.js` |
| **API request(s)** | Prefetch ×7; then `GET /api/v1/dashboard` |
| **Backend route** | `GET /api/v1/dashboard` |
| **Controller / service** | `DashboardController@show` → `ReportService::dashboard()` |
| **Database query** | Multiple aggregates in `buildDashboard()` (cached 45 seconds) |
| **Identified bottleneck** | Dashboard widgets cannot start until unrelated prefetch completes (~7.5 s sequential / ~37 s queued parallel) |
| **Recommended fix** | Same as Cause A/B: mount and call `loadDashboard()` without waiting for stock/customers/products/parties prefetch |

---

# 3. Login / Dashboard behavior

## 3.1 Current authentication flow

1. Login UI: `public/pages/login.html` (`data-layout="auth"`).
2. Submit handled by `public/assets/js/pages/login.js` via `MBMS_UI.bindForm`.
3. `MBMS_AUTH.login()` in `public/assets/js/auth.js` posts to `auth/login` → `POST /api/v1/auth/login` (`AuthController@login`).
4. Token extracted (`token` / `access_token` / `plainTextToken`) and stored in localStorage key `MBMS_AUTH_TOKEN` via `MBMS_CONFIG.setAuthToken` (`public/assets/js/config.js`).
5. Optional user id stored in `MBMS_USER_ID`.
6. On success, `goToDashboard()` navigates to `index.html`.
7. Subsequent API calls send `Authorization: Bearer {token}` (`api.js`).
8. API group requires `auth:sanctum` + `active` (`EnsureUserIsActive`).
9. On 401, `bridge.js` `redirectLogin()` clears token and sends user to `pages/login.html`.
10. Logout: `POST auth/logout` then clear token → login page (`auth.js`).

**Important:** HTML pages are **not** protected by Laravel session middleware. Protection is:

- Client gate in `app.js` (token presence)
- Server gate on `/api/v1/*` (Sanctum)

## 3.2 Current initial route / page

`routes/web.php`:

```php
Route::get('/', function () {
    return redirect('/index.html');
});
```

So application entry **`/` always redirects to Dashboard HTML** (`public/index.html`), regardless of authentication.

## 3.3 Why another page appears before Dashboard (and before Login)

### Unauthenticated app open

1. User opens `/` or `/index.html`.
2. **Dashboard document loads first** (title “Dashboard · MBMS”, dashboard markup).
3. Scripts eventually run; `app.js` sees no token → redirects to `pages/login.html`.

So the page that appears first is **Dashboard**, not Login. Login is a **late client-side redirect**.

### After successful login

Code already navigates to Dashboard (`login.js` `goToDashboard()` → `index.html`).  

Perceived “not opening Dashboard directly / stuck” is largely Problem 1: Dashboard URL loads, but content/bootstrap waits on seven-request prefetch before layout/widgets.

### Authenticated user opens login page

`app.js`: if `data-layout="auth"` and signed in → redirect to `index.html` (Dashboard). Correct.

## 3.4 Exact files responsible

| File | Behavior |
|------|----------|
| `routes/web.php` | `/` → `/index.html` always |
| `public/index.html` | Dashboard shell; **no early auth redirect in `<head>`** |
| `public/assets/js/app.js` | After JS: no token → login; token on login page → dashboard; prefetch gate |
| `public/assets/js/pages/login.js` | Success → `index.html` |
| `public/assets/js/auth.js` | login/logout/isSignedIn |
| `public/assets/js/config.js` | token storage keys |
| `public/assets/js/bridge.js` | 401 → login redirect |
| `app/Http/Controllers/Api/V1/AuthController.php` | API login/me/logout |

## 3.5 How authenticated users are currently redirected

| Situation | Current behavior |
|-----------|------------------|
| Login success | → `index.html` (Dashboard) via `login.js` |
| Open `login.html` while token exists | → `index.html` via `app.js` |
| Open `/` while token exists | → `/index.html` (Dashboard) via `web.php`; then prefetch+dashboard load |
| Refresh while on Tea/Customers/etc. | **Stays on that module page** (MPA). Does **not** force Dashboard |
| API 401 | → `pages/login.html` via `bridge.js` |

## 3.6 How unauthenticated users are handled

| Situation | Current behavior |
|-----------|------------------|
| Open `/` or `/index.html` without token | Dashboard HTML loads first → JS redirect to `pages/login.html` |
| Open any `pages/*.html` module without token | Module HTML may load briefly → `app.js` redirect to login |
| Call API without token | Sanctum 401 → JSON error; bridge may redirect to login if request went through bridged client |
| Login with invalid credentials | Stay on login; show server error (`login.js` `onError`) |

## 3.7 Recommended change (auth navigation goals)

Desired:

- After successful login → **Dashboard**
- If already authenticated and the **application is opened** (entry `/` or `/index.html`) → **Dashboard**
- If not authenticated → **Login** (without Dashboard flash)
- No redirect loops
- Do not bypass Sanctum on API; do not expose API data to guests

Recommended approach (frontend-entry focused, minimal):

1. **`public/index.html`:** add a tiny synchronous inline script in `<head>`: if `localStorage.MBMS_AUTH_TOKEN` missing → `location.replace('pages/login.html')` before paint.  
   - Fixes guest “Dashboard appears first.”
2. **Keep** `login.js` → `index.html` after success.  
   - Already satisfies post-login → Dashboard.
3. **Keep** `app.js` guards (login↔app) as secondary safety net.  
   - Prevents loops: login page with token → dashboard; app page without token → login; early head script only on dashboard entry.
4. **Do not** force every module refresh to Dashboard (that would break deep links / mid-work refresh). Interpret “opens/refreshes the application” as **app entry** (`/` / `index.html`), which already targets Dashboard when authenticated.
5. Pair with Problem 1 prefetch fix so authenticated Dashboard becomes usable quickly after login/open.

Loop avoidance rules:

- Early redirect only guest → login from `index.html`.
- `app.js` on login page only redirects to dashboard when token **exists**.
- Logout clears token then goes to login (already).
- Never redirect login → dashboard → login without token state change.

---

# 4. Recommended fixes

Only fixes justified by this diagnosis. No unrelated refactors, no SPA rewrite, no framework upgrades, no schema/index changes in this round.

## Fix 1 — Stop blocking UI on full prefetch

**What:** In `public/assets/js/app.js`, call `init()` immediately for authenticated pages; do not `await` the full seven-request prefetch before mounting layout / `onReady`.

**Why it solves the problem:** Removes the primary gate that makes every menu wait for locations+products+customers+suppliers+stock+parties+me before showing the clicked page.

**Files:** `public/assets/js/app.js`

## Fix 2 — Shrink prefetch / load reference data on demand

**What:** In `public/assets/js/bridge.js`, reduce `prefetch()` to essentials needed for chrome (at most `auth/me` + `locations`), or make heavy lists (`products`, `customers`, `suppliers`, `stock/balances`, `external-parties`) on-demand per page.

**Why:** Eliminates unnecessary API work on every navigation; especially removes the slow empty `stock/balances` call from unrelated menus.

**Files:** `public/assets/js/bridge.js`

## Fix 3 — Persist reference cache across MPA navigations

**What:** Store prefetch/reference payloads in `sessionStorage` with a short TTL; hydrate `cache` / `MBMS_MOCK` on boot if fresh.

**Why:** Full page reloads currently discard memory cache, forcing repeat fetches. Session cache preserves correctness within the tab session without inventing a new architecture.

**Files:** `public/assets/js/bridge.js` (primary)

## Fix 4 — Skip duplicate page GETs when cache is fresh

**What:** Allow catalog/contacts (and `records.js` `fetchOrMock`) to use fresh cached module data instead of always hitting the network on open.

**Why:** Tea/Customers currently duplicate work already done by prefetch (or prior pages).

**Files:**  
- `public/assets/js/records.js`  
- `public/assets/js/pages/catalog-page.js` (only if needed)  
- `public/assets/js/pages/contacts-page.js` (only if needed)  
- coordination in `public/assets/js/bridge.js`

## Fix 5 — Remove automatic worker-payments chain on workers list

**What:** Stop `bridge.js` `send()` from always requesting `worker-payments?per_page=200` after `GET workers`. Load payments when worker detail / monthly section needs them (`workers-page.js`).

**Why:** Removes a multi-second extra request from every Workers menu open; list view does not need full payment history upfront.

**Files:**  
- `public/assets/js/bridge.js`  
- `public/assets/js/pages/workers-page.js`

## Fix 6 — Early guest redirect on Dashboard entry

**What:** Inline early auth check in `public/index.html` `<head>` → login if no token.

**Why:** Stops Dashboard from being the first painted page for unauthenticated users; aligns entry with Login → (auth) → Dashboard.

**Files:** `public/index.html`

## Fix 7 — Keep existing post-login Dashboard navigation

**What:** Leave `login.js` `goToDashboard()` → `index.html` as-is (verify only).

**Why:** Post-login destination is already correct; Problem 1 fixes make it feel instantaneous.

**Files:** `public/assets/js/pages/login.js` (likely no code change)

---

## Exact file modification list (for a future implementation pass)

| File | Intended change |
|------|-----------------|
| `public/assets/js/app.js` | Do not block `init()` on full prefetch |
| `public/assets/js/bridge.js` | Shrink prefetch; sessionStorage TTL cache; remove workers→payments auto-chain; support cache reuse |
| `public/assets/js/records.js` | Prefer fresh cache in `fetchOrMock` when safe |
| `public/assets/js/pages/catalog-page.js` | Use cache on open when fresh (if still needed after records/bridge changes) |
| `public/assets/js/pages/contacts-page.js` | Same for customers/suppliers |
| `public/assets/js/pages/workers-page.js` | Explicitly load payments for detail/monthly after chain removal |
| `public/index.html` | Early unauthenticated redirect to `pages/login.html` |

**Out of scope for the recommended fix set (unless separately approved):**

- Laravel controller rewrites
- New DB indexes / migrations
- Changing `ReportService` query shape
- Replacing MPA with SPA
- Package/framework upgrades
- UI redesign

---

## Status

- Diagnosis complete and recorded in this file.  
- **No application code was changed** as part of writing this report.  
- Implementation must wait for explicit approval.
