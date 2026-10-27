<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the roles and permissions for tests
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_sanctum_installation_and_user_model_traits(): void
    {
        // 1. Sanctum installed (personal_access_tokens exists)
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));

        // 2 & 3. User model uses HasApiTokens and HasRoles
        $traits = class_uses_recursive(User::class);
        $this->assertContains('Laravel\Sanctum\HasApiTokens', $traits);
        $this->assertContains('Spatie\Permission\Traits\HasRoles', $traits);
    }

    public function test_roles_and_permissions_tables_exist(): void
    {
        // 4 & 5. Spatie tables exist
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('role_has_permissions'));
    }

    public function test_required_roles_exist(): void
    {
        // 6, 7, 8. Core roles exist
        $this->assertDatabaseHas('roles', ['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'LIBRARY_STAFF', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'MEMBER', 'guard_name' => 'web']);
    }

    public function test_super_admin_has_all_permissions(): void
    {
        // 9. SUPER_ADMIN has all defined permissions
        $role = Role::where('name', 'SUPER_ADMIN')->first();
        $totalPermissions = Permission::count();

        $this->assertGreaterThan(0, $totalPermissions);
        $this->assertCount($totalPermissions, $role->permissions);
    }

    public function test_library_staff_does_not_have_users_create_permission(): void
    {
        // 10. LIBRARY_STAFF does not have users.create
        $role = Role::where('name', 'LIBRARY_STAFF')->first();
        $this->assertFalse($role->hasPermissionTo('users.create'));
        $this->assertTrue($role->hasPermissionTo('books.create'));
        $this->assertTrue($role->hasPermissionTo('users.view'));
    }

    public function test_member_has_correct_permissions(): void
    {
        // 11 & 12. MEMBER permissions
        $role = Role::where('name', 'MEMBER')->first();

        $this->assertFalse($role->hasPermissionTo('books.create'));
        $this->assertTrue($role->hasPermissionTo('books.view'));
    }

    public function test_role_assignment_works(): void
    {
        // 13. Role assignment
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $this->assertTrue($user->hasRole('LIBRARY_STAFF'));
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => Role::where('name', 'LIBRARY_STAFF')->first()->id,
            'model_id' => $user->id,
            'model_type' => User::class,
        ]);
    }

    public function test_permission_check_works(): void
    {
        // 14. Permission check via role
        $user = User::factory()->create();
        $user->assignRole('MEMBER');

        $this->assertTrue($user->can('books.view'));
        $this->assertFalse($user->can('books.create'));
    }

    public function test_user_can_obtain_sanctum_token_via_login(): void
    {
        // 15. User can obtain token via login API
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'device_name' => 'test_device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'email', 'name'],
                    'token',
                ],
            ]);

        // Pastikan password tidak diexpose
        $response->assertJsonMissing(['password' => $user->password]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_protected_route_rejects_unauthenticated_request(): void
    {
        // 16. Unauthenticated rejection
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
    }

    public function test_protected_route_accepts_authenticated_request(): void
    {
        // 17. Authenticated acceptance
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');
        $response->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_token_revocation_logout_works(): void
    {
        // 18. Revoke token
        $user = User::factory()->create();
        $token = $user->createToken('test_device')->plainTextToken;

        // Pastikan token valid
        $response1 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/auth/me');
        $response1->assertStatus(200);

        // Logout via endpoint
        $response2 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/auth/logout');
        $response2->assertStatus(200);

        // Verifikasi token terhapus dari database
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Refresh application state to clear auth cache in testing
        $this->refreshApplication();

        // Pastikan request berikutnya dengan token tersebut ditolak
        $response3 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/auth/me');
        $response3->assertStatus(401);
    }
}
