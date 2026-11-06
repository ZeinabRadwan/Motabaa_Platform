<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Employee-specific schedule: working_days / rest_days override the fixed shift's days for this employee;
 * rotation_offset picks the cycle day a rotating shift starts on at effective_from (0 = first day).
 */
class EmployeeShiftAssignment extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated;

    protected $table = 'hr_employee_shift_assignments';

    protected $fillable = [
        'center_id',
        'employee_id',
        'shift_id',
        'rotation_offset',
        'working_days',
        'rest_days',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'rotation_offset' => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return array<int, int>|null null = use the shift's working days */
    public function workingDaysOverride(): ?array
    {
        return $this->working_days === null ? null : Shift::decodeDays($this->working_days);
    }

    /** @return array<int, int>|null */
    public function restDaysOverride(): ?array
    {
        return $this->rest_days === null ? null : Shift::decodeDays($this->rest_days);
    }
}
