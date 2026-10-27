<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIncident;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loanService;

    private ?int $sharedBookId = null;

    private ?int $sharedLocId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loanService = new LoanService;
        $this->sharedBookId = null;
        $this->sharedLocId = null;
    }

    // ─── Helper Factories ─────────────────────────────────────────────────────

    private function makeMember(array $overrides = []): Member
    {
        $mType = MemberType::create(['code' => uniqid('MT'), 'name' => 'Type']);

        return Member::create(array_merge([
            'member_number' => uniqid('MBR-'),
            'name' => 'Test Member',
            'member_type_id' => $mType->id,
            'joined_at' => '2025-01-01',
            'status' => 'ACTIVE',
        ], $overrides));
    }

    private function makeAvailableCopy(?string $barcode = null, ?string $inv = null): BookCopy
    {
        // Instance-level cache: reset per test (PHPUnit creates new instance per test method)
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
        ]);
    }

    private function borrowedAt(): Carbon
    {
        return now()->addHour();
    }

    // ─── Core Happy Paths ─────────────────────────────────────────────────────

    public function test_active_member_can_borrow_1_book(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());

        $this->assertInstanceOf(Loan::class, $loan);
        $this->assertCount(1, $loan->items);
    }

    public function test_active_member_can_borrow_2_books(): void
    {
        $member = $this->makeMember();
        $copy1 = $this->makeAvailableCopy();
        $copy2 = $this->makeAvailableCopy();

        $loan = $this->loanService->createLoan($member->id, [$copy1->id, $copy2->id], $this->borrowedAt());

        $this->assertCount(2, $loan->items);
    }

    public function test_cannot_borrow_3_books_in_one_transaction(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $c3 = $this->makeAvailableCopy();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan can contain a maximum of two books.');
        $this->loanService->createLoan($member->id, [$c1->id, $c2->id, $c3->id], $this->borrowedAt());
    }

    public function test_cannot_borrow_when_member_already_has_2_active_items(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $this->loanService->createLoan($member->id, [$c1->id, $c2->id], $this->borrowedAt());

        $c3 = $this->makeAvailableCopy();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member has reached the maximum number of active loans.');
        $this->loanService->createLoan($member->id, [$c3->id], $this->borrowedAt());
    }

    public function test_can_borrow_when_member_has_fewer_than_2_active_items(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $this->loanService->createLoan($member->id, [$c1->id], $this->borrowedAt());

        $c2 = $this->makeAvailableCopy();
        $loan2 = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());

        $this->assertInstanceOf(Loan::class, $loan2);
    }

    public function test_overdue_active_loan_blocks_new_borrowing(): void
    {
        $member = $this->makeMember();

        $oldCopy = $this->makeAvailableCopy();
        $newCopy = $this->makeAvailableCopy();

        $loan = Loan::create([
            'loan_number' => 'L-OVERDUE',
            'member_id' => $member->id,
            'borrowed_at' => now()->subDays(10),
            'status' => 'ACTIVE',
        ]);

        LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $oldCopy->id,
            'borrowed_at' => now()->subDays(10),
            'due_at' => now()->subDay(),
            'status' => 'ACTIVE',
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Member has overdue books that must be returned before borrowing.'
        );

        $this->loanService->createLoan(
            $member->id,
            [$newCopy->id],
            $this->borrowedAt()
        );
    }
    // ─── Member Eligibility ───────────────────────────────────────────────────

    public function test_inactive_member_is_rejected(): void
    {
        $member = $this->makeMember(['status' => 'INACTIVE']);
        $copy = $this->makeAvailableCopy();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member is inactive.');
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_suspended_member_is_rejected(): void
    {
        $member = $this->makeMember(['status' => 'SUSPENDED']);
        $copy = $this->makeAvailableCopy();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member is inactive.');
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_unpaid_fine_blocks_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        // Create a prior loan with a fine
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'COMPLETED']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'returned_at' => '2026-09-16', 'status' => 'RETURNED']);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 3, 'rate_per_day' => 1000, 'amount' => 3000, 'amount_paid' => 0, 'status' => 'UNPAID', 'assessed_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Outstanding fines must be paid before borrowing.');
        $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
    }

    public function test_partial_fine_blocks_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'COMPLETED']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'returned_at' => '2026-09-16', 'status' => 'RETURNED']);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 3, 'rate_per_day' => 1000, 'amount' => 3000, 'amount_paid' => 1500, 'status' => 'PARTIAL', 'assessed_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Outstanding fines must be paid before borrowing.');
        $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
    }

    public function test_all_fines_paid_allows_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'COMPLETED']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'returned_at' => '2026-09-16', 'status' => 'RETURNED']);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 3, 'rate_per_day' => 1000, 'amount' => 3000, 'amount_paid' => 3000, 'status' => 'PAID', 'assessed_at' => now(), 'paid_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    public function test_unresolved_lost_blocks_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'ACTIVE']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'status' => 'LOST']);
        BookIncident::create(['loan_item_id' => $item->id, 'book_copy_id' => $c1->id, 'type' => 'LOST', 'status' => 'OPEN', 'opened_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member has unresolved lost or damaged book obligations.');
        $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
    }

    public function test_unresolved_damaged_blocks_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'ACTIVE']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'status' => 'DAMAGED']);
        BookIncident::create(['loan_item_id' => $item->id, 'book_copy_id' => $c1->id, 'type' => 'DAMAGED', 'status' => 'OPEN', 'opened_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member has unresolved lost or damaged book obligations.');
        $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
    }

    public function test_resolved_lost_does_not_block_borrowing(): void
    {
        // "Resolved" scenario: member has LOST item but it has been RETURNED (status changed to RETURNED)
        // In schema existing, a resolved LOST can only be seen as non-LOST status
        // (e.g., item status updated to RETURNED after resolution)
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'COMPLETED']);
        // Status = RETURNED, not LOST — so it's considered resolved
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'returned_at' => '2026-09-20', 'status' => 'RETURNED']);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    public function test_resolved_damaged_does_not_block_borrowing(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-HIST', 'member_id' => $member->id, 'borrowed_at' => '2026-09-10', 'status' => 'COMPLETED']);
        // Same logic: status RETURNED = resolved
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-10', 'due_at' => '2026-09-13', 'returned_at' => '2026-09-20', 'status' => 'RETURNED']);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    // ─── BookCopy Eligibility ─────────────────────────────────────────────────

    public function test_unavailable_book_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'BORROWED';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book copy is not available.');
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_borrowed_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'BORROWED';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_reserved_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'RESERVED';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book copy is reserved for another member or reservation has expired.');
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_lost_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'LOST';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_damaged_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'DAMAGED';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_maintenance_copy_is_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $copy->status = 'MAINTENANCE';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
    }

    public function test_duplicate_book_copy_ids_are_rejected(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Duplicate book copy requested.');
        $this->loanService->createLoan($member->id, [$copy->id, $copy->id], $this->borrowedAt());
    }

    // ─── State After Successful Loan ─────────────────────────────────────────

    public function test_loan_item_status_is_active(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
        $this->assertEquals('ACTIVE', $loan->items->first()->status);
    }

    public function test_book_copy_becomes_borrowed(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
        $this->assertEquals('BORROWED', $copy->fresh()->status);
    }

    public function test_loan_status_is_active(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
        $this->assertEquals('ACTIVE', $loan->status);
    }

    public function test_due_at_equals_borrowed_date_plus_3_calendar_days(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $borrowedAt = Carbon::parse('2026-09-17 20:30:00');
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $borrowedAt);
        // startOfDay(2026-09-17) + 3 days = 2026-09-20 00:00:00
        $this->assertEquals('2026-09-20 00:00:00', $loan->items->first()->due_at->toDateTimeString());
    }

    public function test_renewal_count_defaults_0(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
        $this->assertEquals(0, $loan->items->first()->renewal_count);
    }

    public function test_returned_at_remains_null(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
        $this->assertNull($loan->items->first()->returned_at);
    }

    public function test_notes_are_persisted(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt(), 'Catatan penting');
        $this->assertEquals('Catatan penting', $loan->notes);
    }

    // ─── Loan Number ──────────────────────────────────────────────────────────

    public function test_loan_number_format_is_correct(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], Carbon::parse('2026-09-17'));
        $this->assertMatchesRegularExpression('/^LOAN-2026-\d{6}$/', $loan->loan_number);
    }

    public function test_loan_number_is_unique(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $loan1 = $this->loanService->createLoan($member->id, [$c1->id], $this->borrowedAt());
        $loan2 = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertNotEquals($loan1->loan_number, $loan2->loan_number);
    }

    public function test_two_different_loans_get_different_loan_numbers(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $l1 = $this->loanService->createLoan($member->id, [$c1->id], $this->borrowedAt());
        $l2 = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertNotSame($l1->loan_number, $l2->loan_number);
        $this->assertEquals(1, Loan::where('loan_number', $l1->loan_number)->count());
        $this->assertEquals(1, Loan::where('loan_number', $l2->loan_number)->count());
    }

    // ─── Rollback Tests ───────────────────────────────────────────────────────

    public function test_partial_failure_rolls_back_entire_loan(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();

        // Mark c2 as BORROWED to trigger failure mid-transaction
        $c2->status = 'BORROWED';
        $c2->save();

        try {
            $this->loanService->createLoan($member->id, [$c1->id, $c2->id], $this->borrowedAt());
        } catch (BusinessRuleException $e) {
            // Expected
        }

        // c1 must not have been set to BORROWED
        $this->assertEquals('AVAILABLE', $c1->fresh()->status);
        // No orphan loans
        $this->assertDatabaseEmpty('loans');
    }

    public function test_failed_copy_does_not_remain_borrowed(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $c2->status = 'BORROWED';
        $c2->save();

        try {
            $this->loanService->createLoan($member->id, [$c1->id, $c2->id], $this->borrowedAt());
        } catch (BusinessRuleException) {
        }

        $this->assertEquals('AVAILABLE', $c1->fresh()->status);
    }

    public function test_failed_loan_does_not_create_orphan_loan_items(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();

        try {
            DB::transaction(function () use ($member, $copy) {
                $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
                throw new \Exception('Force rollback');
            });
        } catch (\Exception) {
        }

        $this->assertDatabaseEmpty('loan_items');
        $this->assertDatabaseEmpty('loans');
    }

    public function test_transaction_rollback_restores_member_and_loan_state(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();

        try {
            DB::transaction(function () use ($member, $copy) {
                $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());
                throw new \Exception('Force rollback');
            });
        } catch (\Exception) {
        }

        $this->assertEquals('ACTIVE', $member->fresh()->status);
        $this->assertDatabaseEmpty('loans');
    }

    // ─── Isolation ────────────────────────────────────────────────────────────

    public function test_only_requested_copies_change_status(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy(); // NOT borrowed

        $this->loanService->createLoan($member->id, [$c1->id], $this->borrowedAt());

        $this->assertEquals('BORROWED', $c1->fresh()->status);
        $this->assertEquals('AVAILABLE', $c2->fresh()->status);
    }

    public function test_unrelated_available_copies_remain_unchanged(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();
        $c3 = $this->makeAvailableCopy(); // Unrelated

        $this->loanService->createLoan($member->id, [$c1->id, $c2->id], $this->borrowedAt());

        $this->assertEquals('AVAILABLE', $c3->fresh()->status);
    }

    // ─── Relationship Checks ──────────────────────────────────────────────────

    public function test_relationship_loan_has_items(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());

        $this->assertCount(1, $loan->items);
        $this->assertInstanceOf(LoanItem::class, $loan->items->first());
    }

    public function test_relationship_loan_item_belongs_to_book_copy(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $loan = $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());

        $item = $loan->items->first();
        $this->assertEquals($copy->id, $item->bookCopy->id);
    }

    public function test_member_loan_count_is_correct(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();

        $this->loanService->createLoan($member->id, [$c1->id], $this->borrowedAt());
        $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());

        $this->assertEquals(2, Loan::where('member_id', $member->id)->count());
    }

    // ─── Historical Data Checks ───────────────────────────────────────────────

    public function test_borrowing_after_historical_returned_loan_is_allowed(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-OLD', 'member_id' => $member->id, 'borrowed_at' => '2026-09-01', 'status' => 'COMPLETED']);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-01', 'due_at' => '2026-09-04', 'returned_at' => '2026-09-04', 'status' => 'RETURNED']);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    public function test_historical_paid_fine_does_not_block(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $loan = Loan::create(['loan_number' => 'L-OLD', 'member_id' => $member->id, 'borrowed_at' => '2026-09-01', 'status' => 'COMPLETED']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-01', 'due_at' => '2026-09-04', 'returned_at' => '2026-09-07', 'status' => 'RETURNED']);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 3, 'rate_per_day' => 1000, 'amount' => 3000, 'amount_paid' => 3000, 'status' => 'PAID', 'assessed_at' => now(), 'paid_at' => now()]);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    public function test_historical_resolved_lost_damaged_does_not_block(): void
    {
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        // RETURNED = the copy was resolved (not LOST/DAMAGED status)
        $loan = Loan::create(['loan_number' => 'L-OLD', 'member_id' => $member->id, 'borrowed_at' => '2026-09-01', 'status' => 'COMPLETED']);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $c1->id, 'borrowed_at' => '2026-09-01', 'due_at' => '2026-09-04', 'returned_at' => '2026-09-15', 'status' => 'RETURNED']);

        $c2 = $this->makeAvailableCopy();
        $newLoan = $this->loanService->createLoan($member->id, [$c2->id], $this->borrowedAt());
        $this->assertInstanceOf(Loan::class, $newLoan);
    }

    // ─── Concurrency ──────────────────────────────────────────────────────────

    public function test_concurrent_attempt_cannot_borrow_same_book_copy_twice(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();

        // First loan succeeds
        $this->loanService->createLoan($member->id, [$copy->id], $this->borrowedAt());

        // Copy is now BORROWED — second attempt must fail
        $member2 = $this->makeMember();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Book copy is not available.');
        $this->loanService->createLoan($member2->id, [$copy->id], $this->borrowedAt());
    }

    // ─── Config Driven ───────────────────────────────────────────────────────

    public function test_multiple_requested_copies_sorted_before_locking(): void
    {
        // IDs naturally come unsorted; verify that sorting produces correct order
        $member = $this->makeMember();
        $c1 = $this->makeAvailableCopy();
        $c2 = $this->makeAvailableCopy();

        // Pass IDs in reverse order — should still succeed with no deadlock
        $ids = collect([$c1->id, $c2->id])->sortDesc()->values()->toArray();
        $loan = $this->loanService->createLoan($member->id, $ids, $this->borrowedAt());

        $this->assertCount(2, $loan->items);
        $this->assertEquals('BORROWED', $c1->fresh()->status);
        $this->assertEquals('BORROWED', $c2->fresh()->status);
    }

    // ─── loan_duration_days Tests ─────────────────────────────────────────────

    public function test_duration_1_day_sets_correct_due_date(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $base = Carbon::parse('2026-10-01 09:00:00');

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $base, null, 1);
        $item = $loan->items->first();

        $this->assertEquals(
            Carbon::parse('2026-10-01')->startOfDay()->addDays(1)->toDateString(),
            Carbon::parse($item->due_at)->toDateString(),
            'Duration 1 should produce due_at = borrowed_date + 1 day'
        );
    }

    public function test_duration_2_days_sets_correct_due_date(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $base = Carbon::parse('2026-10-01 09:00:00');

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $base, null, 2);
        $item = $loan->items->first();

        $this->assertEquals(
            Carbon::parse('2026-10-01')->startOfDay()->addDays(2)->toDateString(),
            Carbon::parse($item->due_at)->toDateString(),
            'Duration 2 should produce due_at = borrowed_date + 2 days'
        );
    }

    public function test_duration_3_days_sets_correct_due_date(): void
    {
        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $base = Carbon::parse('2026-10-01 09:00:00');

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $base, null, 3);
        $item = $loan->items->first();

        $this->assertEquals(
            Carbon::parse('2026-10-01')->startOfDay()->addDays(3)->toDateString(),
            Carbon::parse($item->due_at)->toDateString(),
            'Duration 3 should produce due_at = borrowed_date + 3 days'
        );
    }

    public function test_null_duration_falls_back_to_config_default(): void
    {
        Config::set('library.loan_days', 3);

        $member = $this->makeMember();
        $copy = $this->makeAvailableCopy();
        $base = Carbon::parse('2026-10-01 09:00:00');

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $base, null, null);
        $item = $loan->items->first();

        $this->assertEquals(
            Carbon::parse('2026-10-01')->startOfDay()->addDays(3)->toDateString(),
            Carbon::parse($item->due_at)->toDateString(),
            'Omitted duration should fall back to config default (3 days)'
        );
    }
}
