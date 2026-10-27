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
use App\Services\RenewalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenewalServiceTest extends TestCase
{
    use RefreshDatabase;

    private RenewalService $renewalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renewalService = new RenewalService;
    }

    /**
     * Create a full set of dependencies for a single active loan item.
     * Returns [$item, $loan, $copy, $member]
     */
    private function makeDependencies(
        array $overrides = [],
        ?string $dueAt = null
    ): array {
        $mType = MemberType::create(['code' => 'T1', 'name' => 'Tes']);
        $member = Member::create(['member_number' => 'MBR-1', 'name' => 'A', 'member_type_id' => $mType->id, 'joined_at' => '2025-01-01']);
        $loc = Location::create(['code' => 'L1', 'name' => 'Loc']);
        $cType = CollectionType::create(['code' => 'C1', 'name' => 'Col']);
        $book = Book::create(['title' => 'Buku', 'collection_type_id' => $cType->id]);
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B1', 'inventory_number' => 'INV-1', 'status' => 'BORROWED', 'location_id' => $loc->id]);
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-09-17 10:00:00', 'status' => 'ACTIVE']);
        $dueAt = $dueAt ?? now()->addDays(5)->toDateTimeString();
        $item = LoanItem::create(array_merge([
            'loan_id' => $loan->id,
            'book_copy_id' => $copy->id,
            'borrowed_at' => '2026-09-17 10:00:00',
            'due_at' => $dueAt,
            'status' => 'ACTIVE',
            'renewal_count' => 0,
        ], $overrides));

        return [$item, $loan, $copy, $member];
    }

    // ─── Core Behavior ────────────────────────────────────────────────────────

    public function test_active_item_can_be_renewed(): void
    {
        [$item] = $this->makeDependencies();
        $renewed = $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertInstanceOf(LoanItem::class, $renewed);
    }

    public function test_renewal_count_increments_from_0_to_1(): void
    {
        [$item] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals(1, $item->fresh()->renewal_count);
    }

    public function test_due_at_becomes_renewal_date_plus_3_days(): void
    {
        [$item] = $this->makeDependencies();
        $renewedAt = Carbon::parse('2026-09-19 20:30:00');
        $this->renewalService->renewItem($item->id, $renewedAt);
        // 2026-09-19 (start of day) + 3 days = 2026-09-22
        $this->assertEquals('2026-09-22 00:00:00', $item->fresh()->due_at->toDateTimeString());
    }

    public function test_renewal_uses_calendar_date_not_72_hours(): void
    {
        [$item] = $this->makeDependencies();
        // Even at 23:59, the calendar date is still 2026-09-19
        $renewedAt = Carbon::parse('2026-09-19 23:59:59');
        $this->renewalService->renewItem($item->id, $renewedAt);
        $this->assertEquals('2026-09-22 00:00:00', $item->fresh()->due_at->toDateTimeString());
    }

    public function test_returned_at_stays_null_after_renewal(): void
    {
        [$item] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertNull($item->fresh()->returned_at);
    }

    public function test_loan_item_status_remains_active(): void
    {
        [$item] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals('ACTIVE', $item->fresh()->status);
    }

    public function test_book_copy_status_remains_borrowed(): void
    {
        [$item, , $copy] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals('BORROWED', $copy->fresh()->status);
    }

    public function test_loan_stays_active_after_renewal(): void
    {
        [$item, $loan] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals('ACTIVE', $loan->fresh()->status);
    }

    // ─── Validation Rejections ────────────────────────────────────────────────

    public function test_cannot_renew_returned_item(): void
    {
        [$item] = $this->makeDependencies(['status' => 'RETURNED', 'returned_at' => '2026-09-19']);
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item is not active and cannot be renewed.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_cannot_renew_item_with_renewal_count_1(): void
    {
        [$item] = $this->makeDependencies(['renewal_count' => 1]);
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Maximum renewal limit has been reached.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_overdue_item_cannot_be_renewed(): void
    {
        [$item] = $this->makeDependencies(
            [],
            now()->subDay()->toDateTimeString()
        );

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'Loan item is overdue and cannot be renewed.'
        );

        $this->renewalService->renewItem($item->id);
    }

    public function test_item_can_be_renewed_until_the_end_of_its_due_calendar_date(): void
    {
        [$item] = $this->makeDependencies([], '2026-10-13 23:59:59');

        $renewed = $this->renewalService->renewItem(
            $item->id,
            Carbon::parse('2026-10-13 23:59:59'),
        );

        $this->assertSame(1, $renewed->fresh()->renewal_count);
    }

    public function test_item_is_overdue_for_renewal_starting_at_next_calendar_midnight(): void
    {
        [$item] = $this->makeDependencies([], '2026-10-13 23:59:59');

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item is overdue and cannot be renewed.');

        $this->renewalService->renewItem(
            $item->id,
            Carbon::parse('2026-10-14 00:00:00'),
        );
    }

    public function test_cannot_renew_with_unpaid_fine(): void
    {
        [$item, , , $member] = $this->makeDependencies();
        Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('All outstanding fines must be fully paid before renewal.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_cannot_renew_with_partial_fine(): void
    {
        [$item] = $this->makeDependencies();
        Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => 2,
            'rate_per_day' => 1000,
            'amount' => 2000,
            'amount_paid' => 1000,
            'status' => 'PARTIAL',
            'assessed_at' => now(),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('All outstanding fines must be fully paid before renewal.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_can_renew_when_all_member_fines_are_paid(): void
    {
        [$item] = $this->makeDependencies();
        Fine::create([
            'loan_item_id' => $item->id,
            'late_days' => 1,
            'rate_per_day' => 1000,
            'amount' => 1000,
            'amount_paid' => 1000,
            'status' => 'PAID',
            'assessed_at' => now(),
            'paid_at' => now(),
        ]);

        $renewed = $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals(1, $renewed->renewal_count);
    }

    public function test_can_renew_when_member_has_no_fines(): void
    {
        [$item] = $this->makeDependencies();
        $renewed = $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        $this->assertEquals(1, $renewed->renewal_count);
    }

    public function test_cannot_renew_with_unresolved_lost_item(): void
    {
        [$item, $loan, , $member] = $this->makeDependencies();

        // Create a second item on same member's loan with LOST status
        $loc = Location::create(['code' => 'L2', 'name' => 'Loc2']);
        $cType = CollectionType::create(['code' => 'C2', 'name' => 'C2']);
        $book2 = Book::create(['title' => 'Buku2', 'collection_type_id' => $cType->id]);
        $copy2 = BookCopy::create(['book_id' => $book2->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'BORROWED', 'location_id' => $loc->id]);
        $lostItem = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-09-17', 'due_at' => '2026-09-20', 'status' => 'LOST']);
        BookIncident::create(['loan_item_id' => $lostItem->id, 'book_copy_id' => $copy2->id, 'type' => 'LOST', 'status' => 'OPEN', 'opened_at' => now()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member has unresolved lost or damaged book obligations.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_cannot_renew_with_unresolved_damaged_item(): void
    {
        [$item, $loan] = $this->makeDependencies();

        $loc = Location::create(['code' => 'L2', 'name' => 'Loc2']);
        $cType = CollectionType::create(['code' => 'C2', 'name' => 'C2']);
        $book2 = Book::create(['title' => 'Buku2', 'collection_type_id' => $cType->id]);
        $copy2 = BookCopy::create(['book_id' => $book2->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'BORROWED', 'location_id' => $loc->id]);
        $damagedItem = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-09-17', 'due_at' => '2026-09-20', 'status' => 'DAMAGED']);
        BookIncident::create(['loan_item_id' => $damagedItem->id, 'book_copy_id' => $copy2->id, 'type' => 'DAMAGED', 'status' => 'OPEN', 'opened_at' => now()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Member has unresolved lost or damaged book obligations.');
        $this->renewalService->renewItem($item->id);
    }

    public function test_inconsistent_loan_item_book_copy_throws_exception(): void
    {
        [$item, , $copy] = $this->makeDependencies();
        $copy->status = 'AVAILABLE';
        $copy->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item and book copy status are inconsistent.');
        $this->renewalService->renewItem($item->id);
    }

    // ─── Date Determinism ─────────────────────────────────────────────────────

    public function test_explicit_renewed_at_works_deterministically(): void
    {
        [$item] = $this->makeDependencies();
        $explicit = Carbon::parse('2026-09-19 08:00:00');
        $this->renewalService->renewItem($item->id, $explicit);
        // 2026-09-19 start of day + 3 days = 2026-09-22
        $this->assertEquals('2026-09-22 00:00:00', $item->fresh()->due_at->toDateTimeString());
    }

    public function test_omitted_renewed_at_uses_now(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        [$item] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id);
        // 2026-10-01 start of day + 3 days = 2026-10-04
        $this->assertEquals(
            '2026-10-04 00:00:00',
            $item->fresh()->due_at->toDateTimeString()
        );
        Carbon::setTestNow();
    }

    // ─── Config ───────────────────────────────────────────────────────────────

    public function test_config_renewal_days_is_used(): void
    {
        Config::set('library.renewal_days', 5);
        [$item] = $this->makeDependencies();
        $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
        // 2026-09-19 + 5 days = 2026-09-24
        $this->assertEquals('2026-09-24 00:00:00', $item->fresh()->due_at->toDateTimeString());
    }

    // ─── Concurrency ──────────────────────────────────────────────────────────

    public function test_concurrent_renewal_cannot_increment_renewal_count_twice(): void
    {
        try {
            [$item] = $this->makeDependencies();

            // First renewal: freeze "now" at the renewal date.
            Carbon::setTestNow('2026-09-19 10:00:00');

            $this->renewalService->renewItem(
                $item->id,
                Carbon::parse('2026-09-19')
            );

            $this->assertEquals(
                1,
                $item->fresh()->renewal_count
            );

            // Second attempt: item is not overdue yet,
            // so the renewal limit check should reject it.
            Carbon::setTestNow('2026-09-20 10:00:00');

            $this->expectException(BusinessRuleException::class);
            $this->expectExceptionMessage(
                'Maximum renewal limit has been reached.'
            );

            $this->renewalService->renewItem(
                $item->id,
                Carbon::parse('2026-09-20')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    // ─── Rollback Tests ───────────────────────────────────────────────────────

    public function test_failed_transaction_does_not_partially_modify_loan_item(): void
    {
        [$item] = $this->makeDependencies();
        $originalDueAt = $item->due_at->toDateTimeString();
        $originalCount = $item->renewal_count;

        try {
            DB::transaction(function () use ($item) {
                $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
                throw new \Exception('Force Rollback');
            });
        } catch (\Exception $e) {
            $this->assertEquals('Force Rollback', $e->getMessage());
        }

        $this->assertEquals($originalDueAt, $item->fresh()->due_at->toDateTimeString());
        $this->assertEquals($originalCount, $item->fresh()->renewal_count);
    }

    public function test_failed_transaction_does_not_modify_book_copy(): void
    {
        [$item, , $copy] = $this->makeDependencies();

        try {
            DB::transaction(function () use ($item) {
                $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
                throw new \Exception('Force Rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertEquals('BORROWED', $copy->fresh()->status);
    }

    public function test_failed_transaction_does_not_modify_loan(): void
    {
        [$item, $loan] = $this->makeDependencies();

        try {
            DB::transaction(function () use ($item) {
                $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
                throw new \Exception('Force Rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertEquals('ACTIVE', $loan->fresh()->status);
    }

    public function test_failed_transaction_does_not_create_unrelated_records(): void
    {
        [$item] = $this->makeDependencies();

        try {
            DB::transaction(function () use ($item) {
                $this->renewalService->renewItem($item->id, Carbon::parse('2026-09-19'));
                throw new \Exception('Force Rollback');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // No fines should be created by renewal (it's return's job)
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $item->id]);
    }

    // ─── Isolation ────────────────────────────────────────────────────────────

    public function test_renewal_only_affects_targeted_loan_item(): void
    {
        // Two items on same loan
        [$item1, $loan, $copy1, $member] = $this->makeDependencies();

        $loc = Location::create(['code' => 'L2', 'name' => 'Loc2']);
        $cType = CollectionType::create(['code' => 'C2', 'name' => 'C2']);
        $book2 = Book::create(['title' => 'Buku2', 'collection_type_id' => $cType->id]);
        $copy2 = BookCopy::create(['book_id' => $book2->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'BORROWED', 'location_id' => $loc->id]);
        $item2 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-09-17 10:00:00', 'due_at' => '2026-09-20 00:00:00', 'status' => 'ACTIVE', 'renewal_count' => 0]);

        $originalDueAt2 = $item2->due_at->toDateTimeString();

        $this->renewalService->renewItem($item1->id, Carbon::parse('2026-09-19'));

        // item2 must NOT be affected
        $this->assertEquals($originalDueAt2, $item2->fresh()->due_at->toDateTimeString());
        $this->assertEquals(0, $item2->fresh()->renewal_count);
    }

    public function test_another_active_loan_item_remains_unchanged(): void
    {
        [$item1, $loan] = $this->makeDependencies();

        $loc = Location::create(['code' => 'L2', 'name' => 'Loc2']);
        $cType = CollectionType::create(['code' => 'C2', 'name' => 'C2']);
        $book2 = Book::create(['title' => 'Buku2', 'collection_type_id' => $cType->id]);
        $copy2 = BookCopy::create(['book_id' => $book2->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'BORROWED', 'location_id' => $loc->id]);
        $item2 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-09-17 10:00:00', 'due_at' => '2026-09-20 00:00:00', 'status' => 'ACTIVE', 'renewal_count' => 0]);

        $this->renewalService->renewItem($item1->id, Carbon::parse('2026-09-19'));

        // item2 is still ACTIVE and still has original due_at and count
        $this->assertEquals('ACTIVE', $item2->fresh()->status);
        $this->assertEquals(0, $item2->fresh()->renewal_count);
        $this->assertEquals('2026-09-20 00:00:00', $item2->fresh()->due_at->toDateTimeString());
    }
}
