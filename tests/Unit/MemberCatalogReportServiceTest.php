<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Services\MemberCatalogReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCatalogReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MemberCatalogReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new MemberCatalogReportService;

        Carbon::setTestNow('2026-09-23 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeMember(
        string $number,
        string $status = 'ACTIVE',
        string $joinedAt = '2026-09-20'
    ): Member {
        $memberType = MemberType::create([
            'code' => 'MT-'.uniqid(),
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => "Member {$number}",
            'member_type_id' => $memberType->id,
            'joined_at' => $joinedAt,
            'status' => $status,
        ]);
    }

    private function makeBook(
        string $title
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

    private function makeLocation(
        string $code
    ): Location {
        return Location::create([
            'code' => $code,
            'name' => "Location {$code}",
        ]);
    }

    private function makeCopy(
        Book $book,
        Location $location,
        string $barcode,
        string $status = 'AVAILABLE'
    ): BookCopy {
        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'location_id' => $location->id,
            'status' => $status,
            'condition' => 'GOOD',
        ]);
    }

    public function test_empty_member_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getMemberReport();

        $this->assertEquals(
            0,
            $result['summary']['total_members']
        );

        $this->assertEquals(
            0,
            $result['summary']['active_count']
        );
    }

    public function test_member_report_counts_statuses_correctly(): void
    {
        $this->makeMember(
            'MEM-CR-001',
            'ACTIVE'
        );

        $this->makeMember(
            'MEM-CR-002',
            'INACTIVE'
        );

        $this->makeMember(
            'MEM-CR-003',
            'SUSPENDED'
        );

        $result = $this->reportService->getMemberReport();

        $this->assertEquals(
            3,
            $result['summary']['total_members']
        );

        $this->assertEquals(
            1,
            $result['summary']['active_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['inactive_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['suspended_count']
        );
    }

    public function test_member_report_can_filter_by_status(): void
    {
        $this->makeMember(
            'MEM-CR-010',
            'ACTIVE'
        );

        $this->makeMember(
            'MEM-CR-011',
            'INACTIVE'
        );

        $result = $this->reportService->getMemberReport(
            null,
            null,
            'INACTIVE'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_members']
        );

        $this->assertEquals(
            1,
            $result['summary']['inactive_count']
        );
    }

    public function test_member_report_can_filter_by_joined_date(): void
    {
        $this->makeMember(
            'MEM-CR-020',
            'ACTIVE',
            '2026-08-31'
        );

        $this->makeMember(
            'MEM-CR-021',
            'ACTIVE',
            '2026-09-10'
        );

        $result = $this->reportService->getMemberReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            1,
            $result['summary']['total_members']
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

    public function test_catalog_report_returns_zero_for_empty_catalog(): void
    {
        $result = $this->reportService->getCatalogReport();

        $this->assertEquals(
            0,
            $result['summary']['total_books']
        );

        $this->assertEquals(
            0,
            $result['summary']['total_copies']
        );
    }

    public function test_catalog_report_counts_books_and_copy_statuses(): void
    {
        $location = $this->makeLocation('LOC-CR-001');

        $book1 = $this->makeBook('Book One');
        $book2 = $this->makeBook('Book Two');

        $this->makeCopy(
            $book1,
            $location,
            'BC-CR-001',
            'AVAILABLE'
        );

        $this->makeCopy(
            $book1,
            $location,
            'BC-CR-002',
            'BORROWED'
        );

        $this->makeCopy(
            $book2,
            $location,
            'BC-CR-003',
            'LOST'
        );

        $result = $this->reportService->getCatalogReport();

        $this->assertEquals(
            2,
            $result['summary']['total_books']
        );

        $this->assertEquals(
            3,
            $result['summary']['total_copies']
        );

        $this->assertEquals(
            1,
            $result['summary']['available_copies']
        );

        $this->assertEquals(
            1,
            $result['summary']['borrowed_copies']
        );

        $this->assertEquals(
            1,
            $result['summary']['lost_copies']
        );
    }

    public function test_catalog_report_can_filter_by_status(): void
    {
        $location = $this->makeLocation('LOC-CR-002');
        $book = $this->makeBook('Status Book');

        $this->makeCopy(
            $book,
            $location,
            'BC-CR-010',
            'AVAILABLE'
        );

        $this->makeCopy(
            $book,
            $location,
            'BC-CR-011',
            'DAMAGED'
        );

        $result = $this->reportService->getCatalogReport(
            'DAMAGED'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_books']
        );

        $this->assertEquals(
            1,
            $result['summary']['total_copies']
        );

        $this->assertEquals(
            1,
            $result['summary']['damaged_copies']
        );
    }

    public function test_catalog_report_can_filter_by_location(): void
    {
        $location1 = $this->makeLocation('LOC-CR-003');
        $location2 = $this->makeLocation('LOC-CR-004');
        $book = $this->makeBook('Location Book');

        $this->makeCopy(
            $book,
            $location1,
            'BC-CR-020'
        );

        $this->makeCopy(
            $book,
            $location2,
            'BC-CR-021'
        );

        $result = $this->reportService->getCatalogReport(
            null,
            $location1->id
        );

        $this->assertEquals(
            1,
            $result['summary']['total_books']
        );

        $this->assertEquals(
            1,
            $result['summary']['total_copies']
        );

        $this->assertEquals(
            $location1->id,
            $result['filters_applied']['location_id']
        );
    }

    public function test_catalog_report_returns_all_status_keys(): void
    {
        $result = $this->reportService->getCatalogReport();

        $this->assertArrayHasKey(
            'AVAILABLE',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'BORROWED',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'RESERVED',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'LOST',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'DAMAGED',
            $result['summary']['status_breakdown']
        );

        $this->assertArrayHasKey(
            'MAINTENANCE',
            $result['summary']['status_breakdown']
        );
    }
}
