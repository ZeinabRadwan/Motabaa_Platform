<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * One location verification of an electronic attendance session. A scheduled periodic check that has not received
 * a location stays `pending` (it is never turned into a successful verification).
 */
class LocationCheck extends Model
{
    use BelongsToCenter;

    public const KIND_CHECK_IN = 'check_in';
    public const KIND_PERIODIC = 'periodic';
    public const KIND_CHECK_OUT = 'check_out';

    public const SOURCE_WEB = 'web';
    public const SOURCE_MOBILE_FOREGROUND = 'mobile_foreground';
    public const SOURCE_MOBILE_BACKGROUND = 'mobile_background';
    public const SOURCE_SYSTEM = 'system';

    public const STATUS_WITHIN_RADIUS = 'within_radius';
    public const STATUS_OUTSIDE_RADIUS = 'outside_radius';
    public const STATUS_PERMISSION_DENIED = 'permission_denied';
    public const STATUS_GPS_UNAVAILABLE = 'gps_unavailable';
    public const STATUS_VERIFICATION_FAILED = 'verification_failed';
    public const STATUS_PENDING = 'pending';

    public const STATUSES = [
        self::STATUS_WITHIN_RADIUS,
        self::STATUS_OUTSIDE_RADIUS,
        self::STATUS_PERMISSION_DENIED,
        self::STATUS_GPS_UNAVAILABLE,
        self::STATUS_VERIFICATION_FAILED,
        self::STATUS_PENDING,
    ];

    public const SOURCES = [self::SOURCE_WEB, self::SOURCE_MOBILE_FOREGROUND, self::SOURCE_MOBILE_BACKGROUND, self::SOURCE_SYSTEM];

    protected $table = 'hr_location_checks';

    protected $fillable = [
        'center_id',
        'employee_id',
        'attendance_record_id',
        'work_location_id',
        'device_id',
        'kind',
        'source',
        'status',
        'reason',
        'due_at',
        'checked_at',
        'received_at',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_meters',
        'radius_meters',
        'is_mocked',
        'client_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'checked_at' => 'datetime',
        'received_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'distance_meters' => 'float',
        'radius_meters' => 'integer',
        'is_mocked' => 'boolean',
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

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
