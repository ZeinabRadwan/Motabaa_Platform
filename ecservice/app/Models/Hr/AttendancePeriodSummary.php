<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * Totals of one employee for one attendance period, rebuilt from the processed attendance days whenever the period
 * is processed or approved.
 */
class AttendancePeriodSummary extends Model
{
    use BelongsToCenter;

    public const COUNTERS = [
        'scheduled_days',
        'present_days',
        'absence_days',
        'late_days',
        'late_minutes',
        'early_departure_days',
        'early_departure_minutes',
        'worked_minutes',
        'expected_minutes',
        'leave_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'holiday_days',
        'rest_days',
        'exempt_days',
        'excused_days',
        'exceptions_count',
        'unresolved_exceptions',
    ];

    protected $table = 'hr_attendance_period_summaries';

    protected $fillable = [
        'center_id',
        'attendance_period_id',
        'employee_id',
        'branch_id',
        'scheduled_days',
        'present_days',
        'absence_days',
        'late_days',
        'late_minutes',
        'early_departure_days',
        'early_departure_minutes',
        'worked_minutes',
        'expected_minutes',
        'leave_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'holiday_days',
        'rest_days',
        'exempt_days',
        'excused_days',
        'exceptions_count',
        'unresolved_exceptions',
        'leave_breakdown',
        'leave_requests',
        'exception_details',
    ];

    protected $casts = [
        'scheduled_days' => 'integer',
        'present_days' => 'integer',
        'absence_days' => 'float',
        'late_days' => 'integer',
        'late_minutes' => 'integer',
        'early_departure_days' => 'integer',
        'early_departure_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'expected_minutes' => 'integer',
        'leave_days' => 'float',
        'paid_leave_days' => 'float',
        'unpaid_leave_days' => 'float',
        'holiday_days' => 'integer',
        'rest_days' => 'integer',
        'exempt_days' => 'integer',
        'excused_days' => 'integer',
        'exceptions_count' => 'integer',
        'unresolved_exceptions' => 'integer',
        'leave_breakdown' => 'array',
        'leave_requests' => 'array',
        'exception_details' => 'array',
    ];

    public function period()
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }
}
