<?php

namespace App\Console\Commands;

use App\Support\Hr\HrLocationMonitor;
use Illuminate\Console\Command;

/**
 * Periodic location checks that fell due in open attendance sessions without receiving a location (attendance page
 * closed or suspended, no network) are stored as pending, so the monitoring dashboard shows them. They are never
 * counted as verified and never mark the employee absent.
 */
class RecordHrMissedLocationChecksCommand extends Command
{
    protected $signature = 'hr:record-missed-location-checks {--center= : Limit to one center id}';

    protected $description = 'Store due location checks of open attendance sessions that received no location as pending';

    public function handle(): int
    {
        $center = $this->option('center') !== null ? (int) $this->option('center') : null;
        $stored = HrLocationMonitor::catchUpAll(now(), $center);

        $this->info("Pending checks stored: {$stored}");

        return self::SUCCESS;
    }
}
