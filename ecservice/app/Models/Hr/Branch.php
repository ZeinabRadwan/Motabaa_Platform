<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, SoftDeletes;

    protected $table = 'hr_branches';

    protected $fillable = [
        'center_id',
        'code',
        'name_ar',
        'name_en',
        'city',
        'country',
        'address',
        'phone',
        'email',
        'cr_number',
        'mol_establishment_number',
        'gosi_registration_number',
        'timezone',
        'is_headquarters',
        'is_active',
        'manager_employee_id',
        'default_shift_id',
        'created_by',
    ];

    protected $casts = [
        'is_headquarters' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function workLocations()
    {
        return $this->hasMany(WorkLocation::class, 'branch_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /** Shift given to employees hired into the branch when the hiring form names none. */
    public function defaultShift()
    {
        return $this->belongsTo(Shift::class, 'default_shift_id')->withTrashed();
    }

    public function workAssignments()
    {
        return $this->hasMany(EmployeeWorkAssignment::class, 'branch_id');
    }
}
