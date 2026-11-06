<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Employee attendance method, GPS verification, attendance policy and grace period in force from effective_from
 * through effective_to. hr_employees caches the row in force today; past rows are never rewritten, so changing an
 * employee's method keeps the configuration that applied to earlier attendance.
 */
class EmployeeAttendanceConfig extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated;

    protected $table = 'hr_employee_attendance_configs';

    protected $fillable = [
        'center_id',
        'employee_id',
        'attendance_mode',
        'gps_policy',
        'attendance_policy_id',
        'grace_minutes',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'attendance_policy_id' => 'integer',
        'grace_minutes' => 'integer',
    ];

    public const CONFIG_FIELDS = ['attendance_mode', 'gps_policy', 'attendance_policy_id', 'grace_minutes'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function attendancePolicy()
    {
        return $this->belongsTo(AttendancePolicy::class, 'attendance_policy_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
