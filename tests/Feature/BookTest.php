<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CollectionType;
use App\Models\Publisher;
use Database\Seeders\BookSeeder;
use Database\Seeders\CollectionTypeSeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helper: buat data pendukung ──────────────────────────────────────────

    private function makeCollectionType(array $overrides = []): CollectionType
    {
        static $counter = 0;
        $counter++;

        return CollectionType::create(array_merge([
            'code' => 'CT'.$counter,
            'name' => 'Tipe '.$counter,
            'is_active' => true,
            'sort_order' => $counter,
        ], $overrides));
    }

    private function makePublisher(array $overrides = []): Publisher
    {
        static $counter = 0;
        $counter++;

        return Publisher::create(array_merge([
            'name' => 'Penerbit '.$counter,
            'is_active' => true,
        ], $overrides));
    }

    private function makeBook(array $overrides = []): Book
    {
        $ct = $this->makeCollectionType();

        return Book::create(array_merge([
            'title' => 'Buku Test',
            'language' => 'id',
            'collection_type_id' => $ct->id,
        ], $overrides));
    }

    // ─── 1. Tabel ─────────────────────────────────────────────────────────────

    public function test_books_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('books'), 'Tabel books tidak ditemukan');
    }

    public function test_books_table_has_expected_columns(): void
    {
        $columns = [
            'id', 'isbn', 'title', 'subtitle', 'description',
            'publication_year', 'edition', 'language',
            'collection_type_id', 'publisher_id', 'cover',
            'created_at', 'updated_at',
        ];

        foreach ($columns as $col) {
            $this->assertTrue(Schema::hasColumn('books', $col), "Kolom '{$col}' tidak ditemukan");
        }
    }

    // ─── 2. Title wajib ───────────────────────────────────────────────────────

    public function test_title_is_required_at_database_level(): void
    {
        $ct = $this->makeCollectionType();

        $this->expectException(QueryException::class);

        DB::table('books')->insert([
            'title' => null,
            'language' => 'id',
            'collection_type_id' => $ct->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 3. ISBN nullable ─────────────────────────────────────────────────────

    public function test_isbn_can_be_null(): void
    {
        $book = $this->makeBook(['isbn' => null]);

        $this->assertNull($book->fresh()->isbn);
    }

    // ─── 4. ISBN unique ketika diisi ──────────────────────────────────────────

    public function test_isbn_must_be_unique_when_provided(): void
    {
        $ct = $this->makeCollectionType();

        Book::create(['isbn' => '9786021276983', 'title' => 'Buku A', 'language' => 'id', 'collection_type_id' => $ct->id]);

        $this->expectException(QueryException::class);

        Book::create(['isbn' => '9786021276983', 'title' => 'Buku B', 'language' => 'id', 'collection_type_id' => $ct->id]);
    }

    public function test_multiple_books_can_have_null_isbn(): void
    {
        $ct = $this->makeCollectionType();

        Book::create(['isbn' => null, 'title' => 'Buku Tanpa ISBN 1', 'language' => 'id', 'collection_type_id' => $ct->id]);
        Book::create(['isbn' => null, 'title' => 'Buku Tanpa ISBN 2', 'language' => 'id', 'collection_type_id' => $ct->id]);

        $this->assertSame(2, Book::whereNull('isbn')->count());
    }

    // ─── 5. collection_type_id wajib ─────────────────────────────────────────

    public function test_collection_type_id_is_required(): void
    {
        $this->expectException(QueryException::class);

        DB::table('books')->insert([
            'title' => 'Buku Tanpa Tipe',
            'language' => 'id',
            'collection_type_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 6. publisher_id nullable ─────────────────────────────────────────────

    public function test_publisher_id_can_be_null(): void
    {
        $book = $this->makeBook(['publisher_id' => null]);

        $this->assertNull($book->fresh()->publisher_id);
        $this->assertNull($book->fresh()->publisher);
    }

    // ─── 7. Default language = 'id' ───────────────────────────────────────────

    public function test_language_defaults_to_id(): void
    {
        $ct = $this->makeCollectionType();
        $book = Book::create(['title' => 'Buku Default Lang', 'collection_type_id' => $ct->id]);

        $this->assertSame('id', $book->fresh()->language);
    }

    // ─── 8. Relationship: book belongs to collection type ─────────────────────

    public function test_book_belongs_to_collection_type(): void
    {
        $ct = $this->makeCollectionType(['code' => 'TEXTBOOK_X', 'name' => 'Buku Pelajaran']);
        $book = Book::create(['title' => 'Matematika', 'language' => 'id', 'collection_type_id' => $ct->id]);

        $this->assertInstanceOf(CollectionType::class, $book->collectionType);
        $this->assertSame($ct->id, $book->collectionType->id);
        $this->assertSame('TEXTBOOK_X', $book->collectionType->code);
    }

    // ─── 9. Relationship: book belongs to publisher ───────────────────────────

    public function test_book_belongs_to_publisher(): void
    {
        $ct = $this->makeCollectionType();
        $publisher = $this->makePublisher(['name' => 'Penerbit Tes']);
        $book = Book::create([
            'title' => 'Buku dengan Penerbit',
            'language' => 'id',
            'collection_type_id' => $ct->id,
            'publisher_id' => $publisher->id,
        ]);

        $this->assertInstanceOf(Publisher::class, $book->publisher);
        $this->assertSame($publisher->id, $book->publisher->id);
        $this->assertSame('Penerbit Tes', $book->publisher->name);
    }

    public function test_book_has_authors_relationship(): void
    {
        $book = $this->makeBook();
        $this->assertInstanceOf(BelongsToMany::class, $book->authors());
    }

    public function test_book_has_categories_relationship(): void
    {
        $book = $this->makeBook();
        $this->assertInstanceOf(BelongsToMany::class, $book->categories());
    }

    public function test_book_has_targets_relationship(): void
    {
        $book = $this->makeBook();
        $this->assertInstanceOf(HasMany::class, $book->targets());
    }

    public function test_book_has_copies_relationship(): void
    {
        $book = $this->makeBook();
        $this->assertInstanceOf(HasMany::class, $book->copies());
    }

    // ─── 10. Data seeder ─────────────────────────────────────────────────────

    public function test_seeder_creates_at_least_10_books(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(BookSeeder::class);

        $this->assertGreaterThanOrEqual(10, Book::count());
    }

    public function test_seeder_creates_expected_titles(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(BookSeeder::class);

        $expectedTitles = [
            'Dasar-Dasar Matematika',
            'Fisika Dasar',
            'Biologi untuk SMA',
            'Kimia Dasar',
            'Ekonomi untuk SMA',
            'Sosiologi untuk SMA',
            'Geografi untuk SMA',
            'Sejarah Indonesia',
            'Laskar Pelangi',
            'Negeri 5 Menara',
        ];

        foreach ($expectedTitles as $title) {
            $this->assertDatabaseHas('books', ['title' => $title]);
        }
    }

    public function test_seeder_books_have_valid_collection_type(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(BookSeeder::class);

        // Setiap buku harus memiliki collection_type yang valid
        $orphans = Book::whereDoesntHave('collectionType')->count();
        $this->assertSame(0, $orphans);
    }

    // ─── 11. Idempotency ─────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(BookSeeder::class);

        $countAfterFirst = Book::count();

        $this->seed(BookSeeder::class);

        $this->assertSame($countAfterFirst, Book::count());
    }

    // ─── 12. FK collection_type_id restrict ──────────────────────────────────

    public function test_cannot_delete_collection_type_with_books(): void
    {
        $ct = $this->makeCollectionType();
        Book::create(['title' => 'Buku Terkait', 'language' => 'id', 'collection_type_id' => $ct->id]);

        $this->expectException(QueryException::class);

        $ct->delete(); // Harus gagal karena ada buku yang menggunakan tipe ini
    }

    // ─── 13. publisher_id nullable & nullOnDelete ────────────────────────────

    public function test_publisher_id_is_set_to_null_when_publisher_deleted(): void
    {
        $ct = $this->makeCollectionType();
        $publisher = $this->makePublisher();
        $book = Book::create([
            'title' => 'Buku dengan Penerbit',
            'language' => 'id',
            'collection_type_id' => $ct->id,
            'publisher_id' => $publisher->id,
        ]);

        $this->assertSame($publisher->id, $book->fresh()->publisher_id);

        $publisher->delete();

        // publisher_id harus menjadi NULL setelah penerbit dihapus
        $this->assertNull($book->fresh()->publisher_id);
    }
}
