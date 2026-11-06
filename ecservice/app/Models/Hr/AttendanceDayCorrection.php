<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of HR corrections of a processed attendance day.
 */
class AttendanceDayCorrection extends Model
{
    use BelongsToCenter;

    public const ACTION_CORRECTED = 'corrected';
    public const ACTION_RESET = 'reset';

    public const UPDATED_AT = null;

    protected $table = 'hr_attendance_day_corrections';

    protected $fillable = [
        'center_id',
        'attendance_day_id',
        'employee_id',
        'action',
        'old_values',
        'new_values',
        'reason',
        'corrected_by',
        'via_impersonation',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'via_impersonation' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function attendanceDay()
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by')->withTrashed();
    }
}
