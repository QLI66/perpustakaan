<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\FinePayment;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Database\Seeders\BookCopySeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ClassLevelSeeder;
use Database\Seeders\CollectionTypeSeeder;
use Database\Seeders\FineSeeder;
use Database\Seeders\LoanSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\MajorSeeder;
use Database\Seeders\MemberSeeder;
use Database\Seeders\MemberTypeSeeder;
use Database\Seeders\PublisherSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FineTest extends TestCase
{
    use RefreshDatabase;

    private function makeDependencies(): array
    {
        $mType = MemberType::create(['code' => 'TEST', 'name' => 'Tes']);
        $member = Member::create(['member_number' => 'MBR-01', 'name' => 'A', 'member_type_id' => $mType->id, 'joined_at' => '2025-01-01']);
        $location = Location::create(['code' => 'TEST', 'name' => 'Tes']);
        $cType = CollectionType::create(['code' => 'TEST', 'name' => 'Tes']);
        $book = Book::create(['title' => 'Buku', 'collection_type_id' => $cType->id]);
        $copy = BookCopy::create(['book_id' => $book->id, 'barcode' => 'BC-001', 'inventory_number' => 'INV-01', 'status' => 'AVAILABLE', 'location_id' => $location->id]);
        $loan = Loan::create(['loan_number' => 'L-01', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        $item = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        $user = User::factory()->create();

        return [$item, $user, $copy];
    }

    public function test_fines_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('fines'));
    }

    public function test_fine_payments_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('fine_payments'));
    }

    public function test_one_loan_item_only_has_one_fine(): void
    {
        [$item] = $this->makeDependencies();

        Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->expectException(QueryException::class);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 2, 'rate_per_day' => 1000, 'amount' => 2000, 'assessed_at' => now()]);
    }

    public function test_loan_item_required(): void
    {
        $this->expectException(QueryException::class);
        Fine::create(['late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
    }

    public function test_late_days_required(): void
    {
        [$item] = $this->makeDependencies();
        $this->expectException(QueryException::class);
        Fine::create(['loan_item_id' => $item->id, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
    }

    public function test_rate_per_day_required(): void
    {
        [$item] = $this->makeDependencies();
        $this->expectException(QueryException::class);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'amount' => 1000, 'assessed_at' => now()]);
    }

    public function test_amount_required(): void
    {
        [$item] = $this->makeDependencies();
        $this->expectException(QueryException::class);
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'assessed_at' => now()]);
    }

    public function test_amount_paid_defaults_zero_and_status_unpaid(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->assertEquals(0, $fine->fresh()->amount_paid);
        $this->assertSame('UNPAID', $fine->fresh()->status);
    }

    public function test_fine_belongs_to_loan_item_and_vice_versa(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->assertInstanceOf(LoanItem::class, $fine->loanItem);
        $this->assertInstanceOf(Fine::class, $item->fine);
        $this->assertEquals($fine->id, $item->fine->id);
    }

    public function test_fine_has_many_payments_and_belongs_to_fine(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
        $payment = FinePayment::create(['fine_id' => $fine->id, 'amount' => 1000, 'paid_at' => now()]);

        $this->assertInstanceOf(Fine::class, $payment->fine);
        $this->assertCount(1, $fine->payments);
        $this->assertInstanceOf(FinePayment::class, $fine->payments->first());
    }

    public function test_payment_amount_must_be_greater_than_zero(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->expectException(QueryException::class);
        FinePayment::create(['fine_id' => $fine->id, 'amount' => 0, 'paid_at' => now()]);
    }

    public function test_amount_paid_cannot_exceed_amount(): void
    {
        [$item] = $this->makeDependencies();
        $this->expectException(QueryException::class);
        // Will fail check constraint chk_fines_amount_paid_valid (amount_paid <= amount)
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'amount_paid' => 2000, 'assessed_at' => now()]);
    }

    public function test_late_days_can_be_zero(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 0, 'rate_per_day' => 1000, 'amount' => 0, 'assessed_at' => now()]);
        $this->assertEquals(0, $fine->late_days);
    }

    public function test_fine_amount_calculation_invariant_seeder(): void
    {
        $this->seed(RolePermissionSeeder::class);
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

        $this->seed(FineSeeder::class);

        $fines = Fine::all();
        foreach ($fines as $fine) {
            $this->assertEquals($fine->amount, $fine->late_days * $fine->rate_per_day);
            $this->assertLessThanOrEqual($fine->amount, $fine->amount_paid);
            if ($fine->status === 'PAID') {
                $this->assertEquals($fine->amount, $fine->amount_paid);
            }
            if ($fine->status === 'UNPAID') {
                $this->assertEquals(0, $fine->amount_paid);
            }
            if ($fine->status === 'PARTIAL') {
                $this->assertGreaterThan(0, $fine->amount_paid);
                $this->assertLessThan($fine->amount, $fine->amount_paid);
            }
        }
    }

    public function test_payment_method_valid_values(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $p1 = FinePayment::create(['fine_id' => $fine->id, 'amount' => 500, 'paid_at' => now(), 'payment_method' => 'CASH']);
        $p2 = FinePayment::create(['fine_id' => $fine->id, 'amount' => 500, 'paid_at' => now(), 'payment_method' => 'TRANSFER']);
        $p3 = FinePayment::create(['fine_id' => $fine->id, 'amount' => 500, 'paid_at' => now(), 'payment_method' => 'OTHER']);

        $this->assertEquals('CASH', $p1->payment_method);
        $this->assertEquals('TRANSFER', $p2->payment_method);
        $this->assertEquals('OTHER', $p3->payment_method);
    }

    public function test_paid_at_nullable_on_unpaid_fine(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
        $this->assertNull($fine->paid_at);
    }

    public function test_paid_status_can_have_paid_at(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'status' => 'PAID', 'amount_paid' => 1000, 'assessed_at' => now(), 'paid_at' => now()]);
        $this->assertNotNull($fine->paid_at);
    }

    public function test_deleting_loan_item_is_restricted_when_fine_exists(): void
    {
        [$item] = $this->makeDependencies();
        Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);

        $this->expectException(QueryException::class);
        $item->delete();
    }

    public function test_deleting_fine_cascades_fine_payments(): void
    {
        [$item] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
        FinePayment::create(['fine_id' => $fine->id, 'amount' => 1000, 'paid_at' => now()]);

        $fine->delete();
        $this->assertDatabaseEmpty('fine_payments');
    }

    public function test_deleting_received_user_sets_received_by_null(): void
    {
        [$item, $user] = $this->makeDependencies();
        $fine = Fine::create(['loan_item_id' => $item->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'assessed_at' => now()]);
        $payment = FinePayment::create(['fine_id' => $fine->id, 'amount' => 1000, 'paid_at' => now(), 'received_by' => $user->id]);

        $user->delete();
        $this->assertNull($payment->fresh()->received_by);
    }

    public function test_scopes_work_correctly(): void
    {
        // Needs separate unique loan items since fine loan_item_id is UNIQUE
        $mType = MemberType::create(['code' => 'T1', 'name' => 'Tes']);
        $member = Member::create(['member_number' => 'MBR-T', 'name' => 'A', 'member_type_id' => $mType->id, 'joined_at' => '2025-01-01']);
        $location = Location::create(['code' => 'T1', 'name' => 'Tes']);
        $cType = CollectionType::create(['code' => 'T1', 'name' => 'Tes']);
        $book = Book::create(['title' => 'Buku', 'collection_type_id' => $cType->id]);
        $copy1 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B1', 'inventory_number' => 'INV-1', 'status' => 'AVAILABLE', 'location_id' => $location->id]);
        $copy2 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B2', 'inventory_number' => 'INV-2', 'status' => 'AVAILABLE', 'location_id' => $location->id]);
        $copy3 = BookCopy::create(['book_id' => $book->id, 'barcode' => 'B3', 'inventory_number' => 'INV-3', 'status' => 'AVAILABLE', 'location_id' => $location->id]);

        $loan = Loan::create(['loan_number' => 'L-X', 'member_id' => $member->id, 'borrowed_at' => '2026-01-01']);
        $item1 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy1->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);
        $item2 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy2->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);
        $item3 = LoanItem::create(['loan_id' => $loan->id, 'book_copy_id' => $copy3->id, 'borrowed_at' => '2026-01-01', 'due_at' => '2026-01-04']);

        Fine::create(['loan_item_id' => $item1->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'status' => 'UNPAID', 'assessed_at' => now()]);
        Fine::create(['loan_item_id' => $item2->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'status' => 'PARTIAL', 'amount_paid' => 500, 'assessed_at' => now()]);
        Fine::create(['loan_item_id' => $item3->id, 'late_days' => 1, 'rate_per_day' => 1000, 'amount' => 1000, 'status' => 'PAID', 'amount_paid' => 1000, 'assessed_at' => now()]);

        $this->assertEquals(2, Fine::unpaid()->count()); // UNPAID and PARTIAL
        $this->assertEquals(1, Fine::paid()->count()); // PAID
    }

    public function test_seeder_creates_fines_and_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
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

        $this->seed(FineSeeder::class);
        $finesCount = Fine::count();
        $paymentsCount = FinePayment::count();

        $this->assertGreaterThan(0, $finesCount);

        // Run seeder again to test idempotency
        $this->seed(FineSeeder::class);
        $this->assertEquals($finesCount, Fine::count());
        $this->assertEquals($paymentsCount, FinePayment::count());
    }
}
