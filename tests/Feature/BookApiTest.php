<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\CollectionType;
use App\Models\Publisher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $member;

    private CollectionType $collectionType;

    private Publisher $publisher;

    private Author $author;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolePermissionSeeder::class
        );

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'book-superadmin@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Library Staff',
            'email' => 'book-staff@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->member = User::create([
            'name' => 'Member',
            'email' => 'book-member@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->member->assignRole('MEMBER');

        $this->collectionType = CollectionType::create([
            'code' => 'BOOK-API-FIC',
            'name' => 'Book API Fiction',
        ]);

        $this->publisher = Publisher::create([
            'name' => 'Book API Publisher',
        ]);

        $this->author = Author::create([
            'name' => 'Book API Author',
        ]);

        $this->category = Category::create([
            'code' => 'BOOK-API-CAT',
            'name' => 'Book API Category',
        ]);
    }

    public function test_guest_cannot_list_books(): void
    {
        $this->getJson('/api/books')
            ->assertStatus(401);
    }

    public function test_member_cannot_list_books(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/books')
            ->assertStatus(403);
    }

    public function test_member_can_use_public_catalog_but_not_administrative_book_endpoints(): void
    {
        $book = Book::create([
            'title' => 'Catalog Only Book',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/catalog/books')
            ->assertOk();

        $this->getJson("/api/books/{$book->id}")
            ->assertForbidden();
    }

    public function test_library_staff_can_list_books(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/books')
            ->assertStatus(200);
    }

    public function test_library_staff_without_books_view_cannot_list_books(): void
    {
        Role::findByName('LIBRARY_STAFF')->revokePermissionTo('books.view');

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/books')
            ->assertForbidden();
    }

    public function test_super_admin_can_list_books(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/books')
            ->assertStatus(200);
    }

    public function test_library_staff_can_create_book(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->postJson('/api/books', [
            'isbn' => '9786021234567',
            'title' => 'Book API Create Test',
            'subtitle' => 'Subtitle',
            'description' => 'Description',
            'publication_year' => 2026,
            'edition' => 'Edisi 1',
            'language' => 'id',
            'collection_type_id' => $this->collectionType->id,
            'publisher_id' => $this->publisher->id,
            'cover' => 'covers/test.jpg',
            'author_ids' => [
                $this->author->id,
            ],
            'category_ids' => [
                $this->category->id,
            ],
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath(
                'data.title',
                'Book API Create Test'
            )
            ->assertJsonPath(
                'data.isbn',
                '9786021234567'
            );

        $bookId = $response->json('data.id');

        $this->assertDatabaseHas(
            'books',
            [
                'id' => $bookId,
                'title' => 'Book API Create Test',
            ]
        );

        $this->assertDatabaseHas(
            'book_authors',
            [
                'book_id' => $bookId,
                'author_id' => $this->author->id,
            ]
        );

        $this->assertDatabaseHas(
            'book_categories',
            [
                'book_id' => $bookId,
                'category_id' => $this->category->id,
            ]
        );
    }

    public function test_member_cannot_create_book(): void
    {
        $this->actingAs(
            $this->member,
            'sanctum'
        )->postJson('/api/books', [
            'title' => 'Member Must Not Create',
            'collection_type_id' => $this->collectionType->id,
        ])->assertStatus(403);
    }

    public function test_isbn_must_be_unique(): void
    {
        Book::create([
            'isbn' => '9786027654321',
            'title' => 'Existing ISBN',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->postJson('/api/books', [
            'isbn' => '9786027654321',
            'title' => 'Duplicate ISBN',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'isbn',
            ]);
    }

    public function test_title_and_collection_type_are_required(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->postJson('/api/books', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'title',
                'collection_type_id',
            ]);
    }

    public function test_staff_can_show_book(): void
    {
        $book = Book::create([
            'isbn' => '9786000000001',
            'title' => 'Show Book',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson("/api/books/{$book->id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'data.title',
                'Show Book'
            );
    }

    public function test_staff_can_update_book(): void
    {
        $book = Book::create([
            'isbn' => '9786000000002',
            'title' => 'Before Update',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->patchJson("/api/books/{$book->id}", [
            'title' => 'After Update',
            'author_ids' => [
                $this->author->id,
            ],
            'category_ids' => [
                $this->category->id,
            ],
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'data.title',
                'After Update'
            );

        $this->assertDatabaseHas(
            'book_authors',
            [
                'book_id' => $book->id,
                'author_id' => $this->author->id,
            ]
        );

        $this->assertDatabaseHas(
            'book_categories',
            [
                'book_id' => $book->id,
                'category_id' => $this->category->id,
            ]
        );
    }

    public function test_member_cannot_update_book(): void
    {
        $book = Book::create([
            'title' => 'Protected Book',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $this->actingAs(
            $this->member,
            'sanctum'
        )->patchJson("/api/books/{$book->id}", [
            'title' => 'Must Stay',
        ])->assertStatus(403);
    }

    public function test_member_cannot_delete_book(): void
    {
        $book = Book::create([
            'title' => 'Protected Delete Book',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $this->actingAs(
            $this->member,
            'sanctum'
        )->deleteJson("/api/books/{$book->id}")
            ->assertStatus(403);
    }

    public function test_staff_can_delete_book_without_reservations(): void
    {
        $book = Book::create([
            'title' => 'Delete Book',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->deleteJson("/api/books/{$book->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing(
            'books',
            [
                'id' => $book->id,
            ]
        );
    }

    public function test_list_supports_search(): void
    {
        Book::create([
            'title' => 'Matematika Modern',
            'collection_type_id' => $this->collectionType->id,
        ]);

        Book::create([
            'title' => 'Sejarah Nusantara',
            'collection_type_id' => $this->collectionType->id,
        ]);

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/books?search=Matematika'
        );

        $response
            ->assertStatus(200)
            ->assertJsonPath(
                'data.items.0.title',
                'Matematika Modern'
            );
    }
}
