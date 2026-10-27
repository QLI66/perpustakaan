<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Http\Resources\FineResource;
use App\Http\Resources\ReturnResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\LoanItem;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use App\Services\LoanService;
use App\Services\RenewalService;
use App\Services\ReturnService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HourlyLoanBackendTest extends TestCase
{
    private User $memberUser;

    private Member $member;

    private BookCopy $bookCopy;

    protected function setUp(): void
    {
        parent::setUp();

        if (
            ! app()->environment('testing')
            || config('database.default') !== 'pgsql'
            || config('database.connections.pgsql.database') !== 'testing'
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::selectOne('SELECT current_database() AS name')->name !== 'testing'
        ) {
            throw new \RuntimeException('Hourly loan tests require APP_ENV=testing and PostgreSQL database testing.');
        }

        DB::connection()->beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-10-14 09:00:00', 'UTC'));

        $suffix = strtoupper(bin2hex(random_bytes(5)));
        $this->memberUser = User::factory()->create();
        $memberType = MemberType::create([
            'code' => 'H'.$suffix,
            'name' => 'Hourly Test Member Type',
        ]);
        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'H-'.$suffix,
            'name' => 'Hourly Test Member',
            'member_type_id' => $memberType->id,
            'joined_at' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        $collectionType = CollectionType::create([
            'code' => 'HC'.$suffix,
            'name' => 'Hourly Test Collection',
        ]);
        $book = Book::create([
            'title' => 'Hourly Test Book',
            'collection_type_id' => $collectionType->id,
        ]);
        $location = Location::create([
            'code' => 'HL'.$suffix,
            'name' => 'Hourly Test Location',
        ]);
        $this->bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'H-'.$suffix,
            'inventory_number' => 'H-'.$suffix,
            'status' => 'AVAILABLE',
            'location_id' => $location->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }

        parent::tearDown();
    }

    public static function dailyDurations(): array
    {
        return [
            'one day' => [1],
            'two days' => [2],
            'three days' => [3],
        ];
    }

    public static function invalidExplicitDurations(): array
    {
        return [
            'unknown unit' => [['duration_unit' => 'week', 'duration_value' => 1]],
            'hourly value two' => [['duration_unit' => 'hour', 'duration_value' => 2]],
            'zero days' => [['duration_unit' => 'day', 'duration_value' => 0]],
            'four days' => [['duration_unit' => 'day', 'duration_value' => 4]],
            'negative duration' => [['duration_unit' => 'day', 'duration_value' => -1]],
            'non-numeric duration' => [['duration_unit' => 'day', 'duration_value' => 'soon']],
            'unit without value' => [['duration_unit' => 'hour']],
            'value without unit' => [['duration_value' => 1]],
            'legacy and explicit duration together' => [
                ['duration_unit' => 'hour', 'duration_value' => 1, 'loan_duration_days' => 1],
            ],
        ];
    }

    public static function hourlyFineCases(): array
    {
        return [
            '59 minutes late' => [59, 0, false],
            'one full hour late' => [60, 1, true],
            'one hour thirty minutes late' => [90, 1, true],
            'two full hours late' => [120, 2, true],
        ];
    }

    #[DataProvider('dailyDurations')]
    public function test_explicit_daily_duration_preserves_calendar_deadline(int $days): void
    {
        $response = $this->createLoanThroughApi([
            'duration_unit' => 'day',
            'duration_value' => $days,
        ]);

        $response->assertCreated();
        $item = $response->json('data.items.0');
        $expectedDueAt = Carbon::parse('2026-10-15 00:00:00', 'UTC')->addDays($days - 1);

        $this->assertSame('day', $item['duration_unit']);
        $this->assertSame($days, $item['duration_value']);
        $this->assertSame(
            $expectedDueAt->toISOString(),
            Carbon::parse($item['due_at'])->utc()->toISOString()
        );
    }

    public function test_explicit_one_hour_duration_uses_actual_borrow_time(): void
    {
        $response = $this->createLoanThroughApi([
            'duration_unit' => 'hour',
            'duration_value' => 1,
        ]);

        $response->assertCreated();
        $item = $response->json('data.items.0');

        $this->assertSame('hour', $item['duration_unit']);
        $this->assertSame(1, $item['duration_value']);
        $this->assertSame(
            Carbon::parse('2026-10-14 10:00:00', 'UTC')->toISOString(),
            Carbon::parse($item['due_at'])->utc()->toISOString()
        );
        $this->assertDatabaseHas('loan_items', [
            'id' => $item['id'],
            'duration_unit' => 'hour',
            'duration_value' => 1,
        ]);
    }

    public function test_legacy_one_day_request_remains_daily(): void
    {
        $response = $this->createLoanThroughApi(['loan_duration_days' => 1]);

        $response->assertCreated();
        $item = $response->json('data.items.0');

        $this->assertSame('day', $item['duration_unit']);
        $this->assertSame(1, $item['duration_value']);
        $this->assertSame(
            Carbon::parse('2026-10-15 00:00:00', 'UTC')->toISOString(),
            Carbon::parse($item['due_at'])->utc()->toISOString()
        );
    }

    public function test_hourly_overdue_status_and_member_filter_use_due_timestamp(): void
    {
        $item = $this->createServiceLoan('hour', 1);
        Carbon::setTestNow($item->due_at->copy()->addMinutes(59));

        $response = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 1);
        $response->assertJsonPath('data.items.0.summary.overdue_items', 1);
        $response->assertJsonPath('data.items.0.items.0.is_overdue', true);
        $response->assertJsonPath('data.items.0.items.0.overdue_hours', 0);

        Carbon::setTestNow($item->due_at->copy()->subSecond());
        $beforeDueResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $beforeDueResponse->assertOk();
        $beforeDueResponse->assertJsonPath('data.pagination.total', 0);
    }

    public function test_daily_overdue_status_and_filter_follow_due_timestamp_on_refresh(): void
    {
        $item = $this->createServiceLoan('day', 1);
        $dueAt = $item->due_at->copy();

        Carbon::setTestNow($dueAt->copy()->subMinute());
        $beforeDueResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $beforeDueResponse->assertOk();
        $beforeDueResponse->assertJsonPath('data.pagination.total', 0);

        Carbon::setTestNow($dueAt);
        $atDueResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $atDueResponse->assertOk();
        $atDueResponse->assertJsonPath('data.pagination.total', 0);

        Carbon::setTestNow($dueAt->copy()->addMinute());
        $afterDueResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $afterDueResponse->assertOk();
        $afterDueResponse->assertJsonPath('data.pagination.total', 1);
        $afterDueResponse->assertJsonPath('data.items.0.summary.overdue_items', 1);
        $afterDueResponse->assertJsonPath('data.items.0.items.0.is_overdue', true);
        $afterDueResponse->assertJsonPath('data.items.0.items.0.overdue_days', 1);

        $refreshedResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=1');

        $refreshedResponse->assertOk();
        $refreshedResponse->assertJsonPath('data.pagination.total', 1);
        $refreshedResponse->assertJsonPath('data.items.0.items.0.is_overdue', true);

        $notOverdueResponse = $this->actingAs($this->memberUser, 'sanctum')
            ->getJson('/api/me/loans?overdue=0');

        $notOverdueResponse->assertOk();
        $notOverdueResponse->assertJsonPath('data.pagination.total', 0);
    }

    public function test_daily_on_time_return_creates_no_fine(): void
    {
        $item = $this->createServiceLoan('day', 1);

        app(ReturnService::class)->returnItem($item->id, $item->due_at->copy());

        $this->assertSame('RETURNED', $item->fresh()->status);
        $this->assertDatabaseMissing('fines', ['loan_item_id' => $item->id]);
    }

    #[DataProvider('invalidExplicitDurations')]
    public function test_api_rejects_invalid_or_ambiguous_explicit_duration(array $duration): void
    {
        $response = $this->createLoanThroughApi($duration);

        $response->assertUnprocessable();
        $this->assertNotEmpty($response->json('errors'));
        $this->assertDatabaseCount('loans', 0);
    }

    #[DataProvider('hourlyFineCases')]
    public function test_hourly_return_assesses_only_complete_late_hours(
        int $minutesLate,
        int $expectedLateHours,
        bool $expectFine
    ): void {
        $item = $this->createServiceLoan('hour', 1);
        $returnedAt = $item->due_at->copy()->addMinutes($minutesLate);

        $returned = app(ReturnService::class)->returnItem($item->id, $returnedAt);
        $payload = (new ReturnResource($returned->load('loan', 'fine')))->resolve(request());

        $this->assertSame('RETURNED', $item->fresh()->status);
        $this->assertSame($returnedAt->toDateTimeString(), $item->fresh()->returned_at->toDateTimeString());
        $this->assertTrue($payload['loan_item']['is_overdue']);
        $this->assertSame($expectedLateHours, $payload['loan_item']['overdue_hours']);

        if (! $expectFine) {
            $this->assertNull($payload['fine']);
            $this->assertDatabaseMissing('fines', ['loan_item_id' => $item->id]);

            return;
        }

        $this->assertSame('hour', $payload['fine']['late_unit']);
        $this->assertSame($expectedLateHours, $payload['fine']['late_units']);
        $this->assertSame(500, (int) $payload['fine']['rate_per_unit']);
        $this->assertSame($expectedLateHours * 500, (int) $payload['fine']['amount']);
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item->id,
            'late_unit' => 'hour',
            'late_units' => $expectedLateHours,
            'rate_per_unit' => 500,
            'amount' => $expectedLateHours * 500,
            'late_days' => 0,
            'rate_per_day' => 0,
        ]);
    }

    public function test_daily_return_still_uses_configured_calendar_day_rate(): void
    {
        $item = $this->createServiceLoan('day', 1);
        $returnedAt = $item->due_at->copy()->addHours(2);
        $dailyRate = (int) config('library.fine_rate_per_day', 1000);

        $returned = app(ReturnService::class)->returnItem($item->id, $returnedAt);
        $finePayload = (new FineResource($returned->fine->load('payments')))->resolve(request());

        $this->assertSame('day', $finePayload['late_unit']);
        $this->assertSame(1, $finePayload['late_units']);
        $this->assertSame($dailyRate, (int) $finePayload['rate_per_unit']);
        $this->assertSame($dailyRate, (int) $finePayload['amount']);
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item->id,
            'late_unit' => 'day',
            'late_units' => 1,
            'rate_per_unit' => $dailyRate,
            'late_days' => 1,
            'rate_per_day' => $dailyRate,
            'amount' => $dailyRate,
        ]);
    }

    public function test_hourly_loan_cannot_be_renewed(): void
    {
        $item = $this->createServiceLoan('hour', 1);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Hourly loan items cannot be renewed.');

        app(RenewalService::class)->renewItem($item->id, Carbon::parse('2026-10-14 09:30:00', 'UTC'));
    }

    public function test_late_return_cannot_create_a_second_fine(): void
    {
        $item = $this->createServiceLoan('hour', 1);
        $returnService = app(ReturnService::class);
        $returnService->returnItem($item->id, $item->due_at->copy()->addHour());

        try {
            $returnService->returnItem($item->id, $item->due_at->copy()->addHours(2));
            $this->fail('Returning an already returned item should be rejected.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('Loan item has already been returned or is not active.', $exception->getMessage());
        }

        $this->assertDatabaseCount('fines', 1);
    }

    private function createLoanThroughApi(array $duration): TestResponse
    {
        return $this->actingAs($this->memberUser, 'sanctum')
            ->postJson('/api/loans', [
                'book_copy_ids' => [$this->bookCopy->id],
                ...$duration,
            ]);
    }

    private function createServiceLoan(string $unit, int $value): LoanItem
    {
        $loan = app(LoanService::class)->createLoan(
            $this->member->id,
            [$this->bookCopy->id],
            Carbon::parse('2026-10-14 09:00:00', 'UTC'),
            null,
            null,
            $unit,
            $value
        );

        return $loan->items->firstOrFail();
    }
}
