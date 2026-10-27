<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockOpnameApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    private Location $location;

    private Book $book;

    private BookCopy $bookCopy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin-stock@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::factory()->create([
            'name' => 'Library Staff Test',
            'email' => 'staff-stock@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->member = User::factory()->create([
            'name' => 'Member Test',
            'email' => 'member-stock@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->member->assignRole('MEMBER');

        $this->location = Location::create([
            'code' => 'STK-LOC',
            'name' => 'Stock Opname Location',
        ]);

        $collectionType = CollectionType::create([
            'code' => 'STK-COL',
            'name' => 'Stock Opname Collection',
        ]);

        $this->book = Book::create([
            'title' => 'Stock Opname Test Book',
            'collection_type_id' => $collectionType->id,
        ]);

        $this->bookCopy = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'STK-BC-001',
            'inventory_number' => 'STK-INV-001',
            'location_id' => $this->location->id,
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
        ]);
    }

    private function createStockOpname(
        ?User $user = null,
        ?int $locationId = null
    ): StockOpname {
        $user ??= $this->libraryStaff;

        $response = $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
                'location_id' => $locationId,
                'notes' => 'API stock opname test',
            ]);

        $response->assertStatus(201);

        return StockOpname::findOrFail(
            $response->json('data.id')
        );
    }

    private function startStockOpname(
        StockOpname $opname,
        ?User $user = null
    ): void {
        $user ??= $this->libraryStaff;

        $this
            ->actingAs($user, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/start"
            )
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION
    // -------------------------------------------------------------------------

    public function test_guest_cannot_list_stock_opnames(): void
    {
        $this
            ->getJson('/api/stock-opnames')
            ->assertStatus(401);
    }

    public function test_guest_cannot_create_stock_opname(): void
    {
        $this
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
            ])
            ->assertStatus(401);
    }

    public function test_guest_cannot_view_stock_opname(): void
    {
        $opname = StockOpname::create([
            'opname_number' => 'OPN-2026-999999',
            'opname_date' => '2026-09-21',
            'status' => 'DRAFT',
        ]);

        $this
            ->getJson("/api/stock-opnames/{$opname->id}")
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // AUTHORIZATION
    // -------------------------------------------------------------------------

    public function test_super_admin_can_list(): void
    {
        $this
            ->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/stock-opnames')
            ->assertStatus(200);
    }

    public function test_library_staff_can_list(): void
    {
        $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/stock-opnames')
            ->assertStatus(200);
    }

    public function test_member_cannot_list(): void
    {
        $this
            ->actingAs($this->member, 'sanctum')
            ->getJson('/api/stock-opnames')
            ->assertStatus(403);
    }

    public function test_member_cannot_create(): void
    {
        $this
            ->actingAs($this->member, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
            ])
            ->assertStatus(403);
    }

    public function test_member_cannot_update(): void
    {
        $opname = $this->createStockOpname();

        $this
            ->actingAs($this->member, 'sanctum')
            ->patchJson(
                "/api/stock-opnames/{$opname->id}",
                [
                    'notes' => 'Unauthorized',
                ]
            )
            ->assertStatus(403);
    }

    public function test_member_cannot_delete(): void
    {
        $opname = $this->createStockOpname();

        $this
            ->actingAs($this->member, 'sanctum')
            ->deleteJson(
                "/api/stock-opnames/{$opname->id}"
            )
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    public function test_library_staff_can_create_stock_opname(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
                'location_id' => $this->location->id,
                'notes' => 'Initial stock opname',
            ]);

        $response->assertStatus(201);

        $response->assertJsonPath(
            'data.status',
            'DRAFT'
        );

        $response->assertJsonPath(
            'data.location_id',
            $this->location->id
        );

        $this->assertDatabaseHas('stock_opnames', [
            'status' => 'DRAFT',
            'location_id' => $this->location->id,
        ]);
    }

    public function test_stock_opname_number_is_generated(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->assertStringStartsWith(
            'OPN-2026-',
            $opname->opname_number
        );
    }

    public function test_create_without_location_is_allowed(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
            ]);

        $response->assertStatus(201);

        $this->assertNull(
            $response->json('data.location_id')
        );
    }

    // -------------------------------------------------------------------------
    // VALIDATION
    // -------------------------------------------------------------------------

    public function test_opname_date_is_required(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'location_id' => $this->location->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'opname_date',
        ]);
    }

    public function test_invalid_opname_date_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => 'NOT-A-DATE',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'opname_date',
        ]);
    }

    public function test_invalid_location_is_rejected(): void
    {
        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/stock-opnames', [
                'opname_date' => '2026-09-21',
                'location_id' => 999999,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'location_id',
        ]);
    }

    // -------------------------------------------------------------------------
    // LIST / DETAIL
    // -------------------------------------------------------------------------

    public function test_can_list_stock_opnames(): void
    {
        $this->createStockOpname();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/stock-opnames');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'items',
                'pagination',
            ],
        ]);

        $this->assertCount(
            1,
            $response->json('data.items')
        );
    }

    public function test_can_filter_by_status(): void
    {
        $this->createStockOpname();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/stock-opnames?status=DRAFT'
            );

        $response->assertStatus(200);

        $this->assertCount(
            1,
            $response->json('data.items')
        );
    }

    public function test_can_filter_by_location(): void
    {
        $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                "/api/stock-opnames?location_id={$this->location->id}"
            );

        $response->assertStatus(200);

        $this->assertCount(
            1,
            $response->json('data.items')
        );
    }

    public function test_can_show_stock_opname_detail(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                "/api/stock-opnames/{$opname->id}"
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.id',
            $opname->id
        );

        $response->assertJsonPath(
            'data.status',
            'DRAFT'
        );
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    public function test_can_update_draft_stock_opname(): void
    {
        $location2 = Location::create([
            'code' => 'STK-LOC-2',
            'name' => 'Second Stock Location',
        ]);

        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->patchJson(
                "/api/stock-opnames/{$opname->id}",
                [
                    'opname_date' => '2026-09-22',
                    'location_id' => $location2->id,
                    'notes' => 'Updated from API test',
                ]
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.location_id',
            $location2->id
        );

        $response->assertJsonPath(
            'data.notes',
            'Updated from API test'
        );
    }

    public function test_cannot_update_in_progress_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->patchJson(
                "/api/stock-opnames/{$opname->id}",
                [
                    'notes' => 'Should fail',
                ]
            );

        $response->assertStatus(422);

        $this->assertEquals(
            'Only a DRAFT stock opname can be updated.',
            $response->json('message')
        );
    }

    // -------------------------------------------------------------------------
    // START
    // -------------------------------------------------------------------------

    public function test_can_start_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/start"
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'IN_PROGRESS'
        );

        $this->assertNotNull(
            $response->json('data.started_at')
        );

        $this->assertDatabaseCount(
            'stock_opname_items',
            1
        );

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'book_copy_id' => $this->bookCopy->id,
            'expected_status' => 'AVAILABLE',
            'expected_condition' => 'GOOD',
            'result' => 'NOT_CHECKED',
        ]);
    }

    public function test_cannot_start_stock_opname_twice(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/start"
            );

        $response->assertStatus(422);

        $this->assertEquals(
            'Only a DRAFT stock opname can be started.',
            $response->json('message')
        );
    }

    // -------------------------------------------------------------------------
    // CHECK ITEM
    // -------------------------------------------------------------------------

    public function test_can_check_item_as_found(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'FOUND',
                    'actual_status' => 'AVAILABLE',
                    'actual_condition' => 'GOOD',
                ]
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.item.result',
            'FOUND'
        );

        $response->assertJsonPath(
            'data.item.actual_status',
            'AVAILABLE'
        );

        $response->assertJsonPath(
            'data.item.actual_condition',
            'GOOD'
        );

        $this->assertNotNull(
            $response->json('data.item.checked_at')
        );
    }

    public function test_can_check_item_as_missing(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'MISSING',
                ]
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.item.result',
            'MISSING'
        );
    }

    public function test_can_check_item_as_different(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'DIFFERENT',
                    'actual_status' => 'DAMAGED',
                    'actual_condition' => 'GOOD',
                ]
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.item.result',
            'DIFFERENT'
        );

        $response->assertJsonPath(
            'data.item.actual_status',
            'DAMAGED'
        );
    }

    public function test_invalid_check_result_is_rejected(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'INVALID',
                ]
            );

        $response->assertStatus(422);

        $response->assertJsonValidationErrors([
            'result',
        ]);
    }

    public function test_member_cannot_check_stock_opname_item(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $this
            ->actingAs($this->member, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'FOUND',
                    'actual_status' => 'AVAILABLE',
                    'actual_condition' => 'GOOD',
                ]
            )
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // COMPLETE
    // -------------------------------------------------------------------------

    public function test_cannot_complete_with_unchecked_items(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/complete"
            );

        $response->assertStatus(422);

        $this->assertEquals(
            'All stock opname items must be checked before completion.',
            $response->json('message')
        );
    }

    public function test_can_complete_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'FOUND',
                    'actual_status' => 'AVAILABLE',
                    'actual_condition' => 'GOOD',
                ]
            )
            ->assertStatus(200);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/complete"
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'COMPLETED'
        );

        $this->assertNotNull(
            $response->json('data.completed_at')
        );

        $this->assertDatabaseHas('stock_opnames', [
            'id' => $opname->id,
            'status' => 'COMPLETED',
        ]);
    }

    // -------------------------------------------------------------------------
    // CANCEL
    // -------------------------------------------------------------------------

    public function test_can_cancel_draft_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/cancel"
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'CANCELLED'
        );
    }

    public function test_can_cancel_in_progress_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/cancel"
            );

        $response->assertStatus(200);

        $response->assertJsonPath(
            'data.status',
            'CANCELLED'
        );
    }

    public function test_cannot_cancel_completed_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'FOUND',
                    'actual_status' => 'AVAILABLE',
                    'actual_condition' => 'GOOD',
                ]
            )
            ->assertStatus(200);

        $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/complete"
            )
            ->assertStatus(200);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/cancel"
            );

        $response->assertStatus(422);

        $this->assertEquals(
            'Only a DRAFT or IN_PROGRESS stock opname can be cancelled.',
            $response->json('message')
        );
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    public function test_can_delete_draft_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->deleteJson(
                "/api/stock-opnames/{$opname->id}"
            );

        $response->assertStatus(200);

        $this->assertDatabaseMissing('stock_opnames', [
            'id' => $opname->id,
        ]);
    }

    public function test_cannot_delete_in_progress_stock_opname(): void
    {
        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->deleteJson(
                "/api/stock-opnames/{$opname->id}"
            );

        $response->assertStatus(422);

        $this->assertEquals(
            'Only a DRAFT stock opname can be deleted.',
            $response->json('message')
        );
    }

    // -------------------------------------------------------------------------
    // DATA INTEGRITY
    // -------------------------------------------------------------------------

    public function test_checking_stock_opname_does_not_modify_book_copy(): void
    {
        $originalStatus = $this->bookCopy->status;
        $originalCondition = $this->bookCopy->condition;

        $opname = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname);

        $item = StockOpnameItem::firstOrFail();

        $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname->id}/items/{$item->id}/check",
                [
                    'result' => 'DIFFERENT',
                    'actual_status' => 'DAMAGED',
                    'actual_condition' => 'POOR',
                ]
            )
            ->assertStatus(200);

        $this->assertEquals(
            $originalStatus,
            $this->bookCopy->fresh()->status
        );

        $this->assertEquals(
            $originalCondition,
            $this->bookCopy->fresh()->condition
        );
    }

    public function test_check_item_must_belong_to_stock_opname(): void
    {
        $opname1 = $this->createStockOpname(
            $this->libraryStaff,
            $this->location->id
        );

        $this->startStockOpname($opname1);

        $item1 = StockOpnameItem::firstOrFail();

        $location2 = Location::create([
            'code' => 'STK-LOC-3',
            'name' => 'Third Stock Location',
        ]);

        $book2 = Book::create([
            'title' => 'Second Stock Book',
            'collection_type_id' => $this->book->collection_type_id,
        ]);

        BookCopy::create([
            'book_id' => $book2->id,
            'barcode' => 'STK-BC-002',
            'inventory_number' => 'STK-INV-002',
            'location_id' => $location2->id,
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
        ]);

        $opname2 = $this->createStockOpname(
            $this->libraryStaff,
            $location2->id
        );

        $this->startStockOpname($opname2);

        $item2 = StockOpnameItem::query()
            ->where('stock_opname_id', $opname2->id)
            ->firstOrFail();

        $this->assertNotEquals(
            $item1->stock_opname_id,
            $item2->stock_opname_id
        );

        $response = $this
            ->actingAs($this->libraryStaff, 'sanctum')
            ->postJson(
                "/api/stock-opnames/{$opname1->id}/items/{$item2->id}/check",
                [
                    'result' => 'FOUND',
                    'actual_status' => 'AVAILABLE',
                    'actual_condition' => 'GOOD',
                ]
            );

        $response->assertStatus(404);
    }
}
