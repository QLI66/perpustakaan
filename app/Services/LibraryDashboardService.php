<?php

namespace App\Services;

use App\Models\Acquisition;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\StockOpname;
use Carbon\Carbon;

class LibraryDashboardService
{
    public function getSummary(?Carbon $now = null): array
    {
        $now ??= now();

        return [
            'totals' => $this->getTotals(),

            'loans' => $this->getLoanSummary($now),

            'fines' => $this->getFineSummary(),

            'incidents' => [
                'open' => BookIncident::query()
                    ->where('status', 'OPEN')
                    ->count(),
            ],

            'acquisitions' => $this->getAcquisitionSummary($now),

            'stock_opnames' => $this->getStockOpnameSummary(),

            'periods' => [
                'today' => $this->getPeriodSummary(
                    $now->copy()->startOfDay(),
                    $now
                ),

                'this_week' => $this->getPeriodSummary(
                    $now->copy()->startOfWeek(),
                    $now
                ),

                'this_month' => $this->getPeriodSummary(
                    $now->copy()->startOfMonth(),
                    $now
                ),

                'this_year' => $this->getPeriodSummary(
                    $now->copy()->startOfYear(),
                    $now
                ),
            ],
        ];
    }

    private function getTotals(): array
    {
        return [
            'books' => Book::query()->count(),

            'book_copies' => BookCopy::query()->count(),

            'members' => Member::query()->count(),

            'active_members' => Member::query()
                ->where('status', 'ACTIVE')
                ->count(),

            'active_loans' => Loan::query()
                ->where('status', 'ACTIVE')
                ->count(),

            'active_loan_items' => LoanItem::query()
                ->where('status', 'ACTIVE')
                ->count(),

            'available_book_copies' => BookCopy::query()
                ->where('status', 'AVAILABLE')
                ->count(),

            'borrowed_book_copies' => BookCopy::query()
                ->where('status', 'BORROWED')
                ->count(),

            'lost_book_copies' => BookCopy::query()
                ->where('status', 'LOST')
                ->count(),

            'damaged_book_copies' => BookCopy::query()
                ->where('status', 'DAMAGED')
                ->count(),
        ];
    }

    private function getLoanSummary(Carbon $now): array
    {
        $overdueItems = LoanItem::query()
            ->where('status', 'ACTIVE')
            ->where('due_at', '<', $now);

        return [
            'active' => Loan::query()
                ->where('status', 'ACTIVE')
                ->count(),

            'overdue_items' => $overdueItems->count(),

            'overdue_members' => LoanItem::query()
                ->where('loan_items.status', 'ACTIVE')
                ->where('loan_items.due_at', '<', $now)
                ->whereHas(
                    'loan',
                    fn ($query) => $query->whereNotNull('member_id')
                )
                ->join(
                    'loans',
                    'loan_items.loan_id',
                    '=',
                    'loans.id'
                )
                ->distinct('loans.member_id')
                ->count('loans.member_id'),
        ];
    }

    private function getFineSummary(): array
    {
        $outstanding = Fine::query()
            ->whereIn(
                'status',
                ['UNPAID', 'PARTIAL']
            );

        $rows = $outstanding->get([
            'amount',
            'amount_paid',
        ]);

        $outstandingAmount = $rows->sum(
            fn ($fine) => max(
                0,
                (float) $fine->amount
                - (float) $fine->amount_paid
            )
        );

        return [
            'unpaid_count' => Fine::query()
                ->where('status', 'UNPAID')
                ->count(),

            'partial_count' => Fine::query()
                ->where('status', 'PARTIAL')
                ->count(),

            'outstanding_count' => $rows->count(),

            'outstanding_amount' => number_format(
                $outstandingAmount,
                2,
                '.',
                ''
            ),

            'paid_count' => Fine::query()
                ->where('status', 'PAID')
                ->count(),

            'total_assessed_amount' => number_format(
                (float) Fine::query()->sum('amount'),
                2,
                '.',
                ''
            ),

            'total_paid_amount' => number_format(
                (float) Fine::query()->sum('amount_paid'),
                2,
                '.',
                ''
            ),
        ];
    }

    private function getAcquisitionSummary(Carbon $now): array
    {
        return [
            'total' => Acquisition::query()->count(),

            'this_month' => Acquisition::query()
                ->whereBetween(
                    'acquisition_date',
                    [
                        $now->copy()->startOfMonth()->toDateString(),
                        $now->copy()->endOfMonth()->toDateString(),
                    ]
                )
                ->count(),

            'this_year' => Acquisition::query()
                ->whereBetween(
                    'acquisition_date',
                    [
                        $now->copy()->startOfYear()->toDateString(),
                        $now->copy()->endOfYear()->toDateString(),
                    ]
                )
                ->count(),

            'this_year_amount' => number_format(
                (float) Acquisition::query()
                    ->whereBetween(
                        'acquisition_date',
                        [
                            $now->copy()->startOfYear()->toDateString(),
                            $now->copy()->endOfYear()->toDateString(),
                        ]
                    )
                    ->sum('total_amount'),
                2,
                '.',
                ''
            ),
        ];
    }

    private function getStockOpnameSummary(): array
    {
        $latest = StockOpname::query()
            ->with('location')
            ->latest('opname_date')
            ->latest('id')
            ->first();

        return [
            'total' => StockOpname::query()->count(),

            'draft' => StockOpname::query()
                ->where('status', 'DRAFT')
                ->count(),

            'in_progress' => StockOpname::query()
                ->where('status', 'IN_PROGRESS')
                ->count(),

            'completed' => StockOpname::query()
                ->where('status', 'COMPLETED')
                ->count(),

            'cancelled' => StockOpname::query()
                ->where('status', 'CANCELLED')
                ->count(),

            'latest' => $latest
                ? [
                    'id' => $latest->id,
                    'opname_number' => $latest->opname_number,
                    'opname_date' => $latest->opname_date?->toDateString(),
                    'status' => $latest->status,
                    'location' => $latest->location
                        ? [
                            'id' => $latest->location->id,
                            'code' => $latest->location->code,
                            'name' => $latest->location->name,
                        ]
                        : null,
                ]
                : null,
        ];
    }

    private function getPeriodSummary(
        Carbon $start,
        Carbon $end
    ): array {
        return [
            'loans' => Loan::query()
                ->whereBetween(
                    'borrowed_at',
                    [$start, $end]
                )
                ->count(),

            'returned_items' => LoanItem::query()
                ->whereNotNull('returned_at')
                ->whereBetween(
                    'returned_at',
                    [$start, $end]
                )
                ->count(),

            'fines_assessed' => Fine::query()
                ->whereBetween(
                    'assessed_at',
                    [$start, $end]
                )
                ->count(),

            'fines_assessed_amount' => number_format(
                (float) Fine::query()
                    ->whereBetween(
                        'assessed_at',
                        [$start, $end]
                    )
                    ->sum('amount'),
                2,
                '.',
                ''
            ),

            'acquisitions' => Acquisition::query()
                ->whereBetween(
                    'acquisition_date',
                    [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]
                )
                ->count(),

            'acquisition_amount' => number_format(
                (float) Acquisition::query()
                    ->whereBetween(
                        'acquisition_date',
                        [
                            $start->toDateString(),
                            $end->toDateString(),
                        ]
                    )
                    ->sum('total_amount'),
                2,
                '.',
                ''
            ),

            'stock_opnames' => StockOpname::query()
                ->whereBetween(
                    'opname_date',
                    [
                        $start->toDateString(),
                        $end->toDateString(),
                    ]
                )
                ->count(),
        ];
    }
}
