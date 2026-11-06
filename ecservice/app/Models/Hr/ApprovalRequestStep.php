<?php

namespace App\Models\Hr;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class ApprovalRequestStep extends Model
{
    protected $table = 'hr_approval_request_steps';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SKIPPED = 'skipped';

    public const APPROVER_DIRECT_MANAGER = 'direct_manager';
    public const APPROVER_DEPARTMENT_MANAGER = 'department_manager';
    public const APPROVER_BRANCH_MANAGER = 'branch_manager';
    public const APPROVER_ROLE = 'role';
    public const APPROVER_USER = 'user';
    public const APPROVER_PERMISSION = 'permission';

    public const APPROVER_TYPES = [
        self::APPROVER_DIRECT_MANAGER,
        self::APPROVER_DEPARTMENT_MANAGER,
        self::APPROVER_BRANCH_MANAGER,
        self::APPROVER_ROLE,
        self::APPROVER_USER,
        self::APPROVER_PERMISSION,
    ];

    /** Approver types whose approver_value is required (role id, user id, permission name). */
    public const APPROVER_TYPES_WITH_VALUE = [
        self::APPROVER_ROLE,
        self::APPROVER_USER,
        self::APPROVER_PERMISSION,
    ];

    protected $fillable = [
        'approval_request_id',
        'step_order',
        'approver_type',
        'approver_value',
        'status',
        'acted_by',
        'acted_at',
        'comment',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'acted_at' => 'datetime',
    ];

    public static function approverTypeLabels(): array
    {
        $labels = [];
        foreach (self::APPROVER_TYPES as $type) {
            $labels[$type] = __("tr.hr.approver_types.{$type}");
        }

        return $labels;
    }

    /**
     * Display name of a role / user / permission approver value (null for manager-based types).
     */
    public static function approverValueLabel(string $type, ?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            self::APPROVER_ROLE => Role::query()->whereKey((int) $value)->value('name') ?? $value,
            self::APPROVER_USER => User::withTrashed()->whereKey((int) $value)->value('name') ?? $value,
            self::APPROVER_PERMISSION => PermissionCatalog::definitions()[$value][app()->getLocale() === 'ar' ? 'name_local' : 'name_en'] ?? $value,
            default => null,
        };
    }

    public function request()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by')->withTrashed();
    }
}
