<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * One manual creation or change of an attendance record: who, when, why, and the previous and new values of the
 * changed fields (times as local "Y-m-d H:i"). Append-only.
 */
class AttendanceRecordChange extends Model
{
    use BelongsToCenter;

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_CHECK_IN_CORRECTED = 'check_in_corrected';
    public const ACTION_CHECK_OUT_CORRECTED = 'check_out_corrected';

    public const UPDATED_AT = null;

    protected $table = 'hr_attendance_record_changes';

    protected $fillable = [
        'center_id',
        'attendance_record_id',
        'employee_id',
        'action',
        'source',
        'entry_reason',
        'reason',
        'old_values',
        'new_values',
        'changed_by',
        'via_impersonation',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'via_impersonation' => 'boolean',
    ];

    public function record()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }
}
