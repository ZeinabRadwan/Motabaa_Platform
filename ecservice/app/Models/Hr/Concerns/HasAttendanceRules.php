<?php

namespace App\Models\Hr\Concerns;

use App\Models\Hr\AttendancePolicy;

/**
 * Attendance policy rules shared by hr_attendance_policies (cache of the version in force today) and
 * hr_attendance_policy_versions (history and scheduled changes). Rule names and options: AttendancePolicy.
 */
trait HasAttendanceRules
{
    public function initializeHasAttendanceRules(): void
    {
        $this->mergeCasts([
            'check_in_before_minutes' => 'integer',
            'check_in_after_minutes' => 'integer',
            'check_out_before_minutes' => 'integer',
            'check_out_after_minutes' => 'integer',
            'late_grace_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
            'absence_after_minutes' => 'integer',
            'duplicate_window_minutes' => 'integer',
            'allow_manual_attendance' => 'boolean',
        ]);
    }

    /** @return array<string, mixed> */
    public function ruleValues(): array
    {
        return $this->only(AttendancePolicy::RULE_FIELDS);
    }
}
