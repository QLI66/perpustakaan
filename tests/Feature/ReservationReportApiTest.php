<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\CollectionType;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservationReportApiTest extends TestCase
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
            'email' => 'reservation-report-admin@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create([
            'name' => 'Report Library Staff',
            'email' => 'reservation-report-staff@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $this->memberUser = User::create([
            'name' => 'Report Member',
            'email' => 'reservation-report-member@library.test',
            'password' => Hash::make('password'),
        ]);

        $this->memberUser->assignRole('MEMBER');
    }

    private function makeMember(
        string $number = 'MEM-REP-001'
    ): Member {
        $memberType = MemberType::create([
            'code' => 'MT-'.uniqid(),
            'name' => 'Student',
        ]);

        return Member::create([
            'member_number' => $number,
            'name' => "Member {$number}",
            'member_type_id' => $memberType->id,
            'joined_at' => '2026-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeBook(
        string $title = 'Reservation Report Book'
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

    private function makeReservation(
        string $number,
        Member $member,
        Book $book,
        string $status,
        string $reservedAt
    ): Reservation {
        return Reservation::create([
            'reservation_number' => $number,
            'member_id' => $member->id,
            'book_id' => $book->id,
            'book_copy_id' => null,
            'status' => $status,
            'reserved_at' => $reservedAt,
            'ready_at' => null,
            'expires_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => null,
        ]);
    }

    public function test_guest_cannot_access_reservation_report(): void
    {
        $this->getJson('/api/reports/reservations')
            ->assertStatus(401);
    }

    public function test_member_cannot_access_reservation_report(): void
    {
        $this->actingAs(
            $this->memberUser,
            'sanctum'
        )->getJson('/api/reports/reservations')
            ->assertStatus(403);
    }

    public function test_library_staff_can_access_reservation_report(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/reservations')
            ->assertStatus(200);
    }

    public function test_super_admin_can_access_reservation_report(): void
    {
        $this->actingAs(
            $this->superAdmin,
            'sanctum'
        )->getJson('/api/reports/reservations')
            ->assertStatus(200);
    }

    public function test_reservation_report_returns_expected_structure(): void
    {
        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/reservations');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'total_reservations',
                        'pending_count',
                        'ready_count',
                        'fulfilled_count',
                        'cancelled_count',
                        'expired_count',
                        'active_count',
                        'fulfillment_rate',
                        'status_breakdown' => [
                            'PENDING',
                            'READY',
                            'FULFILLED',
                            'CANCELLED',
                            'EXPIRED',
                        ],
                        'period_reservations',
                    ],
                    'filters_applied' => [
                        'date_from',
                        'date_to',
                        'member_id',
                        'book_id',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_reservation_report_returns_correct_summary(): void
    {
        $member1 = $this->makeMember('MEM-REP-003');
        $member2 = $this->makeMember('MEM-REP-004');
        $member3 = $this->makeMember('MEM-REP-005');

        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-910001',
            $member1,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910002',
            $member2,
            $book,
            'READY',
            '2026-09-21 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910003',
            $member3,
            $book,
            'FULFILLED',
            '2026-09-22 10:00:00'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson('/api/reports/reservations');

        $response->assertStatus(200);

        $this->assertEquals(
            3,
            $response->json('data.summary.total_reservations')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.pending_count')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.ready_count')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.fulfilled_count')
        );

        $this->assertEquals(
            2,
            $response->json('data.summary.active_count')
        );

        $this->assertEquals(
            33.33,
            $response->json('data.summary.fulfillment_rate')
        );
    }

    public function test_reservation_report_can_filter_by_status(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-910010',
            $member,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910011',
            $member,
            $book,
            'CANCELLED',
            '2026-09-21 10:00:00'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?status=CANCELLED'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_reservations')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.cancelled_count')
        );
    }

    public function test_reservation_report_can_filter_by_member(): void
    {
        $member1 = $this->makeMember('MEM-REP-001');
        $member2 = $this->makeMember('MEM-REP-002');
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-910020',
            $member1,
            $book,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910021',
            $member2,
            $book,
            'FULFILLED',
            '2026-09-21 10:00:00'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?member_id='.$member1->id
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_reservations')
        );
    }

    public function test_reservation_report_can_filter_by_book(): void
    {
        $member = $this->makeMember();
        $book1 = $this->makeBook('Book One');
        $book2 = $this->makeBook('Book Two');

        $this->makeReservation(
            'RES-2026-910030',
            $member,
            $book1,
            'PENDING',
            '2026-09-20 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910031',
            $member,
            $book2,
            'READY',
            '2026-09-21 10:00:00'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?book_id='.$book2->id
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_reservations')
        );

        $this->assertEquals(
            1,
            $response->json('data.summary.ready_count')
        );
    }

    public function test_reservation_report_can_filter_by_date_range(): void
    {
        $member = $this->makeMember();
        $book = $this->makeBook();

        $this->makeReservation(
            'RES-2026-910040',
            $member,
            $book,
            'FULFILLED',
            '2026-08-31 10:00:00'
        );

        $this->makeReservation(
            'RES-2026-910041',
            $member,
            $book,
            'READY',
            '2026-09-10 10:00:00'
        );

        $response = $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations'
            .'?date_from=2026-09-01'
            .'&date_to=2026-09-30'
        );

        $response->assertStatus(200);

        $this->assertEquals(
            1,
            $response->json('data.summary.total_reservations')
        );

        $this->assertEquals(
            '2026-09-01',
            $response->json('data.filters_applied.date_from')
        );

        $this->assertEquals(
            '2026-09-30',
            $response->json('data.filters_applied.date_to')
        );
    }

    public function test_reservation_report_rejects_invalid_status(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?status=INVALID'
        )->assertStatus(422);
    }

    public function test_reservation_report_rejects_invalid_member(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?member_id=999999'
        )->assertStatus(422);
    }

    public function test_reservation_report_rejects_invalid_book(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations?book_id=999999'
        )->assertStatus(422);
    }

    public function test_reservation_report_rejects_invalid_date_range(): void
    {
        $this->actingAs(
            $this->libraryStaff,
            'sanctum'
        )->getJson(
            '/api/reports/reservations'
            .'?date_from=2026-09-30'
            .'&date_to=2026-09-01'
        )->assertStatus(422);
    }
}
