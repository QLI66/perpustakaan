<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\Reservation;
use App\Support\LoanLateness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a new loan transaction for a member.
     *
     * @param  array  $bookCopyIds  Array of BookCopy IDs (1â€“2 items, no duplicates)
     * @param  Carbon|null  $borrowedAt  Defaults to now()
     * @param  int|null  $loanDurationDays  Legacy duration in days; omitted values use the configured default.
     * @param  string|null  $durationUnit  Explicit duration unit (hour or day).
     * @param  int|null  $durationValue  Explicit duration value.
     *
     * @throws BusinessRuleException
     */
    public function createLoan(
        int $memberId,
        array $bookCopyIds,
        ?Carbon $borrowedAt = null,
        ?string $notes = null,
        ?int $loanDurationDays = null,
        ?string $durationUnit = null,
        ?int $durationValue = null
    ): Loan {
        [$durationUnit, $durationValue] = $this->resolveDuration($loanDurationDays, $durationUnit, $durationValue);

        return DB::transaction(function () use ($memberId, $bookCopyIds, $borrowedAt, $notes, $durationUnit, $durationValue) {
            $borrowedAt = $borrowedAt ?? now();

            // â”€â”€ Step 1: Validate bookCopyIds input â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            if (count($bookCopyIds) === 0) {
                throw new BusinessRuleException('At least one book copy must be requested.');
            }

            $maxItems = config('library.max_active_loans', 2);

            if (count($bookCopyIds) > $maxItems) {
                throw new BusinessRuleException('Loan can contain a maximum of two books.');
            }

            if (count($bookCopyIds) !== count(array_unique($bookCopyIds))) {
                throw new BusinessRuleException('Duplicate book copy requested.');
            }

            // â”€â”€ Step 2: Lock and validate Member â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $member = Member::lockForUpdate()->findOrFail($memberId);

            if ($member->status !== 'ACTIVE') {
                throw new BusinessRuleException('Member is inactive.');
            }

            // â”€â”€ Step 3: Check active loan item count â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $activeCount = LoanItem::whereIn(
                'loan_id',
                Loan::where('member_id', $member->id)->select('id')
            )->where('status', 'ACTIVE')->count();

            if ($activeCount >= $maxItems) {
                throw new BusinessRuleException('Member has reached the maximum number of active loans.');
            }

            if (($activeCount + count($bookCopyIds)) > $maxItems) {
                throw new BusinessRuleException('Member has reached the maximum number of active loans.');
            }

            // â”€â”€ Step 4: Check overdue active loan items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $activeLoanItems = LoanItem::whereIn(
                'loan_id',
                Loan::where('member_id', $member->id)->select('id')
            )
                ->where('status', 'ACTIVE')
                ->get(['due_at', 'duration_unit']);

            $hasOverdueLoan = $activeLoanItems->contains(
                fn (LoanItem $item): bool => LoanLateness::isOverdue($item->due_at, now(), $item->duration_unit)
            );

            if ($hasOverdueLoan) {
                throw new BusinessRuleException(
                    'Member has overdue books that must be returned before borrowing.'
                );
            }

            // â”€â”€ Step 5: Check outstanding fines â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $memberLoanItemIds = LoanItem::whereIn(
                'loan_id',
                Loan::where('member_id', $member->id)->select('id')
            )->pluck('id');

            $hasUnpaidFine = Fine::whereIn('loan_item_id', $memberLoanItemIds)
                ->whereIn('status', ['UNPAID', 'PARTIAL'])
                ->exists();

            if ($hasUnpaidFine) {
                throw new BusinessRuleException('Outstanding fines must be paid before borrowing.');
            }

            // â”€â”€ Step 6: Check unresolved LOST/DAMAGED items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $hasUnresolvedIncident = BookIncident::whereIn(
                'loan_item_id',
                $memberLoanItemIds
            )->where('status', 'OPEN')->exists();

            if ($hasUnresolvedIncident) {
                throw new BusinessRuleException('Member has unresolved lost or damaged book obligations.');
            }

            // â”€â”€ Step 7: Sort BookCopy IDs ascending to prevent deadlocks â”€â”€â”€â”€â”€â”€
            $sortedIds = $bookCopyIds;
            sort($sortedIds);

            // Lock all requested BookCopy rows in consistent order
            $bookCopies = BookCopy::whereIn('id', $sortedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Validate each BookCopy
            foreach ($sortedIds as $copyId) {
                if (! $bookCopies->has($copyId)) {
                    throw new BusinessRuleException("Book copy [{$copyId}] not found.");
                }
                $copy = $bookCopies->get($copyId);

                if ($copy->status === 'AVAILABLE') {
                    // Normal borrowing flow
                    continue;
                } elseif ($copy->status === 'RESERVED') {
                    // Check for an assigned PENDING reservation matching this member and copy
                    $reservation = Reservation::where('book_copy_id', $copy->id)
                        ->where('status', 'PENDING')
                        ->where('member_id', $member->id)
                        ->where('expires_at', '>', now())
                        ->lockForUpdate()
                        ->first();

                    if (! $reservation) {
                        throw new BusinessRuleException('Book copy is reserved for another member or reservation has expired.');
                    }

                    // Fulfill the reservation inline
                    $reservation->status = 'FULFILLED';
                    $reservation->fulfilled_at = now();
                    $reservation->save();

                } else {
                    throw new BusinessRuleException('Book copy is not available.');
                }
            }

            // â”€â”€ Step 8: Generate unique loan number â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $loanNumber = $this->generateLoanNumber($borrowedAt);

            // â”€â”€ Step 9: Calculate due_at (calendar date) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            // Use requested duration if provided and within 1â€“3, otherwise fall back to config default.
            $dueAt = $durationUnit === 'hour'
                ? Carbon::parse($borrowedAt)->addHours($durationValue)
                : Carbon::parse($borrowedAt)->startOfDay()->addDays($durationValue);

            // â”€â”€ Step 10: Create Loan â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $loan = Loan::create([
                'loan_number' => $loanNumber,
                'member_id' => $member->id,
                'borrowed_at' => $borrowedAt,
                'status' => 'ACTIVE',
                'notes' => $notes,
            ]);

            // â”€â”€ Step 11: Create LoanItems and update BookCopy statuses â”€â”€â”€â”€â”€â”€â”€â”€â”€
            foreach ($sortedIds as $copyId) {
                $copy = $bookCopies->get($copyId);

                LoanItem::create([
                    'loan_id' => $loan->id,
                    'book_copy_id' => $copy->id,
                    'borrowed_at' => $borrowedAt,
                    'due_at' => $dueAt,
                    'duration_unit' => $durationUnit,
                    'duration_value' => $durationValue,
                    'returned_at' => null,
                    'renewal_count' => 0,
                    'status' => 'ACTIVE',
                ]);

                $copy->status = 'BORROWED';
                $copy->save();
            }

            return $loan->load('items');
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function resolveDuration(?int $loanDurationDays, ?string $durationUnit, ?int $durationValue): array
    {
        if ($durationUnit === null && $durationValue === null) {
            $durationUnit = 'day';
            $legacyDays = $loanDurationDays !== null && $loanDurationDays >= 1 && $loanDurationDays <= 3
                ? $loanDurationDays
                : (int) config('library.loan_days', 3);
            $durationValue ??= $legacyDays;
        } elseif ($durationUnit === null || $durationValue === null || $loanDurationDays !== null) {
            throw new BusinessRuleException('Specify either a complete explicit duration or the legacy daily duration.');
        }

        if (
            ! in_array($durationUnit, ['hour', 'day'], true)
            || ($durationUnit === 'hour' && $durationValue !== 1)
            || ($durationUnit === 'day' && ($durationValue < 1 || $durationValue > 3))
        ) {
            throw new BusinessRuleException('Unsupported loan duration.');
        }

        return [$durationUnit, $durationValue];
    }

    /**
     * Generate a concurrency-safe loan number using an advisory lock.
     *
     * Format: LOAN-YYYY-XXXXXX (zero-padded 6 digits)
     * Strategy: PostgreSQL advisory lock + MAX(id) to ensure uniqueness
     * even under concurrent requests.
     */
    private function generateLoanNumber(Carbon $borrowedAt): string
    {
        $year = $borrowedAt->year;

        // Acquire a PostgreSQL advisory lock scoped to this year.
        // This serialises concurrent loan number generation within the same year.
        DB::statement('SELECT pg_advisory_xact_lock(hashtext(:key))', [
            'key' => "loan_number_generator_{$year}",
        ]);

        // Count existing loans this year and increment safely
        $count = Loan::whereYear('borrowed_at', $year)->count();
        $sequence = $count + 1;

        $candidate = sprintf('LOAN-%d-%06d', $year, $sequence);

        // Final uniqueness guard: if collision (e.g., data imported manually),
        // keep incrementing until we find a free slot.
        while (Loan::where('loan_number', $candidate)->exists()) {
            $sequence++;
            $candidate = sprintf('LOAN-%d-%06d', $year, $sequence);
        }

        return $candidate;
    }
}
