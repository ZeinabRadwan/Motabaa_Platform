<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * Balance of one employee for one leave type and year. Every change goes through HrLeaveBalances, which writes
 * the matching hr_leave_balance_transactions row.
 */
class LeaveBalance extends Model
{
    use BelongsToCenter;

    protected $table = 'hr_leave_balances';

    protected $fillable = [
        'center_id',
        'employee_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'carried_over_days',
        'adjusted_days',
        'used_days',
        'pending_days',
    ];

    protected $casts = [
        'year' => 'integer',
        'entitled_days' => 'float',
        'carried_over_days' => 'float',
        'adjusted_days' => 'float',
        'used_days' => 'float',
        'pending_days' => 'float',
    ];

    /** Days that can still be requested: total minus used and reserved by pending requests. */
    public function available(): float
    {
        return round($this->total() - $this->used_days - $this->pending_days, 2);
    }

    public function total(): float
    {
        return round($this->entitled_days + $this->carried_over_days + $this->adjusted_days, 2);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id')->withTrashed();
    }

    public function transactions()
    {
        return $this->hasMany(LeaveBalanceTransaction::class, 'leave_balance_id');
    }
}
