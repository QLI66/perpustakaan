<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Services\LoanReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new LoanReportService;

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

    private function makeMember(
        string $number,
        string $name
    ): Member {
        $memberType = MemberType::create([
            'code' => 'MT-'.$number,
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => $name,
            'member_type_id' => $memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeBookCopy(
        string $barcode,
        string $inventoryNumber
    ): BookCopy {
        $location = Location::create([
            'code' => 'LOC-'.$barcode,
            'name' => 'Rak '.$barcode,
        ]);

        $collectionType = CollectionType::create([
            'code' => 'COL-'.$barcode,
            'name' => 'Buku',
        ]);

        $book = Book::create([
            'title' => 'Buku '.$barcode,
            'collection_type_id' => $collectionType->id,
        ]);

        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'inventory_number' => $inventoryNumber,
            'status' => 'BORROWED',
            'location_id' => $location->id,
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
    // LOAN REPORT
    // -------------------------------------------------------------------------

    public function test_empty_loan_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getLoanReport();

        $this->assertEquals(0, $result['summary']['total_loans']);
        $this->assertEquals(0, $result['summary']['active_loans']);
        $this->assertEquals(0, $result['summary']['completed_loans']);
        $this->assertEquals(0, $result['summary']['total_loan_items']);
        $this->assertEquals(0, $result['summary']['overdue_loan_items']);
        $this->assertCount(0, $result['items']);
    }

    public function test_loan_report_counts_loans_and_items_correctly(): void
    {
        $member = $this->makeMember('MEM-001', 'Member One');

        $copy1 = $this->makeBookCopy('BC-001', 'INV-001');
        $copy2 = $this->makeBookCopy('BC-002', 'INV-002');

        $loan1 = $this->makeLoan(
            $member,
            'LOAN-001',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-21 00:00:00',
            'ACTIVE'
        );

        $loan2 = $this->makeLoan(
            $member,
            'LOAN-002',
            '2026-09-18 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-18 09:00:00',
            '2026-09-21 00:00:00',
            'RETURNED',
            '2026-09-20 10:00:00'
        );

        $result = $this->reportService->getLoanReport();

        $this->assertEquals(2, $result['summary']['total_loans']);
        $this->assertEquals(1, $result['summary']['active_loans']);
        $this->assertEquals(1, $result['summary']['completed_loans']);
        $this->assertEquals(2, $result['summary']['total_loan_items']);
        $this->assertEquals(1, $result['summary']['overdue_loan_items']);
        $this->assertCount(2, $result['items']);
    }

    public function test_completed_loans_are_not_counted_as_active(): void
    {
        $member = $this->makeMember('MEM-002', 'Member Two');

        $copy = $this->makeBookCopy('BC-003', 'INV-003');

        $loan = $this->makeLoan(
            $member,
            'LOAN-003',
            '2026-09-10 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-10 09:00:00',
            '2026-09-13 00:00:00',
            'RETURNED',
            '2026-09-13 10:00:00'
        );

        $result = $this->reportService->getLoanReport();

        $this->assertEquals(1, $result['summary']['total_loans']);
        $this->assertEquals(0, $result['summary']['active_loans']);
        $this->assertEquals(1, $result['summary']['completed_loans']);
    }

    public function test_overdue_loan_items_are_counted_only_when_active_and_past_due(): void
    {
        $member = $this->makeMember('MEM-003', 'Member Three');

        $copy1 = $this->makeBookCopy('BC-004', 'INV-004');
        $copy2 = $this->makeBookCopy('BC-005', 'INV-005');
        $copy3 = $this->makeBookCopy('BC-006', 'INV-006');

        $loan = $this->makeLoan(
            $member,
            'LOAN-004',
            '2026-09-15 09:00:00',
            'ACTIVE'
        );

        // Overdue and active -> counted.
        $this->makeLoanItem(
            $loan,
            $copy1,
            '2026-09-15 09:00:00',
            '2026-09-20 00:00:00',
            'ACTIVE'
        );

        // Not overdue yet -> not counted.
        $this->makeLoanItem(
            $loan,
            $copy2,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00',
            'ACTIVE'
        );

        // Returned -> not counted even if due date has passed.
        $this->makeLoanItem(
            $loan,
            $copy3,
            '2026-09-10 09:00:00',
            '2026-09-15 00:00:00',
            'RETURNED',
            '2026-09-16 10:00:00'
        );

        $result = $this->reportService->getLoanReport();

        $this->assertEquals(1, $result['summary']['overdue_loan_items']);
    }

    public function test_loan_report_can_filter_by_date_range(): void
    {
        $member = $this->makeMember('MEM-004', 'Member Four');

        $copy1 = $this->makeBookCopy('BC-007', 'INV-007');
        $copy2 = $this->makeBookCopy('BC-008', 'INV-008');

        $loan1 = $this->makeLoan(
            $member,
            'LOAN-005',
            '2026-09-05 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-05 09:00:00',
            '2026-09-08 00:00:00',
            'RETURNED',
            '2026-09-08 10:00:00'
        );

        $loan2 = $this->makeLoan(
            $member,
            'LOAN-006',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-20 09:00:00',
            '2026-09-23 00:00:00',
            'ACTIVE'
        );

        $result = $this->reportService->getLoanReport(
            Carbon::parse('2026-09-19'),
            Carbon::parse('2026-09-21')
        );

        $this->assertEquals(1, $result['summary']['total_loans']);
        $this->assertEquals(1, $result['summary']['active_loans']);
        $this->assertCount(1, $result['items']);
    }

    public function test_loan_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-005', 'Member Five');
        $member2 = $this->makeMember('MEM-006', 'Member Six');

        $copy1 = $this->makeBookCopy('BC-009', 'INV-009');
        $copy2 = $this->makeBookCopy('BC-010', 'INV-010');

        $loan1 = $this->makeLoan(
            $member1,
            'LOAN-007',
            '2026-09-20 09:00:00'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-23 00:00:00'
        );

        $loan2 = $this->makeLoan(
            $member2,
            'LOAN-008',
            '2026-09-21 09:00:00'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-21 09:00:00',
            '2026-09-24 00:00:00'
        );

        $result = $this->reportService->getLoanReport(
            null,
            null,
            $member1->id
        );

        $this->assertEquals(1, $result['summary']['total_loans']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(
            $member1->id,
            $result['items'][0]->member_id
        );
    }

    public function test_loan_report_can_filter_by_status(): void
    {
        $member = $this->makeMember('MEM-007', 'Member Seven');

        $copy1 = $this->makeBookCopy('BC-011', 'INV-011');
        $copy2 = $this->makeBookCopy('BC-012', 'INV-012');

        $loan1 = $this->makeLoan(
            $member,
            'LOAN-009',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-23 00:00:00'
        );

        $loan2 = $this->makeLoan(
            $member,
            'LOAN-010',
            '2026-09-19 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-19 09:00:00',
            '2026-09-22 00:00:00',
            'RETURNED',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getLoanReport(
            null,
            null,
            null,
            'COMPLETED'
        );

        $this->assertEquals(1, $result['summary']['total_loans']);
        $this->assertEquals(0, $result['summary']['active_loans']);
        $this->assertEquals(1, $result['summary']['completed_loans']);
        $this->assertCount(1, $result['items']);
    }

    // -------------------------------------------------------------------------
    // RETURN REPORT
    // -------------------------------------------------------------------------

    public function test_empty_return_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getReturnReport();

        $this->assertEquals(0, $result['summary']['total_returned_items']);
        $this->assertEquals(0, $result['summary']['on_time_returned_items']);
        $this->assertEquals(0, $result['summary']['late_returned_items']);
        $this->assertCount(0, $result['items']);
    }

    public function test_return_report_counts_returned_items_correctly(): void
    {
        $member = $this->makeMember('MEM-008', 'Member Eight');

        $copy1 = $this->makeBookCopy('BC-013', 'INV-013');
        $copy2 = $this->makeBookCopy('BC-014', 'INV-014');
        $copy3 = $this->makeBookCopy('BC-015', 'INV-015');

        $loan = $this->makeLoan(
            $member,
            'LOAN-011',
            '2026-09-15 09:00:00',
            'COMPLETED'
        );

        // On time.
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

        // Still active, therefore not returned.
        $this->makeLoanItem(
            $loan,
            $copy3,
            '2026-09-15 09:00:00',
            '2026-09-25 00:00:00',
            'ACTIVE'
        );

        $result = $this->reportService->getReturnReport();

        $this->assertEquals(2, $result['summary']['total_returned_items']);
        $this->assertEquals(1, $result['summary']['on_time_returned_items']);
        $this->assertEquals(1, $result['summary']['late_returned_items']);
        $this->assertCount(2, $result['items']);
    }

    public function test_return_report_can_filter_by_return_date(): void
    {
        $member = $this->makeMember('MEM-009', 'Member Nine');

        $copy1 = $this->makeBookCopy('BC-016', 'INV-016');
        $copy2 = $this->makeBookCopy('BC-017', 'INV-017');

        $loan = $this->makeLoan(
            $member,
            'LOAN-012',
            '2026-09-10 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy1,
            '2026-09-10 09:00:00',
            '2026-09-13 00:00:00',
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

        $result = $this->reportService->getReturnReport(
            Carbon::parse('2026-09-19'),
            Carbon::parse('2026-09-21')
        );

        $this->assertEquals(1, $result['summary']['total_returned_items']);
        $this->assertEquals(1, $result['summary']['on_time_returned_items']);
        $this->assertEquals(0, $result['summary']['late_returned_items']);
        $this->assertCount(1, $result['items']);
    }

    public function test_return_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-010', 'Member Ten');
        $member2 = $this->makeMember('MEM-011', 'Member Eleven');

        $copy1 = $this->makeBookCopy('BC-018', 'INV-018');
        $copy2 = $this->makeBookCopy('BC-019', 'INV-019');

        $loan1 = $this->makeLoan(
            $member1,
            'LOAN-013',
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
            $member2,
            'LOAN-014',
            '2026-09-16 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-16 09:00:00',
            '2026-09-20 00:00:00',
            'RETURNED',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getReturnReport(
            null,
            null,
            $member1->id
        );

        $this->assertEquals(1, $result['summary']['total_returned_items']);
        $this->assertEquals(1, $result['summary']['on_time_returned_items']);
        $this->assertEquals(0, $result['summary']['late_returned_items']);
        $this->assertCount(1, $result['items']);
    }

    public function test_return_report_ignores_items_without_returned_at(): void
    {
        $member = $this->makeMember('MEM-012', 'Member Twelve');

        $copy = $this->makeBookCopy('BC-020', 'INV-020');

        $loan = $this->makeLoan(
            $member,
            'LOAN-015',
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-20 09:00:00',
            '2026-09-23 00:00:00',
            'ACTIVE',
            null
        );

        $result = $this->reportService->getReturnReport();

        $this->assertEquals(0, $result['summary']['total_returned_items']);
        $this->assertCount(0, $result['items']);
    }
}
