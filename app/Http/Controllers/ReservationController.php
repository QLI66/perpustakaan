<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignReservationRequest;
use App\Http\Requests\ReservationIndexRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    use ApiResponder;

    public function __construct(private readonly ReservationService $reservationService) {}

    public function myReservations(ReservationIndexRequest $request): JsonResponse
    {
        $member = $request->user()->member;

        if (! $member) {
            return $this->errorResponse('Member profile not found.', 404);
        }

        $query = Reservation::with(['book', 'bookCopy'])
            ->where('member_id', $member->id);

        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 15);
        $reservations = $query->paginate($perPage);

        return $this->successResponse(
            ReservationResource::collection($reservations)->response()->getData(true),
            'My reservations retrieved successfully.'
        );
    }

    public function index(ReservationIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        $query = Reservation::with(['member', 'book', 'bookCopy']);

        if ($request->user()->hasRole('MEMBER')) {
            $query->where('member_id', $request->user()->member->id);
        }

        $this->applyFilters($query, $request);

        $perPage = $request->input('per_page', 15);
        $reservations = $query->paginate($perPage);

        return $this->successResponse(
            ReservationResource::collection($reservations)->response()->getData(true),
            'Reservations retrieved successfully.'
        );
    }

    public function show(Reservation $reservation): JsonResponse
    {
        Gate::authorize('view', $reservation);

        $reservation->load(['member', 'book', 'bookCopy']);

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reservation retrieved successfully.'
        );
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $user = $request->user();

        // Only members can create for themselves in this design, or staff can specify (if allowed later).
        // The prompt says: "The authenticated MEMBER becomes the owner. Do NOT accept member_id from MEMBER requests."
        $member = $user->member;

        if (! $member) {
            return $this->errorResponse('Only members can create reservations via this endpoint.', 403);
        }

        Gate::authorize('create', Reservation::class);

        $reservation = $this->reservationService->createReservation(
            $member->id,
            $request->input('book_id'),
            now(),
            $request->input('notes')
        );

        $reservation->load(['member', 'book']);

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reservation created successfully.',
            201
        );
    }

    public function cancel(Reservation $reservation, Request $request): JsonResponse
    {
        Gate::authorize('cancel', $reservation);

        $cancelledReservation = $this->reservationService->cancelReservation($reservation->id);
        $cancelledReservation->load(['member', 'book', 'bookCopy']);

        return $this->successResponse(
            new ReservationResource($cancelledReservation),
            'Reservation cancelled successfully.'
        );
    }

    public function restore(Reservation $reservation): JsonResponse
    {
        Gate::authorize('restore', $reservation);

        $restoredReservation = $this->reservationService->restoreReservation(
            $reservation->id
        );

        $restoredReservation->load(['member', 'book', 'bookCopy']);

        return $this->successResponse(
            new ReservationResource($restoredReservation),
            'Reservation restored successfully.'
        );
    }

    public function assign(AssignReservationRequest $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('manage', Reservation::class);

        $assignedReservation = $this->reservationService->assignCopy(
            $reservation->id,
            $request->input('book_copy_id')
        );
        $assignedReservation->load(['member', 'book', 'bookCopy']);

        return $this->successResponse(
            new ReservationResource($assignedReservation),
            'Reservation assigned successfully.'
        );
    }

    private function applyFilters($query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reservation_number', 'ilike', "%{$search}%")
                    ->orWhereHas('book', fn ($q) => $q->where('title', 'ilike', "%{$search}%")->orWhere('isbn', 'ilike', "%{$search}%"))
                    ->orWhereHas('member', fn ($q) => $q->where('member_number', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"));
            });
        }

        if ($request->has('member_id')) {
            // Only admins/staff should filter by arbitrary member_id.
            // If user is just a member, we ignore this since myReservations forces the member_id.
            // In index(), we allow it.
            $query->where('member_id', $request->input('member_id'));
        }

        if ($request->has('book_id')) {
            $query->where('book_id', $request->input('book_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('reserved_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('reserved_at', '<=', $request->input('date_to'));
        }

        $sort = $request->input('sort', 'reserved_at');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);
    }
}
