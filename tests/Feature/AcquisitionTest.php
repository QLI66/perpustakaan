<?php

namespace Tests\Feature;

use App\Models\Acquisition;
use App\Models\AcquisitionItem;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookAndLocation(): array
    {
        $collectionType = CollectionType::create([
            'code' => 'TEST',
            'name' => 'Test Type',
        ]);

        $book = Book::create([
            'title' => 'Buku Acquisition',
            'collection_type_id' => $collectionType->id,
        ]);

        $location = Location::create([
            'code' => 'RAK-ACQ',
            'name' => 'Rak Acquisition',
        ]);

        return [$book, $location];
    }

    private function makeAcquisition(array $overrides = []): Acquisition
    {
        return Acquisition::create(array_merge([
            'acquisition_number' => 'ACQ-TEST-001',
            'acquisition_date' => '2026-09-21',
            'source_type' => 'PURCHASE',
            'source_name' => 'Supplier Test',
            'reference_number' => 'REF-001',
            'total_amount' => 150000,
            'notes' => 'Test acquisition',
        ], $overrides));
    }

    public function test_acquisitions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('acquisitions'));
    }

    public function test_acquisition_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('acquisition_items'));
    }

    public function test_book_copies_has_acquisition_item_id_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('book_copies', 'acquisition_item_id')
        );
    }

    public function test_can_create_acquisition(): void
    {
        $acquisition = $this->makeAcquisition();

        $this->assertDatabaseHas('acquisitions', [
            'id' => $acquisition->id,
            'acquisition_number' => 'ACQ-TEST-001',
            'source_type' => 'PURCHASE',
        ]);
    }

    public function test_acquisition_number_must_be_unique(): void
    {
        $this->makeAcquisition();

        $this->expectException(QueryException::class);

        $this->makeAcquisition([
            'acquisition_number' => 'ACQ-TEST-001',
        ]);
    }

    public function test_source_type_must_be_valid(): void
    {
        $this->expectException(QueryException::class);

        $this->makeAcquisition([
            'source_type' => 'INVALID',
        ]);
    }

    public function test_total_amount_cannot_be_negative(): void
    {
        $this->expectException(QueryException::class);

        $this->makeAcquisition([
            'total_amount' => -1,
        ]);
    }

    public function test_acquisition_date_casts_to_date(): void
    {
        $acquisition = $this->makeAcquisition();

        $this->assertInstanceOf(
            Carbon::class,
            $acquisition->acquisition_date
        );
    }

    public function test_total_amount_casts_to_decimal(): void
    {
        $acquisition = $this->makeAcquisition([
            'total_amount' => 150000.55,
        ]);

        $this->assertEquals('150000.55', $acquisition->total_amount);
    }

    public function test_acquisition_has_many_items(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $acquisition->load('items');

        $this->assertCount(1, $acquisition->items);
        $this->assertInstanceOf(AcquisitionItem::class, $item);
    }

    public function test_acquisition_item_belongs_to_acquisition(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $this->assertInstanceOf(
            Acquisition::class,
            $item->acquisition
        );
    }

    public function test_acquisition_item_belongs_to_book(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $this->assertInstanceOf(Book::class, $item->book);
    }

    public function test_quantity_must_be_positive(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $this->expectException(QueryException::class);

        AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 0,
            'unit_price' => 75000,
        ]);
    }

    public function test_unit_price_cannot_be_negative(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $this->expectException(QueryException::class);

        AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => -1,
        ]);
    }

    public function test_same_book_cannot_be_added_twice_to_one_acquisition(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $this->expectException(QueryException::class);

        AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 3,
            'unit_price' => 70000,
        ]);
    }

    public function test_deleting_acquisition_cascades_items(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $acquisition->delete();

        $this->assertDatabaseMissing('acquisition_items', [
            'id' => $item->id,
        ]);
    }

    public function test_deleting_book_is_restricted_when_used_by_acquisition_item(): void
    {
        [$book] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $this->expectException(QueryException::class);

        $book->delete();
    }

    public function test_book_copy_can_reference_acquisition_item(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => 75000,
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'ACQ-TEST-001',
            'location_id' => $location->id,
        ]);

        DB::table('book_copies')
            ->where('id', $copy->id)
            ->update([
                'acquisition_item_id' => $item->id,
            ]);

        $copy->refresh();

        $this->assertEquals($item->id, $copy->acquisition_item_id);
        $this->assertInstanceOf(
            AcquisitionItem::class,
            $copy->acquisitionItem
        );
    }

    public function test_acquisition_item_cannot_be_deleted_when_referenced_by_book_copy(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 1,
            'unit_price' => 75000,
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'ACQ-TEST-002',
            'location_id' => $location->id,
        ]);

        DB::table('book_copies')
            ->where('id', $copy->id)
            ->update([
                'acquisition_item_id' => $item->id,
            ]);

        $this->expectException(QueryException::class);

        $item->delete();
    }

    public function test_acquisition_item_has_many_book_copies(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $acquisition = $this->makeAcquisition();

        $item = AcquisitionItem::create([
            'acquisition_id' => $acquisition->id,
            'book_id' => $book->id,
            'quantity' => 2,
            'unit_price' => 75000,
        ]);

        $copy1 = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'ACQ-TEST-003',
            'location_id' => $location->id,
        ]);

        $copy2 = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'ACQ-TEST-004',
            'location_id' => $location->id,
        ]);

        DB::table('book_copies')->where('id', $copy1->id)->update([
            'acquisition_item_id' => $item->id,
        ]);

        DB::table('book_copies')->where('id', $copy2->id)->update([
            'acquisition_item_id' => $item->id,
        ]);

        $item->load('bookCopies');

        $this->assertCount(2, $item->bookCopies);
        $this->assertInstanceOf(BookCopy::class, $item->bookCopies->first());
    }
}
