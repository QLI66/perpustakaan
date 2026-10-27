<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use Database\Seeders\ClassLevelSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassLevelTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Tabel ─────────────────────────────────────────────────────────────

    public function test_class_levels_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('class_levels'),
            'Tabel class_levels tidak ditemukan'
        );
    }

    public function test_class_levels_table_has_expected_columns(): void
    {
        foreach (['id', 'code', 'name', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('class_levels', $column),
                "Kolom '{$column}' tidak ditemukan di tabel class_levels"
            );
        }
    }

    // ─── 2. Unique constraint pada code ───────────────────────────────────────

    public function test_code_must_be_unique(): void
    {
        ClassLevel::create(['code' => 'X', 'name' => 'Kelas X', 'is_active' => true, 'sort_order' => 1]);

        $this->expectException(QueryException::class);

        ClassLevel::create(['code' => 'X', 'name' => 'Duplikat', 'is_active' => true, 'sort_order' => 2]);
    }

    // ─── 3. Name wajib ────────────────────────────────────────────────────────

    public function test_name_is_required_at_database_level(): void
    {
        $this->expectException(QueryException::class);

        DB::table('class_levels')->insert([
            'code' => 'X',
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
        $level = ClassLevel::create(['code' => 'X', 'name' => 'Kelas X', 'sort_order' => 1]);

        $this->assertTrue($level->fresh()->is_active);
    }

    // ─── 5. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_records(): void
    {
        ClassLevel::create(['code' => 'X',   'name' => 'Kelas X',   'is_active' => true,  'sort_order' => 1]);
        ClassLevel::create(['code' => 'XI',  'name' => 'Kelas XI',  'is_active' => false, 'sort_order' => 2]);
        ClassLevel::create(['code' => 'XII', 'name' => 'Kelas XII', 'is_active' => true,  'sort_order' => 3]);

        $active = ClassLevel::active()->get();

        $this->assertSame(2, $active->count());
        $active->each(fn ($l) => $this->assertTrue($l->is_active));
    }

    // ─── 6. Data seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_x_xi_xii(): void
    {
        $this->seed(ClassLevelSeeder::class);

        foreach (['X', 'XI', 'XII'] as $code) {
            $this->assertDatabaseHas('class_levels', ['code' => $code]);
        }

        $this->assertSame(3, ClassLevel::count());
    }

    public function test_seeder_creates_correct_names(): void
    {
        $this->seed(ClassLevelSeeder::class);

        $this->assertDatabaseHas('class_levels', ['code' => 'X',   'name' => 'Kelas X']);
        $this->assertDatabaseHas('class_levels', ['code' => 'XI',  'name' => 'Kelas XI']);
        $this->assertDatabaseHas('class_levels', ['code' => 'XII', 'name' => 'Kelas XII']);
    }

    public function test_seeder_data_ordered_by_sort_order(): void
    {
        $this->seed(ClassLevelSeeder::class);

        $levels = ClassLevel::orderBy('sort_order')->pluck('code')->toArray();

        $this->assertSame(['X', 'XI', 'XII'], $levels);
    }

    // ─── 7. Idempotency ───────────────────────────────────────────────────────

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ClassLevelSeeder::class);
        $this->seed(ClassLevelSeeder::class);

        $this->assertSame(3, ClassLevel::count());
    }

    // ─── 8. Cast is_active menjadi boolean ────────────────────────────────────

    public function test_is_active_is_cast_to_boolean(): void
    {
        $level = ClassLevel::create(['code' => 'X', 'name' => 'Kelas X', 'is_active' => true, 'sort_order' => 1]);

        $fresh = $level->fresh();

        $this->assertIsBool($fresh->is_active);
        $this->assertTrue($fresh->is_active);
    }

    public function test_is_active_false_is_cast_to_boolean(): void
    {
        $level = ClassLevel::create(['code' => 'X', 'name' => 'Kelas X', 'is_active' => false, 'sort_order' => 1]);

        $this->assertIsBool($level->fresh()->is_active);
        $this->assertFalse($level->fresh()->is_active);
    }
}
