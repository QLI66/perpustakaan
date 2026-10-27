<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use ApiResponder;

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.view'), 403, 'This action is unauthorized.');

        $query = Permission::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->orderBy('name', 'asc');

        $permissions = $query->get();

        return $this->successResponse($permissions, 'Permissions retrieved successfully.');
    }
}
