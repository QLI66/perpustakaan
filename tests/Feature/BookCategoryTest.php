<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Category;
use App\Models\CollectionType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeBookAndCategory(): array
    {
        $ct = CollectionType::create(['code' => 'TEST', 'name' => 'Test']);

        $book = Book::create([
            'title' => 'Buku Tes Pivot Category',
            'collection_type_id' => $ct->id,
        ]);

        $category = Category::create(['code' => 'CAT1', 'name' => 'Kategori Tes']);

        return [$book, $category];
    }

    public function test_book_categories_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('book_categories'));
    }

    public function test_book_can_have_category(): void
    {
        [$book, $category] = $this->makeBookAndCategory();

        $book->categories()->attach($category);

        $this->assertCount(1, $book->categories);
        $this->assertSame($category->id, $book->categories->first()->id);
    }

    public function test_category_can_have_books(): void
    {
        [$book, $category] = $this->makeBookAndCategory();

        $category->books()->attach($book);

        $this->assertCount(1, $category->books);
        $this->assertSame($book->id, $category->books->first()->id);
    }

    public function test_duplicate_book_category_pair_rejected(): void
    {
        [$book, $category] = $this->makeBookAndCategory();

        $book->categories()->attach($category);

        $this->expectException(QueryException::class);

        $book->categories()->attach($category); // Harus gagal karena composite unique
    }

    public function test_deleting_book_removes_pivot_association(): void
    {
        [$book, $category] = $this->makeBookAndCategory();
        $book->categories()->attach($category);

        $book->delete();

        $this->assertDatabaseMissing('book_categories', [
            'book_id' => $book->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_deleting_category_removes_pivot_association(): void
    {
        [$book, $category] = $this->makeBookAndCategory();
        $book->categories()->attach($category);

        $category->delete();

        $this->assertDatabaseMissing('book_categories', [
            'book_id' => $book->id,
            'category_id' => $category->id,
        ]);
    }
}
