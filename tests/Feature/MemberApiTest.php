<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\Loan;
use App\Models\Major;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberApiTest extends TestCase
{
    use RefreshDatabase;

    private MemberType $memberType;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->memberType = MemberType::create(['code' => 'STD', 'name' => 'Student', 'is_active' => true]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Library Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->memberUser = User::create([
            'name' => 'Member User',
            'email' => 'member@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->memberUser->assignRole('MEMBER');

        $this->member = Member::create([
            'member_number' => 'MBR-001',
            'name' => 'Test Member',
            'member_type_id' => $this->memberType->id,
            'user_id' => $this->memberUser->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    // ─── A. Authentication ────────────────────────────────────────────────────

    public function test_guest_cannot_list_members(): void
    {
        $this->getJson('/api/members')->assertStatus(401);
    }

    public function test_guest_cannot_view_member(): void
    {
        $this->getJson("/api/members/{$this->member->id}")->assertStatus(401);
    }

    public function test_guest_cannot_create_member(): void
    {
        $this->postJson('/api/members', [])->assertStatus(401);
    }

    public function test_guest_cannot_access_self_member(): void
    {
        $this->getJson('/api/me/member')->assertStatus(401);
    }

    // ─── B. Authorization ─────────────────────────────────────────────────────

    public function test_super_admin_can_list_members(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['items', 'pagination']]);
    }

    public function test_super_admin_can_view_member(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/members/{$this->member->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.member_number', 'MBR-001');
    }

    public function test_library_staff_can_list_members(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/members')
            ->assertStatus(200);
    }

    public function test_member_cannot_list_all_members(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/members')
            ->assertStatus(403);
    }

    public function test_member_cannot_view_another_member(): void
    {
        $anotherMember = Member::create([
            'member_number' => 'MBR-002',
            'name' => 'Other Member',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson("/api/members/{$anotherMember->id}")
            ->assertStatus(403);
    }

    public function test_member_can_view_own_member_record(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson("/api/members/{$this->member->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->member->id);
    }

    public function test_member_cannot_create_member(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-999',
                'name' => 'Unauthorized',
                'member_type_id' => $this->memberType->id,
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(403);
    }

    public function test_member_cannot_update_another_member(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->patchJson("/api/members/{$this->member->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_member_cannot_delete_member(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->deleteJson("/api/members/{$this->member->id}")
            ->assertStatus(403);
    }

    // ─── C. CRUD ─────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_member(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-NEW',
                'name' => 'New Member',
                'member_type_id' => $this->memberType->id,
                'joined_at' => '2026-06-01',
                'status' => 'ACTIVE',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.member_number', 'MBR-NEW');

        $this->assertDatabaseHas('members', ['member_number' => 'MBR-NEW']);
    }

    public function test_super_admin_can_update_member(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/members/{$this->member->id}", ['name' => 'Updated Name']);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_delete_member_without_loans_succeeds(): void
    {
        $noLoanMember = Member::create([
            'member_number' => 'MBR-DEL',
            'name' => 'Deletable Member',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/members/{$noLoanMember->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('members', ['id' => $noLoanMember->id]);
    }

    public function test_delete_member_with_loans_is_blocked(): void
    {
        // Create a loan for the member to simulate history
        Loan::create([
            'loan_number' => 'LN-001',
            'member_id' => $this->member->id,
            'borrowed_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/members/{$this->member->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('members', ['id' => $this->member->id]);
    }

    // ─── D. Validation ───────────────────────────────────────────────────────

    public function test_create_requires_member_number(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'name' => 'Test',
                'member_type_id' => $this->memberType->id,
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_number']);
    }

    public function test_create_rejects_duplicate_member_number(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-001', // Already exists
                'name' => 'Test',
                'member_type_id' => $this->memberType->id,
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_number']);
    }

    public function test_create_rejects_duplicate_identity_number(): void
    {
        $existingMember = Member::create([
            'member_number' => 'MBR-010',
            'name' => 'Existing',
            'member_type_id' => $this->memberType->id,
            'identity_number' => 'ID-UNIQUE-123',
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-NEWX',
                'name' => 'Test',
                'member_type_id' => $this->memberType->id,
                'identity_number' => 'ID-UNIQUE-123',
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identity_number']);
    }

    public function test_create_rejects_duplicate_user_id(): void
    {
        // memberUser is already linked to $this->member
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-DUP',
                'name' => 'Test',
                'member_type_id' => $this->memberType->id,
                'user_id' => $this->memberUser->id,
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_create_rejects_invalid_member_type(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-X',
                'name' => 'Test',
                'member_type_id' => 99999,
                'joined_at' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['member_type_id']);
    }

    public function test_create_rejects_invalid_status(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/members', [
                'member_number' => 'MBR-STBAD',
                'name' => 'Test',
                'member_type_id' => $this->memberType->id,
                'joined_at' => '2026-01-01',
                'status' => 'INVALID_STATUS',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_list_rejects_invalid_sort(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members?sort=password')
            ->assertStatus(422);
    }

    public function test_list_rejects_invalid_direction(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members?direction=sideways')
            ->assertStatus(422);
    }

    // ─── E. Search / Filter ───────────────────────────────────────────────────

    public function test_search_by_name(): void
    {
        Member::create([
            'member_number' => 'MBR-SRH',
            'name' => 'Budi Santoso',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members?search=budi');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Budi Santoso', $response->json('data.items.0.name'));
    }

    public function test_filter_by_status(): void
    {
        Member::create([
            'member_number' => 'MBR-INACT',
            'name' => 'Inactive One',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'INACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members?status=INACTIVE');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('INACTIVE', $response->json('data.items.0.status'));
    }

    public function test_filter_by_member_type(): void
    {
        $otherType = MemberType::create(['code' => 'TCHR', 'name' => 'Teacher', 'is_active' => true]);
        Member::create([
            'member_number' => 'MBR-TCH',
            'name' => 'Teacher A',
            'member_type_id' => $otherType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/members?member_type_id={$otherType->id}");

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Teacher A', $response->json('data.items.0.name'));
    }

    public function test_pagination_defaults(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members');

        $response->assertStatus(200);
        $this->assertArrayHasKey('current_page', $response->json('data.pagination'));
        $this->assertArrayHasKey('per_page', $response->json('data.pagination'));
        $this->assertEquals(15, $response->json('data.pagination.per_page'));
    }

    public function test_sorting_by_name_asc(): void
    {
        Member::create([
            'member_number' => 'MBR-ZZZ',
            'name' => 'Zeta',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members?sort=name&direction=asc');

        $items = $response->json('data.items');
        $this->assertEquals('Test Member', $items[0]['name']); // T comes before Z
    }

    // ─── F. Self Profile ─────────────────────────────────────────────────────

    public function test_member_can_access_own_self_profile(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/member')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $this->member->id)
            ->assertJsonPath('data.name', 'Test Member');
    }

    public function test_user_without_member_record_gets_404_on_self_profile(): void
    {
        $noMemberUser = User::create([
            'name' => 'No Member',
            'email' => 'nomember@test.com',
            'password' => Hash::make('password'),
        ]);
        $noMemberUser->assignRole('MEMBER');

        $this->actingAs($noMemberUser, 'sanctum')
            ->getJson('/api/me/member')
            ->assertStatus(404);
    }

    public function test_self_profile_does_not_accept_member_id_parameter(): void
    {
        // The /me/member endpoint ignores any path substitution; it always uses the auth user
        $anotherMember = Member::create([
            'member_number' => 'MBR-OTHER',
            'name' => 'Other Person',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        // This should still return the AUTHENTICATED user's own member record
        $response = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/member');

        $response->assertStatus(200);
        $this->assertEquals($this->member->id, $response->json('data.id'));
    }

    // ─── G. Security ─────────────────────────────────────────────────────────

    public function test_response_never_exposes_password(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/members/{$this->member->id}");

        $response->assertStatus(200);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    public function test_list_response_never_exposes_password(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members');

        $json = json_encode($response->json());
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('remember_token', $json);
    }

    public function test_404_for_unknown_member(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/members/999999')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    // ─── H. Update member ────────────────────────────────────────────────────

    public function test_update_unique_constraints_ignore_self(): void
    {
        // Updating a member with their own member_number should not trigger unique error
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/members/{$this->member->id}", [
                'member_number' => 'MBR-001', // Same as current
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_filter_by_class_level(): void
    {
        $classLevel = ClassLevel::create(['code' => 'XI', 'name' => 'Kelas XI']);
        Member::create([
            'member_number' => 'MBR-CL',
            'name' => 'Class XI Student',
            'member_type_id' => $this->memberType->id,
            'class_level_id' => $classLevel->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/members?class_level_id={$classLevel->id}");

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Class XI Student', $response->json('data.items.0.name'));
    }

    public function test_filter_by_major(): void
    {
        $major = Major::create(['code' => 'TKJ', 'name' => 'TKJ']);
        Member::create([
            'member_number' => 'MBR-MAJ',
            'name' => 'TKJ Student',
            'member_type_id' => $this->memberType->id,
            'major_id' => $major->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/members?major_id={$major->id}");

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('TKJ Student', $response->json('data.items.0.name'));
    }
}
