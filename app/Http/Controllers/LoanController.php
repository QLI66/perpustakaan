<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoanIndexRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly LoanService $loanService) {}

    private function baseQuery(): Builder
    {
        return Loan::query()->with([
            'member.memberType',
            'items.bookCopy.book',
            'items.bookCopy.location',
            'items.fine',
        ]);
    }

    private function applyOverdueFilter(Builder $query, bool $overdue): void
    {
        $matchesOverdueItem = static function (Builder $items): void {
            $items->where('status', 'ACTIVE')
                ->where('due_at', '<', now());
        };

        if ($overdue) {
            $query->whereHas('items', $matchesOverdueItem);
        } else {
            $query->whereDoesntHave('items', $matchesOverdueItem);
        }
    }

    /**
     * GET /api/loans
     * For SUPER_ADMIN and LIBRARY_STAFF
     */
    public function index(LoanIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('loans.view'), 403);

        $query = $this->baseQuery();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('loan_number', 'ilike', '%'.$search.'%')
                    ->orWhereHas('member', function (Builder $q2) use ($search) {
                        $q2->where('member_number', 'ilike', '%'.$search.'%')
                            ->orWhere('name', 'ilike', '%'.$search.'%');
                    });
            });
        }

        // Filters
        if ($memberId = $request->input('member_id')) {
            $query->where('member_id', $memberId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Date Filters
        if ($dateFrom = $request->input('date_from')) {
            $query->where('borrowed_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo = $request->input('date_to')) {
            $query->where('borrowed_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Overdue Filter
        if ($request->has('overdue')) {
            $this->applyOverdueFilter($query, (bool) $request->input('overdue'));
        }

        // Sorting
        $sort = $request->input('sort', 'borrowed_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => LoanResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Loans retrieved successfully.');
    }

    /**
     * GET /api/me/loans
     * For any authenticated user linked to a Member
     */
    public function myLoans(LoanIndexRequest $request): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (! $member) {
            return $this->errorResponse('Resource not found.', 404);
        }

        $query = $this->baseQuery()->where('member_id', $member->id);

        // Filters
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Overdue Filter
        if ($request->has('overdue')) {
            $this->applyOverdueFilter($query, (bool) $request->input('overdue'));
        }

        // Sorting
        $sort = $request->input('sort', 'borrowed_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => LoanResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Loans retrieved successfully.');
    }

    /**
     * GET /api/loans/{loan}
     */
    public function show(Loan $loan): JsonResponse
    {
        $user = Auth::user();

        // Authorization:
        // SUPER_ADMIN / LIBRARY_STAFF can view any loan
        // MEMBER can only view their own loan
        if (! $user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            $member = $user->member;
            if (! $member || $loan->member_id !== $member->id) {
                abort(403, 'This action is unauthorized.');
            }
        } else {
            abort_unless($user->can('loans.view'), 403);
        }

        $loan->load([
            'member.memberType',
            'items.bookCopy.book',
            'items.bookCopy.location',
            'items.fine',
        ]);

        return $this->successResponse(new LoanResource($loan), 'Loan retrieved successfully.');
    }

    /**
     * POST /api/loans
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $user = Auth::user();

        $memberId = null;

        if ($user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            abort_unless($user->can('loans.create'), 403);
            $memberId = $request->input('member_id');
        } else {
            // Self-service creation
            $member = $user->member;
            if (! $member) {
                abort(403, 'User does not have an associated member record.');
            }
            $memberId = $member->id;
        }

        $bookCopyIds = $request->input('book_copy_ids');
        $notes = $request->input('notes');
        $loanDurationDays = $request->filled('loan_duration_days')
            ? (int) $request->input('loan_duration_days')
            : null;
        $durationUnit = $request->filled('duration_unit')
            ? $request->input('duration_unit')
            : null;
        $durationValue = $request->filled('duration_value')
            ? (int) $request->input('duration_value')
            : null;

        if ($user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            $borrowedAt = $request->filled('borrowed_at')
                ? Carbon::parse($request->input('borrowed_at'))
                : now();
        } else {
            // MEMBER cannot control the transaction timestamp.
            $borrowedAt = now();
        }

        // BusinessRuleException will be handled globally and returned as 422
        $loan = $this->loanService->createLoan(
            $memberId,
            $bookCopyIds,
            $borrowedAt,
            $notes,
            $loanDurationDays,
            $durationUnit,
            $durationValue
        );

        $loan->load([
            'member.memberType',
            'items.bookCopy.book',
            'items.bookCopy.location',
            'items.fine',
        ]);

        return $this->successResponse(new LoanResource($loan), 'Loan created successfully.', 201);
    }
}
