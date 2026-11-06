<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Temporary schedule change for a date range: work another shift, work custom hours, or take the day off.
 * Scope is one employee, one branch, or the whole center (both ids null); the most specific scope wins.
 */
class ShiftException extends Model
{
    use Auditable, BelongsToCenter, SoftDeletes;

    public const TYPE_SHIFT = 'shift';
    public const TYPE_CUSTOM_HOURS = 'custom_hours';
    public const TYPE_DAY_OFF = 'day_off';

    public const TYPES = [self::TYPE_SHIFT, self::TYPE_CUSTOM_HOURS, self::TYPE_DAY_OFF];

    public const SCOPE_EMPLOYEE = 'employee';
    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_CENTER = 'center';

    public const MAX_DAYS = 366;

    protected $table = 'hr_shift_exceptions';

    protected $fillable = [
        'center_id',
        'employee_id',
        'branch_id',
        'date_from',
        'date_to',
        'exception_type',
        'shift_id',
        'start_time',
        'end_time',
        'break_minutes',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'break_minutes' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function targetScope(): string
    {
        if ($this->employee_id !== null) {
            return self::SCOPE_EMPLOYEE;
        }

        return $this->branch_id !== null ? self::SCOPE_BRANCH : self::SCOPE_CENTER;
    }

    public function covers(string $day): bool
    {
        return $this->date_from->toDateString() <= $day && $this->date_to->toDateString() >= $day;
    }

    public static function typeLabels(): array
    {
        return Employee::labels(self::TYPES, 'tr.hr.shift_exception_types');
    }
}
