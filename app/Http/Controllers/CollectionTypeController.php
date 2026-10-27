<?php

namespace App\Http\Controllers;

use App\Http\Requests\CollectionTypeIndexRequest;
use App\Http\Requests\StoreCollectionTypeRequest;
use App\Http\Requests\UpdateCollectionTypeRequest;
use App\Http\Resources\CollectionTypeResource;
use App\Models\CollectionType;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CollectionTypeController extends Controller
{
    use ApiResponder;

    // ─── GET /api/collection-types ────────────────────────────────────────────

    public function index(CollectionTypeIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('collection_types.view'), 403);

        $query = CollectionType::query();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'ilike', '%'.$search.'%')
                    ->orWhere('name', 'ilike', '%'.$search.'%');
            });
        }

        // Filter by is_active
        if ($request->has('is_active') && ! is_null($request->input('is_active'))) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        $sort = $request->input('sort', 'sort_order');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => CollectionTypeResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Collection types retrieved successfully.');
    }

    // ─── GET /api/collection-types/{collectionType} ───────────────────────────

    public function show(CollectionType $collectionType): JsonResponse
    {
        abort_unless(Auth::user()->can('collection_types.view'), 403);

        return $this->successResponse(
            new CollectionTypeResource($collectionType),
            'Collection type retrieved successfully.'
        );
    }

    // ─── POST /api/collection-types ───────────────────────────────────────────

    public function store(StoreCollectionTypeRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('collection_types.create'), 403);

        $collectionType = CollectionType::create($request->validated());

        return $this->successResponse(
            new CollectionTypeResource($collectionType),
            'Collection type created successfully.',
            201
        );
    }

    // ─── PATCH /api/collection-types/{collectionType} ─────────────────────────

    public function update(UpdateCollectionTypeRequest $request, CollectionType $collectionType): JsonResponse
    {
        abort_unless(Auth::user()->can('collection_types.update'), 403);

        $collectionType->update($request->validated());
        $collectionType->refresh();

        return $this->successResponse(
            new CollectionTypeResource($collectionType),
            'Collection type updated successfully.'
        );
    }

    // ─── DELETE /api/collection-types/{collectionType} ────────────────────────
    //
    // DECISION: Hard delete is blocked when the collection type is referenced
    // by any book (restrictOnDelete in migration). Instead of propagating the DB
    // error directly, we check and return a clean 409 response.

    public function destroy(CollectionType $collectionType): JsonResponse
    {
        abort_unless(Auth::user()->can('collection_types.delete'), 403);

        if ($collectionType->books()->exists()) {
            return $this->errorResponse(
                'Collection type cannot be deleted because it is used by one or more books.',
                409
            );
        }

        $collectionType->delete();

        return $this->successResponse(null, 'Collection type deleted successfully.');
    }
}
