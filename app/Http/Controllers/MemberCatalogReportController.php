<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogReportIndexRequest;
use App\Http\Requests\MemberReportIndexRequest;
use App\Services\MemberCatalogReportService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MemberCatalogReportController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly MemberCatalogReportService $reportService
    ) {}

    /**
     * GET /api/reports/members
     */
    public function members(
        MemberReportIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('members.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getMemberReport(
            $dateFrom,
            $dateTo,
            $request->input('status')
        );

        return $this->successResponse(
            $result,
            'Member report retrieved successfully.'
        );
    }

    /**
     * GET /api/reports/catalog
     */
    public function catalog(
        CatalogReportIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('book_copies.view'),
            403,
            'This action is unauthorized.'
        );

        $result = $this->reportService->getCatalogReport(
            $request->input('status'),
            $request->filled('location_id')
                ? (int) $request->input('location_id')
                : null
        );

        return $this->successResponse(
            $result,
            'Catalog report retrieved successfully.'
        );
    }
}
