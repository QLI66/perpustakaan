<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportOverviewIndexRequest;
use App\Services\ReportingOverviewService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportingOverviewController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly ReportingOverviewService $overviewService
    ) {}

    /**
     * GET /api/reports/overview
     */
    public function overview(
        ReportOverviewIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('dashboard.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->overviewService->getOverview(
            $dateFrom,
            $dateTo
        );

        return $this->successResponse(
            $result,
            'Reporting overview retrieved successfully.'
        );
    }
}
