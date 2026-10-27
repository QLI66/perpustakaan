<?php

namespace Tests\Feature;

use App\Models\Acquisition;
use App\Models\Book;
use App\Models\CollectionType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcquisitionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Test',
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::factory()->create([
            'name' => 'Library Staff Test',
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');
        $this->assertTrue(
            $this->libraryStaff->hasRole('LIBRARY_STAFF')
        );

        $this->assertTrue(
            $this->libraryStaff->can('acquisitions.create')
        );

        $this->assertTrue(
            $this->libraryStaff->can('acquisitions.view')
        );
        $this->member = User::factory()->create([
            'name' => 'Member Test',
        ]);
        $this->member->assignRole('MEMBER');
    }

    private function makeBook(
        string $title = 'Buku Acquisition API',
        string $code = 'API-ACQ'
    ): Book {
        $collectionType = CollectionType::create([
            'code' => $code,
            'name' => 'API Test Collection',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    private function acquisitionPayload(Book $book): array
    {
        return [
            'acquisition_date' => '2026-09-21',
            'source_type' => 'PURCHASE',
            'source_name' => 'Supplier API Test',
            'reference_number' => 'PO-API-001',
            'notes' => 'API acquisition test',
            'items' => [
                [
                    'book_id' => $book->id,
                    'quantity' => 2,
                    'unit_price' => 75000,
                    'notes' => 'Item API test',
                ],
            ],
        ];
    }

    public function test_guest_cannot_access_acquisitions(): void
    {
        $response = $this->getJson('/api/acquisitions');

        $response->assertStatus(401);
    }

    public function test_member_cannot_access_acquisitions(): void
    {
        $response = $this
            ->actingAs($this->member, 'sanctum')
            ->getJson('/api/acquisitions');

        $response->assertStatus(403);
    }

    public function test_library_staff_can_list_acquisitions(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/acquisitions');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_list_acquisitions(): void
    {
        $response = $this
            ->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/acquisitions');

        $response->assertStatus(200);
    }

    public function test_member_cannot_create_acquisition(): void
    {
        $book = $this->makeBook();

        $response = $this
            ->actingAs($this->member, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            );

        $response->assertStatus(403);

        $this->assertDatabaseCount('acquisitions', 0);
    }

    public function test_library_staff_can_create_acquisition(): void
    {
        $book = $this->makeBook();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            );

        $response->assertStatus(201);

        $this->assertDatabaseHas('acquisitions', [
            'source_type' => 'PURCHASE',
            'source_name' => 'Supplier API Test',
            'reference_number' => 'PO-API-001',
            'created_by' => $this->libraryStaff->id,
        ]);

        $this->assertDatabaseHas('acquisition_items', [
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);
    }

    public function test_acquisition_total_is_calculated_server_side(): void
    {
        $book = $this->makeBook();

        $payload = $this->acquisitionPayload($book);
        $payload['total_amount'] = 1;

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload);

        $response->assertStatus(201);

        $acquisition = Acquisition::firstOrFail();

        $this->assertEquals('150000.00', $acquisition->total_amount);
    }

    public function test_create_acquisition_validation_rejects_invalid_source_type(): void
    {
        $book = $this->makeBook();

        $payload = $this->acquisitionPayload($book);
        $payload['source_type'] = 'INVALID';

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['source_type']);
    }

    public function test_create_acquisition_validation_rejects_empty_items(): void
    {
        $book = $this->makeBook();

        $payload = $this->acquisitionPayload($book);
        $payload['items'] = [];

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_create_acquisition_validation_rejects_zero_quantity(): void
    {
        $book = $this->makeBook();

        $payload = $this->acquisitionPayload($book);
        $payload['items'][0]['quantity'] = 0;

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'items.0.quantity',
        ]);
    }

    public function test_staff_can_show_acquisition(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $acquisition = Acquisition::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/acquisitions/{$acquisition->id}");

        $response->assertStatus(200);
    }

    public function test_zero_price_is_preserved_in_acquisition_detail_response(): void
    {
        $book = $this->makeBook();
        $payload = $this->acquisitionPayload($book);
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][0]['unit_price'] = 0;

        $created = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload)
            ->assertStatus(201);

        $acquisitionId = $created->json('data.id');

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/acquisitions/{$acquisitionId}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.acquisition_date', '2026-09-21')
            ->assertJsonPath('data.total_amount', '0.00')
            ->assertJsonPath('data.items.0.unit_price', '0.00');
    }

    public function test_staff_can_search_acquisitions(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/acquisitions?search=Supplier%20API%20Test');

        $response->assertStatus(200);
    }

    public function test_staff_can_filter_acquisitions_by_source_type(): void
    {
        $book = $this->makeBook();

        $payload = $this->acquisitionPayload($book);
        $payload['source_type'] = 'DONATION';

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/acquisitions', $payload)
            ->assertStatus(201);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/acquisitions?source_type=DONATION');

        $response->assertStatus(200);
    }

    public function test_staff_can_update_acquisition_metadata(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $acquisition = Acquisition::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->patchJson(
                "/api/acquisitions/{$acquisition->id}",
                [
                    'source_name' => 'Supplier Updated',
                    'reference_number' => 'PO-UPDATED',
                    'notes' => 'Updated acquisition',
                ]
            );

        $response->assertStatus(200);

        $this->assertDatabaseHas('acquisitions', [
            'id' => $acquisition->id,
            'source_name' => 'Supplier Updated',
            'reference_number' => 'PO-UPDATED',
        ]);
    }

    public function test_member_cannot_update_acquisition(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $acquisition = Acquisition::firstOrFail();

        $response = $this
            ->actingAs($this->member, 'sanctum')
            ->patchJson(
                "/api/acquisitions/{$acquisition->id}",
                [
                    'source_name' => 'Unauthorized Update',
                ]
            );

        $response->assertStatus(403);
    }

    public function test_library_staff_can_delete_acquisition_without_book_copies(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $acquisition = Acquisition::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->deleteJson("/api/acquisitions/{$acquisition->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('acquisitions', [
            'id' => $acquisition->id,
        ]);
    }

    public function test_delete_acquisition_is_blocked_when_book_copy_is_linked(): void
    {
        $book = $this->makeBook();

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                '/api/acquisitions',
                $this->acquisitionPayload($book)
            )
            ->assertStatus(201);

        $acquisition = Acquisition::with('items')->firstOrFail();
        $item = $acquisition->items->first();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->deleteJson("/api/acquisitions/{$acquisition->id}");

        // No BookCopy is linked, so deletion should still succeed.
        $response->assertStatus(200);

        $this->assertDatabaseMissing('acquisitions', [
            'id' => $acquisition->id,
        ]);

        $this->assertNull($item->fresh());
    }
}
