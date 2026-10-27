<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use Database\Seeders\BookCopySeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CollectionTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookCopyTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookAndLocation(): array
    {
        $ct = CollectionType::create(['code' => 'TEST', 'name' => 'Test Type']);
        $book = Book::create(['title' => 'Buku Eksemplar', 'collection_type_id' => $ct->id]);
        $location = Location::create(['code' => 'RAK-01', 'name' => 'Rak 01']);

        return [$book, $location];
    }

    public function test_book_copies_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('book_copies'));
    }

    public function test_book_id_is_required(): void
    {
        $this->expectException(QueryException::class);

        BookCopy::create([
            'barcode' => 'TEST-001',
            'location_id' => 1,
        ]);
    }

    public function test_barcode_is_required(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $this->expectException(QueryException::class);

        BookCopy::create([
            'book_id' => $book->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_barcode_must_be_unique(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->expectException(QueryException::class);
        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);
    }

    public function test_inventory_number_can_be_null(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'inventory_number' => null, 'location_id' => $location->id]);

        $this->assertNull($copy->inventory_number);
    }

    public function test_inventory_number_must_be_unique_when_filled(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'inventory_number' => 'INV-001', 'location_id' => $location->id]);

        $this->expectException(QueryException::class);
        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-002', 'inventory_number' => 'INV-001', 'location_id' => $location->id]);
    }

    public function test_location_id_is_required(): void
    {
        [$book] = $this->makeBookAndLocation();

        $this->expectException(QueryException::class);

        BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'TEST-001',
        ]);
    }

    public function test_acquisition_price_cannot_be_negative(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $this->expectException(QueryException::class);

        BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'TEST-001',
            'location_id' => $location->id,
            'acquisition_price' => -1000,
        ]);
    }

    public function test_default_acquisition_source_is_purchase(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertSame('PURCHASE', $copy->fresh()->acquisition_source);
    }

    public function test_default_condition_is_good(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertSame('GOOD', $copy->fresh()->condition);
    }

    public function test_default_status_is_available(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertSame('AVAILABLE', $copy->fresh()->status);
    }

    public function test_book_copy_belongs_to_book(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertInstanceOf(Book::class, $copy->book);
    }

    public function test_book_copy_belongs_to_location(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertInstanceOf(Location::class, $copy->location);
    }

    public function test_book_has_many_copies(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertCount(1, $book->copies);
        $this->assertInstanceOf(BookCopy::class, $book->copies->first());
    }

    public function test_location_has_many_copies(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->assertCount(1, $location->bookCopies);
        $this->assertInstanceOf(BookCopy::class, $location->bookCopies->first());
    }

    public function test_deleting_book_cascades_copies(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $book->delete();

        $this->assertDatabaseMissing('book_copies', ['id' => $copy->id]);
    }

    public function test_deleting_location_is_restricted(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id]);

        $this->expectException(QueryException::class);
        $location->delete();
    }

    public function test_acquisition_date_casts_to_date(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'TEST-001',
            'location_id' => $location->id,
            'acquisition_date' => '2025-01-01',
        ]);

        $this->assertInstanceOf(Carbon::class, $copy->acquisition_date);
    }

    public function test_acquisition_price_casts_to_decimal(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'TEST-001',
            'location_id' => $location->id,
            'acquisition_price' => 50000.55,
        ]);

        // When retrieved or set, casting makes it string (or float) in Laravel
        // Decimal cast to 2 digits.
        $this->assertEquals('50000.55', $copy->acquisition_price);
    }

    public function test_barcode_accepts_manually_supplied_value(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'MANUAL-12345',
            'location_id' => $location->id,
        ]);

        $this->assertSame('MANUAL-12345', $copy->barcode);
    }

    public function test_status_can_be_lost(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id, 'status' => 'LOST']);
        $this->assertSame('LOST', $copy->status);
    }

    public function test_status_can_be_damaged(): void
    {
        [$book, $location] = $this->makeBookAndLocation();
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id, 'status' => 'DAMAGED']);
        $this->assertSame('DAMAGED', $copy->status);
    }

    public function test_condition_can_be_fair_or_poor(): void
    {
        [$book, $location] = $this->makeBookAndLocation();

        $copy1 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-001', 'location_id' => $location->id, 'condition' => 'FAIR']);
        $this->assertSame('FAIR', $copy1->condition);

        $copy2 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'TEST-002', 'location_id' => $location->id, 'condition' => 'POOR']);
        $this->assertSame('POOR', $copy2->condition);
    }

    public function test_seeder_creates_copies(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(BookSeeder::class);

        $this->seed(BookCopySeeder::class);

        $this->assertGreaterThanOrEqual(24, BookCopy::count());
    }

    public function test_seeder_idempotent(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(BookSeeder::class);

        $this->seed(BookCopySeeder::class);
        $count1 = BookCopy::count();

        $this->seed(BookCopySeeder::class);
        $count2 = BookCopy::count();

        $this->assertSame($count1, $count2);
    }
}
