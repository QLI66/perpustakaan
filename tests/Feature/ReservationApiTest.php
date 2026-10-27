<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $memberUser;

    private Member $member;

    private User $otherMemberUser;

    private Member $otherMember;

    private User $staffUser;

    private User $adminUser;

    private Book $book;

    private BookCopy $bookCopy;

    private BookCopy $otherBookCopy;

    private MemberType $memberType;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);
        $this->location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $this->memberUser = User::create([
            'name' => 'Member User',
            'email' => 'member@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->memberUser->assignRole('MEMBER');

        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'MEM001',
            'name' => 'John Doe',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $this->otherMemberUser = User::create([
            'name' => 'Other Member User',
            'email' => 'other-member@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->otherMemberUser->assignRole('MEMBER');

        $this->otherMember = Member::create([
            'user_id' => $this->otherMemberUser->id,
            'member_number' => 'MEM002',
            'name' => 'Jane Doe',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
            'status' => 'ACTIVE',
        ]);

        $this->staffUser = User::create([
            'name' => 'Library Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->staffUser->assignRole('LIBRARY_STAFF');

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
        $this->adminUser->assignRole('SUPER_ADMIN');

        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);
        $this->book = Book::create([
            'title' => 'Test Book',
            'isbn' => '123456789',
            'collection_type_id' => $collectionType->id,
        ]);

        $this->bookCopy = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC001',
            'location_id' => $this->location->id,
            'status' => 'AVAILABLE',
        ]);

        $this->otherBookCopy = BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => 'BC002',
            'location_id' => $this->location->id,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_guest_gets_401()
    {
        $this->getJson('/api/reservations')->assertStatus(401);
        $this->postJson('/api/reservations', [])->assertStatus(401);
    }

    public function test_member_can_create_reservation_for_self()
    {
        Sanctum::actingAs($this->memberUser);

        $response = $this->postJson('/api/reservations', [
            'book_id' => $this->book->id,
            'notes' => 'Need this soon',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.book.id', $this->book->id)
            ->assertJsonPath('data.member.id', $this->member->id);

        $this->assertDatabaseHas('reservations', [
            'member_id' => $this->member->id,
            'book_id' => $this->book->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_member_can_view_own_reservations()
    {
        Sanctum::actingAs($this->memberUser);
        $this->postJson('/api/reservations', ['book_id' => $this->book->id]);

        $response = $this->getJson('/api/me/reservations');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_staff_can_view_all_reservations()
    {
        // Member creates a reservation
        Sanctum::actingAs($this->memberUser);
        $this->postJson('/api/reservations', ['book_id' => $this->book->id]);

        // Staff views it
        Sanctum::actingAs($this->staffUser);
        $response = $this->getJson('/api/reservations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    }

    public function test_member_can_cancel_own_reservation()
    {
        Sanctum::actingAs($this->memberUser);
        $res = $this->postJson('/api/reservations', ['book_id' => $this->book->id])->json('data.id');

        $this->postJson("/api/reservations/{$res}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertDatabaseHas('reservations', [
            'id' => $res,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_staff_can_assign_copy()
    {
        // Member creates reservation
        Sanctum::actingAs($this->memberUser);
        $res = $this->postJson('/api/reservations', ['book_id' => $this->book->id])->json('data.id');

        // Staff assigns copy
        Sanctum::actingAs($this->staffUser);
        $response = $this->postJson("/api/reservations/{$res}/assign", [
            'book_copy_id' => $this->bookCopy->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonPath('data.assigned_copy.id', $this->bookCopy->id);

        $this->assertDatabaseHas('reservations', [
            'id' => $res,
            'status' => 'PENDING',
            'book_copy_id' => $this->bookCopy->id,
        ]);

        $this->assertDatabaseHas('book_copies', [
            'id' => $this->bookCopy->id,
            'status' => 'RESERVED',
        ]);
    }

    public function test_cannot_assign_to_non_pending_reservation()
    {
        Sanctum::actingAs($this->memberUser);
        $res = $this->postJson('/api/reservations', ['book_id' => $this->book->id])->json('data.id');
        $this->postJson("/api/reservations/{$res}/cancel");

        Sanctum::actingAs($this->staffUser);
        $this->postJson("/api/reservations/{$res}/assign", [
            'book_copy_id' => $this->bookCopy->id,
        ])->assertStatus(422); // BusinessRuleException
    }

    public function test_member_reservation_list_contains_only_the_authenticated_members_reservations(): void
    {
        $ownReservation = $this->makeReservation($this->member, 'RES-OWN-001');
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-001');

        Sanctum::actingAs($this->memberUser);

        $this->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $ownReservation->id)
            ->assertJsonMissing([
                'reservation_number' => $otherReservation->reservation_number,
            ]);
    }

    public function test_member_cannot_see_another_members_reservation_in_list(): void
    {
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-002');

        Sanctum::actingAs($this->memberUser);

        $this->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonMissing(['id' => $otherReservation->id])
            ->assertJsonMissing(['reservation_number' => $otherReservation->reservation_number]);
    }

    public function test_member_cannot_view_another_members_reservation_detail(): void
    {
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-003');

        Sanctum::actingAs($this->memberUser);

        $this->getJson("/api/reservations/{$otherReservation->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_cancel_another_members_reservation(): void
    {
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-004');

        Sanctum::actingAs($this->memberUser);

        $this->postJson("/api/reservations/{$otherReservation->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $otherReservation->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_member_cannot_restore_another_members_reservation(): void
    {
        $otherReservation = $this->makeReservation(
            $this->otherMember,
            'RES-OTHER-005',
            'CANCELLED'
        );

        Sanctum::actingAs($this->memberUser);

        $this->postJson("/api/reservations/{$otherReservation->id}/restore")
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $otherReservation->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_member_cannot_assign_a_copy_to_a_reservation(): void
    {
        $reservation = $this->makeReservation($this->member, 'RES-OWN-006');

        Sanctum::actingAs($this->memberUser);

        $this->postJson("/api/reservations/{$reservation->id}/assign", [
            'book_copy_id' => $this->bookCopy->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('book_copies', [
            'id' => $this->bookCopy->id,
            'status' => 'AVAILABLE',
        ]);
    }

    public function test_staff_list_includes_reservations_for_all_members(): void
    {
        $ownReservation = $this->makeReservation($this->member, 'RES-OWN-007');
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-007');

        Sanctum::actingAs($this->staffUser);

        $this->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['id' => $ownReservation->id])
            ->assertJsonFragment(['id' => $otherReservation->id]);
    }

    public function test_staff_can_assign_a_copy_to_a_reservation(): void
    {
        $reservation = $this->makeReservation($this->member, 'RES-OWN-008');

        Sanctum::actingAs($this->staffUser);

        $this->postJson("/api/reservations/{$reservation->id}/assign", [
            'book_copy_id' => $this->bookCopy->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $reservation->id)
            ->assertJsonPath('data.assigned_copy.id', $this->bookCopy->id);

        $this->assertDatabaseHas('book_copies', [
            'id' => $this->bookCopy->id,
            'status' => 'RESERVED',
        ]);
    }

    public function test_staff_can_cancel_and_restore_a_reservation(): void
    {
        $reservation = $this->makeReservation($this->member, 'RES-OWN-011');

        Sanctum::actingAs($this->staffUser);

        $this->postJson("/api/reservations/{$reservation->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->postJson("/api/reservations/{$reservation->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_super_admin_can_access_reservation_management(): void
    {
        $reservation = $this->makeReservation($this->otherMember, 'RES-OTHER-009');

        Sanctum::actingAs($this->adminUser);

        $this->getJson('/api/reservations')
            ->assertOk()
            ->assertJsonFragment(['id' => $reservation->id]);

        $this->getJson("/api/reservations/{$reservation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $reservation->id);

        $this->postJson("/api/reservations/{$reservation->id}/assign", [
            'book_copy_id' => $this->bookCopy->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assigned_copy.id', $this->bookCopy->id);
    }

    public function test_member_direct_api_request_cannot_assign_another_members_reservation(): void
    {
        $otherReservation = $this->makeReservation($this->otherMember, 'RES-OTHER-010');

        Sanctum::actingAs($this->memberUser);

        $this->postJson("/api/reservations/{$otherReservation->id}/assign", [
            'book_copy_id' => $this->otherBookCopy->id,
        ])->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id' => $otherReservation->id,
            'book_copy_id' => null,
        ]);
        $this->assertDatabaseHas('book_copies', [
            'id' => $this->otherBookCopy->id,
            'status' => 'AVAILABLE',
        ]);
    }

    private function makeReservation(
        Member $member,
        string $reservationNumber,
        string $status = 'PENDING'
    ): Reservation {
        return Reservation::create([
            'reservation_number' => $reservationNumber,
            'member_id' => $member->id,
            'book_id' => $this->book->id,
            'status' => $status,
            'reserved_at' => now(),
        ]);
    }
}
