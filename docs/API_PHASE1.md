# Backend API — Phase 1

Base URL: `/api/v1`  
Auth: Bearer token (Laravel Sanctum)  
Response envelope:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

Validation / auth errors use Laravel JSON error format (`message`, `errors`).

---

## Auth

### POST `/api/v1/auth/login`
Auth: none

Request:
- `email` (required)
- `password` (required)
- `device_name` (optional)

Response `data`:
- `token`
- `token_type` (`Bearer`)
- `user`

### GET `/api/v1/auth/me`
Auth: required

### POST `/api/v1/auth/logout`
Auth: required — deletes current token

---

## Users

All routes require auth. No admin role (Master Spec §4).

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/users` | List |
| POST | `/api/v1/users` | Create (`name`, `email`, `phone?`, `password`, `password_confirmation`, `is_active?`) |
| GET | `/api/v1/users/{id}` | Show |
| PUT/PATCH | `/api/v1/users/{id}` | Update |
| DELETE | `/api/v1/users/{id}` | Delete |

---

## Locations

All routes require auth. Seeded: Kabul, Maidan/Wardak, Ghazni.

| Method | Endpoint | Notes |
|--------|----------|--------|
| GET | `/api/v1/locations` | List |
| POST | `/api/v1/locations` | Create (`name`, `code`, `is_active?`) |
| GET | `/api/v1/locations/{id}` | Show |
| PUT/PATCH | `/api/v1/locations/{id}` | Update |
| DELETE | `/api/v1/locations/{id}` | Delete |

---

## Seeded login users

Password for all: `password`

- `user1@example.com`
- `user2@example.com`
- `user3@example.com`

---

## OPEN QUESTIONS (not invented)

1. Exact location/access rules (can one user operate from multiple locations?) — Master Spec §28.7
2. Whether Users/Locations modules are editable by all three equal users (assumed yes for v1 until team decides)
