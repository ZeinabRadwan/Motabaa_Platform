<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use App\Support\Hr\Approvals\Approvable;
use App\Support\Hr\HrLeaveRequests;
use Illuminate\Database\Eloquent\Model;

/**
 * A leave request. Pending requests reserve their days on the balance; the approval engine decides them
 * (direct manager, then HR when the leave type requires it) and calls approvalResolved().
 */
class LeaveRequest extends Model implements Approvable
{
    use Auditable, BelongsToCenter;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /** Requests that hold days: they block overlapping requests and count on the balance. */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED];

    public const SOURCE_SELF = 'self';
    public const SOURCE_HR = 'hr';
    /** Entered by HR as already approved (e.g. leave decided on paper). */
    public const SOURCE_RECORDED = 'recorded';

    public const SOURCES = [self::SOURCE_SELF, self::SOURCE_HR, self::SOURCE_RECORDED];

    public const APPROVAL_TYPE = 'leave_request';

    protected $table = 'hr_leave_requests';

    protected $fillable = [
        'center_id',
        'employee_id',
        'leave_type_id',
        'branch_id',
        'start_date',
        'end_date',
        'days',
        'calendar_days',
        'balance_year',
        'reason',
        'status',
        'source',
        'approval_request_id',
        'requested_by',
        'decided_at',
        'decided_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
        'calendar_days' => 'integer',
        'balance_year' => 'integer',
        'decided_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return Employee::labels(self::STATUSES, 'tr.hr.leave_statuses');
    }

    public function approvalResolved(ApprovalRequest $request): void
    {
        HrLeaveRequests::approvalResolved($this, $request);
    }

    /** Shown with the approval request so approvers decide without opening the HR pages. */
    public function approvalSummary(): array
    {
        $this->loadMissing(['leaveType', 'attachments']);

        return [
            'kind' => self::APPROVAL_TYPE,
            'leave_request_id' => $this->id,
            'leave_type' => $this->leaveType?->name,
            'is_paid' => (bool) $this->leaveType?->is_paid,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'attachments' => $this->attachments->map(fn (LeaveRequestAttachment $file) => [
                'id' => $file->id,
                'name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
            ])->values()->all(),
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id')->withTrashed();
    }

    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function attachments()
    {
        return $this->hasMany(LeaveRequestAttachment::class, 'leave_request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }
}
