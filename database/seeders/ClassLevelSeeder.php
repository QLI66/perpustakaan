<?php

namespace Database\Seeders;

use App\Models\ClassLevel;
use Illuminate\Database\Seeder;

class ClassLevelSeeder extends Seeder
{
    /**
     * Seed data tingkat kelas untuk sistem perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     */
    public function run(): void
    {
        $levels = [
            ['code' => 'X',   'name' => 'Kelas X',   'description' => 'Tingkat kelas 10 SMA/SMK', 'sort_order' => 1],
            ['code' => 'XI',  'name' => 'Kelas XI',  'description' => 'Tingkat kelas 11 SMA/SMK', 'sort_order' => 2],
            ['code' => 'XII', 'name' => 'Kelas XII', 'description' => 'Tingkat kelas 12 SMA/SMK', 'sort_order' => 3],
        ];

        foreach ($levels as $data) {
            ClassLevel::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
