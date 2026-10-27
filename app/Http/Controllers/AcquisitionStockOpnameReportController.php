<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcquisitionReportIndexRequest;
use App\Http\Requests\StockOpnameReportIndexRequest;
use App\Services\AcquisitionStockOpnameReportService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AcquisitionStockOpnameReportController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly AcquisitionStockOpnameReportService $reportService
    ) {}

    /**
     * GET /api/reports/acquisitions
     */
    public function acquisitions(
        AcquisitionReportIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('acquisitions.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getAcquisitionReport(
            $dateFrom,
            $dateTo,
            $request->input('source_type'),
            $request->input('source_name')
        );

        return $this->successResponse(
            $result,
            'Acquisition report retrieved successfully.'
        );
    }

    /**
     * GET /api/reports/stock-opnames
     */
    public function stockOpnames(
        StockOpnameReportIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getStockOpnameReport(
            $dateFrom,
            $dateTo,
            $request->input('status'),
            $request->filled('location_id')
                ? (int) $request->input('location_id')
                : null
        );

        return $this->successResponse(
            $result,
            'Stock opname report retrieved successfully.'
        );
    }
}
