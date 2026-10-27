<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookTarget;
use App\Models\Category;
use App\Models\ClassLevel;
use App\Models\CollectionType;
use App\Models\Major;
use App\Models\Publisher;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Seed data buku contoh untuk sistem perpustakaan sekolah.
     *
     * Seeder ini idempotent: aman dijalankan lebih dari sekali.
     * Menggunakan syncWithoutDetaching untuk menjaga relasi pivot secara idempotent.
     */
    public function run(): void
    {
        // ── Lookup Collection Type ───────────────────────────────────────
        $textbook = CollectionType::where('code', 'TEXTBOOK')->value('id');
        $fiction = CollectionType::where('code', 'FICTION')->value('id');
        $nonFiction = CollectionType::where('code', 'NON_FICTION')->value('id');

        // ── Lookup Publisher ──────────────────────────────────────────────
        $erlangga = Publisher::where('name', 'Erlangga')->value('id');
        $gramedia = Publisher::where('name', 'Gramedia Pustaka Utama')->value('id');
        $balai = Publisher::where('name', 'Balai Pustaka')->value('id');
        $yudhistira = Publisher::where('name', 'Yudhistira')->value('id');
        $kemendikbud = Publisher::where('name', 'Kemendikbud')->value('id');
        $intanpar = Publisher::where('name', 'Intan Pariwara')->value('id');
        $tiga = Publisher::where('name', 'Tiga Serangkai')->value('id');

        // ── Lookup Author ─────────────────────────────────────────────────
        $andrea = Author::where('name', 'Andrea Hirata')->value('id');
        $ahmad = Author::where('name', 'Ahmad Fuadi')->value('id');
        $buya = Author::where('name', 'Buya Hamka')->value('id');
        $pram = Author::where('name', 'Pramoedya Ananta Toer')->value('id');

        // ── Lookup Category ───────────────────────────────────────────────
        $catPelajaran = Category::where('name', 'Pelajaran')->value('id');
        $catMatematika = Category::where('name', 'Matematika')->value('id');
        $catFisika = Category::where('name', 'Fisika')->value('id');
        $catBiologi = Category::where('name', 'Biologi')->value('id');
        $catKimia = Category::where('name', 'Kimia')->value('id');
        $catEkonomi = Category::where('name', 'Ekonomi')->value('id');
        $catSosiologi = Category::where('name', 'Sosiologi')->value('id');
        $catGeografi = Category::where('name', 'Geografi')->value('id');
        $catSejarah = Category::where('name', 'Sejarah')->value('id');

        $catFiksi = Category::where('name', 'Fiksi')->value('id');
        $catNovel = Category::where('name', 'Novel')->value('id');
        $catSastra = Category::where('name', 'Sastra')->value('id');

        // Struktur data: book data + authors array + categories array
        $books = [
            // ── Buku Pelajaran (TEXTBOOK) ─────────────────────────────────
            [
                'data' => [
                    'isbn' => '9786021276983',
                    'title' => 'Dasar-Dasar Matematika',
                    'description' => 'Buku pelajaran Matematika untuk SMA/MA kelas X.',
                    'publication_year' => 2022,
                    'edition' => 'Edisi 1',
                    'collection_type_id' => $textbook,
                    'publisher_id' => $erlangga,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catMatematika],
            ],
            [
                'data' => [
                    'isbn' => '9786021276990',
                    'title' => 'Fisika Dasar',
                    'subtitle' => 'Konsep dan Penerapan',
                    'description' => 'Buku pelajaran Fisika untuk SMA/MA kelas X–XII.',
                    'publication_year' => 2021,
                    'edition' => 'Edisi 2',
                    'collection_type_id' => $textbook,
                    'publisher_id' => $erlangga,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catFisika],
            ],
            [
                'data' => [
                    'isbn' => '9786024077631',
                    'title' => 'Biologi untuk SMA',
                    'description' => 'Buku pelajaran Biologi kelas X, XI, XII SMA/MA.',
                    'publication_year' => 2022,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $yudhistira,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catBiologi],
            ],
            [
                'data' => [
                    'isbn' => '9786024077648',
                    'title' => 'Kimia Dasar',
                    'description' => 'Buku pelajaran Kimia untuk SMA kelas X–XII.',
                    'publication_year' => 2021,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $intanpar,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catKimia],
            ],
            [
                'data' => [
                    'isbn' => '9786024077655',
                    'title' => 'Ekonomi untuk SMA',
                    'description' => 'Buku pelajaran Ekonomi kelas X, XI, XII SMA/MA.',
                    'publication_year' => 2023,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $tiga,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catEkonomi],
            ],
            [
                'data' => [
                    'isbn' => '9786024077662',
                    'title' => 'Sosiologi untuk SMA',
                    'description' => 'Buku pelajaran Sosiologi kelas X, XI, XII SMA/MA.',
                    'publication_year' => 2022,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $kemendikbud,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catSosiologi],
            ],
            [
                'data' => [
                    'isbn' => '9786024077679',
                    'title' => 'Geografi untuk SMA',
                    'description' => 'Buku pelajaran Geografi kelas X, XI, XII SMA/MA.',
                    'publication_year' => 2022,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $kemendikbud,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catGeografi],
            ],
            [
                'data' => [
                    'isbn' => '9786024077686',
                    'title' => 'Sejarah Indonesia',
                    'subtitle' => 'Dari Masa Prasejarah hingga Kemerdekaan',
                    'description' => 'Buku pelajaran Sejarah Indonesia untuk SMA kelas X–XII.',
                    'publication_year' => 2021,
                    'collection_type_id' => $textbook,
                    'publisher_id' => $balai,
                ],
                'authors' => [],
                'categories' => [$catPelajaran, $catSejarah],
            ],
            // ── Fiksi / Novel ─────────────────────────────────────────────
            [
                'data' => [
                    'isbn' => '9789792240887',
                    'title' => 'Laskar Pelangi',
                    'description' => 'Novel karya Andrea Hirata tentang semangat belajar anak-anak Belitung.',
                    'publication_year' => 2005,
                    'collection_type_id' => $fiction,
                    'publisher_id' => $gramedia,
                ],
                'authors' => [$andrea],
                'categories' => [$catFiksi, $catNovel],
            ],
            [
                'data' => [
                    'isbn' => '9789792262513',
                    'title' => 'Negeri 5 Menara',
                    'description' => 'Novel semi-otobiografi Ahmad Fuadi tentang santri pesantren.',
                    'publication_year' => 2009,
                    'collection_type_id' => $fiction, // PERBAIKAN: FICTION, bukan NON_FICTION
                    'publisher_id' => $gramedia,
                ],
                'authors' => [$ahmad],
                'categories' => [$catFiksi, $catNovel],
            ],
            [
                'data' => [
                    'isbn' => null,
                    'title' => 'Tenggelamnya Kapal Van Der Wijck',
                    'description' => 'Novel romantis karya Buya Hamka.',
                    'publication_year' => 1939,
                    'collection_type_id' => $fiction,
                    'publisher_id' => $balai,
                ],
                'authors' => [$buya],
                'categories' => [$catFiksi, $catSastra],
            ],
            [
                'data' => [
                    'isbn' => null,
                    'title' => 'Bumi Manusia',
                    'description' => 'Novel pertama dari Tetralogi Buru karya Pramoedya Ananta Toer.',
                    'publication_year' => 1980,
                    'collection_type_id' => $fiction,
                    'publisher_id' => $gramedia,
                ],
                'authors' => [$pram],
                'categories' => [$catFiksi, $catSastra, $catNovel],
            ],
        ];

        foreach ($books as $item) {
            $data = $item['data'];

            if (! empty($data['isbn'])) {
                $book = Book::firstOrCreate(['isbn' => $data['isbn']], $data);
            } else {
                $book = Book::firstOrCreate(
                    [
                        'title' => $data['title'],
                        'collection_type_id' => $data['collection_type_id'],
                    ],
                    $data
                );
            }

            // Memperbarui pivot tabel tanpa menghapus yang existing (idempotent)
            $book->authors()->syncWithoutDetaching(array_filter($item['authors']));
            $book->categories()->syncWithoutDetaching(array_filter($item['categories']));
        }

        // ── Seed Book Targets ─────────────────────────────────────────────

        // Lookup Class Levels
        $classX = ClassLevel::where('code', 'X')->value('id');
        $classXI = ClassLevel::where('code', 'XI')->value('id');
        $classXII = ClassLevel::where('code', 'XII')->value('id');

        // Lookup Majors
        $majorIpa = Major::where('code', 'IPA')->value('id');
        $majorIps1 = Major::where('code', 'IPS1')->value('id');
        $majorIps2 = Major::where('code', 'IPS2')->value('id');

        // Helper untuk lookup buku dengan aman
        $getBookId = function ($title) {
            return Book::where('title', $title)->value('id');
        };

        // 1. Dasar-Dasar Matematika: X, XI, XII (Semua Jurusan)
        $bookId = $getBookId('Dasar-Dasar Matematika');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => null]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => null]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => null]);
        }

        // 2. Fisika Dasar: X, XI, XII (IPA)
        $bookId = $getBookId('Fisika Dasar');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIpa]);
        }

        // 3. Biologi untuk SMA: X, XI, XII (IPA)
        $bookId = $getBookId('Biologi untuk SMA');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIpa]);
        }

        // 4. Kimia Dasar: X, XI, XII (IPA)
        $bookId = $getBookId('Kimia Dasar');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIpa]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIpa]);
        }

        // 5. Ekonomi untuk SMA: X, XI, XII (IPS1 & IPS2)
        $bookId = $getBookId('Ekonomi untuk SMA');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps2]);
        }

        // 6. Sosiologi untuk SMA: X, XI, XII (IPS1 & IPS2)
        $bookId = $getBookId('Sosiologi untuk SMA');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps2]);
        }

        // 7. Geografi untuk SMA: X, XI, XII (IPS1 & IPS2)
        $bookId = $getBookId('Geografi untuk SMA');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps1]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => $majorIps2]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => $majorIps2]);
        }

        // 8. Sejarah Indonesia: X, XI, XII (Semua Jurusan)
        $bookId = $getBookId('Sejarah Indonesia');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classX,   'major_id' => null]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXI,  'major_id' => null]);
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => $classXII, 'major_id' => null]);
        }

        // 9. Laskar Pelangi: Global (Semua Kelas & Semua Jurusan)
        $bookId = $getBookId('Laskar Pelangi');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => null, 'major_id' => null]);
        }

        // 10. Tenggelamnya Kapal Van Der Wijck: Global
        $bookId = $getBookId('Tenggelamnya Kapal Van Der Wijck');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => null, 'major_id' => null]);
        }

        // 11. Bumi Manusia: Global
        $bookId = $getBookId('Bumi Manusia');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => null, 'major_id' => null]);
        }

        // 12. Negeri 5 Menara: Global
        $bookId = $getBookId('Negeri 5 Menara');
        if ($bookId) {
            BookTarget::firstOrCreate(['book_id' => $bookId, 'class_level_id' => null, 'major_id' => null]);
        }
    }
}
