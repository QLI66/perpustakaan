<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create(['name' => 'Super Admin', 'email' => 'sa@test.com', 'password' => 'p']);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => 'p']);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->memberUser = User::create(['name' => 'Member1', 'email' => 'member1@test.com', 'password' => 'p']);
        $this->memberUser->assignRole('MEMBER');

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);

        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_type_id' => $memberType->id,
            'member_number' => 'M001',
            'name' => 'Member1',
            'status' => 'ACTIVE',
            'joined_at' => now(),
        ]);
    }

    public function test_member_cannot_access_finance_report()
    {
        $response = $this->actingAs($this->memberUser)->getJson('/api/finance/summary');
        $response->assertStatus(403);
    }

    public function test_staff_can_view_finance_report()
    {
        $response = $this->actingAs($this->libraryStaff)->getJson('/api/finance/summary');
        $response->assertStatus(200);
    }

    public function test_finance_totals_calculated_correctly()
    {
        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);
        $book = Book::create([
            'title' => 'Test Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);
        $location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $copy1 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-001', 'location_id' => $location->id, 'status' => 'AVAILABLE']);
        $copy2 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-002', 'location_id' => $location->id, 'status' => 'AVAILABLE']);
        $copy3 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-003', 'location_id' => $location->id, 'status' => 'AVAILABLE']);

        $loan = Loan::create(['member_id' => $this->member->id, 'loan_number' => 'LOAN-1', 'borrowed_at' => now(), 'status' => 'ACTIVE']);
        $loanItem1 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy1->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(2), 'status' => 'ACTIVE']);
        $loanItem2 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(2), 'status' => 'ACTIVE']);
        $loanItem3 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy3->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(2), 'status' => 'ACTIVE']);

        // 1. Fine Paid
        $fine = Fine::create([
            'loan_item_id' => $loanItem1->id,
            'late_days' => 5,
            'rate_per_day' => 1000,
            'amount' => 5000,
            'amount_paid' => 5000,
            'status' => 'PAID',
            'assessed_at' => now(),
            'paid_at' => now(),
        ]);
        FinePayment::create([
            'fine_id' => $fine->id,
            'amount' => 5000,
            'paid_at' => now(),
            'payment_method' => 'CASH',
        ]);

        // 2. Incident Paid (loanItem2)
        BookIncident::create([
            'loan_item_id' => $loanItem2->id,
            'book_copy_id' => $copy2->id,
            'type' => 'LOST',
            'status' => 'RESOLVED',
            'resolution_type' => 'PAID_MONEY',
            'amount_due' => 150000,
            'amount_paid' => 150000,
            'opened_at' => now(),
            'resolved_at' => now(),
        ]);

        // 3. Unpaid incident (loanItem3) - should not be included in total received
        BookIncident::create([
            'loan_item_id' => $loanItem3->id,
            'book_copy_id' => $copy3->id,
            'type' => 'DAMAGED',
            'status' => 'OPEN',
            'amount_due' => 50000,
            'amount_paid' => 0,
            'opened_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/finance/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.fines.paid', 5000)
            ->assertJsonPath('data.incidents.paid', 150000)
            ->assertJsonPath('data.total_received', 155000)
            ->assertJsonPath('data.incidents.outstanding', 50000);
    }

    public function test_finance_transactions_are_returned()
    {
        $collectionType = CollectionType::create(['code' => 'FIC2', 'name' => 'Fiction2']);
        $book = Book::create([
            'title' => 'Test Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);
        $location = Location::create(['code' => 'A2', 'name' => 'Shelf A2']);
        $copy1 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-003', 'location_id' => $location->id, 'status' => 'AVAILABLE']);

        $loan = Loan::create(['member_id' => $this->member->id, 'loan_number' => 'LOAN-2', 'borrowed_at' => now(), 'status' => 'ACTIVE']);
        $loanItem1 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy1->id, 'borrowed_at' => now(), 'due_at' => now()->addDays(2), 'status' => 'ACTIVE']);

        $fine = Fine::create([
            'loan_item_id' => $loanItem1->id,
            'late_days' => 5,
            'rate_per_day' => 1000,
            'amount' => 5000,
            'amount_paid' => 5000,
            'status' => 'PAID',
            'assessed_at' => now(),
            'paid_at' => now(),
        ]);
        FinePayment::create([
            'fine_id' => $fine->id,
            'amount' => 5000,
            'paid_at' => now(),
            'payment_method' => 'CASH',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/finance/transactions');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('DENDA', $response->json('data.items.0.type'));
        $this->assertEquals(5000, $response->json('data.items.0.amount'));
    }
}
