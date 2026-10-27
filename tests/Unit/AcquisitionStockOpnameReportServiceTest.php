<?php

namespace Tests\Unit;

use App\Models\Acquisition;
use App\Models\AcquisitionItem;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\AcquisitionStockOpnameReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcquisitionStockOpnameReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcquisitionStockOpnameReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = new AcquisitionStockOpnameReportService;

        Carbon::setTestNow('2026-09-22 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeBook(
        string $title = 'Sample Book'
    ): Book {
        $collectionType = CollectionType::create([
            'code' => 'COL-'.uniqid(),
            'name' => 'Fiction',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    private function makeLocation(
        string $code = 'LOC-001'
    ): Location {
        return Location::create([
            'code' => $code,
            'name' => "Location {$code}",
        ]);
    }

    private function makeBookCopy(
        Location $location,
        Book $book,
        string $barcode,
        string $status = 'AVAILABLE',
        string $condition = 'GOOD'
    ): BookCopy {
        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'location_id' => $location->id,
            'status' => $status,
            'condition' => $condition,
        ]);
    }

    private function makeAcquisition(
        string $number,
        string $date,
        string $sourceType,
        ?string $sourceName,
        ?float $totalAmount
    ): Acquisition {
        return Acquisition::create([
            'acquisition_number' => $number,
            'acquisition_date' => $date,
            'source_type' => $sourceType,
            'source_name' => $sourceName,
            'reference_number' => null,
            'total_amount' => $totalAmount,
            'created_by' => null,
            'notes' => null,
        ]);
    }

    private function makeAcquisitionItem(
        Acquisition $acquisition,
        Book $book,
        int $quantity,
        ?float $unitPrice = null
    ): AcquisitionItem {
        return AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'notes' => null,
        ]);
    }

    private function makeStockOpname(
        string $number,
        string $date,
        ?int $locationId,
        string $status
    ): StockOpname {
        return StockOpname::create([
            'opname_number' => $number,
            'opname_date' => $date,
            'location_id' => $locationId,
            'status' => $status,
            'created_by' => null,
            'started_at' => in_array(
                $status,
                ['IN_PROGRESS', 'COMPLETED'],
                true
            )
                ? Carbon::parse("{$date} 09:00:00")
                : null,
            'completed_at' => $status === 'COMPLETED'
                ? Carbon::parse("{$date} 16:00:00")
                : null,
            'notes' => null,
        ]);
    }

    private function makeStockOpnameItem(
        StockOpname $opname,
        BookCopy $copy,
        string $result
    ): StockOpnameItem {
        $checked = $result !== 'NOT_CHECKED';

        return StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'book_copy_id' => $copy->id,
            'expected_status' => 'AVAILABLE',
            'actual_status' => $checked ? 'AVAILABLE' : null,
            'expected_condition' => 'GOOD',
            'actual_condition' => $checked ? 'GOOD' : null,
            'result' => $result,
            'checked_at' => $checked
                ? '2026-09-21 10:00:00'
                : null,
            'checked_by' => null,
            'notes' => null,
        ]);
    }

    // ============================================================
    // ACQUISITION REPORT
    // ============================================================

    public function test_empty_acquisition_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getAcquisitionReport();

        $this->assertEquals(
            0,
            $result['summary']['total_acquisitions']
        );

        $this->assertEquals(
            0,
            $result['summary']['total_acquisition_items']
        );

        $this->assertEquals(
            0,
            $result['summary']['total_quantity']
        );

        $this->assertEquals(
            0.0,
            $result['summary']['total_amount']
        );

        $this->assertEquals(
            [],
            $result['summary']['source_breakdown']
        );
    }

    public function test_acquisition_report_counts_correctly(): void
    {
        $book1 = $this->makeBook('Matematika');
        $book2 = $this->makeBook('Fisika');

        $acquisition = $this->makeAcquisition(
            'ACQ-2026-000001',
            '2026-09-21',
            'PURCHASE',
            'Supplier A',
            200000
        );

        $this->makeAcquisitionItem(
            $acquisition,
            $book1,
            2,
            50000
        );

        $this->makeAcquisitionItem(
            $acquisition,
            $book2,
            2,
            50000
        );

        $result = $this->reportService->getAcquisitionReport();

        $this->assertEquals(
            1,
            $result['summary']['total_acquisitions']
        );

        $this->assertEquals(
            2,
            $result['summary']['total_acquisition_items']
        );

        $this->assertEquals(
            4,
            $result['summary']['total_quantity']
        );

        $this->assertEquals(
            200000.0,
            $result['summary']['total_amount']
        );
    }

    public function test_acquisition_report_breaks_down_source_type(): void
    {
        $book1 = $this->makeBook('Purchase Book');
        $book2 = $this->makeBook('Donation Book');
        $book3 = $this->makeBook('Grant Book');

        $purchase = $this->makeAcquisition(
            'ACQ-2026-000001',
            '2026-09-01',
            'PURCHASE',
            'Supplier A',
            100000
        );

        $this->makeAcquisitionItem(
            $purchase,
            $book1,
            2,
            50000
        );

        $donation = $this->makeAcquisition(
            'ACQ-2026-000002',
            '2026-09-02',
            'DONATION',
            'Donor A',
            null
        );

        $this->makeAcquisitionItem(
            $donation,
            $book2,
            5,
            null
        );

        $grant = $this->makeAcquisition(
            'ACQ-2026-000003',
            '2026-09-03',
            'GRANT',
            'Government',
            250000
        );

        $this->makeAcquisitionItem(
            $grant,
            $book3,
            3,
            83333.33
        );

        $result = $this->reportService->getAcquisitionReport();

        $breakdown = collect(
            $result['summary']['source_breakdown']
        )->keyBy('source_type');

        $this->assertCount(3, $breakdown);

        $this->assertEquals(
            1,
            $breakdown['PURCHASE']['acquisition_count']
        );

        $this->assertEquals(
            100000.0,
            $breakdown['PURCHASE']['total_amount']
        );

        $this->assertEquals(
            1,
            $breakdown['DONATION']['acquisition_count']
        );

        $this->assertEquals(
            0.0,
            $breakdown['DONATION']['total_amount']
        );

        $this->assertEquals(
            1,
            $breakdown['GRANT']['acquisition_count']
        );

        $this->assertEquals(
            250000.0,
            $breakdown['GRANT']['total_amount']
        );
    }

    public function test_acquisition_report_can_filter_by_date_range(): void
    {
        $book1 = $this->makeBook('Old Book');
        $book2 = $this->makeBook('New Book');

        $old = $this->makeAcquisition(
            'ACQ-2026-000010',
            '2026-08-31',
            'PURCHASE',
            'Old Supplier',
            50000
        );

        $this->makeAcquisitionItem(
            $old,
            $book1,
            1,
            50000
        );

        $current = $this->makeAcquisition(
            'ACQ-2026-000011',
            '2026-09-10',
            'PURCHASE',
            'New Supplier',
            100000
        );

        $this->makeAcquisitionItem(
            $current,
            $book2,
            2,
            50000
        );

        $result = $this->reportService->getAcquisitionReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            1,
            $result['summary']['total_acquisitions']
        );

        $this->assertEquals(
            2,
            $result['summary']['total_quantity']
        );

        $this->assertEquals(
            100000.0,
            $result['summary']['total_amount']
        );
    }

    public function test_acquisition_report_can_filter_by_source_type(): void
    {
        $book1 = $this->makeBook('Purchase Book');
        $book2 = $this->makeBook('Donation Book');

        $purchase = $this->makeAcquisition(
            'ACQ-2026-000020',
            '2026-09-01',
            'PURCHASE',
            'Supplier',
            100000
        );

        $this->makeAcquisitionItem(
            $purchase,
            $book1,
            2,
            50000
        );

        $donation = $this->makeAcquisition(
            'ACQ-2026-000021',
            '2026-09-02',
            'DONATION',
            'Donor',
            null
        );

        $this->makeAcquisitionItem(
            $donation,
            $book2,
            5,
            null
        );

        $result = $this->reportService->getAcquisitionReport(
            null,
            null,
            'DONATION'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_acquisitions']
        );

        $this->assertEquals(
            5,
            $result['summary']['total_quantity']
        );

        $this->assertEquals(
            0.0,
            $result['summary']['total_amount']
        );
    }

    public function test_acquisition_report_can_filter_by_source_name(): void
    {
        $book1 = $this->makeBook('Supplier Book');
        $book2 = $this->makeBook('Donor Book');

        $supplier = $this->makeAcquisition(
            'ACQ-2026-000030',
            '2026-09-01',
            'PURCHASE',
            'PT Maju Jaya',
            100000
        );

        $this->makeAcquisitionItem(
            $supplier,
            $book1,
            2,
            50000
        );

        $donor = $this->makeAcquisition(
            'ACQ-2026-000031',
            '2026-09-02',
            'DONATION',
            'Bapak Ahmad',
            null
        );

        $this->makeAcquisitionItem(
            $donor,
            $book2,
            3,
            null
        );

        $result = $this->reportService->getAcquisitionReport(
            null,
            null,
            null,
            'Maju'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_acquisitions']
        );

        $this->assertEquals(
            2,
            $result['summary']['total_quantity']
        );

        $this->assertEquals(
            100000.0,
            $result['summary']['total_amount']
        );
    }

    // ============================================================
    // STOCK OPNAME REPORT
    // ============================================================

    public function test_empty_stock_opname_report_returns_zero_summary(): void
    {
        $result = $this->reportService->getStockOpnameReport();

        $this->assertEquals(
            0,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            0,
            $result['summary']['draft_count']
        );

        $this->assertEquals(
            0,
            $result['summary']['in_progress_count']
        );

        $this->assertEquals(
            0,
            $result['summary']['completed_count']
        );

        $this->assertEquals(
            0,
            $result['summary']['cancelled_count']
        );

        $this->assertEquals(
            0,
            $result['summary']['total_expected_items']
        );

        $this->assertEquals(
            0,
            $result['summary']['total_checked_items']
        );

        $this->assertEquals(
            0.0,
            $result['summary']['completion_percentage']
        );
    }

    public function test_stock_opname_report_counts_statuses(): void
    {
        $this->makeStockOpname(
            'OPN-2026-000001',
            '2026-09-01',
            null,
            'DRAFT'
        );

        $this->makeStockOpname(
            'OPN-2026-000002',
            '2026-09-02',
            null,
            'IN_PROGRESS'
        );

        $this->makeStockOpname(
            'OPN-2026-000003',
            '2026-09-03',
            null,
            'COMPLETED'
        );

        $this->makeStockOpname(
            'OPN-2026-000004',
            '2026-09-04',
            null,
            'CANCELLED'
        );

        $result = $this->reportService->getStockOpnameReport();

        $this->assertEquals(
            4,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            1,
            $result['summary']['draft_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['in_progress_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['completed_count']
        );

        $this->assertEquals(
            1,
            $result['summary']['cancelled_count']
        );
    }

    public function test_stock_opname_report_counts_results_and_completion(): void
    {
        $location = $this->makeLocation();
        $book = $this->makeBook();

        $copy1 = $this->makeBookCopy(
            $location,
            $book,
            'BC-001'
        );

        $copy2 = $this->makeBookCopy(
            $location,
            $book,
            'BC-002'
        );

        $copy3 = $this->makeBookCopy(
            $location,
            $book,
            'BC-003'
        );

        $opname = $this->makeStockOpname(
            'OPN-2026-000010',
            '2026-09-21',
            $location->id,
            'IN_PROGRESS'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy1,
            'FOUND'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy2,
            'MISSING'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy3,
            'NOT_CHECKED'
        );

        $result = $this->reportService->getStockOpnameReport();

        $this->assertEquals(
            1,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            3,
            $result['summary']['total_expected_items']
        );

        $this->assertEquals(
            2,
            $result['summary']['total_checked_items']
        );

        $this->assertEquals(
            1,
            $result['summary']['not_checked_items']
        );

        $this->assertEquals(
            1,
            $result['summary']['found_items']
        );

        $this->assertEquals(
            1,
            $result['summary']['missing_items']
        );

        $this->assertEquals(
            0,
            $result['summary']['different_items']
        );

        $this->assertEquals(
            66.67,
            $result['summary']['completion_percentage']
        );
    }

    public function test_stock_opname_report_counts_different_as_checked(): void
    {
        $location = $this->makeLocation();
        $book = $this->makeBook();

        $copy1 = $this->makeBookCopy(
            $location,
            $book,
            'BC-DIFF-001'
        );

        $copy2 = $this->makeBookCopy(
            $location,
            $book,
            'BC-DIFF-002'
        );

        $opname = $this->makeStockOpname(
            'OPN-2026-000011',
            '2026-09-21',
            $location->id,
            'COMPLETED'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy1,
            'DIFFERENT'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy2,
            'FOUND'
        );

        $result = $this->reportService->getStockOpnameReport();

        $this->assertEquals(
            2,
            $result['summary']['total_expected_items']
        );

        $this->assertEquals(
            2,
            $result['summary']['total_checked_items']
        );

        $this->assertEquals(
            1,
            $result['summary']['different_items']
        );

        $this->assertEquals(
            100.0,
            $result['summary']['completion_percentage']
        );
    }

    public function test_stock_opname_report_can_filter_by_status(): void
    {
        $this->makeStockOpname(
            'OPN-2026-000020',
            '2026-09-01',
            null,
            'DRAFT'
        );

        $this->makeStockOpname(
            'OPN-2026-000021',
            '2026-09-02',
            null,
            'COMPLETED'
        );

        $result = $this->reportService->getStockOpnameReport(
            null,
            null,
            'COMPLETED'
        );

        $this->assertEquals(
            1,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            1,
            $result['summary']['completed_count']
        );

        $this->assertEquals(
            0,
            $result['summary']['draft_count']
        );
    }

    public function test_stock_opname_report_can_filter_by_location(): void
    {
        $location1 = $this->makeLocation('LOC-001');
        $location2 = $this->makeLocation('LOC-002');

        $this->makeStockOpname(
            'OPN-2026-000030',
            '2026-09-01',
            $location1->id,
            'COMPLETED'
        );

        $this->makeStockOpname(
            'OPN-2026-000031',
            '2026-09-02',
            $location2->id,
            'COMPLETED'
        );

        $result = $this->reportService->getStockOpnameReport(
            null,
            null,
            null,
            $location1->id
        );

        $this->assertEquals(
            1,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            $location1->id,
            $result['filters_applied']['location_id']
        );
    }

    public function test_stock_opname_report_can_filter_by_date_range(): void
    {
        $this->makeStockOpname(
            'OPN-2026-000040',
            '2026-08-31',
            null,
            'COMPLETED'
        );

        $this->makeStockOpname(
            'OPN-2026-000041',
            '2026-09-10',
            null,
            'COMPLETED'
        );

        $result = $this->reportService->getStockOpnameReport(
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(
            1,
            $result['summary']['total_opnames']
        );

        $this->assertEquals(
            1,
            $result['summary']['completed_count']
        );
    }

    public function test_stock_opname_report_does_not_mutate_book_copy(): void
    {
        $location = $this->makeLocation();
        $book = $this->makeBook();

        $copy = $this->makeBookCopy(
            $location,
            $book,
            'BC-IMMUTABLE-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeStockOpname(
            'OPN-2026-000050',
            '2026-09-21',
            $location->id,
            'IN_PROGRESS'
        );

        $this->makeStockOpnameItem(
            $opname,
            $copy,
            'MISSING'
        );

        $before = $copy->fresh();

        $this->reportService->getStockOpnameReport();

        $after = $copy->fresh();

        $this->assertEquals(
            $before->status,
            $after->status
        );

        $this->assertEquals(
            $before->condition,
            $after->condition
        );
    }
}
