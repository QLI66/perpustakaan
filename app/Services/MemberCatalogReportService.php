<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MemberCatalogReportService
{
    /**
     * @return array<string, mixed>
     */
    public function getMemberReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?string $status = null
    ): array {
        $query = Member::query();

        $this->applyMemberFilters(
            $query,
            $dateFrom,
            $dateTo,
            $status
        );

        $totalMembers = (clone $query)->count();

        $statusBreakdown = (clone $query)
            ->select([
                'status',
                DB::raw('COUNT(*) AS member_count'),
            ])
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(
                fn ($row): array => [
                    $row->status => (int) $row->member_count,
                ]
            )
            ->all();

        $activeCount = (int) ($statusBreakdown['ACTIVE'] ?? 0);
        $inactiveCount = (int) ($statusBreakdown['INACTIVE'] ?? 0);
        $suspendedCount = (int) ($statusBreakdown['SUSPENDED'] ?? 0);

        return [
            'summary' => [
                'total_members' => $totalMembers,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'suspended_count' => $suspendedCount,
                'period_members' => $totalMembers,
                'status_breakdown' => [
                    'ACTIVE' => $activeCount,
                    'INACTIVE' => $inactiveCount,
                    'SUSPENDED' => $suspendedCount,
                ],
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'status' => $status,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCatalogReport(
        ?string $status = null,
        ?int $locationId = null
    ): array {
        $copyQuery = BookCopy::query();

        $this->applyCatalogFilters(
            $copyQuery,
            $status,
            $locationId
        );

        $totalCopies = (clone $copyQuery)->count();

        $totalBooks = (clone $copyQuery)
            ->distinct('book_id')
            ->count('book_id');

        $statusBreakdown = (clone $copyQuery)
            ->select([
                'status',
                DB::raw('COUNT(*) AS copy_count'),
            ])
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(
                fn ($row): array => [
                    $row->status => (int) $row->copy_count,
                ]
            )
            ->all();

        $availableCount = (int) ($statusBreakdown['AVAILABLE'] ?? 0);
        $borrowedCount = (int) ($statusBreakdown['BORROWED'] ?? 0);
        $reservedCount = (int) ($statusBreakdown['RESERVED'] ?? 0);
        $lostCount = (int) ($statusBreakdown['LOST'] ?? 0);
        $damagedCount = (int) ($statusBreakdown['DAMAGED'] ?? 0);
        $maintenanceCount = (int) ($statusBreakdown['MAINTENANCE'] ?? 0);

        return [
            'summary' => [
                'total_books' => $totalBooks,
                'total_copies' => $totalCopies,
                'available_copies' => $availableCount,
                'borrowed_copies' => $borrowedCount,
                'reserved_copies' => $reservedCount,
                'lost_copies' => $lostCount,
                'damaged_copies' => $damagedCount,
                'maintenance_copies' => $maintenanceCount,
                'status_breakdown' => [
                    'AVAILABLE' => $availableCount,
                    'BORROWED' => $borrowedCount,
                    'RESERVED' => $reservedCount,
                    'LOST' => $lostCount,
                    'DAMAGED' => $damagedCount,
                    'MAINTENANCE' => $maintenanceCount,
                ],
            ],
            'filters_applied' => [
                'status' => $status,
                'location_id' => $locationId,
            ],
        ];
    }

    private function applyMemberFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?string $status
    ): void {
        if ($dateFrom !== null) {
            $query->whereDate(
                'joined_at',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo !== null) {
            $query->whereDate(
                'joined_at',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        }
    }

    private function applyCatalogFilters(
        Builder $query,
        ?string $status,
        ?int $locationId
    ): void {
        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }
    }
}
