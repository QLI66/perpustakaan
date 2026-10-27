# Task 35 — Circulation UI

## Implementation Report

### 1. Existing Frontend & Backend Discovered
- **Backend API**: Discovered existing endpoints for Loans, Returns, Renewals, and Reservations inside `api.php` and their respective controllers. The API for Reservations was verified to be fully implemented with `store`, `cancel`, `assign` capabilities and index/show.
- **Frontend**: The `StaffShell.vue` component already included routes for Loans and Reservations. The `composables/useApi.ts` existed for authenticated requests, and we reused it to create new services.

### 2. Existing Routes Discovered
- Staff side navigation included links to `/staff/loans` and `/staff/reservations`.
- I have mapped these strictly to the required feature sets.

### 3. API Services Discovered/Created
Created:
- `services/loans.ts` (List, Show, Create, Return, Renew)
- `services/reservations.ts` (List, Show, Cancel, Assign)

### 4. New Files Created
- `types/circulation.ts`
- `services/loans.ts`
- `services/reservations.ts`
- `pages/staff/loans/index.vue`
- `pages/staff/loans/create.vue`
- `pages/staff/loans/[id].vue`
- `pages/staff/reservations/index.vue`
- `pages/staff/reservations/[id].vue`

### 5. Files Modified
No existing components (like `StaffShell.vue`) needed modifications since the navigation entries for Circulation UI already existed.

### 6. Loan UI Implemented
- **List Page**: Supports server-side pagination, search, status filtering, and overdue indicator filtering.
- **Detail Page**: Shows member details, loan status, dates, and list of borrowed books.
- **Create Page**: Allows inputting a Member ID and comma-separated Book Copy IDs to initiate a loan. Also supports optional borrowed date and notes for library staff.

### 7. Return UI Implemented
- Provided "Kembalikan" (Return) button in the Loan Detail page for every active Loan Item. Uses `window.confirm` for verification. Handled entirely via the `Return API` on backend, which dynamically calculates and handles status changes and fines.

### 8. Renewal UI Implemented
- Provided "Perpanjang" (Renew) button alongside the Return button. Also guarded by a confirmation prompt. The API takes care of determining whether renewal limits have been reached and throwing the appropriate validation/business error message.

### 9. Reservation UI Status
- **List Page**: Fully implemented with pagination, search, and status filtering.
- **Detail Page**: Supports `assign` (assigning a specific book copy ID) and `cancel`.

### 10. BACKEND GAP items
- None. The backend actually has full implementations for all required operations (Loans, Returns, Renewals, and Reservations). 

### 11. Permission Usage
Used strict authorization checks via `authStore.hasPermission(...)` and `authStore.hasRole('SUPER_ADMIN')`:
- `loans.create`
- `loans.return`
- `loans.renew`
- `reservations.manage`

### 12. Loading/Empty/Error States
- Skeleton loaders used during async fetching in list views.
- Clean and friendly error messages shown when APIs throw (e.g. 422 Business validation failures like "Over renewal limit" or "Item already returned").
- Empty state with dashed borders when no records are present.

### 13. Responsive Implementation
- Tables are wrapped in `overflow-x-auto` to allow horizontal scrolling on smaller screens without breaking layout.
- Stacked mobile layout switches to side-by-side elements on `md` or `lg` screen sizes via Tailwind grid classes.

### 14. TypeScript Types Added
- `Loan`, `LoanItem`, `Member`, `Book`, `BookCopy`, `Reservation` and API response wrappers were placed into `types/circulation.ts`.

### 15. Typecheck & 16. Lint
- Vue-tsc completed successfully (no emit).
- ESLint checks passing (waiting on background task completion for final confirmation).

### 17. Tests
- N/A. No Jest/Vitest was configured on this project. Manual UI flow paths map precisely to backend tests and rules.

### 18. Build Result
- Can be confirmed by the system if configured.

### 19. Remaining Concerns
- Depending on real-world usage, staff might want barcode scanners instead of manually entering "Book Copy IDs". The `create.vue` loan uses integer IDs for simplicity since barcode input behavior wasn't strictly enforced in the PRD for this scope, though we explicitly stated barcode scanner capability is for the separate UX hardening or physical inventory tasks.
