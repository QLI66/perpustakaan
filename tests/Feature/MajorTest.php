<?php

namespace Tests\Feature;

use App\Models\Major;
use Database\Seeders\MajorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MajorTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Tabel ─────────────────────────────────────────────────────────────

    public function test_majors_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('majors'),
            'Tabel majors tidak ditemukan'
        );
    }

    public function test_majors_table_has_expected_columns(): void
    {
        foreach (['id', 'code', 'name', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('majors', $column),
                "Kolom '{$column}' tidak ditemukan di tabel majors"
            );
        }
    }

    // ─── 2. Unique constraint pada code ───────────────────────────────────────

    public function test_code_must_be_unique(): void
    {
        Major::create(['code' => 'IPA', 'name' => 'IPA', 'is_active' => true, 'sort_order' => 1]);

        $this->expectException(QueryException::class);

        Major::create(['code' => 'IPA', 'name' => 'Duplikat', 'is_active' => true, 'sort_order' => 2]);
    }

    // ─── 3. Name wajib ────────────────────────────────────────────────────────

    public function test_name_is_required_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('majors')->insert([
            'code' => 'IPA',
            'name' => null,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── 4. Default is_active = true ──────────────────────────────────────────

    public function test_is_active_defaults_to_true(): void
    {
        $major = Major::create(['code' => 'IPA', 'name' => 'IPA', 'sort_order' => 1]);

        $this->assertTrue($major->fresh()->is_active);
    }

    // ─── 5. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_records(): void
    {
        Major::create(['code' => 'IPA',  'name' => 'IPA',   'is_active' => true,  'sort_order' => 1]);
        Major::create(['code' => 'IPS1', 'name' => 'IPS 1', 'is_active' => false, 'sort_order' => 2]);
        Major::create(['code' => 'IPS2', 'name' => 'IPS 2', 'is_active' => true,  'sort_order' => 3]);

        $active = Major::active()->get();

        $this->assertSame(2, $active->count());
        $active->each(fn ($m) => $this->assertTrue($m->is_active));
    }

    // ─── 6. Data seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_ipa_ips1_ips2(): void
    {
        $this->seed(MajorSeeder::class);

        $this->assertDatabaseHas('majors', ['code' => 'IPA',  'name' => 'IPA']);
        $this->assertDatabaseHas('majors', ['code' => 'IPS1', 'name' => 'IPS 1']);
        $this->assertDatabaseHas('majors', ['code' => 'IPS2', 'name' => 'IPS 2']);

        $this->assertSame(3, Major::count());
    }

    public function test_seeder_data_ordered_by_sort_order(): void
    {
        $this->seed(MajorSeeder::class);

        $codes = Major::orderBy('sort_order')->pluck('code')->toArray();

        $this->assertSame(['IPA', 'IPS1', 'IPS2'], $codes);
    }

    public function test_seeder_all_majors_are_active_by_default(): void
    {
        $this->seed(MajorSeeder::class);

        $this->assertSame(3, Major::active()->count());
    }

    // ─── 7. Idempotency ───────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MajorSeeder::class);
        $this->seed(MajorSeeder::class);

        $this->assertSame(3, Major::count());
    }

    // ─── 8. Cast is_active menjadi boolean ────────────────────────────────────

    public function test_is_active_is_cast_to_boolean(): void
    {
        $major = Major::create(['code' => 'IPA', 'name' => 'IPA', 'is_active' => true, 'sort_order' => 1]);

        $this->assertIsBool($major->fresh()->is_active);
        $this->assertTrue($major->fresh()->is_active);
    }

    public function test_is_active_false_is_cast_to_boolean(): void
    {
        $major = Major::create(['code' => 'IPS1', 'name' => 'IPS 1', 'is_active' => false, 'sort_order' => 2]);

        $this->assertIsBool($major->fresh()->is_active);
        $this->assertFalse($major->fresh()->is_active);
    }
}
