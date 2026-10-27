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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReturnConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('testing', app()->environment());
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('testing', config('database.connections.pgsql.database'));
        $this->assertSame('testing', DB::selectOne('SELECT current_database() AS name')->name);
    }

    public function test_concurrent_late_returns_create_one_return_and_one_fine(): void
    {
        $this->assertConcurrentReturn(
            Carbon::parse('2026-10-14 10:00:00', 'UTC'),
            true,
            500
        );
    }

    public function test_concurrent_on_time_returns_create_one_return_and_no_fine(): void
    {
        $this->assertConcurrentReturn(
            Carbon::parse('2026-10-14 09:00:00', 'UTC'),
            false,
            0
        );
    }

    private function assertConcurrentReturn(Carbon $returnedAt, bool $isLate, int $expectedFineAmount): void
    {
        $fixture = $this->createFixture();
        $loanItemId = $fixture['loanItem']->id;
        $loanId = $fixture['loan']->id;
        $bookCopyId = $fixture['bookCopy']->id;
        $transactionOpen = false;
        $processes = [];

        try {
            DB::beginTransaction();
            $transactionOpen = true;
            LoanItem::query()->lockForUpdate()->findOrFail($loanItemId);

            $workerCode = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = Illuminate\Support\Facades\DB::selectOne('SELECT current_database() AS name')->name;
if (
    !app()->environment('testing')
    || config('database.default') !== 'pgsql'
    || Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'pgsql'
    || $database !== 'testing'
) {
    fwrite(STDERR, 'Worker database verification failed.'.PHP_EOL);
    exit(2);
}
$pid = Illuminate\Support\Facades\DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
echo 'READY:'.$pid.':'.$database.PHP_EOL;
flush();
try {
    $item = app(App\Services\ReturnService::class)->returnItem(
        (int)getenv('RETURN_TEST_LOAN_ITEM_ID'),
        Carbon\Carbon::parse((string)getenv('RETURN_TEST_RETURNED_AT')),
    );
    echo 'RESULT:'.json_encode(['result' => 'returned', 'status' => $item->status], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (App\Exceptions\BusinessRuleException $exception) {
    echo 'RESULT:'.json_encode(['result' => 'rejected', 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

            foreach ([1, 2] as $_) {
                $process = new Process(
                    [PHP_BINARY, '-r', $workerCode],
                    base_path(),
                    $this->workerEnvironment($loanItemId, $returnedAt),
                );
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $workers = [];
            foreach ($processes as $process) {
                $workers[] = $this->waitForWorkerReady($process);
            }

            $workerPids = array_column($workers, 'pid');
            $this->assertCount(2, array_unique($workerPids), 'Each return worker must use an independent PostgreSQL backend.');
            $this->assertSame(['testing', 'testing'], array_column($workers, 'database'));

            $deadline = microtime(true) + 20;
            $blockedWorkers = 0;
            do {
                $blockedWorkers = (int) DB::table('pg_stat_activity')
                    ->whereIn('pid', $workerPids)
                    ->where('datname', 'testing')
                    ->where('wait_event_type', 'Lock')
                    ->count();

                if ($blockedWorkers === count($processes)) {
                    break;
                }

                usleep(100_000);
            } while (microtime(true) < $deadline);

            $activity = DB::table('pg_stat_activity')
                ->whereIn('pid', $workerPids)
                ->get(['pid', 'datname', 'state', 'wait_event_type', 'wait_event'])
                ->toArray();

            $this->assertSame(
                count($processes),
                $blockedWorkers,
                'Both independent workers must be blocked on the held loan-item row lock before it is released. '
                    .json_encode($activity, JSON_THROW_ON_ERROR),
            );

            DB::commit();
            $transactionOpen = false;

            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $output = $process->getOutput();

                if (! preg_match('/^RESULT:(\{.*\})$/m', $output, $matches)) {
                    $this->fail('Return worker did not return a structured result: '.$output.$process->getErrorOutput());
                }

                $results[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            }

            $this->assertSame(1, count(array_filter(
                $results,
                static fn (array $result): bool => $result['result'] === 'returned'
            )));
            $rejectedResults = array_values(array_filter(
                $results,
                static fn (array $result): bool => $result['result'] === 'rejected'
            ));
            $this->assertCount(1, $rejectedResults);
            $this->assertSame(
                'Loan item has already been returned or is not active.',
                $rejectedResults[0]['message']
            );

            $this->assertDatabaseHas('loan_items', [
                'id' => $loanItemId,
                'status' => 'RETURNED',
                'returned_at' => $returnedAt->toDateTimeString(),
            ]);
            $this->assertDatabaseHas('loans', ['id' => $loanId, 'status' => 'COMPLETED']);
            $this->assertDatabaseHas('book_copies', ['id' => $bookCopyId, 'status' => 'AVAILABLE']);
            $this->assertSame(
                $isLate ? 1 : 0,
                Fine::query()->where('loan_item_id', $loanItemId)->count()
            );

            if ($isLate) {
                $this->assertDatabaseHas('fines', [
                    'loan_item_id' => $loanItemId,
                    'late_unit' => 'hour',
                    'late_units' => 1,
                    'rate_per_unit' => 500,
                    'amount' => $expectedFineAmount,
                ]);
            } else {
                $this->assertDatabaseMissing('fines', ['loan_item_id' => $loanItemId]);
            }
        } finally {
            if ($transactionOpen) {
                DB::rollBack();
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }

            $this->deleteFixture($fixture);
        }
    }

    /**
     * @return array{memberType: MemberType, member: Member, location: Location, collectionType: CollectionType, book: Book, bookCopy: BookCopy, loan: Loan, loanItem: LoanItem}
     */
    private function createFixture(): array
    {
        $suffix = strtoupper(bin2hex(random_bytes(6)));
        $memberType = MemberType::create(['code' => 'RC'.$suffix, 'name' => 'Return Concurrency']);
        $member = Member::create([
            'member_number' => 'RC-'.$suffix,
            'name' => 'Synthetic Return Concurrency Member',
            'member_type_id' => $memberType->id,
            'joined_at' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);
        $location = Location::create(['code' => 'RL'.$suffix, 'name' => 'Return Concurrency Location']);
        $collectionType = CollectionType::create(['code' => 'RT'.$suffix, 'name' => 'Return Concurrency Collection']);
        $book = Book::create([
            'title' => 'Synthetic Return Concurrency Book '.$suffix,
            'collection_type_id' => $collectionType->id,
        ]);
        $bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'RC-'.$suffix,
            'inventory_number' => 'RC-'.$suffix,
            'status' => 'BORROWED',
            'location_id' => $location->id,
        ]);
        $loan = Loan::create([
            'loan_number' => 'RC-'.$suffix,
            'member_id' => $member->id,
            'borrowed_at' => '2026-10-14 08:00:00',
            'status' => 'ACTIVE',
        ]);
        $loanItem = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $bookCopy->id,
            'borrowed_at' => '2026-10-14 08:00:00',
            'due_at' => '2026-10-14 09:00:00',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'status' => 'ACTIVE',
        ]);

        return compact('memberType', 'member', 'location', 'collectionType', 'book', 'bookCopy', 'loan', 'loanItem');
    }

    /**
     * @param  array{memberType: MemberType, member: Member, location: Location, collectionType: CollectionType, book: Book, bookCopy: BookCopy, loan: Loan, loanItem: LoanItem}  $fixture
     */
    private function deleteFixture(array $fixture): void
    {
        DB::transaction(function () use ($fixture): void {
            Fine::query()->where('loan_item_id', $fixture['loanItem']->id)->delete();
            LoanItem::query()->whereKey($fixture['loanItem']->id)->delete();
            Loan::query()->whereKey($fixture['loan']->id)->delete();
            BookCopy::query()->whereKey($fixture['bookCopy']->id)->delete();
            Book::query()->whereKey($fixture['book']->id)->delete();
            CollectionType::query()->whereKey($fixture['collectionType']->id)->delete();
            Location::query()->whereKey($fixture['location']->id)->delete();
            Member::query()->whereKey($fixture['member']->id)->delete();
            MemberType::query()->whereKey($fixture['memberType']->id)->delete();
        });
    }

    /**
     * @return array<string, string>
     */
    private function workerEnvironment(int $loanItemId, Carbon $returnedAt): array
    {
        $connection = config('database.connections.pgsql');

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'testing',
            'DB_HOST' => (string) $connection['host'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_PORT' => (string) $connection['port'],
            'DB_URL' => '',
            'DB_USERNAME' => (string) $connection['username'],
            'RETURN_TEST_LOAN_ITEM_ID' => (string) $loanItemId,
            'RETURN_TEST_RETURNED_AT' => $returnedAt->toISOString(),
            'SESSION_DRIVER' => 'array',
        ];
    }

    /**
     * @return array{pid: int, database: string}
     */
    private function waitForWorkerReady(Process $process): array
    {
        $deadline = microtime(true) + 20;
        $output = '';

        do {
            $output .= $process->getIncrementalOutput();
            if (preg_match('/READY:(\d+):([a-zA-Z0-9_-]+)/', $output, $matches)) {
                return ['pid' => (int) $matches[1], 'database' => $matches[2]];
            }

            if (! $process->isRunning()) {
                $this->fail(
                    'Return worker exited before readiness: '.$process->getErrorOutput().$output
                );
            }

            usleep(100_000);
        } while (microtime(true) < $deadline);

        $this->fail('Timed out waiting for a concurrent return worker to start.');
    }
}
