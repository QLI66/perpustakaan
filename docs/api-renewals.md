# Renewal API

The Renewal API allows authorized users to extend the due date of an active loan item.

## Authentication

All endpoints require a valid Sanctum Bearer token:
```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | `POST /loans/{loan}/items/{loanItem}/renew` |
|---|---|
| `SUPER_ADMIN` | ✅ Any eligible loan item |
| `LIBRARY_STAFF` | ✅ Any eligible loan item |
| `MEMBER` | ✅ **Only** their own loan item |
| Guest | ❌ 401 |

Members are strictly verified via their `member_id` on the `Loan` record. Attempting to renew a loan item belonging to another member results in a `403 Forbidden` response.

---

## Endpoint

### Process Renewal
`POST /api/loans/{loan}/items/{loanItem}/renew`

**Request Body:**
```json
{
  "renewed_at": "2026-09-19T10:00:00+07:00",
  "notes": "Optional extension reason"
}
```

**Validation:**
- `renewed_at`: Optional. Must be a valid date/time. If omitted, the server uses `now()`.
- `notes`: Optional string, max 1000 chars.

**Security Guardrails:**
Clients **cannot** manipulate business logic outcomes through the API. The following fields will be firmly ignored if sent:
- `member_id`
- `book_copy_id`
- `due_at`
- `renewal_count`
- `status`
- Any fine information

---

## State Transitions & Rules

Upon a successful renewal, the `RenewalService` manages the state transaction atomically:

1. **LoanItem:** 
   - `renewal_count` is incremented by 1 (e.g., `0` -> `1`).
   - `due_at` is extended by 3 days calculated from `startOfDay(renewedAt)`. It is **not** calculated based on the previous due date.
   - Status remains `ACTIVE`.
2. **BookCopy:** Status remains `BORROWED`.
3. **Loan:** Status remains `ACTIVE`.

### Blocking Conditions (HTTP 422)

The `RenewalService` will block the renewal and throw a `BusinessRuleException` under the following conditions:

| Scenario | Service Message |
|---|---|
| Item is already returned/inactive | `Loan item is not active and cannot be renewed.` |
| Maximum renewals reached (e.g., > 0) | `Maximum renewal limit has been reached.` |
| Member has UNPAID or PARTIAL fines | `All outstanding fines must be fully paid before renewal.` |
| Member has OPEN lost/damaged incident | `Member has unresolved lost or damaged book obligations.` |
| Internal mismatch of BookCopy state | `Loan item and book copy status are inconsistent.` |

---

## Error Mapping

| Status | Reason |
|---|---|
| `401 Unauthorized` | Missing or invalid token. |
| `403 Forbidden` | Member attempted to renew someone else's item, or user lacks `loans.renew` permission. |
| `404 Not Found` | The loan doesn't exist, the item doesn't exist, or the `{loanItem}` does not belong to the `{loan}` in the URL (route hierarchy mismatch). |
| `422 Unprocessable Entity` | A domain validation/business rule failed (e.g., renewal limit, unpaid fine). |

---

## Response Structure

**HTTP 200 OK**

```json
{
  "success": true,
  "message": "Book loan renewed successfully.",
  "data": {
    "loan": {
      "id": 1,
      "loan_number": "LOAN-2026-000001",
      "status": "ACTIVE"
    },
    "loan_item": {
      "id": 1,
      "book_copy": {
        "id": 1,
        "barcode": "BC-001",
        "status": "BORROWED",
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
      "due_at": "2026-09-22T00:00:00.000000Z",
      "renewal_count": 1,
      "status": "ACTIVE"
    }
  }
}
```
*(Note: The new `due_at` reflects the extended calendar date.)*
