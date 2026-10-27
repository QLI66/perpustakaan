<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookCopySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lookup Locations
        $rak1 = Location::where('code', 'A01')->value('id');
        $rak2 = Location::where('code', 'B01')->value('id');
        $rak3 = Location::where('code', 'C01')->value('id');

        // Helper function
        $getBookId = function ($title) {
            return Book::where('title', $title)->value('id');
        };

        // Data Eksemplar
        $copies = [
            // 1. Dasar-Dasar Matematika
            ['title' => 'Dasar-Dasar Matematika', 'barcode' => 'MAT-001', 'inv' => 'INV/MAT/001', 'loc' => $rak1, 'src' => 'PURCHASE', 'price' => 75000],
            ['title' => 'Dasar-Dasar Matematika', 'barcode' => 'MAT-002', 'inv' => 'INV/MAT/002', 'loc' => $rak1, 'src' => 'PURCHASE', 'price' => 75000],

            // 2. Fisika Dasar
            ['title' => 'Fisika Dasar', 'barcode' => 'FIS-001', 'inv' => 'INV/FIS/001', 'loc' => $rak1, 'src' => 'PURCHASE', 'price' => 85000],
            ['title' => 'Fisika Dasar', 'barcode' => 'FIS-002', 'inv' => 'INV/FIS/002', 'loc' => $rak1, 'src' => 'DONATION', 'price' => null],

            // 3. Biologi untuk SMA
            ['title' => 'Biologi untuk SMA', 'barcode' => 'BIO-001', 'inv' => 'INV/BIO/001', 'loc' => $rak1, 'src' => 'GRANT', 'price' => null],
            ['title' => 'Biologi untuk SMA', 'barcode' => 'BIO-002', 'inv' => 'INV/BIO/002', 'loc' => $rak1, 'src' => 'GRANT', 'price' => null],

            // 4. Kimia Dasar
            ['title' => 'Kimia Dasar', 'barcode' => 'KIM-001', 'inv' => 'INV/KIM/001', 'loc' => $rak1, 'src' => 'PURCHASE', 'price' => 80000],
            ['title' => 'Kimia Dasar', 'barcode' => 'KIM-002', 'inv' => 'INV/KIM/002', 'loc' => $rak1, 'src' => 'PURCHASE', 'price' => 80000],

            // 5. Ekonomi untuk SMA
            ['title' => 'Ekonomi untuk SMA', 'barcode' => 'EKO-001', 'inv' => 'INV/EKO/001', 'loc' => $rak2, 'src' => 'PURCHASE', 'price' => 65000],
            ['title' => 'Ekonomi untuk SMA', 'barcode' => 'EKO-002', 'inv' => null,           'loc' => $rak2, 'src' => 'DONATION', 'price' => null],

            // 6. Sosiologi untuk SMA
            ['title' => 'Sosiologi untuk SMA', 'barcode' => 'SOS-001', 'inv' => 'INV/SOS/001', 'loc' => $rak2, 'src' => 'PURCHASE', 'price' => 60000],
            ['title' => 'Sosiologi untuk SMA', 'barcode' => 'SOS-002', 'inv' => 'INV/SOS/002', 'loc' => $rak2, 'src' => 'PURCHASE', 'price' => 60000],

            // 7. Geografi untuk SMA
            ['title' => 'Geografi untuk SMA', 'barcode' => 'GEO-001', 'inv' => 'INV/GEO/001', 'loc' => $rak2, 'src' => 'PURCHASE', 'price' => 70000],
            ['title' => 'Geografi untuk SMA', 'barcode' => 'GEO-002', 'inv' => 'INV/GEO/002', 'loc' => $rak2, 'src' => 'PURCHASE', 'price' => 70000],

            // 8. Sejarah Indonesia
            ['title' => 'Sejarah Indonesia', 'barcode' => 'SEJ-001', 'inv' => 'INV/SEJ/001', 'loc' => $rak2, 'src' => 'DONATION', 'price' => null],
            ['title' => 'Sejarah Indonesia', 'barcode' => 'SEJ-002', 'inv' => 'INV/SEJ/002', 'loc' => $rak2, 'src' => 'DONATION', 'price' => null],

            // 9. Laskar Pelangi
            ['title' => 'Laskar Pelangi', 'barcode' => 'FIC-001', 'inv' => 'INV/FIC/001', 'loc' => $rak3, 'src' => 'PURCHASE', 'price' => 95000],
            ['title' => 'Laskar Pelangi', 'barcode' => 'FIC-002', 'inv' => 'INV/FIC/002', 'loc' => $rak3, 'src' => 'PURCHASE', 'price' => 95000],

            // 10. Tenggelamnya Kapal Van Der Wijck
            ['title' => 'Tenggelamnya Kapal Van Der Wijck', 'barcode' => 'FIC-003', 'inv' => 'INV/FIC/003', 'loc' => $rak3, 'src' => 'GRANT', 'price' => null],
            ['title' => 'Tenggelamnya Kapal Van Der Wijck', 'barcode' => 'FIC-004', 'inv' => 'INV/FIC/004', 'loc' => $rak3, 'src' => 'GRANT', 'price' => null],

            // 11. Bumi Manusia
            ['title' => 'Bumi Manusia', 'barcode' => 'FIC-005', 'inv' => 'INV/FIC/005', 'loc' => $rak3, 'src' => 'PURCHASE', 'price' => 120000],
            ['title' => 'Bumi Manusia', 'barcode' => 'FIC-006', 'inv' => 'INV/FIC/006', 'loc' => $rak3, 'src' => 'PURCHASE', 'price' => 120000],

            // 12. Negeri 5 Menara
            ['title' => 'Negeri 5 Menara', 'barcode' => 'FIC-007', 'inv' => 'INV/FIC/007', 'loc' => $rak3, 'src' => 'DONATION', 'price' => null],
            ['title' => 'Negeri 5 Menara', 'barcode' => 'FIC-008', 'inv' => 'INV/FIC/008', 'loc' => $rak3, 'src' => 'DONATION', 'price' => null],
        ];

        foreach ($copies as $copy) {
            $bookId = $getBookId($copy['title']);
            if (! $bookId) {
                continue;
            }

            BookCopy::firstOrCreate(
                ['barcode' => $copy['barcode']],
                [
                    'book_id' => $bookId,
                    'inventory_number' => $copy['inv'],
                    'location_id' => $copy['loc'],
                    'acquisition_date' => Carbon::now()->subMonths(random_int(1, 12))->format('Y-m-d'),
                    'acquisition_source' => $copy['src'],
                    'acquisition_price' => $copy['price'],
                    'condition' => 'GOOD',
                    'status' => 'AVAILABLE',
                ]
            );
        }
    }
}
