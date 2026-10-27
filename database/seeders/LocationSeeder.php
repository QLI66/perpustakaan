<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Seed data lokasi rak perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     * firstOrCreate menggunakan 'code' sebagai identifier unik.
     */
    public function run(): void
    {
        $locations = [
            // ── Kelompok A: Pelajaran ──────────────────────────────────────
            ['code' => 'A01', 'name' => 'Rak Matematika',     'description' => 'Buku-buku pelajaran Matematika',          'sort_order' => 1],
            ['code' => 'A02', 'name' => 'Rak Fisika',         'description' => 'Buku-buku pelajaran Fisika',              'sort_order' => 2],
            ['code' => 'A03', 'name' => 'Rak Biologi',        'description' => 'Buku-buku pelajaran Biologi',             'sort_order' => 3],
            ['code' => 'A04', 'name' => 'Rak Kimia',          'description' => 'Buku-buku pelajaran Kimia',               'sort_order' => 4],
            ['code' => 'A05', 'name' => 'Rak Ekonomi',        'description' => 'Buku-buku pelajaran Ekonomi',             'sort_order' => 5],
            // ── Kelompok B: Fiksi ─────────────────────────────────────────
            ['code' => 'B01', 'name' => 'Rak Novel',          'description' => 'Koleksi novel dan karya fiksi',           'sort_order' => 6],
            ['code' => 'B02', 'name' => 'Rak Cerpen',         'description' => 'Koleksi cerita pendek',                  'sort_order' => 7],
            ['code' => 'B03', 'name' => 'Rak Puisi',          'description' => 'Koleksi puisi dan antologi',              'sort_order' => 8],
            ['code' => 'B04', 'name' => 'Rak Dongeng',        'description' => 'Koleksi dongeng dan cerita rakyat',       'sort_order' => 9],
            // ── Kelompok C: Referensi ─────────────────────────────────────
            ['code' => 'C01', 'name' => 'Rak Referensi',      'description' => 'Buku referensi umum',                    'sort_order' => 10],
            ['code' => 'C02', 'name' => 'Rak Kamus',          'description' => 'Kamus bahasa dan istilah',               'sort_order' => 11],
            ['code' => 'C03', 'name' => 'Rak Ensiklopedia',   'description' => 'Ensiklopedia umum dan tematik',          'sort_order' => 12],
            // ── Kelompok D: Koleksi Khusus ────────────────────────────────
            ['code' => 'D01', 'name' => 'Rak Koleksi Khusus', 'description' => 'Koleksi khusus dan karya civitas sekolah', 'sort_order' => 13],
        ];

        foreach ($locations as $data) {
            Location::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
