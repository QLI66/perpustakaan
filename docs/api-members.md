# Member API

The Member API provides **private** management of library members. All endpoints require authentication. Public catalog endpoints are unaffected.

## Authentication

All endpoints require a valid Sanctum Bearer token:

```
Authorization: Bearer {token}
```

## Authorization Rules

| Role | List | View (own) | View (others) | Create | Update | Delete |
|---|---|---|---|---|---|---|
| `SUPER_ADMIN` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `LIBRARY_STAFF` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `MEMBER` | ❌ 403 | ✅ | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 |
| Guest | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 | ❌ 401 |

MEMBER role uses **ownership logic** — not a global `members.view` permission — to access their own record.

## Endpoints

### 1. List Members
`GET /api/members`

Requires `members.view` permission.

**Query Parameters:**
- `search` (string): Searches `member_number`, `name`, `identity_number`.
- `member_type_id` (integer)
- `class_level_id` (integer)
- `major_id` (integer)
- `status` (string): `ACTIVE`, `INACTIVE`, or `SUSPENDED`.
- `sort` (string): `member_number`, `name`, `joined_at`, `created_at`. Default: `name`.
- `direction` (string): `asc` or `desc`. Default: `asc`.
- `per_page` (integer, max 50): Default: 15.
- `page` (integer).

### 2. View Member
`GET /api/members/{member}`

- Staff/Admin: may view any member.
- MEMBER role: may only view their **own** record; 403 for all others.

### 3. Create Member
`POST /api/members`

Requires `members.create` permission. Returns **HTTP 201** on success.

**Body:**
```json
{
  "member_number": "MBR-001",
  "name": "Student Name",
  "member_type_id": 1,
  "user_id": null,
  "identity_number": null,
  "class_level_id": null,
  "major_id": null,
  "joined_at": "2026-01-01",
  "status": "ACTIVE",
  "notes": null
}
```

### 4. Update Member
`PATCH /api/members/{member}`

Requires `members.update` permission. MEMBER role cannot update.

### 5. Delete Member
`DELETE /api/members/{member}`

Requires `members.delete` permission.

**Delete Strategy:** Hard delete is performed **only** if the member has no loan history. If the member has any loans (active or historical), the deletion is **blocked** with HTTP 409 and a descriptive message. This preserves referential integrity of library transaction records.

To "remove" a member with history, set `status` to `INACTIVE` or `SUSPENDED`.

### 6. My Member Profile
`GET /api/me/member`

Returns the member record linked to the authenticated user (via `members.user_id = auth.id`).

- Returns **HTTP 404** if the authenticated user has no member record.
- Does **not** accept a member ID — always resolves from the authenticated user.

## Security

- `password`, `remember_token`, and `personal_access_tokens` are **never** exposed in any member response.
- `identity_number` is included in member detail for staff/admin and own profile access.
- Sort columns are explicitly whitelisted; arbitrary column injection is rejected with 422.
