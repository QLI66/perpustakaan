<?php

namespace Database\Seeders;

use App\Models\ClassLevel;
use App\Models\Major;
use App\Models\Member;
use App\Models\MemberType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lookup Member Types
        $studentType = MemberType::where('code', 'STUDENT')->value('id');
        $teacherType = MemberType::where('code', 'TEACHER')->value('id');
        $staffType = MemberType::where('code', 'EDUCATION_STAFF')->value('id');
        $otherType = MemberType::where('code', 'OTHER')->value('id');

        // Lookup Class Levels & Majors
        $clsX = ClassLevel::where('code', 'X')->value('id');
        $clsXI = ClassLevel::where('code', 'XI')->value('id');
        $clsXII = ClassLevel::where('code', 'XII')->value('id');

        $mjrIPA = Major::where('code', 'IPA')->value('id');
        $mjrIPS1 = Major::where('code', 'IPS1')->value('id');
        $mjrIPS2 = Major::where('code', 'IPS2')->value('id');

        $members = [
            // 8 STUDENTS
            ['num' => 'MBR-2026-0001', 'name' => 'Siswa X IPA', 'type' => $studentType, 'id_num' => 'NIS-001', 'cls' => $clsX, 'mjr' => $mjrIPA],
            ['num' => 'MBR-2026-0002', 'name' => 'Siswa XI IPA', 'type' => $studentType, 'id_num' => 'NIS-002', 'cls' => $clsXI, 'mjr' => $mjrIPA],
            ['num' => 'MBR-2026-0003', 'name' => 'Siswa XII IPA', 'type' => $studentType, 'id_num' => 'NIS-003', 'cls' => $clsXII, 'mjr' => $mjrIPA],
            ['num' => 'MBR-2026-0004', 'name' => 'Siswa X IPS1', 'type' => $studentType, 'id_num' => 'NIS-004', 'cls' => $clsX, 'mjr' => $mjrIPS1],
            ['num' => 'MBR-2026-0005', 'name' => 'Siswa XI IPS1', 'type' => $studentType, 'id_num' => 'NIS-005', 'cls' => $clsXI, 'mjr' => $mjrIPS1],
            ['num' => 'MBR-2026-0006', 'name' => 'Siswa XII IPS1', 'type' => $studentType, 'id_num' => 'NIS-006', 'cls' => $clsXII, 'mjr' => $mjrIPS1],
            ['num' => 'MBR-2026-0007', 'name' => 'Siswa X IPS2', 'type' => $studentType, 'id_num' => 'NIS-007', 'cls' => $clsX, 'mjr' => $mjrIPS2],
            ['num' => 'MBR-2026-0008', 'name' => 'Siswa XI IPS2', 'type' => $studentType, 'id_num' => 'NIS-008', 'cls' => $clsXI, 'mjr' => $mjrIPS2],

            // 2 TEACHERS
            ['num' => 'MBR-2026-0009', 'name' => 'Guru Budi', 'type' => $teacherType, 'id_num' => 'NIP-001', 'cls' => null, 'mjr' => null],
            ['num' => 'MBR-2026-0010', 'name' => 'Guru Wati', 'type' => $teacherType, 'id_num' => 'NIP-002', 'cls' => null, 'mjr' => null],

            // 1 EDUCATION STAFF
            ['num' => 'MBR-2026-0011', 'name' => 'Staf Andi', 'type' => $staffType, 'id_num' => 'NIP-003', 'cls' => null, 'mjr' => null],

            // 1 OTHER
            ['num' => 'MBR-2026-0012', 'name' => 'Tamu Doni', 'type' => $otherType, 'id_num' => 'KTP-001', 'cls' => null, 'mjr' => null],
        ];

        foreach ($members as $m) {
            Member::firstOrCreate(
                ['member_number' => $m['num']],
                [
                    'name' => $m['name'],
                    'member_type_id' => $m['type'],
                    'identity_number' => $m['id_num'],
                    'class_level_id' => $m['cls'],
                    'major_id' => $m['mjr'],
                    'joined_at' => Carbon::now()->subMonths(random_int(1, 12))->format('Y-m-d'),
                    'status' => 'ACTIVE',
                ]
            );
        }
    }
}
