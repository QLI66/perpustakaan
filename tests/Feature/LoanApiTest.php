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

class LoanApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser1;

    private User $memberUser2;

    private Member $member1;

    private Member $member2;

    private BookCopy $bookCopy1;

    private BookCopy $bookCopy2;

    private BookCopy $bookCopy3;

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

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);

        $this->memberUser1 = User::create([
            'name' => 'Member User 1',
            'email' => 'member1@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->memberUser1->assignRole('MEMBER');

        $this->member1 = Member::create([
            'user_id' => $this->memberUser1->id,
            'member_number' => 'MEM-001',
            'name' => 'Member One',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $this->memberUser2 = User::create([
            'name' => 'Member User 2',
            'email' => 'member2@test.com',
            'password' => Hash::make('password'),
        ]);
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
            'title' => 'Sample Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);

        $location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $this->bookCopy1 = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-001',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);

        $this->bookCopy2 = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-002',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);

        $this->bookCopy3 = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-003',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);
    }

    // A. AUTHENTICATION
    public function test_guest_cannot_access_loans(): void
    {
        $this->getJson('/api/loans')->assertStatus(401);
        $this->getJson('/api/loans/1')->assertStatus(401);
        $this->postJson('/api/loans', [])->assertStatus(401);
        $this->getJson('/api/me/loans')->assertStatus(401);
    }

    // B. AUTHORIZATION
    public function test_super_admin_can_list_and_create(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/loans')->assertStatus(200);
        $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member1->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ])->assertStatus(201);
    }

    public function test_library_staff_can_list_and_create(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')->getJson('/api/loans')->assertStatus(200);
        $this->actingAs($this->libraryStaff, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member1->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ])->assertStatus(201);
    }

    public function test_member_cannot_list_all_loans(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/loans')->assertStatus(403);
    }

    public function test_member_cannot_view_another_members_loan(): void
    {
        // Staff creates a loan for member 2
        $loanRes = $this->actingAs($this->libraryStaff, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member2->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);
        $loanId = $loanRes->json('data.id');

        // Member 1 tries to view it
        $this->actingAs($this->memberUser1, 'sanctum')->getJson("/api/loans/{$loanId}")->assertStatus(403);
    }

    public function test_member_can_view_own_loan(): void
    {
        $loanRes = $this->actingAs($this->libraryStaff, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member1->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);
        $loanId = $loanRes->json('data.id');

        $this->actingAs($this->memberUser1, 'sanctum')->getJson("/api/loans/{$loanId}")->assertStatus(200);
    }

    public function test_member_cannot_create_loan_for_another_member(): void
    {
        // Member 1 tries to supply member 2's ID. Controller ignores it and uses Member 1's ID.
        $res = $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member2->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $res->assertStatus(201);

        // Ensure the loan was created for member 1, not member 2!
        $this->assertEquals($this->member1->id, $res->json('data.member.id'));
    }

    public function test_member_cannot_control_borrowed_at(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson('/api/loans', [
                'book_copy_ids' => [$this->bookCopy1->id],
                'borrowed_at' => '2035-01-01 12:00:00',
            ]);

        $response->assertStatus(201);

        $loanId = $response->json('data.id');

        $loan = Loan::findOrFail($loanId);

        $this->assertNotEquals(
            '2035-01-01 12:00:00',
            $loan->borrowed_at->toDateTimeString()
        );
    }

    // C. CREATE & STATE INTEGRITY
    public function test_successful_loan_creation_state_integrity(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member1->id,
            'book_copy_ids' => [$this->bookCopy1->id, $this->bookCopy2->id],
        ]);

        $response->assertStatus(201);

        $loanId = $response->json('data.id');
        $this->assertNotNull($loanId);
        $this->assertStringStartsWith('LOAN-', $response->json('data.loan_number'));
        $this->assertEquals('ACTIVE', $response->json('data.status'));
        $this->assertCount(2, $response->json('data.items'));

        // DB State Integrity
        $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('loan_items', ['loan_id' => $loanId, 'book_copy_id' => $this->bookCopy1->id, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('loan_items', ['loan_id' => $loanId, 'book_copy_id' => $this->bookCopy2->id, 'status' => 'ACTIVE']);

        // BookCopies must become BORROWED
        $this->assertDatabaseHas('book_copies', ['id' => $this->bookCopy1->id, 'status' => 'BORROWED']);
        $this->assertDatabaseHas('book_copies', ['id' => $this->bookCopy2->id, 'status' => 'BORROWED']);
    }

    // D. EXISTING BUSINESS RULE REGRESSION
    public function test_maximum_active_loans_rejected(): void
    {
        // First loan with 2 items
        $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy1->id, $this->bookCopy2->id],
        ])->assertStatus(201);

        // Try to borrow a 3rd item
        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy3->id],
        ]);

        $response->assertStatus(422); // BusinessRuleException handled globally
        $this->assertEquals('Member has reached the maximum number of active loans.', $response->json('message'));
    }

    public function test_outstanding_fine_blocks_borrowing(): void
    {
        // Set up a completed loan with a fine
        $loan = Loan::create(['loan_number' => 'L-OLD', 'member_id' => $this->member1->id, 'status' => 'COMPLETED', 'borrowed_at' => now()]);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $this->bookCopy3->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(3), 'status' => 'RETURNED']);
        Fine::create([
            'loan_item_id' => $item->id,
            'amount' => 1000,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $response->assertStatus(422);
        $this->assertEquals('Outstanding fines must be paid before borrowing.', $response->json('message'));
    }

    public function test_open_incident_blocks_borrowing(): void
    {
        $loan = Loan::create(['loan_number' => 'L-OLD2', 'member_id' => $this->member1->id, 'status' => 'ACTIVE', 'borrowed_at' => now()]);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $this->bookCopy3->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(3), 'status' => 'ACTIVE']);
        BookIncident::create([
            'incident_number' => 'INC-001',
            'book_copy_id' => $this->bookCopy3->id,
            'loan_item_id' => $item->id,
            'member_id' => $this->member1->id,
            'type' => 'LOST',
            'status' => 'OPEN',
            'opened_at' => now(),
            'reported_at' => now(),
            'reported_by' => $this->libraryStaff->id,
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $response->assertStatus(422);
        $this->assertEquals('Member has unresolved lost or damaged book obligations.', $response->json('message'));
    }

    public function test_unavailable_copy_rejected(): void
    {
        $this->bookCopy1->update(['status' => 'LOST']);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $response->assertStatus(422);
        $this->assertEquals('Book copy is not available.', $response->json('message'));
    }

    // E. LIST FILTERS & G. DETAIL & H. SELF
    public function test_me_loans_returns_own_loans(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')->postJson('/api/loans', [
            'member_id' => $this->member1->id,
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/loans');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_me_loans_filters_overdue(): void
    {
        $loanRes = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson('/api/loans', [
                'member_id' => $this->member1->id,
                'book_copy_ids' => [$this->bookCopy1->id],
                'borrowed_at' => now()->subDays(10)->toISOString(),
            ]);

        $loanRes->assertStatus(201);
        $responseTrue = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/loans?overdue=true');
        $this->assertCount(1, $responseTrue->json('data.items'));

        $responseFalse = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/loans?overdue=false');
        $this->assertCount(0, $responseFalse->json('data.items'));
    }

    public function test_loan_status_and_overdue_filter_change_immediately_after_due_timestamp(): void
    {
        $dueAt = Carbon::parse('2026-10-13 10:00:00');
        $loan = Loan::create([
            'loan_number' => 'LOAN-TIMESTAMP-BOUNDARY',
            'member_id' => $this->member1->id,
            'borrowed_at' => Carbon::parse('2026-10-10 12:00:00'),
            'status' => 'ACTIVE',
        ]);
        $this->bookCopy1->update(['status' => 'BORROWED']);
        LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $this->bookCopy1->id,
            'borrowed_at' => Carbon::parse('2026-10-10 12:00:00'),
            'due_at' => $dueAt,
            'status' => 'ACTIVE',
        ]);

        try {
            Carbon::setTestNow($dueAt->copy()->subMinute());
            $onDueDate = $this->actingAs($this->memberUser1, 'sanctum')
                ->getJson("/api/loans/{$loan->id}");

            $onDueDate->assertStatus(200)
                ->assertJsonPath('data.items.0.is_overdue', false)
                ->assertJsonPath('data.items.0.overdue_days', 0)
                ->assertJsonPath('data.summary.overdue_items', 0);

            Carbon::setTestNow($dueAt);
            $atDue = $this->actingAs($this->memberUser1, 'sanctum')
                ->getJson("/api/loans/{$loan->id}");

            $atDue->assertStatus(200)
                ->assertJsonPath('data.items.0.is_overdue', false)
                ->assertJsonPath('data.items.0.overdue_days', 0)
                ->assertJsonPath('data.summary.overdue_items', 0);

            Carbon::setTestNow($dueAt->copy()->addMinute());
            $afterDue = $this->actingAs($this->memberUser1, 'sanctum')
                ->getJson("/api/loans/{$loan->id}");

            $afterDue->assertStatus(200)
                ->assertJsonPath('data.items.0.is_overdue', true)
                ->assertJsonPath('data.items.0.overdue_days', 1)
                ->assertJsonPath('data.summary.overdue_items', 1);

            $overdueLoans = $this->actingAs($this->memberUser1, 'sanctum')
                ->getJson('/api/me/loans?overdue=true');
            $notOverdueLoans = $this->actingAs($this->memberUser1, 'sanctum')
                ->getJson('/api/me/loans?overdue=false');

            $this->assertContains($loan->id, array_column($overdueLoans->json('data.items'), 'id'));
            $this->assertNotContains($loan->id, array_column($notOverdueLoans->json('data.items'), 'id'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_loan_item_response_includes_fine_and_payment_status(): void
    {
        $loan = Loan::create([
            'loan_number' => 'LOAN-FINE-DETAIL',
            'member_id' => $this->member1->id,
            'borrowed_at' => Carbon::parse('2026-10-10 12:00:00'),
            'status' => 'COMPLETED',
        ]);
        $this->bookCopy1->update(['status' => 'AVAILABLE']);
        $item = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $this->bookCopy1->id,
            'borrowed_at' => Carbon::parse('2026-10-10 12:00:00'),
            'due_at' => Carbon::parse('2026-10-13 00:00:00'),
            'returned_at' => Carbon::parse('2026-10-14 00:00:00'),
            'status' => 'RETURNED',
        ]);
        $fine = Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => 1,
            'rate_per_day' => config('library.fine_rate_per_day', 1000),
            'amount' => config('library.fine_rate_per_day', 1000),
            'amount_paid' => 500,
            'status' => 'PARTIAL',
            'assessed_at' => Carbon::parse('2026-10-14 00:00:00'),
        ]);

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/loans/{$loan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.items.0.status', 'RETURNED')
            ->assertJsonPath('data.items.0.is_overdue', true)
            ->assertJsonPath('data.items.0.fine.id', $fine->id)
            ->assertJsonPath('data.items.0.fine.late_days', 1)
            ->assertJsonPath('data.items.0.fine.status', 'PARTIAL');
        $this->assertEquals(500, (float) $response->json('data.items.0.fine.amount_paid'));
    }

    // I. RESPONSE SECURITY
    public function test_no_sensitive_data_exposure(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->postJson('/api/loans', [
            'book_copy_ids' => [$this->bookCopy1->id],
        ]);

        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/loans');
        $jsonStr = json_encode($response->json());

        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
    }
}
