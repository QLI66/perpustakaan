# Collection Types API

## Overview

Collection Types adalah master data yang mengklasifikasikan jenis koleksi perpustakaan (misalnya Buku Pelajaran, Fiksi, Referensi). Setiap `Book` wajib memiliki `collection_type_id`.

**Base URL:** `/api/collection-types`

---

## Permissions

| Role | View | Create | Update | Delete |
|------|------|--------|--------|--------|
| SUPER_ADMIN | ✅ | ✅ | ✅ | ✅ |
| LIBRARY_STAFF | ✅ | ✅ | ✅ | ✅ |
| MEMBER | ✅ | ❌ | ❌ | ❌ |

> **Note:** Public catalog listing is available via `GET /api/catalog/collection-types` (no auth required).

---

## Endpoints

### List Collection Types

```
GET /api/collection-types
Authorization: Bearer {token}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by code or name |
| `is_active` | boolean | Filter by active status |
| `sort` | string | Sort field: `code`, `name`, `sort_order`, `created_at` |
| `direction` | string | `asc` or `desc` (default: `asc`) |
| `per_page` | integer | Items per page (max 50, default 15) |
| `page` | integer | Page number |

**Response:**
```json
{
  "success": true,
  "message": "Collection types retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "code": "TEXTBOOK",
        "name": "Buku Pelajaran",
        "description": "Buku teks pelajaran sekolah",
        "is_active": true,
        "sort_order": 1,
        "created_at": "2026-09-17T13:48:52.000000Z",
        "updated_at": "2026-09-17T13:48:52.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 8,
      "last_page": 1
    }
  }
}
```

---

### Get Single Collection Type

```
GET /api/collection-types/{id}
Authorization: Bearer {token}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Collection type retrieved successfully.",
  "data": {
    "id": 1,
    "code": "TEXTBOOK",
    "name": "Buku Pelajaran",
    "description": "Buku teks pelajaran sekolah",
    "is_active": true,
    "sort_order": 1,
    "created_at": "2026-09-17T13:48:52.000000Z",
    "updated_at": "2026-09-17T13:48:52.000000Z"
  }
}
```

**Errors:** `401` Unauthenticated, `403` Unauthorized, `404` Not found

---

### Create Collection Type

```
POST /api/collection-types
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "code": "NOVEL",
  "name": "Novel",
  "description": "Koleksi novel",
  "is_active": true,
  "sort_order": 9
}
```

| Field | Required | Rules |
|-------|----------|-------|
| `code` | ✅ | string, max:50, unique |
| `name` | ✅ | string, max:100 |
| `description` | ❌ | string, nullable |
| `is_active` | ❌ | boolean (default: `true`) |
| `sort_order` | ❌ | integer >= 0 (default: `0`) |

**Response:** `201 Created`

**Errors:** `401`, `403`, `422` Validation failed

---

### Update Collection Type

```
PATCH /api/collection-types/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** All fields optional (partial update supported).
```json
{
  "name": "Updated Name",
  "is_active": false
}
```

> **Note:** `code` must remain unique. When updating `code`, the current record's own code is excluded from the unique check.

**Response:** `200 OK`

**Errors:** `401`, `403`, `404`, `422`

---

### Delete Collection Type

```
DELETE /api/collection-types/{id}
Authorization: Bearer {token}
```

**Business Rule:** A collection type **cannot be deleted** if it is referenced by any book. Use `PATCH` to set `is_active: false` to deactivate it instead.

**Response:** `200 OK`

**Errors:**
- `401` Unauthenticated
- `403` Unauthorized
- `404` Not found
- `409` Conflict — collection type is used by one or more books

---

## Seeded Data

The following collection types are seeded by default:

| Code | Name | Sort Order |
|------|------|------------|
| TEXTBOOK | Buku Pelajaran | 1 |
| FICTION | Fiksi | 2 |
| NON_FICTION | Non-Fiksi | 3 |
| REFERENCE | Referensi | 4 |
| MAGAZINE | Majalah | 5 |
| NEWSPAPER | Surat Kabar | 6 |
| COMIC | Komik | 7 |
| SPECIAL | Koleksi Khusus | 8 |

---

## Public Catalog Endpoint

For public (unauthenticated) access to active collection types, use:

```
GET /api/catalog/collection-types
```

This endpoint only returns active records and does not require authentication.
