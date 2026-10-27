<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    // ─── 1. Struktur tabel ────────────────────────────────────────────────────

    public function test_categories_table_exists(): void
    {
        $this->assertTrue(
            Schema::hasTable('categories'),
            'Tabel categories tidak ditemukan'
        );
    }

    public function test_categories_table_has_expected_columns(): void
    {
        foreach (['id', 'parent_id', 'code', 'name', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('categories', $column),
                "Kolom '{$column}' tidak ada di tabel categories"
            );
        }
    }

    // ─── 2. Constraint unik pada code ─────────────────────────────────────────

    public function test_code_must_be_unique(): void
    {
        Category::create([
            'code' => 'PELAJARAN',
            'name' => 'Pelajaran',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->expectException(QueryException::class);

        Category::create([
            'code' => 'PELAJARAN',
            'name' => 'Duplikat',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    // ─── 3. Hierarki parent / children ────────────────────────────────────────

    public function test_root_category_can_have_children(): void
    {
        $root = Category::create([
            'code' => 'ROOT',
            'name' => 'Root',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Category::create(['code' => 'ROOT_CHILD_A', 'name' => 'Child A', 'parent_id' => $root->id, 'is_active' => true, 'sort_order' => 1]);
        Category::create(['code' => 'ROOT_CHILD_B', 'name' => 'Child B', 'parent_id' => $root->id, 'is_active' => true, 'sort_order' => 2]);

        $this->assertSame(2, $root->children()->count());
    }

    public function test_child_belongs_to_correct_parent(): void
    {
        $root = Category::create([
            'code' => 'FIKSI',
            'name' => 'Fiksi',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $child = Category::create([
            'code' => 'FIKSI_NOVEL',
            'name' => 'Novel',
            'parent_id' => $root->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame($root->id, $child->parent->id);
        $this->assertSame('FIKSI', $child->parent->code);
    }

    public function test_root_category_has_null_parent(): void
    {
        $root = Category::create([
            'code' => 'REFERENSI',
            'name' => 'Referensi',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertNull($root->parent_id);
        $this->assertNull($root->parent);
    }

    // ─── 4. Scope active ──────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_categories(): void
    {
        Category::create(['code' => 'CAT_ACTIVE',   'name' => 'Aktif',      'is_active' => true,  'sort_order' => 1]);
        Category::create(['code' => 'CAT_INACTIVE', 'name' => 'Tidak Aktif', 'is_active' => false, 'sort_order' => 2]);

        $activeCount = Category::active()->count();

        $this->assertSame(1, $activeCount);
        $this->assertDatabaseMissing('categories', ['code' => 'CAT_INACTIVE', 'is_active' => true]);
    }

    // ─── 5. Data Seeder ───────────────────────────────────────────────────────

    public function test_seeder_creates_expected_root_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $expectedRoots = ['PELAJARAN', 'FIKSI', 'NON_FIKSI', 'REFERENSI', 'KOLEKSI_KHUSUS'];

        foreach ($expectedRoots as $code) {
            $this->assertDatabaseHas('categories', ['code' => $code, 'parent_id' => null]);
        }
    }

    public function test_seeder_creates_expected_hierarchy(): void
    {
        $this->seed(CategorySeeder::class);

        // Cek jumlah total: 5 root + (8+4+5+4+4) children = 30 baris
        $this->assertSame(30, Category::count());

        // Cek bahwa setiap root memiliki children
        $rootCodes = ['PELAJARAN', 'FIKSI', 'NON_FIKSI', 'REFERENSI', 'KOLEKSI_KHUSUS'];
        foreach ($rootCodes as $code) {
            $root = Category::where('code', $code)->first();
            $this->assertNotNull($root, "Root category '{$code}' tidak ditemukan");
            $this->assertGreaterThan(0, $root->children()->count(), "'{$code}' tidak memiliki children");
        }

        // Spot-check beberapa child
        $checks = [
            ['code' => 'PELAJARAN_MATEMATIKA', 'parent_code' => 'PELAJARAN'],
            ['code' => 'FIKSI_NOVEL',           'parent_code' => 'FIKSI'],
            ['code' => 'NON_FIKSI_BIOGRAFI',    'parent_code' => 'NON_FIKSI'],
            ['code' => 'REFERENSI_KAMUS',        'parent_code' => 'REFERENSI'],
            ['code' => 'KOLEKSI_KHUSUS_KARYA_SISWA', 'parent_code' => 'KOLEKSI_KHUSUS'],
        ];

        foreach ($checks as $check) {
            $child = Category::where('code', $check['code'])->first();
            $this->assertNotNull($child, "Child '{$check['code']}' tidak ditemukan");
            $this->assertSame($check['parent_code'], $child->parent->code);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class); // jalankan dua kali

        // Jumlah harus tetap 30, bukan 60
        $this->assertSame(30, Category::count());
    }
}
