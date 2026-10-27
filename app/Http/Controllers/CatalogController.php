<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogBookIndexRequest;
use App\Http\Resources\Catalog\AuthorResource;
use App\Http\Resources\Catalog\BookDetailResource;
use App\Http\Resources\Catalog\BookListResource;
use App\Http\Resources\Catalog\CategoryResource;
use App\Http\Resources\Catalog\CollectionTypeResource;
use App\Http\Resources\Catalog\PublisherResource;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\CollectionType;
use App\Models\Publisher;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    use ApiResponder;

    public function books(CatalogBookIndexRequest $request)
    {
        $query = Book::query()
            ->with([
                'collectionType',
                'publisher',
                'authors',
                'categories',
                'targets.classLevel',
                'targets.major',
            ])
            ->withCount([
                'copies',
                'copies as available_copies_count' => function (Builder $query) {
                    $query->where('status', 'AVAILABLE');
                },
            ]);

        // 1. Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'ilike', '%'.$search.'%')
                    ->orWhere('subtitle', 'ilike', '%'.$search.'%')
                    ->orWhere('isbn', 'ilike', '%'.$search.'%')
                    ->orWhereHas('authors', function (Builder $q2) use ($search) {
                        $q2->where('name', 'ilike', '%'.$search.'%');
                    })
                    ->orWhereHas('publisher', function (Builder $q2) use ($search) {
                        $q2->where('name', 'ilike', '%'.$search.'%');
                    });
            });
        }

        // 2. Standard Filters
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('categories', function (Builder $q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }
        if ($authorId = $request->input('author_id')) {
            $query->whereHas('authors', function (Builder $q) use ($authorId) {
                $q->where('authors.id', $authorId);
            });
        }
        if ($publisherId = $request->input('publisher_id')) {
            $query->where('publisher_id', $publisherId);
        }
        if ($collectionTypeId = $request->input('collection_type_id')) {
            $query->where('collection_type_id', $collectionTypeId);
        }

        // 3. Status / Availability Filter
        if ($request->filled('status')) {
            $status = $request->input('status');

            $query->whereHas('copies', function (Builder $q) use ($status) {
                $q->where('status', $status);
            });
        } elseif ($request->has('available')) {
            // Backward compatibility for the old available=true/false filter.
            $available = $request->input('available');

            if ($available) {
                $query->whereHas('copies', function (Builder $q) {
                    $q->where('status', 'AVAILABLE');
                });
            } else {
                $query->whereDoesntHave('copies', function (Builder $q) {
                    $q->where('status', 'AVAILABLE');
                });
            }
        }

        // 4. Target Filters
        $classLevelId = $request->input('class_level_id');
        $majorId = $request->input('major_id');

        if ($classLevelId || $majorId) {
            $query->whereHas('targets', function (Builder $q) use ($classLevelId, $majorId) {
                $q->where(function (Builder $sub) use ($classLevelId, $majorId) {
                    // Global target matches everything
                    $sub->whereNull('class_level_id')->whereNull('major_id');

                    if ($classLevelId && $majorId) {
                        // Class-only target
                        $sub->orWhere(function ($q2) use ($classLevelId) {
                            $q2->where('class_level_id', $classLevelId)->whereNull('major_id');
                        });
                        // Major-only target
                        $sub->orWhere(function ($q2) use ($majorId) {
                            $q2->whereNull('class_level_id')->where('major_id', $majorId);
                        });
                        // Exact match
                        $sub->orWhere(function ($q2) use ($classLevelId, $majorId) {
                            $q2->where('class_level_id', $classLevelId)->where('major_id', $majorId);
                        });
                    } elseif ($classLevelId) {
                        // Class-only query
                        $sub->orWhere(function ($q2) use ($classLevelId) {
                            $q2->where('class_level_id', $classLevelId)->whereNull('major_id');
                        });
                        $sub->orWhere(function ($q2) use ($classLevelId) {
                            $q2->where('class_level_id', $classLevelId)->whereNotNull('major_id');
                        });
                    } elseif ($majorId) {
                        // Major-only query
                        $sub->orWhere(function ($q2) use ($majorId) {
                            $q2->whereNull('class_level_id')->where('major_id', $majorId);
                        });
                        $sub->orWhere(function ($q2) use ($majorId) {
                            $q2->whereNotNull('class_level_id')->where('major_id', $majorId);
                        });
                    }
                });
            });
        }

        // 5. Sorting
        $sort = $request->input('sort', 'title');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);

        // 6. Pagination
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage > 50) {
            $perPage = 50;
        }

        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => BookListResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Books retrieved successfully.');
    }

    public function showBook(Book $book)
    {
        $book->load([
            'collectionType',
            'publisher',
            'authors',
            'categories',
            'targets.classLevel',
            'targets.major',
            'copies.location',
        ]);

        return $this->successResponse(
            new BookDetailResource($book),
            'Book retrieved successfully.'
        );
    }

    public function categories()
    {
        // Assuming Category model has an 'is_active' scope or field based on previous implementation
        $categories = Category::where('is_active', true)->orderBy('sort_order')->get();

        return $this->successResponse(
            CategoryResource::collection($categories),
            'Categories retrieved successfully.'
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $user = $request->user();

        if (
            ! $user ||
            ! $user->hasAnyRole(['SUPER_ADMIN', 'LIBRARY_STAFF']) ||
            ! $user->can('categories.create')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin menambahkan kategori.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);
        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama kategori wajib diisi.',
            ], 422);
        }

        $existing = Category::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Nama kategori tersebut sudah terdaftar.',
            ], 422);
        }

        $baseCode = Str::upper(Str::slug($name, '_'));
        $baseCode = mb_substr($baseCode !== '' ? $baseCode : 'CATEGORY', 0, 50);
        $code = $baseCode;
        $suffix = 2;

        while (Category::query()->where('code', $code)->exists()) {
            $suffixText = '_'.$suffix++;
            $code = mb_substr($baseCode, 0, 50 - mb_strlen($suffixText)).$suffixText;
        }

        $category = Category::create([
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'sort_order' => min(((int) Category::max('sort_order')) + 1, 65535),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function authors()
    {
        $authors = Author::orderBy('name')->get();

        return $this->successResponse(
            AuthorResource::collection($authors),
            'Authors retrieved successfully.'
        );
    }

    public function storeAuthor(\Illuminate\Http\Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['SUPER_ADMIN', 'LIBRARY_STAFF'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin menambahkan penulis.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $name = trim($validated['name']);

        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama penulis wajib diisi.',
            ], 422);
        }

        $existing = \App\Models\Author::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Nama penulis tersebut sudah terdaftar.',
            ], 422);
        }

        $author = \App\Models\Author::create([
            'name' => $name,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Penulis berhasil ditambahkan.',
            'data' => $author,
        ], 201);
    }
    public function publishers()
    {
        // Publisher uses 'is_active' scope
        $publishers = Publisher::active()->orderBy('name')->get();

        return $this->successResponse(
            PublisherResource::collection($publishers),
            'Publishers retrieved successfully.'
        );
    }

    public function collectionTypes()
    {
        // CollectionType has 'is_active' based on typical structure
        $types = CollectionType::where('is_active', true)->orderBy('sort_order')->get();

        return $this->successResponse(
            CollectionTypeResource::collection($types),
            'Collection types retrieved successfully.'
        );
    }
}
