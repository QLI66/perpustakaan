<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly FinanceService $financeService) {}

    /**
     * GET /api/finance/summary
     */
    public function summary(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('dashboard.view'), 403, 'This action is unauthorized.');

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : null;

        $summary = $this->financeService->getSummary($dateFrom, $dateTo);

        return $this->successResponse($summary, 'Finance summary retrieved successfully.');
    }

    /**
     * GET /api/finance/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('dashboard.view'), 403, 'This action is unauthorized.');

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->input('date_from')) : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->input('date_to')) : null;
        $type = $request->input('type');
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 15);

        $transactions = $this->financeService->getTransactions($perPage, $dateFrom, $dateTo, $type, $search);

        return $this->successResponse([
            'items' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ], 'Finance transactions retrieved successfully.');
    }
}
