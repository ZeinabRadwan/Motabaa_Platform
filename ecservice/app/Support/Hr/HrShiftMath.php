<?php

namespace App\Support\Hr;

use App\Models\Hr\Shift;
use Carbon\CarbonInterface;

/**
 * Pure clock arithmetic for shifts (no database), kept separate so it can be unit tested.
 * Times are "H:i" (or "H:i:s"); an end time at or before the start time ends on the next calendar day.
 */
final class HrShiftMath
{
    public const DAY_MINUTES = 1440;

    public static function toMinutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hours * 60 + $minutes;
    }

    public static function isOvernight(string $start, string $end): bool
    {
        return self::toMinutes($end) <= self::toMinutes($start);
    }

    /** Length of the shift from start to end, crossing midnight when needed (equal times = 24 hours). */
    public static function spanMinutes(string $start, string $end): int
    {
        $span = (self::toMinutes($end) - self::toMinutes($start) + self::DAY_MINUTES) % self::DAY_MINUTES;

        return $span === 0 ? self::DAY_MINUTES : $span;
    }

    /** Minutes from the shift start to the given clock time, moving it past midnight when it is earlier. */
    public static function offsetFromStart(string $start, string $time): int
    {
        return (self::toMinutes($time) - self::toMinutes($start) + self::DAY_MINUTES) % self::DAY_MINUTES;
    }

    /** Configured expected minutes, or the span minus the break. */
    public static function expectedMinutes(string $start, string $end, int $breakMinutes, ?int $configured = null): int
    {
        return $configured ?? max(0, self::spanMinutes($start, $end) - $breakMinutes);
    }

    /**
     * Problems with a fixed shift's hours, as validation.hr reasons keyed by field.
     *
     * @return array<string, string>
     */
    public static function timingErrors(string $start, string $end, int $breakMinutes, ?string $breakStart, ?int $expected): array
    {
        $span = self::spanMinutes($start, $end);
        $errors = [];
        if ($breakMinutes >= $span) {
            $errors['break_minutes'] = 'shift_break_too_long';
        } elseif ($breakStart !== null && ($breakMinutes === 0 || self::offsetFromStart($start, $breakStart) + $breakMinutes > $span)) {
            $errors['break_start_time'] = 'shift_break_outside';
        }
        if ($expected !== null && $expected > $span) {
            $errors['expected_minutes'] = 'shift_expected_too_long';
        }

        return $errors;
    }

    /**
     * Cycle day (0-based) on $date for a rotation that is on day $offset at $anchor. Dates before the anchor
     * wrap backwards, so the cycle is continuous in both directions.
     */
    public static function rotationIndex(CarbonInterface $anchor, CarbonInterface $date, int $length, int $offset = 0): int
    {
        if ($length < 1) {
            return 0;
        }
        $days = (int) $anchor->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return (($days + $offset) % $length + $length) % $length;
    }

    /** Suggested type from the hours, used when none is given. */
    public static function suggestType(string $start, string $end): string
    {
        $hour = intdiv((int) self::toMinutes($start), 60);
        if (self::isOvernight($start, $end) || $hour >= 20 || $hour < 4) {
            return Shift::TYPE_NIGHT;
        }

        return $hour < 12 ? Shift::TYPE_MORNING : Shift::TYPE_EVENING;
    }

    /** Overlap in whole minutes of two [start, end) intervals. */
    public static function overlapMinutes(CarbonInterface $aStart, CarbonInterface $aEnd, CarbonInterface $bStart, CarbonInterface $bEnd): int
    {
        $start = max($aStart->getTimestamp(), $bStart->getTimestamp());
        $end = min($aEnd->getTimestamp(), $bEnd->getTimestamp());

        return $end > $start ? intdiv($end - $start, 60) : 0;
    }

    /** Whole minutes from $from to $to (negative when $to is earlier). */
    public static function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return $seconds >= 0 ? intdiv($seconds, 60) : -intdiv(-$seconds, 60);
    }
}
