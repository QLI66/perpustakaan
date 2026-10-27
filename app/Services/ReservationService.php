<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * Create a new reservation for a book.
     *
     * @throws BusinessRuleException
     */
    public function createReservation(
        int $memberId,
        int $bookId,
        ?Carbon $reservedAt = null,
        ?string $notes = null
    ): Reservation {
        return DB::transaction(function () use ($memberId, $bookId, $reservedAt, $notes) {
            $reservedAt = $reservedAt ?? now();

            // 1. Lock Member to ensure consistency
            $member = Member::lockForUpdate()->findOrFail($memberId);

            if ($member->status !== 'ACTIVE') {
                throw new BusinessRuleException('Member is inactive.');
            }

            // 2. Validate Book existence
            $book = Book::findOrFail($bookId);

            // 3. Check outstanding fines
            $memberLoanItemIds = LoanItem::whereIn(
                'loan_id',
                Loan::where('member_id', $member->id)->select('id')
            )->pluck('id');

            $hasUnpaidFine = Fine::whereIn('loan_item_id', $memberLoanItemIds)
                ->whereIn('status', ['UNPAID', 'PARTIAL'])
                ->exists();

            if ($hasUnpaidFine) {
                throw new BusinessRuleException('Outstanding fines must be paid before making a reservation.');
            }

            // 4. Check unresolved incidents
            $hasUnresolvedIncident = BookIncident::whereIn('loan_item_id', $memberLoanItemIds)
                ->where('status', 'OPEN')
                ->exists();

            if ($hasUnresolvedIncident) {
                throw new BusinessRuleException('Member has unresolved lost or damaged book obligations.');
            }

            // 5. Prevent duplicate reservations for the same book
            $hasActiveReservation = Reservation::where('member_id', $member->id)
                ->where('book_id', $book->id)
                ->whereIn('status', ['PENDING', 'READY'])
                ->exists();

            if ($hasActiveReservation) {
                throw new BusinessRuleException('Member already has an active reservation for this book.');
            }

            // 6. Generate reservation number safely
            $reservationNumber = $this->generateReservationNumber($reservedAt);

            // 7. Create Reservation
            return Reservation::create([
                'reservation_number' => $reservationNumber,
                'member_id' => $member->id,
                'book_id' => $book->id,
                'status' => 'PENDING',
                'reserved_at' => $reservedAt,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Cancel an active reservation.
     *
     * @throws BusinessRuleException
     */
    public function cancelReservation(int $reservationId, ?Carbon $cancelledAt = null): Reservation
    {
        return DB::transaction(function () use ($reservationId, $cancelledAt) {
            $cancelledAt = $cancelledAt ?? now();

            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if (! in_array($reservation->status, ['PENDING', 'READY'])) {
                throw new BusinessRuleException('Only PENDING or READY reservations can be cancelled.');
            }

            // If a copy has already been assigned, release it.
            // This applies to both PENDING + RESERVED and legacy READY + RESERVED.
            if ($reservation->book_copy_id) {
                $bookCopy = BookCopy::lockForUpdate()->find($reservation->book_copy_id);

                if ($bookCopy && $bookCopy->status === 'RESERVED') {
                    $bookCopy->status = 'AVAILABLE';
                    $bookCopy->save();
                }

                $reservation->book_copy_id = null;
            }

            $reservation->status = 'CANCELLED';
            $reservation->cancelled_at = $cancelledAt;
            $reservation->ready_at = null;
            $reservation->expires_at = null;
            $reservation->save();

            return $reservation;
        });
    }

    /**
     * Restore a cancelled reservation back to PENDING.
     *
     * The reservation does not automatically receive a BookCopy.
     * Staff must assign a copy again through the normal FIFO process.
     *
     * @throws BusinessRuleException
     */
    public function restoreReservation(int $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'CANCELLED') {
                throw new BusinessRuleException('Only CANCELLED reservations can be restored.');
            }

            $reservation->status = 'PENDING';
            $reservation->book_copy_id = null;
            $reservation->ready_at = null;
            $reservation->fulfilled_at = null;
            $reservation->cancelled_at = null;
            $reservation->expires_at = null;
            $reservation->save();

            return $reservation;
        });
    }

    /**
     * Assign a BookCopy to a Reservation, making it READY.
     * Enforces FIFO: only the earliest PENDING reservation for this book can be assigned.
     *
     * @throws BusinessRuleException
     */
    public function assignCopy(int $reservationId, int $bookCopyId, ?Carbon $readyAt = null): Reservation
    {
        return DB::transaction(function () use ($reservationId, $bookCopyId, $readyAt) {
            $readyAt = $readyAt ?? now();

            // Lock BookCopy first to avoid deadlocks (Copy -> Reservation)
            $bookCopy = BookCopy::lockForUpdate()->findOrFail($bookCopyId);

            if ($bookCopy->status !== 'AVAILABLE') {
                throw new BusinessRuleException('Book copy must be AVAILABLE to be assigned.');
            }

            // Lock Reservation
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->status !== 'PENDING') {
                throw new BusinessRuleException('Reservation must be PENDING to be assigned.');
            }

            if ($reservation->book_id !== $bookCopy->book_id) {
                throw new BusinessRuleException('Book copy does not match the reserved book.');
            }

            // Enforce FIFO: find the earliest PENDING reservation for this book
            $earliestReservation = Reservation::where('book_id', $reservation->book_id)
                ->where('status', 'PENDING')
                ->orderBy('reserved_at', 'asc')
                ->lockForUpdate() // Lock the earliest found row to prevent race conditions in FIFO calculation
                ->first();

            if ($earliestReservation && $earliestReservation->id !== $reservation->id) {
                throw new BusinessRuleException('Reservation assignment must follow FIFO order. Another member reserved this book earlier.');
            }

            // Calculate expiry
            $expireDays = config('library.reservation_expire_days', 3);
            $expiresAt = $readyAt->copy()->addDays($expireDays);

            // Update BookCopy
            $bookCopy->status = 'RESERVED';
            $bookCopy->save();

            // Update Reservation
            // Assignment hanya mengamankan copy untuk proses peminjaman.
            // Reservation tetap PENDING sampai loan benar-benar disimpan.
            $reservation->book_copy_id = $bookCopy->id;
            $reservation->status = 'PENDING';
            $reservation->ready_at = null;
            $reservation->expires_at = $expiresAt;
            $reservation->save();

            return $reservation;
        });
    }

    /**
     * Mark READY reservations as EXPIRED if their expires_at has passed.
     */
    public function expireReservations(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $expiredCount = 0;

        DB::transaction(function () use ($now, &$expiredCount) {
            // Find assigned active reservations whose pickup window has expired.
            // PENDING reservations without an assigned copy are not affected.
            $reservations = Reservation::whereIn('status', ['PENDING', 'READY'])
                ->whereNotNull('book_copy_id')
                ->where('expires_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                // Double check status after lock
                if (
                    in_array($reservation->status, ['PENDING', 'READY'], true) &&
                    $reservation->book_copy_id
                ) {
                    $reservation->status = 'EXPIRED';
                    $reservation->save();

                    if ($reservation->book_copy_id) {
                        $bookCopy = BookCopy::lockForUpdate()->find($reservation->book_copy_id);
                        if ($bookCopy && $bookCopy->status === 'RESERVED') {
                            $bookCopy->status = 'AVAILABLE';
                            $bookCopy->save();
                        }
                    }
                    $expiredCount++;
                }
            }
        });

        return $expiredCount;
    }

    /**
     * Generate a concurrency-safe reservation number using an advisory lock.
     * Format: RES-YYYY-XXXXXX
     */
    private function generateReservationNumber(Carbon $reservedAt): string
    {
        $year = $reservedAt->year;

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(:key))', [
            'key' => "reservation_number_generator_{$year}",
        ]);

        $count = Reservation::whereYear('reserved_at', $year)->count();
        $sequence = $count + 1;

        $candidate = sprintf('RES-%d-%06d', $year, $sequence);

        while (Reservation::where('reservation_number', $candidate)->exists()) {
            $sequence++;
            $candidate = sprintf('RES-%d-%06d', $year, $sequence);
        }

        return $candidate;
    }
}
