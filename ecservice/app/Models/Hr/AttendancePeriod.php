<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A payroll attendance period of a center (or one branch). Processing builds the per employee summaries; approving
 * locks every attendance day of the period so payroll reads stable, approved figures, and only approved periods
 * are exposed to payroll.
 */
class AttendancePeriod extends Model
{
    use Auditable, BelongsToCenter;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_APPROVED = 'approved';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PROCESSED, self::STATUS_APPROVED];

    public const MAX_DAYS = 62;

    protected $table = 'hr_attendance_periods';

    protected $fillable = [
        'center_id',
        'branch_id',
        'name',
        'period_start',
        'period_end',
        'status',
        'employees_count',
        'totals',
        'notes',
        'processed_at',
        'processed_by',
        'approved_at',
        'approved_by',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'employees_count' => 'integer',
        'totals' => 'array',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return Employee::labels(self::STATUSES, 'tr.hr.attendance_period_statuses');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function summaries()
    {
        return $this->hasMany(AttendancePeriodSummary::class, 'attendance_period_id');
    }

    public function days()
    {
        return $this->hasMany(AttendanceDay::class, 'attendance_period_id');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function reopener()
    {
        return $this->belongsTo(User::class, 'reopened_by')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
