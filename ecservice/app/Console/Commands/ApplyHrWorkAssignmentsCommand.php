<?php

namespace App\Console\Commands;

use App\Support\Hr\HrWorkAssignments;
use Illuminate\Console\Command;

/**
 * Scheduled branch transfers and work location changes take effect on their date: this copies the assignment
 * in force today into hr_employees.branch_id / work_location_id (used by lists, filters and branch scoping).
 */
class ApplyHrWorkAssignmentsCommand extends Command
{
    protected $signature = 'hr:apply-work-assignments {--center= : Limit to one center id}';

    protected $description = 'Apply branch transfers and work location changes that take effect today';

    public function handle(): int
    {
        $center = $this->option('center');
        $updated = HrWorkAssignments::applyDue($center !== null ? (int) $center : null);

        $this->info("Employees updated: {$updated}");

        return self::SUCCESS;
    }
}
