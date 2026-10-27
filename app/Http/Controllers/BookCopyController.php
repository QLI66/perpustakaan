<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookCopyIndexRequest;
use App\Http\Requests\StoreBookCopyRequest;
use App\Http\Requests\UpdateBookCopyRequest;
use App\Http\Resources\BookCopyResource;
use App\Models\BookCopy;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BookCopyController extends Controller
{
    use ApiResponder;

    private function baseQuery(): Builder
    {
        return BookCopy::query()->with(['book', 'location']);
    }

    // ─── GET /api/book-copies ───────────────────────────────────────────────────

    public function index(BookCopyIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.view'), 403);

        $query = $this->baseQuery();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('barcode', 'ilike', '%'.$search.'%')
                    ->orWhere('inventory_number', 'ilike', '%'.$search.'%')
                    ->orWhereHas('book', function (Builder $q2) use ($search) {
                        $q2->where('title', 'ilike', '%'.$search.'%')
                            ->orWhere('isbn', 'ilike', '%'.$search.'%');
                    });
            });
        }

        // Filters
        if ($bookId = $request->input('book_id')) {
            $query->where('book_id', $bookId);
        }
        if ($locationId = $request->input('location_id')) {
            $query->where('location_id', $locationId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($condition = $request->input('condition')) {
            $query->where('condition', $condition);
        }
        if ($acquisitionSource = $request->input('acquisition_source')) {
            $query->where('acquisition_source', $acquisitionSource);
        }

        // Available shorthand filter
        if ($request->has('available')) {
            $available = $request->input('available');
            if ($available) {
                $query->where('status', 'AVAILABLE');
            } else {
                $query->where('status', '!=', 'AVAILABLE');
            }
        }

        // Sorting
        $sort = $request->input('sort', 'barcode');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => BookCopyResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Book copies retrieved successfully.');
    }

    // ─── GET /api/book-copies/{bookCopy} ────────────────────────────────────────

    /**
     * Find a single book copy by barcode.
     */
    public function byBarcode(string $barcode): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.view'), 403);

        $bookCopy = $this->baseQuery()
            ->where('barcode', $barcode)
            ->first();

        if (! $bookCopy) {
            return $this->errorResponse(
                'Book copy with barcode "'.$barcode.'" not found.',
                404
            );
        }

        return $this->successResponse(
            new BookCopyResource($bookCopy),
            'Book copy retrieved successfully.'
        );
    }

    public function show(BookCopy $bookCopy): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.view'), 403);

        $bookCopy->load(['book', 'location']);

        return $this->successResponse(new BookCopyResource($bookCopy), 'Book copy retrieved successfully.');
    }

    // ─── POST /api/book-copies ──────────────────────────────────────────────────

    public function store(StoreBookCopyRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.create'), 403);

        $data = $request->validated();
        $data['status'] = 'AVAILABLE'; // New copies always start as AVAILABLE

        $bookCopy = BookCopy::create($data);
        $bookCopy->load(['book', 'location']);

        return $this->successResponse(new BookCopyResource($bookCopy), 'Book copy created successfully.', 201);
    }

    // ─── PATCH /api/book-copies/{bookCopy} ──────────────────────────────────────

    public function update(UpdateBookCopyRequest $request, BookCopy $bookCopy): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.update'), 403);

        // Validated data never contains 'status' — see UpdateBookCopyRequest
        $bookCopy->update($request->validated());
        $bookCopy->refresh()->load(['book', 'location']);

        return $this->successResponse(new BookCopyResource($bookCopy), 'Book copy updated successfully.');
    }

    // ─── DELETE /api/book-copies/{bookCopy} ─────────────────────────────────────
    //
    // DECISION: Hard delete is blocked when the copy has any loan_item or
    // incident history to prevent corruption of historical library records.
    // Copies with history should be marked LOST/DAMAGED via domain services.

    public function destroy(BookCopy $bookCopy): JsonResponse
    {
        abort_unless(Auth::user()->can('book_copies.delete'), 403);

        if ($bookCopy->loanItems()->exists()) {
            return $this->errorResponse(
                'Book copy cannot be deleted because it has loan history. Consider marking it as LOST or DAMAGED via the appropriate incident process.',
                409
            );
        }

        if ($bookCopy->incidents()->exists()) {
            return $this->errorResponse(
                'Book copy cannot be deleted because it has incident history.',
                409
            );
        }

        $bookCopy->delete();

        return $this->successResponse(null, 'Book copy deleted successfully.');
    }
}
