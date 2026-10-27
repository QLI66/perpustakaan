<?php

namespace Tests\Feature;

use App\Models\Acquisition;
use App\Models\AcquisitionItem;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcquisitionStockOpnameReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Super Admin Report',
            'email' => 'superadmin-report@library.test',
            'password' => Hash::make('password'),
        ]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Library Staff Report',
            'email' => 'staff-report@library.test',
            'password' => Hash::make('password'),
        ]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->member = User::create([
            'name' => 'Member Report',
            'email' => 'member-report@library.test',
            'password' => Hash::make('password'),
        ]);
        $this->member->assignRole('MEMBER');
    }

    private function makeBook(
        string $title = 'Report Book'
    ): Book {
        $collectionType = CollectionType::create([
            'code' => 'COL-'.uniqid(),
            'name' => 'Report Collection',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    private function makeLocation(
        ?string $code = null
    ): Location {
        $code ??= 'LOC-'.uniqid();

        return Location::create([
            'code' => $code,
            'name' => "Location {$code}",
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
                ? "{$date} 09:00:00"
                : null,
            'completed_at' => $status === 'COMPLETED'
                ? "{$date} 16:00:00"
                : null,
            'notes' => null,
        ]);
    }

    private function makeBookCopy(
        Location $location,
        Book $book,
        string $barcode
    ): BookCopy {
        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'inventory_number' => 'INV-'.uniqid(),
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
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
    // AUTHORIZATION
    // ============================================================

    public function test_guest_cannot_access_acquisition_report(): void
    {
        $this->getJson('/api/reports/acquisitions')
            ->assertStatus(401);
    }

    public function test_guest_cannot_access_stock_opname_report(): void
    {
        $this->getJson('/api/reports/stock-opnames')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_acquisition_report(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/reports/acquisitions')
            ->assertStatus(403);
    }

    public function test_member_cannot_access_stock_opname_report(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/reports/stock-opnames')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_acquisition_report(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/acquisitions')
            ->assertStatus(200);
    }

    public function test_library_staff_can_access_stock_opname_report(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/reports/stock-opnames')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_acquisition_report(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/reports/acquisitions')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_stock_opname_report(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/reports/stock-opnames')
            ->assertStatus(200);
    }

    // ============================================================
    // ACQUISITION REPORT
    // ============================================================

    public function test_acquisition_report_returns_expected_structure(): void
    {
        $book = $this->makeBook();

        $acquisition = $this->makeAcquisition(
            'ACQ-2026-900001',
            '2026-09-21',
            'PURCHASE',
            'Supplier Test',
            150000
        );

        $this->makeAcquisitionItem(
            $acquisition,
            $book,
            2,
            75000
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/acquisitions');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_acquisitions',
                        'total_acquisition_items',
                        'total_quantity',
                        'total_amount',
                        'period_acquisitions',
                        'period_quantity',
                        'period_amount',
                        'source_breakdown',
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'source_type',
                        'source_name',
                    ],
                ],
            ]);
    }

    public function test_acquisition_report_returns_correct_summary(): void
    {
        $book1 = $this->makeBook('Matematika');
        $book2 = $this->makeBook('Fisika');

        $acquisition = $this->makeAcquisition(
            'ACQ-2026-900002',
            '2026-09-21',
            'PURCHASE',
            'Supplier Test',
            250000
        );

        $this->makeAcquisitionItem(
            $acquisition,
            $book1,
            2,
            75000
        );

        $this->makeAcquisitionItem(
            $acquisition,
            $book2,
            2,
            50000
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/acquisitions');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_acquisitions')
        );

        $this->assertEquals(
            2,
            $response->json('data.summary.total_acquisition_items')
        );

        $this->assertEquals(
            4,
            $response->json('data.summary.total_quantity')
        );

        $this->assertEquals(
            250000,
            $response->json('data.summary.total_amount')
        );
    }

    public function test_acquisition_report_can_filter_by_source_type(): void
    {
        $book1 = $this->makeBook('Purchase Book');
        $book2 = $this->makeBook('Donation Book');

        $purchase = $this->makeAcquisition(
            'ACQ-2026-900003',
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
            'ACQ-2026-900004',
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

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/acquisitions?source_type=DONATION');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_acquisitions')
        );

        $this->assertEquals(
            5,
            $response->json('data.summary.total_quantity')
        );

        $this->assertEquals(
            'DONATION',
            $response->json('data.filters_applied.source_type')
        );
    }

    public function test_acquisition_report_can_filter_by_source_name(): void
    {
        $book1 = $this->makeBook('Supplier Book');
        $book2 = $this->makeBook('Donor Book');

        $supplier = $this->makeAcquisition(
            'ACQ-2026-900005',
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
            'ACQ-2026-900006',
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

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/acquisitions?source_name=Maju');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_acquisitions')
        );

        $this->assertEquals(
            2,
            $response->json('data.summary.total_quantity')
        );

        $this->assertEquals(
            'Maju',
            $response->json('data.filters_applied.source_name')
        );
    }

    public function test_acquisition_report_can_filter_by_date_range(): void
    {
        $book1 = $this->makeBook('Old Book');
        $book2 = $this->makeBook('New Book');

        $old = $this->makeAcquisition(
            'ACQ-2026-900007',
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
            'ACQ-2026-900008',
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

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/acquisitions'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_acquisitions')
        );

        $this->assertEquals(
            2,
            $response->json('data.summary.total_quantity')
        );

        $this->assertEquals(
            100000,
            $response->json('data.summary.total_amount')
        );
    }

    public function test_acquisition_report_rejects_invalid_source_type(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/acquisitions?source_type=INVALID'
        )->assertStatus(422);
    }

    public function test_acquisition_report_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/acquisitions'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }

    // ============================================================
    // STOCK OPNAME REPORT
    // ============================================================

    public function test_stock_opname_report_returns_expected_structure(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/stock-opnames');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_opnames',
                        'draft_count',
                        'in_progress_count',
                        'completed_count',
                        'cancelled_count',
                        'total_expected_items',
                        'total_checked_items',
                        'not_checked_items',
                        'found_items',
                        'missing_items',
                        'different_items',
                        'completion_percentage',
                        'period_opnames',
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'status',
                        'location_id',
                    ],
                ],
            ]);
    }

    public function test_stock_opname_report_returns_correct_summary(): void
    {
        $location = $this->makeLocation();
        $book = $this->makeBook();

        $copy1 = $this->makeBookCopy(
            $location,
            $book,
            'BC-REPORT-001'
        );

        $copy2 = $this->makeBookCopy(
            $location,
            $book,
            'BC-REPORT-002'
        );

        $copy3 = $this->makeBookCopy(
            $location,
            $book,
            'BC-REPORT-003'
        );

        $opname = $this->makeStockOpname(
            'OPN-2026-900001',
            '2026-09-21',
            $location->id,
            'COMPLETED'
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
            'DIFFERENT'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/stock-opnames');

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_opnames')
        );

        $this->assertEquals(
            3,
            $response->json('data.summary.total_expected_items')
        );

        $this->assertEquals(
            3,
            $response->json('data.summary.total_checked_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.found_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.missing_items')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.different_items')
        );

        $this->assertEquals(
            100,
            $response->json('data.summary.completion_percentage')
        );
    }

    public function test_stock_opname_report_can_filter_by_status(): void
    {
        $this->makeStockOpname(
            'OPN-2026-900002',
            '2026-09-01',
            null,
            'DRAFT'
        );

        $this->makeStockOpname(
            'OPN-2026-900003',
            '2026-09-02',
            null,
            'COMPLETED'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames?status=COMPLETED'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_opnames')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.completed_count')
        );

        $this->assertEquals(
            'COMPLETED',
            $response->json('data.filters_applied.status')
        );
    }

    public function test_stock_opname_report_can_filter_by_location(): void
    {
        $location1 = $this->makeLocation('LOC-REPORT-001');
        $location2 = $this->makeLocation('LOC-REPORT-002');

        $this->makeStockOpname(
            'OPN-2026-900004',
            '2026-09-01',
            $location1->id,
            'COMPLETED'
        );

        $this->makeStockOpname(
            'OPN-2026-900005',
            '2026-09-02',
            $location2->id,
            'COMPLETED'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames'
            .'?location_id='.$location1->id
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_opnames')
        );

        $this->assertEquals(
            $location1->id,
            $response->json('data.filters_applied.location_id')
        );
    }

    public function test_stock_opname_report_can_filter_by_date_range(): void
    {
        $this->makeStockOpname(
            'OPN-2026-900006',
            '2026-08-31',
            null,
            'COMPLETED'
        );

        $this->makeStockOpname(
            'OPN-2026-900007',
            '2026-09-10',
            null,
            'COMPLETED'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_opnames')
        );
    }

    public function test_stock_opname_report_rejects_invalid_status(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames?status=INVALID'
        )->assertStatus(422);
    }

    public function test_stock_opname_report_rejects_invalid_location(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames?location_id=999999'
        )->assertStatus(422);
    }

    public function test_stock_opname_report_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/stock-opnames'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }
}
