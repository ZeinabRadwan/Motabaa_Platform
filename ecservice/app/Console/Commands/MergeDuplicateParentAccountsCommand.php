<?php

namespace App\Console\Commands;

use App\Support\ParentAccountMergePlanner;
use App\Support\ParentAccountMerger;
use Illuminate\Console\Command;

class MergeDuplicateParentAccountsCommand extends Command
{
    protected $signature = 'parent:merge-duplicates
                            {--apply : Execute high-confidence merges (default is dry-run only)}
                            {--force : Skip confirmation prompt when using --apply}
                            {--approve=* : Admin-reviewed group key (e.g. 253-268) allowed to override name_mismatch only}
                            {--output= : Write JSON report path (default: storage/app/parent-merge-results.json)}';

    protected $description = 'Detect and merge high-confidence duplicate parent-only accounts (dry-run by default)';

    protected $aliases = ['parents:merge-duplicates'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $path = $this->option('output') ?: storage_path('app/parent-merge-results.json');

        if ($apply) {
            $this->warn('APPLY MODE: database will be modified. Ensure you have a recent backup before production use.');
            if (! $this->option('force') && ! $this->confirm('Continue with merges?', false)) {
                $this->comment('Aborted.');

                return self::SUCCESS;
            }
        }

        $plan = ParentAccountMergePlanner::plan($apply, (array) $this->option('approve'));

        $report = $plan;
        if ($apply) {
            $execution = ParentAccountMerger::executePlan($plan);
            $report['execution'] = $execution;
            $report['summary']['merged'] = count($execution['merged'] ?? []);
            $report['summary']['failed'] = count($execution['failed'] ?? []);
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Parent-only accounts scanned: '.$plan['parent_account_count']);
        $this->info('High-confidence merge groups: '.$plan['summary']['would_merge']);
        $this->info('Skipped / review groups: '.$plan['summary']['skipped']);

        if ($apply) {
            $this->info('Groups merged: '.($report['summary']['merged'] ?? 0));
            foreach ($report['execution']['failed'] ?? [] as $failure) {
                $this->error('  [failed, rolled back] group='.$failure['group_key'].' '.$failure['error']);
            }
        } else {
            $this->comment('Dry run only — no database changes. Re-run with --apply after backup and review.');
        }

        $this->line('Report: '.$path);

        foreach ($plan['merge_groups'] as $group) {
            $flags = array_filter([
                ! empty($group['admin_approved']) ? 'admin-approved' : null,
                ! empty($group['email_differs']) ? 'email differs' : null,
                ! empty($group['phone_differs']) ? 'phone differs' : null,
                ! empty($group['linked_students_differ']) ? 'different students' : null,
                ($group['match_source'] ?? null) === ParentAccountMergePlanner::MATCH_SOURCE_ADDITIONAL_PHONE ? 'via additional phone' : null,
            ]);
            $this->line(sprintf(
                '  [merge] group=%s match=%s primary=%d duplicates=[%s] students_after=[%s]%s',
                $group['group_key'],
                $group['match_type'],
                $group['primary_account_id'],
                implode(',', $group['merged_account_ids']),
                implode(',', $group['linked_students_after_primary']),
                $flags ? ' ('.implode('; ', $flags).')' : ''
            ));
            $this->printUsers($group);
        }

        foreach ($plan['skipped_groups'] as $group) {
            $this->line(sprintf(
                '  [skip] group=%s match=%s reasons=%s',
                $group['group_key'],
                $group['match_type'],
                implode(', ', $group['skip_reasons'] ?? [])
            ));
            if (! empty($group['review_notes'])) {
                $this->line('         notes: '.implode(', ', $group['review_notes']));
            }
            $this->printUsers($group);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function printUsers(array $group): void
    {
        foreach ($group['users'] ?? [] as $u) {
            $this->line(sprintf(
                '         #%d name="%s" email=%s phone=%s students=%d',
                $u['id'],
                $u['name'],
                $u['email'] ?? '-',
                $u['phone'] ?? '-',
                $u['linked_student_count']
            ));
        }
    }
}
