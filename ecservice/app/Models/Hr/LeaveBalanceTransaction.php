<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger row of a leave balance change. `days` is signed from the point of view of the column it
 * changes (reserve +pending, release -pending, use +used, restore -used, entitlement / carry_over / adjustment
 * +/- the total).
 */
class LeaveBalanceTransaction extends Model
{
    use BelongsToCenter;

    public const KIND_ENTITLEMENT = 'entitlement';
    public const KIND_CARRY_OVER = 'carry_over';
    public const KIND_ADJUSTMENT = 'adjustment';
    public const KIND_RESERVE = 'reserve';
    public const KIND_RELEASE = 'release';
    public const KIND_USE = 'use';
    public const KIND_RESTORE = 'restore';

    public const KINDS = [
        self::KIND_ENTITLEMENT,
        self::KIND_CARRY_OVER,
        self::KIND_ADJUSTMENT,
        self::KIND_RESERVE,
        self::KIND_RELEASE,
        self::KIND_USE,
        self::KIND_RESTORE,
    ];

    public const UPDATED_AT = null;

    protected $table = 'hr_leave_balance_transactions';

    protected $fillable = [
        'center_id',
        'leave_balance_id',
        'employee_id',
        'leave_type_id',
        'leave_request_id',
        'kind',
        'days',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'days' => 'float',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function balance()
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
