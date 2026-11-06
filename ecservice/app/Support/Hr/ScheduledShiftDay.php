<?php

namespace App\Support\Hr;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * An employee's resolved schedule for one calendar day: the input attendance processing compares punches
 * against. Overnight shifts belong to the day they start on; endAt then falls on the next calendar day.
 */
final class ScheduledShiftDay
{
    public const STATUS_WORKING = 'working';
    public const STATUS_REST = 'rest';
    public const STATUS_OFF = 'off';
    public const STATUS_UNSCHEDULED = 'unscheduled';
    public const STATUS_NOT_EMPLOYED = 'not_employed';

    public const SOURCE_ASSIGNMENT = 'assignment';
    public const SOURCE_EXCEPTION = 'exception';
    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly string $date,
        public readonly string $status,
        public readonly string $source = self::SOURCE_NONE,
        public readonly ?ShiftDefinition $shift = null,
        public readonly ?int $assignedShiftId = null,
        public readonly ?int $assignmentId = null,
        public readonly ?int $rotationDay = null,
        public readonly ?CarbonImmutable $startAt = null,
        public readonly ?CarbonImmutable $endAt = null,
        public readonly ?CarbonImmutable $breakStartAt = null,
        public readonly ?CarbonImmutable $breakEndAt = null,
        public readonly int $breakMinutes = 0,
        public readonly int $expectedMinutes = 0,
        public readonly int $lateGraceMinutes = 0,
        public readonly int $earlyLeaveGraceMinutes = 0,
        public readonly ?int $exceptionId = null,
        public readonly ?string $exceptionType = null,
        public readonly ?string $exceptionReason = null,
        /** Attendance rules in force on this day (working days resolved by HrShiftSchedule). */
        public readonly ?AttendanceRuleSet $rules = null,
    ) {
    }

    /**
     * A working day of $shift on $date in $timezone, with the break window when the shift fixes one.
     */
    public static function working(
        string $date,
        ShiftDefinition $shift,
        string $timezone,
        int $lateGrace,
        int $earlyGrace,
        string $source = self::SOURCE_ASSIGNMENT,
        array $context = [],
        ?AttendanceRuleSet $rules = null,
    ): self {
        $startAt = CarbonImmutable::parse("{$date} {$shift->startTime}", $timezone);
        $endAt = $startAt->addMinutes($shift->spanMinutes());
        $breakStartAt = $shift->breakStartTime !== null
            ? $startAt->addMinutes(HrShiftMath::offsetFromStart($shift->startTime, $shift->breakStartTime))
            : null;

        return new self(
            $date,
            self::STATUS_WORKING,
            $source,
            $shift,
            $context['assigned_shift_id'] ?? $shift->shiftId,
            $context['assignment_id'] ?? null,
            $context['rotation_day'] ?? null,
            $startAt,
            $endAt,
            $breakStartAt,
            $breakStartAt?->addMinutes($shift->breakMinutes),
            $shift->breakMinutes,
            $shift->expectedMinutes(),
            $lateGrace,
            $earlyGrace,
            $context['exception_id'] ?? null,
            $context['exception_type'] ?? null,
            $context['exception_reason'] ?? null,
            $rules,
        );
    }

    public function isWorking(): bool
    {
        return $this->status === self::STATUS_WORKING;
    }

    public function isOvernight(): bool
    {
        return $this->startAt !== null && $this->endAt->toDateString() !== $this->startAt->toDateString();
    }

    /** Whether $at falls within the shift widened by $windowMinutes on both sides. */
    public function windowContains(CarbonInterface $at, int $windowMinutes): bool
    {
        if (! $this->isWorking()) {
            return false;
        }

        return $at->getTimestamp() >= $this->startAt->subMinutes($windowMinutes)->getTimestamp()
            && $at->getTimestamp() <= $this->endAt->addMinutes($windowMinutes)->getTimestamp();
    }

    /**
     * Whether a punch at $at can belong to this day: inside the shift widened by $windowMinutes, or by the
     * attendance rules' check-in / check-out windows when those reach further.
     */
    public function punchWindowContains(CarbonInterface $at, int $windowMinutes): bool
    {
        if ($this->windowContains($at, $windowMinutes)) {
            return true;
        }
        if ($this->rules === null || ! $this->isWorking()) {
            return false;
        }

        $from = $this->rules->checkInWindow($this)[0];
        $to = $this->rules->checkOutWindow($this)[1];

        return $at->getTimestamp() >= $from->getTimestamp() && $at->getTimestamp() <= $to->getTimestamp();
    }

    /** Minutes from $at to the nearer edge of the shift (0 inside it). */
    public function distanceMinutes(CarbonInterface $at): int
    {
        if ($at->getTimestamp() < $this->startAt->getTimestamp()) {
            return HrShiftMath::minutesBetween($at, $this->startAt);
        }
        if ($at->getTimestamp() > $this->endAt->getTimestamp()) {
            return HrShiftMath::minutesBetween($this->endAt, $at);
        }

        return 0;
    }

    public function toArray(): array
    {
        $shift = $this->shift;

        return [
            'date' => $this->date,
            'status' => $this->status,
            'source' => $this->source,
            'shift_id' => $shift?->shiftId,
            'shift_code' => $shift?->code,
            'shift_name' => $shift?->name,
            'shift_type' => $shift?->type,
            'color' => $shift?->color,
            'assigned_shift_id' => $this->assignedShiftId,
            'rotation_day' => $this->rotationDay === null ? null : $this->rotationDay + 1,
            'start_time' => $this->startAt?->format('H:i'),
            'end_time' => $this->endAt?->format('H:i'),
            'start_at' => $this->startAt?->toIso8601String(),
            'end_at' => $this->endAt?->toIso8601String(),
            'is_overnight' => $this->isOvernight(),
            'break_start' => $this->breakStartAt?->format('H:i'),
            'break_end' => $this->breakEndAt?->format('H:i'),
            'break_minutes' => $this->breakMinutes,
            'expected_minutes' => $this->expectedMinutes,
            'late_grace_minutes' => $this->lateGraceMinutes,
            'early_leave_grace_minutes' => $this->earlyLeaveGraceMinutes,
            'exception_id' => $this->exceptionId,
            'exception_type' => $this->exceptionType,
            'exception_reason' => $this->exceptionReason,
            'attendance_mode' => $this->rules?->attendanceMode,
            'gps_requirement' => $this->rules?->gpsRequirement,
            'attendance_policy_id' => $this->rules?->policyId,
            'attendance_policy_version_id' => $this->rules?->policyVersionId,
        ];
    }
}
