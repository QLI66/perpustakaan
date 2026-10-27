<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LoanLateness
{
    public static function lateHours(?CarbonInterface $dueAt, ?CarbonInterface $asOf): int
    {
        if (! $dueAt || ! $asOf) {
            return 0;
        }

        $lateSeconds = CarbonImmutable::instance($asOf)->getTimestamp()
            - CarbonImmutable::instance($dueAt)->getTimestamp();

        return $lateSeconds > 0 ? intdiv($lateSeconds, 3600) : 0;
    }

    public static function isOverdue(
        ?CarbonInterface $dueAt,
        ?CarbonInterface $asOf,
        ?string $durationUnit = 'day'
    ): bool {
        if (! $dueAt || ! $asOf) {
            return false;
        }

        return CarbonImmutable::instance($asOf)->greaterThan(CarbonImmutable::instance($dueAt));
    }

    public static function lateDays(?CarbonInterface $dueAt, ?CarbonInterface $asOf): int
    {
        if (! $dueAt || ! $asOf) {
            return 0;
        }

        $due = CarbonImmutable::instance($dueAt);
        $asOf = CarbonImmutable::instance($asOf);

        if (! $asOf->greaterThan($due)) {
            return 0;
        }

        $dueCalendar = $due->setTimezone('Asia/Jakarta')->startOfDay();
        $asOfCalendar = $asOf->setTimezone('Asia/Jakarta')->startOfDay();

        return 1 + max(0, (int) $dueCalendar->diffInDays($asOfCalendar));
    }

    public static function calendarDate(CarbonInterface $date): string
    {
        return CarbonImmutable::instance($date)
            ->setTimezone('Asia/Jakarta')
            ->toDateString();
    }
}
