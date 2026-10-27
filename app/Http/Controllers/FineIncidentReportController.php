<?php

namespace App\Http\Controllers;

use App\Http\Requests\FineReportIndexRequest;
use App\Http\Requests\IncidentReportIndexRequest;
use App\Services\FineIncidentReportService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FineIncidentReportController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly FineIncidentReportService $reportService
    ) {}

    /**
     * GET /api/reports/fines
     */
    public function fines(FineReportIndexRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('fines.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getFineReport(
            $dateFrom,
            $dateTo,
            $request->filled('member_id') ? (int) $request->input('member_id') : null,
            $request->input('status')
        );

        return $this->successResponse(
            $result,
            'Fine report retrieved successfully.'
        );
    }

    /**
     * GET /api/reports/incidents
     */
    public function incidents(IncidentReportIndexRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('book_incidents.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getIncidentReport(
            $dateFrom,
            $dateTo,
            $request->filled('member_id') ? (int) $request->input('member_id') : null,
            $request->input('type'),
            $request->input('status')
        );

        return $this->successResponse(
            $result,
            'Incident report retrieved successfully.'
        );
    }
}
