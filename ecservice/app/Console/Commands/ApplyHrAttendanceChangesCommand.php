<?php

namespace App\Console\Commands;

use App\Support\Hr\HrAttendancePolicyService;
use App\Support\Hr\HrEmployeeAttendance;
use Illuminate\Console\Command;

/**
 * Scheduled attendance policy rule changes and employee attendance configuration changes take effect on their
 * date: this copies the rows in force today into hr_attendance_policies / hr_employees (used by lists, filters
 * and lookups). Attendance rules are always resolved from the dated rows, so a late run never changes which
 * rules apply on a given day.
 */
class ApplyHrAttendanceChangesCommand extends Command
{
    protected $signature = 'hr:apply-attendance-changes {--center= : Limit to one center id}';

    protected $description = 'Apply attendance policy and employee attendance configuration changes that take effect today';

    public function handle(): int
    {
        $center = $this->option('center') !== null ? (int) $this->option('center') : null;

        $policies = HrAttendancePolicyService::applyDue($center);
        $employees = HrEmployeeAttendance::applyDue($center);

        $this->info("Attendance policies updated: {$policies}");
        $this->info("Employees updated: {$employees}");

        return self::SUCCESS;
    }
}
