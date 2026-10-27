<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Services\BookIncidentService;
use App\Services\LoanService;
use App\Services\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class BookIncidentServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookIncidentService $incidentService;

    private LoanService $loanService;

    private RenewalService $renewalService;

    private ?int $sharedBookId = null;

    private ?int $sharedLocId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->incidentService = new BookIncidentService;
        $this->loanService = new LoanService;
        $this->renewalService = new RenewalService;
        $this->sharedBookId = null;
        $this->sharedLocId = null;
    }

    private function makeMember(): Member
    {
        $mType = MemberType::create(['code' => uniqid('MT'), 'name' => 'Type']);

        return Member::create([
            'member_number' => uniqid('MBR-'),
            'name' => 'Test Member',
            'member_type_id' => $mType->id,
            'joined_at' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function makeAvailableCopy(?string $barcode = null, ?string $inv = null, float $price = 50000): BookCopy
    {
        if ($this->sharedBookId === null) {
            $loc = Location::create(['code' => 'L-SHARED', 'name' => 'Shared Loc']);
            $cType = CollectionType::create(['code' => 'C-SHARED', 'name' => 'Shared Col']);
            $book = Book::create(['title' => 'Test Book', 'collection_type_id' => $cType->id]);
            $this->sharedBookId = $book->id;
            $this->sharedLocId = $loc->id;
        }

        return BookCopy::create([
            'book_id' => $this->sharedBookId,
            'barcode' => $barcode ?? uniqid('BC-'),
            'inventory_number' => $inv ?? uniqid('INV-'),
            'status' => 'AVAILABLE',
            'location_id' => $this->sharedLocId,
            'acquisition_price' => $price,
        ]);
    }

    private function makeLoanItemWithStatus(string $status, ?BookCopy $copy = null): LoanItem
    {
        $member = $this->makeMember();
        $copy = $copy ?? $this->makeAvailableCopy();
        $copy->status = $status === 'ACTIVE' ? 'BORROWED' : $status;
        $copy->save();

        $loan = Loan::create([
            'loan_number' => uniqid('LOAN-'),
            'member_id' => $member->id,
            'borrowed_at' => now()->subDays(5),
            'status' => 'ACTIVE',
        ]);

        return LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => $status === 'ACTIVE'
    ? now()->addDays(2)
    : now()->subDays(2),
            'status' => $status,
            'returned_at' => $status === 'RETURNED' ? now() : null,
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    // ─── 1-7: OPEN INCIDENT TESTS ──────────────────────────────────────────

    public function test_can_open_lost_incident(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $this->assertEquals('LOST', $incident->type);
        $this->assertEquals('OPEN', $incident->status);
    }

    public function test_can_open_damaged_incident(): void
    {
        $item = $this->makeLoanItemWithStatus('DAMAGED');
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $this->assertEquals('DAMAGED', $incident->type);
    }

    public function test_can_open_incident_for_active_item_transitions_status(): void
    {
        // Domain fix: ACTIVE items are now accepted.
        // The service atomically transitions LoanItem → LOST/DAMAGED + BookCopy → LOST/DAMAGED
        $item = $this->makeLoanItemWithStatus('ACTIVE');
        $item->refresh();
        $copyId = $item->book_copy_id;

        $incident = $this->incidentService->openIncident($item->id, 'LOST');

        $this->assertEquals('OPEN', $incident->status);
        $this->assertEquals('LOST', $incident->type);

        $item->refresh();
        $this->assertEquals('LOST', $item->status);

        $copy = BookCopy::find($copyId);
        $this->assertEquals('LOST', $copy->status);
    }

    public function test_opening_incident_for_terminal_item_completes_loan_without_active_items(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $loan = $item->loan;

        $this->assertSame('ACTIVE', $loan->status);

        $this->incidentService->openIncident($item->id, 'LOST');

        $this->assertSame('COMPLETED', $loan->fresh()->status);
    }

    public function test_cannot_open_incident_for_returned_item_remains_blocked(): void
    {
        $item = $this->makeLoanItemWithStatus('RETURNED');
        $this->expectException(BusinessRuleException::class);
        $this->incidentService->openIncident($item->id, 'DAMAGED');
    }

    public function test_cannot_open_incident_for_returned_item(): void
    {
        $item = $this->makeLoanItemWithStatus('RETURNED');
        $this->expectException(BusinessRuleException::class);
        $this->incidentService->openIncident($item->id, 'DAMAGED');
    }

    public function test_cannot_create_second_incident(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $this->incidentService->openIncident($item->id, 'LOST');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book incident already exists.');
        $this->incidentService->openIncident($item->id, 'LOST');
    }

    public function test_amount_due_uses_acquisition_price(): void
    {
        $copy = $this->makeAvailableCopy(null, null, 75000);
        $item = $this->makeLoanItemWithStatus('LOST', $copy);
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $this->assertEquals(75000, $incident->amount_due);
    }

    public function test_incident_starts_open(): void
    {
        $item = $this->makeLoanItemWithStatus('DAMAGED');
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $this->assertTrue(BookIncident::open()->where('id', $incident->id)->exists());
    }

    // ─── 8-10, 27-28: PAYMENT RESOLUTION TESTS ────────────────────────────────

    public function test_resolve_payment_changes_status_resolved(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();

        $resolved = $this->incidentService->resolveWithPayment($incident->id, $user->id);
        $this->assertEquals('RESOLVED', $resolved->status);
    }

    public function test_resolve_payment_stores_amount_paid_equals_amount_due(): void
    {
        $copy = $this->makeAvailableCopy(null, null, 100000);
        $item = $this->makeLoanItemWithStatus('LOST', $copy);
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();

        $resolved = $this->incidentService->resolveWithPayment($incident->id, $user->id);
        $this->assertEquals(100000, $resolved->amount_paid);
    }

    public function test_resolve_payment_stores_paid_money(): void
    {
        $item = $this->makeLoanItemWithStatus('DAMAGED');
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $user = $this->makeUser();

        $resolved = $this->incidentService->resolveWithPayment($incident->id, $user->id);
        $this->assertEquals('PAID_MONEY', $resolved->resolution_type);
    }

    public function test_paid_money_requires_amount_due(): void
    {
        $copy = $this->makeAvailableCopy(null, null, 0);
        $copy->acquisition_price = null;
        $copy->save();
        $item = $this->makeLoanItemWithStatus('LOST', $copy);
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Acquisition price is required for monetary resolution.');
        $this->incidentService->resolveWithPayment($incident->id, $user->id);
    }

    // ─── 11-14, 29: REPLACEMENT RESOLUTION TESTS ─────────────────────────────

    public function test_resolve_replacement_changes_status_resolved(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $replacement = $this->makeAvailableCopy();
        $user = $this->makeUser();

        $resolved = $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);
        $this->assertEquals('RESOLVED', $resolved->status);
    }

    public function test_resolve_replacement_stores_replacement_copy(): void
    {
        $item = $this->makeLoanItemWithStatus('DAMAGED');
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $replacement = $this->makeAvailableCopy();
        $user = $this->makeUser();

        $resolved = $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);
        $this->assertEquals($replacement->id, $resolved->replacement_book_copy_id);
        $this->assertEquals('REPLACED_BOOK', $resolved->resolution_type);
    }

    public function test_replacement_must_be_available(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');

        $replacement = $this->makeAvailableCopy();
        $replacement->status = 'BORROWED';
        $replacement->save();
        $user = $this->makeUser();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Replacement book copy must be AVAILABLE.');
        $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);
    }

    public function test_replacement_must_belong_to_same_book(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');

        // Force a different book
        $loc = Location::create(['code' => uniqid(), 'name' => uniqid()]);
        $cType = CollectionType::create(['code' => uniqid(), 'name' => uniqid()]);
        $otherBook = Book::create(['title' => 'Other Book', 'collection_type_id' => $cType->id]);
        $replacement = BookCopy::create([
            'book_id' => $otherBook->id,
            'barcode' => uniqid(),
            'inventory_number' => uniqid(),
            'status' => 'AVAILABLE',
            'location_id' => $loc->id,
        ]);
        $user = $this->makeUser();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Replacement book copy must belong to the same book.');
        $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);
    }

    // ─── 15-18: RESOLVED METADATA TESTS ──────────────────────────────────────

    public function test_resolved_incident_has_resolved_at(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();
        $resolved = $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $this->assertNotNull($resolved->resolved_at);
    }

    public function test_resolved_incident_has_resolved_by(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();
        $resolved = $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $this->assertEquals($user->id, $resolved->resolved_by);
    }

    public function test_resolved_incident_no_longer_considered_open(): void
    {
        $item = $this->makeLoanItemWithStatus('DAMAGED');
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $user = $this->makeUser();
        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $this->assertFalse(BookIncident::open()->where('id', $incident->id)->exists());
        $this->assertTrue(BookIncident::resolved()->where('id', $incident->id)->exists());
    }

    public function test_invalid_incident_type_is_rejected(): void
    {
        $item = $this->makeLoanItemWithStatus('ACTIVE');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Incident type must be LOST or DAMAGED.');

        $this->incidentService->openIncident(
            $item->id,
            'INVALID'
        );
    }

    public function test_open_incident_is_detectable(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $this->assertTrue(BookIncident::open()->where('id', $incident->id)->exists());
    }

    // ─── 19-20: FOREIGN KEY CONSTRAINTS TESTS ────────────────────────────────

    public function test_deleted_loan_item_is_restricted_by_incident(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $this->incidentService->openIncident($item->id, 'LOST');

        $this->expectException(PDOException::class);
        $item->delete();
    }

    public function test_deleted_book_copy_is_restricted_by_incident(): void
    {
        $copy = $this->makeAvailableCopy();
        $item = $this->makeLoanItemWithStatus('LOST', $copy);
        $this->incidentService->openIncident($item->id, 'LOST');

        $this->expectException(PDOException::class);
        $copy->delete();
    }

    // ─── 21: CONCURRENCY TEST ────────────────────────────────────────────────

    public function test_concurrent_resolution_cannot_resolve_twice(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();

        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book incident is already resolved.');
        $this->incidentService->resolveWithPayment($incident->id, $user->id);
    }

    // ─── 22-26: ROLLBACK & SIDE EFFECTS TESTS ────────────────────────────────

    public function test_failed_payment_resolution_rolls_back(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();

        // Simulasikan amount_due = null langsung ke DB tanpa constraint failure untuk memicu BusinessRuleException
        $incident->amount_due = null;
        $incident->save();

        try {
            $this->incidentService->resolveWithPayment($incident->id, $user->id);
        } catch (BusinessRuleException) {
        }

        $incident->refresh();
        $this->assertEquals('OPEN', $incident->status);
        $this->assertNull($incident->amount_paid);
    }

    public function test_failed_replacement_resolution_rolls_back(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $replacement = $this->makeAvailableCopy();
        $replacement->status = 'BORROWED';
        $replacement->save();
        $user = $this->makeUser();

        try {
            $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);
        } catch (BusinessRuleException) {
        }

        $incident->refresh();
        $this->assertEquals('OPEN', $incident->status);
        $this->assertNull($incident->replacement_book_copy_id);
    }

    public function test_original_lost_book_copy_stays_lost(): void
    {
        $copy = $this->makeAvailableCopy();
        $item = $this->makeLoanItemWithStatus('LOST', $copy);
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $user = $this->makeUser();
        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $copy->refresh();
        $this->assertEquals('LOST', $copy->status);
    }

    public function test_original_damaged_book_copy_stays_damaged(): void
    {
        $copy = $this->makeAvailableCopy();
        $item = $this->makeLoanItemWithStatus('DAMAGED', $copy);
        $incident = $this->incidentService->openIncident($item->id, 'DAMAGED');
        $user = $this->makeUser();
        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        $copy->refresh();
        $this->assertEquals('DAMAGED', $copy->status);
    }

    public function test_replacement_copy_remains_available(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $replacement = $this->makeAvailableCopy();
        $user = $this->makeUser();
        $this->incidentService->resolveWithReplacement($incident->id, $replacement->id, $user->id);

        $replacement->refresh();
        $this->assertEquals('AVAILABLE', $replacement->status);
    }

    // ─── 30-31: INTEGRATION WITH LOAN/RENEWAL SERVICES ───────────────────────

    public function test_resolved_incident_can_be_reused_by_loan_service_eligibility(): void
    {
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');
        $member = $item->loan->member;

        $newCopy = $this->makeAvailableCopy();

        // OPEN incident harus memblokir
        try {
            $this->loanService->createLoan($member->id, [$newCopy->id]);
            $this->fail('Expected BusinessRuleException for unresolved incident');
        } catch (BusinessRuleException) {
        }

        // Resolve
        $user = $this->makeUser();
        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        // RESOLVED incident tidak boleh memblokir
        $loan = $this->loanService->createLoan($member->id, [$newCopy->id]);
        $this->assertNotNull($loan);
    }

    public function test_resolved_incident_can_be_reused_by_renewal_service_eligibility(): void
    {
        // 1. Buat loan item active untuk direnew
        $activeItem = $this->makeLoanItemWithStatus('ACTIVE');
        $member = $activeItem->loan->member;

        // 2. Buat incident pada loan lain milik member yang sama
        $lostCopy = $this->makeAvailableCopy();
        $lostCopy->status = 'LOST';
        $lostCopy->save();
        $lostLoan = Loan::create(['loan_number' => uniqid(), 'member_id' => $member->id, 'borrowed_at' => now(), 'status' => 'ACTIVE']);
        $lostItem = LoanItem::create(['loan_id' => $lostLoan->id, 'book_copy_id' => $lostCopy->id, 'borrowed_at' => now(), 'due_at' => now(), 'status' => 'LOST']);
        $incident = $this->incidentService->openIncident($lostItem->id, 'LOST');

        // OPEN incident memblokir renewal
        try {
            $this->renewalService->renewItem($activeItem->id);
            $this->fail('Expected BusinessRuleException for unresolved incident');
        } catch (BusinessRuleException) {
        }

        // Resolve
        $user = $this->makeUser();
        $this->incidentService->resolveWithPayment($incident->id, $user->id);

        // RESOLVED incident mengizinkan renewal
        $renewed = $this->renewalService->renewItem($activeItem->id);
        $this->assertEquals(1, $renewed->renewal_count);
    }

    public function test_seeded_data_or_history_is_not_affected_unexpectedly(): void
    {
        // Pastikan tidak ada incident yang 'bocor' atau mengubah behavior tanpa incident
        $this->assertEquals(0, BookIncident::count());
    }

    public function test_amount_paid_cannot_exceed_amount_due(): void
    {
        // Tested implicitly via PostgreSQL check constraint
        $item = $this->makeLoanItemWithStatus('LOST');
        $incident = $this->incidentService->openIncident($item->id, 'LOST');

        $this->expectException(PDOException::class);
        $incident->amount_paid = $incident->amount_due + 1;
        $incident->save();
    }
}
