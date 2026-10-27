# Book Copy API

The Book Copy API provides **private** management of physical library book copies. It is exclusively for administrative use. Public availability is exposed via the Catalog API.

## Authentication

All endpoints require a valid Sanctum Bearer token:

```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | List | View | Create | Update | Delete |
|---|---|---|---|---|---|
| `SUPER_ADMIN` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `LIBRARY_STAFF` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `MEMBER` | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |
| Guest | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 |

## Endpoints

### 1. List Book Copies
`GET /api/book-copies`

**Query Parameters:**
- `search` (string): Searches `barcode`, `inventory_number`, `book.title`, and `book.isbn`. Case-insensitive.
- `book_id` (integer)
- `location_id` (integer)
- `status` (string): e.g., `AVAILABLE`, `BORROWED`, `LOST`.
- `condition` (string): `NEW`, `GOOD`, `FAIR`, `POOR`.
- `acquisition_source` (string): `PURCHASE`, `DONATION`, etc.
- `available` (boolean): `true` = `AVAILABLE`, `false` = anything else.
- `sort` (string): `barcode`, `inventory_number`, `acquisition_date`, `acquisition_price`, `created_at`. Default: `barcode`.
- `direction` (string): `asc` or `desc`. Default: `asc`.
- `per_page` (integer, max 50): Default: 15.
- `page` (integer).

### 2. View Book Copy
`GET /api/book-copies/{bookCopy}`

### 3. Create Book Copy
`POST /api/book-copies`

Returns **HTTP 201**.

**Important:** New copies are automatically created with `status: "AVAILABLE"`. The `status` field is not accepted in the request body. Barcodes must be manually supplied; they are not auto-generated.

**Body Example:**
```json
{
  "book_id": 1,
  "barcode": "BC-001",
  "inventory_number": "INV-001",
  "location_id": 1,
  "acquisition_date": "2026-01-01",
  "acquisition_source": "PURCHASE",
  "acquisition_price": 150000.00,
  "condition": "GOOD",
  "notes": "First edition"
}
```

### 4. Update Book Copy
`PATCH /api/book-copies/{bookCopy}`

**Important:** The `status` field cannot be arbitrarily manipulated via generic PATCH. Transitions like `BORROWED -> AVAILABLE` or `LOST -> AVAILABLE` must be handled exclusively by domain services (LoanService, ReturnService, BookIncidentService) to prevent bypassing business rules. 

### 5. Delete Book Copy
`DELETE /api/book-copies/{bookCopy}`

**Delete Strategy:** 
A hard delete is permitted only if the book copy has **no** historical transaction or incident data. 
If the copy is tied to any `loan_items` or `book_incidents`, the deletion is **blocked** with an HTTP 409 Conflict. This ensures that the library's transaction history is never corrupted. For physical removal of historical copies, they should be marked as `LOST` or `DAMAGED` via the incident workflow.
