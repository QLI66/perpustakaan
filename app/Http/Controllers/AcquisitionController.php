<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcquisitionIndexRequest;
use App\Http\Requests\StoreAcquisitionRequest;
use App\Http\Requests\UpdateAcquisitionRequest;
use App\Http\Resources\AcquisitionResource;
use App\Models\Acquisition;
use App\Services\AcquisitionService;
use App\Traits\ApiResponder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AcquisitionController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly AcquisitionService $acquisitionService
    ) {}

    /**
     * GET /api/acquisitions
     */
    public function index(AcquisitionIndexRequest $request): JsonResponse
    {

        abort_unless(
            Auth::user()->can('acquisitions.view'),
            403,
            'This action is unauthorized.'
        );

        $query = Acquisition::query()
            ->with(['items.book']);

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function (Builder $q) use ($search) {
                $q->where(
                    'acquisition_number',
                    'ilike',
                    "%{$search}%"
                )
                    ->orWhere(
                        'source_name',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'reference_number',
                        'ilike',
                        "%{$search}%"
                    );
            });
        }

        // Filter source type
        if ($request->filled('source_type')) {
            $query->where(
                'source_type',
                $request->input('source_type')
            );
        }

        // Filter date range
        if ($request->filled('date_from')) {
            $query->whereDate(
                'acquisition_date',
                '>=',
                $request->input('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'acquisition_date',
                '<=',
                $request->input('date_to')
            );
        }

        // Sorting
        $sort = $request->input(
            'sort',
            'acquisition_date'
        );

        $direction = $request->input(
            'direction',
            'desc'
        );

        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = $request->input(
            'per_page',
            15
        );

        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            [
                'items' => AcquisitionResource::collection(
                    $paginator->items()
                ),

                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'Acquisitions retrieved successfully.'
        );
    }

    /**
     * GET /api/acquisitions/{acquisition}
     */
    public function show(
        Acquisition $acquisition
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('acquisitions.view'),
            403,
            'This action is unauthorized.'
        );

        $acquisition->load([
            'items.book',
            'items.bookCopies',
        ]);

        return $this->successResponse(
            new AcquisitionResource($acquisition),
            'Acquisition retrieved successfully.'
        );
    }

    /**
     * POST /api/acquisitions
     */
    public function store(
        StoreAcquisitionRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('acquisitions.create'),
            403,
            'This action is unauthorized.'
        );

        $data = $request->validated();

        $acquisition = $this->acquisitionService
            ->createAcquisition(
                Carbon::parse(
                    $data['acquisition_date']
                ),
                $data['source_type'],
                $data['source_name'] ?? null,
                $data['reference_number'] ?? null,
                $data['items'],
                Auth::id(),
                $data['notes'] ?? null
            );

        $acquisition->load([
            'items.book',
            'items.bookCopies',
        ]);

        return $this->successResponse(
            new AcquisitionResource($acquisition),
            'Acquisition created successfully.',
            201
        );
    }

    /**
     * PATCH /api/acquisitions/{acquisition}
     */
    public function update(
        UpdateAcquisitionRequest $request,
        Acquisition $acquisition
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('acquisitions.update'),
            403,
            'This action is unauthorized.'
        );

        $acquisition->update(
            $request->validated()
        );

        $acquisition->refresh()->load([
            'items.book',
            'items.bookCopies',
        ]);

        return $this->successResponse(
            new AcquisitionResource($acquisition),
            'Acquisition updated successfully.'
        );
    }

    /**
     * DELETE /api/acquisitions/{acquisition}
     */
    public function destroy(
        Acquisition $acquisition
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('acquisitions.delete'),
            403,
            'This action is unauthorized.'
        );

        $hasBookCopies = $acquisition->items()
            ->whereHas('bookCopies')
            ->exists();

        if ($hasBookCopies) {
            return $this->errorResponse(
                'Acquisition cannot be deleted because its items are already linked to book copies.',
                409
            );
        }

        $acquisition->delete();

        return $this->successResponse(
            null,
            'Acquisition deleted successfully.'
        );
    }
}
