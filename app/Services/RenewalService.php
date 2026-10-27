<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\BookIncident;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Support\LoanLateness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RenewalService
{
    /**
     * Process a renewal of an active borrowed loan item.
     *
     *
     * @throws BusinessRuleException
     */
    public function renewItem(int $loanItemId, ?Carbon $renewedAt = null): LoanItem
    {
        return DB::transaction(function () use ($loanItemId, $renewedAt) {
            $renewedAt = $renewedAt ?? now();

            // ── Step 1: Lock LoanItem ────────────────────────────────────────
            $item = LoanItem::lockForUpdate()->findOrFail($loanItemId);

            // ── Step 2: Validate LoanItem status ─────────────────────────────
            if ($item->status !== 'ACTIVE') {
                throw new BusinessRuleException('Loan item is not active and cannot be renewed.');
            }

            if ($item->duration_unit === 'hour') {
                throw new BusinessRuleException('Hourly loan items cannot be renewed.');
            }

            // ── Step 3: Check overdue status ─────────────────────────────────
            if (LoanLateness::lateDays($item->due_at, $renewedAt) > 0) {
                throw new BusinessRuleException(
                    'Loan item is overdue and cannot be renewed.'
                );
            }

            // ── Step 4: Validate renewal_count ───────────────────────────────
            if ($item->renewal_count >= 1) {
                throw new BusinessRuleException('Maximum renewal limit has been reached.');
            }

            // ── Step 5: Load Loan and Member ─────────────────────────────────
            $loan = Loan::with('member')->lockForUpdate()->findOrFail($item->loan_id);
            $member = $loan->member;

            // ── Step 6: Consistency check (BookCopy must still be BORROWED) ──
            $bookCopy = $item->bookCopy()->lockForUpdate()->firstOrFail();
            if ($bookCopy->status !== 'BORROWED') {
                throw new BusinessRuleException('Loan item and book copy status are inconsistent.');
            }

            // ── Step 7: Check all fines belonging to this member ─────────────
            // Traverse: Member -> Loans -> LoanItems -> Fines
            $memberLoanItemIds = LoanItem::whereIn(
                'loan_id',
                Loan::where('member_id', $member->id)->select('id')
            )->pluck('id');

            $hasUnpaidFine = Fine::whereIn('loan_item_id', $memberLoanItemIds)
                ->whereIn('status', ['UNPAID', 'PARTIAL'])
                ->exists();

            if ($hasUnpaidFine) {
                throw new BusinessRuleException('All outstanding fines must be fully paid before renewal.');
            }

            // ── Step 7: Check for unresolved LOST/DAMAGED items ──────────────
            $hasUnresolvedIncident = BookIncident::whereIn('loan_item_id', $memberLoanItemIds)
                ->where('status', 'OPEN')
                ->exists();

            if ($hasUnresolvedIncident) {
                throw new BusinessRuleException('Member has unresolved lost or damaged book obligations.');
            }

            // ── Step 8: Calculate new due_at (calendar date) ─────────────────
            $renewalDays = config('library.renewal_days', 3);
            $newDueAt = Carbon::parse($renewedAt)->startOfDay()->addDays($renewalDays);

            // ── Step 9: Update LoanItem ───────────────────────────────────────
            $item->due_at = $newDueAt;
            $item->renewal_count += 1;
            $item->save();

            return $item;
        });
    }
}
