<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\Reservation;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReservationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservationService $service;

    private LoanService $loanService;

    private Member $member;

    private Book $book;

    private BookCopy $bookCopy;

    private MemberType $memberType;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReservationService::class);
        $this->loanService = app(LoanService::class);

        $this->memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);
        $this->location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $user = User::factory()->create();
        $this->member = Member::create([
            'user_id' => $user->id,
            'member_number' => 'MEM001',
            'name' => 'John Doe',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
            'status' => 'ACTIVE',
        ]);

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
    }

    public function test_can_create_reservation_for_eligible_member()
    {
        $reservation = $this->service->createReservation($this->member->id, $this->book->id);

        $this->assertEquals('PENDING', $reservation->status);
        $this->assertNotNull($reservation->reservation_number);
        $this->assertEquals($this->member->id, $reservation->member_id);
        $this->assertEquals($this->book->id, $reservation->book_id);
    }

    public function test_cannot_create_duplicate_active_reservation()
    {
        $this->service->createReservation($this->member->id, $this->book->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member already has an active reservation for this book.');

        $this->service->createReservation($this->member->id, $this->book->id);
    }

    public function test_member_with_unpaid_fine_cannot_reserve()
    {
        $loan = Loan::create([
            'loan_number' => 'L1',
            'member_id' => $this->member->id,
            'borrowed_at' => now(),
            'status' => 'COMPLETED',
        ]);

        $loanItem = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $this->bookCopy->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'returned_at' => now(),
            'status' => 'RETURNED',
        ]);

        Fine::create([
            'loan_item_id' => $loanItem->id,
            'amount' => 1000,
            'rate_per_day' => 1000,
            'calculated_amount' => 1000,
            'late_days' => 1,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Outstanding fines must be paid');

        $this->service->createReservation($this->member->id, $this->book->id);
    }

    public function test_assign_enforces_fifo_strictly()
    {
        // Member 1 reserves
        $member1 = $this->member;
        $res1 = $this->service->createReservation($member1->id, $this->book->id, now()->subMinutes(10));

        // Member 2 reserves
        $member2 = Member::create([
            'member_number' => 'MEM002',
            'name' => 'Jane Doe',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
        ]);
        $res2 = $this->service->createReservation($member2->id, $this->book->id, now()->subMinutes(5));

        // Try to assign to member 2 first (bypass FIFO)
        try {
            $this->service->assignCopy($res2->id, $this->bookCopy->id);
            $this->fail('Should have thrown BusinessRuleException for FIFO violation');
        } catch (BusinessRuleException $e) {
            $this->assertEquals('Reservation assignment must follow FIFO order. Another member reserved this book earlier.', $e->getMessage());
        }

        // Assign to member 1 (success)
        $this->service->assignCopy($res1->id, $this->bookCopy->id);

        $res1->refresh();
        $this->bookCopy->refresh();

        $this->assertEquals('PENDING', $res1->status);
        $this->assertEquals('RESERVED', $this->bookCopy->status);
    }

    public function test_loan_service_accepts_reserved_copy_with_ready_reservation()
    {
        $res = $this->service->createReservation($this->member->id, $this->book->id);
        $this->service->assignCopy($res->id, $this->bookCopy->id);

        // The member borrows the book
        $loan = $this->loanService->createLoan($this->member->id, [$this->bookCopy->id]);

        $res->refresh();
        $this->bookCopy->refresh();

        $this->assertEquals('FULFILLED', $res->status);
        $this->assertEquals('BORROWED', $this->bookCopy->status);
        $this->assertEquals(1, $loan->items->count());
    }

    public function test_loan_service_rejects_reserved_copy_for_wrong_member()
    {
        $res = $this->service->createReservation($this->member->id, $this->book->id);
        $this->service->assignCopy($res->id, $this->bookCopy->id);

        $otherMember = Member::create([
            'member_number' => 'MEM002',
            'name' => 'Jane',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book copy is reserved for another member or reservation has expired.');

        $this->loanService->createLoan($otherMember->id, [$this->bookCopy->id]);
    }

    public function test_expire_reservations_command_logic()
    {
        $res = $this->service->createReservation($this->member->id, $this->book->id);
        $this->service->assignCopy($res->id, $this->bookCopy->id, now()->subDays(4)); // assigned 4 days ago

        $res->refresh();
        $this->assertTrue($res->expires_at->isPast());
        $this->assertEquals('PENDING', $res->status);

        $expiredCount = $this->service->expireReservations();

        $res->refresh();
        $this->bookCopy->refresh();

        $this->assertEquals(1, $expiredCount);
        $this->assertEquals('EXPIRED', $res->status);
        $this->assertEquals('AVAILABLE', $this->bookCopy->status);
    }

    public function test_reservation_before_expiry_stays_pending_and_copy_reserved(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-FUTURE',
            'PENDING',
            now()->addMinute()
        );

        $this->assertSame(0, $this->service->expireReservations());

        $this->assertSame('PENDING', $reservation->fresh()->status);
        $this->assertSame('RESERVED', $this->bookCopy->fresh()->status);
    }

    public function test_expired_reservation_is_expired_and_reserved_copy_is_released(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-PAST',
            'PENDING',
            now()->subSecond()
        );

        $this->assertSame(1, $this->service->expireReservations());

        $this->assertSame('EXPIRED', $reservation->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
    }

    public function test_expired_legacy_ready_reservation_releases_its_reserved_copy(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-READY',
            'READY',
            now()->subSecond()
        );

        $this->assertSame(1, $this->service->expireReservations());

        $this->assertSame('EXPIRED', $reservation->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
    }

    public function test_expired_reservation_without_assigned_copy_is_ignored(): void
    {
        $reservation = Reservation::create([
            'reservation_number' => 'RES-EXP-NOCOPY',
            'member_id' => $this->member->id,
            'book_id' => $this->book->id,
            'status' => 'PENDING',
            'reserved_at' => now()->subDays(4),
            'expires_at' => now()->subSecond(),
        ]);

        $this->assertSame(0, $this->service->expireReservations());
        $this->assertSame('PENDING', $reservation->fresh()->status);
        $this->assertNull($reservation->fresh()->book_copy_id);
    }

    public function test_cancelled_reservation_is_not_processed_by_expiry(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-CANCEL',
            'CANCELLED',
            now()->subSecond()
        );

        $this->assertSame(0, $this->service->expireReservations());
        $this->assertSame('CANCELLED', $reservation->fresh()->status);
        $this->assertSame('RESERVED', $this->bookCopy->fresh()->status);
    }

    public function test_fulfilled_reservation_is_not_processed_by_expiry(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-FULFILLED',
            'FULFILLED',
            now()->subSecond()
        );

        $this->assertSame(0, $this->service->expireReservations());
        $this->assertSame('FULFILLED', $reservation->fresh()->status);
        $this->assertSame('RESERVED', $this->bookCopy->fresh()->status);
    }

    public function test_already_expired_reservation_is_idempotent(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-IDEMPOTENT',
            'EXPIRED',
            now()->subSecond(),
            'AVAILABLE'
        );
        $updatedAt = $reservation->updated_at;

        $this->assertSame(0, $this->service->expireReservations());

        $this->assertSame('EXPIRED', $reservation->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
        $this->assertEquals($updatedAt, $reservation->fresh()->updated_at);
    }

    public function test_all_expired_reservations_are_processed(): void
    {
        $first = $this->makeAssignedReservation(
            'RES-EXP-MULTI-1',
            'PENDING',
            now()->subMinute()
        );
        $secondCopy = $this->createBookCopy('BC-EXP-2');
        $second = $this->makeAssignedReservation(
            'RES-EXP-MULTI-2',
            'PENDING',
            now()->subMinute(),
            'RESERVED',
            $secondCopy,
            $this->createMember('MEM-EXP-MULTI')
        );

        $this->assertSame(2, $this->service->expireReservations());
        $this->assertSame('EXPIRED', $first->fresh()->status);
        $this->assertSame('EXPIRED', $second->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
        $this->assertSame('AVAILABLE', $secondCopy->fresh()->status);
    }

    public function test_only_expired_reservations_are_processed(): void
    {
        $expired = $this->makeAssignedReservation(
            'RES-EXP-MIXED-1',
            'PENDING',
            now()->subSecond()
        );
        $futureCopy = $this->createBookCopy('BC-EXP-FUTURE');
        $future = $this->makeAssignedReservation(
            'RES-EXP-MIXED-2',
            'PENDING',
            now()->addMinute(),
            'RESERVED',
            $futureCopy,
            $this->createMember('MEM-EXP-MIXED')
        );

        $this->assertSame(1, $this->service->expireReservations());
        $this->assertSame('EXPIRED', $expired->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
        $this->assertSame('PENDING', $future->fresh()->status);
        $this->assertSame('RESERVED', $futureCopy->fresh()->status);
    }

    public function test_expiry_only_releases_copies_that_are_still_reserved(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-COPY-STATUS',
            'PENDING',
            now()->subSecond(),
            'BORROWED'
        );

        $this->assertSame(1, $this->service->expireReservations());
        $this->assertSame('EXPIRED', $reservation->fresh()->status);
        $this->assertSame('BORROWED', $this->bookCopy->fresh()->status);
    }

    public function test_expiry_command_is_idempotent_when_run_twice(): void
    {
        $reservation = $this->makeAssignedReservation(
            'RES-EXP-COMMAND',
            'PENDING',
            now()->subSecond()
        );

        $this->assertSame(0, Artisan::call('reservations:expire'));
        $firstRunOutput = Artisan::output();
        $firstRunUpdatedAt = $reservation->fresh()->updated_at;
        $this->assertSame(0, Artisan::call('reservations:expire'));
        $secondRunOutput = Artisan::output();

        $this->assertStringContainsString('Successfully expired 1 reservations.', $firstRunOutput);
        $this->assertStringContainsString('Successfully expired 0 reservations.', $secondRunOutput);
        $this->assertSame('EXPIRED', $reservation->fresh()->status);
        $this->assertSame('AVAILABLE', $this->bookCopy->fresh()->status);
        $this->assertEquals($firstRunUpdatedAt, $reservation->fresh()->updated_at);
    }

    public function test_expiry_command_is_registered_once_to_run_every_minute(): void
    {
        $this->assertSame(0, Artisan::call('schedule:list'));
        $scheduleOutput = Artisan::output();

        $this->assertSame(1, substr_count($scheduleOutput, 'reservations:expire'));
        $this->assertStringContainsString('* * * * *', $scheduleOutput);
    }

    public function test_cancellation_releases_copy_atomically()
    {
        $res = $this->service->createReservation($this->member->id, $this->book->id);
        $this->service->assignCopy($res->id, $this->bookCopy->id);

        $this->service->cancelReservation($res->id);

        $res->refresh();
        $this->bookCopy->refresh();

        $this->assertEquals('CANCELLED', $res->status);
        $this->assertEquals('AVAILABLE', $this->bookCopy->status);
    }

    private function makeAssignedReservation(
        string $reservationNumber,
        string $status,
        Carbon $expiresAt,
        string $copyStatus = 'RESERVED',
        ?BookCopy $bookCopy = null,
        ?Member $member = null
    ): Reservation {
        $bookCopy ??= $this->bookCopy;
        $member ??= $this->member;
        $bookCopy->status = $copyStatus;
        $bookCopy->save();

        return Reservation::create([
            'reservation_number' => $reservationNumber,
            'member_id' => $member->id,
            'book_id' => $this->book->id,
            'book_copy_id' => $bookCopy->id,
            'status' => $status,
            'reserved_at' => now()->subDays(4),
            'expires_at' => $expiresAt,
        ]);
    }

    private function createBookCopy(string $barcode): BookCopy
    {
        return BookCopy::create([
            'book_id' => $this->book->id,
            'barcode' => $barcode,
            'location_id' => $this->location->id,
            'status' => 'AVAILABLE',
        ]);
    }

    private function createMember(string $memberNumber): Member
    {
        return Member::create([
            'member_number' => $memberNumber,
            'name' => 'Reservation Expiry Test Member',
            'member_type_id' => $this->memberType->id,
            'joined_at' => now(),
            'status' => 'ACTIVE',
        ]);
    }
}
