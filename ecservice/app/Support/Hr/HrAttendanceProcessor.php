<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Central attendance processing: evaluates each employee's attendance per date (HrAttendanceDayEvaluator) from
 * the shift schedule, the attendance record, approved leave, holidays, rest days and the attendance policy, and
 * stores the result in hr_attendance_days (one row per employee and date, so reprocessing never counts twice).
 *  - Dates after today are never processed; today's working days stay pending until the check-out window closes.
 *  - Days locked by an approved attendance period are skipped.
 *  - HR corrections are kept: the computed values are refreshed, the final values re-derived from the correction.
 * Triggers: attendance record changes (that day), leave decisions (the leave range), holiday changes (days already
 * processed in the range), the daily hr:process-attendance command and HR's process action / period processing.
 */
final class HrAttendanceProcessor
{
    private const CHUNK = 100;

    /** Rejected check-in reasons that mean the location could not be verified. */
    public const GPS_REJECTION_REASONS = [
        'attendance_outside_radius',
        'attendance_low_accuracy',
        'attendance_location_not_configured',
        'attendance_location_denied',
        'attendance_location_unavailable',
    ];

    /** @return array{processed: int, locked: int} */
    public static function forEmployee(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to, ?CarbonInterface $now = null): array
    {
        return self::process($employee->center_id, collect([$employee]), $from, $to, $now);
    }

    /**
     * @param  Collection<int, Employee>  $employees  employees of one center
     * @return array{processed: int, locked: int}
     */
    public static function process(int $centerId, Collection $employees, CarbonInterface|string $from, CarbonInterface|string $to, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $today = $now->setTimezone(self::timezone($centerId))->toDateString();
        $first = Carbon::parse($from)->toDateString();
        $last = min(Carbon::parse($to)->toDateString(), $today);

        $stats = ['processed' => 0, 'locked' => 0];
        if ($employees->isEmpty() || $last < $first) {
            return $stats;
        }

        foreach ($employees->values()->chunk(self::CHUNK) as $chunk) {
            $result = self::processChunk($centerId, EloquentCollection::make($chunk->values()->all()), $first, $last, $now);
            $stats['processed'] += $result['processed'];
            $stats['locked'] += $result['locked'];
        }

        return $stats;
    }

    /** Reprocesses the day of an attendance record; never breaks the attendance recording itself. */
    public static function refreshRecord(AttendanceRecord $record): void
    {
        try {
            $employee = Employee::withTrashed()->find($record->employee_id);
            if ($employee !== null && $record->attendance_date !== null) {
                $date = $record->attendance_date->toDateString();
                self::forEmployee($employee, $date, $date);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Reprocesses the employees whose days in the range were already processed (e.g. after a holiday change). */
    public static function refreshProcessed(int $centerId, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $first = Carbon::parse($from)->toDateString();
        $last = Carbon::parse($to)->toDateString();
        $ids = AttendanceDay::query()
            ->forCenter($centerId)
            ->whereDate('attendance_date', '>=', $first)
            ->whereDate('attendance_date', '<=', $last)
            ->whereNull('locked_at')
            ->distinct()
            ->pluck('employee_id');

        $employees = Employee::withTrashed()->forCenter($centerId)->whereIn('id', $ids)->get();

        return self::process($centerId, $employees, $first, $last);
    }

    /** The first date in the range locked by an approved attendance period, if any. */
    public static function firstLockedDate(int $employeeId, string $from, string $to): ?string
    {
        $date = AttendanceDay::query()
            ->where('employee_id', $employeeId)
            ->whereDate('attendance_date', '>=', $from)
            ->whereDate('attendance_date', '<=', $to)
            ->whereNotNull('locked_at')
            ->orderBy('attendance_date')
            ->value('attendance_date');

        return $date !== null ? Carbon::parse($date)->toDateString() : null;
    }

    /**
     * Recomputes the final values of a day from its computed values and its HR correction. A corrected status or
     * minutes apply only while the computed status is a working-day status (approved leave or a holiday added
     * later wins; the correction comes back if they are withdrawn).
     */
    public static function applyOutcome(AttendanceDay $day): void
    {
        $correctable = in_array($day->computed_status, AttendanceDay::CORRECTABLE_STATUSES, true);

        $day->fill(HrAttendanceDayEvaluator::outcome(
            [
                'computed_status' => $day->computed_status,
                'computed_late_minutes' => $day->computed_late_minutes,
                'computed_early_departure_minutes' => $day->computed_early_departure_minutes,
                'exception_reasons' => $day->exception_reasons,
            ],
            [
                'override_status' => $correctable ? $day->override_status : null,
                'override_late_minutes' => $correctable ? $day->override_late_minutes : null,
                'override_early_departure_minutes' => $correctable ? $day->override_early_departure_minutes : null,
                'overridden_at' => $day->overridden_at,
            ],
        ));
    }

    public static function timezone(int $centerId): string
    {
        return (string) (HrSettings::get($centerId, 'general.timezone') ?: config('app.timezone'));
    }

    /** @return array{processed: int, locked: int} */
    private static function processChunk(int $centerId, EloquentCollection $employees, string $from, string $to, CarbonImmutable $now): array
    {
        $employees->loadMissing(['employmentPeriods']);
        $ids = $employees->pluck('id')->all();

        $schedule = HrShiftSchedule::forEmployees($centerId, $employees, $from, $to);
        $branches = HrHolidays::branchesOn($employees, $from, $to);
        $holidays = HrHolidays::between($centerId, $from, $to);
        $weekend = HrLeaveRequests::weekendDays($centerId);

        $records = [];
        AttendanceRecord::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('attendance_date', '>=', $from)
            ->whereDate('attendance_date', '<=', $to)
            ->get()
            ->each(function (AttendanceRecord $record) use (&$records) {
                $records[$record->employee_id][$record->attendance_date->toDateString()] = $record;
            });

        $leaves = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->with('leaveType')
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');

        $rejected = self::rejectedCheckIns($centerId, $ids, $from, $to);

        $existing = [];
        AttendanceDay::query()
            ->whereIn('employee_id', $ids)
            ->whereDate('attendance_date', '>=', $from)
            ->whereDate('attendance_date', '<=', $to)
            ->get()
            ->each(function (AttendanceDay $day) use (&$existing) {
                $existing[$day->employee_id][$day->attendance_date->toDateString()] = $day;
            });

        $stats = ['processed' => 0, 'locked' => 0];
        foreach ($employees as $employee) {
            foreach ($schedule[$employee->id] ?? [] as $date => $scheduled) {
                $row = $existing[$employee->id][$date] ?? null;
                if ($row?->isLocked()) {
                    $stats['locked']++;

                    continue;
                }

                $leave = ($leaves->get($employee->id) ?? collect())->first(fn (LeaveRequest $request) => $request->covers($date));
                $branchId = $branches[$employee->id][$date] ?? null;
                $computed = HrAttendanceDayEvaluator::evaluate(
                    $scheduled,
                    $records[$employee->id][$date] ?? null,
                    $leave,
                    HrHolidays::on($holidays, $date, $branchId),
                    $weekend,
                    isset($rejected[$employee->id][$date]),
                    $now,
                );

                self::store($row, $centerId, $employee, $date, $branchId, $computed, $now);
                $stats['processed']++;
            }
        }

        return $stats;
    }

    private static function store(?AttendanceDay $row, int $centerId, Employee $employee, string $date, ?int $branchId, array $computed, CarbonImmutable $now): void
    {
        foreach (['scheduled_start_at', 'scheduled_end_at', 'check_in_at', 'check_out_at'] as $field) {
            if ($computed[$field] instanceof CarbonInterface) {
                $computed[$field] = CarbonImmutable::instance($computed[$field])->setTimezone(config('app.timezone'));
            }
        }

        $day = $row ?? new AttendanceDay([
            'center_id' => $centerId,
            'employee_id' => $employee->id,
            'attendance_date' => $date,
        ]);
        $day->fill($computed + ['branch_id' => $branchId]);
        self::applyOutcome($day);

        if (! $day->exists || $day->isDirty()) {
            $day->processed_at = $now->setTimezone(config('app.timezone'));
            try {
                $day->save();
            } catch (QueryException $e) {
                // Processed concurrently: the unique (employee, date) index keeps one row.
                if ($day->exists || ! AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('attendance_date', $date)->exists()) {
                    throw $e;
                }
            }
        }
    }

    /** @return array<int, array<string, true>> employee id => date => true */
    private static function rejectedCheckIns(int $centerId, array $ids, string $from, string $to): array
    {
        $timezone = self::timezone($centerId);
        $rows = AttendancePunch::query()
            ->whereIn('employee_id', $ids)
            ->where('type', AttendancePunch::TYPE_CHECK_IN)
            ->where('result', AttendancePunch::RESULT_REJECTED)
            ->whereIn('reason', self::GPS_REJECTION_REASONS)
            ->where('attempted_at', '>=', Carbon::parse($from, $timezone)->subDay()->setTimezone(config('app.timezone')))
            ->where('attempted_at', '<', Carbon::parse($to, $timezone)->addDays(2)->setTimezone(config('app.timezone')))
            ->get(['employee_id', 'attendance_date', 'attempted_at']);

        $dates = [];
        foreach ($rows as $punch) {
            $date = $punch->attendance_date?->toDateString()
                ?? Carbon::parse($punch->attempted_at)->setTimezone($timezone)->toDateString();
            if ($date >= $from && $date <= $to) {
                $dates[$punch->employee_id][$date] = true;
            }
        }

        return $dates;
    }
}
