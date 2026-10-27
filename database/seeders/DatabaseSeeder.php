<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Panggil seeder master data yang independen/paling bawah hierarkinya terlebih dahulu
        $this->call([
            RolePermissionSeeder::class, // Authentication & Authorization foundation
            MemberTypeSeeder::class, // Independen
            CollectionTypeSeeder::class,
            CategorySeeder::class,
            PublisherSeeder::class,
            AuthorSeeder::class,
            ClassLevelSeeder::class,
            MajorSeeder::class,
            LocationSeeder::class,
            MemberSeeder::class,     // Setelah MemberType, ClassLevel, Major
            BookSeeder::class,       // Harus setelah CollectionTypeSeeder & PublisherSeeder
            BookCopySeeder::class,   // Harus setelah BookSeeder & LocationSeeder
            LoanSeeder::class,       // Harus setelah MemberSeeder & BookCopySeeder
            FineSeeder::class,       // Harus setelah LoanSeeder
            BookIncidentSeeder::class,
        ]);
    }
}
