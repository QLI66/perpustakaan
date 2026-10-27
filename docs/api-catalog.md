# Catalog API

The Catalog API provides public, read-only access to the library's collection of books, categories, authors, publishers, and collection types.

## Authentication
**None.** All endpoints under `/api/catalog` are fully public and do not require a Sanctum token.

## Endpoints

### 1. List Books
`GET /api/catalog/books`

Returns a paginated list of books.

**Query Parameters (Optional):**
- `search` (string): Searches title, subtitle, isbn, author names, and publisher names.
- `category_id` (integer)
- `author_id` (integer)
- `publisher_id` (integer)
- `collection_type_id` (integer)
- `class_level_id` (integer): Filters by applicable class.
- `major_id` (integer): Filters by applicable major.
- `available` (boolean): `true` to only return books with at least one AVAILABLE copy.
- `sort` (string): `title`, `publication_year`, or `created_at`. Default: `title`.
- `direction` (string): `asc` or `desc`. Default: `asc`.
- `per_page` (integer): Items per page (max 50). Default: 15.
- `page` (integer): Page number.

**Book Target Filtering Semantics (`class_level_id` & `major_id`)**
- A book with NO targets assigned is considered "Global" and will appear in all searches.
- If querying both `class_level_id` and `major_id`, books matching the exact combination, global books, class-only books, and major-only books will be returned.

### 2. View Book Detail
`GET /api/catalog/books/{book}`

Returns comprehensive information about a single book, including full availability breakdown and locations.

**Availability Object:**
```json
"availability": {
  "total_copies": 2,
  "available_copies": 1,
  "borrowed_copies": 1,
  "reserved_copies": 0,
  "lost_copies": 0,
  "damaged_copies": 0,
  "maintenance_copies": 0,
  "is_available": true
}
```

*Note: Individual book copy identifiers (e.g., `barcode`, `inventory_number`) are intentionally omitted from public catalog responses for security reasons.*

### 3. List Categories
`GET /api/catalog/categories`

Returns all active categories sorted by their defined sort order.

### 4. List Authors
`GET /api/catalog/authors`

Returns all active authors.

### 5. List Publishers
`GET /api/catalog/publishers`

Returns all active publishers.

### 6. List Collection Types
`GET /api/catalog/collection-types`

Returns all active collection types sorted by their defined sort order.
