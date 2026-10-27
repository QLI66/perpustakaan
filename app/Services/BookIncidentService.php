<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\Loan;
use App\Models\LoanItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookIncidentService
{
    /**
     * Open a new book incident for a LOST or DAMAGED LoanItem.
     */
    public function openIncident(
        int $loanItemId,
        string $type,
        ?Carbon $openedAt = null,
        ?string $notes = null
    ): BookIncident {

        if (! in_array($type, ['LOST', 'DAMAGED'], true)) {
            throw new BusinessRuleException('Incident type must be LOST or DAMAGED.');
        }

        return DB::transaction(function () use ($loanItemId, $type, $openedAt, $notes) {
            $openedAt = $openedAt ?? now();

            // 1. Lock LoanItem
            $item = LoanItem::lockForUpdate()->findOrFail($loanItemId);

            // 2. Transition LoanItem status if ACTIVE, or validate if already LOST/DAMAGED
            if ($item->status === 'ACTIVE') {
                $item->status = $type === 'LOST' ? 'LOST' : 'DAMAGED';
                $item->save();
            } elseif (! in_array($item->status, ['LOST', 'DAMAGED'])) {
                throw new BusinessRuleException('Loan item must be ACTIVE, LOST or DAMAGED.');
            }

            $loan = Loan::lockForUpdate()->findOrFail($item->loan_id);
            $hasActiveItems = LoanItem::where('loan_id', $loan->id)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $hasActiveItems && $loan->status !== 'COMPLETED') {
                $loan->status = 'COMPLETED';
                $loan->save();
            }

            // 3. Ensure no duplicate incident
            if (BookIncident::where('loan_item_id', $item->id)->exists()) {
                throw new BusinessRuleException('Book incident already exists.');
            }

            // 4. Lock BookCopy
            $copy = $item->bookCopy()->lockForUpdate()->firstOrFail();

            // 5. Transition BookCopy if BORROWED
            if ($copy->status === 'BORROWED') {
                $copy->status = $type === 'LOST' ? 'LOST' : 'DAMAGED';
                $copy->save();
            }

            // 6. Validate BookCopy status consistency
            $expectedCopyStatus = $type === 'LOST' ? 'LOST' : 'DAMAGED';
            if ($copy->status !== $expectedCopyStatus) {
                throw new BusinessRuleException("Book copy status must be {$expectedCopyStatus} for a {$type} incident.");
            }

            // 7. Determine amount_due from acquisition_price
            $amountDue = $copy->acquisition_price ?? null;

            // 7. Create incident
            return BookIncident::create([
                'loan_item_id' => $item->id,
                'book_copy_id' => $copy->id,
                'type' => $type,
                'status' => 'OPEN',
                'amount_due' => $amountDue,
                'opened_at' => $openedAt,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Resolve an incident via monetary payment.
     */
    public function resolveWithPayment(
        int $incidentId,
        int $userId,
        ?Carbon $resolvedAt = null,
        ?string $notes = null
    ): BookIncident {
        return DB::transaction(function () use ($incidentId, $userId, $resolvedAt, $notes) {
            $resolvedAt = $resolvedAt ?? now();

            // 1. Lock incident
            $incident = BookIncident::lockForUpdate()->findOrFail($incidentId);

            // 2. Validate status
            if ($incident->status !== 'OPEN') {
                throw new BusinessRuleException('Book incident is already resolved.');
            }

            // 3. amount_due must be set
            if ($incident->amount_due === null) {
                throw new BusinessRuleException('Acquisition price is required for monetary resolution.');
            }

            // 4. Update
            $incident->amount_paid = $incident->amount_due;
            $incident->resolution_type = 'PAID_MONEY';
            $incident->status = 'RESOLVED';
            $incident->resolved_at = $resolvedAt;
            $incident->resolved_by = $userId;
            if ($notes !== null) {
                $incident->notes = $notes;
            }
            $incident->save();

            return $incident;
        });
    }

    /**
     * Resolve an incident via book replacement.
     */
    public function resolveWithReplacement(
        int $incidentId,
        int $replacementBookCopyId,
        int $userId,
        ?Carbon $resolvedAt = null,
        ?string $notes = null
    ): BookIncident {
        return DB::transaction(function () use ($incidentId, $replacementBookCopyId, $userId, $resolvedAt, $notes) {
            $resolvedAt = $resolvedAt ?? now();

            // 1. Lock incident
            $incident = BookIncident::lockForUpdate()->findOrFail($incidentId);

            // 2. Validate OPEN
            if ($incident->status !== 'OPEN') {
                throw new BusinessRuleException('Book incident is already resolved.');
            }

            // 3. Lock replacement BookCopy
            $replacement = BookCopy::lockForUpdate()->findOrFail($replacementBookCopyId);

            // 4. Replacement must be AVAILABLE
            if ($replacement->status !== 'AVAILABLE') {
                throw new BusinessRuleException('Replacement book copy must be AVAILABLE.');
            }

            // 5. Replacement must belong to the same Book as original
            $originalCopy = BookCopy::findOrFail($incident->book_copy_id);
            if ($replacement->book_id !== $originalCopy->book_id) {
                throw new BusinessRuleException('Replacement book copy must belong to the same book.');
            }

            // 6. Update
            $incident->replacement_book_copy_id = $replacement->id;
            $incident->resolution_type = 'REPLACED_BOOK';
            $incident->status = 'RESOLVED';
            $incident->resolved_at = $resolvedAt;
            $incident->resolved_by = $userId;
            if ($notes !== null) {
                $incident->notes = $notes;
            }
            $incident->save();

            // Original copy stays LOST/DAMAGED — no status change.
            // Replacement copy stays AVAILABLE — no status change.

            return $incident;
        });
    }
}
