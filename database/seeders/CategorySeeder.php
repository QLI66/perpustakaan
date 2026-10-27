<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed data hierarki kategori perpustakaan sekolah.
     *
     * Struktur kode:
     *   Root  → PELAJARAN, FIKSI, NON_FIKSI, REFERENSI, KOLEKSI_KHUSUS
     *   Child → {ROOT_CODE}_{CHILD_SUFFIX}
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     */
    public function run(): void
    {
        $tree = [
            [
                'code' => 'PELAJARAN',
                'name' => 'Pelajaran',
                'description' => 'Buku-buku pelajaran sekolah',
                'sort_order' => 1,
                'children' => [
                    ['code' => 'PELAJARAN_MATEMATIKA', 'name' => 'Matematika',  'sort_order' => 1],
                    ['code' => 'PELAJARAN_FISIKA',     'name' => 'Fisika',      'sort_order' => 2],
                    ['code' => 'PELAJARAN_BIOLOGI',    'name' => 'Biologi',     'sort_order' => 3],
                    ['code' => 'PELAJARAN_KIMIA',      'name' => 'Kimia',       'sort_order' => 4],
                    ['code' => 'PELAJARAN_EKONOMI',    'name' => 'Ekonomi',     'sort_order' => 5],
                    ['code' => 'PELAJARAN_SOSIOLOGI',  'name' => 'Sosiologi',   'sort_order' => 6],
                    ['code' => 'PELAJARAN_GEOGRAFI',   'name' => 'Geografi',    'sort_order' => 7],
                    ['code' => 'PELAJARAN_SEJARAH',    'name' => 'Sejarah',     'sort_order' => 8],
                ],
            ],
            [
                'code' => 'FIKSI',
                'name' => 'Fiksi',
                'description' => 'Karya sastra dan cerita fiksi',
                'sort_order' => 2,
                'children' => [
                    ['code' => 'FIKSI_NOVEL',   'name' => 'Novel',   'sort_order' => 1],
                    ['code' => 'FIKSI_CERPEN',  'name' => 'Cerpen',  'sort_order' => 2],
                    ['code' => 'FIKSI_PUISI',   'name' => 'Puisi',   'sort_order' => 3],
                    ['code' => 'FIKSI_DONGENG', 'name' => 'Dongeng', 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'NON_FIKSI',
                'name' => 'Non-Fiksi',
                'description' => 'Karya nonfiksi dan buku ilmu pengetahuan',
                'sort_order' => 3,
                'children' => [
                    ['code' => 'NON_FIKSI_AGAMA',      'name' => 'Agama',       'sort_order' => 1],
                    ['code' => 'NON_FIKSI_TEKNOLOGI',  'name' => 'Teknologi',   'sort_order' => 2],
                    ['code' => 'NON_FIKSI_KESEHATAN',  'name' => 'Kesehatan',   'sort_order' => 3],
                    ['code' => 'NON_FIKSI_PENDIDIKAN', 'name' => 'Pendidikan',  'sort_order' => 4],
                    ['code' => 'NON_FIKSI_BIOGRAFI',   'name' => 'Biografi',    'sort_order' => 5],
                ],
            ],
            [
                'code' => 'REFERENSI',
                'name' => 'Referensi',
                'description' => 'Buku referensi, kamus, ensiklopedia',
                'sort_order' => 4,
                'children' => [
                    ['code' => 'REFERENSI_ENSIKLOPEDIA', 'name' => 'Ensiklopedia',  'sort_order' => 1],
                    ['code' => 'REFERENSI_KAMUS',        'name' => 'Kamus',         'sort_order' => 2],
                    ['code' => 'REFERENSI_ATLAS',        'name' => 'Atlas',         'sort_order' => 3],
                    ['code' => 'REFERENSI_PANDUAN',      'name' => 'Buku Panduan',  'sort_order' => 4],
                ],
            ],
            [
                'code' => 'KOLEKSI_KHUSUS',
                'name' => 'Koleksi Khusus',
                'description' => 'Koleksi khusus dan karya civitas sekolah',
                'sort_order' => 5,
                'children' => [
                    ['code' => 'KOLEKSI_KHUSUS_KARYA_SISWA',   'name' => 'Karya Siswa',     'sort_order' => 1],
                    ['code' => 'KOLEKSI_KHUSUS_KARYA_GURU',    'name' => 'Karya Guru',      'sort_order' => 2],
                    ['code' => 'KOLEKSI_KHUSUS_BUKU_DAERAH',   'name' => 'Buku Daerah',     'sort_order' => 3],
                    ['code' => 'KOLEKSI_KHUSUS_KOLEKSI_SEKOLAH', 'name' => 'Koleksi Sekolah', 'sort_order' => 4],
                ],
            ],
        ];

        foreach ($tree as $rootData) {
            $children = $rootData['children'] ?? [];
            unset($rootData['children']);

            // Buat atau perbarui root category
            $root = Category::firstOrCreate(
                ['code' => $rootData['code']],
                array_merge($rootData, ['parent_id' => null, 'is_active' => true])
            );

            // Buat atau perbarui child categories
            foreach ($children as $childData) {
                Category::firstOrCreate(
                    ['code' => $childData['code']],
                    array_merge($childData, [
                        'parent_id' => $root->id,
                        'description' => null,
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
