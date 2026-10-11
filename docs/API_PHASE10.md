# Backend API — Phase 10 (Settings)

Base URL: `/api/v1`  
Auth: Bearer token  

## Endpoints

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/settings` | Optional `?group=` |
| GET | `/api/v1/settings/{key}` | e.g. `stock.low_stock_threshold` |
| PUT | `/api/v1/settings/{key}` | Body: `{ "value": ... }` |
| PUT | `/api/v1/settings` | Bulk: `{ "settings": { "key": value } }` |

Typed values are returned cast (`boolean`, `integer`, `decimal`, `json`, `string`).

## Seeded keys

- `app.name`
- `app.currency_code` (OPEN QUESTION)
- `app.timezone`
- `stock.low_stock_threshold` (used by dashboard warnings)
- `accounting.costing_method` (OPEN QUESTION — empty until team decides)
- `accounting.include_operating_expenses_in_profit`
- `factory.auto_consume_raw_materials`
- `factory.track_factory_material_stock`

Unresolved Master Spec decisions are stored as settings placeholders — do not invent runtime production/P&L behavior from them yet.
