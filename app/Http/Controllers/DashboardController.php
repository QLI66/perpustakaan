<?php

namespace App\Http\Controllers;

use App\Services\LibraryDashboardService;
use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly LibraryDashboardService $dashboardService
    ) {}

    /**
     * GET /api/dashboard/summary
     */
    public function summary(): JsonResponse
    {
        abort_unless(
            Auth::user()->can('dashboard.view'),
            403,
            'This action is unauthorized.'
        );

        return $this->successResponse(
            $this->dashboardService->getSummary(),
            'Library dashboard summary retrieved successfully.'
        );
    }
}
