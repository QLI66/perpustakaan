# Return API

The Return API provides a **private** operational endpoint for library staff and admins to process book returns.

## Authentication

All endpoints require a valid Sanctum Bearer token:
```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | `POST /loans/{loan}/items/{loanItem}/return` |
|---|---|
| `SUPER_ADMIN` | ✅ |
| `LIBRARY_STAFF` | ✅ |
| `MEMBER` | ❌ 403 |
| Guest | ❌ 401 |

A return is strictly an operational action performed by staff. Members cannot self-return books via this API.

---

## Endpoint

### Process Return
`POST /api/loans/{loan}/items/{loanItem}/return`

This endpoint marks a specific loan item as returned. It validates that the `{loanItem}` belongs to the `{loan}`.

**Request Body:**
```json
{
  "returned_at": "2026-09-19T10:30:00+07:00",
  "notes": "Optional"
}
```

**Validation:**
- `returned_at`: optional, valid date/time. If omitted, the server uses `now()`.
- `notes`: optional string, max 1000 chars.

**Security Guardrails:**
Clients **cannot** manipulate business logic outcomes through the API. The following fields will be strictly ignored if sent:
- `member_id`
- `book_copy_id`
- `due_at`
- `late_days`
- `amount`
- `rate_per_day`

---

## State Transitions

Upon a successful return, the `ReturnService` manages the following state changes atomically:

1. **LoanItem:** Status changes to `RETURNED`. `returned_at` is recorded.
2. **BookCopy:** Status changes to `AVAILABLE`.
3. **Loan:** Status changes to `COMPLETED` *only* if all items in the loan have been returned. Otherwise, it remains `ACTIVE`.

## Fines

The Return API **does not** contain internal fine logic. It delegates all calendar calculation to the `ReturnService`.
If an item is returned past its `due_at` date, the `ReturnService` will automatically generate a `Fine` record. The details of this fine will be exposed in the API response.

---

## Error Mapping (HTTP 422)

If the `ReturnService` rejects the operation, a `422 Unprocessable Entity` is returned containing the domain reason:

| Scenario | Service Message |
|---|---|
| Item already returned | `Loan item has already been returned or is not active.` |
| BookCopy not BORROWED | `Loan item and book copy status are inconsistent. Book copy is not in BORROWED status.` |
| Duplicate Fine guard | `A fine already exists for this loan item.` |

If the URL parameters are inconsistent (e.g., LoanItem `2` does not belong to Loan `1`), the API returns `404 Not Found` to obscure existence.

---

## Response Structure

**HTTP 200 OK**

```json
{
  "success": true,
  "message": "Book returned successfully.",
  "data": {
    "loan": {
      "id": 1,
      "loan_number": "LOAN-2026-000001",
      "status": "COMPLETED"
    },
    "loan_item": {
      "id": 1,
      "book_copy": {
        "id": 1,
        "barcode": "BC-001",
        "status": "AVAILABLE",
        "book": {
          "id": 1,
          "title": "Laskar Pelangi",
          "isbn": "9786022912344"
        },
        "location": {
          "id": 1,
          "code": "A01",
          "name": "Rak Utama"
        }
      },
      "borrowed_at": "2026-09-17T05:00:00.000000Z",
      "due_at": "2026-09-20T00:00:00.000000Z",
      "returned_at": "2026-09-21T10:30:00.000000Z",
      "renewal_count": 0,
      "status": "RETURNED",
      "is_overdue": true,
      "overdue_days": 1
    },
    "loan_status": "COMPLETED",
    "fine": {
      "id": 1,
      "late_days": 1,
      "rate_per_day": "1000.00",
      "amount": "1000.00",
      "amount_paid": "0.00",
      "status": "UNPAID",
      "assessed_at": "2026-09-21T10:30:00.000000Z"
    }
  }
}
```

If the return is on-time, `is_overdue` will be `false`, `overdue_days` will be `0`, and the `fine` object will be `null`.
