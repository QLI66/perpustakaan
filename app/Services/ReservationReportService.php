<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReservationReportService
{
    /**
     * Generate reservation report.
     *
     * @return array<string, mixed>
     */
    public function getReservationReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?int $memberId = null,
        ?int $bookId = null,
        ?string $status = null
    ): array {
        $query = Reservation::query();

        $this->applyFilters(
            $query,
            $dateFrom,
            $dateTo,
            $memberId,
            $bookId,
            $status
        );

        $totalReservations = (clone $query)->count();

        $statusBreakdown = (clone $query)
            ->select([
                'status',
                DB::raw('COUNT(*) AS reservation_count'),
            ])
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(
                fn ($row): array => [
                    $row->status => (int) $row->reservation_count,
                ]
            )
            ->all();

        $pendingCount = (int) ($statusBreakdown['PENDING'] ?? 0);
        $readyCount = (int) ($statusBreakdown['READY'] ?? 0);
        $fulfilledCount = (int) ($statusBreakdown['FULFILLED'] ?? 0);
        $cancelledCount = (int) ($statusBreakdown['CANCELLED'] ?? 0);
        $expiredCount = (int) ($statusBreakdown['EXPIRED'] ?? 0);

        $activeCount = $pendingCount + $readyCount;

        $fulfillmentRate = $totalReservations > 0
            ? round(
                ($fulfilledCount / $totalReservations) * 100,
                2
            )
            : 0.0;

        return [
            'summary' => [
                'total_reservations' => $totalReservations,
                'pending_count' => $pendingCount,
                'ready_count' => $readyCount,
                'fulfilled_count' => $fulfilledCount,
                'cancelled_count' => $cancelledCount,
                'expired_count' => $expiredCount,
                'active_count' => $activeCount,
                'fulfillment_rate' => $fulfillmentRate,
                'status_breakdown' => [
                    'PENDING' => $pendingCount,
                    'READY' => $readyCount,
                    'FULFILLED' => $fulfilledCount,
                    'CANCELLED' => $cancelledCount,
                    'EXPIRED' => $expiredCount,
                ],
                'period_reservations' => $totalReservations,
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'member_id' => $memberId,
                'book_id' => $bookId,
                'status' => $status,
            ],
        ];
    }

    private function applyFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?int $memberId,
        ?int $bookId,
        ?string $status
    ): void {
        if ($dateFrom !== null) {
            $query->whereDate(
                'reserved_at',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo !== null) {
            $query->whereDate(
                'reserved_at',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($memberId !== null) {
            $query->where(
                'member_id',
                $memberId
            );
        }

        if ($bookId !== null) {
            $query->where(
                'book_id',
                $bookId
            );
        }

        if ($status !== null) {
            $query->where(
                'status',
                $status
            );
        }
    }
}
