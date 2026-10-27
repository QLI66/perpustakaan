<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\CollectionType;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Reservation;
use App\Services\ReservationReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new ReservationReportService;

        Carbon::setTestNow('2026-09-23 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeMember(
        string $number = 'MEM-R-001'
    ): Member {
        $memberType = MemberType::create([
            'code' => 'MT-'.uniqid(),
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => "Member {$number}",
            'member_type_id' => $memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeBook(
        string $title = 'Reservation Book'
    ): Book {
        $collectionType = CollectionType::create([
            'code' => 'COL-'.uniqid(),
            'name' => 'Reference',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    private function makeReservation(
        string $number,
        Member $member,
        Book $book,
        string $status,
        string $reservedAt
    ): Reservation {
        return Reservation::create([
            'reservation_number' => $number,
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => null,
            'status' => $status,
            'reserved_at' => $reservedAt,
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => null,
        ]);
    }

    public function test_empty_reservation_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getReservationReport();

        $this->assertEquals(
            0,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            0,
            $result['summary']['active_count']
        );

        $this->assertEquals(
            0.0,
            $result['summary']['fulfillment_rate']
        );

        $this->assertEquals(
            [
                'PENDING' => 0,
                'READY' => 0,
                'FULFILLED' => 0,
                'CANCELLED' => 0,
                'EXPIRED' => 0,
            ],
            $result['summary']['status_breakdown']
        );
    }

    public function test_reservation_report_counts_statuses_correctly(): void
    {
        $member1 = $this->makeMember('MEM-R-001');
        $member2 = $this->makeMember('MEM-R-002');
        $member3 = $this->makeMember('MEM-R-003');
        $member4 = $this->makeMember('MEM-R-004');
        $member5 = $this->makeMember('MEM-R-005');

        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000050',
            $member1,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000051',
            $member2,
            $book,
            'READY',
            '2026-09-21 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000052',
            $member3,
            $book,
            'FULFILLED',
            '2026-09-22 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000004',
            $member4,
            $book,
            'CANCELLED',
            '2026-09-17 09:00:00'
        );

        $this->makeReservation(
            'RES-2026-000005',
            $member5,
            $book,
            'EXPIRED',
            '2026-09-16 09:00:00'
        );

        $result = $this->reportService->getReservationReport();

        $this->assertEquals(
            5,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            1,
            $result['summary']['pending_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['ready_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['fulfilled_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['cancelled_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['expired_count']
        );

        $this->assertEquals(
            2,
            $result['summary']['active_count']
        );

        $this->assertEquals(
            20.0,
            $result['summary']['fulfillment_rate']
        );
    }

    public function test_reservation_report_can_filter_by_date_range(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000010',
            $member,
            $book,
            'FULFILLED',
            '2026-08-31 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000011',
            $member,
            $book,
            'READY',
            '2026-09-10 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            1,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            1,
            $result['summary']['ready_count']
        );

        $this->assertEquals(
            '2026-09-01',
            $result['filters_applied']['date_from']
        );

        $this->assertEquals(
            '2026-09-30',
            $result['filters_applied']['date_to']
        );
    }

    public function test_reservation_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-R-001');
        $member2 = $this->makeMember('MEM-R-002');
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000020',
            $member1,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000021',
            $member2,
            $book,
            'FULFILLED',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            null,
            null,
            $member1->id
        );

        $this->assertEquals(
            1,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            1,
            $result['summary']['pending_count']
        );

        $this->assertEquals(
            $member1->id,
            $result['filters_applied']['member_id']
        );
    }

    public function test_reservation_report_can_filter_by_book(): void
    {
        $member = $this->makeMember();
        $book1 = $this->makeBook('Book One');
        $book2 = $this->makeBook('Book Two');

        $this->makeReservation(
            'RES-2026-000030',
            $member,
            $book1,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000031',
            $member,
            $book2,
            'READY',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            null,
            null,
            null,
            $book2->id
        );

        $this->assertEquals(
            1,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            1,
            $result['summary']['ready_count']
        );

        $this->assertEquals(
            $book2->id,
            $result['filters_applied']['book_id']
        );
    }

    public function test_reservation_report_can_filter_by_status(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000040',
            $member,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000041',
            $member,
            $book,
            'CANCELLED',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            null,
            null,
            null,
            null,
            'CANCELLED'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            1,
            $result['summary']['cancelled_count']
        );

        $this->assertEquals(
            'CANCELLED',
            $result['filters_applied']['status']
        );
    }

    public function test_reservation_report_active_count_is_pending_plus_ready(): void
    {
        $member1 = $this->makeMember('MEM-R-006');
        $member2 = $this->makeMember('MEM-R-007');
        $member3 = $this->makeMember('MEM-R-008');

        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000050',
            $member1,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000051',
            $member2,
            $book,
            'READY',
            '2026-09-21 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000052',
            $member3,
            $book,
            'FULFILLED',
            '2026-09-22 10:00:00'
        );

        $result = $this->reportService->getReservationReport();

        $this->assertEquals(
            3,
            $result['summary']['total_reservations']
        );

        $this->assertEquals(
            2,
            $result['summary']['active_count']
        );
    }

    public function test_reservation_report_fulfillment_rate_is_zero_without_reservations(): void
    {
        $result = $this->reportService->getReservationReport();

        $this->assertEquals(
            0.0,
            $result['summary']['fulfillment_rate']
        );
    }

    public function test_reservation_report_fulfillment_rate_is_calculated_from_filtered_data(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000060',
            $member,
            $book,
            'FULFILLED',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-000061',
            $member,
            $book,
            'CANCELLED',
            '2026-09-21 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            null,
            null,
            null,
            null,
            'FULFILLED'
        );

        $this->assertEquals(
            100.0,
            $result['summary']['fulfillment_rate']
        );
    }

    public function test_reservation_report_period_matches_filtered_total(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000070',
            $member,
            $book,
            'READY',
            '2026-09-10 10:00:00'
        );

        $result = $this->reportService->getReservationReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            $result['summary']['total_reservations'],
            $result['summary']['period_reservations']
        );
    }

    public function test_reservation_report_returns_all_status_keys(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-000080',
            $member,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $result = $this->reportService->getReservationReport();

        $this->assertArrayHasKey(
            'PENDING',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'READY',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'FULFILLED',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'CANCELLED',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'EXPIRED',
            $result['summary']['status_breakdown']
        );
    }
}
