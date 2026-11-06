<?php

namespace App\Support\Hr;

use App\Models\Hr\Shift;
use App\Models\Hr\ShiftVersion;

/**
 * Immutable view of a shift with the timing of one version (or the cached columns), used by the schedule
 * resolver and the calculator. Rotating definitions have no hours; their days come from rotationPattern.
 */
final class ShiftDefinition
{
    /**
     * @param  array<int, int>  $workingDays
     * @param  array<int, int>  $restDays
     * @param  array<int, int|null>  $rotationPattern
     */
    public function __construct(
        public readonly ?int $shiftId,
        public readonly ?string $code,
        public readonly ?string $name,
        public readonly string $type,
        public readonly ?string $color,
        public readonly ?string $startTime,
        public readonly ?string $endTime,
        public readonly ?string $breakStartTime,
        public readonly int $breakMinutes,
        public readonly ?int $configuredExpectedMinutes,
        public readonly ?int $graceMinutes,
        public readonly ?int $earlyLeaveGraceMinutes,
        public readonly array $workingDays,
        public readonly array $restDays,
        public readonly array $rotationPattern = [],
        public readonly ?int $versionId = null,
    ) {
    }

    public static function fromShift(Shift $shift, ShiftVersion|Shift|null $timing = null): self
    {
        $timing ??= $shift;
        $values = $timing->timingAttributes();
        $rotating = $shift->isRotating();

        return new self(
            $shift->id,
            $shift->code,
            $shift->name,
            (string) $shift->shift_type,
            $shift->color,
            $rotating ? null : $values['start_time'],
            $rotating ? null : $values['end_time'],
            $rotating ? null : $values['break_start_time'],
            $rotating ? 0 : $values['break_minutes'],
            $rotating ? null : $values['expected_minutes'],
            $values['grace_minutes'],
            $values['early_leave_grace_minutes'],
            Shift::decodeDays($values['working_days']),
            Shift::decodeDays($values['rest_days']),
            $rotating ? ($values['rotation_pattern'] ?? []) : [],
            $timing instanceof ShiftVersion ? $timing->id : null,
        );
    }

    /** Hours worked for one day only (temporary custom hours); grace periods come from the regular schedule. */
    public static function custom(string $start, string $end, int $breakMinutes, ?self $base = null): self
    {
        return new self(
            null, null, null, Shift::TYPE_CUSTOM, $base?->color, $start, $end, null, $breakMinutes, null,
            $base?->graceMinutes, $base?->earlyLeaveGraceMinutes, [], [],
        );
    }

    public function isRotating(): bool
    {
        return $this->type === Shift::TYPE_ROTATING;
    }

    public function cycleLength(): int
    {
        return count($this->rotationPattern);
    }

    public function isOvernight(): bool
    {
        return $this->startTime !== null && HrShiftMath::isOvernight($this->startTime, $this->endTime);
    }

    public function spanMinutes(): int
    {
        return $this->startTime === null ? 0 : HrShiftMath::spanMinutes($this->startTime, $this->endTime);
    }

    public function expectedMinutes(): int
    {
        return $this->startTime === null
            ? 0
            : HrShiftMath::expectedMinutes($this->startTime, $this->endTime, $this->breakMinutes, $this->configuredExpectedMinutes);
    }
}
