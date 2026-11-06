<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendanceDay;
use App\Models\Hr\AttendanceDayCorrection;
use App\Models\Hr\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * HR corrections of processed attendance days (edit_hr-attendance-processing): set a working day to present,
 * absent or excused, correct its late / early minutes, or confirm it as reviewed — always with a reason. The
 * computed values stay; the correction survives reprocessing until it is reset. Not allowed on days locked by an
 * approved period, on or before payroll.changes_locked_until, outside the user's branches, or on the user's own
 * attendance without edit_hr-own-attendance-records. Values can be corrected only on working days; other days
 * with an exception (attendance during leave) can only be confirmed as reviewed.
 * Every correction is stored in hr_attendance_day_corrections and the HR audit log.
 */
final class HrAttendanceCorrections
{
    private const TRACKED = [
        'status', 'late_minutes', 'early_departure_minutes', 'absence_days', 'review_status',
        'override_status', 'override_late_minutes', 'override_early_departure_minutes',
    ];

    public function __construct(
        private readonly int $centerId,
        private readonly User $actor,
        private readonly ?array $branchScope,
    ) {
    }

    /**
     * @param  array{status?: ?string, late_minutes?: ?int, early_departure_minutes?: ?int, reason: string}  $input
     *         keys left out keep the computed value
     */
    public function correct(AttendanceDay $day, array $input): AttendanceDay
    {
        return DB::transaction(function () use ($day, $input) {
            $day = $this->lockedDay($day);
            $changesValues = isset($input['status']) || isset($input['late_minutes']) || isset($input['early_departure_minutes']);
            if ($changesValues && ! self::correctable($day)) {
                throw new HrException('attendance_day_review_only', 'status', 422);
            }
            $before = $this->snapshot($day);

            $day->fill([
                'override_status' => $input['status'] ?? null,
                'override_late_minutes' => isset($input['late_minutes']) ? (int) $input['late_minutes'] : null,
                'override_early_departure_minutes' => isset($input['early_departure_minutes']) ? (int) $input['early_departure_minutes'] : null,
                'override_reason' => trim((string) $input['reason']),
                'overridden_by' => $this->actor->id,
                'overridden_at' => now(),
            ]);
            HrAttendanceProcessor::applyOutcome($day);
            $day->save();

            $this->log($day, AttendanceDayCorrection::ACTION_CORRECTED, $before, (string) $input['reason']);

            return $day;
        });
    }

    public function reset(AttendanceDay $day, string $reason): AttendanceDay
    {
        return DB::transaction(function () use ($day, $reason) {
            $day = $this->lockedDay($day);
            if (! $day->isOverridden()) {
                throw new HrException('attendance_day_not_corrected', 'error', 409);
            }
            $before = $this->snapshot($day);

            $day->fill([
                'override_status' => null,
                'override_late_minutes' => null,
                'override_early_departure_minutes' => null,
                'override_reason' => null,
                'overridden_by' => null,
                'overridden_at' => null,
            ]);
            HrAttendanceProcessor::applyOutcome($day);
            $day->save();

            $this->log($day, AttendanceDayCorrection::ACTION_RESET, $before, $reason);

            return $day;
        });
    }

    private function lockedDay(AttendanceDay $day): AttendanceDay
    {
        $day = AttendanceDay::query()->forCenter($this->centerId)->whereKey($day->id)->lockForUpdate()->firstOrFail();
        $employee = Employee::withTrashed()->findOrFail($day->employee_id);

        if ($day->isLocked()) {
            throw new HrException('attendance_day_locked', 'error', 409, ['date' => $day->attendance_date->toDateString()]);
        }
        if (! HrBranchScope::allows($this->branchScope, $day->branch_id ?? $employee->branch_id)) {
            throw new HrException('branch_not_authorized', 'error', 403);
        }
        if ((int) $employee->user_id === (int) $this->actor->id && ! can(HrManualAttendance::OWN_RECORDS_PERMISSION, $this->actor)) {
            throw new HrException('attendance_own_record', 'error', 403);
        }
        $locked = HrEffectiveDating::lockedUntil($this->centerId);
        if ($locked !== null && $day->attendance_date->lte($locked)) {
            throw new HrException('attendance_date_locked', 'error', 422, ['date' => $locked->toDateString()]);
        }
        if (! self::correctable($day) && empty($day->exception_reasons)) {
            throw new HrException('attendance_day_not_correctable', 'error', 422);
        }

        return $day;
    }

    private static function correctable(AttendanceDay $day): bool
    {
        return in_array($day->computed_status, AttendanceDay::CORRECTABLE_STATUSES, true);
    }

    private function snapshot(AttendanceDay $day): array
    {
        $values = [];
        foreach (self::TRACKED as $key) {
            $values[$key] = $day->getAttribute($key);
        }

        return $values;
    }

    private function log(AttendanceDay $day, string $action, array $before, string $reason): void
    {
        $after = $this->snapshot($day);
        $old = [];
        $new = [];
        foreach (self::TRACKED as $key) {
            if ($before[$key] != $after[$key]) {
                $old[$key] = $before[$key];
                $new[$key] = $after[$key];
            }
        }

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        AttendanceDayCorrection::create([
            'center_id' => $day->center_id,
            'attendance_day_id' => $day->id,
            'employee_id' => $day->employee_id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => trim($reason),
            'corrected_by' => $this->actor->id,
            'via_impersonation' => isImpersonating($this->actor),
            'ip_address' => $request?->ip(),
        ]);

        HrAuditLogger::record($day, HrAuditLogger::EVENT_UPDATED, $old, $new + ['reason' => trim($reason), 'action' => $action]);
    }
}
