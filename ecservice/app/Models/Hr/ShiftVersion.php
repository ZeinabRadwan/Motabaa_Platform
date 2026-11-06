<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\Hr\Concerns\HasShiftTiming;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shift timing in force from effective_from through effective_to. Schedules for past dates keep using the
 * version that was in force then, so changing a shift never rewrites attendance history.
 */
class ShiftVersion extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated, HasShiftTiming;

    protected $table = 'hr_shift_versions';

    protected $fillable = [
        'center_id',
        'shift_id',
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
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
