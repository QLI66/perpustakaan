<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ─── Minimal Seed untuk keperluan Test jika DatabaseSeeder belum dijalankan
        if (! Role::where('name', 'SUPER_ADMIN')->exists()) {
            $superAdmin = Role::create(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
            $staff = Role::create(['name' => 'LIBRARY_STAFF', 'guard_name' => 'web']);
            $memberRole = Role::create(['name' => 'MEMBER', 'guard_name' => 'web']);

            $pUsersCreate = Permission::create(['name' => 'users.create', 'guard_name' => 'web']);
            $pUsersView = Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
            $pBooksCreate = Permission::create(['name' => 'books.create', 'guard_name' => 'web']);
            $pBooksUpdate = Permission::create(['name' => 'books.update', 'guard_name' => 'web']);
            $pBooksView = Permission::create(['name' => 'books.view', 'guard_name' => 'web']);
            $pRolesCreate = Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);

            $superAdmin->syncPermissions(Permission::all());
            $staff->syncPermissions([$pUsersView, $pBooksCreate, $pBooksUpdate, $pBooksView]);
            $memberRole->syncPermissions([$pBooksView]);
        }

        // Setup temporary routes for authorization testing
        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('/api/test/users', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:users.view');

            Route::post('/api/test/users', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:users.create');

            Route::get('/api/test/books', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:books.view');

            Route::post('/api/test/books', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:books.create');

            Route::put('/api/test/books/1', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:books.update');

            Route::post('/api/test/roles', function () {
                return response()->json(['message' => 'ok']);
            })->middleware('can:roles.create');
        });
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/test/books');
        $response->assertStatus(401);
        $response->assertJsonStructure(['success', 'message']);
        $this->assertFalse($response->json('success'));
    }

    public function test_authenticated_member_recognized_and_has_books_view(): void
    {
        $user = $this->createUserWithRole('MEMBER');
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/test/books');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'ok']);
    }

    public function test_member_lacks_books_create(): void
    {
        $user = $this->createUserWithRole('MEMBER');
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/test/books');

        $response->assertStatus(403);
    }

    public function test_library_staff_has_books_create_and_update(): void
    {
        $user = $this->createUserWithRole('LIBRARY_STAFF');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/test/books')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/test/books/1')
            ->assertStatus(200);
    }

    public function test_library_staff_has_users_view_but_lacks_users_create(): void
    {
        $user = $this->createUserWithRole('LIBRARY_STAFF');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/test/users')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/test/users')
            ->assertStatus(403);
    }

    public function test_super_admin_has_users_create_and_roles_create(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/test/users')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/test/roles')
            ->assertStatus(200);
    }

    public function test_role_permission_guard_behavior_is_consistent(): void
    {
        // Even when logged in via Sanctum guard, the User should resolve 'web' guard permissions
        $user = $this->createUserWithRole('MEMBER');

        // This simulates Sanctum login
        $this->actingAs($user, 'sanctum');

        $this->assertTrue($user->hasRole('MEMBER'));
        $this->assertTrue($user->hasPermissionTo('books.view'));
        $this->assertFalse($user->hasPermissionTo('users.create'));
    }
}
