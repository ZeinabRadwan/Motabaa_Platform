<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A continuous period during a session in which the employee's location checks were outside the allowed radius.
 * Consecutive outside checks extend the same exception; a check inside the radius or the end of the session closes
 * it. HR reviews it (acknowledge / dismiss); it never changes the attendance by itself.
 */
class LocationException extends Model
{
    use Auditable;
    use BelongsToCenter;

    public const TYPE_OUTSIDE_RADIUS = 'outside_radius';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const REVIEW_PENDING = 'pending';
    public const REVIEW_ACKNOWLEDGED = 'acknowledged';
    public const REVIEW_DISMISSED = 'dismissed';

    protected $table = 'hr_location_exceptions';

    protected $fillable = [
        'center_id',
        'employee_id',
        'attendance_record_id',
        'work_location_id',
        'type',
        'status',
        'started_at',
        'last_seen_at',
        'ended_at',
        'first_check_id',
        'last_check_id',
        'checks_count',
        'max_distance_meters',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ended_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'checks_count' => 'integer',
        'max_distance_meters' => 'float',
    ];

    /** Only the opening and HR's review are audited, not every check that extends the period. */
    public function auditExcludedAttributes(): array
    {
        return ['status', 'last_seen_at', 'ended_at', 'last_check_id', 'checks_count', 'max_distance_meters'];
    }

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

    public function lastCheck()
    {
        return $this->belongsTo(LocationCheck::class, 'last_check_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
