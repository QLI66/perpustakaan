<?php

namespace App\Services;

use App\Models\Acquisition;
use App\Models\AcquisitionItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AcquisitionStockOpnameReportService
{
    /**
     * Generate acquisition report.
     *
     * @return array<string, mixed>
     */
    public function getAcquisitionReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?string $sourceType = null,
        ?string $sourceName = null
    ): array {
        $query = Acquisition::query();

        $this->applyAcquisitionFilters(
            $query,
            $dateFrom,
            $dateTo,
            $sourceType,
            $sourceName
        );

        $totalAcquisitions = (clone $query)->count();

        $totalAmount = (float) (
            (clone $query)->sum('total_amount')
        );

        $acquisitionIds = (clone $query)->select('id');

        $itemsQuery = AcquisitionItem::query()
            ->whereIn('acquisition_id', $acquisitionIds);

        $totalAcquisitionItems = $itemsQuery->count();

        $totalQuantity = (int) $itemsQuery->sum('quantity');

        $sourceBreakdown = (clone $query)
            ->select([
                'source_type',
                DB::raw('COUNT(*) AS acquisition_count'),
                DB::raw(
                    'COALESCE(SUM(total_amount), 0) AS total_amount'
                ),
            ])
            ->groupBy('source_type')
            ->orderBy('source_type')
            ->get()
            ->map(
                fn ($row): array => [
                    'source_type' => $row->source_type,
                    'acquisition_count' => (int) $row->acquisition_count,
                    'total_amount' => (float) $row->total_amount,
                ]
            )
            ->values()
            ->all();

        return [
            'summary' => [
                'total_acquisitions' => $totalAcquisitions,
                'total_acquisition_items' => $totalAcquisitionItems,
                'total_quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
                'period_acquisitions' => $totalAcquisitions,
                'period_quantity' => $totalQuantity,
                'period_amount' => $totalAmount,
                'source_breakdown' => $sourceBreakdown,
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'source_type' => $sourceType,
                'source_name' => $sourceName,
            ],
        ];
    }

    /**
     * Generate stock opname report.
     *
     * @return array<string, mixed>
     */
    public function getStockOpnameReport(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null,
        ?string $status = null,
        ?int $locationId = null
    ): array {
        $query = StockOpname::query();

        $this->applyStockOpnameFilters(
            $query,
            $dateFrom,
            $dateTo,
            $status,
            $locationId
        );

        $totalOpnames = (clone $query)->count();

        $statusBreakdown = (clone $query)
            ->select([
                'status',
                DB::raw('COUNT(*) AS opname_count'),
            ])
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->mapWithKeys(
                fn ($row): array => [
                    $row->status => (int) $row->opname_count,
                ]
            )
            ->all();

        $opnameIds = (clone $query)->select('id');

        $itemsQuery = StockOpnameItem::query()
            ->whereIn('stock_opname_id', $opnameIds);

        $totalItems = $itemsQuery->count();

        $resultBreakdown = (clone $itemsQuery)
            ->select([
                'result',
                DB::raw('COUNT(*) AS item_count'),
            ])
            ->groupBy('result')
            ->orderBy('result')
            ->get()
            ->mapWithKeys(
                fn ($row): array => [
                    $row->result => (int) $row->item_count,
                ]
            )
            ->all();

        $notCheckedItems = (int) (
            $resultBreakdown['NOT_CHECKED'] ?? 0
        );

        $foundItems = (int) (
            $resultBreakdown['FOUND'] ?? 0
        );

        $missingItems = (int) (
            $resultBreakdown['MISSING'] ?? 0
        );

        $differentItems = (int) (
            $resultBreakdown['DIFFERENT'] ?? 0
        );

        $checkedItems = $foundItems
            + $missingItems
            + $differentItems;

        $completionPercentage = $totalItems > 0
            ? round(($checkedItems / $totalItems) * 100, 2)
            : 0.0;

        return [
            'summary' => [
                'total_opnames' => $totalOpnames,
                'draft_count' => (int) (
                    $statusBreakdown['DRAFT'] ?? 0
                ),
                'in_progress_count' => (int) (
                    $statusBreakdown['IN_PROGRESS'] ?? 0
                ),
                'completed_count' => (int) (
                    $statusBreakdown['COMPLETED'] ?? 0
                ),
                'cancelled_count' => (int) (
                    $statusBreakdown['CANCELLED'] ?? 0
                ),
                'total_expected_items' => $totalItems,
                'total_checked_items' => $checkedItems,
                'not_checked_items' => $notCheckedItems,
                'found_items' => $foundItems,
                'missing_items' => $missingItems,
                'different_items' => $differentItems,
                'completion_percentage' => $completionPercentage,
                'period_opnames' => $totalOpnames,
            ],
            'filters_applied' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'status' => $status,
                'location_id' => $locationId,
            ],
        ];
    }

    private function applyAcquisitionFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?string $sourceType,
        ?string $sourceName
    ): void {
        if ($dateFrom !== null) {
            $query->whereDate(
                'acquisition_date',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo !== null) {
            $query->whereDate(
                'acquisition_date',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($sourceType !== null) {
            $query->where(
                'source_type',
                $sourceType
            );
        }

        if ($sourceName !== null) {
            $query->where(
                'source_name',
                'ILIKE',
                "%{$sourceName}%"
            );
        }
    }

    private function applyStockOpnameFilters(
        Builder $query,
        ?Carbon $dateFrom,
        ?Carbon $dateTo,
        ?string $status,
        ?int $locationId
    ): void {
        if ($dateFrom !== null) {
            $query->whereDate(
                'opname_date',
                '>=',
                $dateFrom->toDateString()
            );
        }

        if ($dateTo !== null) {
            $query->whereDate(
                'opname_date',
                '<=',
                $dateTo->toDateString()
            );
        }

        if ($status !== null) {
            $query->where(
                'status',
                $status
            );
        }

        if ($locationId !== null) {
            $query->where(
                'location_id',
                $locationId
            );
        }
    }
}
