<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\CollectionType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookAuthorTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookAndAuthor(): array
    {
        $ct = CollectionType::create(['code' => 'TEST', 'name' => 'Test']);

        $book = Book::create([
            'title' => 'Buku Tes Pivot Author',
            'collection_type_id' => $ct->id,
        ]);

        $author = Author::create(['name' => 'Penulis Tes']);

        return [$book, $author];
    }

    public function test_book_authors_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('book_authors'));
    }

    public function test_book_can_have_author(): void
    {
        [$book, $author] = $this->makeBookAndAuthor();

        $book->authors()->attach($author);

        $this->assertCount(1, $book->authors);
        $this->assertSame($author->id, $book->authors->first()->id);
    }

    public function test_author_can_have_books(): void
    {
        [$book, $author] = $this->makeBookAndAuthor();

        $author->books()->attach($book);

        $this->assertCount(1, $author->books);
        $this->assertSame($book->id, $author->books->first()->id);
    }

    public function test_duplicate_book_author_pair_rejected(): void
    {
        [$book, $author] = $this->makeBookAndAuthor();

        $book->authors()->attach($author);

        $this->expectException(QueryException::class);

        $book->authors()->attach($author); // Harus gagal karena composite unique
    }

    public function test_deleting_book_removes_pivot_association(): void
    {
        [$book, $author] = $this->makeBookAndAuthor();
        $book->authors()->attach($author);

        $book->delete();

        $this->assertDatabaseMissing('book_authors', [
            'book_id' => $book->id,
            'author_id' => $author->id,
        ]);
    }

    public function test_deleting_author_removes_pivot_association(): void
    {
        [$book, $author] = $this->makeBookAndAuthor();
        $book->authors()->attach($author);

        $author->delete();

        $this->assertDatabaseMissing('book_authors', [
            'book_id' => $book->id,
            'author_id' => $author->id,
        ]);
    }
}
