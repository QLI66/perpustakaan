<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReservationReportIndexRequest;
use App\Services\ReservationReportService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReservationReportController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly ReservationReportService $reportService
    ) {}

    /**
     * GET /api/reports/reservations
     */
    public function reservations(
        ReservationReportIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('reservations.manage'),
            403,
            'This action is unauthorized.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : null;

        $memberId = $request->filled('member_id')
            ? (int) $request->input('member_id')
            : null;

        $bookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $result = $this->reportService->getReservationReport(
            $dateFrom,
            $dateTo,
            $memberId,
            $bookId,
            $request->input('status')
        );

        return $this->successResponse(
            $result,
            'Reservation report retrieved successfully.'
        );
    }
}
