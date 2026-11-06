<?php

namespace App\Console\Commands;

use App\Models\Center;
use App\Support\Hr\HrStaffSync;
use Illuminate\Console\Command;

/**
 * Creates HR departments from the legacy users.department keys and an HR profile for every staff user
 * of the center that has none. users rows are never modified.
 */
class SyncHrEmployeesCommand extends Command
{
    protected $signature = 'hr:sync-employees
                            {--center=* : Center id(s); default is every center}
                            {--apply : Write rows (default is dry-run only)}
                            {--force : Skip confirmation prompt when using --apply}';

    protected $description = 'Bootstrap HR departments and employee profiles from existing staff (dry-run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $centerIds = array_map('intval', (array) $this->option('center'));
        $centers = Center::query()
            ->when($centerIds !== [], fn ($query) => $query->whereIn('id', $centerIds))
            ->orderBy('id')
            ->get(['id', 'title']);

        if ($centers->isEmpty()) {
            $this->warn('No matching centers.');

            return self::SUCCESS;
        }

        if ($apply) {
            $this->warn('APPLY MODE: hr_departments and hr_employees rows will be created. users are not changed.');
            if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }
        }

        foreach ($centers as $center) {
            $plan = HrStaffSync::plan($center->id);
            $this->info("Center #{$center->id} {$center->title}: "
                .count($plan['departments_to_create']).' department(s), '
                .count($plan['employees_to_create']).' employee profile(s) to create');

            foreach ($plan['employees_to_create'] as $row) {
                $this->line("  + user #{$row['user_id']} {$row['name']}".($row['department'] ? " ({$row['department']})" : ''));
            }

            if ($apply) {
                $result = HrStaffSync::apply($plan);
                $this->info("  created {$result['departments_created']} department(s), {$result['employees_created']} profile(s)");
            }
        }

        if (! $apply) {
            $this->comment('Dry run only. Re-run with --apply to write.');
        }

        return self::SUCCESS;
    }
}
