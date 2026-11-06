<?php

namespace App\Console\Commands;

use App\Models\Hr\Employee;
use App\Support\Hr\HrAttendanceProcessor;
use Illuminate\Console\Command;

/**
 * Processes the attendance of the last days for every center, so absences, lateness and exceptions appear without
 * waiting for HR to process a payroll period. Attendance records, leave decisions and holiday changes already
 * reprocess the days they affect; this run also picks up schedule changes and days whose check-out window closed.
 */
class ProcessHrAttendanceCommand extends Command
{
    protected $signature = 'hr:process-attendance
        {--center= : Limit to one center id}
        {--days=3 : Number of days to process, ending today}';

    protected $description = 'Process HR attendance (absence, lateness, exceptions) for the last days';

    public function handle(): int
    {
        $days = max(1, min(62, (int) $this->option('days')));
        $centerIds = Employee::query()
            ->when($this->option('center') !== null, fn ($query) => $query->where('center_id', (int) $this->option('center')))
            ->distinct()
            ->pluck('center_id');

        $processed = 0;
        foreach ($centerIds as $centerId) {
            $today = now()->setTimezone(HrAttendanceProcessor::timezone((int) $centerId))->startOfDay();
            $from = $today->copy()->subDays($days - 1)->toDateString();
            Employee::query()
                ->forCenter((int) $centerId)
                ->where(fn ($query) => $query->employed()->orWhereDate('terminated_at', '>=', $from))
                ->orderBy('id')
                ->chunkById(200, function ($employees) use ($centerId, $from, $today, &$processed) {
                    $processed += HrAttendanceProcessor::process((int) $centerId, $employees, $from, $today->toDateString())['processed'];
                });
        }

        $this->info("Attendance days processed: {$processed}");

        return self::SUCCESS;
    }
}
