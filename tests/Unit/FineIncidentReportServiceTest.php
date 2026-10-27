<?php

namespace Tests\Unit;

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
use App\Services\FineIncidentReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineIncidentReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private FineIncidentReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new FineIncidentReportService;

        Carbon::setTestNow('2026-09-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeMember(string $number = 'MEM-001'): Member
    {
        $type = MemberType::create([
            'code' => 'STD-'.uniqid(),
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => 'Test Member',
            'member_type_id' => $type->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeCopy(string $barcode): BookCopy
    {
        $location = Location::first();

        if (! $location) {
            $location = Location::create([
                'code' => 'LOC-'.uniqid(),
                'name' => 'Main Library',
            ]);
        }

        $collectionType = CollectionType::first();

        if (! $collectionType) {
            $collectionType = CollectionType::create([
                'code' => 'COL-'.uniqid(),
                'name' => 'General',
            ]);
        }

        $book = Book::create([
            'title' => 'Test Book '.uniqid(),
            'collection_type_id' => $collectionType->id,
        ]);

        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'inventory_number' => 'INV-'.uniqid(),
            'status' => 'RETURNED',
            'location_id' => $location->id,
        ]);
    }

    private function makeLoanItem(Member $member, string $suffix): LoanItem
    {
        $copy = $this->makeCopy('BC-'.$suffix);

        $loan = Loan::create([
            'loan_number' => 'LOAN-'.$suffix,
            'member_id' => $member->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'status' => 'COMPLETED',
        ]);

        return LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'due_at' => '2026-09-13 00:00:00',
            'returned_at' => '2026-09-15 10:00:00',
            'status' => 'RETURNED',
        ]);
    }

    private function makeFine(
        Member $member,
        string $suffix,
        int $amount,
        int $amountPaid,
        string $status,
        string $assessedAt
    ): Fine {
        $item = $this->makeLoanItem($member, $suffix);

        return Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => max(0, intdiv($amount, 1000)),
            'rate_per_day' => 1000,
            'amount' => $amount,
            'amount_paid' => $amountPaid,
            'status' => $status,
            'assessed_at' => $assessedAt,
            'paid_at' => $status === 'PAID' ? $assessedAt : null,
        ]);
    }

    private function makeIncident(
        Member $member,
        string $suffix,
        string $type,
        string $status,
        int $amountDue,
        int $amountPaid,
        string $openedAt
    ): BookIncident {
        $copy = $this->makeCopy('INC-BC-'.$suffix);

        $loan = Loan::create([
            'loan_number' => 'INC-LOAN-'.$suffix,
            'member_id' => $member->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'status' => 'COMPLETED',
        ]);

        $item = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => '2026-09-10 09:00:00',
            'due_at' => '2026-09-13 00:00:00',
            'returned_at' => '2026-09-15 10:00:00',
            'status' => $type === 'LOST' ? 'LOST' : 'DAMAGED',
        ]);

        return BookIncident::create([
            'incident_number' => 'INC-'.$suffix,
            'book_copy_id' => $copy->id,
            'loan_item_id' => $item->id,
            'member_id' => $member->id,
            'type' => $type,
            'status' => $status,
            'amount_due' => $amountDue,
            'amount_paid' => $amountPaid,
            'opened_at' => $openedAt,
            'reported_at' => $openedAt,
            'reported_by' => null,
            'resolution_type' => $status === 'RESOLVED' ? 'PAID_MONEY' : null,
            'resolved_at' => $status === 'RESOLVED' ? $openedAt : null,
            'resolved_by' => null,
        ]);
    }

    public function test_empty_fine_report_returns_zero_totals(): void
    {
        $result = $this->reportService->getFineReport();

        $this->assertSame(0, $result['summary']['total_fines']);
        $this->assertSame(0, $result['summary']['unpaid_count']);
        $this->assertSame(0, $result['summary']['partial_count']);
        $this->assertSame(0, $result['summary']['paid_count']);
        $this->assertSame(0.0, $result['summary']['total_amount']);
        $this->assertSame(0.0, $result['summary']['total_amount_paid']);
        $this->assertSame(0.0, $result['summary']['total_outstanding']);
    }

    public function test_fine_report_counts_statuses_and_amounts(): void
    {
        $member = $this->makeMember();

        $this->makeFine($member, 'F001', 1000, 0, 'UNPAID', '2026-09-01 10:00:00');
        $this->makeFine($member, 'F002', 2000, 500, 'PARTIAL', '2026-09-02 10:00:00');
        $this->makeFine($member, 'F003', 3000, 3000, 'PAID', '2026-09-03 10:00:00');

        $result = $this->reportService->getFineReport();

        $this->assertEquals(3, $result['summary']['total_fines']);
        $this->assertEquals(1, $result['summary']['unpaid_count']);
        $this->assertEquals(1, $result['summary']['partial_count']);
        $this->assertEquals(1, $result['summary']['paid_count']);
        $this->assertEquals(6000.0, $result['summary']['total_amount']);
        $this->assertEquals(3500.0, $result['summary']['total_amount_paid']);
        $this->assertEquals(2500.0, $result['summary']['total_outstanding']);
    }

    public function test_fine_report_can_filter_by_status(): void
    {
        $member = $this->makeMember();

        $this->makeFine($member, 'F101', 1000, 0, 'UNPAID', '2026-09-01 10:00:00');
        $this->makeFine($member, 'F102', 2000, 500, 'PARTIAL', '2026-09-02 10:00:00');
        $this->makeFine($member, 'F103', 3000, 3000, 'PAID', '2026-09-03 10:00:00');

        $result = $this->reportService->getFineReport(
            null,
            null,
            null,
            'UNPAID'
        );

        $this->assertEquals(1, $result['summary']['total_fines']);
        $this->assertEquals(1, $result['summary']['unpaid_count']);
        $this->assertEquals(1000.0, $result['summary']['total_amount']);
        $this->assertEquals(1000.0, $result['summary']['total_outstanding']);
    }

    public function test_fine_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-F-001');
        $member2 = $this->makeMember('MEM-F-002');

        $this->makeFine($member1, 'FM1', 1000, 0, 'UNPAID', '2026-09-01 10:00:00');
        $this->makeFine($member2, 'FM2', 2000, 0, 'UNPAID', '2026-09-01 10:00:00');

        $result = $this->reportService->getFineReport(
            null,
            null,
            $member1->id
        );

        $this->assertEquals(1, $result['summary']['total_fines']);
        $this->assertEquals(1000.0, $result['summary']['total_amount']);
    }

    public function test_fine_report_can_filter_by_assessed_date(): void
    {
        $member = $this->makeMember();

        $this->makeFine($member, 'FD1', 1000, 0, 'UNPAID', '2026-08-31 10:00:00');
        $this->makeFine($member, 'FD2', 2000, 1000, 'PARTIAL', '2026-09-10 10:00:00');

        $result = $this->reportService->getFineReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(1, $result['summary']['total_fines']);
        $this->assertEquals(2000.0, $result['summary']['total_amount']);
    }

    public function test_empty_incident_report_returns_zero_totals(): void
    {
        $result = $this->reportService->getIncidentReport();

        $this->assertSame(0, $result['summary']['total_incidents']);
        $this->assertSame(0, $result['summary']['open_count']);
        $this->assertSame(0, $result['summary']['resolved_count']);
        $this->assertSame(0, $result['summary']['lost_count']);
        $this->assertSame(0, $result['summary']['damaged_count']);
        $this->assertSame(0.0, $result['summary']['total_amount_due']);
        $this->assertSame(0.0, $result['summary']['total_amount_paid']);
        $this->assertSame(0.0, $result['summary']['total_outstanding']);
    }

    public function test_incident_report_counts_statuses_types_and_amounts(): void
    {
        $member = $this->makeMember();

        $this->makeIncident(
            $member,
            'I001',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            $member,
            'I002',
            'DAMAGED',
            'RESOLVED',
            50000,
            50000,
            '2026-09-02 10:00:00'
        );

        $result = $this->reportService->getIncidentReport();

        $this->assertEquals(2, $result['summary']['total_incidents']);
        $this->assertEquals(1, $result['summary']['open_count']);
        $this->assertEquals(1, $result['summary']['resolved_count']);
        $this->assertEquals(1, $result['summary']['lost_count']);
        $this->assertEquals(1, $result['summary']['damaged_count']);
        $this->assertEquals(150000.0, $result['summary']['total_amount_due']);
        $this->assertEquals(50000.0, $result['summary']['total_amount_paid']);
        $this->assertEquals(100000.0, $result['summary']['total_outstanding']);
    }

    public function test_incident_report_can_filter_by_type(): void
    {
        $member = $this->makeMember();

        $this->makeIncident(
            $member,
            'IT1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            $member,
            'IT2',
            'DAMAGED',
            'OPEN',
            50000,
            0,
            '2026-09-02 10:00:00'
        );

        $result = $this->reportService->getIncidentReport(
            null,
            null,
            null,
            'LOST'
        );

        $this->assertEquals(1, $result['summary']['total_incidents']);
        $this->assertEquals(1, $result['summary']['lost_count']);
        $this->assertEquals(0, $result['summary']['damaged_count']);
    }

    public function test_incident_report_can_filter_by_status(): void
    {
        $member = $this->makeMember();

        $this->makeIncident(
            $member,
            'IS1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            $member,
            'IS2',
            'DAMAGED',
            'RESOLVED',
            50000,
            50000,
            '2026-09-02 10:00:00'
        );

        $result = $this->reportService->getIncidentReport(
            null,
            null,
            null,
            null,
            'RESOLVED'
        );

        $this->assertEquals(1, $result['summary']['total_incidents']);
        $this->assertEquals(0, $result['summary']['open_count']);
        $this->assertEquals(1, $result['summary']['resolved_count']);
    }

    public function test_incident_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-I-001');
        $member2 = $this->makeMember('MEM-I-002');

        $this->makeIncident(
            $member1,
            'IM1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-09-01 10:00:00'
        );

        $this->makeIncident(
            $member2,
            'IM2',
            'DAMAGED',
            'OPEN',
            50000,
            0,
            '2026-09-01 10:00:00'
        );

        $result = $this->reportService->getIncidentReport(
            null,
            null,
            $member1->id
        );

        $this->assertEquals(1, $result['summary']['total_incidents']);
        $this->assertEquals(100000.0, $result['summary']['total_amount_due']);
    }

    public function test_incident_report_can_filter_by_opened_date(): void
    {
        $member = $this->makeMember();

        $this->makeIncident(
            $member,
            'ID1',
            'LOST',
            'OPEN',
            100000,
            0,
            '2026-08-31 10:00:00'
        );

        $this->makeIncident(
            $member,
            'ID2',
            'DAMAGED',
            'OPEN',
            50000,
            0,
            '2026-09-10 10:00:00'
        );

        $result = $this->reportService->getIncidentReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(1, $result['summary']['total_incidents']);
        $this->assertEquals(50000.0, $result['summary']['total_amount_due']);
    }
}
