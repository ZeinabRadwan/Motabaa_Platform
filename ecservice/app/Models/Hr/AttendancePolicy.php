<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\Hr\Concerns\HasAttendanceRules;
use App\Models\Hr\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Attendance rules employees are assigned to. Rule columns cache the hr_attendance_policy_versions row in
 * force today (current_version_id); history and scheduled changes live in the versions, so attendance for a
 * past date is always evaluated with the rules in force on that date.
 */
class AttendancePolicy extends Model
{
    use Auditable, BelongsToCenter, HasAttendanceRules, HasLocalizedName, SoftDeletes;

    public const COUNTING_FULL = 'full';
    public const COUNTING_EXCESS_ONLY = 'excess_only';

    /** Beyond the grace period: count the whole delay, or only the minutes after the grace period. */
    public const COUNTING_MODES = [self::COUNTING_FULL, self::COUNTING_EXCESS_ONLY];

    public const ACTION_RECORD_ONLY = 'record_only';
    public const ACTION_REQUIRE_JUSTIFICATION = 'require_justification';
    public const ACTION_DEDUCT = 'deduct';

    /** What happens when an employee arrives late / leaves early beyond the grace period. */
    public const DEVIATION_ACTIONS = [self::ACTION_RECORD_ONLY, self::ACTION_REQUIRE_JUSTIFICATION, self::ACTION_DEDUCT];

    public const MISSING_CHECKOUT_FLAG = 'flag_for_review';
    public const MISSING_CHECKOUT_AUTO = 'auto_checkout_at_shift_end';
    public const MISSING_CHECKOUT_ABSENT = 'mark_absent';

    /** Applied when the check-out window closes without a check-out. */
    public const MISSING_CHECKOUT_ACTIONS = [self::MISSING_CHECKOUT_FLAG, self::MISSING_CHECKOUT_AUTO, self::MISSING_CHECKOUT_ABSENT];

    public const DUPLICATE_FIRST_IN_LAST_OUT = 'first_in_last_out';
    public const DUPLICATE_REJECT = 'reject';
    public const DUPLICATE_FLAG = 'flag_for_review';

    /** Applied to repeated punches within duplicate_window_minutes or a second check-in for the same shift. */
    public const DUPLICATE_ACTIONS = [self::DUPLICATE_FIRST_IN_LAST_OUT, self::DUPLICATE_REJECT, self::DUPLICATE_FLAG];

    public const GPS_REQUIRED = 'required';
    public const GPS_FLEXIBLE = 'flexible';
    public const GPS_NONE = 'none';

    /** GPS verification for electronic attendance. Manual attendance never requires GPS. */
    public const GPS_REQUIREMENTS = [self::GPS_REQUIRED, self::GPS_FLEXIBLE, self::GPS_NONE];

    public const RULE_FIELDS = [
        'check_in_before_minutes',
        'check_in_after_minutes',
        'check_out_before_minutes',
        'check_out_after_minutes',
        'late_grace_minutes',
        'early_leave_grace_minutes',
        'late_counting',
        'late_action',
        'absence_after_minutes',
        'early_leave_counting',
        'early_leave_action',
        'missing_checkout_action',
        'duplicate_punch_action',
        'duplicate_window_minutes',
        'gps_requirement',
        'allow_manual_attendance',
    ];

    protected $table = 'hr_attendance_policies';

    protected $fillable = [
        'center_id',
        'code',
        'name_ar',
        'name_en',
        'description',
        'check_in_before_minutes',
        'check_in_after_minutes',
        'check_out_before_minutes',
        'check_out_after_minutes',
        'late_grace_minutes',
        'early_leave_grace_minutes',
        'late_counting',
        'late_action',
        'absence_after_minutes',
        'early_leave_counting',
        'early_leave_action',
        'missing_checkout_action',
        'duplicate_punch_action',
        'duplicate_window_minutes',
        'gps_requirement',
        'allow_manual_attendance',
        'current_version_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_version_id' => 'integer',
    ];

    public function auditExcludedAttributes(): array
    {
        return ['current_version_id'];
    }

    /** @return array<string, array<string, string>> option group => value => label */
    public static function optionLabels(): array
    {
        $prefix = 'tr.hr.attendance_rules';

        return [
            'counting_modes' => Employee::labels(self::COUNTING_MODES, "{$prefix}.counting_modes"),
            'deviation_actions' => Employee::labels(self::DEVIATION_ACTIONS, "{$prefix}.deviation_actions"),
            'missing_checkout_actions' => Employee::labels(self::MISSING_CHECKOUT_ACTIONS, "{$prefix}.missing_checkout_actions"),
            'duplicate_actions' => Employee::labels(self::DUPLICATE_ACTIONS, "{$prefix}.duplicate_actions"),
            'gps_requirements' => Employee::labels(self::GPS_REQUIREMENTS, "{$prefix}.gps_requirements"),
        ];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'attendance_policy_id');
    }

    public function versions()
    {
        return $this->hasMany(AttendancePolicyVersion::class, 'attendance_policy_id');
    }

    /** Configuration rows (any date) that reference this policy. */
    public function attendanceConfigs()
    {
        return $this->hasMany(EmployeeAttendanceConfig::class, 'attendance_policy_id');
    }

    /** Employees still employed whose configuration in force today uses this policy (for counts). */
    public function currentConfigs()
    {
        return $this->hasMany(EmployeeAttendanceConfig::class, 'attendance_policy_id')
            ->effectiveOn(today())
            ->whereHas('employee', fn ($query) => $query->employed());
    }

    /** Next rule change that has not taken effect yet. */
    public function scheduledVersion()
    {
        return $this->hasOne(AttendancePolicyVersion::class, 'attendance_policy_id')
            ->where('effective_from', '>', today()->toDateString().' 23:59:59')
            ->orderBy('effective_from');
    }
}
