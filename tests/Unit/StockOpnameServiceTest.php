<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use App\Services\StockOpnameService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockOpnameServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockOpnameService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StockOpnameService;
    }

    private function makeLocation(
        string $code = 'LOC-001'
    ): Location {
        return Location::create([
            'code' => $code,
            'name' => "Location {$code}",
        ]);
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

    private function makeBookCopy(
        Location $location,
        ?Book $book = null,
        string $barcode = 'BC-001',
        string $status = 'AVAILABLE',
        string $condition = 'GOOD'
    ): BookCopy {
        $book ??= $this->makeBook();

        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'location_id' => $location->id,
            'status' => $status,
            'condition' => $condition,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Stock Opname User',
            'email' => uniqid().'@test.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeDraftOpname(
        ?int $locationId = null,
        ?int $createdBy = null
    ): StockOpname {
        return $this->service->createStockOpname([
            'opname_date' => '2026-09-21',
            'location_id' => $locationId,
            'notes' => 'Stock opname test',
        ], $createdBy);
    }

    // -------------------------------------------------------------------------
    // CREATE
    // -------------------------------------------------------------------------

    public function test_can_create_draft_stock_opname(): void
    {
        $location = $this->makeLocation();
        $user = $this->makeUser();

        $opname = $this->makeDraftOpname(
            $location->id,
            $user->id
        );

        $this->assertInstanceOf(StockOpname::class, $opname);
        $this->assertEquals('DRAFT', $opname->status);
        $this->assertEquals('2026-09-21', $opname->opname_date->toDateString());
        $this->assertEquals($location->id, $opname->location_id);
        $this->assertEquals($user->id, $opname->created_by);
        $this->assertStringStartsWith('OPN-2026-', $opname->opname_number);
    }

    public function test_stock_opname_number_is_unique(): void
    {
        $opname1 = $this->makeDraftOpname();
        $opname2 = $this->makeDraftOpname();

        $this->assertNotSame(
            $opname1->opname_number,
            $opname2->opname_number
        );

        $this->assertDatabaseCount('stock_opnames', 2);
    }

    public function test_created_stock_opname_has_no_items_yet(): void
    {
        $opname = $this->makeDraftOpname();

        $this->assertDatabaseCount('stock_opname_items', 0);
        $this->assertCount(0, $opname->items);
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    public function test_can_update_draft_stock_opname(): void
    {
        $location1 = $this->makeLocation('LOC-001');
        $location2 = $this->makeLocation('LOC-002');

        $opname = $this->makeDraftOpname($location1->id);

        $updated = $this->service->updateStockOpname(
            $opname->id,
            [
                'opname_date' => '2026-09-22',
                'location_id' => $location2->id,
                'notes' => 'Updated notes',
            ]
        );

        $this->assertEquals(
            '2026-09-22',
            $updated->opname_date->toDateString()
        );
        $this->assertEquals(
            $location2->id,
            $updated->location_id
        );
        $this->assertEquals(
            'Updated notes',
            $updated->notes
        );
    }

    public function test_cannot_update_in_progress_opname(): void
    {
        $location = $this->makeLocation();
        $this->makeBookCopy($location);

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only a DRAFT stock opname can be updated.'
        );

        $this->service->updateStockOpname(
            $opname->id,
            ['notes' => 'Should fail']
        );
    }

    // -------------------------------------------------------------------------
    // START
    // -------------------------------------------------------------------------

    public function test_start_creates_snapshot_items_for_all_copies_in_location(): void
    {
        $location = $this->makeLocation();
        $book = $this->makeBook();

        $copy1 = $this->makeBookCopy(
            $location,
            $book,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $copy2 = $this->makeBookCopy(
            $location,
            $book,
            'BC-002',
            'BORROWED',
            'FAIR'
        );

        $opname = $this->makeDraftOpname($location->id);

        $started = $this->service->startStockOpname($opname->id);

        $this->assertEquals(
            'IN_PROGRESS',
            $started->status
        );

        $this->assertNotNull($started->started_at);

        $this->assertDatabaseCount(
            'stock_opname_items',
            2
        );

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'book_copy_id' => $copy1->id,
            'expected_status' => 'AVAILABLE',
            'expected_condition' => 'GOOD',
            'result' => 'NOT_CHECKED',
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $opname->id,
            'book_copy_id' => $copy2->id,
            'expected_status' => 'BORROWED',
            'expected_condition' => 'FAIR',
            'result' => 'NOT_CHECKED',
        ]);
    }

    public function test_start_without_location_includes_all_book_copies(): void
    {
        $location1 = $this->makeLocation('LOC-001');
        $location2 = $this->makeLocation('LOC-002');

        $this->makeBookCopy(
            $location1,
            null,
            'BC-001'
        );

        $this->makeBookCopy(
            $location2,
            null,
            'BC-002'
        );

        $opname = $this->makeDraftOpname();

        $this->service->startStockOpname($opname->id);

        $this->assertDatabaseCount(
            'stock_opname_items',
            2
        );
    }

    public function test_start_fails_when_no_book_copies_exist(): void
    {
        $location = $this->makeLocation();

        $opname = $this->makeDraftOpname($location->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'No book copies found for the selected stock opname scope.'
        );

        $this->service->startStockOpname($opname->id);
    }

    public function test_cannot_start_opname_twice(): void
    {
        $location = $this->makeLocation();
        $this->makeBookCopy($location);

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only a DRAFT stock opname can be started.'
        );

        $this->service->startStockOpname($opname->id);
    }

    // -------------------------------------------------------------------------
    // CHECK ITEM
    // -------------------------------------------------------------------------

    public function test_can_check_item_as_found(): void
    {
        $location = $this->makeLocation();

        $copy = $this->makeBookCopy(
            $location,
            null,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::where(
            'stock_opname_id',
            $opname->id
        )->firstOrFail();

        $user = $this->makeUser();

        $checked = $this->service->checkItem(
            $item->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ],
            $user->id
        );

        $this->assertEquals('FOUND', $checked->result);
        $this->assertEquals(
            'AVAILABLE',
            $checked->actual_status
        );
        $this->assertEquals(
            'GOOD',
            $checked->actual_condition
        );
        $this->assertNotNull($checked->checked_at);
        $this->assertEquals(
            $user->id,
            $checked->checked_by
        );

        // Stock opname must NOT modify BookCopy.
        $this->assertEquals(
            'AVAILABLE',
            $copy->fresh()->status
        );

        $this->assertEquals(
            'GOOD',
            $copy->fresh()->condition
        );
    }

    public function test_can_check_item_as_missing(): void
    {
        $location = $this->makeLocation();

        $copy = $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::where(
            'stock_opname_id',
            $opname->id
        )->firstOrFail();

        $checked = $this->service->checkItem(
            $item->id,
            [
                'result' => 'MISSING',
            ]
        );

        $this->assertEquals(
            'MISSING',
            $checked->result
        );

        $this->assertNull(
            $checked->actual_status
        );

        $this->assertNull(
            $checked->actual_condition
        );
    }

    public function test_can_check_item_as_different_status(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $checked = $this->service->checkItem(
            $item->id,
            [
                'result' => 'DIFFERENT',
                'actual_status' => 'DAMAGED',
                'actual_condition' => 'GOOD',
            ]
        );

        $this->assertEquals(
            'DIFFERENT',
            $checked->result
        );

        $this->assertEquals(
            'DAMAGED',
            $checked->actual_status
        );

        $this->assertEquals(
            'GOOD',
            $checked->actual_condition
        );
    }

    public function test_can_check_item_as_different_condition(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $checked = $this->service->checkItem(
            $item->id,
            [
                'result' => 'DIFFERENT',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'POOR',
            ]
        );

        $this->assertEquals(
            'DIFFERENT',
            $checked->result
        );

        $this->assertEquals(
            'POOR',
            $checked->actual_condition
        );
    }

    public function test_found_requires_matching_status_and_condition(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'FOUND result requires actual status and condition to match the expected values.'
        );

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'DAMAGED',
                'actual_condition' => 'GOOD',
            ]
        );
    }

    public function test_missing_cannot_have_actual_values(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'MISSING result cannot have actual status or actual condition.'
        );

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'MISSING',
                'actual_status' => 'AVAILABLE',
            ]
        );
    }

    public function test_different_requires_at_least_one_actual_difference(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001',
            'AVAILABLE',
            'GOOD'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'DIFFERENT result requires at least one actual value to differ from the expected value.'
        );

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'DIFFERENT',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ]
        );
    }

    public function test_invalid_check_result_is_rejected(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Invalid stock opname result.'
        );

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'INVALID',
            ]
        );
    }

    public function test_check_item_cannot_be_done_on_draft_opname(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $item = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'book_copy_id' => BookCopy::firstOrFail()->id,
            'expected_status' => 'AVAILABLE',
            'expected_condition' => 'GOOD',
            'result' => 'NOT_CHECKED',
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only an IN_PROGRESS stock opname can be checked.'
        );

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ]
        );
    }

    // -------------------------------------------------------------------------
    // COMPLETE
    // -------------------------------------------------------------------------

    public function test_cannot_complete_when_items_are_unchecked(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'All stock opname items must be checked before completion.'
        );

        $this->service->completeStockOpname($opname->id);
    }

    public function test_can_complete_when_all_items_are_checked(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $this->makeBookCopy(
            $location,
            null,
            'BC-002',
            'BORROWED',
            'FAIR'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $items = StockOpnameItem::where(
            'stock_opname_id',
            $opname->id
        )->orderBy('id')->get();

        $this->service->checkItem(
            $items[0]->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ]
        );

        $this->service->checkItem(
            $items[1]->id,
            [
                'result' => 'MISSING',
            ]
        );

        $completed = $this->service->completeStockOpname(
            $opname->id,
            Carbon::parse('2026-09-21 16:00:00')
        );

        $this->assertEquals(
            'COMPLETED',
            $completed->status
        );

        $this->assertNotNull(
            $completed->completed_at
        );

        $this->assertEquals(
            '2026-09-21 16:00:00',
            $completed->completed_at->toDateTimeString()
        );
    }

    public function test_completed_opname_cannot_be_completed_again(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ]
        );

        $this->service->completeStockOpname($opname->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only an IN_PROGRESS stock opname can be completed.'
        );

        $this->service->completeStockOpname($opname->id);
    }

    // -------------------------------------------------------------------------
    // CANCEL
    // -------------------------------------------------------------------------

    public function test_can_cancel_draft_opname(): void
    {
        $opname = $this->makeDraftOpname();

        $cancelled = $this->service->cancelStockOpname(
            $opname->id
        );

        $this->assertEquals(
            'CANCELLED',
            $cancelled->status
        );
    }

    public function test_can_cancel_in_progress_opname(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $cancelled = $this->service->cancelStockOpname(
            $opname->id
        );

        $this->assertEquals(
            'CANCELLED',
            $cancelled->status
        );
    }

    public function test_completed_opname_cannot_be_cancelled(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $item = StockOpnameItem::firstOrFail();

        $this->service->checkItem(
            $item->id,
            [
                'result' => 'FOUND',
                'actual_status' => 'AVAILABLE',
                'actual_condition' => 'GOOD',
            ]
        );

        $this->service->completeStockOpname(
            $opname->id
        );

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only a DRAFT or IN_PROGRESS stock opname can be cancelled.'
        );

        $this->service->cancelStockOpname(
            $opname->id
        );
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    public function test_can_delete_draft_opname(): void
    {
        $opname = $this->makeDraftOpname();

        $this->service->deleteStockOpname(
            $opname->id
        );

        $this->assertDatabaseMissing(
            'stock_opnames',
            ['id' => $opname->id]
        );
    }

    public function test_cannot_delete_in_progress_opname(): void
    {
        $location = $this->makeLocation();

        $this->makeBookCopy(
            $location,
            null,
            'BC-001'
        );

        $opname = $this->makeDraftOpname($location->id);

        $this->service->startStockOpname($opname->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Only a DRAFT stock opname can be deleted.'
        );

        $this->service->deleteStockOpname(
            $opname->id
        );
    }
}
