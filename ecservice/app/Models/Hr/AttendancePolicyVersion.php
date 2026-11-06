<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\Hr\Concerns\HasAttendanceRules;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Attendance policy rules in force from effective_from through effective_to. Attendance for a past date keeps
 * using the version that was in force then, so changing a policy never rewrites attendance history.
 */
class AttendancePolicyVersion extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated, HasAttendanceRules;

    protected $table = 'hr_attendance_policy_versions';

    protected $fillable = [
        'center_id',
        'attendance_policy_id',
        'check_in_before_minutes',
        'check_in_after_minutes',
        'check_out_before_minutes',
        'check_out_after_minutes',
        'late_grace_minutes',
        'early_leave_grace_minutes',
        'late_counting',
        'late_action',
        'absence_after_minutes',
        'early_leave_counting',
        'early_leave_action',
        'missing_checkout_action',
        'duplicate_punch_action',
        'duplicate_window_minutes',
        'gps_requirement',
        'allow_manual_attendance',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    public function policy()
    {
        return $this->belongsTo(AttendancePolicy::class, 'attendance_policy_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
