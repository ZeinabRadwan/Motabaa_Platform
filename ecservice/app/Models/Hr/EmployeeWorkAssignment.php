<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\EffectiveDated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Where an employee works (branch + optional work location) from effective_from. A branch transfer or a
 * work location change is a new row; rows are never edited after they take effect.
 */
class EmployeeWorkAssignment extends Model
{
    use Auditable, BelongsToCenter, EffectiveDated;

    protected $table = 'hr_employee_work_assignments';

    protected $fillable = [
        'center_id',
        'employee_id',
        'branch_id',
        'work_location_id',
        'effective_from',
        'effective_to',
        'reason',
        'created_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
