<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Services\ReturnService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FineApiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $libraryStaff;

    private User $memberUser1;

    private User $memberUser2;

    private Member $member1;

    private Member $member2;

    private Loan $loan1;

    private LoanItem $loanItem1;

    private Fine $fine1;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'testing') {
            throw new \RuntimeException('Fine payment tests must run against the isolated PostgreSQL testing database.');
        }

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::create(['name' => 'Super Admin', 'email' => 'sa@test.com', 'password' => Hash::make('p')]);
        $this->superAdmin->assignRole('SUPER_ADMIN');

        $this->libraryStaff = User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => Hash::make('p')]);
        $this->libraryStaff->assignRole('LIBRARY_STAFF');

        $memberType = MemberType::create(['code' => 'STD', 'name' => 'Student']);

        $this->memberUser1 = User::create(['name' => 'Member1', 'email' => 'member1@test.com', 'password' => Hash::make('p')]);
        $this->memberUser1->assignRole('MEMBER');
        $this->member1 = Member::create([
            'user_id' => $this->memberUser1->id,
            'member_number' => 'MEM-001',
            'name' => 'Member One',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $this->memberUser2 = User::create(['name' => 'Member2', 'email' => 'member2@test.com', 'password' => Hash::make('p')]);
        $this->memberUser2->assignRole('MEMBER');
        $this->member2 = Member::create([
            'user_id' => $this->memberUser2->id,
            'member_number' => 'MEM-002',
            'name' => 'Member Two',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $collectionType = CollectionType::create(['code' => 'FIC', 'name' => 'Fiction']);
        $book = Book::create([
            'title' => 'Test Book',
            'isbn' => '1234567890123',
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);
        $location = Location::create(['code' => 'A1', 'name' => 'Shelf A1']);

        $bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'BC-001',
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);

        $this->loan1 = Loan::create([
            'loan_number' => 'LOAN-1',
            'member_id' => $this->member1->id,
            'borrowed_at' => now()->subDays(5),
            'status' => 'COMPLETED',
        ]);

        $this->loanItem1 = LoanItem::create([
            'loan_id' => $this->loan1->id,
            'book_copy_id' => $bookCopy->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'returned_at' => now(),
            'status' => 'RETURNED',
        ]);

        $this->fine1 = Fine::create([
            'loan_item_id' => $this->loanItem1->id,
            'late_days' => 2,
            'rate_per_day' => 1000,
            'amount' => 2000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);
    }

    // A. AUTHENTICATION
    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/fines')->assertStatus(401);
        $this->getJson("/api/fines/{$this->fine1->id}")->assertStatus(401);
        $this->getJson('/api/me/fines')->assertStatus(401);
        $this->postJson("/api/fines/{$this->fine1->id}/payments")->assertStatus(401);
    }

    // B. AUTHORIZATION (LIST / DETAIL)
    public function test_super_admin_can_view_all_fines(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/fines')->assertStatus(200);
    }

    public function test_member_cannot_view_all_fines(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/fines')->assertStatus(403);
    }

    public function test_member_can_view_own_fine(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson("/api/fines/{$this->fine1->id}");
        $response->assertStatus(200);
        $this->assertEquals($this->fine1->id, $response->json('data.id'));
    }

    public function test_member_cannot_view_another_members_fine(): void
    {
        $this->actingAs($this->memberUser2, 'sanctum')->getJson("/api/fines/{$this->fine1->id}")->assertStatus(403);
    }

    public function test_member_can_access_me_fines(): void
    {
        $response = $this->actingAs($this->memberUser1, 'sanctum')->getJson('/api/me/fines');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertEquals($this->fine1->id, $response->json('data.items.0.id'));
    }

    public function test_member_fines_include_one_minute_daily_accrual_without_persisting_or_duplicating_it(): void
    {
        $item = $this->createOverdueDailyItem(now()->subMinute());
        $response = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines')
            ->assertOk()
            ->assertJsonPath('data.summary.total_amount', '3000.00')
            ->assertJsonPath('data.summary.outstanding_amount', '3000.00');

        $accruedFine = collect($response->json('data.items'))
            ->firstWhere('loan_item_id', $item->id);
        $this->assertSame('1000.00', $accruedFine['amount']);
        $this->assertSame(1, $accruedFine['late_days']);
        $this->assertTrue($accruedFine['is_accruing']);

        $refreshed = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines')
            ->assertOk();
        $this->assertSame(
            '1000.00',
            collect($refreshed->json('data.items'))->firstWhere('loan_item_id', $item->id)['amount']
        );
        $this->assertDatabaseCount('fines', 1);

        app(ReturnService::class)->returnItem($item->id, now());

        $afterReturn = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines')
            ->assertOk()
            ->assertJsonPath('data.summary.total_amount', '3000.00')
            ->assertJsonPath('data.summary.outstanding_amount', '3000.00');

        $returnedFine = collect($afterReturn->json('data.items'))
            ->firstWhere('loan_item_id', $item->id);
        $this->assertNotNull($returnedFine);
        $this->assertFalse($returnedFine['is_accruing']);
        $this->assertDatabaseCount('fines', 2);
    }

    public function test_daily_accrual_adds_one_unit_for_each_started_day_after_due_time(): void
    {
        $item = $this->createOverdueDailyItem(now()->subDay()->subSecond());

        $response = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines')
            ->assertOk()
            ->assertJsonPath('data.summary.total_amount', '4000.00')
            ->assertJsonPath('data.summary.outstanding_amount', '4000.00');

        $accruedFine = collect($response->json('data.items'))
            ->firstWhere('loan_item_id', $item->id);
        $this->assertSame(2, $accruedFine['late_days']);
        $this->assertSame('2000.00', $accruedFine['amount']);
        $this->assertTrue($accruedFine['is_accruing']);
        $this->assertDatabaseCount('fines', 1);
    }

    public function test_staff_fines_page_filter_returns_the_related_live_fine(): void
    {
        $item = $this->createOverdueDailyItem(now()->subMinute());

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/fines?loan_item_id={$item->id}")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.loan_item_id', $item->id)
            ->assertJsonPath('data.items.0.amount', '1000.00')
            ->assertJsonPath('data.items.0.is_accruing', true);

        $this->assertSame('accrual-'.$item->id, $response->json('data.items.0.id'));
        $this->assertDatabaseCount('fines', 1);
    }

    public function test_staff_fines_search_by_member_name_includes_accrued_fine(): void
    {
        $item = $this->createOverdueDailyItem(now()->subMinute());

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?search=Member One')
            ->assertOk();

        $this->assertSame(2, $response->json('data.pagination.total'));
        $items = collect($response->json('data.items'));
        $this->assertTrue($items->contains('id', $this->fine1->id));
        $this->assertTrue($items->contains('id', 'accrual-'.$item->id));
        $this->assertDatabaseCount('fines', 1);
    }

    public function test_staff_fines_filter_by_member_id_includes_accrued_fine(): void
    {
        $item = $this->createOverdueDailyItem(now()->subMinute());

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson("/api/fines?member_id={$this->member1->id}")
            ->assertOk();

        $this->assertSame(2, $response->json('data.pagination.total'));
        $items = collect($response->json('data.items'));
        $this->assertTrue($items->contains('id', $this->fine1->id));
        $this->assertTrue($items->contains('id', 'accrual-'.$item->id));
        $this->assertDatabaseCount('fines', 1);
    }

    public function test_staff_fines_general_list_includes_accrued_fines_without_duplication(): void
    {
        $item = $this->createOverdueDailyItem(now()->subMinute());

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines')
            ->assertOk();

        $this->assertSame(2, $response->json('data.pagination.total'));
        $items = collect($response->json('data.items'));
        $this->assertTrue($items->contains('id', $this->fine1->id));
        $this->assertTrue($items->contains('id', 'accrual-'.$item->id));
        $this->assertDatabaseCount('fines', 1);
    }

    public function test_two_overdue_items_are_listed_once_consistently_for_staff_and_member(): void
    {
        $firstItem = $this->createOverdueDailyItem(now()->subMinute());
        $secondCopy = BookCopy::create([
            'book_id' => $this->loanItem1->bookCopy->book_id,
            'barcode' => 'BC-002',
            'location_id' => $this->loanItem1->bookCopy->location_id,
            'status' => 'BORROWED',
        ]);
        $secondItem = $this->createOverdueDailyItem(now()->subMinute(), $secondCopy);
        $expectedItemIds = collect([$firstItem->id, $secondItem->id])->sort()->values()->all();

        $staffResponse = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?search=Member%20One&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3);

        $staffAccrued = collect($staffResponse->json('data.items'))
            ->where('is_accruing', true)
            ->values();
        $this->assertSame(
            $expectedItemIds,
            $staffAccrued->pluck('loan_item_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
        $this->assertCount(2, $staffAccrued);
        foreach ($staffAccrued as $fine) {
            $this->assertSame('1000.00', $fine['amount']);
        }

        $bookSearch = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?search=Test%20Book')
            ->assertOk();
        $this->assertSame(3, $bookSearch->json('data.pagination.total'));

        $firstPage = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?search=Test%20Book&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.last_page', 3);
        $secondPage = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?search=Test%20Book&per_page=1&page=2')
            ->assertOk();
        $this->assertNotSame(
            $firstPage->json('data.items.0.id'),
            $secondPage->json('data.items.0.id')
        );

        $memberResponse = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.summary.total_amount', '4000.00');
        $memberAccrued = collect($memberResponse->json('data.items'))
            ->where('is_accruing', true)
            ->values();
        $this->assertSame(
            $expectedItemIds,
            $memberAccrued->pluck('loan_item_id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
        $this->assertCount(2, $memberAccrued);
        $this->assertDatabaseCount('fines', 1);

        app(ReturnService::class)->returnItem($firstItem->id, now());

        $afterReturn = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?member_id='.$this->member1->id)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3);
        $returnedItemFines = collect($afterReturn->json('data.items'))
            ->where('loan_item_id', $firstItem->id)
            ->values();
        $this->assertCount(1, $returnedItemFines);
        $this->assertFalse($returnedItemFines->first()['is_accruing']);
        $remainingAccrual = collect($afterReturn->json('data.items'))
            ->firstWhere('loan_item_id', $secondItem->id);
        $this->assertTrue($remainingAccrual['is_accruing']);
        $this->assertDatabaseCount('fines', 2);
    }

    public function test_staff_fines_excludes_accrued_fines_when_filtering_by_paid_status(): void
    {
        $this->createOverdueDailyItem(now()->subMinute());

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->getJson('/api/fines?status=PAID')
            ->assertOk();

        $this->assertSame(0, $response->json('data.pagination.total'));
        $this->assertEmpty($response->json('data.items'));
    }

    public function test_member_fine_summary_combines_partial_payment_and_running_daily_accrual(): void
    {
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 500,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000005'))
            ->assertCreated();

        $this->createOverdueDailyItem(now()->subMinute());
        $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines')
            ->assertOk()
            ->assertJsonPath('data.summary.total_amount', '3000.00')
            ->assertJsonPath('data.summary.total_paid', '500.00')
            ->assertJsonPath('data.summary.outstanding_amount', '2500.00');
    }

    public function test_member_fine_summary_includes_fines_beyond_first_page(): void
    {
        for ($index = 0; $index < 50; $index++) {
            $loanItem = LoanItem::create([
                'loan_id' => $this->loan1->id,
                'book_copy_id' => $this->loanItem1->book_copy_id,
                'borrowed_at' => now()->subDays(5),
                'due_at' => now()->subDays(2),
                'returned_at' => now(),
                'status' => 'RETURNED',
            ]);

            Fine::create([
                'loan_item_id' => $loanItem->id,
                'late_days' => 1,
                'rate_per_day' => 1000,
                'amount' => 1000,
                'amount_paid' => 250,
                'status' => 'PARTIAL',
                'assessed_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->memberUser1, 'sanctum')
            ->getJson('/api/me/fines?per_page=50');

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data.items'));
        $this->assertSame('52000.00', $response->json('data.summary.total_amount'));
        $this->assertSame('12500.00', $response->json('data.summary.total_paid'));
        $this->assertSame('39500.00', $response->json('data.summary.outstanding_amount'));
    }

    private function createOverdueDailyItem(Carbon $dueAt, ?BookCopy $bookCopy = null): LoanItem
    {
        $loan = Loan::create([
            'loan_number' => 'LOAN-LIVE-'.strtoupper(bin2hex(random_bytes(4))),
            'member_id' => $this->member1->id,
            'borrowed_at' => $dueAt->copy()->subDay(),
            'status' => 'ACTIVE',
        ]);

        $bookCopy ??= $this->loanItem1->bookCopy;
        $bookCopy->update(['status' => 'BORROWED']);

        return LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $bookCopy->id,
            'borrowed_at' => $dueAt->copy()->subDay(),
            'due_at' => $dueAt,
            'duration_unit' => 'day',
            'duration_value' => 1,
            'status' => 'ACTIVE',
        ]);
    }

    // C. SEARCH & FILTER
    public function test_fines_search_by_member_name(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/fines?search=Member One');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_fines_filter_by_status(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/fines?status=UNPAID');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.items'));

        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/fines?status=PAID');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.items'));
    }

    // D. PAYMENT (AUTHORIZATION)
    public function test_member_cannot_pay_fine(): void
    {
        $this->actingAs($this->memberUser1, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 1000,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000001'))
            ->assertStatus(403);
    }

    public function test_payment_requires_a_valid_idempotency_key(): void
    {
        $url = "/api/fines/{$this->fine1->id}/payments";
        $payload = ['amount' => 100, 'payment_method' => 'CASH'];

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson($url, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson($url, $payload, ['Idempotency-Key' => 'not-a-uuid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);

        $this->assertDatabaseCount('fine_payments', 0);
    }

    // E. PAYMENT (BUSINESS RULES)
    public function test_successful_partial_payment(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 1500,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000002'));

        $response->assertStatus(201);
        $this->assertEquals('1500.00', $response->json('data.amount'));

        $this->fine1->refresh();
        $this->assertEquals('1500.00', $this->fine1->amount_paid);
        $this->assertEquals('PARTIAL', $this->fine1->status);
        $this->assertNull($this->fine1->paid_at);
    }

    public function test_successful_full_payment(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 2000,
                'payment_method' => 'TRANSFER',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000003'));

        $response->assertStatus(201);
        $this->assertEquals('2000.00', $response->json('data.amount'));

        $this->fine1->refresh();
        $this->assertEquals('2000.00', $this->fine1->amount_paid);
        $this->assertEquals('PAID', $this->fine1->status);
        $this->assertNotNull($this->fine1->paid_at);
    }

    public function test_overpayment_rejected(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 5000,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000004'));

        $response->assertStatus(422);

        $this->fine1->refresh();
        $this->assertEquals('0.00', $this->fine1->amount_paid); // Unchanged
    }

    public function test_payment_on_fully_paid_fine_rejected(): void
    {
        $this->fine1->update(['amount_paid' => 2000, 'status' => 'PAID', 'paid_at' => now()]);

        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 100,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000005'));

        $response->assertStatus(422);
    }

    public function test_negative_payment_rejected_by_validation(): void
    {
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => -100,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000006'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_client_cannot_force_status(): void
    {
        // Even if client attempts to pass status=PAID, it should be ignored.
        $response = $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 500,
                'payment_method' => 'CASH',
                'status' => 'PAID', // Unfillable/Ignored
                'amount_paid' => 2000, // Unfillable/Ignored
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000007'));

        $response->assertStatus(201);

        $this->fine1->refresh();
        $this->assertEquals('500.00', $this->fine1->amount_paid);
        $this->assertEquals('PARTIAL', $this->fine1->status); // Remains PARTIAL
    }

    // F. DETAIL VIEW (DATA INTEGRITY)
    public function test_detail_view_includes_outstanding_and_payments(): void
    {
        // First, make a payment
        $this->actingAs($this->libraryStaff, 'sanctum')
            ->postJson("/api/fines/{$this->fine1->id}/payments", [
                'amount' => 500,
                'payment_method' => 'CASH',
            ], $this->paymentHeaders('00000000-0000-4000-8000-000000000008'));

        // Fetch detail
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/fines/{$this->fine1->id}");

        $response->assertStatus(200);
        $this->assertEquals('2000.00', $response->json('data.amount'));
        $this->assertEquals('500.00', $response->json('data.amount_paid'));
        $this->assertEquals('1500.00', $response->json('data.outstanding_amount'));
        $this->assertEquals('PARTIAL', $response->json('data.status'));

        $this->assertCount(1, $response->json('data.payment_history'));
        $this->assertEquals('500.00', $response->json('data.payment_history.0.amount'));
        $this->assertEquals('CASH', $response->json('data.payment_history.0.payment_method'));
    }

    // G. SECURITY
    public function test_no_sensitive_data_exposed(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/fines/{$this->fine1->id}");

        $jsonStr = json_encode($response->json());
        $this->assertStringNotContainsString('password', $jsonStr);
        $this->assertStringNotContainsString('remember_token', $jsonStr);
    }

    public function test_same_key_and_payload_replays_the_original_partial_payment(): void
    {
        $payload = ['amount' => 500, 'payment_method' => 'CASH'];
        $key = '00000000-0000-4000-8000-000000000009';

        $firstResponse = $this->postPayment($this->libraryStaff, $payload, $key);
        $replayResponse = $this->postPayment($this->libraryStaff, $payload, $key);

        $firstResponse->assertStatus(201);
        $replayResponse->assertStatus(200)
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($firstResponse->json('data.id'), $replayResponse->json('data.id'));
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertDatabaseHas('fine_payments', ['idempotency_key' => $key, 'amount' => '500.00']);
        $this->assertSame('500.00', $this->fine1->fresh()->amount_paid);
    }

    public function test_same_key_with_different_payload_returns_conflict(): void
    {
        $key = '00000000-0000-4000-8000-000000000010';
        $this->postPayment($this->libraryStaff, ['amount' => 500, 'payment_method' => 'CASH'], $key)
            ->assertStatus(201);

        $this->postPayment($this->libraryStaff, ['amount' => 600, 'payment_method' => 'CASH'], $key)
            ->assertStatus(409);

        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertSame('500.00', $this->fine1->fresh()->amount_paid);
    }

    public function test_lost_response_retry_returns_the_committed_payment(): void
    {
        $payload = ['amount' => 500, 'payment_method' => 'TRANSFER'];
        $key = '00000000-0000-4000-8000-000000000011';

        $this->postPayment($this->libraryStaff, $payload, $key)->assertStatus(201);
        $retryResponse = $this->postPayment($this->libraryStaff, $payload, $key);

        $retryResponse->assertStatus(200)
            ->assertHeader('Idempotency-Replayed', 'true');
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertSame('500.00', $this->fine1->fresh()->amount_paid);
    }

    public function test_different_keys_can_record_distinct_partial_payments(): void
    {
        $this->postPayment($this->libraryStaff, ['amount' => 500, 'payment_method' => 'CASH'], '00000000-0000-4000-8000-000000000012')
            ->assertStatus(201);
        $this->postPayment($this->libraryStaff, ['amount' => 700, 'payment_method' => 'TRANSFER'], '00000000-0000-4000-8000-000000000013')
            ->assertStatus(201);

        $this->assertDatabaseCount('fine_payments', 2);
        $this->assertSame('1200.00', $this->fine1->fresh()->amount_paid);
    }

    public function test_full_payment_retry_returns_the_original_payment(): void
    {
        $payload = ['amount' => 2000, 'payment_method' => 'CASH'];
        $key = '00000000-0000-4000-8000-000000000014';

        $firstResponse = $this->postPayment($this->libraryStaff, $payload, $key);
        $retryResponse = $this->postPayment($this->libraryStaff, $payload, $key);

        $firstResponse->assertStatus(201);
        $retryResponse->assertStatus(200);
        $this->assertSame($firstResponse->json('data.id'), $retryResponse->json('data.id'));
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertSame('2000.00', $this->fine1->fresh()->amount_paid);
        $this->assertSame('PAID', $this->fine1->fresh()->status);
    }

    public function test_zero_payment_is_rejected(): void
    {
        $this->postPayment($this->libraryStaff, ['amount' => 0, 'payment_method' => 'CASH'], '00000000-0000-4000-8000-000000000015')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('fine_payments', 0);
    }

    public function test_amount_with_more_than_two_decimal_places_is_rejected(): void
    {
        $this->postPayment($this->libraryStaff, ['amount' => '10.001', 'payment_method' => 'CASH'], '00000000-0000-4000-8000-000000000016')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('fine_payments', 0);
    }

    public function test_amount_with_two_decimal_places_is_accepted(): void
    {
        $response = $this->postPayment($this->libraryStaff, ['amount' => '10.00', 'payment_method' => 'CASH'], '00000000-0000-4000-8000-000000000017');

        $response->assertStatus(201);
        $this->assertSame('10.00', $response->json('data.amount'));
        $this->assertSame('10.00', $this->fine1->fresh()->amount_paid);
    }

    public function test_reference_number_with_100_characters_is_accepted(): void
    {
        $referenceNumber = str_repeat('R', 100);

        $this->postPayment($this->libraryStaff, [
            'amount' => 100,
            'payment_method' => 'TRANSFER',
            'reference_number' => $referenceNumber,
        ], '00000000-0000-4000-8000-000000000018')->assertStatus(201);

        $this->assertDatabaseHas('fine_payments', ['reference_number' => $referenceNumber]);
    }

    public function test_reference_number_with_101_characters_is_rejected(): void
    {
        $this->postPayment($this->libraryStaff, [
            'amount' => 100,
            'payment_method' => 'TRANSFER',
            'reference_number' => str_repeat('R', 101),
        ], '00000000-0000-4000-8000-000000000019')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference_number']);

        $this->assertDatabaseCount('fine_payments', 0);
    }

    public function test_payment_transaction_rolls_back_when_fine_update_fails(): void
    {
        Event::listen('eloquent.updating: '.Fine::class, function (): void {
            throw new \RuntimeException('Simulated fine update failure after payment insert.');
        });

        $this->postPayment($this->libraryStaff, ['amount' => 500, 'payment_method' => 'CASH'], '00000000-0000-4000-8000-000000000020')
            ->assertStatus(500);

        $this->assertDatabaseCount('fine_payments', 0);
        $this->assertSame('0.00', $this->fine1->fresh()->amount_paid);
        $this->assertSame('UNPAID', $this->fine1->fresh()->status);
    }

    private function postPayment(User $user, array $payload, string $idempotencyKey): TestResponse
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/fines/{$this->fine1->id}/payments",
                $payload,
                $this->paymentHeaders($idempotencyKey),
            );
    }

    private function paymentHeaders(string $idempotencyKey): array
    {
        return ['Idempotency-Key' => $idempotencyKey];
    }
}
