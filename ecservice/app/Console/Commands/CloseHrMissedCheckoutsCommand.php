<?php

namespace App\Console\Commands;

use App\Support\Hr\HrElectronicAttendance;
use Illuminate\Console\Command;

/**
 * Electronic attendance sessions still open after their check-out window are closed with the attendance policy's
 * missing check-out action (auto check-out at the shift end, or flagged without a check-out). The attendance page
 * applies the same rule when the employee opens it; this run covers employees who never come back.
 */
class CloseHrMissedCheckoutsCommand extends Command
{
    protected $signature = 'hr:close-missed-checkouts {--center= : Limit to one center id}';

    protected $description = 'Close electronic attendance sessions whose check-out window has ended';

    public function handle(): int
    {
        $center = $this->option('center') !== null ? (int) $this->option('center') : null;
        $closed = HrElectronicAttendance::closeExpiredSessions(now(), $center);

        $this->info("Sessions closed: {$closed}");

        return self::SUCCESS;
    }
}
