<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use App\Models\Hr\Concerns\HasShiftTiming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Working hours template. end_time at or before start_time means the shift ends the next day; the shift
 * belongs to the calendar day it starts on. Timing columns cache the hr_shift_versions row in force today
 * (current_version_id); history and scheduled changes live in the versions.
 *
 * A rotating shift has no hours of its own: rotation_pattern is a cycle of day slots, each holding the id of
 * a fixed shift or null for a rest day. Its start/end columns hold placeholders (they are NOT NULL).
 */
class Shift extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, HasShiftTiming, SoftDeletes;

    public const TYPE_MORNING = 'morning';
    public const TYPE_EVENING = 'evening';
    public const TYPE_NIGHT = 'night';
    public const TYPE_ROTATING = 'rotating';
    public const TYPE_CUSTOM = 'custom';

    public const TYPES = [self::TYPE_MORNING, self::TYPE_EVENING, self::TYPE_NIGHT, self::TYPE_ROTATING, self::TYPE_CUSTOM];

    public const MAX_CYCLE_DAYS = 56;

    protected $table = 'hr_shifts';

    protected $fillable = [
        'center_id',
        'branch_id',
        'code',
        'shift_type',
        'name_ar',
        'name_en',
        'color',
        'start_time',
        'end_time',
        'break_start_time',
        'break_minutes',
        'expected_minutes',
        'grace_minutes',
        'early_leave_grace_minutes',
        'working_days',
        'rest_days',
        'rotation_pattern',
        'current_version_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_version_id' => 'integer',
    ];

    public function auditExcludedAttributes(): array
    {
        return ['current_version_id'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'shift_id');
    }

    public function versions()
    {
        return $this->hasMany(ShiftVersion::class, 'shift_id');
    }

    /** Assignments in force today for employees still employed (for counts). */
    public function currentAssignments()
    {
        return $this->hasMany(EmployeeShiftAssignment::class, 'shift_id')
            ->effectiveOn(today())
            ->whereHas('employee', fn ($query) => $query->employed());
    }

    /** Next timing change that has not taken effect yet. */
    public function scheduledVersion()
    {
        return $this->hasOne(ShiftVersion::class, 'shift_id')
            ->where('effective_from', '>', today()->toDateString().' 23:59:59')
            ->orderBy('effective_from');
    }

    public function isRotating(): bool
    {
        return $this->shift_type === self::TYPE_ROTATING;
    }

    public function cycleLength(): int
    {
        return $this->isRotating() ? count($this->rotation_pattern ?? []) : 0;
    }

    public function timeRange(): string
    {
        if ($this->isRotating()) {
            return __('tr.hr.shift_rotation_cycle', ['days' => $this->cycleLength()]);
        }

        return substr((string) $this->start_time, 0, 5).' - '.substr((string) $this->end_time, 0, 5);
    }

    public static function typeLabels(): array
    {
        return Employee::labels(self::TYPES, 'tr.hr.shift_types');
    }
}
