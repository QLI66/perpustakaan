<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
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

class LoanReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    private Member $member;

    private Member $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Library Staff',
            'email' => 'staff-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $memberType = MemberType::create([
            'code' => 'STD-REPORT',
            'name' => 'Student',
        ]);

        $this->memberUser = User::create([
            'name' => 'Member Report',
            'email' => 'member-report@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->memberUser->assignRole('MEMBER');

        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'MEM-REPORT-001',
            'name' => 'Member Report One',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $memberUser2 = User::create([
            'name' => 'Member Report Two',
            'email' => 'member-report-2@test.com',
            'password' => Hash::make('password'),
        ]);
        $memberUser2->assignRole('MEMBER');

        $this->member2 = Member::create([
            'user_id' => $memberUser2->id,
            'member_number' => 'MEM-REPORT-002',
            'name' => 'Member Report Two',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        Carbon::setTestNow('2026-09-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private function makeBookCopy(
        string $barcode,
        ?string $title = null
    ): BookCopy {
        static $counter = 0;
        $counter++;

        $collectionType = CollectionType::create([
            'code' => 'CR-'.$counter.'-'.uniqid(),
            'name' => 'Report Collection '.$counter,
        ]);

        $location = Location::create([
            'code' => 'LR-'.$counter.'-'.uniqid(),
            'name' => 'Report Location '.$counter,
        ]);

        $book = Book::create([
            'title' => $title ?? 'Report Book '.$counter,
            'isbn' => '978000000'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2026,
            'language' => 'id',
        ]);

        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'inventory_number' => 'INV-'.$counter,
            'location_id' => $location->id,
            'status' => 'BORROWED',
        ]);
    }

    private function makeLoan(
        Member $member,
        string $loanNumber,
        string $borrowedAt,
        string $status = 'ACTIVE'
    ): Loan {
        return Loan::create([
            'loan_number' => $loanNumber,
            'member_id' => $member->id,
            'borrowed_at' => $borrowedAt,
            'status' => $status,
        ]);
    }

    private function makeLoanItem(
        Loan $loan,
        BookCopy $copy,
        string $borrowedAt,
        string $dueAt,
        string $status = 'ACTIVE',
        ?string $returnedAt = null
    ): LoanItem {
        return LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'returned_at' => $returnedAt,
            'status' => $status,
            'renewal_count' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // AUTHENTICATION & AUTHORIZATION
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_loan_report(): void
    {
        $this->getJson('/api/reports/loans')
            ->assertStatus(401);
    }

    public function test_guest_cannot_access_return_report(): void
    {
        $this->getJson('/api/reports/returns')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_loan_report(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/reports/loans')
            ->assertStatus(403);
    }

    public function test_member_cannot_access_return_report(): void
    {
        $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/reports/returns')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_loan_report(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/loans')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_return_report(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/reports/returns')
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // LOAN REPORT
    // -------------------------------------------------------------------------

    public function test_loan_report_returns_expected_structure(): void
    {
        $copy = $this->makeBookCopy('RPT-LOAN-001');

        $loan = $this->makeLoan(
            $this->member,
            'RPT-LOAN-001',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/loans');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_loans',
                        'active_loans',
                        'completed_loans',
                        'total_loan_items',
                        'overdue_loan_items',
                    ],
                    'items',
                ],
            ]);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_loans')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.active_loans')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.total_loan_items')
        );
    }

    public function test_loan_report_counts_overdue_items(): void
    {
        $copy = $this->makeBookCopy('RPT-OVERDUE-001');

        $loan = $this->makeLoan(
            $this->member,
            'RPT-LOAN-OVERDUE',
            '2026-09-15 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-15 09:00:00',
            '2026-09-20 00:00:00',
            'ACTIVE'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/loans');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.overdue_loan_items')
        );
    }

    public function test_loan_report_can_filter_by_status(): void
    {
        $copy1 = $this->makeBookCopy('RPT-STATUS-001');
        $copy2 = $this->makeBookCopy('RPT-STATUS-002');

        $activeLoan = $this->makeLoan(
            $this->member,
            'RPT-STATUS-ACTIVE',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $activeLoan,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00'
        );

        $completedLoan = $this->makeLoan(
            $this->member,
            'RPT-STATUS-COMPLETED',
            '2026-09-18 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $completedLoan,
            $copy2,
            '2026-09-18 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/loans?status=COMPLETED');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_loans')
        );

        $this->assertEquals(
            0,
            $response->json('data.summary.active_loans')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.completed_loans')
        );
    }

    public function test_loan_report_can_filter_by_member(): void
    {
        $copy1 = $this->makeBookCopy('RPT-MEMBER-001');
        $copy2 = $this->makeBookCopy('RPT-MEMBER-002');

        $loan1 = $this->makeLoan(
            $this->member,
            'RPT-MEMBER-LOAN-001',
            '2026-09-20 09:00:00'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00'
        );

        $loan2 = $this->makeLoan(
            $this->member2,
            'RPT-MEMBER-LOAN-002',
            '2026-09-21 09:00:00'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-21 09:00:00',
            '2026-09-25 00:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/loans?member_id='.$this->member->id
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_loans')
        );
    }

    public function test_loan_report_can_filter_by_date_range(): void
    {
        $copy1 = $this->makeBookCopy('RPT-DATE-001');
        $copy2 = $this->makeBookCopy('RPT-DATE-002');

        $loan1 = $this->makeLoan(
            $this->member,
            'RPT-DATE-OLD',
            '2026-09-05 09:00:00'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-05 09:00:00',
            '2026-09-10 00:00:00'
        );

        $loan2 = $this->makeLoan(
            $this->member,
            'RPT-DATE-NEW',
            '2026-09-20 09:00:00'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/loans'
                .'?date_from=2026-09-19'
                .'&date_to=2026-09-21'
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_loans')
        );
    }

    // -------------------------------------------------------------------------
    // RETURN REPORT
    // -------------------------------------------------------------------------

    public function test_return_report_returns_expected_structure(): void
    {
        $copy = $this->makeBookCopy('RPT-RETURN-001');

        $loan = $this->makeLoan(
            $this->member,
            'RPT-RETURN-001',
            '2026-09-15 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-15 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/returns');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_returned_items',
                        'on_time_returned_items',
                        'late_returned_items',
                    ],
                    'items',
                ],
            ]);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_returned_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.on_time_returned_items')
        );

        $this->assertEquals(
            0,
            $response->json('data.summary.late_returned_items')
        );
    }

    public function test_return_report_counts_late_returns(): void
    {
        $copy1 = $this->makeBookCopy('RPT-LATE-001');
        $copy2 = $this->makeBookCopy('RPT-LATE-002');

        $loan = $this->makeLoan(
            $this->member,
            'RPT-LATE-LOAN',
            '2026-09-15 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy1,
            '2026-09-15 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $this->makeLoanItem(
            $loan,
            $copy2,
            '2026-09-15 09:00:00',
            '2026-09-20 00:00:00',
            'RETURNED',
            '2026-09-21 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/returns');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('data.summary.total_returned_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.on_time_returned_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.late_returned_items')
        );
    }

    public function test_return_report_can_filter_by_return_date(): void
    {
        $copy1 = $this->makeBookCopy('RPT-RETURN-DATE-001');
        $copy2 = $this->makeBookCopy('RPT-RETURN-DATE-002');

        $loan = $this->makeLoan(
            $this->member,
            'RPT-RETURN-DATE-LOAN',
            '2026-09-10 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy1,
            '2026-09-10 09:00:00',
            '2026-09-18 00:00:00',
            'RETURNED',
            '2026-09-15 10:00:00'
        );

        $this->makeLoanItem(
            $loan,
            $copy2,
            '2026-09-15 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/returns'
                .'?date_from=2026-09-19'
                .'&date_to=2026-09-21'
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_returned_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.on_time_returned_items')
        );

        $this->assertEquals(
            0,
            $response->json('data.summary.late_returned_items')
        );
    }

    public function test_return_report_can_filter_by_member(): void
    {
        $copy1 = $this->makeBookCopy('RPT-RETURN-MEMBER-001');
        $copy2 = $this->makeBookCopy('RPT-RETURN-MEMBER-002');

        $loan1 = $this->makeLoan(
            $this->member,
            'RPT-RETURN-MEMBER-LOAN-001',
            '2026-09-15 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-15 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $loan2 = $this->makeLoan(
            $this->member2,
            'RPT-RETURN-MEMBER-LOAN-002',
            '2026-09-16 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-16 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/returns?member_id='.$this->member->id
            );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_returned_items')
        );
    }

    // -------------------------------------------------------------------------
    // VALIDATION
    // -------------------------------------------------------------------------

    public function test_loan_report_rejects_invalid_date_range(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/loans'
                .'?date_from=2026-09-21'
                .'&date_to=2026-09-10'
            );

        $response->assertStatus(422);
    }

    public function test_return_report_rejects_invalid_date_range(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson(
                '/api/reports/returns'
                .'?date_from=2026-09-21'
                .'&date_to=2026-09-10'
            );

        $response->assertStatus(422);
    }

    public function test_loan_report_rejects_unknown_member(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/loans?member_id=999999');

        $response->assertStatus(422);
    }

    public function test_return_report_rejects_unknown_member(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/returns?member_id=999999');

        $response->assertStatus(422);
    }
}
