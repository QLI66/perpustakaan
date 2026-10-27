<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\CollectionType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoanDurationValidationApiTest extends TestCase
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
        ) {
            throw new \RuntimeException('Loan duration API tests require APP_ENV=testing and PostgreSQL database testing.');
        }

        $databaseName = DB::selectOne('SELECT current_database() AS database_name')->database_name;
        if ($databaseName !== 'testing') {
            throw new \RuntimeException('Loan duration API tests require the active PostgreSQL database to be testing.');
        }

        DB::connection()->beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-10-14 10:00:00', 'UTC'));

        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $this->memberUser = User::factory()->create();
        $memberType = MemberType::create([
            'code' => 'LD'.$suffix,
            'name' => 'Loan Duration Test',
        ]);
        $this->member = Member::create([
            'user_id' => $this->memberUser->id,
            'member_number' => 'LD-'.$suffix,
            'name' => 'Loan Duration Test Member',
            'member_type_id' => $memberType->id,
            'joined_at' => '2025-01-01',
            'status' => 'ACTIVE',
        ]);

        $collectionType = CollectionType::create([
            'code' => 'LD'.$suffix,
            'name' => 'Loan Duration Test Collection',
        ]);
        $book = Book::create([
            'title' => 'Loan Duration Test Book',
            'collection_type_id' => $collectionType->id,
        ]);
        $location = Location::create([
            'code' => 'LD'.$suffix,
            'name' => 'Loan Duration Test Location',
        ]);
        $this->bookCopy = BookCopy::create([
            'book_id' => $book->id,
            'barcode' => 'LD-'.$suffix,
            'inventory_number' => 'LD-'.$suffix,
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

    public static function validDurations(): array
    {
        return [
            'one day' => [1],
            'two days' => [2],
            'three days' => [3],
        ];
    }

    public static function invalidDurations(): array
    {
        return [
            'zero' => [0],
            'four days' => [4],
            'negative' => [-1],
            'non-numeric' => ['not-a-number'],
        ];
    }

    #[DataProvider('validDurations')]
    public function test_api_accepts_supported_loan_durations_and_sets_due_date(int $durationDays): void
    {
        $response = $this->actingAs($this->memberUser, 'sanctum')
            ->postJson('/api/loans', [
                'book_copy_ids' => [$this->bookCopy->id],
                'loan_duration_days' => $durationDays,
            ]);

        $response->assertCreated();

        $expectedDueAt = Carbon::parse('2026-10-14 00:00:00', 'UTC')
            ->addDays($durationDays);
        $actualDueAt = Carbon::parse($response->json('data.items.0.due_at'));

        $this->assertSame(
            $expectedDueAt->toDateTimeString(),
            $actualDueAt->utc()->toDateTimeString()
        );
        $this->assertDatabaseHas('loan_items', [
            'loan_id' => $response->json('data.id'),
            'due_at' => $expectedDueAt->toDateTimeString(),
        ]);
    }

    #[DataProvider('invalidDurations')]
    public function test_api_rejects_unsupported_loan_durations(mixed $durationDays): void
    {
        $response = $this->actingAs($this->memberUser, 'sanctum')
            ->postJson('/api/loans', [
                'book_copy_ids' => [$this->bookCopy->id],
                'loan_duration_days' => $durationDays,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['loan_duration_days']);

        $this->assertDatabaseCount('loans', 0);
    }
}
