<?php

namespace Database\Seeders;

use App\Models\Major;
use Illuminate\Database\Seeder;

class MajorSeeder extends Seeder
{
    /**
     * Seed data jurusan untuk sistem perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     */
    public function run(): void
    {
        $majors = [
            ['code' => 'IPA',  'name' => 'IPA',   'description' => 'Jurusan Ilmu Pengetahuan Alam',   'sort_order' => 1],
            ['code' => 'IPS1', 'name' => 'IPS 1', 'description' => 'Jurusan Ilmu Pengetahuan Sosial 1', 'sort_order' => 2],
            ['code' => 'IPS2', 'name' => 'IPS 2', 'description' => 'Jurusan Ilmu Pengetahuan Sosial 2', 'sort_order' => 3],
        ];

        foreach ($majors as $data) {
            Major::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
