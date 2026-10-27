<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CollectionType;
use Database\Seeders\CollectionTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CollectionTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabel collection_types harus ada di database.
     */
    public function test_collection_types_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('collection_types'),
            'Tabel collection_types tidak ditemukan'
        );
    }

    /**
     * Field code harus bersifat unique (tidak boleh duplikat).
     */
    public function test_code_must_be_unique(): void
    {
        CollectionType::create([
            'code' => 'TEXTBOOK',
            'name' => 'Buku Pelajaran',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(QueryException::class);

        CollectionType::create([
            'code' => 'TEXTBOOK',
            'name' => 'Duplikat',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    /**
     * Data seeder harus tersedia setelah CollectionTypeSeeder dijalankan.
     */
    public function test_seeder_populates_all_collection_types(): void
    {
        $this->seed(CollectionTypeSeeder::class);

        $expectedCodes = [
            'TEXTBOOK',
            'FICTION',
            'NON_FICTION',
            'REFERENCE',
            'MAGAZINE',
            'NEWSPAPER',
            'COMIC',
            'SPECIAL',
        ];

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('collection_types', ['code' => $code]);
        }

        $this->assertSame(8, CollectionType::count());
    }

    /**
     * Scope active() hanya mengembalikan record dengan is_active = true.
     */
    public function test_active_scope_returns_only_active_records(): void
    {
        $this->seed(CollectionTypeSeeder::class);

        // Nonaktifkan satu record
        CollectionType::where('code', 'COMIC')->update(['is_active' => false]);

        $active = CollectionType::active()->count();

        $this->assertSame(7, $active);
    }

    /**
     * Test that factory can create models successfully.
     */
    public function test_factory_can_create_collection_type(): void
    {
        $collectionType = CollectionType::factory()->create();

        $this->assertDatabaseHas('collection_types', [
            'id' => $collectionType->id,
            'code' => $collectionType->code,
        ]);
        $this->assertTrue($collectionType->is_active);
    }

    /**
     * Test relationship to books and restrict on delete.
     */
    public function test_cannot_delete_collection_type_if_used_by_book(): void
    {
        $collectionType = CollectionType::factory()->create();

        $book = Book::create([
            'isbn' => '978-0000000000',
            'title' => 'Test Book',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'id',
        ]);

        $this->assertTrue($collectionType->books->contains($book));

        $this->expectException(QueryException::class);

        $collectionType->delete();
    }
}
