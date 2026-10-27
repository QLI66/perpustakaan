<?php

namespace Tests\Unit;

use App\Models\Acquisition;
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
use App\Models\StockOpname;
use App\Services\LibraryDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private LibraryDashboardService $service;

    private CollectionType $collectionType;

    private MemberType $memberType;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LibraryDashboardService;

        $this->collectionType = CollectionType::create([
            'code' => 'DASH-COL',
            'name' => 'Dashboard Collection',
        ]);

        $this->memberType = MemberType::create([
            'code' => 'DASH-MEM',
            'name' => 'Dashboard Member',
        ]);

        $this->location = Location::create([
            'code' => 'DASH-LOC',
            'name' => 'Dashboard Location',
        ]);
    }

    private function makeBook(
        string $title = 'Dashboard Book'
    ): Book {
        return Book::create([
            'title' => $title,
            'collection_type_id' => $this->collectionType->id,
        ]);
    }

    private function makeCopy(
        ?Book $book = null,
        string $barcode = 'DASH-BC-001',
        string $status = 'AVAILABLE',
        string $condition = 'GOOD'
    ): BookCopy {
        $book ??= $this->makeBook();

        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'inventory_number' => 'INV-'.$barcode,
            'location_id' => $this->location->id,
            'status' => $status,
            'condition' => $condition,
        ]);
    }

    private function makeMember(): Member
    {
        return Member::create([
            'member_number' => 'DASH-'.uniqid(),
            'name' => 'Dashboard Member',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeLoan(
        Member $member,
        string $borrowedAt,
        string $status = 'ACTIVE'
    ): Loan {
        return Loan::create([
            'loan_number' => 'DASH-LOAN-'.uniqid(),
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
            'status' => $status,
            'returned_at' => $returnedAt,
            'renewal_count' => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // EMPTY DATABASE
    // -------------------------------------------------------------------------

    public function test_empty_dashboard_returns_zero_totals(): void
    {
        $now = Carbon::parse('2026-09-21 10:00:00');

        $summary = $this->service->getSummary($now);

        $this->assertEquals(0, $summary['totals']['books']);
        $this->assertEquals(0, $summary['totals']['book_copies']);
        $this->assertEquals(0, $summary['totals']['members']);

        $this->assertEquals(0, $summary['loans']['active']);
        $this->assertEquals(0, $summary['loans']['overdue_items']);
        $this->assertEquals(0, $summary['loans']['overdue_members']);

        $this->assertEquals(0, $summary['fines']['unpaid_count']);
        $this->assertEquals(0, $summary['fines']['partial_count']);
        $this->assertEquals(0, $summary['fines']['outstanding_count']);

        $this->assertEquals(0, $summary['incidents']['open']);

        $this->assertEquals(0, $summary['acquisitions']['total']);

        $this->assertEquals(0, $summary['stock_opnames']['total']);
        $this->assertNull($summary['stock_opnames']['latest']);
    }

    // -------------------------------------------------------------------------
    // TOTALS
    // -------------------------------------------------------------------------

    public function test_total_counts_are_calculated_correctly(): void
    {
        $book1 = $this->makeBook('Book One');
        $book2 = $this->makeBook('Book Two');

        $this->makeCopy(
            $book1,
            'DASH-001',
            'AVAILABLE'
        );

        $this->makeCopy(
            $book1,
            'DASH-002',
            'BORROWED'
        );

        $this->makeCopy(
            $book2,
            'DASH-003',
            'LOST'
        );

        $this->makeCopy(
            $book2,
            'DASH-004',
            'DAMAGED'
        );

        $this->makeMember();

        $inactiveMember = Member::create([
            'member_number' => 'DASH-INACTIVE',
            'name' => 'Inactive Member',
            'member_type_id' => $this->memberType->id,
            'joined_at' => '2026-09-01',
            'status' => 'INACTIVE',
        ]);

        $this->assertNotNull($inactiveMember);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            2,
            $summary['totals']['books']
        );

        $this->assertEquals(
            4,
            $summary['totals']['book_copies']
        );

        $this->assertEquals(
            2,
            $summary['totals']['members']
        );

        $this->assertEquals(
            1,
            $summary['totals']['active_members']
        );

        $this->assertEquals(
            1,
            $summary['totals']['available_book_copies']
        );

        $this->assertEquals(
            1,
            $summary['totals']['borrowed_book_copies']
        );

        $this->assertEquals(
            1,
            $summary['totals']['lost_book_copies']
        );

        $this->assertEquals(
            1,
            $summary['totals']['damaged_book_copies']
        );
    }

    // -------------------------------------------------------------------------
    // LOANS
    // -------------------------------------------------------------------------

    public function test_active_and_overdue_loan_summary_is_correct(): void
    {
        $member1 = $this->makeMember();

        $copy1 = $this->makeCopy(
            null,
            'DASH-LOAN-001',
            'BORROWED'
        );

        $copy2 = $this->makeCopy(
            null,
            'DASH-LOAN-002',
            'BORROWED'
        );

        $loan = $this->makeLoan(
            $member1,
            '2026-09-20 09:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan,
            $copy1,
            '2026-09-20 09:00:00',
            '2026-09-20 00:00:00',
            'ACTIVE'
        );

        $this->makeLoanItem(
            $loan,
            $copy2,
            '2026-09-20 09:00:00',
            '2026-09-25 00:00:00',
            'ACTIVE'
        );

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            1,
            $summary['loans']['active']
        );

        $this->assertEquals(
            1,
            $summary['loans']['overdue_items']
        );

        $this->assertEquals(
            1,
            $summary['loans']['overdue_members']
        );
    }

    public function test_completed_loans_are_not_counted_as_active(): void
    {
        $member = $this->makeMember();

        $copy = $this->makeCopy(
            null,
            'DASH-COMP-001',
            'AVAILABLE'
        );

        $loan = $this->makeLoan(
            $member,
            '2026-09-10 09:00:00',
            'COMPLETED'
        );

        $this->makeLoanItem(
            $loan,
            $copy,
            '2026-09-10 09:00:00',
            '2026-09-13 00:00:00',
            'RETURNED',
            '2026-09-12 10:00:00'
        );

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            0,
            $summary['loans']['active']
        );

        $this->assertEquals(
            0,
            $summary['loans']['overdue_items']
        );
    }

    // -------------------------------------------------------------------------
    // FINES
    // -------------------------------------------------------------------------

    public function test_fine_summary_calculates_outstanding_amount(): void
    {
        $member = $this->makeMember();

        $copy1 = $this->makeCopy(
            null,
            'DASH-FINE-001',
            'AVAILABLE'
        );

        $copy2 = $this->makeCopy(
            null,
            'DASH-FINE-002',
            'AVAILABLE'
        );

        $loan1 = $this->makeLoan(
            $member,
            '2026-09-10 09:00:00',
            'COMPLETED'
        );

        $item1 = $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-10 09:00:00',
            '2026-09-11 00:00:00',
            'RETURNED',
            '2026-09-12 09:00:00'
        );

        $loan2 = $this->makeLoan(
            $member,
            '2026-09-13 09:00:00',
            'COMPLETED'
        );

        $item2 = $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-13 09:00:00',
            '2026-09-14 00:00:00',
            'RETURNED',
            '2026-09-16 09:00:00'
        );

        Fine::create([
            'loan_item_id' => $item1->id,
            'late_days' => 3,
            'rate_per_day' => 1000,
            'amount' => 3000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => '2026-09-12 09:00:00',
        ]);

        Fine::create([
            'loan_item_id' => $item2->id,
            'late_days' => 2,
            'rate_per_day' => 1000,
            'amount' => 2000,
            'amount_paid' => 500,
            'status' => 'PARTIAL',
            'assessed_at' => '2026-09-16 09:00:00',
        ]);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            1,
            $summary['fines']['unpaid_count']
        );

        $this->assertEquals(
            1,
            $summary['fines']['partial_count']
        );

        $this->assertEquals(
            2,
            $summary['fines']['outstanding_count']
        );

        $this->assertEquals(
            '4500.00',
            $summary['fines']['outstanding_amount']
        );

        $this->assertEquals(
            '5000.00',
            $summary['fines']['total_assessed_amount']
        );

        $this->assertEquals(
            '500.00',
            $summary['fines']['total_paid_amount']
        );
    }

    // -------------------------------------------------------------------------
    // INCIDENTS
    // -------------------------------------------------------------------------

    public function test_only_open_incidents_are_counted(): void
    {
        $member = $this->makeMember();

        $copy1 = $this->makeCopy(
            null,
            'DASH-INC-001',
            'LOST'
        );

        $copy2 = $this->makeCopy(
            null,
            'DASH-INC-002',
            'DAMAGED'
        );

        $loan1 = $this->makeLoan(
            $member,
            '2026-09-10 09:00:00',
            'ACTIVE'
        );

        $item1 = $this->makeLoanItem(
            $loan1,
            $copy1,
            '2026-09-10 09:00:00',
            '2026-09-13 00:00:00',
            'LOST'
        );

        $loan2 = $this->makeLoan(
            $member,
            '2026-09-11 09:00:00',
            'ACTIVE'
        );

        $item2 = $this->makeLoanItem(
            $loan2,
            $copy2,
            '2026-09-11 09:00:00',
            '2026-09-14 00:00:00',
            'DAMAGED'
        );

        BookIncident::create([
            'loan_item_id' => $item1->id,
            'book_copy_id' => $copy1->id,
            'type' => 'LOST',
            'status' => 'OPEN',
            'opened_at' => '2026-09-15 10:00:00',
        ]);

        BookIncident::create([
            'loan_item_id' => $item2->id,
            'book_copy_id' => $copy2->id,
            'type' => 'DAMAGED',
            'status' => 'RESOLVED',
            'resolution_type' => 'FOUND',
            'opened_at' => '2026-09-16 10:00:00',
            'resolved_at' => '2026-09-17 10:00:00',
        ]);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            1,
            $summary['incidents']['open']
        );
    }

    // -------------------------------------------------------------------------
    // ACQUISITIONS
    // -------------------------------------------------------------------------

    public function test_acquisition_summary_calculates_month_and_year(): void
    {
        Acquisition::create([
            'acquisition_number' => 'ACQ-2026-000001',
            'acquisition_date' => '2026-09-05',
            'source_type' => 'PURCHASE',
            'total_amount' => 100000,
        ]);

        Acquisition::create([
            'acquisition_number' => 'ACQ-2026-000002',
            'acquisition_date' => '2026-09-20',
            'source_type' => 'DONATION',
            'total_amount' => 50000,
        ]);

        Acquisition::create([
            'acquisition_number' => 'ACQ-2025-000001',
            'acquisition_date' => '2025-12-20',
            'source_type' => 'GRANT',
            'total_amount' => 75000,
        ]);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            3,
            $summary['acquisitions']['total']
        );

        $this->assertEquals(
            2,
            $summary['acquisitions']['this_month']
        );

        $this->assertEquals(
            2,
            $summary['acquisitions']['this_year']
        );

        $this->assertEquals(
            '150000.00',
            $summary['acquisitions']['this_year_amount']
        );
    }

    // -------------------------------------------------------------------------
    // STOCK OPNAMES
    // -------------------------------------------------------------------------

    public function test_stock_opname_summary_and_latest_are_correct(): void
    {
        $first = StockOpname::create([
            'opname_number' => 'OPN-2026-000001',
            'opname_date' => '2026-09-01',
            'location_id' => $this->location->id,
            'status' => 'COMPLETED',
        ]);

        StockOpname::create([
            'opname_number' => 'OPN-2026-000002',
            'opname_date' => '2026-09-20',
            'location_id' => $this->location->id,
            'status' => 'IN_PROGRESS',
        ]);

        StockOpname::create([
            'opname_number' => 'OPN-2026-000003',
            'opname_date' => '2026-09-21',
            'location_id' => $this->location->id,
            'status' => 'DRAFT',
        ]);

        StockOpname::create([
            'opname_number' => 'OPN-2026-000004',
            'opname_date' => '2026-09-21',
            'location_id' => $this->location->id,
            'status' => 'CANCELLED',
        ]);

        $this->assertNotNull($first);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 10:00:00')
        );

        $this->assertEquals(
            4,
            $summary['stock_opnames']['total']
        );

        $this->assertEquals(
            1,
            $summary['stock_opnames']['draft']
        );

        $this->assertEquals(
            1,
            $summary['stock_opnames']['in_progress']
        );

        $this->assertEquals(
            1,
            $summary['stock_opnames']['completed']
        );

        $this->assertEquals(
            1,
            $summary['stock_opnames']['cancelled']
        );

        $this->assertEquals(
            'OPN-2026-000004',
            $summary['stock_opnames']['latest']['opname_number']
        );
    }

    // -------------------------------------------------------------------------
    // PERIOD SUMMARY
    // -------------------------------------------------------------------------

    public function test_period_summary_counts_transactions_in_correct_period(): void
    {
        $member = $this->makeMember();

        $copy1 = $this->makeCopy(
            null,
            'DASH-PERIOD-001',
            'AVAILABLE'
        );

        $copy2 = $this->makeCopy(
            null,
            'DASH-PERIOD-002',
            'AVAILABLE'
        );

        $loanToday = $this->makeLoan(
            $member,
            '2026-09-21 08:00:00',
            'COMPLETED'
        );

        $itemToday = $this->makeLoanItem(
            $loanToday,
            $copy1,
            '2026-09-21 08:00:00',
            '2026-09-21 12:00:00',
            'RETURNED',
            '2026-09-21 10:00:00'
        );

        $loanThisMonth = $this->makeLoan(
            $member,
            '2026-09-10 08:00:00',
            'COMPLETED'
        );

        $itemThisMonth = $this->makeLoanItem(
            $loanThisMonth,
            $copy2,
            '2026-09-10 08:00:00',
            '2026-09-13 00:00:00',
            'RETURNED',
            '2026-09-14 10:00:00'
        );

        Fine::create([
            'loan_item_id' => $itemToday->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => '2026-09-21 10:30:00',
        ]);

        Fine::create([
            'loan_item_id' => $itemThisMonth->id,
            'late_days' => 2,
            'rate_per_day' => 1000,
            'amount' => 2000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => '2026-09-02 10:30:00',
        ]);

        Acquisition::create([
            'acquisition_number' => 'ACQ-PERIOD-001',
            'acquisition_date' => '2026-09-21',
            'source_type' => 'PURCHASE',
            'total_amount' => 125000,
        ]);

        Acquisition::create([
            'acquisition_number' => 'ACQ-PERIOD-002',
            'acquisition_date' => '2026-09-10',
            'source_type' => 'DONATION',
            'total_amount' => 50000,
        ]);

        StockOpname::create([
            'opname_number' => 'OPN-PERIOD-001',
            'opname_date' => '2026-09-21',
            'status' => 'COMPLETED',
        ]);

        $summary = $this->service->getSummary(
            Carbon::parse('2026-09-21 11:00:00')
        );

        $this->assertEquals(
            1,
            $summary['periods']['today']['loans']
        );

        $this->assertEquals(
            1,
            $summary['periods']['today']['returned_items']
        );

        $this->assertEquals(
            1,
            $summary['periods']['today']['fines_assessed']
        );

        $this->assertEquals(
            '1000.00',
            $summary['periods']['today']['fines_assessed_amount']
        );

        $this->assertEquals(
            1,
            $summary['periods']['today']['acquisitions']
        );

        $this->assertEquals(
            '125000.00',
            $summary['periods']['today']['acquisition_amount']
        );

        $this->assertEquals(
            1,
            $summary['periods']['today']['stock_opnames']
        );

        $this->assertEquals(
            2,
            $summary['periods']['this_month']['loans']
        );

        $this->assertEquals(
            2,
            $summary['periods']['this_month']['returned_items']
        );

        $this->assertEquals(
            2,
            $summary['periods']['this_month']['fines_assessed']
        );

        $this->assertEquals(
            '3000.00',
            $summary['periods']['this_month']['fines_assessed_amount']
        );

        $this->assertEquals(
            2,
            $summary['periods']['this_month']['acquisitions']
        );

        $this->assertEquals(
            '175000.00',
            $summary['periods']['this_month']['acquisition_amount']
        );

        $this->assertEquals(
            1,
            $summary['periods']['this_month']['stock_opnames']
        );
    }
}
