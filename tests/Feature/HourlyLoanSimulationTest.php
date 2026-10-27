<?php

namespace Tests\Feature;

use App\Http\Resources\FineResource;
use App\Http\Resources\LoanItemResource;
use App\Http\Resources\ReturnResource;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Fine;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Services\LoanService;
use App\Services\ReturnService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HourlyLoanSimulationTest extends TestCase
{
    private LoanService $loanService;

    private ReturnService $returnService;

    protected function setUp(): void
    {
        parent::setUp();

        $databaseName = DB::selectOne('SELECT current_database() AS database_name')->database_name;
        if (
            ! app()->environment('testing')
            || DB::connection()->getDriverName() !== 'pgsql'
            || $databaseName !== 'testing'
        ) {
            throw new \RuntimeException('Hourly loan simulation tests require APP_ENV=testing and PostgreSQL database testing.');
        }

        DB::connection()->beginTransaction();
        $this->loanService = new LoanService;
        $this->returnService = new ReturnService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if (DB::connection()->transactionLevel() > 0) {
            DB::connection()->rollBack();
        }

        parent::tearDown();
    }

    public static function hourlyThresholdCases(): array
    {
        return [
            '30 minutes after borrowing' => [30, false, 0, 0],
            'exactly at the due time' => [60, false, 0, 0],
            '30 minutes after due time' => [90, true, 0, 0],
            '59 minutes after due time' => [119, true, 0, 0],
            'one full hour after due time' => [120, true, 1, 500],
            'one hour and 30 minutes after due time' => [150, true, 1, 500],
            'two full hours after due time' => [180, true, 2, 1000],
        ];
    }

    public function test_loan_service_creates_a_successful_one_hour_simulation_fixture(): void
    {
        [$loan, $item, $copy, $borrowedAt, $dueAt] = $this->createOneHourLoanFixture();

        $this->assertSame('ACTIVE', $loan->status);
        $this->assertSame('ACTIVE', $item->status);
        $this->assertSame('BORROWED', $copy->fresh()->status);
        $this->assertSame($borrowedAt->copy()->addHour()->toDateTimeString(), $dueAt->toDateTimeString());
        $this->assertSame($dueAt->toDateTimeString(), $item->due_at->toDateTimeString());
    }

    #[DataProvider('hourlyThresholdCases')]
    public function test_hourly_simulation_calculates_full_hours_without_sleep(
        int $minutesAfterBorrowing,
        bool $expectedOverdue,
        int $expectedHours,
        int $expectedFine
    ): void {
        $borrowedAt = Carbon::parse('2026-10-13 09:00:00', 'UTC');
        $dueAt = $borrowedAt->copy()->addHour();
        Carbon::setTestNow($borrowedAt->copy()->addMinutes($minutesAfterBorrowing));

        $simulation = $this->simulateHourlyStatus($dueAt);

        $this->assertSame($expectedOverdue, $simulation['is_overdue']);
        $this->assertSame($expectedHours, $simulation['overdue_hours']);
        $this->assertSame($expectedFine, $simulation['fine_amount']);
    }

    public function test_production_loan_resource_applies_option_b_daily_fine_for_same_day_overdue(): void
    {
        [, $item, , , $dueAt] = $this->createOneHourLoanFixture();
        Carbon::setTestNow($dueAt->copy()->addHour());

        $hourlySimulation = $this->simulateHourlyStatus($dueAt);
        $productionPayload = (new LoanItemResource($item->fresh()))->resolve(request());

        $this->assertSame(1, $hourlySimulation['overdue_hours']);
        $this->assertSame(500, $hourlySimulation['fine_amount']);
        $this->assertTrue($productionPayload['is_overdue']);
        $this->assertSame(1, $productionPayload['overdue_days']);
    }

    public function test_return_saves_timestamp_and_completes_loan_with_same_day_production_fine(): void
    {
        [$loan, $item, $copy, , $dueAt] = $this->createOneHourLoanFixture();
        $returnedAt = $dueAt->copy()->addHour();

        $returned = $this->returnService->returnItem($item->id, $returnedAt);
        $payload = (new ReturnResource($returned->load('loan', 'fine')))->resolve(request());

        $this->assertSame('RETURNED', $returned->fresh()->status);
        $this->assertSame($returnedAt->toDateTimeString(), $returned->fresh()->returned_at->toDateTimeString());
        $this->assertSame('AVAILABLE', $copy->fresh()->status);
        $this->assertSame('COMPLETED', $loan->fresh()->status);
        $this->assertNotNull($payload['fine']);
        $this->assertTrue($payload['loan_item']['is_overdue']);
        $this->assertSame(1, $payload['loan_item']['overdue_days']);
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item->id,
            'late_days' => 1,
            'amount' => 1000,
        ]);
    }

    public function test_calendar_day_regression_exposes_fine_in_return_resource(): void
    {
        [$loan, $item, , , $dueAt] = $this->createOneHourLoanFixture();
        $returnedAt = $dueAt->copy()->addDay();
        $dailyRate = (int) config('library.fine_rate_per_day', 1000);

        $returned = $this->returnService->returnItem($item->id, $returnedAt);
        $payload = (new ReturnResource($returned->load('loan', 'fine')))->resolve(request());

        $this->assertSame('COMPLETED', $loan->fresh()->status);
        $this->assertSame('RETURNED', $returned->fresh()->status);
        $this->assertTrue($payload['loan_item']['is_overdue']);
        $this->assertSame(2, $payload['loan_item']['overdue_days']);
        $this->assertSame(2 * $dailyRate, (int) $payload['fine']['amount']);
        $this->assertSame('UNPAID', $payload['fine']['status']);
        $this->assertDatabaseHas('fines', [
            'loan_item_id' => $item->id,
            'late_days' => 2,
            'amount' => 2 * $dailyRate,
        ]);

        $finePayload = (new FineResource(
            Fine::where('loan_item_id', $item->id)->firstOrFail()->load('payments')
        ))->resolve(request());

        $this->assertSame(2 * $dailyRate, (int) $finePayload['amount']);
        $this->assertSame('UNPAID', $finePayload['status']);
        $this->assertSame(0, (int) $finePayload['amount_paid']);
    }

    private function createOneHourLoanFixture(): array
    {
        Config::set('library.loan_days', 1);
        $borrowedAt = Carbon::parse('2026-10-13 09:00:00', 'UTC');
        Carbon::setTestNow($borrowedAt);

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $memberType = MemberType::create([
            'code' => 'H'.$suffix,
            'name' => 'Hourly QA',
        ]);
        $member = Member::create([
            'member_number' => 'HQA-'.$suffix,
            'name' => 'Hourly QA Member',
            'member_type_id' => $memberType->id,
            'joined_at' => '2025-01-01',
        ]);
        $location = Location::create([
            'code' => 'H'.$suffix,
            'name' => 'Hourly QA Location',
        ]);
        $collectionType = CollectionType::create([
            'code' => 'H'.$suffix,
            'name' => 'Hourly QA Collection',
        ]);
        $book = Book::create([
            'title' => 'Hourly QA Book',
            'collection_type_id' => $collectionType->id,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'HQA-'.$suffix,
            'inventory_number' => 'HQA-'.$suffix,
            'status' => 'AVAILABLE',
            'location_id' => $location->id,
        ]);

        $loan = $this->loanService->createLoan($member->id, [$copy->id], $borrowedAt);
        $item = $loan->items->firstOrFail();
        $dueAt = $borrowedAt->copy()->addHour();

        $item->due_at = $dueAt;
        $item->save();

        return [$loan, $item->fresh(), $copy, $borrowedAt, $dueAt];
    }

    /**
     * Test-only hourly model; it is deliberately not used by application services or API resources.
     *
     * @return array{is_overdue: bool, overdue_hours: int, fine_amount: int}
     */
    private function simulateHourlyStatus(Carbon $dueAt): array
    {
        $lateSeconds = now()->getTimestamp() - $dueAt->getTimestamp();
        $overdueHours = $lateSeconds > 0 ? intdiv($lateSeconds, 3600) : 0;

        return [
            'is_overdue' => $lateSeconds > 0,
            'overdue_hours' => $overdueHours,
            'fine_amount' => $overdueHours * 500,
        ];
    }
}
