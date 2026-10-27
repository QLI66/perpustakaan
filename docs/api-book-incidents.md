# Book Incident API

The Book Incident API allows authorized library staff and super admins to track and resolve lost or damaged library books.

## Authentication

All endpoints require a valid Sanctum Bearer token:
```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | GET `/book-incidents` | GET `/book-incidents/{id}` | GET `/me/book-incidents` | POST `/book-incidents` | POST `.../resolve/*` |
|---|---|---|---|---|---|
| `SUPER_ADMIN` | ✅ All | ✅ Any | ✅ | ✅ | ✅ |
| `LIBRARY_STAFF` | ✅ All | ✅ Any | ✅ | ✅ | ✅ |
| `MEMBER` | ❌ 403 | ✅ Own only | ✅ Own only | ❌ 403 | ❌ 403 |
| Guest | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 |

---

## Endpoints

### GET /api/book-incidents
List all incidents. Only accessible by `SUPER_ADMIN` and `LIBRARY_STAFF`.

**Query Parameters:**
| Parameter | Type | Description |
|---|---|---|
| `search` | string | Searches member number/name, loan number, book barcode/title/isbn |
| `type` | string | `LOST` or `DAMAGED` |
| `status` | string | `OPEN` or `RESOLVED` |
| `resolution_type` | string | `PAID_MONEY` or `REPLACED_BOOK` |
| `date_from` / `date_to` | date | Filter by `opened_at` |

### GET /api/book-incidents/{incident}
View a single incident with full details. Members may only view their own incidents.

### GET /api/me/book-incidents
Authenticated member's own incidents.

---

### POST /api/book-incidents
Open a new incident. Only `SUPER_ADMIN` and `LIBRARY_STAFF`.

> [!WARNING]  
> **Pre-condition**: The `LoanItem` and its associated `BookCopy` must ALREADY be in `LOST` or `DAMAGED` status via the Return API or Loan API before an incident can be opened. The incident simply tracks the resolution of that state.

**Request Body:**
```json
{
  "loan_item_id": 123,
  "type": "LOST",
  "opened_at": "2026-09-19T10:00:00+07:00",
  "notes": "Member reported lost"
}
```

**Note:** `amount_due` is automatically derived by the server from the book copy's `acquisition_price`.

---

### POST /api/book-incidents/{incident}/resolve/payment
Resolve an incident via monetary payment.

**Request Body:**
```json
{
  "resolved_at": "2026-09-19T10:00:00+07:00",
  "notes": "Paid in cash at counter"
}
```
**Note:** The amount required is strictly enforced by the server (`amount_paid` will equal `amount_due`). The client does not submit an `amount`.

---

### POST /api/book-incidents/{incident}/resolve/replacement
Resolve an incident by replacing the book.

**Request Body:**
```json
{
  "replacement_book_copy_id": 456,
  "resolved_at": "2026-09-19T10:00:00+07:00",
  "notes": "Replaced with brand new copy"
}
```

> [!IMPORTANT]
> The `replacement_book_copy_id` must point to a copy that is `AVAILABLE` and belongs to the **same `Book`** as the original copy.

---

## Response Structure (Detail)

**HTTP 200 OK**
```json
{
  "success": true,
  "message": "Book incident details retrieved successfully.",
  "data": {
    "id": 1,
    "type": "LOST",
    "status": "OPEN",
    "loan_item": { "id": 123 },
    "loan": { "id": 50, "loan_number": "LOAN-001" },
    "member": { "id": 10, "member_number": "MEM-001", "name": "John Doe" },
    "book_copy": { "id": 5, "barcode": "BC-001" },
    "book": { "id": 2, "title": "Example Book", "isbn": "123" },
    "resolution_type": null,
    "replacement_book_copy": null,
    "amount_due": "50000.00",
    "amount_paid": "0.00",
    "outstanding_amount": "50000.00",
    "opened_at": "2026-09-19T10:00:00.000000Z",
    "resolved_at": null,
    "resolved_by": null,
    "notes": "Reported lost"
  }
}
```
