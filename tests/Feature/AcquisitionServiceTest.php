<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\CollectionType;
use App\Models\User;
use App\Services\AcquisitionService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcquisitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcquisitionService $acquisitionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acquisitionService = app(AcquisitionService::class);
    }

    private function makeBook(
        string $title = 'Buku Acquisition Test',
        string $code = 'TEST-ACQ'
    ): Book {
        $collectionType = CollectionType::create([
            'code' => $code,
            'name' => 'Test Collection',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    public function test_acquisition_service_can_create_acquisition_with_items(): void
    {
        $book1 = $this->makeBook('Matematika Test', 'MAT-ACQ');
        $book2 = $this->makeBook('Fisika Test', 'FIS-ACQ');

        $acquisition = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            'PO-001',
            [
                [
                    'book_id' => $book1->id,
                    'quantity' => 2,
                    'unit_price' => 75000,
                ],
                [
                    'book_id' => $book2->id,
                    'quantity' => 3,
                    'unit_price' => 50000,
                ],
            ]
        );

        $this->assertDatabaseHas('acquisitions', [
            'id' => $acquisition->id,
            'acquisition_number' => 'ACQ-2026-000001',
            'source_type' => 'PURCHASE',
            'source_name' => 'Supplier Test',
            'reference_number' => 'PO-001',
        ]);

        $this->assertSame(2, $acquisition->items->count());
        $this->assertSame('ACQ-2026-000001', $acquisition->acquisition_number);

        $this->assertDatabaseCount('acquisition_items', 2);
    }

    public function test_acquisition_number_is_generated_per_year(): void
    {
        $book1 = $this->makeBook('Buku Satu', 'NUM-ACQ-1');
        $book2 = $this->makeBook('Buku Dua', 'NUM-ACQ-2');

        $first = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier A',
            null,
            [
                [
                    'book_id' => $book1->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ]
        );

        $second = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-22'),
            'PURCHASE',
            'Supplier B',
            null,
            [
                [
                    'book_id' => $book2->id,
                    'quantity' => 1,
                    'unit_price' => 20000,
                ],
            ]
        );

        $this->assertSame('ACQ-2026-000001', $first->acquisition_number);
        $this->assertSame('ACQ-2026-000002', $second->acquisition_number);
    }

    public function test_acquisition_number_resets_for_different_year(): void
    {
        $book1 = $this->makeBook('Buku 2026', 'YEAR-ACQ-1');
        $book2 = $this->makeBook('Buku 2027', 'YEAR-ACQ-2');

        $first = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-12-31'),
            'PURCHASE',
            'Supplier',
            null,
            [
                [
                    'book_id' => $book1->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ]
        );

        $second = $this->acquisitionService->createAcquisition(
            Carbon::parse('2027-01-01'),
            'PURCHASE',
            'Supplier',
            null,
            [
                [
                    'book_id' => $book2->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ]
        );

        $this->assertSame('ACQ-2026-000001', $first->acquisition_number);
        $this->assertSame('ACQ-2027-000001', $second->acquisition_number);
    }

    public function test_total_amount_is_calculated_by_service(): void
    {
        $book = $this->makeBook();

        $acquisition = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 2,
                    'unit_price' => 75000,
                ],
            ]
        );

        $this->assertEquals('150000.00', $acquisition->fresh()->total_amount);
    }

    public function test_total_amount_is_null_when_all_unit_prices_are_null(): void
    {
        $book = $this->makeBook();

        $acquisition = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'DONATION',
            'Donor Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 5,
                    'unit_price' => null,
                ],
            ]
        );

        $this->assertNull($acquisition->fresh()->total_amount);
    }

    public function test_invalid_source_type_is_rejected(): void
    {
        $book = $this->makeBook();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Acquisition source type must be PURCHASE, DONATION, GRANT, GIFT, or OTHER.'
        );

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'INVALID',
            'Unknown',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
            ]
        );
    }

    public function test_empty_items_are_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('At least one acquisition item is required.');

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            []
        );
    }

    public function test_duplicate_book_is_rejected(): void
    {
        $book = $this->makeBook();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'The same book cannot be added more than once to one acquisition.'
        );

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 1,
                    'unit_price' => 10000,
                ],
                [
                    'book_id' => $book->id,
                    'quantity' => 2,
                    'unit_price' => 20000,
                ],
            ]
        );
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $book = $this->makeBook();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Acquisition item quantity must be greater than zero.'
        );

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 0,
                    'unit_price' => 10000,
                ],
            ]
        );
    }

    public function test_negative_unit_price_is_rejected(): void
    {
        $book = $this->makeBook();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Acquisition item unit price cannot be negative.'
        );

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 1,
                    'unit_price' => -100,
                ],
            ]
        );
    }

    public function test_invalid_book_rolls_back_entire_transaction(): void
    {
        $validBook = $this->makeBook();

        $this->assertDatabaseCount('acquisitions', 0);
        $this->assertDatabaseCount('acquisition_items', 0);

        $this->expectException(QueryException::class);

        $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            'ROLLBACK-001',
            [
                [
                    'book_id' => $validBook->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
                [
                    'book_id' => 999999999,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ]
        );

        $this->assertDatabaseCount('acquisitions', 0);
        $this->assertDatabaseCount('acquisition_items', 0);
    }

    public function test_created_by_is_stored_when_provided(): void
    {
        $book = $this->makeBook();

        $user = User::factory()->create();

        $acquisition = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'PURCHASE',
            'Supplier Test',
            null,
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 1,
                    'unit_price' => 50000,
                ],
            ],
            $user->id
        );

        $this->assertSame($user->id, $acquisition->fresh()->created_by);
        $this->assertSame($user->id, $acquisition->creator->id);
    }

    public function test_notes_are_stored_in_acquisition_and_item(): void
    {
        $book = $this->makeBook();

        $acquisition = $this->acquisitionService->createAcquisition(
            Carbon::parse('2026-09-21'),
            'DONATION',
            'Donor Test',
            'DON-001',
            [
                [
                    'book_id' => $book->id,
                    'quantity' => 3,
                    'unit_price' => null,
                    'notes' => 'Buku sumbangan',
                ],
            ],
            null,
            'Donasi perpustakaan semester ganjil'
        );

        $this->assertSame(
            'Donasi perpustakaan semester ganjil',
            $acquisition->fresh()->notes
        );

        $this->assertSame(
            'Buku sumbangan',
            $acquisition->items->first()->notes
        );
    }

    public function test_acquisition_source_types_allowed_by_database_are_preserved(): void
    {
        $sourceTypes = [
            'PURCHASE',
            'DONATION',
            'GRANT',
            'GIFT',
            'OTHER',
        ];

        foreach ($sourceTypes as $index => $sourceType) {
            $book = $this->makeBook(
                "Buku Source {$sourceType}",
                "SRC-{$index}"
            );

            $acquisition = $this->acquisitionService->createAcquisition(
                Carbon::parse('2026-09-21'),
                $sourceType,
                'Source Test',
                null,
                [
                    [
                        'book_id' => $book->id,
                        'quantity' => 1,
                        'unit_price' => 10000,
                    ],
                ]
            );

            $this->assertSame(
                $sourceType,
                $acquisition->fresh()->source_type
            );
        }
    }

    public function test_acquisition_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('acquisitions'));
        $this->assertTrue(Schema::hasTable('acquisition_items'));
    }
}
