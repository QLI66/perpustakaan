<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    use ApiResponder;

    /**
     * GET /api/locations
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('locations.view'),
            403,
            'This action is unauthorized.'
        );

        $query = Location::query();

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        if (! in_array($sort, ['id', 'code', 'name', 'created_at'], true)) {
            $sort = 'name';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($sort, $direction);

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 50));

        $paginator = $query->paginate($perPage);

        return $this->successResponse(
            [
                'items' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'Locations retrieved successfully.'
        );
    }

    /**
     * POST /api/locations
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()->can('locations.create'),
            403,
            'This action is unauthorized.'
        );

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                'unique:locations,code',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $location = Location::create([
            'code' => trim($data['code']),
            'name' => trim($data['name']),
        ]);

        return $this->successResponse(
            $location,
            'Location created successfully.',
            201
        );
    }
}
