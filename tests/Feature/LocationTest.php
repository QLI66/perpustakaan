<?php

namespace Tests\Feature;

use App\Models\Location;
use Database\Seeders\LocationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Tabel ─────────────────────────────────────────────────────────────

    public function test_locations_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('locations'),
            'Tabel locations tidak ditemukan'
        );
    }

    public function test_locations_table_has_expected_columns(): void
    {
        foreach (['id', 'code', 'name', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'] as $col) {
            $this->assertTrue(
                Schema::hasColumn('locations', $col),
                "Kolom '{$col}' tidak ditemukan di tabel locations"
            );
        }
    }

    // ─── 2. Unique constraint pada code ───────────────────────────────────────

    public function test_code_must_be_unique(): void
    {
        Location::create(['code' => 'A01', 'name' => 'Rak Matematika', 'is_active' => true, 'sort_order' => 1]);

        $this->expectException(QueryException::class);

        Location::create(['code' => 'A01', 'name' => 'Duplikat', 'is_active' => true, 'sort_order' => 2]);
    }

    // ─── 3. Name wajib ────────────────────────────────────────────────────────

    public function test_name_is_required_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('locations')->insert([
            'code' => 'A01',
            'name' => null,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 4. Description nullable ──────────────────────────────────────────────

    public function test_description_can_be_null(): void
    {
        $location = Location::create([
            'code' => 'A01',
            'name' => 'Rak Matematika',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertNull($location->fresh()->description);
        $this->assertDatabaseHas('locations', ['code' => 'A01', 'description' => null]);
    }

    // ─── 5. is_active default true ────────────────────────────────────────────

    public function test_is_active_defaults_to_true(): void
    {
        $location = Location::create(['code' => 'A01', 'name' => 'Rak Matematika', 'sort_order' => 1]);

        $this->assertTrue($location->fresh()->is_active);
    }

    // ─── 6. sort_order default 0 ──────────────────────────────────────────────

    public function test_sort_order_defaults_to_zero(): void
    {
        $location = Location::create(['code' => 'A01', 'name' => 'Rak Matematika', 'is_active' => true]);

        $this->assertSame(0, $location->fresh()->sort_order);
    }

    // ─── 7. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_locations(): void
    {
        Location::create(['code' => 'A01', 'name' => 'Rak Aktif 1',       'is_active' => true,  'sort_order' => 1]);
        Location::create(['code' => 'A02', 'name' => 'Rak Tidak Aktif',   'is_active' => false, 'sort_order' => 2]);
        Location::create(['code' => 'A03', 'name' => 'Rak Aktif 2',       'is_active' => true,  'sort_order' => 3]);

        $active = Location::active()->get();

        $this->assertSame(2, $active->count());
        $active->each(fn ($l) => $this->assertTrue($l->is_active));
    }

    public function test_inactive_locations_excluded_from_active_scope(): void
    {
        Location::create(['code' => 'A01', 'name' => 'Rak Nonaktif', 'is_active' => false, 'sort_order' => 1]);

        $this->assertSame(0, Location::active()->count());
    }

    // ─── 8. Data seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_all_locations(): void
    {
        $this->seed(LocationSeeder::class);

        $expectedCodes = ['A01', 'A02', 'A03', 'A04', 'A05', 'B01', 'B02', 'B03', 'B04', 'C01', 'C02', 'C03', 'D01'];

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('locations', ['code' => $code]);
        }

        $this->assertSame(13, Location::count());
    }

    public function test_seeder_creates_correct_names(): void
    {
        $this->seed(LocationSeeder::class);

        $this->assertDatabaseHas('locations', ['code' => 'A01', 'name' => 'Rak Matematika']);
        $this->assertDatabaseHas('locations', ['code' => 'B01', 'name' => 'Rak Novel']);
        $this->assertDatabaseHas('locations', ['code' => 'C01', 'name' => 'Rak Referensi']);
        $this->assertDatabaseHas('locations', ['code' => 'D01', 'name' => 'Rak Koleksi Khusus']);
    }

    public function test_seeder_all_locations_active_by_default(): void
    {
        $this->seed(LocationSeeder::class);

        $this->assertSame(13, Location::active()->count());
    }

    public function test_seeder_data_ordered_by_sort_order(): void
    {
        $this->seed(LocationSeeder::class);

        $codes = Location::orderBy('sort_order')->pluck('code')->toArray();

        $this->assertSame(
            ['A01', 'A02', 'A03', 'A04', 'A05', 'B01', 'B02', 'B03', 'B04', 'C01', 'C02', 'C03', 'D01'],
            $codes
        );
    }

    // ─── 9. Idempotency ───────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(LocationSeeder::class);
        $this->seed(LocationSeeder::class);

        // Jumlah harus tetap 13, bukan 26
        $this->assertSame(13, Location::count());
    }

    // ─── 10. Cast is_active menjadi boolean ───────────────────────────────────

    public function test_is_active_is_cast_to_boolean(): void
    {
        $location = Location::create(['code' => 'A01', 'name' => 'Rak Matematika', 'is_active' => true, 'sort_order' => 1]);

        $fresh = $location->fresh();

        $this->assertIsBool($fresh->is_active);
        $this->assertTrue($fresh->is_active);
    }

    public function test_is_active_false_is_cast_to_boolean(): void
    {
        $location = Location::create(['code' => 'A01', 'name' => 'Rak Nonaktif', 'is_active' => false, 'sort_order' => 1]);

        $this->assertIsBool($location->fresh()->is_active);
        $this->assertFalse($location->fresh()->is_active);
    }
}
