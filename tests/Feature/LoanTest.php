<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use Database\Seeders\BookCopySeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ClassLevelSeeder;
use Database\Seeders\CollectionTypeSeeder;
use Database\Seeders\LoanSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\MajorSeeder;
use Database\Seeders\MemberSeeder;
use Database\Seeders\MemberTypeSeeder;
use Database\Seeders\PublisherSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    private function makeDependencies(): array
    {
        $mType = MemberType::create(['code' => 'TEST', 'name' => 'Tes']);
        $member = Member::create([
            'member_number' => 'MBR-01',
            'name' => 'A',
            'member_type_id' => $mType->id,
            'joined_at' => '2025-01-01',
        ]);

        $location = Location::create(['code' => 'TEST', 'name' => 'Tes']);
        $cType = CollectionType::create(['code' => 'TEST', 'name' => 'Tes']);
        $book = Book::create(['title' => 'Buku', 'collection_type_id' => $cType->id]);
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-001', 'inventory_number' => 'INV-01', 'status' => 'AVAILABLE', 'location_id' => $location->id]);
        $copy2 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-002', 'inventory_number' => 'INV-02', 'status' => 'AVAILABLE', 'location_id' => $location->id]);

        return [$member, $copy, $copy2];
    }

    public function test_loans_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('loans'));
    }

    public function test_loan_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('loan_items'));
    }

    public function test_loan_number_unique(): void
    {
        [$member] = $this->makeDependencies();

        Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);

        $this->expectException(QueryException::class);
        Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
    }

    public function test_member_id_required(): void
    {
        $this->expectException(QueryException::class);
        Loan::create(['loan_number' => 'L-01', 'borrowed_at' => '2026-01-01']);
    }

    public function test_borrowed_at_required(): void
    {
        [$member] = $this->makeDependencies();

        $this->expectException(QueryException::class);
        Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id]);
    }

    public function test_default_loan_status_active(): void
    {
        [$member] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);

        $this->assertSame('ACTIVE', $loan->fresh()->status);
    }

    public function test_loan_belongs_to_member_and_has_many_items(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $this->assertInstanceOf(Member::class, $loan->member);
        $this->assertInstanceOf(Loan::class, $member->loans->first());
        $this->assertCount(1, $loan->items);
        $this->assertInstanceOf(LoanItem::class, $loan->items->first());
    }

    public function test_loan_item_belongs_to_loan_and_book_copy(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $this->assertInstanceOf(Loan::class, $item->loan);
        $this->assertInstanceOf(BookCopy::class, $item->bookCopy);
        $this->assertCount(1, $copy->loanItems);
    }

    public function test_due_at_required(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);

        $this->expectException(QueryException::class);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01']);
    }

    public function test_renewal_count_defaults_zero_and_status_active(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $this->assertSame(0, $item->fresh()->renewal_count);
        $this->assertSame('ACTIVE', $item->fresh()->status);
        $this->assertNull($item->fresh()->returned_at);
    }

    public function test_same_book_copy_cannot_have_two_active_loan_items(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04', 'status' => 'ACTIVE']);

        $loan2 = Loan::create(['loan_number' => 'L-02', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);

        $this->expectException(QueryException::class);
        // Should fail due to Partial Unique Index (status = 'ACTIVE')
        LoanItem::create(['loan_id' => $loan2->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04', 'status' => 'ACTIVE']);
    }

    public function test_same_book_copy_may_appear_in_multiple_historical_returned_items(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan1 = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        LoanItem::create(['loan_id' => $loan1->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04', 'status' => 'RETURNED', 'returned_at' => '2026-01-03']);

        $loan2 = Loan::create(['loan_number' => 'L-02', 'member_id' => $member->id, 'borrowed_at' => '2026-02-01']);
        LoanItem::create(['loan_id' => $loan2->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-02-01', 'due_at' => '2026-02-04', 'status' => 'RETURNED', 'returned_at' => '2026-02-03']);

        $loan3 = Loan::create(['loan_number' => 'L-03', 'member_id' => $member->id, 'borrowed_at' => '2026-03-01']);
        $activeItem = LoanItem::create(['loan_id' => $loan3->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-03-01', 'due_at' => '2026-03-04', 'status' => 'ACTIVE']);

        // Validation: partial unique index works correctly
        $this->assertCount(3, LoanItem::where('book_copy_id', $copy->id)->get());
    }

    public function test_deleting_member_with_loans_is_restricted(): void
    {
        [$member] = $this->makeDependencies();
        Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);

        $this->expectException(QueryException::class);
        $member->delete();
    }

    public function test_deleting_loan_removes_loan_items(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $loan->delete();
        $this->assertDatabaseMissing('loan_items', ['book_copy_id' => $copy->id]);
    }

    public function test_deleting_book_copy_with_loan_history_is_restricted(): void
    {
        [$member, $copy] = $this->makeDependencies();
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $this->expectException(QueryException::class);
        $copy->delete();
    }

    public function test_status_values_at_application_level(): void
    {
        [$member, $copy, $copy2] = $this->makeDependencies();

        $l1 = Loan::create(['loan_number' => 'L-1', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01', 'status' => 'ACTIVE']);
        $l2 = Loan::create(['loan_number' => 'L-2', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01', 'status' => 'COMPLETED']);
        $l3 = Loan::create(['loan_number' => 'L-3', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01', 'status' => 'CANCELLED']);

        $this->assertSame('ACTIVE', $l1->status);
        $this->assertSame('COMPLETED', $l2->status);
        $this->assertSame('CANCELLED', $l3->status);

        $li1 = LoanItem::create(['loan_id' => $l1->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04', 'status' => 'ACTIVE']);
        // Must use copy2 to avoid active conflict
        $li2 = LoanItem::create(['loan_id' => $l1->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04', 'status' => 'RETURNED']);

        $this->assertSame('ACTIVE', $li1->status);
        $this->assertSame('RETURNED', $li2->status);
    }

    public function test_seeder_creates_expected_loans_and_items(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(LocationSeeder::class);

        $this->seed(MemberSeeder::class);
        $this->seed(BookSeeder::class);
        $this->seed(BookCopySeeder::class);

        $this->seed(LoanSeeder::class);

        $this->assertGreaterThanOrEqual(8, Loan::count());
        $this->assertGreaterThanOrEqual(8, LoanItem::count());
    }

    public function test_active_and_completed_loans_consistency(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(LocationSeeder::class);

        $this->seed(MemberSeeder::class);
        $this->seed(BookSeeder::class);
        $this->seed(BookCopySeeder::class);
        $this->seed(LoanSeeder::class);

        // Active items -> Book must be BORROWED
        $activeItems = LoanItem::where('status', 'ACTIVE')->get();
        foreach ($activeItems as $item) {
            $this->assertSame('BORROWED', $item->bookCopy->status);
            $this->assertNull($item->returned_at);
        }

        // Completed items -> Book must be AVAILABLE (unless re-borrowed, but seeder logic guarantees)
        $completedItems = LoanItem::where('status', 'RETURNED')->get();
        foreach ($completedItems as $item) {
            // BookCopy status might be BORROWED if it was re-borrowed later, but we can just check returned_at is filled
            $this->assertNotNull($item->returned_at);
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MemberTypeSeeder::class);
        $this->seed(ClassLevelSeeder::class);
        $this->seed(MajorSeeder::class);
        $this->seed(CollectionTypeSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(PublisherSeeder::class);
        $this->seed(LocationSeeder::class);

        $this->seed(MemberSeeder::class);
        $this->seed(BookSeeder::class);
        $this->seed(BookCopySeeder::class);

        $this->seed(LoanSeeder::class);
        $loansCount = Loan::count();
        $itemsCount = LoanItem::count();

        $this->seed(LoanSeeder::class);

        $this->assertSame($loansCount, Loan::count());
        $this->assertSame($itemsCount, LoanItem::count());
    }
}
