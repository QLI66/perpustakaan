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
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FinePaymentConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        DB::table('fine_payments')->delete();
        DB::table('fines')->delete();
        DB::table('loan_items')->delete();
        DB::table('loans')->delete();
        DB::table('book_copies')->delete();
        DB::table('books')->delete();
        DB::table('locations')->delete();
        DB::table('members')->delete();
        DB::table('member_types')->delete();
        DB::table('collection_types')->delete();
        DB::table('users')->where('email', 'like', '%@example.test')->delete();

        parent::tearDown();
    }

    public function test_concurrent_duplicate_requests_create_one_payment(): void
    {
        [$fine, $staffOne] = $this->createFineFixture();
        $payload = [
            'amount' => '500.00',
            'payment_method' => 'CASH',
            'idempotency_key' => '00000000-0000-4000-8000-000000000101',
        ];

        $results = $this->runConcurrentPayments([
            ['fine' => $fine, 'user' => $staffOne, 'payload' => $payload],
            ['fine' => $fine, 'user' => $staffOne, 'payload' => $payload],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['replayed'] === false)));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['replayed'] === true)));
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'amount_paid' => '500.00']);
    }

    public function test_two_staff_concurrently_paying_one_fine_cannot_overpay(): void
    {
        [$fine, $staffOne, $staffTwo] = $this->createFineFixture();

        $results = $this->runConcurrentPayments([
            [
                'fine' => $fine,
                'user' => $staffOne,
                'payload' => [
                    'amount' => '1500.00',
                    'payment_method' => 'CASH',
                    'idempotency_key' => '00000000-0000-4000-8000-000000000102',
                ],
            ],
            [
                'fine' => $fine,
                'user' => $staffTwo,
                'payload' => [
                    'amount' => '1500.00',
                    'payment_method' => 'TRANSFER',
                    'idempotency_key' => '00000000-0000-4000-8000-000000000103',
                ],
            ],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'created')));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'business_rejected')));
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertDatabaseHas('fines', ['id' => $fine->id, 'amount_paid' => '1500.00']);
    }

    public function test_concurrent_reuse_of_one_key_on_different_fines_returns_conflict(): void
    {
        [$fineOne, $staffOne] = $this->createFineFixture(1);
        [$fineTwo, $staffTwo] = $this->createFineFixture(2);
        $idempotencyKey = '00000000-0000-4000-8000-000000000104';

        $results = $this->runConcurrentPayments([
            [
                'fine' => $fineOne,
                'user' => $staffOne,
                'payload' => [
                    'amount' => '500.00',
                    'payment_method' => 'CASH',
                    'idempotency_key' => $idempotencyKey,
                ],
            ],
            [
                'fine' => $fineTwo,
                'user' => $staffTwo,
                'payload' => [
                    'amount' => '500.00',
                    'payment_method' => 'CASH',
                    'idempotency_key' => $idempotencyKey,
                ],
            ],
        ]);

        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'created')));
        $this->assertSame(1, count(array_filter($results, fn (array $result): bool => $result['result'] === 'idempotency_conflict')));
        $this->assertDatabaseCount('fine_payments', 1);
        $this->assertSame(
            '500.00',
            bcadd((string) $fineOne->fresh()->amount_paid, (string) $fineTwo->fresh()->amount_paid, 2),
        );
    }

    /**
     * @param  array<array{fine: Fine, user: User, payload: array{amount: string, payment_method: string, idempotency_key: string}}>  $requests
     * @return list<array{result: string, replayed?: bool}>
     */
    private function runConcurrentPayments(array $requests): array
    {
        $workerCode = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'READY:'.Illuminate\Support\Facades\DB::selectOne('SELECT pg_backend_pid() AS pid')->pid.PHP_EOL;
flush();
Illuminate\Support\Facades\Auth::loginUsingId((int) getenv('PAYMENT_TEST_USER_ID'));
try {
    $result = app(App\Services\FinePaymentService::class)->recordPayment(
        (int) getenv('FINE_PAYMENT_TEST_FINE_ID'),
        json_decode(base64_decode((string) getenv('PAYMENT_TEST_PAYLOAD')), true, 512, JSON_THROW_ON_ERROR),
    );
    echo 'RESULT:'.json_encode(['result' => 'created', 'replayed' => $result['replayed']], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (App\Exceptions\BusinessRuleException $exception) {
    echo 'RESULT:'.json_encode(['result' => 'business_rejected'], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (App\Exceptions\IdempotencyConflictException $exception) {
    echo 'RESULT:'.json_encode(['result' => 'idempotency_conflict'], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
PHP;

        DB::beginTransaction();
        $fineIds = array_values(array_unique(array_map(
            fn (array $request): int => $request['fine']->id,
            $requests,
        )));
        sort($fineIds);
        foreach ($fineIds as $fineId) {
            Fine::query()->lockForUpdate()->findOrFail($fineId);
        }

        $processes = [];
        $transactionOpen = true;

        try {
            foreach ($requests as $request) {
                $process = new Process(
                    [PHP_BINARY, '-r', $workerCode],
                    base_path(),
                    $this->workerEnvironment($request['fine']->id, $request['user']->id, $request['payload']),
                );
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $processIds = [];
            $capturedOutput = [];
            foreach ($processes as $process) {
                $ready = $this->waitForWorkerReady($process);
                $processIds[] = $ready['pid'];
                $capturedOutput[] = $ready['output'];
            }

            $deadline = microtime(true) + 20;
            do {
                $blockedWorkers = (int) DB::table('pg_stat_activity')
                    ->whereIn('pid', $processIds)
                    ->where('wait_event_type', 'Lock')
                    ->count();

                if ($blockedWorkers === count($requests)) {
                    break;
                }

                usleep(100_000);
            } while (microtime(true) < $deadline);

            $activity = DB::table('pg_stat_activity')
                ->whereIn('pid', $processIds)
                ->get(['pid', 'state', 'wait_event_type', 'wait_event', 'datname'])
                ->toArray();
            $processOutput = array_map(
                fn (Process $process, int $index): string => $capturedOutput[$index]
                    .$process->getIncrementalOutput()
                    .$process->getIncrementalErrorOutput()
                    .' exit='.$process->getExitCode(),
                $processes,
                array_keys($processes),
            );
            $this->assertSame(
                count($requests),
                $blockedWorkers,
                'Concurrent worker processes did not block on the held PostgreSQL fine row lock. '
                    .json_encode(['activity' => $activity, 'output' => $processOutput], JSON_THROW_ON_ERROR),
            );

            DB::commit();
            $transactionOpen = false;

            $results = [];
            foreach ($processes as $index => $process) {
                $process->wait();
                $output = $capturedOutput[$index].$process->getIncrementalOutput();
                $errorOutput = $process->getIncrementalErrorOutput();
                $this->assertSame(0, $process->getExitCode(), $errorOutput);

                if (! preg_match('/^RESULT:(\{.*\})$/m', $output, $matches)) {
                    $this->fail('Concurrent payment worker did not return a result: '.$output.$errorOutput);
                }

                $decodedResult = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
                $results[] = $decodedResult;
            }

            return $results;
        } finally {
            if ($transactionOpen) {
                DB::rollBack();
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }

    /**
     * @param  array{amount: string, payment_method: string, idempotency_key: string}  $payload
     * @return array<string, string>
     */
    private function workerEnvironment(int $fineId, int $userId, array $payload): array
    {
        $connection = config('database.connections.pgsql');

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_HOST' => (string) $connection['host'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_PORT' => (string) $connection['port'],
            'DB_URL' => '',
            'DB_USERNAME' => (string) $connection['username'],
            'FINE_PAYMENT_TEST_FINE_ID' => (string) $fineId,
            'PAYMENT_TEST_PAYLOAD' => base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
            'PAYMENT_TEST_USER_ID' => (string) $userId,
            'SESSION_DRIVER' => 'array',
        ];
    }

    private function waitForWorkerReady(Process $process): array
    {
        $deadline = microtime(true) + 20;
        $output = '';
        while (microtime(true) < $deadline) {
            $output .= $process->getIncrementalOutput();
            if (preg_match('/READY:(\d+)/', $output, $matches)) {
                return ['pid' => (int) $matches[1], 'output' => $output];
            }

            if (! $process->isRunning()) {
                $this->fail('Concurrent payment worker exited before it was ready: '.$process->getErrorOutput());
            }

            usleep(100_000);
        }

        $this->fail('Timed out waiting for a concurrent payment worker to start.');
    }

    /**
     * @return array{0: Fine, 1: User, 2?: User}
     */
    private function createFineFixture(int $suffix = 1): array
    {
        $staffOne = User::create([
            'name' => 'Staff One',
            'email' => "staff-one-{$suffix}@example.test",
            'password' => Hash::make('password'),
        ]);
        $staffTwo = User::create([
            'name' => 'Staff Two',
            'email' => "staff-two-{$suffix}@example.test",
            'password' => Hash::make('password'),
        ]);

        $memberType = MemberType::create(['code' => "STD{$suffix}", 'name' => 'Student']);
        $memberUser = User::create([
            'name' => 'Member',
            'email' => "member-{$suffix}@example.test",
            'password' => Hash::make('password'),
        ]);
        $member = Member::create([
            'user_id' => $memberUser->id,
            'member_number' => "MEM-CONCURRENT-{$suffix}",
            'name' => 'Concurrent Member',
            'member_type_id' => $memberType->id,
            'joined_at' => now(),
        ]);

        $collectionType = CollectionType::create(['code' => "FIC{$suffix}", 'name' => 'Fiction']);
        $book = Book::create([
            'title' => "Concurrency Test Book {$suffix}",
            'isbn' => sprintf('9876543210%03d', $suffix),
            'collection_type_id' => $collectionType->id,
            'publication_year' => 2024,
            'language' => 'en',
        ]);
        $location = Location::create(['code' => "CONC{$suffix}", 'name' => 'Concurrency Shelf']);
        $bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => "BC-CONCURRENT-{$suffix}",
            'location_id' => $location->id,
            'status' => 'AVAILABLE',
        ]);
        $loan = Loan::create([
            'loan_number' => "LOAN-CONCURRENT-{$suffix}",
            'member_id' => $member->id,
            'borrowed_at' => now()->subDays(5),
            'status' => 'COMPLETED',
        ]);
        $loanItem = LoanItem::create([
            'loan_id' => $loan->id,
            'book_copy_id' => $bookCopy->id,
            'borrowed_at' => now()->subDays(5),
            'due_at' => now()->subDays(2),
            'returned_at' => now(),
            'status' => 'RETURNED',
        ]);
        $fine = Fine::create([
            'loan_item_id' => $loanItem->id,
            'late_days' => 2,
            'rate_per_day' => 1000,
            'amount' => 2000,
            'amount_paid' => 0,
            'status' => 'UNPAID',
            'assessed_at' => now(),
        ]);

        return [$fine, $staffOne, $staffTwo];
    }
}
