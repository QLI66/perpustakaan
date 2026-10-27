<?php

namespace Database\Seeders;

use App\Models\CollectionType;
use Illuminate\Database\Seeder;

class CollectionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'TEXTBOOK',    'name' => 'Buku Pelajaran',   'description' => 'Buku teks pelajaran sekolah',           'sort_order' => 1],
            ['code' => 'FICTION',     'name' => 'Fiksi',            'description' => 'Karya sastra dan cerita fiksi',          'sort_order' => 2],
            ['code' => 'NON_FICTION', 'name' => 'Non-Fiksi',        'description' => 'Karya nonfiksi dan buku ilmu pengetahuan', 'sort_order' => 3],
            ['code' => 'REFERENCE',   'name' => 'Referensi',        'description' => 'Buku referensi, kamus, ensiklopedia',    'sort_order' => 4],
            ['code' => 'MAGAZINE',    'name' => 'Majalah',          'description' => 'Majalah dan terbitan berkala',           'sort_order' => 5],
            ['code' => 'NEWSPAPER',   'name' => 'Surat Kabar',      'description' => 'Surat kabar dan koran',                 'sort_order' => 6],
            ['code' => 'COMIC',       'name' => 'Komik',            'description' => 'Buku komik dan novel grafis',            'sort_order' => 7],
            ['code' => 'SPECIAL',     'name' => 'Koleksi Khusus',   'description' => 'Koleksi khusus dan langka',             'sort_order' => 8],
        ];

        foreach ($types as $type) {
            CollectionType::firstOrCreate(
                ['code' => $type['code']],
                array_merge($type, ['is_active' => true])
            );
        }
    }
}
