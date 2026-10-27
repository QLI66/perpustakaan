<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookTarget;
use App\Models\ClassLevel;
use App\Models\CollectionType;
use App\Models\Major;
use Database\Seeders\BookSeeder;
use Database\Seeders\ClassLevelSeeder;
use Database\Seeders\CollectionTypeSeeder;
use Database\Seeders\MajorSeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookTargetTest extends TestCase
{
    use RefreshDatabase;

    private function makeBook(): Book
    {
        $ct = CollectionType::create(['code' => 'TEST', 'name' => 'Test']);

        return Book::create(['title' => 'Buku Target Tes', 'collection_type_id' => $ct->id]);
    }

    private function makeClassLevel(string $code): ClassLevel
    {
        return ClassLevel::create(['code' => $code, 'name' => 'Kelas '.$code]);
    }

    private function makeMajor(string $code): Major
    {
        return Major::create(['code' => $code, 'name' => 'Jurusan '.$code]);
    }

    // ─── 1. Table & Fields ───────────────────────────────────────────────────

    public function test_book_targets_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('book_targets'));
    }

    public function test_book_id_is_required(): void
    {
        $this->expectException(QueryException::class);

        DB::table('book_targets')->insert([
            'book_id' => null,
            'class_level_id' => null,
            'major_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_class_level_id_and_major_id_can_be_null(): void
    {
        $book = $this->makeBook();

        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => null]);

        $this->assertNull($target->class_level_id);
        $this->assertNull($target->major_id);
    }

    // ─── 2. Creation ──────────────────────────────────────────────────────────

    public function test_exact_target_can_be_created(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');
        $major = $this->makeMajor('IPA');

        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => $major->id]);

        $this->assertSame($class->id, $target->class_level_id);
        $this->assertSame($major->id, $target->major_id);
    }

    public function test_class_null_major_can_be_created(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');

        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => null]);

        $this->assertSame($class->id, $target->class_level_id);
    }

    public function test_null_class_major_can_be_created(): void
    {
        $book = $this->makeBook();
        $major = $this->makeMajor('IPA');

        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => $major->id]);

        $this->assertSame($major->id, $target->major_id);
    }

    public function test_global_target_can_be_created(): void
    {
        $book = $this->makeBook();
        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => null]);

        $this->assertDatabaseHas('book_targets', ['id' => $target->id]);
    }

    // ─── 3. Uniqueness ────────────────────────────────────────────────────────

    public function test_duplicate_exact_target_rejected(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');
        $major = $this->makeMajor('IPA');

        BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => $major->id]);

        $this->expectException(QueryException::class);
        BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => $major->id]);
    }

    public function test_duplicate_class_null_major_rejected(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');

        BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => null]);

        $this->expectException(QueryException::class);
        BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id, 'major_id' => null]);
    }

    public function test_duplicate_null_class_major_rejected(): void
    {
        $book = $this->makeBook();
        $major = $this->makeMajor('IPA');

        BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => $major->id]);

        $this->expectException(QueryException::class);
        BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => $major->id]);
    }

    public function test_duplicate_global_target_rejected(): void
    {
        $book = $this->makeBook();

        BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => null]);

        $this->expectException(QueryException::class);
        BookTarget::create(['book_id' => $book->id, 'class_level_id' => null, 'major_id' => null]);
    }

    // ─── 4. Relationships (Model BookTarget) ──────────────────────────────────

    public function test_book_relationship_works(): void
    {
        $book = $this->makeBook();
        $target = BookTarget::create(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $target->book);
        $this->assertSame($book->id, $target->book->id);
    }

    public function test_class_level_relationship_works(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');
        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id]);

        $this->assertInstanceOf(ClassLevel::class, $target->classLevel);
    }

    public function test_major_relationship_works(): void
    {
        $book = $this->makeBook();
        $major = $this->makeMajor('IPA');
        $target = BookTarget::create(['book_id' => $book->id, 'major_id' => $major->id]);

        $this->assertInstanceOf(Major::class, $target->major);
    }

    // ─── 5. Constraints / Cascade ─────────────────────────────────────────────

    public function test_deleting_book_removes_targets(): void
    {
        $book = $this->makeBook();
        $target = BookTarget::create(['book_id' => $book->id]);

        $book->delete();

        $this->assertDatabaseMissing('book_targets', ['id' => $target->id]);
    }

    public function test_deleting_class_level_nulls_class_level_id(): void
    {
        $book = $this->makeBook();
        $class = $this->makeClassLevel('X');
        $target = BookTarget::create(['book_id' => $book->id, 'class_level_id' => $class->id]);

        $class->delete();

        $this->assertNull($target->fresh()->class_level_id);
    }

    public function test_deleting_major_nulls_major_id(): void
    {
        $book = $this->makeBook();
        $major = $this->makeMajor('IPA');
        $target = BookTarget::create(['book_id' => $book->id, 'major_id' => $major->id]);

        $major->delete();

        $this->assertNull($target->fresh()->major_id);
    }

    // ─── 6. Relationship Book->targets() ──────────────────────────────────────

    public function test_book_targets_relationship_works(): void
    {
        $book = $this->makeBook();
        BookTarget::create(['book_id' => $book->id]);

        $this->assertCount(1, $book->targets);
        $this->assertInstanceOf(BookTarget::class, $book->targets->first());
    }

    // ─── 7. Seeder ────────────────────────────────────────────────────────────

    public function test_seeder_creates_expected_targets(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(BookSeeder::class);

        // Seeder menghasilkan banyak target dari mapping 12 buku
        $this->assertGreaterThanOrEqual(10, BookTarget::count());

        // Cek spesifik Laskar Pelangi -> global target
        $laskar = Book::where('title', 'Laskar Pelangi')->first();
        $this->assertNotNull($laskar);

        $globalTarget = $laskar->targets()->whereNull('class_level_id')->whereNull('major_id')->first();
        $this->assertNotNull($globalTarget);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);
        $this->seed(PublisherSeeder::class);

        $this->seed(BookSeeder::class);
        $count1 = BookTarget::count();

        $this->seed(BookSeeder::class);
        $count2 = BookTarget::count();

        $this->assertSame($count1, $count2);
    }
}
