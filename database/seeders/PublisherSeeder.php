<?php

namespace Database\Seeders;

use App\Models\Publisher;
use Illuminate\Database\Seeder;

class PublisherSeeder extends Seeder
{
    /**
     * Seed data penerbit awal sistem perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     * Pencarian dilakukan berdasarkan 'name' karena tidak ada kolom unique code
     * pada tabel publishers.
     */
    public function run(): void
    {
        $publishers = [
            [
                'name' => 'Erlangga',
                'address' => 'Jakarta',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.erlangga.co.id',
                'is_active' => true,
            ],
            [
                'name' => 'Gramedia Pustaka Utama',
                'address' => 'Jakarta',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.gramedia.com',
                'is_active' => true,
            ],
            [
                'name' => 'Yudhistira',
                'address' => 'Jakarta',
                'phone' => null,
                'email' => null,
                'website' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Mizan',
                'address' => 'Bandung',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.mizan.com',
                'is_active' => true,
            ],
            [
                'name' => 'Balai Pustaka',
                'address' => 'Jakarta',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.balaipustaka.co.id',
                'is_active' => true,
            ],
            [
                'name' => 'Kemendikbud',
                'address' => 'Jakarta',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.kemdikbud.go.id',
                'is_active' => true,
            ],
            [
                'name' => 'Intan Pariwara',
                'address' => 'Klaten',
                'phone' => null,
                'email' => null,
                'website' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Tiga Serangkai',
                'address' => 'Solo',
                'phone' => null,
                'email' => null,
                'website' => 'https://www.tigaserangkai.com',
                'is_active' => true,
            ],
        ];

        foreach ($publishers as $data) {
            Publisher::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
