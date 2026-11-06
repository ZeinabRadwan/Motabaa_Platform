<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    use Auditable, BelongsToCenter;

    protected $table = 'hr_approval_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Request types the approval engine accepts. Business modules register theirs here as they ship.
     */
    public const TYPES = [
        'leave_request',
        'manual_attendance',
        'additional_work_day',
        'payroll_run',
    ];

    protected $fillable = [
        'center_id',
        'branch_id',
        'workflow_id',
        'request_type',
        'approvable_type',
        'approvable_id',
        'employee_id',
        'requested_by',
        'status',
        'current_step',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        $labels = [];
        foreach (self::TYPES as $type) {
            $labels[$type] = __("tr.hr.request_types.{$type}");
        }

        return $labels;
    }

    public static function statusLabels(): array
    {
        $labels = [];
        foreach (self::STATUSES as $status) {
            $labels[$status] = __("tr.hr.approval_statuses.{$status}");
        }

        return $labels;
    }

    public function approvable()
    {
        return $this->morphTo();
    }

    public function steps()
    {
        return $this->hasMany(ApprovalRequestStep::class, 'approval_request_id')->orderBy('step_order');
    }

    public function currentStepRecord(): ?ApprovalRequestStep
    {
        $steps = $this->relationLoaded('steps') ? $this->steps : $this->steps()->get();

        return $steps->firstWhere('step_order', $this->current_step);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
