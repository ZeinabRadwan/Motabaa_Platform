<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * One check-in / check-out attempt from the attendance page, kept whether it was accepted, rejected or ignored
 * as a duplicate. Append-only.
 */
class AttendancePunch extends Model
{
    use BelongsToCenter;

    public const TYPE_CHECK_IN = 'check_in';
    public const TYPE_CHECK_OUT = 'check_out';

    public const RESULT_ACCEPTED = 'accepted';
    public const RESULT_REJECTED = 'rejected';
    public const RESULT_DUPLICATE = 'duplicate';

    public const UPDATED_AT = null;

    protected $table = 'hr_attendance_punches';

    protected $fillable = [
        'center_id',
        'employee_id',
        'attendance_record_id',
        'type',
        'result',
        'reason',
        'attempted_at',
        'attendance_date',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_meters',
        'work_location_id',
        'verification',
        'location_error',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'attendance_date' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'distance_meters' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function record()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id')->withTrashed();
    }
}
