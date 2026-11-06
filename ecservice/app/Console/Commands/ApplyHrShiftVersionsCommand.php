<?php

namespace App\Console\Commands;

use App\Support\Hr\HrShiftService;
use Illuminate\Console\Command;

/**
 * Scheduled shift timing changes take effect on their date: this copies the version in force today into
 * the hr_shifts columns (used by lists and lookups). Schedules read the versions directly, so a late run
 * never changes which hours apply on a given day.
 */
class ApplyHrShiftVersionsCommand extends Command
{
    protected $signature = 'hr:apply-shift-versions {--center= : Limit to one center id}';

    protected $description = 'Apply shift timing changes that take effect today';

    public function handle(): int
    {
        $center = $this->option('center');
        $updated = HrShiftService::applyDue($center !== null ? (int) $center : null);

        $this->info("Shifts updated: {$updated}");

        return self::SUCCESS;
    }
}
