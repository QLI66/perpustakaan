<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckStockOpnameItemRequest;
use App\Http\Requests\StockOpnameIndexRequest;
use App\Http\Requests\StoreStockOpnameRequest;
use App\Http\Requests\UpdateStockOpnameRequest;
use App\Http\Resources\StockOpnameResource;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockOpnameService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockOpnameService $stockOpnameService
    ) {}

    /**
     * GET /api/stock-opnames
     */
    public function index(
        StockOpnameIndexRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.view'),
            403,
            'This action is unauthorized.'
        );

        $query = StockOpname::query()
            ->with([
                'location',
                'creator',
            ]);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search): void {
                $q->where(
                    'opname_number',
                    'ILIKE',
                    "%{$search}%"
                )
                    ->orWhere(
                        'notes',
                        'ILIKE',
                        "%{$search}%"
                    );
            });
        }

        if ($request->filled('location_id')) {
            $query->where(
                'location_id',
                $request->integer('location_id')
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'opname_date',
                '>=',
                $request->input('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'opname_date',
                '<=',
                $request->input('date_to')
            );
        }

        $sort = $request->input(
            'sort',
            'opname_date'
        );

        $direction = $request->input(
            'direction',
            'desc'
        );

        $query->orderBy(
            $sort,
            $direction
        );

        $perPage = $request->integer(
            'per_page',
            15
        );

        $opnames = $query->paginate(
            $perPage
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opnames retrieved successfully.',
            'data' => [
                'items' => StockOpnameResource::collection(
                    $opnames->items()
                ),
                'pagination' => [
                    'current_page' => $opnames->currentPage(),
                    'per_page' => $opnames->perPage(),
                    'total' => $opnames->total(),
                    'last_page' => $opnames->lastPage(),
                    'from' => $opnames->firstItem(),
                    'to' => $opnames->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/stock-opnames
     */
    public function store(
        StoreStockOpnameRequest $request
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.create'),
            403,
            'This action is unauthorized.'
        );

        $opname = $this->stockOpnameService->createStockOpname(
            $request->validated(),
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname created successfully.',
            'data' => new StockOpnameResource($opname),
        ], 201);
    }

    /**
     * GET /api/stock-opnames/{stockOpname}
     */
    public function show(
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.view'),
            403,
            'This action is unauthorized.'
        );

        $stockOpname->load([
            'location',
            'creator',
            'items.bookCopy.book',
            'items.bookCopy.location',
            'items.checkedBy',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock opname retrieved successfully.',
            'data' => new StockOpnameResource(
                $stockOpname
            ),
        ]);
    }

    /**
     * PATCH /api/stock-opnames/{stockOpname}
     */
    public function update(
        UpdateStockOpnameRequest $request,
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.update'),
            403,
            'This action is unauthorized.'
        );

        $updated = $this->stockOpnameService->updateStockOpname(
            $stockOpname->id,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname updated successfully.',
            'data' => new StockOpnameResource($updated),
        ]);
    }

    /**
     * DELETE /api/stock-opnames/{stockOpname}
     */
    public function destroy(
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.delete'),
            403,
            'This action is unauthorized.'
        );

        $this->stockOpnameService->deleteStockOpname(
            $stockOpname->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname deleted successfully.',
            'data' => null,
        ]);
    }

    /**
     * POST /api/stock-opnames/{stockOpname}/start
     */
    public function start(
        Request $request,
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.update'),
            403,
            'This action is unauthorized.'
        );

        $startedAt = null;

        if ($request->filled('started_at')) {
            $startedAt = Carbon::parse(
                $request->input('started_at')
            );
        }

        $started = $this->stockOpnameService->startStockOpname(
            $stockOpname->id,
            $startedAt
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname started successfully.',
            'data' => new StockOpnameResource($started),
        ]);
    }

    /**
     * POST /api/stock-opnames/{stockOpname}/items/{item}/check
     */
    public function checkItem(
        CheckStockOpnameItemRequest $request,
        StockOpname $stockOpname,
        StockOpnameItem $item
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.update'),
            403,
            'This action is unauthorized.'
        );

        if ($item->stock_opname_id !== $stockOpname->id) {
            abort(404);
        }

        $checked = $this->stockOpnameService->checkItem(
            $item->id,
            $request->validated(),
            Auth::id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname item checked successfully.',
            'data' => [
                'item' => $checked->load([
                    'stockOpname',
                    'bookCopy.book',
                    'bookCopy.location',
                    'checkedBy',
                ]),
            ],
        ]);
    }

    /**
     * POST /api/stock-opnames/{stockOpname}/complete
     */
    public function complete(
        Request $request,
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.update'),
            403,
            'This action is unauthorized.'
        );

        $completedAt = null;

        if ($request->filled('completed_at')) {
            $completedAt = Carbon::parse(
                $request->input('completed_at')
            );
        }

        $completed = $this->stockOpnameService->completeStockOpname(
            $stockOpname->id,
            $completedAt
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname completed successfully.',
            'data' => new StockOpnameResource($completed),
        ]);
    }

    /**
     * POST /api/stock-opnames/{stockOpname}/cancel
     */
    public function cancel(
        StockOpname $stockOpname
    ): JsonResponse {
        abort_unless(
            Auth::user()->can('stock_opnames.update'),
            403,
            'This action is unauthorized.'
        );

        $cancelled = $this->stockOpnameService->cancelStockOpname(
            $stockOpname->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock opname cancelled successfully.',
            'data' => new StockOpnameResource($cancelled),
        ]);
    }
}
