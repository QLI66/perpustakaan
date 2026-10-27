<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class LoanReportService
{
    /**
     * Get loan summary and filtered loan records.
     */
    public function getLoanReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?int $memberId = null,
        ?string $status = null
    ): array {
        $query = Loan::query();

        $this->applyLoanFilters(
            $query,
            $dateFrom,
            $dateTo,
            $memberId,
            $status
        );

        $totalLoans = (clone $query)->count();

        $activeLoans = (clone $query)
            ->where('status', 'ACTIVE')
            ->count();

        $completedLoans = (clone $query)
            ->where('status', 'COMPLETED')
            ->count();

        $totalLoanItems = (clone $query)
            ->withCount('items')
            ->get()
            ->sum('items_count');

        $overdueLoanItems = LoanItem::query()
            ->where('loan_items.status', 'ACTIVE')
            ->where('loan_items.due_at', '<', now())
            ->whereHas('loan', function ($loanQuery) use (
                $dateFrom,
                $dateTo,
                $memberId,
                $status
            ) {
                if ($dateFrom) {
                    $loanQuery->whereDate(
                        'borrowed_at',
                        '>=',
                        $dateFrom->toDateString()
                    );
                }

                if ($dateTo) {
                    $loanQuery->whereDate(
                        'borrowed_at',
                        '<=',
                        $dateTo->toDateString()
                    );
                }

                if ($memberId !== null) {
                    $loanQuery->where('member_id', $memberId);
                }

                if ($status !== null) {
                    $loanQuery->where('status', $status);
                }
            })
            ->count();

        $items = (clone $query)
            ->with([
                'member',
                'items.bookCopy.book',
            ])
            ->withCount('items')
            ->orderByDesc('borrowed_at')
            ->get();

        return [
            'summary' => [
                'total_loans' => $totalLoans,
                'active_loans' => $activeLoans,
                'completed_loans' => $completedLoans,
                'total_loan_items' => $totalLoanItems,
                'overdue_loan_items' => $overdueLoanItems,
            ],
            'items' => $items,
        ];
    }

    /**
     * Get return summary and returned loan items.
     */
    public function getReturnReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?int $memberId = null
    ): array {
        $query = LoanItem::query()
            ->whereNotNull('returned_at');

        if ($dateFrom) {
            $query->whereDate(
                'returned_at',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo) {
            $query->whereDate(
                'returned_at',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($memberId !== null) {
            $query->whereHas(
                'loan',
                fn ($loanQuery) => $loanQuery->where(
                    'member_id',
                    $memberId
                )
            );
        }

        $totalReturnedItems = (clone $query)->count();

        $lateReturnedItems = (clone $query)
            ->whereColumn(
                'loan_items.returned_at',
                '>',
                'loan_items.due_at'
            )
            ->count();

        $onTimeReturnedItems = $totalReturnedItems - $lateReturnedItems;

        $items = $query
            ->with([
                'loan.member',
                'bookCopy.book',
            ])
            ->orderByDesc('returned_at')
            ->get();

        return [
            'summary' => [
                'total_returned_items' => $totalReturnedItems,
                'on_time_returned_items' => $onTimeReturnedItems,
                'late_returned_items' => $lateReturnedItems,
            ],
            'items' => $items,
        ];
    }

    private function applyLoanFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?int $memberId,
        ?string $status
    ): void {
        if ($dateFrom) {
            $query->whereDate(
                'borrowed_at',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo) {
            $query->whereDate(
                'borrowed_at',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
    }
}
