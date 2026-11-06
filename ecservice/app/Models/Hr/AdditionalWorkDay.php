<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use App\Support\Hr\Approvals\Approvable;
use App\Support\Hr\HrAdditionalWorkDays;
use Illuminate\Database\Eloquent\Model;

/**
 * An additional working day (a whole or half day; payroll never pays overtime hours). Drafts and pending records
 * can be edited; submitted records are decided by the approval engine; approved days are paid by the payroll of
 * the first approved period that settles them (status paid).
 */
class AdditionalWorkDay extends Model implements Approvable
{
    use Auditable, BelongsToCenter;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_PAID,
    ];

    /** Records that hold their work date: another record of the same employee and date is a duplicate. */
    public const ACTIVE_STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PAID];

    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_PENDING];

    public const ALLOWED_DAYS = [0.5, 1.0];

    public const APPROVAL_TYPE = 'additional_work_day';

    public const DAY_TYPE_HOLIDAY = 'holiday';

    protected $table = 'hr_additional_work_days';

    protected $fillable = [
        'center_id',
        'employee_id',
        'branch_id',
        'work_date',
        'days',
        'day_type',
        'reason',
        'status',
        'duplicate_guard',
        'attendance_period_id',
        'paid_period_id',
        'paid_at',
        'gross_salary',
        'daily_rate',
        'amount',
        'approval_request_id',
        'created_by',
        'updated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'work_date' => 'date',
        'duplicate_guard' => 'date',
        'days' => 'float',
        'gross_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return Employee::labels(self::STATUSES, 'tr.hr.additional_day_statuses');
    }

    public static function dayTypeLabels(): array
    {
        return Employee::labels(['working', 'rest', 'off', 'unscheduled', self::DAY_TYPE_HOLIDAY], 'tr.hr.additional_day_types');
    }

    public function auditExcludedAttributes(): array
    {
        return ['duplicate_guard', 'updated_by'];
    }

    public function approvalResolved(ApprovalRequest $request): void
    {
        HrAdditionalWorkDays::approvalResolved($this, $request);
    }

    /** Shown with the approval request so approvers decide without opening the HR pages. */
    public function approvalSummary(): array
    {
        return [
            'kind' => self::APPROVAL_TYPE,
            'additional_work_day_id' => $this->id,
            'work_date' => $this->work_date->toDateString(),
            'days' => $this->days,
            'day_type' => $this->day_type,
            'day_type_label' => $this->day_type ? (self::dayTypeLabels()[$this->day_type] ?? $this->day_type) : null,
            'reason' => $this->reason,
            'status' => $this->status,
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function attendancePeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function paidPeriod()
    {
        return $this->belongsTo(AttendancePeriod::class, 'paid_period_id');
    }

    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function rejecter()
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }
}
