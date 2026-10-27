<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RenewalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser1;

    private User $memberUser2;

    private Member $member1;

    private Member $member2;

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
            'member_id' => $this->member1->id,
            'borrowed_at' => now()->subDays(2),
            'status' => 'ACTIVE',
        ]);

        $this->loanItem = LoanItem::create([
            'loan_id' => $this->loan->id,
            'book_copy_id' => $this->bookCopy->id,
            'borrowed_at' => now()->subDays(2),
            'due_at' => now()->addDays(1),
            'renewal_count' => 0,
            'status' => 'ACTIVE',
        ]);
    }

    private function renewUrl(?Loan $loan = null, ?LoanItem $loanItem = null): string
    {
        $loan = $loan ?? $this->loan;
        $loanItem = $loanItem ?? $this->loanItem;

        return "/api/loans/{$loan->id}/items/{$loanItem->id}/renew";
    }

    // A. AUTHENTICATION
    public function test_guest_cannot_renew(): void
    {
        $this->postJson($this->renewUrl())->assertStatus(401);
    }

    // B. AUTHORIZATION
    public function test_super_admin_can_renew(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')->postJson($this->renewUrl())->assertStatus(200);
    }

    public function test_library_staff_can_renew(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')->postJson($this->renewUrl())->assertStatus(200);
    }

    public function test_member_can_renew_own_item(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl())->assertStatus(200);
    }

    public function test_member_cannot_renew_another_members_item(): void
    {
        $this->actingAs($this->memberUser2, 'sanctum')->postJson($this->renewUrl())->assertStatus(403);
    }

    // C. SUCCESS & STATE TRANSITIONS
    public function test_successful_renewal_state_transitions(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());

        $response->assertStatus(200);

        $this->assertEquals(1, $response->json('data.loan_item.renewal_count'));
        $this->assertEquals('ACTIVE', $response->json('data.loan_item.status'));
        $this->assertEquals('ACTIVE', $response->json('data.loan.status'));

        $this->assertDatabaseHas('loan_items', [
            'id' => $this->loanItem->id,
            'renewal_count' => 1,
            'status' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('book_copies', [
            'id' => $this->bookCopy->id,
            'status' => 'BORROWED', // remains BORROWED
        ]);
    }

    // D. DATE BEHAVIOR
    public function test_new_due_date_is_based_on_renewed_at(): void
    {
        $renewedAtStr = '2026-09-19T10:00:00+07:00';

        // Existing due_at was setup to be something else.
        $response = $this->actingAs($this->libraryStaff, 'sanctum')->postJson($this->renewUrl(), [
            'renewed_at' => $renewedAtStr,
        ]);

        $response->assertStatus(200);

        // renewed_at startOfDay + 3 days
        $expectedDueAt = '2026-09-22T00:00:00.000000Z'; // Config renewal_days is 3

        $this->assertEquals($expectedDueAt, $response->json('data.loan_item.due_at'));
    }

    public function test_member_cannot_control_renewed_at(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson($this->renewUrl(), [
                'renewed_at' => '2035-01-01 12:00:00',
            ]);

        $response->assertStatus(200);

        $actualDueAt = Carbon::parse(
            $response->json('data.loan_item.due_at')
        );

        $this->assertNotEquals(
            '2035-01-04 00:00:00',
            $actualDueAt->toDateTimeString()
        );
    }

    // E. FINE RESTRICTIONS
    public function test_unpaid_fine_blocks_renewal(): void
    {
        Fine::create([
            'loan_item_id' => $this->loanItem->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(422);
        $this->assertEquals('All outstanding fines must be fully paid before renewal.', $response->json('message'));
    }

    public function test_partial_fine_blocks_renewal(): void
    {
        Fine::create([
            'loan_item_id' => $this->loanItem->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 500,
            'status' => 'PARTIAL',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(422);
    }

    public function test_paid_fine_allows_renewal(): void
    {
        Fine::create([
            'loan_item_id' => $this->loanItem->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 1000,
            'status' => 'PAID',
            'assessed_at' => now(),
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(200);
    }

    // F. INCIDENT RESTRICTIONS
    public function test_open_incident_blocks_renewal(): void
    {
        BookIncident::create([
            'incident_number' => 'INC-1',
            'book_copy_id' => $this->bookCopy->id,
            'loan_item_id' => $this->loanItem->id,
            'member_id' => $this->member1->id,
            'type' => 'LOST',
            'status' => 'OPEN',
            'opened_at' => now(),
            'reported_at' => now(),
            'reported_by' => $this->libraryStaff->id,
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(422);
        $this->assertEquals('Member has unresolved lost or damaged book obligations.', $response->json('message'));
    }

    public function test_resolved_incident_allows_renewal(): void
    {
        BookIncident::create([
            'incident_number' => 'INC-1',
            'book_copy_id' => $this->bookCopy->id,
            'loan_item_id' => $this->loanItem->id,
            'member_id' => $this->member1->id,
            'type' => 'LOST',
            'status' => 'RESOLVED',
            'resolution_type' => 'FOUND',
            'opened_at' => now(),
            'reported_at' => now(),
            'reported_by' => $this->libraryStaff->id,
            'resolved_at' => now(),
            'resolved_by' => $this->libraryStaff->id,
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(200);
    }

    // G. REPEAT RENEWAL
    public function test_second_renewal_blocked(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl())->assertStatus(200);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(422);
        $this->assertEquals('Maximum renewal limit has been reached.', $response->json('message'));
    }

    // H. INVALID STATE
    public function test_non_active_item_blocked(): void
    {
        $this->loanItem->update(['status' => 'RETURNED']);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());
        $response->assertStatus(422);
    }

    public function test_unknown_loan_returns_404(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson("/api/loans/999/items/{$this->loanItem->id}/renew")
            ->assertStatus(404);
    }

    public function test_mismatched_loan_and_item_returns_404(): void
    {
        $otherLoan = Loan::create([
            'loan_number' => 'LOAN-2026-000002',
            'member_id' => $this->member1->id,
            'borrowed_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson($this->renewUrl($otherLoan))
            ->assertStatus(404);
    }

    // I. REQUEST SECURITY
    public function test_client_cannot_control_business_fields(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl(), [
            'due_at' => now()->addDays(100)->toISOString(), // Should be ignored
            'renewal_count' => 0,                           // Should be ignored
            'status' => 'COMPLETED',                        // Should be ignored
        ]);

        $response->assertStatus(200);

        // due_at should be correctly +3 days, not +100 days
        $expectedDueAt = now()->startOfDay()->addDays(3)->toISOString();
        $this->assertEquals($expectedDueAt, $response->json('data.loan_item.due_at'));

        // count is 1, not 0
        $this->assertEquals(1, $response->json('data.loan_item.renewal_count'));

        // status is ACTIVE, not COMPLETED
        $this->assertEquals('ACTIVE', $response->json('data.loan_item.status'));
    }

    // J. RESPONSE SECURITY
    public function test_no_sensitive_data_in_response(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson($this->renewUrl());

        $jsonStr = json_encode($response->json());
        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
    }
}
