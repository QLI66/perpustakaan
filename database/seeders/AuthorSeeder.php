<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    /**
     * Seed data penulis awal sistem perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     * firstOrCreate menggunakan 'name' karena tidak ada unique constraint,
     * namun untuk data awal ini setiap nama bersifat unik.
     */
    public function run(): void
    {
        $authors = [
            [
                'name' => 'Andrea Hirata',
                'biography' => 'Novelis Indonesia, dikenal melalui tetralogi Laskar Pelangi.',
                'birth_date' => '1967-10-24',
                'death_date' => null,
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Tere Liye',
                'biography' => 'Penulis produktif Indonesia dengan banyak novel populer.',
                'birth_date' => '1979-05-21',
                'death_date' => null,
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Dee Lestari',
                'biography' => 'Penulis dan musisi Indonesia, dikenal melalui seri Supernova.',
                'birth_date' => '1976-01-20',
                'death_date' => null,
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Pramoedya Ananta Toer',
                'biography' => 'Sastrawan terbesar Indonesia, dikenal melalui Tetralogi Buru.',
                'birth_date' => '1925-02-06',
                'death_date' => '2006-04-30',
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Ahmad Fuadi',
                'biography' => 'Novelis Indonesia, dikenal melalui novel Negeri 5 Menara.',
                'birth_date' => '1972-12-30',
                'death_date' => null,
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'NH Dini',
                'biography' => 'Novelis dan sastrawan perempuan Indonesia.',
                'birth_date' => '1936-02-29',
                'death_date' => '2018-12-04',
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Chairil Anwar',
                'biography' => 'Pelopor Angkatan \'45, penyair terkemuka Indonesia.',
                'birth_date' => '1922-07-26',
                'death_date' => '1949-04-28',
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Buya Hamka',
                'biography' => 'Ulama, sastrawan, dan sejarawan Indonesia, pengarang Tenggelamnya Kapal Van Der Wijck.',
                'birth_date' => '1908-02-17',
                'death_date' => '1981-07-24',
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
            [
                'name' => 'Ki Hadjar Dewantara',
                'biography' => 'Bapak Pendidikan Nasional Indonesia, pendiri Taman Siswa.',
                'birth_date' => '1889-05-02',
                'death_date' => '1959-04-26',
                'nationality' => 'Indonesia',
                'is_active' => true,
            ],
        ];

        foreach ($authors as $data) {
            Author::firstOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
