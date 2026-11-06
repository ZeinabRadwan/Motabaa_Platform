<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('parent-uploads:prune-chunks')->hourly();
        $schedule->command('hr:apply-work-assignments')->dailyAt('00:05')->timezone('Asia/Riyadh')->withoutOverlapping();
        $schedule->command('hr:apply-shift-versions')->dailyAt('00:06')->timezone('Asia/Riyadh')->withoutOverlapping();
        $schedule->command('hr:apply-attendance-changes')->dailyAt('00:07')->timezone('Asia/Riyadh')->withoutOverlapping();
        $schedule->command('hr:close-missed-checkouts')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('hr:record-missed-location-checks')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('hr:process-attendance')->hourly()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
