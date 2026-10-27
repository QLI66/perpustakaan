# Reservations API Documentation

The Reservations module allows members to reserve a book and join a FIFO (First-In, First-Out) queue. Once a copy of the book becomes available, staff can assign the copy to the earliest eligible reservation, marking it as `READY`. The member can then borrow the assigned copy within a specified timeframe (e.g., 3 days).

## Reservation Lifecycle

1. **PENDING**: The member has requested the book, but no copy is assigned yet.
2. **READY**: An available copy has been assigned to this reservation. The member has until `expires_at` to borrow it.
3. **FULFILLED**: The member successfully borrowed the assigned copy (a Loan was created).
4. **CANCELLED**: The member or staff cancelled the reservation before fulfillment.
5. **EXPIRED**: The member failed to borrow the assigned copy before `expires_at`.

## Constraints & Concurrency

- **FIFO Enforcement**: A copy can only be assigned to the *earliest* `PENDING` reservation for a book, ensuring fair queue processing.
- **Single Active Reservation**: A member may only have one active (`PENDING` or `READY`) reservation per book. This is enforced via business logic and a PostgreSQL partial unique index.
- **Atomicity**: Transitions between reservation states and book copy states (e.g., `READY` to `FULFILLED` via loan creation) are strictly wrapped in database transactions with row-level locking (`lockForUpdate`).

## Endpoints

### 1. View My Reservations
- **Method**: `GET`
- **Path**: `/api/me/reservations`
- **Auth**: Authenticated Member
- **Query Params**: `status`, `search`, `date_from`, `date_to`, `sort`, `direction`, `per_page`, `page`

### 2. View All Reservations (Staff/Admin)
- **Method**: `GET`
- **Path**: `/api/reservations`
- **Auth**: Staff or Super Admin
- **Query Params**: Same as above, plus `member_id` and `book_id`

### 3. View Reservation Details
- **Method**: `GET`
- **Path**: `/api/reservations/{reservation}`
- **Auth**: Staff, Admin, or the Member who owns the reservation

### 4. Create a Reservation
- **Method**: `POST`
- **Path**: `/api/reservations`
- **Auth**: Authenticated Member
- **Payload**:
  ```json
  {
      "book_id": 1,
      "notes": "Optional notes"
  }
  ```

### 5. Cancel a Reservation
- **Method**: `POST`
- **Path**: `/api/reservations/{reservation}/cancel`
- **Auth**: Staff, Admin, or the Member who owns the reservation
- **Description**: Transitions the reservation to `CANCELLED`. If the reservation was `READY`, the assigned `BookCopy` becomes `AVAILABLE` again.

### 6. Assign a Copy (Staff Operation)
- **Method**: `POST`
- **Path**: `/api/reservations/{reservation}/assign`
- **Auth**: Staff or Super Admin (requires `reservations.manage` permission)
- **Payload**:
  ```json
  {
      "book_copy_id": 10
  }
  ```
- **Description**: Transitions a `PENDING` reservation to `READY`, sets an expiration date, and marks the `BookCopy` as `RESERVED`. Strictly enforces FIFO ordering.

## Loan Integration

Reservations are fulfilled through the existing `LoanService`. 
When `POST /api/loans` is called with a `BookCopy` ID that is `RESERVED`, the service verifies that the borrowing member holds a valid `READY` reservation for that specific copy. If valid, the reservation is marked as `FULFILLED`, the copy becomes `BORROWED`, and the loan is created in a single atomic transaction.

## Expiration

An artisan command exists to process expirations:
```bash
php artisan reservations:expire
```
This command finds all `READY` reservations where `expires_at` has passed, marks them as `EXPIRED`, and resets the associated `BookCopy` to `AVAILABLE`. This command should be scheduled via Laravel Scheduler (e.g., hourly or daily).
