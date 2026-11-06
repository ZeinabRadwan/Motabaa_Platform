<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * A mobile device an employee registered for location updates. The device secret is only ever stored as a SHA-256
 * hash (`token_hash`) and is never returned after registration.
 */
class AttendanceDevice extends Model
{
    use Auditable;
    use BelongsToCenter;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    public const REVOKED_BY_HR = 'hr_revoked';
    public const REVOKED_REPLACED = 'replaced';
    public const REVOKED_UNREGISTERED = 'unregistered';

    public const PLATFORMS = ['android', 'ios'];

    protected $table = 'hr_attendance_devices';

    protected $fillable = [
        'center_id',
        'employee_id',
        'user_id',
        'device_uuid',
        'platform',
        'device_name',
        'device_model',
        'os_version',
        'app_version',
        'token_hash',
        'token_issued_at',
        'status',
        'approved_by',
        'approved_at',
        'revoked_by',
        'revoked_at',
        'revoke_reason',
        'last_seen_at',
        'last_ip',
        'last_location_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'token_issued_at' => 'datetime',
        'approved_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_location_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function auditExcludedAttributes(): array
    {
        return ['token_issued_at', 'last_seen_at', 'last_ip', 'last_location_at'];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
