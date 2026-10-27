<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CollectionType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

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
    }

    // ─── A. AUTHENTICATION ────────────────────────────────────────────────────

    public function test_guest_cannot_list_collection_types(): void
    {
        $this->getJson('/api/collection-types')->assertStatus(401);
    }

    public function test_guest_cannot_create_collection_type(): void
    {
        $this->postJson('/api/collection-types', ['code' => 'X', 'name' => 'X'])->assertStatus(401);
    }

    // ─── B. AUTHORIZATION ─────────────────────────────────────────────────────

    public function test_member_cannot_create_collection_type(): void
    {
        Sanctum::actingAs($this->memberUser);
        $this->postJson('/api/collection-types', ['code' => 'NEWTYPE', 'name' => 'New Type'])
            ->assertStatus(403);
    }

    public function test_member_can_view_collection_types(): void
    {
        CollectionType::factory()->create(['code' => 'VIS', 'name' => 'Visible']);

        Sanctum::actingAs($this->memberUser);
        $this->getJson('/api/collection-types')->assertStatus(200);
    }

    public function test_member_cannot_update_collection_type(): void
    {
        $ct = CollectionType::factory()->create();

        Sanctum::actingAs($this->memberUser);
        $this->patchJson("/api/collection-types/{$ct->id}", ['name' => 'Changed'])->assertStatus(403);
    }

    public function test_member_cannot_delete_collection_type(): void
    {
        $ct = CollectionType::factory()->create();

        Sanctum::actingAs($this->memberUser);
        $this->deleteJson("/api/collection-types/{$ct->id}")->assertStatus(403);
    }

    // ─── C. LIST / INDEX ──────────────────────────────────────────────────────

    public function test_super_admin_can_list_collection_types(): void
    {
        CollectionType::factory()->count(3)->create();

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson('/api/collection-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items' => [
                        '*' => ['id', 'code', 'name', 'is_active', 'sort_order', 'created_at', 'updated_at'],
                    ],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_library_staff_can_list_collection_types(): void
    {
        CollectionType::factory()->create();

        Sanctum::actingAs($this->libraryStaff);
        $this->getJson('/api/collection-types')->assertStatus(200);
    }

    public function test_index_supports_search(): void
    {
        CollectionType::factory()->create(['code' => 'TEXTBOOK', 'name' => 'Buku Pelajaran']);
        CollectionType::factory()->create(['code' => 'FICTION', 'name' => 'Fiksi']);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson('/api/collection-types?search=pelajaran');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('TEXTBOOK', $response->json('data.items.0.code'));
    }

    public function test_index_supports_filter_by_is_active(): void
    {
        CollectionType::factory()->create(['code' => 'ACTIVE', 'name' => 'Active', 'is_active' => true]);
        CollectionType::factory()->create(['code' => 'INACTIVE', 'name' => 'Inactive', 'is_active' => false]);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson('/api/collection-types?is_active=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('ACTIVE', $response->json('data.items.0.code'));
    }

    public function test_index_pagination_defaults(): void
    {
        CollectionType::factory()->count(5)->create();

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson('/api/collection-types');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, $response->json('data.pagination.total'));
    }

    public function test_index_rejects_invalid_sort(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->getJson('/api/collection-types?sort=password')->assertStatus(422);
    }

    // ─── D. SHOW ──────────────────────────────────────────────────────────────

    public function test_super_admin_can_view_collection_type(): void
    {
        $ct = CollectionType::factory()->create(['code' => 'TEST', 'name' => 'Test Type']);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson("/api/collection-types/{$ct->id}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['code' => 'TEST', 'name' => 'Test Type']]);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->getJson('/api/collection-types/999999')->assertStatus(404);
    }

    // ─── E. CREATE ────────────────────────────────────────────────────────────

    public function test_super_admin_can_create_collection_type(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $response = $this->postJson('/api/collection-types', [
            'code' => 'NOVEL',
            'name' => 'Novel',
            'description' => 'Koleksi novel',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => ['code' => 'NOVEL', 'name' => 'Novel'],
            ]);

        $this->assertDatabaseHas('collection_types', ['code' => 'NOVEL']);
    }

    public function test_library_staff_can_create_collection_type(): void
    {
        Sanctum::actingAs($this->libraryStaff);
        $response = $this->postJson('/api/collection-types', [
            'code' => 'STAFF_CREATED',
            'name' => 'Staff Created',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_rejects_missing_code(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', ['name' => 'No Code'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_rejects_missing_name(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', ['code' => 'NONAME'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_rejects_duplicate_code(): void
    {
        CollectionType::factory()->create(['code' => 'DUPCODE']);

        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', ['code' => 'DUPCODE', 'name' => 'Duplicate'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_rejects_code_exceeding_max_length(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', [
            'code' => str_repeat('A', 51),
            'name' => 'Too Long Code',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_create_rejects_name_exceeding_max_length(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', [
            'code' => 'LONGNAME',
            'name' => str_repeat('A', 101),
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_create_description_is_nullable(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $response = $this->postJson('/api/collection-types', [
            'code' => 'NODESC',
            'name' => 'No Description',
            'description' => null,
        ]);

        $response->assertStatus(201);
        $this->assertNull($response->json('data.description'));
    }

    public function test_is_active_defaults_to_true(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->postJson('/api/collection-types', ['code' => 'DEFAULTS', 'name' => 'Defaults']);

        $this->assertDatabaseHas('collection_types', ['code' => 'DEFAULTS', 'is_active' => true]);
    }

    // ─── F. UPDATE ────────────────────────────────────────────────────────────

    public function test_super_admin_can_update_collection_type(): void
    {
        $ct = CollectionType::factory()->create(['code' => 'OLD', 'name' => 'Old Name']);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->patchJson("/api/collection-types/{$ct->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJson(['data' => ['name' => 'New Name']]);
    }

    public function test_update_unique_constraint_ignores_self(): void
    {
        $ct = CollectionType::factory()->create(['code' => 'SELFCODE']);

        Sanctum::actingAs($this->superAdmin);
        // Updating with the same code on the same record should not fail
        $this->patchJson("/api/collection-types/{$ct->id}", ['code' => 'SELFCODE', 'name' => 'Updated'])
            ->assertStatus(200);
    }

    public function test_update_rejects_duplicate_code_from_other_record(): void
    {
        CollectionType::factory()->create(['code' => 'TAKEN']);
        $ct = CollectionType::factory()->create(['code' => 'MINE']);

        Sanctum::actingAs($this->superAdmin);
        $this->patchJson("/api/collection-types/{$ct->id}", ['code' => 'TAKEN'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_can_deactivate_collection_type(): void
    {
        $ct = CollectionType::factory()->create(['is_active' => true]);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->patchJson("/api/collection-types/{$ct->id}", ['is_active' => false]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.is_active'));
    }

    public function test_update_returns_404_for_unknown_id(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->patchJson('/api/collection-types/999999', ['name' => 'Updated'])->assertStatus(404);
    }

    // ─── G. DELETE ────────────────────────────────────────────────────────────

    public function test_super_admin_can_delete_unused_collection_type(): void
    {
        $ct = CollectionType::factory()->create();

        Sanctum::actingAs($this->superAdmin);
        $this->deleteJson("/api/collection-types/{$ct->id}")->assertStatus(200);

        $this->assertDatabaseMissing('collection_types', ['id' => $ct->id]);
    }

    public function test_cannot_delete_collection_type_used_by_book(): void
    {
        $ct = CollectionType::factory()->create();
        Book::create([
            'title' => 'Test Book',
            'collection_type_id' => $ct->id,
            'publication_year' => 2024,
            'language' => 'id',
        ]);

        Sanctum::actingAs($this->superAdmin);
        $response = $this->deleteJson("/api/collection-types/{$ct->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('collection_types', ['id' => $ct->id]);
    }

    public function test_delete_returns_404_for_unknown_id(): void
    {
        Sanctum::actingAs($this->superAdmin);
        $this->deleteJson('/api/collection-types/999999')->assertStatus(404);
    }

    // ─── H. RESPONSE INTEGRITY ────────────────────────────────────────────────

    public function test_response_does_not_expose_sensitive_data(): void
    {
        $ct = CollectionType::factory()->create();

        Sanctum::actingAs($this->superAdmin);
        $response = $this->getJson("/api/collection-types/{$ct->id}");

        $data = $response->json('data');
        $this->assertArrayNotHasKey('pivot', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('is_active', $data);
    }
}
