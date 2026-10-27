<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponder;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponder;

    public function index(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('users.view'), 403, 'This action is unauthorized.');

        $query = User::with('roles');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->input('role'));
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);

        $perPage = (int) $request->input('per_page', 15);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Users retrieved successfully.');
    }

    public function show(User $user): JsonResponse
    {
        abort_unless(Auth::user()->can('users.view'), 403, 'This action is unauthorized.');

        $user->load('roles');

        return $this->successResponse($user, 'User retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('users.create'), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['nullable', 'array', self::coreRoleExclusivityRule()],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user->load('roles');

        return $this->successResponse($user, 'User created successfully.', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless(Auth::user()->can('users.update'), 403, 'This action is unauthorized.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['nullable', 'array', self::coreRoleExclusivityRule()],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }

        $user->load('roles');

        return $this->successResponse($user, 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        abort_unless(Auth::user()->can('users.delete'), 403, 'This action is unauthorized.');

        if ($user->id === Auth::id()) {
            return $this->errorResponse('Cannot delete yourself.', 400);
        }

        // Check if user is associated with anything crucial?
        // e.g. member, fine payments received, book incidents resolved
        if ($user->member()->exists() || $user->receivedFinePayments()->exists() || $user->resolvedBookIncidents()->exists()) {
            return $this->errorResponse('Cannot delete user because they are linked to existing records.', 409);
        }

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully.');
    }

    private static function coreRoleExclusivityRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $coreRoles = array_unique(array_filter(
                $value,
                static fn (mixed $role): bool => is_string($role)
                    && in_array($role, ['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'], true),
            ));

            if (count($coreRoles) > 1) {
                $fail('Pilih maksimal satu role inti: SUPER_ADMIN, LIBRARY_STAFF, atau MEMBER.');
            }
        };
    }
}
