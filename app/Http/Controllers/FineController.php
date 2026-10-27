<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyConflictException;
use App\Http\Requests\FineIndexRequest;
use App\Http\Requests\FinePaymentRequest;
use App\Http\Resources\FinePaymentResource;
use App\Http\Resources\FineResource;
use App\Models\Fine;
use App\Services\FineAccrualService;
use App\Services\FinePaymentService;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FineController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly FinePaymentService $paymentService,
        private readonly FineAccrualService $accrualService,
    ) {}

    /**
     * GET /api/fines
     */
    public function index(FineIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('fines.view'), 403, 'This action is unauthorized.');

        $query = Fine::with([
            'loanItem.loan.member',
            'loanItem.bookCopy.book',
        ]);

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->whereHas('loanItem', function (Builder $q) use ($searchTerm) {
                $q->whereHas('loan', function ($sq) use ($searchTerm) {
                    $sq->where('loan_number', 'ilike', "%{$searchTerm}%")
                        ->orWhereHas('member', fn ($msq) => $msq->where('member_number', 'ilike', "%{$searchTerm}%")->orWhere('name', 'ilike', "%{$searchTerm}%"));
                })->orWhereHas('bookCopy', function ($sq) use ($searchTerm) {
                    $sq->where('barcode', 'ilike', "%{$searchTerm}%")
                        ->orWhereHas('book', fn ($bsq) => $bsq->where('title', 'ilike', "%{$searchTerm}%")->orWhere('isbn', 'ilike', "%{$searchTerm}%"));
                });
            });
        }

        if ($request->filled('member_id')) {
            $memberId = $request->input('member_id');
            $query->whereHas('loanItem', fn ($q) => $q->whereHas('loan', fn ($sq) => $sq->where('member_id', $memberId)));
        }

        if ($request->filled('loan_id')) {
            $loanId = $request->input('loan_id');
            $query->whereHas('loanItem', fn ($q) => $q->where('loan_id', $loanId));
        }

        if ($request->filled('loan_item_id')) {
            $query->where('loan_item_id', $request->input('loan_item_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('status', $request->input('payment_status')); // Alias to status
        }

        if ($request->filled('date_from')) {
            $query->whereDate('assessed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('assessed_at', '<=', $request->input('date_to'));
        }

        if ($request->boolean('overdue_only')) {
            // "overdue" fines are those with outstanding balance.
            $query->whereIn('status', ['UNPAID', 'PARTIAL']);
        }

        $isStatusExcludingUnpaid = (
            ($request->filled('status') && $request->input('status') !== 'UNPAID')
            || ($request->filled('payment_status') && $request->input('payment_status') !== 'UNPAID')
        );

        $accruedFines = collect();

        if (! $isStatusExcludingUnpaid) {
            if ($request->filled('loan_item_id')) {
                $accrued = $this->accrualService->currentForLoanItem((int) $request->input('loan_item_id'));
                $accruedFines = $accrued ? collect([$accrued]) : collect();
            } elseif ($request->filled('member_id')) {
                $accruedFines = $this->accrualService->currentForMember((int) $request->input('member_id'));
            } else {
                $accruedFines = $this->accrualService->allCurrent();
            }

            $accruedFines = $accruedFines->filter(
                fn (Fine $fine) => $this->accruedFineMatchesStaffFilters($fine, $request)
            );
        }

        $persistedFines = $query->get();

        $persistedLoanItemIds = $persistedFines->pluck('loan_item_id')->filter()->all();
        $accruedFines = $accruedFines->reject(
            fn (Fine $fine) => in_array($fine->loan_item_id, $persistedLoanItemIds, true)
        );

        $allFines = $persistedFines->concat($accruedFines);

        $sort = $request->input('sort', 'assessed_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortedFines = $allFines->sortBy(
            function (Fine $fine) use ($sort) {
                $val = $fine->getAttribute($sort) ?? $fine->$sort;
                if ($val instanceof \DateTimeInterface) {
                    return $val->getTimestamp();
                }
                if (is_numeric($val)) {
                    return (float) $val;
                }

                return (string) $val;
            },
            SORT_REGULAR,
            $direction === 'desc'
        )->values();

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginator = new LengthAwarePaginator(
            $sortedFines->forPage($page, $perPage)->values(),
            $sortedFines->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->successResponse(
            [
                'items' => FineResource::collection($paginator),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'Fines retrieved successfully.'
        );
    }

    /**
     * GET /api/me/fines
     */
    public function myFines(FineIndexRequest $request): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (! $member) {
            abort(404, 'Member record not found.');
        }

        $query = Fine::with([
            'loanItem.loan',
            'loanItem.bookCopy.book',
        ])->whereHas('loanItem', function (Builder $q) use ($member) {
            $q->whereHas('loan', function (Builder $sq) use ($member) {
                $sq->where('member_id', $member->id);
            });
        });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('assessed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('assessed_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('loan_item_id')) {
            $query->where('loan_item_id', $request->input('loan_item_id'));
        }

        $fines = $query->get();
        $accruedFines = $this->accrualService->currentForMember($member->id);

        if ($request->filled('loan_item_id')) {
            $accruedFines = $accruedFines->where(
                'loan_item_id',
                (int) $request->input('loan_item_id')
            );
        }

        if ($request->filled('status') && $request->input('status') !== 'UNPAID') {
            $accruedFines = collect();
        }

        if ($request->filled('date_from')) {
            $accruedFines = $accruedFines->filter(
                fn (Fine $fine) => $fine->assessed_at->toDateString() >= $request->input('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $accruedFines = $accruedFines->filter(
                fn (Fine $fine) => $fine->assessed_at->toDateString() <= $request->input('date_to')
            );
        }

        if ($request->filled('search')) {
            $search = Str::lower($request->input('search'));
            $accruedFines = $accruedFines->filter(function (Fine $fine) use ($search) {
                $item = $fine->loanItem;
                $values = [
                    $item->loan?->loan_number,
                    $item->loan?->member?->name,
                    $item->loan?->member?->member_number,
                    $item->bookCopy?->barcode,
                    $item->bookCopy?->book?->title,
                    $item->bookCopy?->book?->isbn,
                ];

                return collect($values)->contains(
                    fn (?string $value) => $value !== null && Str::contains(Str::lower($value), $search)
                );
            });
        }

        $fines = $fines->concat($accruedFines);
        $totalAmount = '0.00';
        $totalPaid = '0.00';
        $outstandingAmount = '0.00';

        foreach ($fines as $fine) {
            $amount = (string) $fine->amount;
            $paid = (string) $fine->amount_paid;
            $totalAmount = bcadd($totalAmount, $amount, 2);
            $totalPaid = bcadd($totalPaid, $paid, 2);
            $outstandingAmount = bcadd(
                $outstandingAmount,
                bcsub($amount, $paid, 2),
                2
            );
        }

        $sort = $request->input('sort', 'assessed_at');
        $direction = $request->input('direction', 'desc');
        $fines = $fines->sortBy(
            fn (Fine $fine) => $fine->getAttribute($sort),
            SORT_REGULAR,
            $direction === 'desc'
        )->values();

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $paginator = new LengthAwarePaginator(
            $fines->forPage($page, $perPage)->values(),
            $fines->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return $this->successResponse(
            [
                'items' => FineResource::collection($paginator),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
                'summary' => [
                    'total_amount' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'outstanding_amount' => $outstandingAmount,
                ],
            ],
            'My fines retrieved successfully.'
        );
    }

    private function accruedFineMatchesStaffFilters(Fine $fine, FineIndexRequest $request): bool
    {
        $item = $fine->loanItem;

        if (
            ($request->filled('status') && $request->input('status') !== 'UNPAID')
            || ($request->filled('payment_status') && $request->input('payment_status') !== 'UNPAID')
        ) {
            return false;
        }

        if ($request->filled('loan_item_id') && $fine->loan_item_id !== (int) $request->input('loan_item_id')) {
            return false;
        }

        if ($request->filled('member_id') && $item->loan?->member_id !== (int) $request->input('member_id')) {
            return false;
        }

        if ($request->filled('loan_id') && $item->loan_id !== (int) $request->input('loan_id')) {
            return false;
        }

        if ($request->filled('date_from') && $fine->assessed_at->toDateString() < $request->input('date_from')) {
            return false;
        }

        if ($request->filled('date_to') && $fine->assessed_at->toDateString() > $request->input('date_to')) {
            return false;
        }

        if ($request->filled('search')) {
            $search = Str::lower($request->input('search'));
            $values = [
                $item->loan?->loan_number,
                $item->loan?->member?->name,
                $item->loan?->member?->member_number,
                $item->bookCopy?->barcode,
                $item->bookCopy?->book?->title,
                $item->bookCopy?->book?->isbn,
            ];

            if (! collect($values)->contains(
                fn (?string $value) => $value !== null && Str::contains(Str::lower($value), $search)
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * GET /api/fines/{fine}
     */
    public function show(Fine $fine): JsonResponse
    {
        $user = Auth::user();

        // Admin/Staff check
        if (! $user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            // Member contextual check
            $member = $user->member;
            $fine->loadMissing('loanItem.loan');

            if (! $member || ! $fine->loanItem || ! $fine->loanItem->loan || $fine->loanItem->loan->member_id !== $member->id) {
                abort(403, 'This action is unauthorized.');
            }
        } else {
            abort_unless($user->can('fines.view'), 403);
        }

        $fine->load([
            'loanItem.loan.member',
            'loanItem.bookCopy.book',
            'loanItem.bookCopy.location',
            'payments.receivedBy',
        ]);

        return $this->successResponse(
            new FineResource($fine),
            'Fine details retrieved successfully.'
        );
    }

    /**
     * POST /api/fines/{fine}/payments
     */
    public function pay(FinePaymentRequest $request, Fine $fine): JsonResponse
    {
        abort_unless(Auth::user()->can('fines.payment'), 403, 'This action is unauthorized.');

        try {
            $result = $this->paymentService->recordPayment($fine->id, $request->validated());
        } catch (IdempotencyConflictException $exception) {
            return $this->errorResponse($exception->getMessage(), 409);
        }

        $payment = $result['payment'];
        $payment->load('receivedBy');

        $response = $this->successResponse(
            new FinePaymentResource($payment),
            'Payment recorded successfully.',
            $result['replayed'] ? 200 : 201
        );

        if ($result['replayed']) {
            $response->headers->set('Idempotency-Replayed', 'true');
        }

        return $response;
    }
}
