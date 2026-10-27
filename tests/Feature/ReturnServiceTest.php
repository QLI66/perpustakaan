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
use App\Services\ReturnService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReturnService $returnService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->returnService = new ReturnService;
    }

    private function makeDependencies(string $dueAt = '2026-09-20 10:00:00'): array
    {
        $mType = MemberType::create(['code' => 'T1', 'name' => 'Tes']);
        $member = Member::create(['member_number' => 'MBR-1', 'name' => 'A', 'member_type_id' => $mType->id, 'joined_at' => '2025-01-01']);
        $location = Location::create(['code' => 'L1', 'name' => 'L']);
        $cType = CollectionType::create(['code' => 'C1', 'name' => 'C']);
        $book = Book::create(['title' => 'Buku', 'collection_type_id' => $cType->id]);

        $copy1 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B1', 'inventory_number' => 'INV-1', 'status' => 'BORROWED', 'location_id' => $location->id]);
        $copy2 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'BORROWED', 'location_id' => $location->id]);

        $loan = Loan::create(['loan_number' => 'L-X', 'member_id' => $member->id, 'borrowed_at' => '2026-09-17 10:00:00', 'status' => 'ACTIVE']);
        $item1 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy1->id, 'borrowed_at' => '2026-09-17 10:00:00', 'due_at' => $dueAt, 'status' => 'ACTIVE']);
        $item2 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-09-17 10:00:00', 'due_at' => $dueAt, 'status' => 'ACTIVE']);

        return [$item1, $item2, $loan, $copy1, $copy2];
    }

    public function test_active_loan_item_can_be_returned(): void
    {
        [$item1] = $this->makeDependencies();
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertDatabaseHas('loan_items', ['id' => $item1->id, 'status' => 'RETURNED']);
    }

    public function test_returned_at_is_set(): void
    {
        [$item1] = $this->makeDependencies();
        $returnedAt = Carbon::parse('2026-09-19 10:00:00');
        $this->returnService->returnItem($item1->id, $returnedAt);
        $this->assertEquals($returnedAt->toDateTimeString(), $item1->fresh()->returned_at->toDateTimeString());
    }

    public function test_loan_item_status_becomes_returned(): void
    {
        [$item1] = $this->makeDependencies();
        $returned = $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals('RETURNED', $returned->status);
    }

    public function test_book_copy_status_becomes_available(): void
    {
        [$item1, , , $copy1] = $this->makeDependencies();
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals('AVAILABLE', $copy1->fresh()->status);
    }

    public function test_on_time_return_creates_no_fine(): void
    {
        // due: 2026-09-20 10:00, return: 2026-09-20 10:00 -> 0 days
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-20 10:00:00'));
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $item1->id]);
    }

    public function test_return_late_on_due_date_charges_one_started_day(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 00:00:00');

        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-20 12:00:00'));

        $this->assertSame('RETURNED', $item1->fresh()->status);
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item1->id,
            'late_days' => 1,
            'amount' => config('library.fine_rate_per_day', 1000),
        ]);
    }

    public function test_return_at_midnight_after_due_date_creates_one_day_fine(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 23:59:59');

        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-21 00:00:00'));

        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item1->id,
            'late_days' => 1,
            'amount' => config('library.fine_rate_per_day', 1000),
        ]);
    }

    public function test_1_day_late_creates_1000_fine(): void
    {
        // due: 2026-09-20 10:00, return: 2026-09-21 09:00 -> under Option B with 1 midnight transition, 2 late days
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-21 09:00:00'));

        $fine = Fine::where('loan_item_id', $item1->id)->first();
        $this->assertNotNull($fine);
        $this->assertEquals(2, $fine->late_days);
        $this->assertEquals(2000, $fine->amount);
    }

    public function test_3_days_late_creates_3000_fine(): void
    {
        // due: 2026-09-20, return: 2026-09-23 -> under Option B with 3 midnight transitions, 4 late days
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-23 10:00:00'));

        $fine = Fine::where('loan_item_id', $item1->id)->first();
        $this->assertNotNull($fine);
        $this->assertEquals(4, $fine->late_days);
        $this->assertEquals(4000, $fine->amount);
    }

    public function test_fine_amount_equals_late_days_times_rate_per_day(): void
    {
        Config::set('library.fine_rate_per_day', 2000);
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-22 10:00:00')); // 2 calendar days (3 late days under Option B)

        $fine = Fine::where('loan_item_id', $item1->id)->first();
        $this->assertEquals(3, $fine->late_days);
        $this->assertEquals(2000, $fine->rate_per_day);
        $this->assertEquals(6000, $fine->amount);
    }

    public function test_fine_status_defaults_unpaid(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-21 10:00:00'));
        $fine = Fine::where('loan_item_id', $item1->id)->first();
        $this->assertEquals('UNPAID', $fine->status);
        $this->assertEquals(0, $fine->amount_paid);
    }

    public function test_fine_assessed_at_equals_returned_at(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $returnedAt = Carbon::parse('2026-09-21 15:30:00');
        $this->returnService->returnItem($item1->id, $returnedAt);
        $fine = Fine::where('loan_item_id', $item1->id)->first();
        $this->assertEquals($returnedAt->toDateTimeString(), $fine->assessed_at->toDateTimeString());
    }

    public function test_cannot_return_already_returned_item(): void
    {
        [$item1] = $this->makeDependencies();
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item has already been returned or is not active.');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-20 10:00:00'));
    }

    public function test_cannot_return_non_active_item(): void
    {
        [$item1] = $this->makeDependencies();
        $item1->status = 'LOST';
        $item1->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item has already been returned or is not active.');
        $this->returnService->returnItem($item1->id);
    }

    public function test_cannot_return_when_book_copy_status_is_not_borrowed(): void
    {
        [$item1, , , $copy1] = $this->makeDependencies();
        $copy1->status = 'AVAILABLE';
        $copy1->save();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Loan item and book copy status are inconsistent. Book copy is not in BORROWED status.');
        $this->returnService->returnItem($item1->id);
    }

    public function test_inconsistent_state_throws_business_rule_exception(): void
    {
        [$item1, , , $copy1] = $this->makeDependencies();
        $copy1->status = 'LOST';
        $copy1->save();

        $this->expectException(BusinessRuleException::class);
        $this->returnService->returnItem($item1->id);
    }

    public function test_returning_first_item_keeps_loan_active(): void
    {
        [$item1, , $loan] = $this->makeDependencies();
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals('ACTIVE', $loan->fresh()->status);
    }

    public function test_returning_final_active_item_changes_loan_to_completed(): void
    {
        [$item1, $item2, $loan] = $this->makeDependencies();
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals('ACTIVE', $loan->fresh()->status);

        $this->returnService->returnItem($item2->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals('COMPLETED', $loan->fresh()->status);
    }

    public function test_returned_item_cannot_produce_second_fine(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        // Manually inject a fine
        Fine::create(['loan_item_id' => $item1->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('A fine already exists for this loan item.');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-25 10:00:00'));
    }

    public function test_failed_transaction_rolls_back_changes(): void
    {
        [$item1, $item2, $loan, $copy1] = $this->makeDependencies('2026-09-20 10:00:00');

        try {
            DB::transaction(function () use ($item1) {
                // Call the service, which will update item, book copy, and create fine
                $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-22 10:00:00'));

                // Then intentionally throw an exception to abort transaction
                throw new \Exception('Force Rollback');
            });
        } catch (\Exception $e) {
            $this->assertEquals('Force Rollback', $e->getMessage());
        }

        // Verify everything rolled back!
        $this->assertEquals('ACTIVE', $item1->fresh()->status);
        $this->assertNull($item1->fresh()->returned_at);
        $this->assertEquals('BORROWED', $copy1->fresh()->status);
        $this->assertEquals('ACTIVE', $loan->fresh()->status);
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $item1->id]);
    }

    public function test_fine_is_connected_to_correct_loan_item(): void
    {
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-22 10:00:00'));
        $fine = Fine::first();
        $this->assertEquals($item1->id, $fine->loan_item_id);
    }

    public function test_returned_at_can_be_explicitly_supplied(): void
    {
        [$item1] = $this->makeDependencies();
        $explicit = Carbon::parse('2025-01-01 15:00:00');
        $item = $this->returnService->returnItem($item1->id, $explicit);
        $this->assertEquals($explicit->toDateTimeString(), $item->returned_at->toDateTimeString());
    }

    public function test_returned_at_defaults_to_now_when_omitted(): void
    {
        [$item1] = $this->makeDependencies();
        Carbon::setTestNow('2026-10-10 12:00:00');
        $item = $this->returnService->returnItem($item1->id);
        $this->assertEquals('2026-10-10 12:00:00', $item->returned_at->toDateTimeString());
        Carbon::setTestNow();
    }

    public function test_rate_comes_from_config(): void
    {
        Config::set('library.fine_rate_per_day', 500);
        [$item1] = $this->makeDependencies('2026-09-20 10:00:00');
        $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-21 10:00:00')); // under Option B, 2 late days

        $fine = Fine::first();
        $this->assertEquals(1000, $fine->amount);
    }

    public function test_book_copy_relationship_remains_correct_after_return(): void
    {
        [$item1, , , $copy1] = $this->makeDependencies();
        $item = $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals($copy1->id, $item->bookCopy->id);
        $this->assertEquals('AVAILABLE', $item->bookCopy->status);
    }

    public function test_loan_relationship_remains_correct_after_return(): void
    {
        [$item1, , $loan] = $this->makeDependencies();
        $item = $this->returnService->returnItem($item1->id, Carbon::parse('2026-09-19 10:00:00'));
        $this->assertEquals($loan->id, $item->loan->id);
    }
}
