<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A geofence (center point + radius) where employees of a branch are allowed to check in.
 */
class WorkLocation extends Model
{
    use Auditable, BelongsToCenter, HasLocalizedName, SoftDeletes;

    protected $table = 'hr_work_locations';

    protected $fillable = [
        'center_id',
        'branch_id',
        'name_ar',
        'name_en',
        'address',
        'latitude',
        'longitude',
        'radius_meters',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'work_location_id');
    }

    public function workAssignments()
    {
        return $this->hasMany(EmployeeWorkAssignment::class, 'work_location_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
