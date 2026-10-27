<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and permissions directly (no seeder to avoid permission table issue)
        $superAdminRole = Role::create(['name' => 'SUPER_ADMIN', 'guard_name' => 'web']);
        $libraryStaffRole = Role::create(['name' => 'LIBRARY_STAFF', 'guard_name' => 'web']);
        $memberRole = Role::create(['name' => 'MEMBER', 'guard_name' => 'web']);

        // Create admin-level permissions
        $perms = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'books.view',
        ];
        foreach ($perms as $p) {
            Permission::create(['name' => $p, 'guard_name' => 'web']);
        }

        $superAdminRole->syncPermissions($perms);
        $libraryStaffRole->syncPermissions(['users.view', 'books.view']);
        // MEMBER gets no admin perms
    }

    // ────────────────────────────────────────────────────────────────────────
    // USER TESTS
    // ────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_list_users(): void
    {
        $response = $this->getJson('/api/users');
        $response->assertStatus(401);
    }

    public function test_member_cannot_list_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('MEMBER');

        $response = $this->actingAs($user)->getJson('/api/users');
        $response->assertStatus(403);
    }

    public function test_library_staff_can_list_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $response = $this->actingAs($user)->getJson('/api/users');
        $response->assertStatus(200);
    }

    public function test_library_staff_cannot_create_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $response = $this->actingAs($user)->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(403);
    }

    public function test_super_admin_can_list_users(): void
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($user)->getJson('/api/users');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['items', 'pagination']]);
    }

    public function test_super_admin_can_create_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'roles' => ['LIBRARY_STAFF'],
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.email', 'newuser@example.com');
    }

    public function test_user_creation_rejects_multiple_core_roles_and_allows_each_single_core_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $coreRoleCombinations = [
            ['SUPER_ADMIN', 'LIBRARY_STAFF'],
            ['SUPER_ADMIN', 'MEMBER'],
            ['LIBRARY_STAFF', 'MEMBER'],
            ['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'],
        ];

        foreach ($coreRoleCombinations as $index => $roles) {
            $response = $this->actingAs($admin)->postJson('/api/users', [
                'name' => 'Invalid User '.$index,
                'email' => "invalid-user-{$index}@example.com",
                'password' => 'password123',
                'roles' => $roles,
            ]);
            $response->assertStatus(422)->assertJsonValidationErrors(['roles']);
            if ($index === 0) {
                $response->assertJsonFragment([
                    'roles' => ['Pilih maksimal satu role inti: SUPER_ADMIN, LIBRARY_STAFF, atau MEMBER.'],
                ]);
            }

            $this->assertDatabaseMissing('users', ['email' => "invalid-user-{$index}@example.com"]);
        }

        foreach (['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'] as $index => $role) {
            $email = "single-role-{$index}@example.com";

            $this->actingAs($admin)->postJson('/api/users', [
                'name' => 'Single Role User '.$index,
                'email' => $email,
                'password' => 'password123',
                'roles' => [$role],
            ])->assertStatus(201);

            $this->assertSame([$role], User::where('email', $email)->firstOrFail()->getRoleNames()->all());
        }

        Role::create(['name' => 'CUSTOM_ROLE', 'guard_name' => 'web']);
        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Member With Additional Role',
            'email' => 'member-custom-role@example.com',
            'password' => 'password123',
            'roles' => ['MEMBER', 'CUSTOM_ROLE'],
        ])->assertStatus(201);

        $this->assertEqualsCanonicalizing(
            ['MEMBER', 'CUSTOM_ROLE'],
            User::where('email', 'member-custom-role@example.com')->firstOrFail()->getRoleNames()->all(),
        );
    }

    public function test_super_admin_can_update_user(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $target = User::factory()->create(['name' => 'Old Name']);
        $target->assignRole('MEMBER');

        $response = $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
            'name' => 'New Name',
        ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');
        $this->assertSame(['MEMBER'], $target->fresh()->getRoleNames()->all());
    }

    public function test_user_update_rejects_multiple_core_roles_and_preserves_roles_until_valid_selection(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');
        $target = User::factory()->create();
        $target->assignRole('MEMBER');

        $coreRoleCombinations = [
            ['SUPER_ADMIN', 'LIBRARY_STAFF'],
            ['SUPER_ADMIN', 'MEMBER'],
            ['LIBRARY_STAFF', 'MEMBER'],
            ['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'],
        ];

        foreach ($coreRoleCombinations as $roles) {
            $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
                'roles' => $roles,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['roles']);

            $this->assertSame(['MEMBER'], $target->fresh()->getRoleNames()->all());
        }

        foreach (['SUPER_ADMIN', 'LIBRARY_STAFF', 'MEMBER'] as $role) {
            $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
                'roles' => [$role],
            ])->assertStatus(200);

            $this->assertSame([$role], $target->fresh()->getRoleNames()->all());
        }

        Role::create(['name' => 'CUSTOM_ROLE', 'guard_name' => 'web']);
        $this->actingAs($admin)->patchJson("/api/users/{$target->id}", [
            'roles' => ['LIBRARY_STAFF', 'CUSTOM_ROLE'],
        ])->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            ['LIBRARY_STAFF', 'CUSTOM_ROLE'],
            $target->fresh()->getRoleNames()->all(),
        );
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$admin->id}");
        $response->assertStatus(400);
    }

    public function test_library_staff_cannot_delete_users(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('LIBRARY_STAFF');

        $target = User::factory()->create();

        $response = $this->actingAs($staff)->deleteJson("/api/users/{$target->id}");
        $response->assertStatus(403);
    }

    // ────────────────────────────────────────────────────────────────────────
    // ROLE TESTS
    // ────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_list_roles(): void
    {
        $response = $this->getJson('/api/roles');
        $response->assertStatus(401);
    }

    public function test_member_cannot_list_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('MEMBER');

        $response = $this->actingAs($user)->getJson('/api/roles');
        $response->assertStatus(403);
    }

    public function test_library_staff_cannot_list_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $response = $this->actingAs($user)->getJson('/api/roles');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_list_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($user)->getJson('/api/roles');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_create_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($admin)->postJson('/api/roles', [
            'name' => 'CUSTOM_ROLE',
            'permissions' => ['books.view'],
        ]);
        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'CUSTOM_ROLE');
    }

    public function test_super_admin_cannot_modify_core_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $role = Role::findByName('LIBRARY_STAFF');

        $response = $this->actingAs($admin)->patchJson("/api/roles/{$role->id}", [
            'name' => 'HACKED_ROLE',
        ]);
        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_delete_core_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('SUPER_ADMIN');

        $role = Role::findByName('MEMBER');

        $response = $this->actingAs($admin)->deleteJson("/api/roles/{$role->id}");
        $response->assertStatus(403);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PERMISSION TESTS
    // ────────────────────────────────────────────────────────────────────────

    public function test_guest_cannot_list_permissions(): void
    {
        $response = $this->getJson('/api/permissions');
        $response->assertStatus(401);
    }

    public function test_member_cannot_list_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('MEMBER');

        $response = $this->actingAs($user)->getJson('/api/permissions');
        $response->assertStatus(403);
    }

    public function test_library_staff_cannot_list_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $response = $this->actingAs($user)->getJson('/api/permissions');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_list_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('SUPER_ADMIN');

        $response = $this->actingAs($user)->getJson('/api/permissions');
        $response->assertStatus(200);
    }
}
