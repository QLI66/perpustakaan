<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@perpustakaan.test'],
            [
                'name' => 'Administrator Perpustakaan',
                'password' => Hash::make('password'),
            ]
        );

        $admin->syncRoles(['SUPER_ADMIN']);

        $staff = User::updateOrCreate(
            ['email' => 'staff@perpustakaan.test'],
            [
                'name' => 'Petugas Perpustakaan',
                'password' => Hash::make('password'),
            ]
        );

        $staff->syncRoles(['LIBRARY_STAFF']);

        $memberUser = User::updateOrCreate(
            ['email' => 'member@perpustakaan.test'],
            [
                'name' => 'Member Demo',
                'password' => Hash::make('password'),
            ]
        );

        $memberUser->syncRoles(['MEMBER']);

        $memberType = MemberType::firstOrCreate(
            ['code' => 'STD'],
            ['name' => 'Student']
        );

        Member::updateOrCreate(
            ['user_id' => $memberUser->id],
            [
                'member_number' => 'MEM-DEMO-001',
                'name' => 'Member Demo',
                'member_type_id' => $memberType->id,
                'joined_at' => now(),
                'status' => 'ACTIVE',
            ]
        );

        $this->command->info('Akun development berhasil disiapkan.');
        $this->command->info('ADMIN  : admin@perpustakaan.test / password');
        $this->command->info('STAFF  : staff@perpustakaan.test / password');
        $this->command->info('MEMBER : member@perpustakaan.test / password');
    }
}
