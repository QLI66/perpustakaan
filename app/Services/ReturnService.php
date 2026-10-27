<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Support\LoanLateness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Process a return of a borrowed book copy (Loan Item).
     *
     *
     * @throws BusinessRuleException
     */
    public function returnItem(int $loanItemId, ?Carbon $returnedAt = null): LoanItem
    {
        return DB::transaction(function () use ($loanItemId, $returnedAt) {
            $returnedAt = $returnedAt ?? now();

            // 1. Lock LoanItem
            $item = LoanItem::with('bookCopy')->lockForUpdate()->findOrFail($loanItemId);

            // 2. Validate LoanItem Status
            if ($item->status !== 'ACTIVE' || $item->returned_at !== null) {
                throw new BusinessRuleException('Loan item has already been returned or is not active.');
            }

            // 3. Lock and Validate BookCopy Status
            // Since we loaded it via 'with', let's re-query with lock to be absolutely safe
            $bookCopy = $item->bookCopy()->lockForUpdate()->firstOrFail();

            if ($bookCopy->status !== 'BORROWED') {
                throw new BusinessRuleException('Loan item and book copy status are inconsistent. Book copy is not in BORROWED status.');
            }

            // 4. Check for fines
            $isHourly = $item->duration_unit === 'hour';
            $lateUnits = $isHourly
                ? LoanLateness::lateHours($item->due_at, $returnedAt)
                : LoanLateness::lateDays($item->due_at, $returnedAt);

            if ($lateUnits > 0) {
                // If fine already exists, throw exception instead of silencing or duplicating
                if (Fine::where('loan_item_id', $item->id)->exists()) {
                    throw new BusinessRuleException('A fine already exists for this loan item.');
                }

                $lateUnit = $isHourly ? 'hour' : 'day';
                $rate = $isHourly ? 500 : config('library.fine_rate_per_day', 1000);
                $amount = $lateUnits * $rate;

                Fine::create([
                    'loan_item_id' => $item->id,
                    'late_days' => $isHourly ? 0 : $lateUnits,
                    'rate_per_day' => $isHourly ? 0 : $rate,
                    'late_unit' => $lateUnit,
                    'late_units' => $lateUnits,
                    'rate_per_unit' => $rate,
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'status' => 'UNPAID',
                    'assessed_at' => clone $returnedAt,
                ]);
            }

            // 5. Update LoanItem
            $item->status = 'RETURNED';
            $item->returned_at = clone $returnedAt;
            $item->save();

            // 6. Update BookCopy
            $bookCopy->status = 'AVAILABLE';
            $bookCopy->save();

            // 7. Check and Update Loan Status
            // Lock Loan
            $loan = Loan::lockForUpdate()->findOrFail($item->loan_id);

            // Check if there are any remaining ACTIVE items in this loan
            $hasActiveItems = LoanItem::where('loan_id', $loan->id)
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $hasActiveItems) {
                $loan->status = 'COMPLETED';
                $loan->save();
            }

            // Update the loaded relation to reflect the new state immediately
            $item->setRelation('bookCopy', $bookCopy);

            return $item;
        });
    }
}
