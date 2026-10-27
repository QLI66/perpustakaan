<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenewItemRequest;
use App\Http\Resources\RenewalResource;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Services\RenewalService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RenewalController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly RenewalService $renewalService) {}

    /**
     * POST /api/loans/{loan}/items/{loanItem}/renew
     *
     * Authorization:
     * - SUPER_ADMIN or LIBRARY_STAFF with loans.renew permission.
     * - MEMBER can only renew their own loan item.
     */
    public function __invoke(RenewItemRequest $request, Loan $loan, LoanItem $loanItem): JsonResponse
    {
        $user = Auth::user();

        // 1. Authorization
        if (! $user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            $member = $user->member;
            if (! $member || $loan->member_id !== $member->id) {
                abort(403, 'This action is unauthorized.');
            }
        } else {
            abort_unless($user->can('loans.renew'), 403);
        }

        // 2. Validate route consistency
        if ($loanItem->loan_id !== $loan->id) {
            abort(404, 'Resource not found.');
        }

        if ($user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            $renewedAt = $request->filled('renewed_at')
                ? Carbon::parse($request->input('renewed_at'))
                : null;
        } else {
            // MEMBER cannot control the renewal timestamp.
            $renewedAt = now();
        }

        // 3. Delegate to RenewalService
        // BusinessRuleException is handled globally → 422
        $renewedItem = $this->renewalService->renewItem($loanItem->id, $renewedAt);

        $renewedItem->load(['loan', 'bookCopy.book', 'bookCopy.location']);

        return $this->successResponse(
            new RenewalResource($renewedItem),
            'Book loan renewed successfully.'
        );
    }
}
