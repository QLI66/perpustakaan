<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoanReportIndexRequest;
use App\Http\Requests\ReturnReportIndexRequest;
use App\Services\LoanReportService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoanReportController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly LoanReportService $reportService
    ) {}

    /**
     * GET /api/reports/loans
     */
    public function loans(LoanReportIndexRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('loans.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getLoanReport(
            $dateFrom,
            $dateTo,
            $request->input('member_id'),
            $request->input('status')
        );

        return $this->successResponse(
            $result,
            'Loan report retrieved successfully.'
        );
    }

    /**
     * GET /api/reports/returns
     */
    public function returns(ReturnReportIndexRequest $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('loans.view'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $result = $this->reportService->getReturnReport(
            $dateFrom,
            $dateTo,
            $request->input('member_id')
        );

        return $this->successResponse(
            $result,
            'Return report retrieved successfully.'
        );
    }
}
