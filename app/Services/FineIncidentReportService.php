<?php

namespace App\Services;

use App\Models\BookIncident;
use App\Models\Fine;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FineIncidentReportService
{
    // ─── Fine Report ──────────────────────────────────────────────────────────

    /**
     * Get a summary report for fines.
     *
     * @param  Carbon|null  $dateFrom  Filter by assessed_at >= dateFrom
     * @param  Carbon|null  $dateTo  Filter by assessed_at <= dateTo
     * @param  int|null  $memberId  Filter by member (via LoanItem -> Loan)
     * @param  string|null  $status  Filter by fine status (UNPAID|PARTIAL|PAID)
     */
    public function getFineReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?int $memberId = null,
        ?string $status = null
    ): array {
        $base = Fine::query();
        $this->applyFineFilters($base, $dateFrom, $dateTo, $memberId, $status);

        $totalFines = (clone $base)->count();
        $unpaidCount = (clone $base)->where('status', 'UNPAID')->count();
        $partialCount = (clone $base)->where('status', 'PARTIAL')->count();
        $paidCount = (clone $base)->where('status', 'PAID')->count();

        $totalAmount = (float) ((clone $base)->sum('amount') ?? 0);
        $totalAmountPaid = (float) ((clone $base)->sum('amount_paid') ?? 0);
        $totalOutstanding = round($totalAmount - $totalAmountPaid, 2);

        // Period sub-set: fines assessed within the given date range
        $periodBase = Fine::query();
        $this->applyFineFilters($periodBase, $dateFrom, $dateTo, $memberId, $status);

        // When no date filter, period == overall
        $periodCount = $totalFines;
        $periodAmount = $totalAmount;

        // If explicit date range given, compute period amounts already captured by filter
        // (the date filter is already applied, so period == filtered base)
        if ($dateFrom || $dateTo) {
            // Already filtered, so counts are identical — no extra query needed
        }

        return [
            'summary' => [
                'total_fines' => $totalFines,
                'unpaid_count' => $unpaidCount,
                'partial_count' => $partialCount,
                'paid_count' => $paidCount,
                'total_amount' => $totalAmount,
                'total_amount_paid' => $totalAmountPaid,
                'total_outstanding' => $totalOutstanding,
                'period_fine_count' => $periodCount,
                'period_fine_amount' => $periodAmount,
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'member_id' => $memberId,
                'status' => $status,
            ],
        ];
    }

    // ─── Incident Report ──────────────────────────────────────────────────────

    /**
     * Get a summary report for book incidents.
     *
     * @param  Carbon|null  $dateFrom  Filter by opened_at >= dateFrom
     * @param  Carbon|null  $dateTo  Filter by opened_at <= dateTo
     * @param  int|null  $memberId  Filter by member (via LoanItem -> Loan)
     * @param  string|null  $type  Filter by incident type (LOST|DAMAGED)
     * @param  string|null  $status  Filter by incident status (OPEN|RESOLVED)
     */
    public function getIncidentReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?int $memberId = null,
        ?string $type = null,
        ?string $status = null
    ): array {
        $base = BookIncident::query();
        $this->applyIncidentFilters($base, $dateFrom, $dateTo, $memberId, $type, $status);

        $totalIncidents = (clone $base)->count();
        $openCount = (clone $base)->where('status', 'OPEN')->count();
        $resolvedCount = (clone $base)->where('status', 'RESOLVED')->count();
        $lostCount = (clone $base)->where('type', 'LOST')->count();
        $damagedCount = (clone $base)->where('type', 'DAMAGED')->count();

        $totalAmountDue = (float) ((clone $base)->sum('amount_due') ?? 0);
        $totalAmountPaid = (float) ((clone $base)->sum('amount_paid') ?? 0);
        $totalOutstanding = round($totalAmountDue - $totalAmountPaid, 2);

        // Period count (incidents opened in the date range — same as base when date filter applied)
        $periodCount = $totalIncidents;

        return [
            'summary' => [
                'total_incidents' => $totalIncidents,
                'open_count' => $openCount,
                'resolved_count' => $resolvedCount,
                'lost_count' => $lostCount,
                'damaged_count' => $damagedCount,
                'total_amount_due' => $totalAmountDue,
                'total_amount_paid' => $totalAmountPaid,
                'total_outstanding' => $totalOutstanding,
                'period_opened_count' => $periodCount,
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'member_id' => $memberId,
                'type' => $type,
                'status' => $status,
            ],
        ];
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function applyFineFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?int $memberId,
        ?string $status
    ): void {
        if ($dateFrom) {
            $query->whereDate('assessed_at', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('assessed_at', '<=', $dateTo->toDateString());
        }

        if ($memberId !== null) {
            $query->whereHas(
                'loanItem.loan',
                fn (Builder $q) => $q->where('member_id', $memberId)
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
    }

    private function applyIncidentFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?int $memberId,
        ?string $type,
        ?string $status
    ): void {
        if ($dateFrom) {
            $query->whereDate('opened_at', '>=', $dateFrom->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('opened_at', '<=', $dateTo->toDateString());
        }

        if ($memberId !== null) {
            $query->whereHas(
                'loanItem.loan',
                fn (Builder $q) => $q->where('member_id', $memberId)
            );
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
    }
}
