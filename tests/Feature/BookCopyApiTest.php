<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookCopyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Book $book;

    private Location $location;

    private BookCopy $bookCopy;

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

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);
        Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'MEM-001',
            'name' => 'Student Member',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);

        $this->book = Book::create([
            'title' => 'Sample Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);

        $this->location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $this->bookCopy = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-001',
            'inventory_number' => 'INV-001',
            'location_id' => $this->location->id,
            'acquisition_date' => '2026-01-01',
            'acquisition_source' => 'PURCHASE',
            'acquisition_price' => 150000.00,
            'condition' => 'GOOD',
            'status' => 'AVAILABLE',
        ]);
    }

    // A. Authentication
    public function test_guest_cannot_list_book_copies(): void
    {
        $this->getJson('/api/book-copies')->assertStatus(401);
    }

    public function test_guest_cannot_view_book_copy(): void
    {
        $this->getJson("/api/book-copies/{$this->bookCopy->id}")->assertStatus(401);
    }

    public function test_guest_cannot_create_book_copy(): void
    {
        $this->postJson('/api/book-copies', [])->assertStatus(401);
    }

    public function test_guest_cannot_update_book_copy(): void
    {
        $this->patchJson("/api/book-copies/{$this->bookCopy->id}", [])->assertStatus(401);
    }

    public function test_guest_cannot_delete_book_copy(): void
    {
        $this->deleteJson("/api/book-copies/{$this->bookCopy->id}")->assertStatus(401);
    }

    // B. Authorization
    public function test_super_admin_can_manage(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies')->assertStatus(200);
    }

    public function test_library_staff_can_manage(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')->getJson('/api/book-copies')->assertStatus(200);
    }

    public function test_member_cannot_list(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')->getJson('/api/book-copies')->assertStatus(403);
    }

    public function test_member_cannot_detail(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')->getJson("/api/book-copies/{$this->bookCopy->id}")->assertStatus(403);
    }

    public function test_member_cannot_create(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-MEMBER',
            'location_id' => $this->location->id,
        ])->assertStatus(403);
    }

    public function test_member_cannot_update(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')->patchJson("/api/book-copies/{$this->bookCopy->id}", [
            'condition' => 'FAIR',
        ])->assertStatus(403);
    }

    public function test_member_cannot_delete(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')->deleteJson("/api/book-copies/{$this->bookCopy->id}")->assertStatus(403);
    }

    // C. LIST
    public function test_successful_listing(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'items' => [
                    '*' => ['id', 'barcode', 'inventory_number', 'book', 'location', 'status'],
                ],
                'pagination',
            ],
        ]);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_pagination_defaults(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies');
        $this->assertEquals(15, $response->json('data.pagination.per_page'));
    }

    public function test_maximum_per_page_rejected(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?per_page=100');
        $response->assertStatus(422); // Rejected by FormRequest max:50
    }

    public function test_sorting(): void
    {
        BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-000',
            'location_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?sort=barcode&direction=asc');
        $items = $response->json('data.items');
        $this->assertEquals('BC-000', $items[0]['barcode']);
        $this->assertEquals('BC-001', $items[1]['barcode']);
    }

    // D. SEARCH
    public function test_search_by_barcode(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?search=BC-001');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_search_by_inventory_number(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?search=INV-001');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_search_by_book_title(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?search=Sample Book');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_search_by_isbn(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?search=1234567890123');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_search_case_insensitive(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?search=sample book');
        $this->assertCount(1, $response->json('data.items'));
    }

    // E. FILTER
    public function test_filter_by_book(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/book-copies?book_id={$this->book->id}");
        $this->assertCount(1, $response->json('data.items'));

        $response2 = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?book_id=9999');
        $response2->assertStatus(422); // Fails exists:books,id validation
    }

    public function test_filter_by_location(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/book-copies?location_id={$this->location->id}");
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_filter_by_status(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?status=AVAILABLE');
        $this->assertCount(1, $response->json('data.items'));

        $response2 = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?status=BORROWED');
        $this->assertCount(0, $response2->json('data.items'));
    }

    public function test_filter_by_condition(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?condition=GOOD');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_filter_by_acquisition_source(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?acquisition_source=PURCHASE');
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_filter_by_available_true(): void
    {
        BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-002',
            'location_id' => $this->location->id,
            'status' => 'BORROWED',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?available=true');
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('BC-001', $response->json('data.items.0.barcode'));
    }

    public function test_filter_by_available_false(): void
    {
        BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-002',
            'location_id' => $this->location->id,
            'status' => 'BORROWED',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-copies?available=false');
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('BC-002', $response->json('data.items.0.barcode'));
    }

    // F. CREATE
    public function test_successful_creation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-003',
            'inventory_number' => 'INV-003',
            'location_id' => $this->location->id,
            'acquisition_date' => '2026-02-01',
            'acquisition_source' => 'DONATION',
            'acquisition_price' => 0,
            'condition' => 'NEW',
        ]);

        $response->assertStatus(201);
        $this->assertEquals('BC-003', $response->json('data.barcode'));
        $this->assertEquals('AVAILABLE', $response->json('data.status')); // Enforced by controller
    }

    public function test_barcode_required(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'location_id' => $this->location->id,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barcode']);
    }

    public function test_barcode_unique(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-001', // Already exists
            'location_id' => $this->location->id,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barcode']);
    }

    public function test_inventory_unique(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-004',
            'inventory_number' => 'INV-001', // Already exists
            'location_id' => $this->location->id,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['inventory_number']);
    }

    public function test_book_foreign_key_validation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => 9999, // Invalid
            'barcode' => 'BC-004',
            'location_id' => $this->location->id,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['book_id']);
    }

    public function test_location_foreign_key_validation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-004',
            'location_id' => 9999, // Invalid
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['location_id']);
    }

    public function test_acquisition_source_validation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-004',
            'location_id' => $this->location->id,
            'acquisition_source' => 'INVALID',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['acquisition_source']);
    }

    public function test_acquisition_price_gte_0(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-004',
            'location_id' => $this->location->id,
            'acquisition_price' => -10,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['acquisition_price']);
    }

    public function test_condition_validation(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-004',
            'location_id' => $this->location->id,
            'condition' => 'INVALID',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['condition']);
    }

    public function test_new_copy_starts_available(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/book-copies', [
            'book_id' => $this->book->id,
            'barcode' => 'BC-005',
            'location_id' => $this->location->id,
        ]);
        $response->assertStatus(201);
        $this->assertEquals('AVAILABLE', $response->json('data.status'));
        $this->assertDatabaseHas('book_copies', [
            'barcode' => 'BC-005',
            'status' => 'AVAILABLE',
        ]);
    }

    // G. UPDATE
    public function test_successful_metadata_update(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->patchJson("/api/book-copies/{$this->bookCopy->id}", [
            'condition' => 'POOR',
            'notes' => 'Page 5 is torn',
        ]);
        $response->assertStatus(200);
        $this->assertEquals('POOR', $response->json('data.condition'));
        $this->assertEquals('Page 5 is torn', $response->json('data.notes'));
    }

    public function test_unique_barcode_validation_ignores_self(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->patchJson("/api/book-copies/{$this->bookCopy->id}", [
            'barcode' => 'BC-001', // Same barcode
        ]);
        $response->assertStatus(200);
    }

    public function test_unique_inventory_validation_ignores_self(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->patchJson("/api/book-copies/{$this->bookCopy->id}", [
            'inventory_number' => 'INV-001', // Same inventory
        ]);
        $response->assertStatus(200);
    }

    public function test_status_cannot_be_arbitrarily_manipulated(): void
    {
        // Try to change status to BORROWED
        $response = $this->actingAs($this->superAdmin, 'sanctum')->patchJson("/api/book-copies/{$this->bookCopy->id}", [
            'status' => 'BORROWED',
        ]);
        $response->assertStatus(200);

        // Status should remain AVAILABLE
        $this->bookCopy->refresh();
        $this->assertEquals('AVAILABLE', $this->bookCopy->status);

        // And the API response should also reflect that it wasn't updated
        $this->assertEquals('AVAILABLE', $response->json('data.status'));
    }

    // H. DELETE
    public function test_copy_without_history_can_be_deleted(): void
    {
        $copyToDelete = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-DEL',
            'location_id' => $this->location->id,
            'status' => 'AVAILABLE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/book-copies/{$copyToDelete->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('book_copies', ['id' => $copyToDelete->id]);
    }

    public function test_copy_with_loan_history_cannot_be_deleted(): void
    {
        $member = Member::first();
        $loan = Loan::create([
            'member_id' => $member->id,
            'loan_number' => 'L-001',
            'borrowed_at' => now(),
            'status' => 'ACTIVE',
        ]);

        LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $this->bookCopy->id,
            'borrowed_at' => now(), // Needed by db schema
            'due_at' => now()->addDays(7),
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/book-copies/{$this->bookCopy->id}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('book_copies', ['id' => $this->bookCopy->id]);
    }

    public function test_copy_with_incident_history_cannot_be_deleted(): void
    {
        $member = Member::first();
        $loan = Loan::create([
            'member_id' => $member->id,
            'loan_number' => 'L-002',
            'borrowed_at' => now(),
            'status' => 'ACTIVE',
        ]);
        $loanItem = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $this->bookCopy->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(7),
            'status' => 'ACTIVE',
        ]);

        BookIncident::create([
            'incident_number' => 'INC-001',
            'book_copy_id' => $this->bookCopy->id,
            'loan_item_id' => $loanItem->id, // Needed by db schema
            'member_id' => $member->id,
            'type' => 'DAMAGED',
            'status' => 'UNRESOLVED',
            'opened_at' => now(), // Needed by db schema
            'reported_at' => now(),
            'reported_by' => $this->libraryStaff->id,
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/book-copies/{$this->bookCopy->id}");
        $response->assertStatus(409);
        $this->assertDatabaseHas('book_copies', ['id' => $this->bookCopy->id]);
    }

    // I. DETAIL & J. SECURITY
    public function test_detail_relationships_correct_and_secure(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/book-copies/{$this->bookCopy->id}");
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($this->book->id, $data['book']['id']);
        $this->assertEquals($this->location->id, $data['location']['id']);

        $jsonStr = json_encode($data);
        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
        $this->assertStringNotContainsString('personal_access_tokens', $jsonStr);
    }
}
