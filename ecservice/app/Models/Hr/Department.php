<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, SoftDeletes;

    protected $table = 'hr_departments';

    protected $fillable = [
        'center_id',
        'parent_id',
        'code',
        'name_ar',
        'name_en',
        'sort_order',
        'is_active',
        'manager_employee_id',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }
}
