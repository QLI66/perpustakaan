<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberCatalogReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create([
            'name' => 'Report Super Admin',
            'email' => 'member-catalog-admin@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Report Library Staff',
            'email' => 'member-catalog-staff@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->memberUser = User::create([
            'name' => 'Report Member',
            'email' => 'member-catalog-member@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->memberUser->assignRole('MEMBER');
    }

    private function makeMember(
        string $number
    ): Member {
        $memberType = MemberType::create([
            'code' => 'MT-'.uniqid(),
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => "Member {$number}",
            'member_type_id' => $memberType->id,
            'joined_at' => '2026-09-20',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeBook(
        string $title
    ): Book {
        $collectionType = CollectionType::create([
            'code' => 'COL-'.uniqid(),
            'name' => 'Reference',
        ]);

        return Book::create([
            'title' => $title,
            'collection_type_id' => $collectionType->id,
        ]);
    }

    private function makeLocation(
        string $code
    ): Location {
        return Location::create([
            'code' => $code,
            'name' => "Location {$code}",
        ]);
    }

    private function makeCopy(
        Book $book,
        Location $location,
        string $barcode,
        string $status = 'AVAILABLE'
    ): BookCopy {
        return BookCopy::create([
            'book_id' => $book->id,
            'barcode' => $barcode,
            'location_id' => $location->id,
            'status' => $status,
            'condition' => 'GOOD',
        ]);
    }

    public function test_guest_cannot_access_member_report(): void
    {
        $this->getJson('/api/reports/members')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_member_report(): void
    {
        $this->actingAs(
            $this->memberUser,
            'sanctum'
        )->getJson('/api/reports/members')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_member_report(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/members')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_member_report(): void
    {
        $this->actingAs(
            $this->superAdmin,
            'sanctum'
        )->getJson('/api/reports/members')
            ->assertStatus(200);
    }

    public function test_member_report_returns_expected_structure(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/members');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_members',
                        'active_count',
                        'inactive_count',
                        'suspended_count',
                        'period_members',
                        'status_breakdown' => [
                            'ACTIVE',
                            'INACTIVE',
                            'SUSPENDED',
                        ],
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_member_report_returns_correct_summary(): void
    {
        $this->makeMember('MEM-API-001');
        $this->makeMember('MEM-API-002');

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/members');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('data.summary.total_members')
        );

        $this->assertEquals(
            2,
            $response->json('data.summary.active_count')
        );
    }

    public function test_member_report_can_filter_by_status(): void
    {
        $member1 = $this->makeMember('MEM-API-010');
        $member2 = $this->makeMember('MEM-API-011');

        $member2->update([
            'status' => 'SUSPENDED',
        ]);

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/members?status=SUSPENDED'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_members')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.suspended_count')
        );
    }

    public function test_member_report_can_filter_by_date_range(): void
    {
        $old = $this->makeMember('MEM-API-020');

        $old->update([
            'joined_at' => '2026-08-31',
        ]);

        $this->makeMember('MEM-API-021');

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/members'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_members')
        );
    }

    public function test_member_report_rejects_invalid_status(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/members?status=INVALID'
        )->assertStatus(422);
    }

    public function test_member_report_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/members'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }

    public function test_guest_cannot_access_catalog_report(): void
    {
        $this->getJson('/api/reports/catalog')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_catalog_report(): void
    {
        $this->actingAs(
            $this->memberUser,
            'sanctum'
        )->getJson('/api/reports/catalog')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_catalog_report(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/catalog')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_catalog_report(): void
    {
        $this->actingAs(
            $this->superAdmin,
            'sanctum'
        )->getJson('/api/reports/catalog')
            ->assertStatus(200);
    }

    public function test_catalog_report_returns_expected_structure(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/catalog');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_books',
                        'total_copies',
                        'available_copies',
                        'borrowed_copies',
                        'reserved_copies',
                        'lost_copies',
                        'damaged_copies',
                        'maintenance_copies',
                        'status_breakdown' => [
                            'AVAILABLE',
                            'BORROWED',
                            'RESERVED',
                            'LOST',
                            'DAMAGED',
                            'MAINTENANCE',
                        ],
                    ],
                    'filters_applied' => [
                        'status',
                        'location_id',
                    ],
                ],
            ]);
    }

    public function test_catalog_report_returns_correct_summary(): void
    {
        $location = $this->makeLocation('LOC-API-001');

        $book1 = $this->makeBook('Catalog Book One');
        $book2 = $this->makeBook('Catalog Book Two');

        $this->makeCopy(
            $book1,
            $location,
            'BC-API-001',
            'AVAILABLE'
        );

        $this->makeCopy(
            $book1,
            $location,
            'BC-API-002',
            'BORROWED'
        );

        $this->makeCopy(
            $book2,
            $location,
            'BC-API-003',
            'DAMAGED'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/catalog');

        $response->assertStatus(200);

        $this->assertEquals(
            2,
            $response->json('data.summary.total_books')
        );

        $this->assertEquals(
            3,
            $response->json('data.summary.total_copies')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.available_copies')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.borrowed_copies')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.damaged_copies')
        );
    }

    public function test_catalog_report_can_filter_by_status(): void
    {
        $location = $this->makeLocation('LOC-API-002');
        $book = $this->makeBook('Catalog Status Book');

        $this->makeCopy(
            $book,
            $location,
            'BC-API-010',
            'AVAILABLE'
        );

        $this->makeCopy(
            $book,
            $location,
            'BC-API-011',
            'LOST'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/catalog?status=LOST'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_copies')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.lost_copies')
        );
    }

    public function test_catalog_report_can_filter_by_location(): void
    {
        $location1 = $this->makeLocation('LOC-API-003');
        $location2 = $this->makeLocation('LOC-API-004');
        $book = $this->makeBook('Catalog Location Book');

        $this->makeCopy(
            $book,
            $location1,
            'BC-API-020'
        );

        $this->makeCopy(
            $book,
            $location2,
            'BC-API-021'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/catalog?location_id='.$location1->id
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_copies')
        );

        $this->assertEquals(
            $location1->id,
            $response->json('data.filters_applied.location_id')
        );
    }

    public function test_catalog_report_rejects_invalid_status(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/catalog?status=INVALID'
        )->assertStatus(422);
    }

    public function test_catalog_report_rejects_invalid_location(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/catalog?location_id=999999'
        )->assertStatus(422);
    }
}
