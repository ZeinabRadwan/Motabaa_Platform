<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Evaluates one employee's attendance for one date. Pure (no database): the processor loads the inputs, tests call
 * it directly. Precedence:
 *  1. outside employment: not_employed;
 *  2. approved leave on a day it covers (calendar-day leave: every day; working-day leave: the days it consumes):
 *     on_leave (attendance recorded anyway is an exception to review);
 *  3. an applicable holiday on a working day (or a weekday without schedule): holiday;
 *  4. rest day / day off / unscheduled day;
 *  5. a working day:
 *     - exempt employees: exempt;
 *     - no attendance: pending until the check-out window closes; then incomplete when check-in attempts were
 *       rejected (failed GPS verification is never an absence by itself), else absent;
 *     - an open session: pending until the check-out window closes, then incomplete (missing check-out);
 *     - a missing check-in / check-out: incomplete, an exception HR resolves;
 *     - arrival later than the policy's absence threshold: absent;
 *     - otherwise present / late / early_departure / late_early_departure, with the late / early minutes from
 *       HrShiftCalculator (shift start + grace, counting rule of the policy).
 */
final class HrAttendanceDayEvaluator
{
    /**
     * @param  ?LeaveRequest  $leave  approved leave covering the date (with its leaveType relation loaded)
     * @param  int[]  $weekendDays
     * @return array<string, mixed> computed attributes of the attendance day
     */
    public static function evaluate(
        ScheduledShiftDay $day,
        ?AttendanceRecord $record,
        ?LeaveRequest $leave,
        ?Holiday $holiday,
        array $weekendDays,
        bool $hasRejectedPunches,
        CarbonInterface $now,
    ): array {
        $result = self::base($day, $record);

        if ($day->status === ScheduledShiftDay::STATUS_NOT_EMPLOYED) {
            return self::finish($result, AttendanceDay::STATUS_NOT_EMPLOYED);
        }

        $weekday = ! in_array(Carbon::parse($day->date)->dayOfWeek, $weekendDays, true);
        $countsAsWorkday = $day->isWorking() || ($day->status === ScheduledShiftDay::STATUS_UNSCHEDULED && $weekday);
        $attended = $record !== null && ($record->check_in_at !== null || $record->check_out_at !== null);

        $leaveType = $leave?->leaveType;
        if ($leave !== null && $leaveType !== null && ($leaveType->countsCalendarDays() || ($countsAsWorkday && $holiday === null))) {
            $result['leave_request_id'] = $leave->id;
            $result['leave_type_id'] = $leaveType->id;
            $result['leave_is_paid'] = (bool) $leaveType->is_paid;
            if ($attended) {
                $result['exception_reasons'][] = AttendanceDay::EXCEPTION_ATTENDED_DURING_LEAVE;
            }

            return self::finish($result, AttendanceDay::STATUS_ON_LEAVE);
        }

        if ($holiday !== null && $countsAsWorkday) {
            $result['holiday_id'] = $holiday->id;
            if ($attended) {
                $result['review_flags'][] = 'attended_on_holiday';
            }

            return self::finish($result, AttendanceDay::STATUS_HOLIDAY);
        }

        if (! $day->isWorking()) {
            if ($attended) {
                $result['review_flags'][] = 'attended_off_schedule';
            }

            return self::finish($result, match ($day->status) {
                ScheduledShiftDay::STATUS_REST => AttendanceDay::STATUS_REST_DAY,
                ScheduledShiftDay::STATUS_OFF => AttendanceDay::STATUS_DAY_OFF,
                default => AttendanceDay::STATUS_UNSCHEDULED,
            });
        }

        if ($day->rules?->attendanceMode === Employee::ATTENDANCE_EXEMPT) {
            return self::finish($result, AttendanceDay::STATUS_EXEMPT);
        }

        $closed = $now->getTimestamp() >= self::closesAt($day)->getTimestamp();

        if (! $attended) {
            if (! $closed) {
                return self::finish($result, AttendanceDay::STATUS_PENDING);
            }
            if ($hasRejectedPunches) {
                $result['exception_reasons'][] = AttendanceDay::EXCEPTION_GPS_VERIFICATION_FAILED;

                return self::finish($result, AttendanceDay::STATUS_INCOMPLETE);
            }

            return self::finish($result, AttendanceDay::STATUS_ABSENT, AttendanceDay::REASON_NO_ATTENDANCE);
        }

        $result['review_flags'] = array_merge($result['review_flags'], array_values((array) ($record->review_reasons ?? [])));
        self::measure($result, $day, $record);

        if ($record->isOpen()) {
            if (! $closed) {
                return self::finish($result, AttendanceDay::STATUS_PENDING);
            }
            $result['exception_reasons'][] = AttendanceDay::EXCEPTION_MISSING_CHECK_OUT;

            return self::incomplete($result, $day);
        }
        if ($record->check_in_at === null) {
            $result['exception_reasons'][] = AttendanceDay::EXCEPTION_MISSING_CHECK_IN;

            return self::incomplete($result, $day);
        }
        if ($record->check_out_at === null) {
            $result['exception_reasons'][] = AttendanceDay::EXCEPTION_MISSING_CHECK_OUT;

            return self::incomplete($result, $day);
        }

        $threshold = $day->rules?->absenceAfterMinutes;
        if ($threshold !== null && HrShiftMath::minutesBetween($day->startAt, $record->check_in_at) > $threshold) {
            $result['computed_late_minutes'] = 0;
            $result['computed_early_departure_minutes'] = 0;

            return self::finish($result, AttendanceDay::STATUS_ABSENT, AttendanceDay::REASON_LATE_BEYOND_ABSENCE_THRESHOLD);
        }

        return self::finish($result, self::presentStatus($result['computed_late_minutes'], $result['computed_early_departure_minutes']));
    }

    /**
     * Final values from the computed ones and an HR correction. Late / early minutes count only on days the
     * employee attended (present variants and incomplete days); absence_days is 1 only for absent days.
     *
     * @param  array<string, mixed>  $computed  computed_status, computed_late_minutes, computed_early_departure_minutes, exception_reasons
     * @param  array<string, mixed>  $override  override_status, override_late_minutes, override_early_departure_minutes, overridden_at
     * @return array{status: string, late_minutes: int, early_departure_minutes: int, absence_days: float, review_status: ?string}
     */
    public static function outcome(array $computed, array $override = []): array
    {
        $late = $override['override_late_minutes'] ?? $computed['computed_late_minutes'] ?? 0;
        $early = $override['override_early_departure_minutes'] ?? $computed['computed_early_departure_minutes'] ?? 0;
        $status = $override['override_status'] ?? null;

        if ($status === AttendanceDay::STATUS_PRESENT) {
            $status = self::presentStatus((int) $late, (int) $early);
        }
        $status ??= (string) $computed['computed_status'];

        $counts = in_array($status, AttendanceDay::PRESENT_STATUSES, true) || $status === AttendanceDay::STATUS_INCOMPLETE;
        $hasExceptions = ! empty($computed['exception_reasons']);

        return [
            'status' => $status,
            'late_minutes' => $counts ? (int) $late : 0,
            'early_departure_minutes' => $counts ? (int) $early : 0,
            'absence_days' => $status === AttendanceDay::STATUS_ABSENT ? 1.0 : 0.0,
            'review_status' => ! $hasExceptions ? null : (($override['overridden_at'] ?? null) !== null ? AttendanceDay::REVIEW_RESOLVED : AttendanceDay::REVIEW_PENDING),
        ];
    }

    public static function presentStatus(int $late, int $early): string
    {
        return match (true) {
            $late > 0 && $early > 0 => AttendanceDay::STATUS_LATE_EARLY_DEPARTURE,
            $late > 0 => AttendanceDay::STATUS_LATE,
            $early > 0 => AttendanceDay::STATUS_EARLY_DEPARTURE,
            default => AttendanceDay::STATUS_PRESENT,
        };
    }

    /** When attendance for a working day can no longer be recorded: the end of the check-out window. */
    public static function closesAt(ScheduledShiftDay $day): CarbonInterface
    {
        $window = $day->rules?->checkOutWindow($day);

        return $window !== null ? $window[1]->max($day->endAt) : $day->endAt;
    }

    private static function base(ScheduledShiftDay $day, ?AttendanceRecord $record): array
    {
        $working = $day->isWorking();

        return [
            'day_type' => $day->status,
            'computed_status' => null,
            'status_reason' => null,
            'shift_id' => $working ? $day->shift?->shiftId : null,
            'scheduled_start_at' => $working ? $day->startAt : null,
            'scheduled_end_at' => $working ? $day->endAt : null,
            'expected_minutes' => $working ? $day->expectedMinutes : 0,
            'late_grace_minutes' => $working ? $day->lateGraceMinutes : 0,
            'attendance_record_id' => $record?->id,
            'attendance_method' => $record?->attendance_method,
            'check_in_at' => $record?->check_in_at,
            'check_out_at' => $record?->check_out_at,
            'worked_minutes' => $record?->worked_minutes,
            'computed_late_minutes' => 0,
            'computed_early_departure_minutes' => 0,
            'leave_request_id' => null,
            'leave_type_id' => null,
            'leave_is_paid' => null,
            'holiday_id' => null,
            'exception_reasons' => [],
            'review_flags' => [],
        ];
    }

    /** Late / early / worked minutes of a working day's record (stored values when HR chose another shift). */
    private static function measure(array &$result, ScheduledShiftDay $day, AttendanceRecord $record): void
    {
        if ($record->shift_overridden) {
            $result['computed_late_minutes'] = (int) $record->late_minutes;
            $result['computed_early_departure_minutes'] = (int) $record->early_departure_minutes;
            $result['worked_minutes'] = $record->worked_minutes;

            return;
        }

        $evaluation = HrShiftCalculator::evaluate($day, $record->check_in_at, $record->isOpen() ? null : $record->check_out_at);
        $result['computed_late_minutes'] = $record->check_in_at !== null ? $evaluation['late_minutes'] : 0;
        $result['computed_early_departure_minutes'] = $record->check_out_at !== null && ! $record->isOpen() ? $evaluation['early_departure_minutes'] : 0;
        $result['worked_minutes'] = $record->check_in_at !== null && $record->check_out_at !== null && ! $record->isOpen()
            ? $evaluation['worked_minutes']
            : null;
    }

    private static function incomplete(array $result, ScheduledShiftDay $day): array
    {
        if ($day->rules?->missingCheckoutAction === AttendancePolicy::MISSING_CHECKOUT_ABSENT
            && in_array(AttendanceDay::EXCEPTION_MISSING_CHECK_OUT, $result['exception_reasons'], true)) {
            $result['review_flags'][] = AttendanceDay::FLAG_POLICY_MARKS_ABSENT;
        }

        return self::finish($result, AttendanceDay::STATUS_INCOMPLETE);
    }

    private static function finish(array $result, string $status, ?string $reason = null): array
    {
        $result['computed_status'] = $status;
        $result['status_reason'] = $reason;
        $result['exception_reasons'] = array_values(array_unique($result['exception_reasons']));
        $result['review_flags'] = array_values(array_unique(array_filter($result['review_flags'])));
        if ($result['exception_reasons'] === []) {
            $result['exception_reasons'] = null;
        }
        if ($result['review_flags'] === []) {
            $result['review_flags'] = null;
        }

        return $result;
    }
}
