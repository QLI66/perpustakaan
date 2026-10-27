<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponder;

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.view'), 403, 'This action is unauthorized.');

        $query = Role::with('permissions');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'ilike', "%{$search}%");
        }

        $query->orderBy('name', 'asc');

        $roles = $query->get();

        return $this->successResponse($roles, 'Roles retrieved successfully.');
    }

    public function show(Role $role): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.view'), 403, 'This action is unauthorized.');

        $role->load('permissions');

        return $this->successResponse($role, 'Role retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.create'), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        return $this->successResponse($role, 'Role created successfully.', 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.update'), 403, 'This action is unauthorized.');

        // Prevent modifying core roles like SUPER_ADMIN to avoid locking ourselves out
        if (in_array($role->name, ['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'])) {
            return $this->errorResponse('Cannot modify core system roles.', 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($data['name'])) {
            $role->name = $data['name'];
            $role->save();
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        return $this->successResponse($role, 'Role updated successfully.');
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_unless(Auth::user()->can('roles.delete'), 403, 'This action is unauthorized.');

        if (in_array($role->name, ['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'])) {
            return $this->errorResponse('Cannot delete core system roles.', 403);
        }

        if ($role->users()->exists()) {
            return $this->errorResponse('Cannot delete role that is assigned to users.', 409);
        }

        $role->delete();

        return $this->successResponse(null, 'Role deleted successfully.');
    }
}
