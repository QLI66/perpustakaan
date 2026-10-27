<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookIncidentIndexRequest;
use App\Http\Requests\ResolveIncidentPaymentRequest;
use App\Http\Requests\ResolveIncidentReplacementRequest;
use App\Http\Requests\StoreBookIncidentRequest;
use App\Http\Resources\BookIncidentResource;
use App\Models\BookIncident;
use App\Services\BookIncidentService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BookIncidentController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly BookIncidentService $incidentService) {}

    /**
     * GET /api/book-incidents
     */
    public function index(BookIncidentIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('book_incidents.view'), 403, 'This action is unauthorized.');

        $query = BookIncident::with([
            'loanItem.loan.member',
            'bookCopy.book',
            'replacementBookCopy',
            'resolvedBy',
        ]);

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->whereHas('loanItem.loan.member', function (Builder $mq) use ($searchTerm) {
                    $mq->where('member_number', 'ilike', "%{$searchTerm}%")
                        ->orWhere('name', 'ilike', "%{$searchTerm}%");
                })->orWhereHas('loanItem.loan', function (Builder $lq) use ($searchTerm) {
                    $lq->where('loan_number', 'ilike', "%{$searchTerm}%");
                })->orWhereHas('bookCopy', function (Builder $cq) use ($searchTerm) {
                    $cq->where('barcode', 'ilike', "%{$searchTerm}%")
                        ->orWhereHas('book', function (Builder $bq) use ($searchTerm) {
                            $bq->where('title', 'ilike', "%{$searchTerm}%")
                                ->orWhere('isbn', 'ilike', "%{$searchTerm}%");
                        });
                });
            });
        }

        if ($request->filled('member_id')) {
            $memberId = $request->input('member_id');
            $query->whereHas('loanItem.loan', fn ($q) => $q->where('member_id', $memberId));
        }

        if ($request->filled('loan_id')) {
            $query->whereHas('loanItem', fn ($q) => $q->where('loan_id', $request->input('loan_id')));
        }

        if ($request->filled('loan_item_id')) {
            $query->where('loan_item_id', $request->input('loan_item_id'));
        }

        if ($request->filled('book_copy_id')) {
            $query->where('book_copy_id', $request->input('book_copy_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('resolution_type')) {
            $query->where('resolution_type', $request->input('resolution_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'opened_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        $perPage = $request->input('per_page', 15);
        $incidents = $query->paginate($perPage);

        return $this->successResponse(
            [
                'items' => BookIncidentResource::collection($incidents),
                'pagination' => [
                    'total' => $incidents->total(),
                    'per_page' => $incidents->perPage(),
                    'current_page' => $incidents->currentPage(),
                    'last_page' => $incidents->lastPage(),
                ],
            ],
            'Book incidents retrieved successfully.'
        );
    }

    /**
     * GET /api/me/book-incidents
     */
    public function myIncidents(BookIncidentIndexRequest $request): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (! $member) {
            abort(404, 'Member record not found.');
        }

        $query = BookIncident::with([
            'loanItem.loan',
            'bookCopy.book',
            'replacementBookCopy',
            'resolvedBy',
        ])->whereHas('loanItem.loan', function (Builder $q) use ($member) {
            $q->where('member_id', $member->id);
        });

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'opened_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        $perPage = $request->input('per_page', 15);
        $incidents = $query->paginate($perPage);

        return $this->successResponse(
            [
                'items' => BookIncidentResource::collection($incidents),
                'pagination' => [
                    'total' => $incidents->total(),
                    'per_page' => $incidents->perPage(),
                    'current_page' => $incidents->currentPage(),
                    'last_page' => $incidents->lastPage(),
                ],
            ],
            'My book incidents retrieved successfully.'
        );
    }

    /**
     * GET /api/book-incidents/{incident}
     */
    public function show(BookIncident $incident): JsonResponse
    {
        $user = Auth::user();

        // Admin/Staff check
        if (! $user->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            // Member contextual check
            $member = $user->member;
            $incident->loadMissing('loanItem.loan');

            if (! $member || ! $incident->loanItem || ! $incident->loanItem->loan || $incident->loanItem->loan->member_id !== $member->id) {
                abort(403, 'This action is unauthorized.');
            }
        } else {
            abort_unless($user->can('book_incidents.view'), 403);
        }

        $incident->load([
            'loanItem.loan.member',
            'bookCopy.book',
            'replacementBookCopy',
            'resolvedBy',
        ]);

        return $this->successResponse(
            new BookIncidentResource($incident),
            'Book incident details retrieved successfully.'
        );
    }

    /**
     * POST /api/book-incidents
     */
    public function store(StoreBookIncidentRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('book_incidents.create'), 403, 'This action is unauthorized.');

        $data = $request->validated();

        $openedAt = ! empty($data['opened_at']) ? Carbon::parse($data['opened_at']) : null;

        $incident = $this->incidentService->openIncident(
            $data['loan_item_id'],
            $data['type'],
            $openedAt,
            $data['notes'] ?? null
        );

        $incident->load([
            'loanItem.loan.member',
            'bookCopy.book',
        ]);

        return $this->successResponse(
            new BookIncidentResource($incident),
            'Book incident opened successfully.',
            201
        );
    }

    /**
     * POST /api/book-incidents/{incident}/resolve/payment
     */
    public function resolvePayment(ResolveIncidentPaymentRequest $request, BookIncident $incident): JsonResponse
    {
        abort_unless(Auth::user()->can('book_incidents.resolve'), 403, 'This action is unauthorized.');

        $data = $request->validated();
        $resolvedAt = ! empty($data['resolved_at']) ? Carbon::parse($data['resolved_at']) : null;

        $resolvedIncident = $this->incidentService->resolveWithPayment(
            $incident->id,
            Auth::id(),
            $resolvedAt,
            $data['notes'] ?? null
        );

        $resolvedIncident->load([
            'loanItem.loan.member',
            'bookCopy.book',
            'resolvedBy',
        ]);

        return $this->successResponse(
            new BookIncidentResource($resolvedIncident),
            'Book incident resolved by payment successfully.'
        );
    }

    /**
     * POST /api/book-incidents/{incident}/resolve/replacement
     */
    public function resolveReplacement(ResolveIncidentReplacementRequest $request, BookIncident $incident): JsonResponse
    {
        abort_unless(Auth::user()->can('book_incidents.resolve'), 403, 'This action is unauthorized.');

        $data = $request->validated();
        $resolvedAt = ! empty($data['resolved_at']) ? Carbon::parse($data['resolved_at']) : null;

        $resolvedIncident = $this->incidentService->resolveWithReplacement(
            $incident->id,
            $data['replacement_book_copy_id'],
            Auth::id(),
            $resolvedAt,
            $data['notes'] ?? null
        );

        $resolvedIncident->load([
            'loanItem.loan.member',
            'bookCopy.book',
            'replacementBookCopy',
            'resolvedBy',
        ]);

        return $this->successResponse(
            new BookIncidentResource($resolvedIncident),
            'Book incident resolved by replacement successfully.'
        );
    }
}
