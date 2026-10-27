<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnItemRequest;
use App\Http\Resources\ReturnResource;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Services\ReturnService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReturnController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly ReturnService $returnService) {}

    /**
     * POST /api/loans/{loan}/items/{loanItem}/return
     *
     * Authorization: SUPER_ADMIN or LIBRARY_STAFF with loans.return permission.
     * MEMBER cannot perform returns.
     *
     * LoanItem / Loan consistency is validated before calling the service:
     * we verify that the given LoanItem actually belongs to the given Loan,
     * preventing cross-loan manipulation via URL.
     */
    public function __invoke(ReturnItemRequest $request, Loan $loan, LoanItem $loanItem): JsonResponse
    {
        abort_unless(Auth::user()->can('loans.return'), 403);

        // Validate LoanItem belongs to the given Loan (route consistency check)
        if ($loanItem->loan_id !== $loan->id) {
            abort(404, 'Resource not found.');
        }

        $returnedAt = $request->filled('returned_at')
            ? Carbon::parse($request->input('returned_at'))
            : null;

        // BusinessRuleException is handled globally → 422
        $returnedItem = $this->returnService->returnItem($loanItem->id, $returnedAt);

        // Reload the loan relation so loan_status reflects any COMPLETED transition
        $returnedItem->load(['loan', 'bookCopy.book', 'bookCopy.location', 'fine']);

        return $this->successResponse(
            new ReturnResource($returnedItem),
            'Book returned successfully.'
        );
    }
}
