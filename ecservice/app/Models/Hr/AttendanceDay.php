<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The processed attendance of an employee for one date: one row per employee and date, so reprocessing updates the
 * same row and nothing is ever counted twice. computed_* columns hold the result of HrAttendanceDayEvaluator;
 * status / late_minutes / early_departure_minutes / absence_days are the final values (the computed ones, or the
 * HR correction stored in override_*). Days locked by an approved attendance period are never reprocessed.
 */
class AttendanceDay extends Model
{
    use BelongsToCenter;

    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_EARLY_DEPARTURE = 'early_departure';
    public const STATUS_LATE_EARLY_DEPARTURE = 'late_early_departure';
    public const STATUS_ABSENT = 'absent';
    /** Missing check-in or check-out, or failed GPS attempts without a record: an exception for HR to review. */
    public const STATUS_INCOMPLETE = 'incomplete';
    /** The day is not over yet (or the session is still open). */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_REST_DAY = 'rest_day';
    public const STATUS_DAY_OFF = 'day_off';
    public const STATUS_EXEMPT = 'exempt';
    /** Absence excused by HR (not counted as absence). */
    public const STATUS_EXCUSED = 'excused';
    public const STATUS_UNSCHEDULED = 'unscheduled';
    public const STATUS_NOT_EMPLOYED = 'not_employed';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_EARLY_DEPARTURE,
        self::STATUS_LATE_EARLY_DEPARTURE,
        self::STATUS_ABSENT,
        self::STATUS_INCOMPLETE,
        self::STATUS_PENDING,
        self::STATUS_ON_LEAVE,
        self::STATUS_HOLIDAY,
        self::STATUS_REST_DAY,
        self::STATUS_DAY_OFF,
        self::STATUS_EXEMPT,
        self::STATUS_EXCUSED,
        self::STATUS_UNSCHEDULED,
        self::STATUS_NOT_EMPLOYED,
    ];

    /** The employee attended (whatever the lateness). */
    public const PRESENT_STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_EARLY_DEPARTURE,
        self::STATUS_LATE_EARLY_DEPARTURE,
    ];

    /** Statuses of a scheduled working day that HR may correct. */
    public const CORRECTABLE_STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_LATE,
        self::STATUS_EARLY_DEPARTURE,
        self::STATUS_LATE_EARLY_DEPARTURE,
        self::STATUS_ABSENT,
        self::STATUS_INCOMPLETE,
        self::STATUS_EXCUSED,
    ];

    /** What HR can set a day to; `present` keeps the late / early minutes (computed or corrected). */
    public const OVERRIDE_STATUSES = [self::STATUS_PRESENT, self::STATUS_ABSENT, self::STATUS_EXCUSED];

    public const EXCEPTION_MISSING_CHECK_IN = 'missing_check_in';
    public const EXCEPTION_MISSING_CHECK_OUT = 'missing_check_out';
    /** Check-in attempts were rejected (GPS / location) and no attendance was recorded: never an absence by itself. */
    public const EXCEPTION_GPS_VERIFICATION_FAILED = 'gps_verification_failed';
    public const EXCEPTION_ATTENDED_DURING_LEAVE = 'attended_during_leave';

    public const EXCEPTIONS = [
        self::EXCEPTION_MISSING_CHECK_IN,
        self::EXCEPTION_MISSING_CHECK_OUT,
        self::EXCEPTION_GPS_VERIFICATION_FAILED,
        self::EXCEPTION_ATTENDED_DURING_LEAVE,
    ];

    public const REASON_NO_ATTENDANCE = 'no_attendance';
    public const REASON_LATE_BEYOND_ABSENCE_THRESHOLD = 'late_beyond_absence_threshold';
    public const REASON_OVERRIDDEN = 'overridden';

    public const FLAG_POLICY_MARKS_ABSENT = 'policy_marks_absent';

    public const REVIEW_PENDING = 'pending';
    public const REVIEW_RESOLVED = 'resolved';

    protected $table = 'hr_attendance_days';

    protected $fillable = [
        'center_id',
        'employee_id',
        'branch_id',
        'attendance_date',
        'day_type',
        'status',
        'computed_status',
        'status_reason',
        'shift_id',
        'scheduled_start_at',
        'scheduled_end_at',
        'expected_minutes',
        'late_grace_minutes',
        'attendance_record_id',
        'attendance_method',
        'check_in_at',
        'check_out_at',
        'worked_minutes',
        'late_minutes',
        'early_departure_minutes',
        'computed_late_minutes',
        'computed_early_departure_minutes',
        'absence_days',
        'leave_request_id',
        'leave_type_id',
        'leave_is_paid',
        'holiday_id',
        'exception_reasons',
        'review_flags',
        'review_status',
        'override_status',
        'override_late_minutes',
        'override_early_departure_minutes',
        'override_reason',
        'overridden_by',
        'overridden_at',
        'attendance_period_id',
        'locked_at',
        'processed_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'expected_minutes' => 'integer',
        'late_grace_minutes' => 'integer',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_departure_minutes' => 'integer',
        'computed_late_minutes' => 'integer',
        'computed_early_departure_minutes' => 'integer',
        'absence_days' => 'float',
        'leave_is_paid' => 'boolean',
        'exception_reasons' => 'array',
        'review_flags' => 'array',
        'override_late_minutes' => 'integer',
        'override_early_departure_minutes' => 'integer',
        'overridden_at' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public static function statusLabels(): array
    {
        return Employee::labels(self::STATUSES, 'tr.hr.attendance_day_statuses');
    }

    public static function exceptionLabels(): array
    {
        return Employee::labels(self::EXCEPTIONS, 'tr.hr.attendance_exceptions');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** HR corrected or reviewed the day (possibly confirming the computed values); reprocessing keeps the correction. */
    public function isOverridden(): bool
    {
        return $this->overridden_at !== null;
    }

    public function scopeWithUnresolvedExceptions(Builder $query): Builder
    {
        return $query->where($this->getTable().'.review_status', self::REVIEW_PENDING);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id')->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id')->withTrashed();
    }

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id')->withTrashed();
    }

    public function holiday()
    {
        return $this->belongsTo(Holiday::class, 'holiday_id')->withTrashed();
    }

    public function period()
    {
        return $this->belongsTo(AttendancePeriod::class, 'attendance_period_id');
    }

    public function overrider()
    {
        return $this->belongsTo(User::class, 'overridden_by')->withTrashed();
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceDayCorrection::class, 'attendance_day_id');
    }
}
