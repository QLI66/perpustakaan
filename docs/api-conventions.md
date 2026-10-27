# API Conventions

This document outlines the standard conventions used across the Library System API. Adhering to these conventions ensures a consistent, predictable, and maintainable developer experience.

## Standard Response Format

All API responses follow a strict JSON structure. 

### Success Response
When an API request succeeds (e.g., reading data, creating a record, or successful action), the response always returns `success: true`.

```json
{
    "success": true,
    "message": "Action successful.",
    "data": {
        // Requested resource or data goes here
    }
}
```
*Note: For endpoints that do not return data (like a logout action), the `data` field is omitted or returned as `null`.*

### Error Response
When an API request fails due to client error, authentication/authorization issues, or server errors, it returns `success: false`.

```json
{
    "success": false,
    "message": "Resource not found.",
    "errors": {
        // Optional detailed error object
    }
}
```

### Validation Error Response (HTTP 422)
When the request payload fails validation, a `422 Unprocessable Entity` is returned. The `errors` object contains an array of messages keyed by the field name.

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}
```

### Business Rule Violations (HTTP 422)
When an action cannot be completed because it violates domain rules (e.g., "Outstanding fines must be paid before borrowing"), a `422 Unprocessable Entity` is returned without the validation `errors` object, but with a descriptive `message`.

```json
{
    "success": false,
    "message": "Outstanding fines must be paid before borrowing."
}
```

## HTTP Status Codes

The API utilizes standard HTTP status codes:

- `200 OK`: Successful request (GET, PUT, PATCH).
- `201 Created`: Successful creation of a resource (POST).
- `204 No Content`: Successful deletion (DELETE) without a response body.
- `400 Bad Request`: Malformed syntax or invalid request format.
- `401 Unauthorized`: Authentication is required and has failed or has not yet been provided.
- `403 Forbidden`: The request was valid, but the server is refusing action. The user might not have the necessary permissions.
- `404 Not Found`: The requested resource could not be found.
- `409 Conflict`: Request conflicts with the current state of the server (sometimes used for business rules, though we predominantly use 422).
- `422 Unprocessable Entity`: Validation errors or Business Rule exceptions.
- `500 Internal Server Error`: An unexpected condition was encountered. **Stack traces and SQL errors are never exposed in production.**

## Authentication

The API uses **Laravel Sanctum** for token-based authentication.

- Protected routes require the `Authorization` header with a Bearer token.
- **Header format:** `Authorization: Bearer {token}`
- Raw passwords and Sanctum `PersonalAccessToken` instances are strictly hidden from API serialization.

## Authorization

Authorization is handled via **Spatie Laravel Permission**.

- Role and Permission checks are mapped to the `web` guard across the database. The `User` model is configured to resolve Spatie checks against the `web` guard to ensure consistent behavior even when authenticated via `sanctum`.
- Use the `can:{permission_name}` middleware on routes.
- Unauthorized attempts correctly return a standard `403 Forbidden` response.

## Pagination Convention
**TODO:** The structure for paginated lists (e.g., using Laravel's default `LengthAwarePaginator` vs custom meta-wrapper) is pending finalization and will be documented here once established.

## Timestamp Format
All timestamps output by the API follow the ISO-8601 format (e.g., `2026-09-18T12:00:00Z`). Timezone conversions should be handled client-side.
