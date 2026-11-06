<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use App\Support\Hr\HrAttendanceProcessor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The attendance of an employee for one attendance date (the scheduled day the check-in belongs to, so an
 * overnight shift's check-out stays on the day it started), whatever the recording method:
 *  - electronic: checked in / out by the employee from the website or the mobile API; hr_attendance_punches keeps
 *    every attempt;
 *  - manual: entered by HR; hr_attendance_record_changes keeps every creation and change with its reason.
 * HR corrections of an electronic record keep the method; the corrected time's verification becomes `manual`.
 * One record per employee and date, whatever the method.
 */
class AttendanceRecord extends Model
{
    use BelongsToCenter;

    /** Session state: open (electronic only), closed, or closed with a missing side. */
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_MISSING_CHECK_OUT = 'missing_check_out';
    public const STATUS_MISSING_CHECK_IN = 'missing_check_in';

    public const METHOD_ELECTRONIC = 'electronic';
    public const METHOD_MANUAL = 'manual';

    public const METHODS = [self::METHOD_ELECTRONIC, self::METHOD_MANUAL];

    /** Attendance status computed from the times and the schedule on every save (attendanceStatusFor()). */
    public const ATTENDANCE_PRESENT = 'present';
    public const ATTENDANCE_LATE = 'late';
    public const ATTENDANCE_EARLY_DEPARTURE = 'early_departure';
    public const ATTENDANCE_LATE_EARLY_DEPARTURE = 'late_early_departure';
    public const ATTENDANCE_MISSING_CHECK_IN = 'missing_check_in';
    public const ATTENDANCE_MISSING_CHECK_OUT = 'missing_check_out';
    /** Attendance on a day without a scheduled shift (rest day, day off, unscheduled): no lateness applies. */
    public const ATTENDANCE_OFF_SCHEDULE = 'off_schedule';
    public const ATTENDANCE_IN_PROGRESS = 'in_progress';

    public const ATTENDANCE_STATUSES = [
        self::ATTENDANCE_PRESENT,
        self::ATTENDANCE_LATE,
        self::ATTENDANCE_EARLY_DEPARTURE,
        self::ATTENDANCE_LATE_EARLY_DEPARTURE,
        self::ATTENDANCE_MISSING_CHECK_IN,
        self::ATTENDANCE_MISSING_CHECK_OUT,
        self::ATTENDANCE_OFF_SCHEDULE,
        self::ATTENDANCE_IN_PROGRESS,
    ];

    public const SOURCE_FORM = 'form';
    public const SOURCE_BULK = 'bulk';
    public const SOURCE_IMPORT = 'import';

    public const ENTRY_SOURCES = [self::SOURCE_FORM, self::SOURCE_BULK, self::SOURCE_IMPORT];

    /** Why a record was entered manually; `other` requires details. */
    public const ENTRY_REASONS = [
        'manual_attendance_method',
        'forgot_check_in',
        'forgot_check_out',
        'system_or_device_issue',
        'field_work',
        'official_assignment',
        'no_website_access',
        'other',
    ];

    public const REVIEW_OUTSIDE_RADIUS = 'outside_radius';
    public const REVIEW_LOW_ACCURACY = 'low_accuracy';
    public const REVIEW_LOCATION_UNAVAILABLE = 'location_unavailable';
    public const REVIEW_LOCATION_NOT_CONFIGURED = 'location_not_configured';
    public const REVIEW_LATE_JUSTIFICATION = 'late_requires_justification';
    public const REVIEW_EARLY_JUSTIFICATION = 'early_leave_requires_justification';
    public const REVIEW_REPEATED_CHECK_OUT = 'repeated_check_out';
    public const REVIEW_MISSING_CHECK_OUT = 'missing_check_out';
    public const REVIEW_AUTO_CHECK_OUT = 'auto_check_out';
    /** A periodic location check found the employee outside the allowed radius during the session. */
    public const REVIEW_LEFT_WORK_LOCATION = 'left_work_location';

    protected $table = 'hr_attendance_records';

    protected $fillable = [
        'center_id',
        'employee_id',
        'attendance_date',
        'attendance_method',
        'status',
        'open_session_key',
        'shift_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'attendance_config_id',
        'attendance_policy_id',
        'attendance_policy_version_id',
        'gps_requirement',
        'missing_checkout_action',
        'check_out_deadline_at',
        'work_location_id',
        'location_radius_meters',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_accuracy_meters',
        'check_in_distance_meters',
        'check_in_verification',
        'check_in_ip',
        'check_in_user_agent',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_accuracy_meters',
        'check_out_distance_meters',
        'check_out_verification',
        'check_out_ip',
        'check_out_user_agent',
        'late_minutes',
        'early_departure_minutes',
        'worked_minutes',
        'needs_review',
        'review_reasons',
        'location_check_interval_minutes',
        'next_location_check_at',
        'last_location_check_id',
        'location_status',
        'attendance_status',
        'entry_source',
        'entry_reason',
        'entry_reason_notes',
        'notes',
        'shift_overridden',
        'created_by',
        'updated_by',
        'modified_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'check_out_deadline_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'check_in_accuracy_meters' => 'float',
        'check_in_distance_meters' => 'float',
        'check_out_latitude' => 'float',
        'check_out_longitude' => 'float',
        'check_out_accuracy_meters' => 'float',
        'check_out_distance_meters' => 'float',
        'location_radius_meters' => 'integer',
        'late_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'worked_minutes' => 'integer',
        'needs_review' => 'boolean',
        'review_reasons' => 'array',
        'location_check_interval_minutes' => 'integer',
        'next_location_check_at' => 'datetime',
        'shift_overridden' => 'boolean',
        'modified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $record) {
            $record->attendance_status = self::attendanceStatusFor($record);
        });
        static::saved(fn (self $record) => HrAttendanceProcessor::refreshRecord($record));
    }

    /**
     * Pure, so electronic and manual records get the same status from the same values: missing sides first, then
     * an open session, a day without a scheduled shift, and finally the lateness / early departure computed with
     * the shift's grace periods.
     */
    public static function attendanceStatusFor(self $record): string
    {
        $late = (int) $record->late_minutes > 0;
        $early = (int) $record->early_departure_minutes > 0;

        return match (true) {
            $record->check_in_at === null => self::ATTENDANCE_MISSING_CHECK_IN,
            $record->status === self::STATUS_CHECKED_IN => self::ATTENDANCE_IN_PROGRESS,
            $record->check_out_at === null => self::ATTENDANCE_MISSING_CHECK_OUT,
            $record->scheduled_start_at === null => self::ATTENDANCE_OFF_SCHEDULE,
            $late && $early => self::ATTENDANCE_LATE_EARLY_DEPARTURE,
            $late => self::ATTENDANCE_LATE,
            $early => self::ATTENDANCE_EARLY_DEPARTURE,
            default => self::ATTENDANCE_PRESENT,
        };
    }

    public function isManual(): bool
    {
        return $this->attendance_method === self::METHOD_MANUAL;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function history()
    {
        return $this->hasMany(AttendanceRecordChange::class, 'attendance_record_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id')->withTrashed();
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id')->withTrashed();
    }

    public function punches()
    {
        return $this->hasMany(AttendancePunch::class, 'attendance_record_id');
    }

    public function locationChecks()
    {
        return $this->hasMany(LocationCheck::class, 'attendance_record_id');
    }

    public function lastLocationCheck()
    {
        return $this->belongsTo(LocationCheck::class, 'last_location_check_id');
    }

    public function locationExceptions()
    {
        return $this->hasMany(LocationException::class, 'attendance_record_id');
    }

    public function isMonitored(): bool
    {
        return $this->location_check_interval_minutes !== null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where($this->getTable().'.status', self::STATUS_CHECKED_IN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    public function addReviewReasons(array $reasons): void
    {
        $merged = array_values(array_unique(array_merge($this->review_reasons ?? [], array_filter($reasons))));
        $this->review_reasons = $merged === [] ? null : $merged;
        $this->needs_review = $merged !== [];
    }

    public function removeReviewReasons(array $reasons): void
    {
        $kept = array_values(array_diff($this->review_reasons ?? [], $reasons));
        $this->review_reasons = $kept === [] ? null : $kept;
        $this->needs_review = $kept !== [];
    }
}
