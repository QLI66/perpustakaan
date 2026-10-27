<?php

namespace Tests\Unit;

use App\Support\LoanLateness;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanLatenessTest extends TestCase
{
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
            throw new \RuntimeException('Loan lateness unit tests must run against the isolated PostgreSQL testing database.');
        }
    }

    public function test_daily_loan_option_b_exact_table_boundaries(): void
    {
        // Reference due date: 15 Oktober 2026 pukul 07:00:00 WIB
        $dueAt = Carbon::parse('2026-10-15 07:00:00', 'Asia/Jakarta');
        $ratePerDay = 1000;

        $cases = [
            '15 Okt 06:59:59 WIB (before due)' => [
                'asOf' => '2026-10-15 06:59:59',
                'expectedLateDays' => 0,
                'expectedFine' => 0,
                'isOverdue' => false,
            ],
            '15 Okt 07:00:00 WIB (exact due time)' => [
                'asOf' => '2026-10-15 07:00:00',
                'expectedLateDays' => 0,
                'expectedFine' => 0,
                'isOverdue' => false,
            ],
            '15 Okt 07:00:01 WIB (1 second past due)' => [
                'asOf' => '2026-10-15 07:00:01',
                'expectedLateDays' => 1,
                'expectedFine' => 1000,
                'isOverdue' => true,
            ],
            '15 Okt 23:59:59 WIB (end of due calendar day)' => [
                'asOf' => '2026-10-15 23:59:59',
                'expectedLateDays' => 1,
                'expectedFine' => 1000,
                'isOverdue' => true,
            ],
            '16 Okt 00:00:00 WIB (first calendar transition at midnight)' => [
                'asOf' => '2026-10-16 00:00:00',
                'expectedLateDays' => 2,
                'expectedFine' => 2000,
                'isOverdue' => true,
            ],
            '16 Okt 00:00:01 WIB (1 second after first midnight transition)' => [
                'asOf' => '2026-10-16 00:00:01',
                'expectedLateDays' => 2,
                'expectedFine' => 2000,
                'isOverdue' => true,
            ],
            '17 Okt 00:00:00 WIB (second calendar transition at midnight)' => [
                'asOf' => '2026-10-17 00:00:00',
                'expectedLateDays' => 3,
                'expectedFine' => 3000,
                'isOverdue' => true,
            ],
            '18 Okt 08:30:00 WIB (multiple consecutive calendar days)' => [
                'asOf' => '2026-10-18 08:30:00',
                'expectedLateDays' => 4,
                'expectedFine' => 4000,
                'isOverdue' => true,
            ],
        ];

        foreach ($cases as $label => $testCase) {
            $asOf = Carbon::parse($testCase['asOf'], 'Asia/Jakarta');
            $lateDays = LoanLateness::lateDays($dueAt, $asOf);
            $fineAmount = $lateDays * $ratePerDay;
            $overdue = LoanLateness::isOverdue($dueAt, $asOf);

            $this->assertSame(
                $testCase['expectedLateDays'],
                $lateDays,
                "Failed lateDays assert for case: {$label}"
            );
            $this->assertSame(
                $testCase['expectedFine'],
                $fineAmount,
                "Failed fine amount assert for case: {$label}"
            );
            $this->assertSame(
                $testCase['isOverdue'],
                $overdue,
                "Failed isOverdue assert for case: {$label}"
            );
        }
    }

    public function test_hourly_loan_fine_calculation_is_unaffected_by_calendar_midnight(): void
    {
        $dueAt = Carbon::parse('2026-10-15 23:30:00', 'Asia/Jakarta');
        $hourlyRate = 500;

        // 59 minutes late: 16 Okt 00:29:00 WIB (crossed midnight WIB, but < 60 mins late)
        $eval59 = Carbon::parse('2026-10-16 00:29:00', 'Asia/Jakarta');
        $lateHours59 = LoanLateness::lateHours($dueAt, $eval59);
        $this->assertSame(0, $lateHours59);
        $this->assertSame(0, $lateHours59 * $hourlyRate);

        // Exactly 60 minutes late: 16 Okt 00:30:00 WIB
        $eval60 = Carbon::parse('2026-10-16 00:30:00', 'Asia/Jakarta');
        $lateHours60 = LoanLateness::lateHours($dueAt, $eval60);
        $this->assertSame(1, $lateHours60);
        $this->assertSame(500, $lateHours60 * $hourlyRate);

        // 119 minutes late: 16 Okt 01:29:00 WIB
        $eval119 = Carbon::parse('2026-10-16 01:29:00', 'Asia/Jakarta');
        $lateHours119 = LoanLateness::lateHours($dueAt, $eval119);
        $this->assertSame(1, $lateHours119);
        $this->assertSame(500, $lateHours119 * $hourlyRate);

        // Exactly 120 minutes late: 16 Okt 01:30:00 WIB
        $eval120 = Carbon::parse('2026-10-16 01:30:00', 'Asia/Jakarta');
        $lateHours120 = LoanLateness::lateHours($dueAt, $eval120);
        $this->assertSame(2, $lateHours120);
        $this->assertSame(1000, $lateHours120 * $hourlyRate);
    }

    public function test_calendar_date_uses_asia_jakarta_timezone(): void
    {
        // 2026-10-15 17:00:00 UTC is 2026-10-16 00:00:00 WIB
        $utcTime = Carbon::parse('2026-10-15 17:00:00', 'UTC');
        $this->assertSame('2026-10-16', LoanLateness::calendarDate($utcTime));

        // 2026-10-15 16:59:59 UTC is 2026-10-15 23:59:59 WIB
        $utcTimeBefore = Carbon::parse('2026-10-15 16:59:59', 'UTC');
        $this->assertSame('2026-10-15', LoanLateness::calendarDate($utcTimeBefore));
    }
}
