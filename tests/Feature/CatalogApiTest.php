<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookTarget;
use App\Models\Category;
use App\Models\ClassLevel;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\Major;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function createCollectionType(): CollectionType
    {
        return CollectionType::create(['code' => 'CT-'.uniqid(), 'name' => 'Collection Type', 'is_active' => true, 'sort_order' => 1]);
    }

    private function createBook(array $attributes = []): Book
    {
        $collectionType = $this->createCollectionType();

        return Book::create(array_merge([
            'isbn' => '978-'.uniqid(),
            'title' => 'Buku Test',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'id',
        ], $attributes));
    }

    public function test_guest_can_list_books(): void
    {
        $this->createBook(['title' => 'Book A']);
        $this->createBook(['title' => 'Book B']);
        $this->createBook(['title' => 'Book C']);

        $response = $this->getJson('/api/catalog/books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'items' => [
                    '*' => ['id', 'isbn', 'title'],
                ],
                'pagination',
            ],
        ]);
        $this->assertCount(3, $response->json('data.items'));
    }

    public function test_catalog_endpoints_do_not_require_auth(): void
    {
        $this->createBook();

        $this->getJson('/api/catalog/books')->assertStatus(200);
        $this->getJson('/api/catalog/categories')->assertStatus(200);
        $this->getJson('/api/catalog/authors')->assertStatus(200);
        $this->getJson('/api/catalog/publishers')->assertStatus(200);
        $this->getJson('/api/catalog/collection-types')->assertStatus(200);
    }

    public function test_library_staff_can_create_a_category_from_the_catalog(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/catalog/categories', [
                'name' => 'Sains Terapan',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Sains Terapan')
            ->assertJsonPath('data.code', 'SAINS_TERAPAN');

        $this->assertDatabaseHas('categories', [
            'name' => 'Sains Terapan',
            'code' => 'SAINS_TERAPAN',
            'is_active' => true,
        ]);

        $this->getJson('/api/catalog/categories')
            ->assertJsonFragment([
                'id' => $response->json('data.id'),
                'name' => 'Sains Terapan',
            ]);
    }

    public function test_guest_cannot_create_a_category_from_the_catalog(): void
    {
        $this->postJson('/api/catalog/categories', [
            'name' => 'Kategori Tanpa Login',
        ])->assertUnauthorized();
    }

    public function test_category_creation_rejects_duplicate_names(): void
    {
        $user = User::factory()->create();
        $user->assignRole('LIBRARY_STAFF');
        Category::create([
            'code' => 'SAINS_EXISTING',
            'name' => 'Sains Terapan',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/catalog/categories', [
                'name' => 'sains terapan',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nama kategori tersebut sudah terdaftar.');
    }

    public function test_authenticated_user_can_access_catalog(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => Hash::make('password')]);
        $this->createBook();

        $this->actingAs($user, 'sanctum')->getJson('/api/catalog/books')->assertStatus(200);
    }

    public function test_search_behavior(): void
    {
        $book1 = $this->createBook(['title' => 'Matematika Dasar']);
        $book2 = $this->createBook(['title' => 'Fisika Kuantum']);

        $author = Author::create(['name' => 'Prof. Einstein']);
        $book3 = $this->createBook(['title' => 'Sains Umum']);
        $book3->authors()->attach($author);

        // Search by title
        $response = $this->getJson('/api/catalog/books?search=matematika');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($book1->id, $response->json('data.items.0.id'));

        // Search by author
        $response = $this->getJson('/api/catalog/books?search=einstein');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($book3->id, $response->json('data.items.0.id'));
    }

    public function test_target_filtering_exact_match(): void
    {
        $class = ClassLevel::create(['code' => 'X', 'name' => 'Kelas X']);
        $major = Major::create(['code' => 'IPA', 'name' => 'MIPA']);

        $book = $this->createBook();
        BookTarget::create([
            'book_id' => $book->id,
            'class_level_id' => $class->id,
            'major_id' => $major->id,
        ]);

        $response = $this->getJson("/api/catalog/books?class_level_id={$class->id}&major_id={$major->id}");
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_target_filtering_global_match(): void
    {
        $class = ClassLevel::create(['code' => 'X2', 'name' => 'Kelas X']);
        $major = Major::create(['code' => 'IPA2', 'name' => 'MIPA']);

        $book = $this->createBook();
        BookTarget::create([
            'book_id' => $book->id,
            'class_level_id' => null,
            'major_id' => null, // Global
        ]);

        $response = $this->getJson("/api/catalog/books?class_level_id={$class->id}&major_id={$major->id}");
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_availability_filtering(): void
    {
        $loc = Location::create(['code' => 'L1', 'name' => 'Loc1']);

        $bookAvailable = $this->createBook();
        BookCopy::create(['book_id' => $bookAvailable->id, 'barcode' => 'B1', 'inventory_number' => 'I1', 'location_id' => $loc->id, 'status' => 'AVAILABLE']);

        $bookBorrowed = $this->createBook();
        BookCopy::create(['book_id' => $bookBorrowed->id, 'barcode' => 'B2', 'inventory_number' => 'I2', 'location_id' => $loc->id, 'status' => 'BORROWED']);

        // available=true
        $response = $this->getJson('/api/catalog/books?available=true');
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($bookAvailable->id, $response->json('data.items.0.id'));

        // available=false
        $response = $this->getJson('/api/catalog/books?available=false');
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($bookBorrowed->id, $response->json('data.items.0.id'));
    }

    public function test_sorting_and_pagination(): void
    {
        $this->createBook(['title' => 'Zebra']);
        $this->createBook(['title' => 'Apple']);

        $response = $this->getJson('/api/catalog/books?sort=title&direction=asc&per_page=1');

        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals('Apple', $response->json('data.items.0.title'));
        $this->assertEquals(2, $response->json('data.pagination.total'));
        $this->assertEquals(2, $response->json('data.pagination.last_page'));
    }

    public function test_invalid_filters_return_422(): void
    {
        $response = $this->getJson('/api/catalog/books?sort=password');
        $response->assertStatus(422);
    }

    public function test_book_detail_does_not_expose_barcode(): void
    {
        $loc = Location::create(['code' => 'L3', 'name' => 'Loc3']);
        $book = $this->createBook();
        BookCopy::create([
            'book_id' => $book->id,
            'location_id' => $loc->id,
            'barcode' => 'SECRET-BARCODE-123',
            'inventory_number' => 'INV-001',
            'status' => 'AVAILABLE',
        ]);

        $response = $this->getJson("/api/catalog/books/{$book->id}");
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertStringNotContainsString('SECRET-BARCODE-123', json_encode($json));
        $this->assertStringNotContainsString('INV-001', json_encode($json));

        $this->assertEquals(1, $json['data']['availability']['available_copies']);
    }

    public function test_book_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/catalog/books/999999');
        $response->assertStatus(404);
        $response->assertJson(['success' => false, 'message' => 'Resource not found.']);
    }
}
