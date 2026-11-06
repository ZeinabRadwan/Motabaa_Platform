<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;
use App\Models\Hr\Employee;
use Carbon\CarbonImmutable;

/**
 * Attendance rules that apply to one employee on one date: the employee's attendance configuration in force
 * that day combined with the attendance policy version in force that day (or the center defaults when the
 * employee has no policy). Attendance processing stores configId / policyVersionId with each record, so the
 * rules a record was evaluated with stay known after later changes.
 */
final class AttendanceRuleSet
{
    public const SOURCE_POLICY = 'policy';
    public const SOURCE_CENTER_DEFAULTS = 'center_defaults';

    public function __construct(
        public readonly string $date,
        public readonly string $attendanceMode,
        /** The employee's own GPS setting (Employee::GPS_POLICIES). */
        public readonly string $gpsPolicy,
        /** The GPS verification that applies: required | flexible | none. Always none unless electronic. */
        public readonly string $gpsRequirement,
        public readonly ?int $configId,
        public readonly ?int $policyId,
        public readonly ?int $policyVersionId,
        public readonly string $source,
        /** Employee override of the late-arrival grace period. */
        public readonly ?int $employeeGraceMinutes,
        public readonly int $lateGraceMinutes,
        public readonly int $earlyLeaveGraceMinutes,
        public readonly int $checkInBeforeMinutes,
        public readonly ?int $checkInAfterMinutes,
        public readonly ?int $checkOutBeforeMinutes,
        public readonly int $checkOutAfterMinutes,
        public readonly string $lateCounting,
        public readonly string $lateAction,
        public readonly ?int $absenceAfterMinutes,
        public readonly string $earlyLeaveCounting,
        public readonly string $earlyLeaveAction,
        public readonly string $missingCheckoutAction,
        public readonly string $duplicatePunchAction,
        public readonly int $duplicateWindowMinutes,
        public readonly bool $allowManualAttendance,
    ) {
    }

    /**
     * GPS verification that applies for an attendance method: manual and exempt employees never provide a
     * location; electronic employees use their own setting, or the policy's requirement when set to "policy".
     */
    public static function resolveGps(string $mode, ?string $gpsPolicy, string $policyRequirement): string
    {
        if ($mode !== Employee::ATTENDANCE_ELECTRONIC) {
            return Employee::GPS_NONE;
        }
        if ($gpsPolicy === null || $gpsPolicy === Employee::GPS_FOLLOW_POLICY || ! in_array($gpsPolicy, Employee::GPS_POLICIES, true)) {
            return $policyRequirement;
        }

        return $gpsPolicy;
    }

    public function isElectronic(): bool
    {
        return $this->attendanceMode === Employee::ATTENDANCE_ELECTRONIC;
    }

    public function isManual(): bool
    {
        return $this->attendanceMode === Employee::ATTENDANCE_MANUAL;
    }

    public function requiresGps(): bool
    {
        return $this->gpsRequirement === AttendancePolicy::GPS_REQUIRED;
    }

    /** Late-arrival grace: employee override, then the shift's, then the policy's (or the center default). */
    public function lateGrace(?int $shiftGrace = null): int
    {
        return $this->employeeGraceMinutes ?? $shiftGrace ?? $this->lateGraceMinutes;
    }

    /** Early-departure grace: the shift's, then the policy's (or the center default). */
    public function earlyLeaveGrace(?int $shiftGrace = null): int
    {
        return $shiftGrace ?? $this->earlyLeaveGraceMinutes;
    }

    public function lateCountsAfterGrace(): bool
    {
        return $this->lateCounting === AttendancePolicy::COUNTING_EXCESS_ONLY;
    }

    public function earlyLeaveCountsAfterGrace(): bool
    {
        return $this->earlyLeaveCounting === AttendancePolicy::COUNTING_EXCESS_ONLY;
    }

    /**
     * When a check-in for $day is accepted: from check_in_before minutes before the start until check_in_after
     * minutes after it (until the shift ends when not limited).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null null when $day is not a working day
     */
    public function checkInWindow(ScheduledShiftDay $day): ?array
    {
        if (! $day->isWorking()) {
            return null;
        }
        $until = $this->checkInAfterMinutes === null ? $day->endAt : $day->startAt->addMinutes($this->checkInAfterMinutes);

        return [$day->startAt->subMinutes($this->checkInBeforeMinutes), $until->min($day->endAt)];
    }

    /**
     * When a check-out for $day is accepted: from check_out_before minutes before the end (from the start when
     * not limited) until check_out_after minutes after the end.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function checkOutWindow(ScheduledShiftDay $day): ?array
    {
        if (! $day->isWorking()) {
            return null;
        }
        $from = $this->checkOutBeforeMinutes === null ? $day->startAt : $day->endAt->subMinutes($this->checkOutBeforeMinutes);

        return [$from->max($day->startAt), $day->endAt->addMinutes($this->checkOutAfterMinutes)];
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'attendance_mode' => $this->attendanceMode,
            'gps_policy' => $this->gpsPolicy,
            'gps_requirement' => $this->gpsRequirement,
            'config_id' => $this->configId,
            'attendance_policy_id' => $this->policyId,
            'attendance_policy_version_id' => $this->policyVersionId,
            'source' => $this->source,
            'employee_grace_minutes' => $this->employeeGraceMinutes,
            'late_grace_minutes' => $this->lateGraceMinutes,
            'early_leave_grace_minutes' => $this->earlyLeaveGraceMinutes,
            'check_in_before_minutes' => $this->checkInBeforeMinutes,
            'check_in_after_minutes' => $this->checkInAfterMinutes,
            'check_out_before_minutes' => $this->checkOutBeforeMinutes,
            'check_out_after_minutes' => $this->checkOutAfterMinutes,
            'late_counting' => $this->lateCounting,
            'late_action' => $this->lateAction,
            'absence_after_minutes' => $this->absenceAfterMinutes,
            'early_leave_counting' => $this->earlyLeaveCounting,
            'early_leave_action' => $this->earlyLeaveAction,
            'missing_checkout_action' => $this->missingCheckoutAction,
            'duplicate_punch_action' => $this->duplicatePunchAction,
            'duplicate_window_minutes' => $this->duplicateWindowMinutes,
            'allow_manual_attendance' => $this->allowManualAttendance,
        ];
    }
}
