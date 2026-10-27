# Loan API

The Loan API provides **private** management of library loan transactions. Access is strictly controlled based on roles.

## Authentication

All endpoints require a valid Sanctum Bearer token:
```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | `GET /loans` | `GET /loans/{id}` | `POST /loans` | `GET /me/loans` |
|---|---|---|---|---|
| `SUPER_ADMIN` | ✅ | ✅ (any) | ✅ | ✅ (if has Member) |
| `LIBRARY_STAFF` | ✅ | ✅ (any) | ✅ | ✅ (if has Member) |
| `MEMBER` | ❌ 403 | ✅ (own only) | ✅ (self only) | ✅ |
| Guest | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 |

---

## Endpoints

### 1. List All Loans
`GET /api/loans` — **Staff/Admin only**

**Query Parameters:**
| Param | Type | Description |
|---|---|---|
| `search` | string | Searches `loan_number`, `member.member_number`, `member.name` (case-insensitive) |
| `member_id` | integer | Filter by member (must exist) |
| `status` | string | `ACTIVE`, `COMPLETED`, `CANCELLED` |
| `date_from` | date | `borrowed_at >= start of date` |
| `date_to` | date | `borrowed_at <= end of date` |
| `overdue` | boolean | `true` = has at least one active overdue item |
| `sort` | string | `loan_number`, `borrowed_at`, `status`, `created_at`. Default: `borrowed_at` |
| `direction` | string | `asc`, `desc`. Default: `desc` |
| `per_page` | integer | Max 50. Default: 15 |
| `page` | integer | |

---

### 2. View Loan
`GET /api/loans/{loan}`

- **Staff/Admin:** Can view any loan.
- **Member:** Can only view their own loan. Returns `403` otherwise.

---

### 3. Create Loan
`POST /api/loans`

**Member Ownership Rules:**
- **Staff/Admin:** Must supply `member_id` in the request body.
- **Member:** `member_id` from the request body is **ignored**. The server automatically uses the authenticated user's linked member record. This prevents a member from creating loans on behalf of another member.

**Request Body:**
```json
{
  "member_id": 1,
  "book_copy_ids": [1, 2],
  "borrowed_at": "2026-09-19T05:00:00+07:00",
  "notes": "Optional note"
}
```

**Validation:**
- `book_copy_ids`: required, array, 1–2 items, each must exist in `book_copies`
- `borrowed_at`: optional, valid datetime
- `notes`: optional string, max 1000 chars

**Returns:** HTTP 201 on success.

---

### 4. My Loans (Self)
`GET /api/me/loans`

Returns the authenticated user's own loan history. Returns `404` if the user has no linked Member record.

**Query Parameters:** same as `/api/loans` but without `member_id` and `search`.

---

## Business Rule Errors (HTTP 422)

The Loan API delegates all domain validation to `LoanService`. When the service rejects a loan, the error is returned as HTTP `422`:

| Scenario | Message |
|---|---|
| Member has 2 active items | `Member has reached the maximum number of active loans.` |
| Unpaid/partial fine exists | `Outstanding fines must be paid before borrowing.` |
| Unresolved lost/damaged incident | `Member has unresolved lost or damaged book obligations.` |
| Book copy not AVAILABLE | `Book copy is not available.` |
| Duplicate book_copy_ids in request | `Duplicate book copy requested.` |
| Inactive member | `Member is inactive.` |

---

## Response Structure

### Loan List
```json
{
  "success": true,
  "message": "Loans retrieved successfully.",
  "data": {
    "items": [
      {
        "id": 1,
        "loan_number": "LOAN-2026-000001",
        "member": {
          "id": 1,
          "member_number": "MEM-001",
          "name": "Example Student",
          "member_type": { "id": 1, "code": "STD", "name": "Student" }
        },
        "borrowed_at": "2026-09-19T05:00:00.000000Z",
        "status": "ACTIVE",
        "notes": null,
        "items": [
          {
            "id": 1,
            "book_copy": {
              "id": 1,
              "barcode": "BC-001",
              "book": { "id": 1, "title": "Laskar Pelangi", "isbn": "..." },
              "location": { "id": 1, "code": "A01", "name": "Rak A01" }
            },
            "borrowed_at": "2026-09-19T05:00:00.000000Z",
            "due_at": "2026-09-22T00:00:00.000000Z",
            "returned_at": null,
            "renewal_count": 0,
            "status": "ACTIVE",
            "is_overdue": false,
            "overdue_days": 0
          }
        ],
        "summary": {
          "total_items": 1,
          "active_items": 1,
          "returned_items": 0,
          "overdue_items": 0
        },
        "created_at": "2026-09-19T05:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 1,
      "last_page": 1
    }
  }
}
```

---

## What Is NOT Supported Yet

The following endpoints are intentionally **not implemented** in this task and will be addressed in future API modules:

- `PATCH /api/loans/{loan}` — Return (via `ReturnService`)
- Renewal — (via `RenewalService`)
- Loan cancellation
- Fine payment
- Book Incident reporting
