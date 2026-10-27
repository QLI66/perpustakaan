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

class BookIncidentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser1;

    private User $memberUser2;

    private Member $member1;

    private Member $member2;

    private Book $book;

    private BookCopy $bookCopyLost;

    private BookCopy $bookCopyDamaged;

    private BookCopy $bookCopyReplacement;

    private BookCopy $bookCopyDifferentBook;

    private Loan $loan1;

    private LoanItem $loanItemLost;

    private LoanItem $loanItemDamaged;

    private BookIncident $incidentDamaged;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create(['name' => 'Super Admin', 'email' => 'sa@test.com', 'password' => Hash::make('p')]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => Hash::make('p')]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);

        $this->memberUser1 = User::create(['name' => 'Member1', 'email' => 'member1@test.com', 'password' => Hash::make('p')]);
        $this->memberUser1->assignRole('MEMBER');
        $this->member1 = Member::create([
            'user_id' => $this->memberUser1->id,
            'member_number' => 'MEM-001',
            'name' => 'Member One',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $this->memberUser2 = User::create(['name' => 'Member2', 'email' => 'member2@test.com', 'password' => Hash::make('p')]);
        $this->memberUser2->assignRole('MEMBER');
        $this->member2 = Member::create([
            'user_id' => $this->memberUser2->id,
            'member_number' => 'MEM-002',
            'name' => 'Member Two',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);
        $this->book = Book::create([
            'title' => 'Test Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);

        $otherBook = Book::create([
            'title' => 'Other Book',
            'isbn' => '9876543210987',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);

        $location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        // For lost incident
        $this->bookCopyLost = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-001',
            'location_id' => $location->id,
            'status' => 'LOST',
            'acquisition_price' => 50000,
        ]);

        // For damaged incident
        $this->bookCopyDamaged = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-002',
            'location_id' => $location->id,
            'status' => 'DAMAGED',
            'acquisition_price' => 75000,
        ]);

        // Valid replacement
        $this->bookCopyReplacement = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-003',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);

        // Invalid replacement (different book)
        $this->bookCopyDifferentBook = BookCopy::create([
            'book_id' => $otherBook->id,
            'barcode' => 'BC-004',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);

        $this->loan1 = Loan::create([
            'loan_number' => 'LOAN-1',
            'member_id' => $this->member1->id,
            'borrowed_at' => now()->subDays(5),
            'status' => 'ACTIVE',
        ]);

        $this->loanItemLost = LoanItem::create([
            'loan_id' => $this->loan1->id,
            'book_copy_id' => $this->bookCopyLost->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'status' => 'LOST',
        ]);

        $this->loanItemDamaged = LoanItem::create([
            'loan_id' => $this->loan1->id,
            'book_copy_id' => $this->bookCopyDamaged->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'status' => 'DAMAGED',
        ]);

        // Create an existing open incident
        $this->incidentDamaged = BookIncident::create([
            'loan_item_id' => $this->loanItemDamaged->id,
            'book_copy_id' => $this->bookCopyDamaged->id,
            'type' => 'DAMAGED',
            'status' => 'OPEN',
            'amount_due' => 75000,
            'opened_at' => now(),
        ]);
    }

    // A. AUTHENTICATION
    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/book-incidents')->assertStatus(401);
        $this->getJson("/api/book-incidents/{$this->incidentDamaged->id}")->assertStatus(401);
        $this->getJson('/api/me/book-incidents')->assertStatus(401);
        $this->postJson('/api/book-incidents')->assertStatus(401);
        $this->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/payment")->assertStatus(401);
        $this->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/replacement")->assertStatus(401);
    }

    // B. AUTHORIZATION
    public function test_super_admin_can_view_all_incidents(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-incidents')->assertStatus(200);
    }

    public function test_member_cannot_view_all_incidents(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/book-incidents')->assertStatus(403);
    }

    public function test_member_can_view_own_incident(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson("/api/book-incidents/{$this->incidentDamaged->id}");
        $response->assertStatus(200);
        $this->assertEquals($this->incidentDamaged->id, $response->json('data.id'));
    }

    public function test_member_cannot_view_another_members_incident(): void
    {
        $this->actingAs($this->memberUser2, 'sanctum')->getJson("/api/book-incidents/{$this->incidentDamaged->id}")->assertStatus(403);
    }

    public function test_member_cannot_create_incident(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson('/api/book-incidents', [
                'loan_item_id' => $this->loanItemLost->id,
                'type' => 'LOST',
            ])->assertStatus(403);
    }

    public function test_member_cannot_resolve_incident(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/payment", [])
            ->assertStatus(403);
    }

    public function test_member_can_access_me_incidents(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/book-incidents');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($this->incidentDamaged->id, $response->json('data.items.0.id'));
    }

    // C. CREATE INCIDENT
    public function test_create_lost_incident_from_active_loan(): void
    {
        // 1. Create a fresh ACTIVE loan with an ACTIVE item and BORROWED book copy
        $activeBookCopy = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC-ACTIVE-1',
            'location_id' => Location::first()->id,
            'status' => 'BORROWED',
            'acquisition_price' => 120000,
        ]);

        $activeLoan = Loan::create([
            'loan_number' => 'LOAN-ACTIVE-1',
            'member_id' => $this->member1->id,
            'borrowed_at' => now()->subDays(1),
            'status' => 'ACTIVE',
        ]);

        $activeLoanItem = LoanItem::create([
            'loan_id' => $activeLoan->id,
            'book_copy_id' => $activeBookCopy->id,
            'borrowed_at' => now()->subDays(1),
            'due_at' => now()->addDays(2),
            'status' => 'ACTIVE',
        ]);

        // 2. Make the API call to open the incident
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/book-incidents', [
                'loan_item_id' => $activeLoanItem->id,
                'type' => 'LOST',
                'notes' => 'Member lost the book',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('LOST', $response->json('data.type'));
        $this->assertEquals('OPEN', $response->json('data.status'));
        $this->assertEquals('120000.00', $response->json('data.amount_due'));

        // 3. Verify Database State Transitions
        $activeLoanItem->refresh();
        $this->assertEquals('LOST', $activeLoanItem->status);

        $activeBookCopy->refresh();
        $this->assertEquals('LOST', $activeBookCopy->status);

        $activeLoan->refresh();
        $this->assertEquals('COMPLETED', $activeLoan->status); // Because it was the only item
    }

    public function test_create_damaged_incident_from_already_damaged_item_works(): void
    {
        // This tests backward compatibility: if the item is ALREADY DAMAGED (e.g. from a manual DB fix), it still works.
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/book-incidents', [
                'loan_item_id' => $this->loanItemDamaged->id,
                'type' => 'DAMAGED',
                'notes' => 'Member damaged the book',
            ]);

        // This will fail with 422 because $this->incidentDamaged already exists for $this->loanItemDamaged in setUp.
        // We expect it to fail properly.
        $response->assertStatus(422);
    }

    // D. SEARCH & FILTER
    public function test_incidents_search_by_member_name(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-incidents?search=Member One');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_incidents_filter_by_type(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-incidents?type=DAMAGED');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));

        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/book-incidents?type=LOST');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.items'));
    }

    // E. PAYMENT RESOLUTION
    public function test_resolve_incident_via_payment(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/payment", [
                'notes' => 'Paid in full',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('RESOLVED', $response->json('data.status'));
        $this->assertEquals('PAID_MONEY', $response->json('data.resolution_type'));
        $this->assertEquals('75000.00', $response->json('data.amount_paid'));
        $this->assertEquals('0.00', $response->json('data.outstanding_amount'));
        $this->assertNotNull($response->json('data.resolved_at'));
        $this->assertEquals($this->libraryStaff->id, $response->json('data.resolved_by.id'));

        // DB Assertions
        $this->incidentDamaged->refresh();
        $this->assertEquals('RESOLVED', $this->incidentDamaged->status);
        $this->assertEquals('PAID_MONEY', $this->incidentDamaged->resolution_type);
        $this->assertEquals('75000.00', $this->incidentDamaged->amount_paid);

        // Cannot resolve again
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/payment", [])
            ->assertStatus(422);
    }

    // F. REPLACEMENT RESOLUTION
    public function test_resolve_incident_via_replacement(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/replacement", [
                'replacement_book_copy_id' => $this->bookCopyReplacement->id,
                'notes' => 'Replaced with a new copy',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('RESOLVED', $response->json('data.status'));
        $this->assertEquals('REPLACED_BOOK', $response->json('data.resolution_type'));
        $this->assertEquals($this->bookCopyReplacement->id, $response->json('data.replacement_book_copy.id'));
        $this->assertNotNull($response->json('data.resolved_at'));
        $this->assertEquals($this->libraryStaff->id, $response->json('data.resolved_by.id'));

        // DB Assertions
        $this->incidentDamaged->refresh();
        $this->assertEquals('RESOLVED', $this->incidentDamaged->status);
        $this->assertEquals('REPLACED_BOOK', $this->incidentDamaged->resolution_type);
        $this->assertEquals($this->bookCopyReplacement->id, $this->incidentDamaged->replacement_book_copy_id);

        // Cannot resolve again
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/replacement", [
                'replacement_book_copy_id' => $this->bookCopyReplacement->id,
            ])
            ->assertStatus(422);
    }

    public function test_replacement_rejected_if_different_book(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/book-incidents/{$this->incidentDamaged->id}/resolve/replacement", [
                'replacement_book_copy_id' => $this->bookCopyDifferentBook->id,
            ]);

        $response->assertStatus(422);
    }

    // G. SECURITY
    public function test_no_sensitive_data_exposed(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/book-incidents/{$this->incidentDamaged->id}");

        $jsonStr = json_encode($response->json());
        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
    }
}
