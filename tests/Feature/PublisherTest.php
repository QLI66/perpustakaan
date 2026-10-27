<?php

namespace Tests\Feature;

use App\Models\Publisher;
use Database\Seeders\PublisherSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublisherTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Struktur tabel ────────────────────────────────────────────────────

    public function test_publishers_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('publishers'),
            'Tabel publishers tidak ditemukan'
        );
    }

    public function test_publishers_table_has_expected_columns(): void
    {
        $columns = ['id', 'name', 'address', 'phone', 'email', 'website', 'is_active', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('publishers', $column),
                "Kolom '{$column}' tidak ditemukan di tabel publishers"
            );
        }
    }

    // ─── 2. Validasi field name wajib isi ─────────────────────────────────────

    public function test_name_is_required(): void
    {
        $this->expectException(QueryException::class);

        // name sengaja tidak diberikan → NOT NULL violation di database
        Publisher::create([
            'name' => '',   // string kosong bukan NULL, tapi kita cek via DB
            'is_active' => true,
        ]);

        // Cara lebih ketat: paksa NULL ke kolom name
        Publisher::forceCreate(['is_active' => true]);
    }

    public function test_name_cannot_be_null_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        // Lewati mass assignment — langsung insert via query builder
        DB::table('publishers')->insert([
            'name' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 3. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_publishers(): void
    {
        Publisher::create(['name' => 'Penerbit Aktif',       'is_active' => true]);
        Publisher::create(['name' => 'Penerbit Tidak Aktif', 'is_active' => false]);

        $active = Publisher::active()->get();

        $this->assertSame(1, $active->count());
        $this->assertSame('Penerbit Aktif', $active->first()->name);
    }

    public function test_inactive_publishers_are_excluded_from_active_scope(): void
    {
        Publisher::create(['name' => 'P1', 'is_active' => false]);
        Publisher::create(['name' => 'P2', 'is_active' => false]);

        $this->assertSame(0, Publisher::active()->count());
    }

    // ─── 4. Data Seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_expected_publishers(): void
    {
        $this->seed(PublisherSeeder::class);

        $expected = [
            'Erlangga',
            'Gramedia Pustaka Utama',
            'Yudhistira',
            'Mizan',
            'Balai Pustaka',
            'Kemendikbud',
            'Intan Pariwara',
            'Tiga Serangkai',
        ];

        foreach ($expected as $name) {
            $this->assertDatabaseHas('publishers', ['name' => $name]);
        }

        $this->assertSame(8, Publisher::count());
    }

    public function test_seeder_all_publishers_are_active_by_default(): void
    {
        $this->seed(PublisherSeeder::class);

        $this->assertSame(8, Publisher::active()->count());
    }

    // ─── 5. Idempotency ───────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(PublisherSeeder::class);
        $this->seed(PublisherSeeder::class); // jalankan dua kali

        // Jumlah harus tetap 8, bukan 16
        $this->assertSame(8, Publisher::count());
    }
}
