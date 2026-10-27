<?php

namespace Database\Seeders;

use App\Models\MemberType;
use Illuminate\Database\Seeder;

class MemberTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'code' => 'STUDENT',
                'name' => 'Siswa',
                'description' => 'Peserta didik aktif',
                'sort_order' => 10,
            ],
            [
                'code' => 'TEACHER',
                'name' => 'Guru',
                'description' => 'Tenaga pendidik aktif',
                'sort_order' => 20,
            ],
            [
                'code' => 'EDUCATION_STAFF',
                'name' => 'Tenaga Kependidikan',
                'description' => 'Staf tata usaha dan karyawan sekolah',
                'sort_order' => 30,
            ],
            [
                'code' => 'OTHER',
                'name' => 'Lainnya',
                'description' => 'Tamu atau anggota honorer',
                'sort_order' => 99,
            ],
        ];

        foreach ($types as $type) {
            MemberType::firstOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'sort_order' => $type['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
