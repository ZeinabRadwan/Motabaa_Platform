<?php

namespace App\Support\Hr;

use Carbon\CarbonInterface;

/**
 * Compares a check-in / check-out pair with a scheduled day. Pure (no database), so attendance processing
 * can call it per day and tests can cover it directly.
 *
 *  - Late arrival: check-in after start + grace. With $countAfterGrace the grace minutes are deducted
 *    from the lateness, otherwise the whole delay counts once the grace period is exceeded. When not given,
 *    the counting rule comes from the day's attendance rules (policy late_counting / early_leave_counting).
 *  - Early departure: check-out before end - early-leave grace, with $earlyCountAfterGrace (default: the
 *    late-arrival rule when $countAfterGrace is given, else the day's early_leave_counting).
 *  - Worked time: presence inside the shift minus break time. A fixed break window deducts the part of the
 *    window the employee was present for; a floating break deducts the break length (at most the presence).
 *  - Time outside the shift (before start / after end) is reported separately, never as worked time.
 */
final class HrShiftCalculator
{
    /**
     * @return array{
     *   scheduled: bool, late_minutes: int, early_departure_minutes: int, worked_minutes: int,
     *   break_minutes: int, presence_minutes: int, outside_shift_minutes: int, expected_minutes: int,
     *   shortfall_minutes: int, is_late: bool, is_early_departure: bool, missing_check_in: bool, missing_check_out: bool
     * }
     */
    public static function evaluate(
        ScheduledShiftDay $day,
        ?CarbonInterface $checkIn,
        ?CarbonInterface $checkOut,
        ?bool $countAfterGrace = null,
        ?bool $earlyCountAfterGrace = null,
    ): array {
        $earlyCountAfterGrace ??= $countAfterGrace ?? ($day->rules?->earlyLeaveCountsAfterGrace() ?? false);
        $countAfterGrace ??= $day->rules?->lateCountsAfterGrace() ?? false;

        $result = [
            'scheduled' => $day->isWorking(),
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'worked_minutes' => 0,
            'break_minutes' => 0,
            'presence_minutes' => 0,
            'outside_shift_minutes' => 0,
            'expected_minutes' => $day->isWorking() ? $day->expectedMinutes : 0,
            'shortfall_minutes' => 0,
            'is_late' => false,
            'is_early_departure' => false,
            'missing_check_in' => $checkIn === null,
            'missing_check_out' => $checkOut === null,
        ];

        if (! $day->isWorking()) {
            if ($checkIn !== null && $checkOut !== null && $checkOut->greaterThan($checkIn)) {
                $result['presence_minutes'] = HrShiftMath::minutesBetween($checkIn, $checkOut);
                $result['outside_shift_minutes'] = $result['presence_minutes'];
            }

            return $result;
        }

        if ($checkIn !== null) {
            $result['late_minutes'] = self::beyondGrace(HrShiftMath::minutesBetween($day->startAt, $checkIn), $day->lateGraceMinutes, $countAfterGrace);
        }
        if ($checkOut !== null) {
            $result['early_departure_minutes'] = self::beyondGrace(HrShiftMath::minutesBetween($checkOut, $day->endAt), $day->earlyLeaveGraceMinutes, $earlyCountAfterGrace);
        }
        $result['is_late'] = $result['late_minutes'] > 0;
        $result['is_early_departure'] = $result['early_departure_minutes'] > 0;

        if ($checkIn === null || $checkOut === null || ! $checkOut->greaterThan($checkIn)) {
            $result['shortfall_minutes'] = $result['expected_minutes'];

            return $result;
        }

        $presence = HrShiftMath::minutesBetween($checkIn, $checkOut);
        $inShift = HrShiftMath::overlapMinutes($checkIn, $checkOut, $day->startAt, $day->endAt);
        $break = $day->breakStartAt !== null
            ? HrShiftMath::overlapMinutes($checkIn, $checkOut, $day->breakStartAt, $day->breakEndAt)
            : min($day->breakMinutes, $inShift);

        $result['presence_minutes'] = $presence;
        $result['break_minutes'] = $break;
        $result['worked_minutes'] = max(0, $inShift - $break);
        $result['outside_shift_minutes'] = $presence - $inShift;
        $result['shortfall_minutes'] = max(0, $result['expected_minutes'] - $result['worked_minutes']);

        return $result;
    }

    private static function beyondGrace(int $minutes, int $grace, bool $countAfterGrace): int
    {
        if ($minutes <= $grace) {
            return 0;
        }

        return $countAfterGrace ? $minutes - $grace : $minutes;
    }
}
