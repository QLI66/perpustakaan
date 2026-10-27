<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Fine;
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

class ReturnApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Member $member;

    private Loan $loan;

    private LoanItem $loanItem;

    private BookCopy $bookCopy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create(['name' => 'Super Admin', 'email' => 'sa@test.com', 'password' => Hash::make('p')]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => Hash::make('p')]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);
        $this->memberUser = User::create(['name' => 'Member', 'email' => 'member@test.com', 'password' => Hash::make('p')]);
        $this->memberUser->assignRole('MEMBER');
        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'MEM-001',
            'name' => 'Member One',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);
        $book = Book::create([
            'title' => 'Test Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);
        $location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $this->bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-001',
            'location_id' => $location->id,
            'status' => 'BORROWED',
        ]);

        $this->loan = Loan::create([
            'loan_number' => 'LOAN-2026-000001',
            'member_id' => $this->member->id,
            'borrowed_at' => now()->subDays(1),
            'status' => 'ACTIVE',
        ]);

        $this->loanItem = LoanItem::create([
            'loan_id' => $this->loan->id,
            'book_copy_id' => $this->bookCopy->id,
            'borrowed_at' => now()->subDays(1),
            'due_at' => now()->addDays(2),
            'status' => 'ACTIVE',
        ]);
    }

    private function returnUrl(?Loan $loan = null, ?LoanItem $loanItem = null): string
    {
        $loan = $loan ?? $this->loan;
        $loanItem = $loanItem ?? $this->loanItem;

        return "/api/loans/{$loan->id}/items/{$loanItem->id}/return";
    }

    // A. AUTHENTICATION
    public function test_guest_gets_401(): void
    {
        $this->postJson($this->returnUrl())->assertStatus(401);
    }

    // B. AUTHORIZATION
    public function test_member_cannot_return(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->postJson($this->returnUrl())
            ->assertStatus(403);
    }

    public function test_super_admin_can_return(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());
        $response->assertStatus(200);
    }

    public function test_library_staff_can_return(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson($this->returnUrl());
        $response->assertStatus(200);
    }

    // C. SUCCESS / STATE TRANSITIONS
    public function test_successful_return_state_transitions(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());

        $response->assertStatus(200);
        $response->assertJsonPath('data.loan_item.status', 'RETURNED');
        $response->assertJsonPath('data.loan_item.book_copy.status', 'AVAILABLE');
        $this->assertNotNull($response->json('data.loan_item.returned_at'));

        // DB state
        $this->assertDatabaseHas('loan_items', ['id' => $this->loanItem->id, 'status' => 'RETURNED']);
        $this->assertDatabaseHas('book_copies', ['id' => $this->bookCopy->id, 'status' => 'AVAILABLE']);
    }

    public function test_loan_completed_when_all_items_returned(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());

        $response->assertStatus(200);
        $response->assertJsonPath('data.loan_status', 'COMPLETED');
        $this->assertDatabaseHas('loans', ['id' => $this->loan->id, 'status' => 'COMPLETED']);
    }

    public function test_loan_remains_active_when_another_item_active(): void
    {
        // Create a second active copy + loan item
        $collectionType = CollectionType::where('code', 'FIC')->first();
        $book2 = Book::create(['title' => 'B2', 'isbn' => '9999999999999', 'collection_type_id' => $collectionType->id, 'publication_year' => 2024, 'language' => 'en']);
        $location2 = Location::where('code', 'A1')->first();
        $copy2 = BookCopy::create(['book_id' => $book2->id, 'barcode' => 'BC-002', 'location_id' => $location2->id, 'status' => 'BORROWED']);
        LoanItem::create(['loan_id' => $this->loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(2), 'status' => 'ACTIVE']);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());

        $response->assertStatus(200);
        $response->assertJsonPath('data.loan_status', 'ACTIVE');
        $this->assertDatabaseHas('loans', ['id' => $this->loan->id, 'status' => 'ACTIVE']);
    }

    // D. DATE / LATE BEHAVIOR
    public function test_on_time_return_no_fine(): void
    {
        // due_at is in 2 days, returned today → no fine
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'returned_at' => now()->toISOString(),
            ]);
        $response->assertStatus(200);
        $response->assertJsonPath('data.fine', null);
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $this->loanItem->id]);
    }

    public function test_due_date_end_is_on_time_and_api_reports_returned_without_fine(): void
    {
        $this->loanItem->update(['due_at' => '2026-10-13 23:59:59']);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'returned_at' => '2026-10-13T23:59:59Z',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan_item.status', 'RETURNED')
            ->assertJsonPath('data.loan_status', 'COMPLETED')
            ->assertJsonPath('data.loan_item.is_overdue', false)
            ->assertJsonPath('data.loan_item.overdue_days', 0)
            ->assertJsonPath('data.fine', null);
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $this->loanItem->id]);
    }

    public function test_return_at_next_calendar_midnight_reports_one_day_fine(): void
    {
        $this->loanItem->update(['due_at' => '2026-10-13 23:59:59']);
        $rate = config('library.fine_rate_per_day', 1000);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'returned_at' => '2026-10-14T00:00:00Z',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.loan_item.status', 'RETURNED')
            ->assertJsonPath('data.loan_status', 'COMPLETED')
            ->assertJsonPath('data.loan_item.is_overdue', true)
            ->assertJsonPath('data.loan_item.overdue_days', 1)
            ->assertJsonPath('data.fine.late_days', 1)
            ->assertJsonPath('data.fine.status', 'UNPAID');
        $this->assertEquals($rate, (float) $response->json('data.fine.amount'));
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $this->loanItem->id,
            'late_days' => 1,
            'amount' => $rate,
        ]);
    }

    public function test_one_day_late_return_creates_fine(): void
    {
        // Set due_at to yesterday; under Option B with midnight transition, returning today is 2 late days
        $this->loanItem->update(['due_at' => now()->subDay()]);
        $rate = config('library.fine_rate_per_day', 1000);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'returned_at' => now()->toISOString(),
            ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.fine'));
        $this->assertEquals(2, $response->json('data.fine.late_days'));
        $this->assertEquals($rate, $response->json('data.fine.rate_per_day'));
        $this->assertEquals(2 * $rate, $response->json('data.fine.amount'));
        $this->assertDatabaseHas('fines', ['loan_item_id' => $this->loanItem->id, 'late_days' => 2]);
    }

    public function test_multiple_days_late(): void
    {
        // Set due_at to 3 days ago; under Option B with 3 midnight transitions, returning today is 4 late days
        $this->loanItem->update(['due_at' => now()->subDays(3)]);
        $rate = config('library.fine_rate_per_day', 1000);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'returned_at' => now()->toISOString(),
            ]);

        $response->assertStatus(200);
        $this->assertEquals(4, $response->json('data.fine.late_days'));
        $this->assertEquals(4 * $rate, $response->json('data.fine.amount'));
    }

    // E. FINE INTEGRATION
    public function test_no_duplicate_fine_for_same_loan_item(): void
    {
        // Pre-create an existing fine to simulate the guard in ReturnService
        Fine::create([
            'loan_item_id' => $this->loanItem->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        // Make the item look overdue
        $this->loanItem->update(['due_at' => now()->subDay()]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());

        // ReturnService throws BusinessRuleException if fine already exists
        $response->assertStatus(422);
    }

    // F. INVALID STATES
    public function test_already_returned_loan_item_rejected(): void
    {
        $this->loanItem->update(['status' => 'RETURNED', 'returned_at' => now()]);
        $this->bookCopy->update(['status' => 'AVAILABLE']);

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl())
            ->assertStatus(422);
    }

    public function test_loan_item_not_belonging_to_loan_returns_404(): void
    {
        // Create a separate loan + item
        $otherLoan = Loan::create([
            'loan_number' => 'LOAN-2026-000002',
            'member_id' => $this->member->id,
            'borrowed_at' => now(),
            'status' => 'ACTIVE',
        ]);

        // Try to return this->loanItem under the OTHER loan
        $url = "/api/loans/{$otherLoan->id}/items/{$this->loanItem->id}/return";
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($url)
            ->assertStatus(404);
    }

    public function test_unknown_loan_returns_404(): void
    {
        $url = "/api/loans/9999/items/{$this->loanItem->id}/return";
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($url)
            ->assertStatus(404);
    }

    public function test_unknown_loan_item_returns_404(): void
    {
        $url = "/api/loans/{$this->loan->id}/items/9999/return";
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($url)
            ->assertStatus(404);
    }

    // G. REQUEST VALIDATION
    public function test_invalid_returned_at_format_rejected(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), ['returned_at' => 'not-a-date'])
            ->assertStatus(422);
    }

    // H. SECURITY - unauthorized fields must not affect outcome
    public function test_client_cannot_manipulate_business_fields(): void
    {
        $originalDue = $this->loanItem->due_at;

        // Supply an early due_at to make return appear on time even if it's late
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl(), [
                'due_at' => now()->subDays(10)->toISOString(), // Should be ignored
                'late_days' => 0,                                  // Should be ignored
                'amount' => 0,                                  // Should be ignored
                'rate_per_day' => 0,                                  // Should be ignored
                'book_copy_id' => 999,                                // Should be ignored
                'member_id' => 999,                                // Should be ignored
            ]);

        // The return should succeed since due_at is actually in the future (set in setUp)
        $response->assertStatus(200);

        // due_at in DB must remain unchanged
        $this->loanItem->refresh();
        $this->assertEquals($originalDue->toDateTimeString(), $this->loanItem->due_at->toDateTimeString());
    }

    // I. RESPONSE SECURITY
    public function test_no_sensitive_data_in_response(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson($this->returnUrl());

        $response->assertStatus(200);
        $jsonStr = json_encode($response->json());
        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
        $this->assertStringNotContainsString('personal_access_tokens', $jsonStr);
    }
}
