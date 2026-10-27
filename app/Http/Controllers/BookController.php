<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class BookController extends Controller
{
    use ApiResponder;

    /**
     * GET /api/books
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])
                && Auth::user()->can('books.view'),
            403,
            'This action is unauthorized.'
        );

        $query = Book::query()
            ->with([
                'collectionType',
                'publisher',
                'authors',
                'categories',
            ])
            ->withCount([
                'copies',
                'copies as available_copies_count' => function (Builder $query) {
                    $query->where('status', 'AVAILABLE');
                },
            ]);

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('subtitle', 'ilike', "%{$search}%")
                    ->orWhere('isbn', 'ilike', "%{$search}%")
                    ->orWhereHas('authors', function (Builder $authorQuery) use ($search) {
                        $authorQuery->where(
                            'name',
                            'ilike',
                            "%{$search}%"
                        );
                    })
                    ->orWhereHas('publisher', function (Builder $publisherQuery) use ($search) {
                        $publisherQuery->where(
                            'name',
                            'ilike',
                            "%{$search}%"
                        );
                    });
            });
        }

        if ($request->filled('collection_type_id')) {
            $query->where(
                'collection_type_id',
                $request->input('collection_type_id')
            );
        }

        if ($request->filled('publisher_id')) {
            $query->where(
                'publisher_id',
                $request->input('publisher_id')
            );
        }

        $sort = $request->input(
            'sort',
            'created_at'
        );

        $direction = $request->input(
            'direction',
            'desc'
        );

        $allowedSorts = [
            'title',
            'isbn',
            'publication_year',
            'created_at',
        ];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            50
        );

        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            [
                'items' => BookResource::collection(
                    $paginator->items()
                ),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'Books retrieved successfully.'
        );
    }

    /**
     * POST /api/books
     */
    public function store(
        StoreBookRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('books.create'),
            403,
            'This action is unauthorized.'
        );

        $data = $request->validated();

        $authorIds = $data['author_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];

        $bookData = Arr::except(
            $data,
            [
                'author_ids',
                'category_ids',
            ]
        );

        $book = Book::create($bookData);

        $book->authors()->sync($authorIds);
        $book->categories()->sync($categoryIds);

        $book->load([
            'collectionType',
            'publisher',
            'authors',
            'categories',
            'targets.classLevel',
            'targets.major',
        ]);

        return $this->successResponse(
            new BookResource($book),
            'Book created successfully.',
            201
        );
    }

    /**
     * GET /api/books/{book}
     */
    public function show(Book $book): JsonResponse
    {
        abort_unless(
            Auth::user()->hasRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])
                && Auth::user()->can('books.view'),
            403,
            'This action is unauthorized.'
        );

        $book->load([
            'collectionType',
            'publisher',
            'authors',
            'categories',
            'targets.classLevel',
            'targets.major',
        ]);

        $book->loadCount([
            'copies',
            'copies as available_copies_count' => function (Builder $query) {
                $query->where('status', 'AVAILABLE');
            },
        ]);

        return $this->successResponse(
            new BookResource($book),
            'Book retrieved successfully.'
        );
    }

    /**
     * PATCH /api/books/{book}
     */
    public function update(
        UpdateBookRequest $request,
        Book $book
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('books.update'),
            403,
            'This action is unauthorized.'
        );

        $data = $request->validated();

        $authorIds = array_key_exists(
            'author_ids',
            $data
        )
            ? $data['author_ids']
            : null;

        $categoryIds = array_key_exists(
            'category_ids',
            $data
        )
            ? $data['category_ids']
            : null;

        $bookData = Arr::except(
            $data,
            [
                'author_ids',
                'category_ids',
            ]
        );

        $book->update($bookData);

        if ($authorIds !== null) {
            $book->authors()->sync($authorIds);
        }

        if ($categoryIds !== null) {
            $book->categories()->sync($categoryIds);
        }

        $book->refresh()->load([
            'collectionType',
            'publisher',
            'authors',
            'categories',
            'targets.classLevel',
            'targets.major',
        ]);

        $book->loadCount([
            'copies',
            'copies as available_copies_count' => function (Builder $query) {
                $query->where('status', 'AVAILABLE');
            },
        ]);

        return $this->successResponse(
            new BookResource($book),
            'Book updated successfully.'
        );
    }

    /**
     * DELETE /api/books/{book}
     */
    public function destroy(Book $book): JsonResponse
    {
        abort_unless(
            Auth::user()->can('books.delete'),
            403,
            'This action is unauthorized.'
        );

        if ($book->reservations()->exists()) {
            return $this->errorResponse(
                'Book cannot be deleted because reservations are associated with it.',
                409
            );
        }

        $book->delete();

        return $this->successResponse(
            null,
            'Book deleted successfully.'
        );
    }
}
