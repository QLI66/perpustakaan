<?php

namespace Tests\Feature;

use App\Models\Author;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Tabel dan kolom ───────────────────────────────────────────────────

    public function test_authors_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('authors'),
            'Tabel authors tidak ditemukan'
        );
    }

    public function test_authors_table_has_expected_columns(): void
    {
        $columns = ['id', 'name', 'biography', 'birth_date', 'death_date', 'nationality', 'is_active', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('authors', $column),
                "Kolom '{$column}' tidak ditemukan di tabel authors"
            );
        }
    }

    // ─── 2. Field name wajib isi ──────────────────────────────────────────────

    public function test_name_is_required_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('authors')->insert([
            'name' => null,   // NOT NULL violation
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 3. Field opsional bisa null ──────────────────────────────────────────

    public function test_optional_fields_can_be_null(): void
    {
        $author = Author::create([
            'name' => 'Penulis Tanpa Detail',
            'biography' => null,
            'birth_date' => null,
            'death_date' => null,
            'nationality' => null,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('authors', ['name' => 'Penulis Tanpa Detail']);
        $this->assertNull($author->biography);
        $this->assertNull($author->birth_date);
        $this->assertNull($author->death_date);
        $this->assertNull($author->nationality);
    }

    // ─── 4. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_authors(): void
    {
        Author::create(['name' => 'Penulis Aktif',       'is_active' => true]);
        Author::create(['name' => 'Penulis Tidak Aktif', 'is_active' => false]);
        Author::create(['name' => 'Penulis Aktif 2',     'is_active' => true]);

        $active = Author::active()->get();

        $this->assertSame(2, $active->count());
        $active->each(fn ($a) => $this->assertTrue($a->is_active));
    }

    public function test_inactive_authors_are_excluded_from_active_scope(): void
    {
        Author::create(['name' => 'P1', 'is_active' => false]);
        Author::create(['name' => 'P2', 'is_active' => false]);

        $this->assertSame(0, Author::active()->count());
    }

    // ─── 5. Cast tanggal ──────────────────────────────────────────────────────

    public function test_date_fields_are_cast_correctly(): void
    {
        $author = Author::create([
            'name' => 'Chairil Anwar',
            'birth_date' => '1922-07-26',
            'death_date' => '1949-04-28',
            'is_active' => true,
        ]);

        $fresh = $author->fresh();

        $this->assertInstanceOf(Carbon::class, $fresh->birth_date);
        $this->assertInstanceOf(Carbon::class, $fresh->death_date);
        $this->assertSame('1922-07-26', $fresh->birth_date->format('Y-m-d'));
        $this->assertSame('1949-04-28', $fresh->death_date->format('Y-m-d'));
    }

    public function test_null_date_fields_remain_null_after_cast(): void
    {
        $author = Author::create([
            'name' => 'Penulis Masih Hidup',
            'birth_date' => '1980-01-01',
            'death_date' => null,
            'is_active' => true,
        ]);

        $this->assertNull($author->fresh()->death_date);
    }

    // ─── 6. Data seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_expected_authors(): void
    {
        $this->seed(AuthorSeeder::class);

        $expected = [
            'Andrea Hirata',
            'Tere Liye',
            'Dee Lestari',
            'Pramoedya Ananta Toer',
            'Ahmad Fuadi',
            'NH Dini',
            'Chairil Anwar',
            'Buya Hamka',
            'Ki Hadjar Dewantara',
        ];

        foreach ($expected as $name) {
            $this->assertDatabaseHas('authors', ['name' => $name]);
        }

        $this->assertSame(9, Author::count());
    }

    public function test_seeder_authors_have_correct_data(): void
    {
        $this->seed(AuthorSeeder::class);

        // Spot-check: Pramoedya memiliki birth_date dan death_date
        $pram = Author::where('name', 'Pramoedya Ananta Toer')->first();
        $this->assertNotNull($pram);
        $this->assertSame('1925-02-06', $pram->birth_date->format('Y-m-d'));
        $this->assertSame('2006-04-30', $pram->death_date->format('Y-m-d'));
        $this->assertSame('Indonesia', $pram->nationality);

        // Spot-check: Tere Liye masih hidup (death_date null)
        $tere = Author::where('name', 'Tere Liye')->first();
        $this->assertNull($tere->death_date);
    }

    // ─── 7. Idempotency ───────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AuthorSeeder::class);
        $this->seed(AuthorSeeder::class); // jalankan dua kali

        // Jumlah harus tetap 9, bukan 18
        $this->assertSame(9, Author::count());
    }

    // ─── 8. DatabaseSeeder menjalankan AuthorSeeder ───────────────────────────

    public function test_database_seeder_runs_author_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Minimal 9 authors dari AuthorSeeder
        $this->assertGreaterThanOrEqual(9, Author::count());

        $this->assertDatabaseHas('authors', ['name' => 'Andrea Hirata']);
        $this->assertDatabaseHas('authors', ['name' => 'Ki Hadjar Dewantara']);
    }
}
